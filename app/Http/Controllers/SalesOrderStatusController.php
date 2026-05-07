<?php

namespace App\Http\Controllers;

use App\Actions\Sales\BuildSalesOrderIssuePlanAction;
use App\Actions\Sales\CancelPackedSalesOrderAction;
use App\Actions\Sales\MoveSalesOrderToPackingAction;
use App\Actions\Sales\PackSalesOrderAction;
use App\Actions\Workflows\AssertSalesOrderStageTasksCompletedAction;
use App\Actions\Workflows\DeleteOpenSalesOrderTasksAction;
use App\Actions\Workflows\GenerateSalesOrderWorkflowTasksAction;
use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Http\Requests\Sales\UpdateSalesOrderStatusRequest;
use App\Models\SalesOrder;
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
        AssertSalesOrderStageTasksCompletedAction $assertStageTasksCompletedAction,
        GenerateSalesOrderWorkflowTasksAction $generateWorkflowTasksAction,
        DeleteOpenSalesOrderTasksAction $deleteOpenSalesOrderTasksAction
    ): JsonResponse {
        Gate::authorize('sales-sales-orders-manage');

        $targetStatus = (string) $request->validated('status');
        $resolver = app(ResolveSalesWorkflowStageAction::class);
        $targetStage = $resolver->activeStageForStatus($salesOrder, $targetStatus);

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
                $targetStatus === SalesOrder::STATUS_CANCELLED => $salesOrder->status === SalesOrder::STATUS_PACKED
                    ? $cancelPackedSalesOrderAction->execute($salesOrder, $deleteOpenSalesOrderTasksAction)
                    : $this->transitionWithoutInventory(
                        $salesOrder,
                        $targetStatus,
                        $assertStageTasksCompletedAction,
                        $generateWorkflowTasksAction,
                        $deleteOpenSalesOrderTasksAction,
                        $resolver
                    ),
                $targetStage !== null && $targetStage->key === 'packed' => $packSalesOrderAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $assertStageTasksCompletedAction,
                    $generateWorkflowTasksAction,
                    $targetStatus,
                    $targetStage->key
                ),
                $salesOrder->status === SalesOrder::STATUS_OPEN && $targetStage !== null => $moveToPackingAction->execute(
                    $salesOrder,
                    $buildPlanAction,
                    $generateWorkflowTasksAction,
                    $targetStatus,
                    $targetStage->key
                ),
                default => $this->transitionWithoutInventory(
                    $salesOrder,
                    $targetStatus,
                    $assertStageTasksCompletedAction,
                    $generateWorkflowTasksAction,
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
        AssertSalesOrderStageTasksCompletedAction $assertStageTasksCompletedAction,
        GenerateSalesOrderWorkflowTasksAction $generateWorkflowTasksAction,
        DeleteOpenSalesOrderTasksAction $deleteOpenSalesOrderTasksAction,
        ResolveSalesWorkflowStageAction $resolver
    ): SalesOrder
    {
        return DB::transaction(function () use (
            $salesOrder,
            $targetStatus,
            $assertStageTasksCompletedAction,
            $generateWorkflowTasksAction,
            $deleteOpenSalesOrderTasksAction,
            $resolver
        ): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($targetStatus !== SalesOrder::STATUS_CANCELLED && $resolver->currentStageForStatus($lockedOrder) !== null) {
                $assertStageTasksCompletedAction->execute($lockedOrder);
            }

            $lockedOrder->forceFill(['status' => $targetStatus])->save();

            $targetStage = $resolver->activeStageForStatus($lockedOrder, $targetStatus);

            if ($targetStage !== null) {
                $generateWorkflowTasksAction->execute($lockedOrder, $targetStage->key);
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
            ->where('workflow_stage_id', $stage->id)
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
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'title' => $task->title,
            'description' => $task->description,
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
}
