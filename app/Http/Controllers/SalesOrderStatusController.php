<?php

namespace App\Http\Controllers;

use App\Actions\Sales\BuildSalesOrderIssuePlanAction;
use App\Actions\Sales\CancelPackedSalesOrderAction;
use App\Actions\Sales\MoveSalesOrderToPackingAction;
use App\Actions\Sales\PackSalesOrderAction;
use App\Actions\Workflows\BuildWorkflowProgressStepsAction;
use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\DeleteOpenSalesOrderTasksAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Http\Requests\Sales\UpdateSalesOrderStatusRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\WorkflowStage;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Handle sales order lifecycle status updates.
 */
class SalesOrderStatusController extends Controller
{
    /**
     * Update the lifecycle status for a sales order.
     */
    public function update(
        UpdateSalesOrderStatusRequest $request,
        SalesOrder $salesOrder,
        BuildSalesOrderIssuePlanAction $buildPlanAction,
        MoveSalesOrderToPackingAction $moveToPackingAction,
        PackSalesOrderAction $packSalesOrderAction,
        CancelPackedSalesOrderAction $cancelPackedSalesOrderAction,
        AssertWorkflowStageTasksCompletedAction $assertWorkflowStageTasksCompletedAction,
        GenerateWorkflowStageTasksAction $generateWorkflowStageTasksAction,
        DeleteOpenSalesOrderTasksAction $deleteOpenSalesOrderTasksAction,
        BuildWorkflowProgressStepsAction $buildWorkflowProgressStepsAction
    ): JsonResponse {
        Gate::authorize('sales-sales-orders-manage');

        $targetStatus = (string) $request->validated('status');
        $resolver = app(ResolveSalesWorkflowStageAction::class);
        $currentStage = $resolver->currentStageForStatus($salesOrder);
        $targetStage = $resolver->activeStageForStatus($salesOrder, $targetStatus);
        $createStageKey = $resolver->stageForStatus($salesOrder, SalesOrder::STATUS_OPEN)?->key ?? 'packing';

        if (! $salesOrder->canTransitionTo($targetStatus)) {
            return response()->json([
                'message' => 'Status transition is not allowed.',
                'errors' => [
                    'status' => ['Status transition is not allowed.'],
                ],
            ], 422);
        }

        try {
            $salesOrder = match (true) {
                $targetStatus === SalesOrder::STATUS_CANCELLED => $this->hasPostedInventoryImpact($salesOrder)
                    ? $cancelPackedSalesOrderAction->execute($salesOrder, $deleteOpenSalesOrderTasksAction)
                    : $this->transitionWithoutInventory(
                        $salesOrder,
                        $targetStatus,
                        $assertWorkflowStageTasksCompletedAction,
                        $generateWorkflowStageTasksAction,
                        $deleteOpenSalesOrderTasksAction,
                        $resolver
                    ),
                $salesOrder->status === SalesOrder::STATUS_DRAFT => $moveToPackingAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $generateWorkflowStageTasksAction,
                    $targetStatus,
                    $createStageKey
                ),
                $currentStage?->is_inventory_effect_stage && $targetStage !== null => $packSalesOrderAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $assertWorkflowStageTasksCompletedAction,
                    $generateWorkflowStageTasksAction,
                    $targetStatus,
                    $targetStage?->key ?? $currentStage?->key ?? 'packing'
                ),
                default => $this->transitionWithoutInventory(
                    $salesOrder,
                    $targetStatus,
                    $assertWorkflowStageTasksCompletedAction,
                    $generateWorkflowStageTasksAction,
                    $deleteOpenSalesOrderTasksAction,
                    $resolver
                ),
            };
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'status' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $salesOrder = $salesOrder->fresh();
        $currentStage = $resolver->currentStageForStatus($salesOrder);
        $progressStage = in_array($salesOrder->status, [
            SalesOrder::STATUS_COMPLETED,
            SalesOrder::STATUS_CANCELLED,
        ], true)
            ? null
            : $resolver->currentStageForStatus($salesOrder);

        return response()->json([
            'data' => [
                'id' => $salesOrder->id,
                'status' => $salesOrder->status,
                'available_status_transitions' => $salesOrder->availableTransitions(),
                'can_edit' => $salesOrder->isEditable(),
                'can_manage_lines' => $salesOrder->allowsLineMutations(),
                'current_stage_tasks' => $this->currentStageTasksData($salesOrder),
                'workflowProgressSteps' => $buildWorkflowProgressStepsAction->execute(
                    (int) $salesOrder->tenant_id,
                    'sales',
                    $progressStage?->id,
                    $progressStage?->key,
                    null,
                    in_array($salesOrder->status, [
                        SalesOrder::STATUS_COMPLETED,
                        SalesOrder::STATUS_CANCELLED,
                    ], true)
                ),
            ],
            'workflow' => $this->workflowPayload($salesOrder),
        ]);
    }

    /**
     * Persist a non-inventory lifecycle transition.
     */
    private function transitionWithoutInventory(
        SalesOrder $salesOrder,
        string $targetStatus,
        AssertWorkflowStageTasksCompletedAction $assertWorkflowStageTasksCompletedAction,
        GenerateWorkflowStageTasksAction $generateWorkflowStageTasksAction,
        DeleteOpenSalesOrderTasksAction $deleteOpenSalesOrderTasksAction,
        ResolveSalesWorkflowStageAction $resolver
    ): SalesOrder
    {
        return DB::transaction(function () use (
            $salesOrder,
            $targetStatus,
            $assertWorkflowStageTasksCompletedAction,
            $generateWorkflowStageTasksAction,
            $deleteOpenSalesOrderTasksAction,
            $resolver
        ): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($targetStatus !== SalesOrder::STATUS_CANCELLED && $resolver->currentStageForStatus($lockedOrder) !== null) {
                $assertWorkflowStageTasksCompletedAction->execute(
                    (int) $lockedOrder->tenant_id,
                    (int) $lockedOrder->id,
                    $resolver->currentStageForStatus($lockedOrder),
                    'Complete all tasks for this stage before moving the sales order forward.'
                );
            }

            $lockedOrder->forceFill(['status' => $targetStatus])->save();

            $targetStage = $resolver->activeStageForStatus($lockedOrder, $targetStatus);

            if ($targetStage !== null) {
                $generateWorkflowStageTasksAction->execute(
                    (int) $lockedOrder->tenant_id,
                    (int) $lockedOrder->id,
                    $targetStage
                );
            }

            if ($targetStatus === SalesOrder::STATUS_CANCELLED) {
                $deleteOpenSalesOrderTasksAction->execute($lockedOrder);
            }

            return $lockedOrder->fresh();
        });
    }

    /**
     * Build the current-stage workflow tasks payload.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function currentStageTasksData(SalesOrder $order): array
    {
        $stage = app(ResolveSalesWorkflowStageAction::class)->currentStageForStatus($order);

        if (! $stage) {
            return [];
        }

        $viewerUserId = auth()->id();

        return Task::query()
            ->with(['assignedTo', 'completedBy'])
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('domain_record_id', $order->id)
            ->where(function ($query) use ($stage): void {
                $query->where('source', Task::SOURCE_MANUAL)
                    ->orWhere(function ($query) use ($stage): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $stage->id);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskData($task, $viewerUserId))
            ->values()
            ->all();
    }

    /**
     * Build workflow task payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function taskData(Task $task, ?int $viewerUserId): array
    {
        return [
            'id' => $task->id,
            'source' => $task->source,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'is_completed' => $task->isCompleted(),
            'can_complete' => ! $task->isCompleted()
                && $viewerUserId !== null
                && (int) $task->assigned_to_user_id === (int) $viewerUserId,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'complete_url' => route('tasks.complete', $task),
        ];
    }

    /**
     * Build the shared workflow action-button payload for a sales order.
     *
     * @return array<string, mixed>
     */
    private function workflowPayload(SalesOrder $salesOrder): array
    {
        $resolver = app(ResolveSalesWorkflowStageAction::class);
        $currentStage = $resolver->currentStageForStatus($salesOrder);
        $displayStage = $resolver->previousActiveStage($salesOrder);
        $actions = [];

        foreach ($salesOrder->availableTransitions() as $status) {
            $stage = $this->workflowActionStage($salesOrder, $status, $resolver);

            $actions[] = [
                'id' => $status,
                'type' => $status,
                'label' => $this->workflowActionLabel($status, $stage),
                'description' => $this->workflowActionDescription($status, $stage),
                'endpoint' => route('sales.orders.status.update', $salesOrder),
                'method' => 'PATCH',
            ];
        }

        $currentLabel = $displayStage?->status_complete_label
            ?? $currentStage?->status_complete_label
            ?? $currentStage?->name
            ?? $salesOrder->status;

        return [
            'status' => $salesOrder->status,
            'status_label' => $currentLabel,
            'display_label' => $currentLabel,
            'currentLabel' => $currentLabel,
            'current_stage_label' => $currentLabel,
            'current_stage' => $currentStage ? [
                'id' => (int) $currentStage->id,
                'workflow_domain_id' => (int) $currentStage->workflow_domain_id,
                'key' => $currentStage->key,
                'name' => $currentStage->name,
                'action_verb' => $currentStage->action_verb,
                'status_complete_label' => $currentStage->status_complete_label,
                'description' => $currentStage->description,
            ] : null,
            'actions' => $actions,
            'header_menu' => [
                'currentLabel' => $currentLabel,
                'options' => $actions,
            ],
        ];
    }

    /**
     * Resolve the label for a sales workflow action.
     */
    private function workflowActionLabel(string $status, ?WorkflowStage $stage): string
    {
        return match ($status) {
            SalesOrder::STATUS_CANCELLED => 'Cancel',
            SalesOrder::STATUS_COMPLETED => 'Complete',
            default => $this->workflowVerb($stage?->action_verb ?: $stage?->name ?: $status),
        };
    }

    /**
     * Resolve the helper copy for a sales workflow action.
     */
    private function workflowActionDescription(string $status, ?WorkflowStage $stage): string
    {
        if (filled($stage?->description)) {
            return (string) $stage->description;
        }

        return match ($status) {
            SalesOrder::STATUS_CANCELLED => 'Cancel this sales order.',
            SalesOrder::STATUS_COMPLETED => 'Mark this sales order complete.',
            default => 'Move this sales order to the next workflow stage.',
        };
    }

    /**
     * Resolve the workflow stage backing a sales-order action.
     */
    private function workflowActionStage(
        SalesOrder $salesOrder,
        string $status,
        ResolveSalesWorkflowStageAction $resolver
    ): ?WorkflowStage {
        if ($salesOrder->status === SalesOrder::STATUS_DRAFT && $status === SalesOrder::STATUS_OPEN) {
            return $resolver->stageForStatus($salesOrder, SalesOrder::STATUS_OPEN);
        }

        if ($status === SalesOrder::STATUS_CANCELLED || $status === SalesOrder::STATUS_COMPLETED) {
            return null;
        }

        return $resolver->nextActiveStage($salesOrder);
    }

    /**
     * Normalize workflow action verbs into sentence case for the UI.
     */
    private function workflowVerb(string $value): string
    {
        return Str::headline(Str::lower(trim($value)));
    }

    /**
     * Determine whether inventory has already been posted for the sales order.
     */
    private function hasPostedInventoryImpact(SalesOrder $salesOrder): bool
    {
        $lineIds = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->id)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return false;
        }

        return StockMove::query()
            ->where('source_type', SalesOrderLine::class)
            ->whereIn('source_id', $lineIds->all())
            ->exists();
    }
}
