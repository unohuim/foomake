<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;

/**
 * Trait HasTenantScope
 */
trait HasTenantScope
{
    /**
     * Boot the tenant scope for the model.
     */
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
