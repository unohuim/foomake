<?php

namespace App\Navigation;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\User;

/**
 * Resolve tenant-scoped navigation eligibility state.
 */
class NavigationEligibility
{
    /**
     * Return the navigation eligibility state for the given user.
     *
     * @return array<string, bool>
     */
    public function forUser(?User $user): array
    {
        return $this->forTenantId($user?->tenant_id);
    }

    /**
     * Return the navigation eligibility state for the given tenant id.
     *
     * @return array<string, bool>
     */
    public function forTenantId(?int $tenantId): array
    {
        if (! $tenantId) {
            return $this->emptyState();
        }

        return [
            'salesOrdersEnabled' => Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->exists()
                && Item::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_sellable', true)
                    ->exists(),
            'purchaseOrdersEnabled' => Supplier::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->exists()
                && Item::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_purchasable', true)
                    ->exists(),
            'makeOrdersEnabled' => Item::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_manufacturable', true)
                ->exists()
                && Recipe::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->exists(),
        ];
    }

    /**
     * Return an empty eligibility state.
     *
     * @return array<string, bool>
     */
    private function emptyState(): array
    {
        return [
            'salesOrdersEnabled' => false,
            'purchaseOrdersEnabled' => false,
            'makeOrdersEnabled' => false,
        ];
    }
}
