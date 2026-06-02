<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant-scoped polymorphic note attached to an explicitly supported resource.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $noteable_type
 * @property int $noteable_id
 * @property int $author_user_id
 * @property string $body
 * @property string $visibility
 * @property bool $is_pinned
 * @property Carbon|null $edited_at
 */
class Note extends Model
{
    use HasTenantScope;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'noteable_type',
        'noteable_id',
        'author_user_id',
        'body',
        'visibility',
        'is_pinned',
        'edited_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_pinned' => 'boolean',
        'edited_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this note.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the author who created this note.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * Get the parent resource this note is attached to.
     */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }
}
