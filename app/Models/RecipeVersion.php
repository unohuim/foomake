<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned execution template for a recipe parent.
 */
class RecipeVersion extends Model
{
    use HasTenantScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS_APPROVED_LEGACY = 'APPROVED';

    protected $fillable = [
        'tenant_id',
        'recipe_id',
        'version_number',
        'name',
        'output_quantity',
        'recipe_type',
        'status',
        'effective_from',
        'effective_until',
        'approved_at',
        'approved_by_user_id',
        'notes',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:6',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Parent recipe identity.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Legacy publish audit user.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Version-owned ingredient lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RecipeVersionLine::class);
    }

    /**
     * User checkout records for this version.
     */
    public function checkouts(): HasMany
    {
        return $this->hasMany(RecipeVersionCheckout::class);
    }

    /**
     * Normalize a persisted or input lifecycle status.
     */
    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === self::STATUS_APPROVED_LEGACY) {
            return self::STATUS_PUBLISHED;
        }

        return $status;
    }

    /**
     * Determine whether the version is currently published.
     */
    public function isPublished(): bool
    {
        return self::normalizeStatus($this->status) === self::STATUS_PUBLISHED;
    }

    /**
     * Determine whether the version is editable when checked out.
     */
    public function isEditableLifecycle(): bool
    {
        return self::normalizeStatus($this->status) === self::STATUS_DRAFT;
    }

    /**
     * Resolve the formatted display number in x.xx form.
     */
    public function versionNumberDisplay(): string
    {
        return self::formatVersionNumber((int) $this->version_number);
    }

    /**
     * Format a stored centesimal version integer into x.xx.
     */
    public static function formatVersionNumber(int $versionNumber): string
    {
        if ($versionNumber < 100) {
            return sprintf('%d.00', $versionNumber);
        }

        return sprintf('%d.%02d', intdiv($versionNumber, 100), $versionNumber % 100);
    }

    /**
     * Normalize a stored legacy version integer for sorting/incrementing.
     */
    public static function normalizeVersionNumberForOrdering(int $versionNumber): int
    {
        if ($versionNumber < 100) {
            return $versionNumber * 100;
        }

        return $versionNumber;
    }
}
