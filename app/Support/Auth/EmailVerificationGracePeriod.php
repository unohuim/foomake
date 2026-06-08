<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Resolve the soft email-verification window for newly registered users.
 */
class EmailVerificationGracePeriod
{
    public const HOURS = 24;

    /**
     * Determine whether an unverified user may temporarily access the app.
     */
    public function isActive(User $user, ?Carbon $now = null): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        return $this->endsAt($user)->greaterThan($now ?? now());
    }

    /**
     * Resolve when the user's soft verification window ends.
     */
    public function endsAt(User $user): Carbon
    {
        return $user->created_at->copy()->addHours(self::HOURS);
    }
}
