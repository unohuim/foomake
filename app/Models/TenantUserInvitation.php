<?php

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TenantUserInvitation
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $email
 * @property int $role_id
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_user_id
 */
class TenantUserInvitation extends Model
{
    use HasFactory;
    use HasTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'invited_by_user_id',
        'email',
        'role_id',
        'token',
        'expires_at',
        'revoked_at',
        'accepted_at',
        'accepted_user_id',
    ];

    /**
     * Get the tenant that owns this invitation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who created this invitation.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Get the selected role for the invited member.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user who accepted this invitation.
     */
    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    /**
     * Determine whether the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine whether the invitation has already been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null || $this->accepted_user_id !== null;
    }

    /**
     * Determine whether the invitation has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Find an invitation by the plain URL token.
     */
    public static function findByPlainToken(string $token): ?self
    {
        return self::withoutGlobalScopes()
            ->where('token', hash('sha256', $token))
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
