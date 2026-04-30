<?php

namespace App\Services\Purchasing;

use App\Models\Supplier;

/**
 * Default supplier delete guard.
 */
class DefaultSupplierDeleteGuard implements SupplierDeleteGuard
{
    /**
     * Check if a supplier has linked materials.
     */
    public function isLinkedToMaterials(Supplier $supplier): bool
    {
        return $supplier->purchaseOptions()->exists();
    }
}
