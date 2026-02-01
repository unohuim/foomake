<?php

namespace App\Services\Purchasing;

use App\Models\Supplier;

/**
 * Determine whether a supplier is linked to materials.
 */
interface SupplierDeleteGuard
{
    /**
     * Check if a supplier has linked materials.
     */
    public function isLinkedToMaterials(Supplier $supplier): bool;
}
