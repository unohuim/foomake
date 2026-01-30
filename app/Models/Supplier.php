<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Supplier
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $company_name
 * @property string|null $url
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $currency_code
 */
class Supplier extends Model
{
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'company_name',
        'url',
        'phone',
        'email',
        'currency_code',
    ];

    /**
     * Get the tenant that owns the supplier.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
