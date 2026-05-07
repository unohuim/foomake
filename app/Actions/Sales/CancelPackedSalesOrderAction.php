<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reverse posted packed-order stock moves and cancel the order.
 */
class CancelPackedSalesOrderAction
{
    private const SCALE = 6;

    /**
     * Cancel a packed sales order and append reversing stock moves.
     *
     * @throws DomainException
     */
    public function execute(SalesOrder $salesOrder): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder): SalesOrder {
            $lockedOrder = SalesOrder::query()
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== SalesOrder::STATUS_PACKED) {
                throw new DomainException('Status transition is not allowed.');
            }

            $lineIds = SalesOrderLine::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->pluck('id');

            $moves = StockMove::query()
                ->where('source_type', SalesOrderLine::class)
                ->whereIn('source_id', $lineIds->all() === [] ? [0] : $lineIds->all())
                ->orderBy('id')
                ->get();

            foreach ($moves as $move) {
                StockMove::query()->create([
                    'tenant_id' => $move->tenant_id,
                    'item_id' => $move->item_id,
                    'uom_id' => $move->uom_id,
                    'quantity' => bcsub('0.000000', (string) $move->quantity, self::SCALE),
                    'type' => $move->type,
                    'status' => $move->status,
                    'source_type' => $move->source_type,
                    'source_id' => $move->source_id,
                ]);
            }

            $lockedOrder->forceFill([
                'status' => SalesOrder::STATUS_CANCELLED,
            ])->save();

            return $lockedOrder->fresh();
        });
    }
}
