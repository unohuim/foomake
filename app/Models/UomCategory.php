<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class UomCategory
 *
 * Represents a unit-of-measure category.
 */
class UomCategory extends Model
{
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
    ];

    /**
     * @return HasMany
     */
    public function uoms(): HasMany
    {
        return $this->hasMany(Uom::class);
    }
}
