<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Uom
 *
 * Represents a unit of measure within a category.
 */
class Uom extends Model
{
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'uom_category_id',
        'name',
        'symbol',
        'display_precision',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'display_precision' => 'integer',
    ];

    /**
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(UomCategory::class, 'uom_category_id');
    }

    /**
     * @return HasMany
     */
    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    /**
     * @return HasMany
     */
    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }
}
