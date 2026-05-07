<?php

namespace App\Actions\Sales;

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
    public function execute(SalesOrder $salesOrder, BuildSalesOrderIssuePlanAction $buildPlanAction): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $buildPlanAction): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== SalesOrder::STATUS_PACKING) {
                throw new DomainException('Status transition is not allowed.');
            }

            $plan = $buildPlanAction->execute($lockedOrder);

            foreach ($plan as $moveData) {
                StockMove::query()->create($moveData);
            }

            $lockedOrder->forceFill([
                'status' => SalesOrder::STATUS_PACKED,
            ])->save();

            return $lockedOrder->fresh();
        });
    }
}

