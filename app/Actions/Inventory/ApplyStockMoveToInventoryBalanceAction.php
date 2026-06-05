<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBalance;
use App\Models\StockMove;
use Illuminate\Support\Facades\DB;

/**
 * Apply a posted stock move to the derived per-UoM inventory balance table.
 */
class ApplyStockMoveToInventoryBalanceAction
{
    private const SCALE = 6;

    /**
     * Apply the move quantity to its item/UoM balance.
     */
    public function execute(StockMove $stockMove): void
    {
        if (! $this->shouldAffectBalance($stockMove)) {
            return;
        }

        DB::transaction(function () use ($stockMove): void {
            $balance = InventoryBalance::query()
                ->where('tenant_id', $stockMove->tenant_id)
                ->where('item_id', $stockMove->item_id)
                ->where('uom_id', $stockMove->uom_id)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                InventoryBalance::query()->create([
                    'tenant_id' => $stockMove->tenant_id,
                    'item_id' => $stockMove->item_id,
                    'uom_id' => $stockMove->uom_id,
                    'quantity' => bcadd((string) $stockMove->quantity, '0', self::SCALE),
                ]);

                return;
            }

            $balance->quantity = bcadd((string) $balance->quantity, (string) $stockMove->quantity, self::SCALE);
            $balance->save();
        });
    }

    /**
     * Determine whether the stock move should affect derived on-hand balances.
     */
    private function shouldAffectBalance(StockMove $stockMove): bool
    {
        return $stockMove->status === null || $stockMove->status === 'POSTED';
    }
}
