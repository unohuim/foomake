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
 */
class Tenant extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_name',
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
        static::created(function (Tenant $tenant): void {
            app(EnsureWorkflowDomainsSeededAction::class)->execute();
            app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
        });
    }
}
