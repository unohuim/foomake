<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * First-party attribution record for anonymous public-page visitors.
 *
 * @property int $id
 * @property string $visitor_id
 * @property int|null $user_id
 * @property string|null $first_landing_page
 * @property string|null $first_referrer
 * @property string|null $first_utm_source
 * @property string|null $first_utm_medium
 * @property string|null $first_utm_campaign
 * @property string|null $first_utm_content
 * @property string|null $first_utm_term
 * @property string|null $latest_landing_page
 * @property string|null $latest_referrer
 * @property string|null $latest_utm_source
 * @property string|null $latest_utm_medium
 * @property string|null $latest_utm_campaign
 * @property string|null $latest_utm_content
 * @property string|null $latest_utm_term
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $converted_at
 */
class VisitorAttribution extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'visitor_id',
        'user_id',
        'first_landing_page',
        'first_referrer',
        'first_utm_source',
        'first_utm_medium',
        'first_utm_campaign',
        'first_utm_content',
        'first_utm_term',
        'latest_landing_page',
        'latest_referrer',
        'latest_utm_source',
        'latest_utm_medium',
        'latest_utm_campaign',
        'latest_utm_content',
        'latest_utm_term',
        'first_seen_at',
        'last_seen_at',
        'converted_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    /**
     * Get the user this visitor converted into, when applicable.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
