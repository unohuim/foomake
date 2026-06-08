<?php

namespace App\Http\Controllers;

use App\Actions\Sales\BuildSalesOrderIssuePlanAction;
use App\Actions\Sales\CancelPackedSalesOrderAction;
use App\Actions\Sales\MoveSalesOrderToPackingAction;
use App\Actions\Sales\PackSalesOrderAction;
use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\DeleteOpenSalesOrderTasksAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Http\Requests\Sales\UpdateSalesOrderStatusRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Task;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
        DeleteOpenSalesOrderTasksAction $deleteOpenSalesOrderTasksAction
    ): JsonResponse {
        Gate::authorize('sales-sales-orders-manage');

        $targetStatus = (string) $request->validated('status');
        $resolver = app(ResolveSalesWorkflowStageAction::class);
        $targetStage = $resolver->activeStageForStatus($salesOrder, $targetStatus);
        $packedStage = $resolver->stageForStatus($salesOrder, SalesOrder::STATUS_PACKED);

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
                $targetStatus === SalesOrder::STATUS_PACKED && $packedStage?->is_inventory_effect_stage => $packSalesOrderAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $assertWorkflowStageTasksCompletedAction,
                    $generateWorkflowStageTasksAction,
                    $targetStatus,
                    $packedStage->key
                ),
                $targetStatus !== SalesOrder::STATUS_PACKING
                    && $targetStage !== null
                    && $targetStage->is_inventory_effect_stage => $packSalesOrderAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $assertWorkflowStageTasksCompletedAction,
                    $generateWorkflowStageTasksAction,
                    $targetStatus,
                    $targetStage->key
                ),
                $salesOrder->status === SalesOrder::STATUS_OPEN && $targetStage !== null => $moveToPackingAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $generateWorkflowStageTasksAction,
                    $targetStatus,
                    $targetStage->key
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

        return response()->json([
            'data' => [
                'id' => $salesOrder->id,
                'status' => $salesOrder->status,
                'available_status_transitions' => $salesOrder->availableTransitions(),
                'can_edit' => $salesOrder->isEditable(),
                'can_manage_lines' => $salesOrder->allowsLineMutations(),
                'current_stage_tasks' => $this->currentStageTasksData($salesOrder),
            ],
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
