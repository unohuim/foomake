<?php

namespace App\Actions\Inventory;

use App\Models\InventoryCount;
use App\Models\StockMove;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Post an inventory count and write variance adjustments to the ledger.
 */
class PostInventoryCountAction
{
    /**
     * Post the inventory count and create variance stock moves.
     *
     * @param InventoryCount $inventoryCount
     * @param int $postedByUserId
     * @return InventoryCount
     *
     * @throws DomainException
     */
    public function execute(InventoryCount $inventoryCount, int $postedByUserId): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $postedByUserId): InventoryCount {
            $lockedCount = InventoryCount::query()
                ->whereKey($inventoryCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count already posted.');
            }

            $lines = $lockedCount->lines()->with('item')->get();

            if ($lines->isEmpty()) {
                throw new DomainException('Inventory count must have at least one line.');
            }

            $countedByItem = [];
            $itemsById = [];

            foreach ($lines as $line) {
                if ((int) $line->tenant_id !== (int) $lockedCount->tenant_id) {
                    throw new DomainException('Inventory count line tenant mismatch.');
                }

                if ($line->item === null || (int) $line->item->tenant_id !== (int) $lockedCount->tenant_id) {
                    throw new DomainException('Inventory count line item tenant mismatch.');
                }

                $itemsById[$line->item_id] = $line->item;

                $countedByItem[$line->item_id] = ($countedByItem[$line->item_id] ?? BigDecimal::zero())
                    ->plus(BigDecimal::of($line->counted_quantity));
            }

            foreach ($countedByItem as $itemId => $countedQuantity) {
                $item = $itemsById[$itemId];

                $onHand = BigDecimal::of($item->onHandQuantity());

                $variance = $countedQuantity
                    ->minus($onHand)
                    ->toScale(6, RoundingMode::HALF_UP);

                if ($variance->isZero()) {
                    continue;
                }

                StockMove::create([
                    'tenant_id' => $lockedCount->tenant_id,
                    'item_id' => $item->id,
                    'uom_id' => $item->base_uom_id,
                    'quantity' => $variance->__toString(),
                    'type' => 'inventory_count_adjustment',
                    'source_type' => InventoryCount::class,
                    'source_id' => $lockedCount->id,
                ]);
            }

            $lockedCount->posted_at = now();
            $lockedCount->posted_by_user_id = $postedByUserId;
            $lockedCount->save();

            return $lockedCount;
        });
    }
}
