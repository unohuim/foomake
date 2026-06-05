<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\Uom;
use App\Support\Uom\UomConversionPathResolver;

/**
 * Validate conversion coverage from existing balances into a target item base UoM.
 */
class CanConvertInventoryBalancesToUomAction
{
    public function __construct(
        private readonly UomConversionPathResolver $conversionPathResolver
    ) {
    }

    /**
     * Determine whether every non-zero balance can convert to the target UoM.
     */
    public function execute(Item $item, Uom $targetUom): bool
    {
        if ($item->baseUom === null) {
            return false;
        }

        if (
            (int) $item->base_uom_id !== (int) $targetUom->id
            && ! $this->conversionPathResolver->canResolve(
                (int) $item->tenant_id,
                (int) $item->id,
                $item->baseUom,
                $targetUom,
                UomConversionPathResolver::PRECEDENCE_ITEM_FIRST
            )
        ) {
            return false;
        }

        $balances = InventoryBalance::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('item_id', $item->id)
            ->with('uom')
            ->get();

        foreach ($balances as $balance) {
            if (bccomp((string) $balance->quantity, '0.000000', 6) === 0) {
                continue;
            }

            if ((int) $balance->uom_id === (int) $targetUom->id) {
                continue;
            }

            if ($balance->uom === null) {
                return false;
            }

            if (! $this->conversionPathResolver->canResolve(
                (int) $item->tenant_id,
                (int) $item->id,
                $balance->uom,
                $targetUom,
                UomConversionPathResolver::PRECEDENCE_ITEM_FIRST
            )) {
                return false;
            }
        }

        return true;
    }
}
