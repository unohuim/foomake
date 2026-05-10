<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\Uom\SystemUomCloner;

/**
 * Class TenantObserver
 */
class TenantObserver
{
    /**
     * @var SystemUomCloner
     */
    private SystemUomCloner $cloner;

    /**
     * @param SystemUomCloner $cloner
     */
    public function __construct(SystemUomCloner $cloner)
    {
        $this->cloner = $cloner;
    }

    /**
     * Handle the Tenant "created" event.
     */
    public function created(Tenant $tenant): void
    {
        // Observer ensures defaults are cloned for all tenant creation paths (registration, seeders, factories).
        $this->cloner->cloneForTenant($tenant);
    }
}
