<?php

namespace App\Actions\Sales;

use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Actions\Workflows\ResolveSalesWorkflowStageAction;
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
        GenerateWorkflowStageTasksAction $generateWorkflowStageTasksAction,
        string $targetStatus,
        string $targetStageKey
    ): SalesOrder {
        return DB::transaction(function () use (
            $salesOrder,
            $buildPlanAction,
            $generateWorkflowStageTasksAction,
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
