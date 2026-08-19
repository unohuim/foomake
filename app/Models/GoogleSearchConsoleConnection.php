<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Store tenant-scoped Google Search Console OAuth credentials.
 */
class GoogleSearchConsoleConnection extends Model
{
    use HasTenantScope;

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'site_url',
        'scopes',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
        'connected_at',
        'last_verified_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Determine whether Search Console can be accessed without user presence.
     */
    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && filled($this->refresh_token);
    }
}
