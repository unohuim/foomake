<?php

namespace App\Actions\Sales;

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
    public function execute(SalesOrder $salesOrder, BuildSalesOrderIssuePlanAction $buildPlanAction): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $buildPlanAction): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== SalesOrder::STATUS_OPEN) {
                throw new DomainException('Status transition is not allowed.');
            }

            $buildPlanAction->execute($lockedOrder);

            $lockedOrder->forceFill([
                'status' => SalesOrder::STATUS_PACKING,
            ])->save();

            return $lockedOrder->fresh();
        });
    }
}

