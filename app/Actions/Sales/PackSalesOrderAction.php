<?php

namespace App\Actions\Sales;

use App\Actions\Workflows\AssertSalesOrderStageTasksCompletedAction;
use App\Actions\Workflows\GenerateSalesOrderWorkflowTasksAction;
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
        AssertSalesOrderStageTasksCompletedAction $assertStageTasksCompletedAction,
        GenerateSalesOrderWorkflowTasksAction $generateWorkflowTasksAction,
        string $targetStatus,
        string $targetStageKey
    ): SalesOrder {
        return DB::transaction(function () use (
            $salesOrder,
            $buildPlanAction,
            $assertStageTasksCompletedAction,
            $generateWorkflowTasksAction,
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

            if ($lockedOrder->status !== SalesOrder::STATUS_OPEN) {
                $assertStageTasksCompletedAction->execute($lockedOrder);
            }

            $plan = $buildPlanAction->execute($lockedOrder);

            foreach ($plan as $moveData) {
                StockMove::query()->create($moveData);
            }

            $lockedOrder->forceFill([
                'status' => $targetStatus,
            ])->save();

            $generateWorkflowTasksAction->execute($lockedOrder, $targetStageKey);

            return $lockedOrder->fresh();
        });
    }
}
