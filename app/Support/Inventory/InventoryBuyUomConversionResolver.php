<?php

namespace App\Support\Inventory;

use App\Models\ItemPurchaseOption;
use App\Support\Uom\UomConversionPathResolver;

/**
 * Resolve open purchase-order package quantities into item base UoM quantities.
 */
class InventoryBuyUomConversionResolver
{
    private const SCALE = 6;

    public function __construct(
        private readonly UomConversionPathResolver $conversionPathResolver
    ) {
    }

    /**
     * Convert package count into the purchase option item's base UoM quantity.
     */
    public function baseQuantityFor(ItemPurchaseOption $option, string $packCount): ?string
    {
        $item = $option->item;
        $packUom = $option->packUom;
        $baseUom = $item?->baseUom;

        if (! $item || ! $packUom || ! $baseUom) {
            return null;
        }

        if ((int) $option->tenant_id !== (int) $item->tenant_id) {
            return null;
        }

        if (bccomp($packCount, $this->zero(), self::SCALE) !== 1) {
            return null;
        }

        $packQuantity = bcadd((string) $option->pack_quantity, '0', self::SCALE);

        if (bccomp($packQuantity, $this->zero(), self::SCALE) !== 1) {
            return null;
        }

        $packageQuantity = bcmul($packQuantity, $packCount, self::SCALE);
        return $this->conversionPathResolver->convertQuantity(
            (int) $option->tenant_id,
            (int) $item->id,
            $packUom,
            $baseUom,
            $packageQuantity,
            UomConversionPathResolver::PRECEDENCE_GENERAL_FIRST
        );
    }

    /**
     * Return canonical zero quantity.
     */
    private function zero(): string
    {
        return '0.000000';
    }
}
