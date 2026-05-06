<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Store tenant-scoped external product source connector state.
 */
class ExternalProductSourceConnection extends Model
{
    use HasTenantScope;

    public const SOURCE_WOOCOMMERCE = 'woocommerce';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'tenant_id',
        'source',
        'connection_label',
        'is_connected',
        'connected_at',
        'store_url',
        'consumer_key',
        'consumer_secret',
        'status',
        'last_verified_at',
        'last_error',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'connected_at' => 'datetime',
        'store_url' => 'encrypted',
        'consumer_key' => 'encrypted',
        'consumer_secret' => 'encrypted',
        'last_verified_at' => 'datetime',
    ];

    /**
     * Get the owning tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Determine whether the connection is usable for previews/imports.
     */
    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && filled($this->store_url)
            && filled($this->consumer_key)
            && filled($this->consumer_secret);
    }
}
