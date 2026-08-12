<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Store a tenant-scoped FooMake token connection for an installed WordPress plugin.
 */
class WordPressPluginConnection extends Model
{
    use HasTenantScope;

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_REVOKED = 'revoked';

    /**
     * @var string
     */
    protected $table = 'wordpress_plugin_connections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'plugin_uuid',
        'site_url',
        'site_name',
        'status',
        'access_token_hash',
        'site_access_token',
        'last_seen_at',
        'connected_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'connected_at' => 'datetime',
            'revoked_at' => 'datetime',
            'site_access_token' => 'encrypted',
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
     * Determine whether the plugin token is currently usable.
     */
    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && filled($this->access_token_hash)
            && $this->revoked_at === null;
    }

    /**
     * Determine whether FooMake can call the paired WordPress plugin.
     */
    public function canServeImports(): bool
    {
        return $this->isConnected() && filled($this->site_access_token);
    }
}
