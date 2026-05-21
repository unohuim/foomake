<?php

namespace App\Support\Inventory;

use App\Models\Item;

/**
 * Resolve inventory availability values for a single item.
 */
class InventoryAvailabilityCalculator
{
    /**
     * @param InventoryAvailabilityIndexReadModel $readModel
     */
    public function __construct(
        private readonly InventoryAvailabilityIndexReadModel $readModel
    ) {
    }

    /**
     * Build the availability payload for one tenant-scoped item.
     *
     * @return array<string, mixed>
     */
    public function forItem(Item $item): array
    {
        return $this->readModel->rowForItem($item);
    }
}
