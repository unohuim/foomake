<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User checkout context for one recipe version.
 */
class RecipeVersionCheckout extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'recipe_id',
        'recipe_version_id',
        'user_id',
        'checked_out_at',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_out_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    /**
     * Owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Parent recipe identity.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Checked out recipe version.
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    /**
     * Checkout owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
