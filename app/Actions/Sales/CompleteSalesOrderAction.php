<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Complete a sales order and post its inventory impact to the stock ledger.
 */
class CompleteSalesOrderAction
{
    private const SCALE = 6;

    /**
     * Complete the sales order and create one issue stock move per line.
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

            if ($lockedOrder->status !== SalesOrder::STATUS_OPEN) {
                throw new DomainException('Status transition is not allowed.');
            }

            $lines = $lockedOrder->lines()->with('item')->get();

            foreach ($lines as $line) {
                $this->createStockMoveForLine($lockedOrder, $line);
            }

            $lockedOrder->forceFill([
                'status' => SalesOrder::STATUS_COMPLETED,
            ])->save();

            return $lockedOrder->fresh();
        });
    }

    /**
     * Create the issue stock move for one sales order line.
     *
     * @throws DomainException
     */
    protected function createStockMoveForLine(SalesOrder $salesOrder, SalesOrderLine $line): void
    {
        if ((int) $line->tenant_id !== (int) $salesOrder->tenant_id) {
            throw new DomainException('Sales order line tenant mismatch.');
        }

        if ($line->item === null || (int) $line->item->tenant_id !== (int) $salesOrder->tenant_id) {
            throw new DomainException('Sales order line item is invalid.');
        }

        $existingStockMove = StockMove::query()
            ->where('source_type', SalesOrderLine::class)
            ->where('source_id', $line->id)
            ->exists();

        if ($existingStockMove) {
            throw new DomainException('Sales order line already has a stock move.');
        }

        $stockMove = StockMove::query()->create([
            'tenant_id' => $salesOrder->tenant_id,
            'item_id' => $line->item_id,
            'uom_id' => $line->item->base_uom_id,
            'quantity' => $this->issueQuantity((string) $line->quantity),
            'type' => 'issue',
            'status' => 'POSTED',
            'source_type' => SalesOrderLine::class,
            'source_id' => $line->id,
        ]);

        $this->afterStockMoveCreated($stockMove, $salesOrder, $line);
    }

    /**
     * Run any follow-up behavior immediately after a stock move is created.
     *
     * This exists as the smallest test seam for exercising transaction rollback
     * while keeping the production completion transaction intact.
     */
    protected function afterStockMoveCreated(
        StockMove $stockMove,
        SalesOrder $salesOrder,
        SalesOrderLine $line
    ): void {
        unset($stockMove, $salesOrder, $line);
    }

    /**
     * Convert a positive line quantity into a signed issue quantity.
     */
    protected function issueQuantity(string $quantity): string
    {
        return bcsub('0.000000', bcadd($quantity, '0', self::SCALE), self::SCALE);
    }
}
