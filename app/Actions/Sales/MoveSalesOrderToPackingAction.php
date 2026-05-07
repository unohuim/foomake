<?php

namespace App\Actions\Sales;

use App\Actions\Workflows\GenerateSalesOrderWorkflowTasksAction;
use App\Models\SalesOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Validate a sales order for packing and transition it to PACKING.
 */
class MoveSalesOrderToPackingAction
{
    /**
     * Validate availability and move the order from OPEN to PACKING.
     *
     * @throws DomainException
     */
    public function execute(
        SalesOrder $salesOrder,
        BuildSalesOrderIssuePlanAction $buildPlanAction,
        GenerateSalesOrderWorkflowTasksAction $generateWorkflowTasksAction,
        string $targetStatus,
        string $targetStageKey
    ): SalesOrder {
        return DB::transaction(function () use (
            $salesOrder,
            $buildPlanAction,
            $generateWorkflowTasksAction,
            $targetStatus,
            $targetStageKey
        ): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->status !== SalesOrder::STATUS_OPEN
                || ! $lockedOrder->canTransitionTo($targetStatus)
            ) {
                throw new DomainException('Status transition is not allowed.');
            }

            $buildPlanAction->execute($lockedOrder);

            $lockedOrder->forceFill([
                'status' => $targetStatus,
            ])->save();

            $generateWorkflowTasksAction->execute($lockedOrder, $targetStageKey);

            return $lockedOrder->fresh();
        });
    }
}
