<?php

namespace App\Actions\Inventory;

use App\Models\ItemPurchaseOption;
use App\Models\StockMove;
use App\Support\Uom\UomConversionPathResolver;
use DomainException;
use Illuminate\Support\Facades\Auth;

class ReceivePurchaseOptionAction
{
    private const SCALE = 6;

    public function __construct(
        private readonly ?UomConversionPathResolver $conversionPathResolver = null
    ) {
    }

    /**
     * Receive inventory for a purchase option and create a receipt stock move.
     */
    public function execute(ItemPurchaseOption $option, string $packCount): StockMove
    {
        $baseQuantity = $this->baseQuantityFor($option, $packCount);

        return StockMove::create([
            'tenant_id' => $option->tenant_id,
            'item_id' => $option->item_id,
            'uom_id' => $option->item->base_uom_id,
            'quantity' => $baseQuantity,
            'type' => 'receipt',
            'status' => 'POSTED',
        ]);
    }

    /**
     * Resolve a received package count into the item's base UoM quantity.
     */
    public function baseQuantityFor(ItemPurchaseOption $option, string $packCount): string
    {
        $this->ensureValidPackCount($packCount);
        $this->ensureAuthenticatedTenant($option);
        $this->ensureOptionTenantMatchesItem($option);

        $packQuantity = (string) $option->pack_quantity;

        if (bccomp($packQuantity, '0', self::SCALE) <= 0) {
            throw new DomainException('Pack quantity must be greater than zero.');
        }

        $totalPackQuantity = bcmul($packQuantity, $packCount, self::SCALE);

        return $this->convertToBaseUom($option, $totalPackQuantity);
    }

    /**
     * Ensure pack count is greater than zero.
     */
    private function ensureValidPackCount(string $packCount): void
    {
        if (bccomp($packCount, '0', self::SCALE) <= 0) {
            throw new DomainException('Pack count must be greater than zero.');
        }
    }

    /**
     * Ensure the authenticated tenant matches the option tenant when authenticated.
     */
    private function ensureAuthenticatedTenant(ItemPurchaseOption $option): void
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        if ($user && $user->tenant_id !== $option->tenant_id) {
            throw new DomainException('Tenant mismatch for purchase option.');
        }
    }

    /**
     * Ensure the purchase option tenant matches the item tenant.
     */
    private function ensureOptionTenantMatchesItem(ItemPurchaseOption $option): void
    {
        $item = $option->item;

        if (! $item) {
            throw new DomainException('Purchase option must reference an item.');
        }

        if ($option->tenant_id !== $item->tenant_id) {
            throw new DomainException('Purchase option tenant must match item tenant.');
        }
    }

    /**
     * Convert a quantity from pack UoM to item base UoM.
     */
    private function convertToBaseUom(ItemPurchaseOption $option, string $quantity): string
    {
        $item = $option->item;
        $packUom = $option->packUom;
        $baseUom = $item->baseUom;

        if (! $packUom || ! $baseUom) {
            throw new DomainException('Missing required unit of measure.');
        }

        if ($packUom->id === $baseUom->id) {
            return $quantity;
        }

        $convertedQuantity = $this->resolver()->convertQuantity(
            (int) $option->tenant_id,
            (int) $item->id,
            $packUom,
            $baseUom,
            $quantity,
            UomConversionPathResolver::PRECEDENCE_ITEM_FIRST
        );

        if ($convertedQuantity === null) {
            throw new DomainException('Missing required unit conversion.');
        }

        return $convertedQuantity;
    }

    /**
     * Resolve the conversion path resolver while preserving simple manual construction in tests.
     */
    private function resolver(): UomConversionPathResolver
    {
        return $this->conversionPathResolver ?? app(UomConversionPathResolver::class);
    }
}
