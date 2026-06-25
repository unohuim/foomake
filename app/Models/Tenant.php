<?php

namespace App\Models;

use App\Actions\Workflows\EnsureWorkflowDomainsSeededAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Tenant
 *
 * @property int $id
 * @property string $tenant_name
 * @property string|null $currency_code
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $billing_exempt_until
 * @property string|null $billing_provider
 * @property string|null $billing_provider_customer_id
 * @property string|null $billing_provider_subscription_id
 * @property string|null $billing_subscription_status
 * @property \Illuminate\Support\Carbon|null $billing_subscription_ends_at
 */
class Tenant extends Model
{
    use HasFactory;

    public const BILLING_SUBSCRIPTION_STATUS_ACTIVE = 'active';
    public const BILLING_SUBSCRIPTION_STATUS_TRIALING = 'trialing';
    public const BILLING_SUBSCRIPTION_STATUS_INCOMPLETE = 'incomplete';
    public const BILLING_SUBSCRIPTION_STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';
    public const BILLING_SUBSCRIPTION_STATUS_PAST_DUE = 'past_due';
    public const BILLING_SUBSCRIPTION_STATUS_CANCELED = 'canceled';
    public const BILLING_SUBSCRIPTION_STATUS_UNPAID = 'unpaid';
    public const BILLING_SUBSCRIPTION_STATUS_PAUSED = 'paused';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_name',
        'currency_code',
        'trial_ends_at',
        'billing_exempt_until',
        'billing_provider',
        'billing_provider_customer_id',
        'billing_provider_subscription_id',
        'billing_subscription_status',
        'billing_subscription_ends_at',
    ];

    /**
     * Get the users for the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Boot the tenant model hooks.
     */
    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->trial_ends_at ??= now()->addDays(7);
        });

        static::created(function (Tenant $tenant): void {
            app(EnsureWorkflowDomainsSeededAction::class)->execute();
            app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'billing_exempt_until' => 'datetime',
            'billing_subscription_ends_at' => 'datetime',
        ];
    }
}
