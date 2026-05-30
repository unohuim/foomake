<?php

namespace App\Actions\Sales;

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
use App\Models\SalesOrder;
use App\Models\StockMove;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Consume inventory and transition a sales order from PACKING to PACKED.
 */
class PackSalesOrderAction
{
    /**
     * Post issue stock moves and move the order to PACKED.
     *
     * @throws DomainException
     */
    public function execute(
        SalesOrder $salesOrder,
        BuildSalesOrderIssuePlanAction $buildPlanAction,
        AssertWorkflowStageTasksCompletedAction $assertWorkflowStageTasksCompletedAction,
        GenerateWorkflowStageTasksAction $generateWorkflowStageTasksAction,
        string $targetStatus,
        string $targetStageKey
    ): SalesOrder {
        return DB::transaction(function () use (
            $salesOrder,
            $buildPlanAction,
            $assertWorkflowStageTasksCompletedAction,
            $generateWorkflowStageTasksAction,
            $targetStatus,
            $targetStageKey
        ): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedOrder->canTransitionTo($targetStatus)) {
                throw new DomainException('Status transition is not allowed.');
            }

            $currentStage = null;

            if ($lockedOrder->status !== SalesOrder::STATUS_OPEN) {
                $currentStage = app(ResolveSalesWorkflowStageAction::class)->currentStageForStatus($lockedOrder);

                $assertWorkflowStageTasksCompletedAction->execute(
                    (int) $lockedOrder->tenant_id,
                    (int) $lockedOrder->id,
                    $currentStage,
                    'Complete all tasks for this stage before moving the sales order forward.'
                );
            }

            $plan = $buildPlanAction->execute($lockedOrder);

            foreach ($plan as $moveData) {
                StockMove::query()->create($moveData);
            }

            $lockedOrder->forceFill([
                'status' => $targetStatus,
            ])->save();

            if ($currentStage?->key === $targetStageKey) {
                return $lockedOrder->fresh();
            }

            $stage = app(ResolveSalesWorkflowStageAction::class)->execute($lockedOrder, $targetStageKey);

            $generateWorkflowStageTasksAction->execute(
                (int) $lockedOrder->tenant_id,
                (int) $lockedOrder->id,
                $stage
            );

            return $lockedOrder->fresh();
        });
    }
}
