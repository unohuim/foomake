<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $counted_at
 * @property Carbon|null $posted_at
 * @property int|null $posted_by_user_id
 * @property string|null $notes
 */
class InventoryCount extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'counted_at',
        'posted_at',
        'posted_by_user_id',
        'notes',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    /**
     * @return BelongsTo
     */
    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /**
     * @return MorphMany
     */
    public function stockMoves(): MorphMany
    {
        return $this->morphMany(StockMove::class, 'source');
    }

    /**
     * @return string
     */
    public function getStatusAttribute(): string
    {
        return $this->posted_at === null ? 'draft' : 'posted';
    }
}
