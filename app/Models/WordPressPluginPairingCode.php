<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Store a short-lived WordPress plugin pairing request before token exchange.
 */
class WordPressPluginPairingCode extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'plugin_uuid',
        'site_url',
        'site_name',
        'callback_url',
        'code_hash',
        'expires_at',
        'approved_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Hash a raw pairing code for storage or lookup.
     */
    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Get the approved tenant, when approval has happened.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Determine whether the pairing request has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine whether the pairing request has been approved by a tenant admin.
     */
    public function isApproved(): bool
    {
        return $this->tenant_id !== null && $this->approved_at !== null;
    }

    /**
     * Determine whether the pairing request has already been exchanged.
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
