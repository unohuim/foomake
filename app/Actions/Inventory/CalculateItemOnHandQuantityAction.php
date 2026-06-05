<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Support\Uom\UomConversionPathResolver;
use DomainException;

/**
 * Calculate item on-hand quantity from derived per-UoM balances.
 */
class CalculateItemOnHandQuantityAction
{
    private const SCALE = 6;

    public function __construct(
        private readonly UomConversionPathResolver $conversionPathResolver
    ) {
    }

    /**
     * Return on-hand quantity converted into the item's current base UoM.
     *
     * @throws DomainException
     */
    public function execute(Item $item): string
    {
        $baseUom = $item->baseUom;

        if ($baseUom === null) {
            throw new DomainException('Item base UoM is required to calculate inventory.');
        }

        $total = '0.000000';

        $balances = InventoryBalance::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('item_id', $item->id)
            ->with('uom')
            ->get();

        foreach ($balances as $balance) {
            if ($balance->uom === null) {
                throw new DomainException('Inventory balance UoM is required to calculate inventory.');
            }

            $quantity = (string) $balance->quantity;

            if ((int) $balance->uom_id !== (int) $baseUom->id) {
                $quantity = $this->conversionPathResolver->convertQuantity(
                    (int) $item->tenant_id,
                    (int) $item->id,
                    $balance->uom,
                    $baseUom,
                    $quantity,
                    UomConversionPathResolver::PRECEDENCE_ITEM_FIRST
                );

                if ($quantity === null) {
                    throw new DomainException('Inventory balance cannot be converted to the item base UoM.');
                }
            }

            $total = bcadd($total, $quantity, self::SCALE);
        }

        return $total;
    }
}
