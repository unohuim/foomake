<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Store tenant-scoped external customer identities mapped to local customers.
 */
class ExternalCustomerMapping extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'source',
        'external_customer_id',
    ];

    /**
     * Get the owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the mapped local customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
