<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class UomCategory
 *
 * Represents a unit-of-measure category.
 */
class UomCategory extends Model
{
    protected $fillable = [
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
