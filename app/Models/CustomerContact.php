<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class CustomerContact
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $role
 * @property bool $is_primary
 */
class CustomerContact extends Model
{
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'is_primary',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the tenant that owns the contact.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the customer that owns the contact.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sales orders using this contact.
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'contact_id');
    }

    /**
     * Get the full display name for the contact.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
