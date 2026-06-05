<?php

namespace App\Actions\Marketing;

use App\Models\User;
use App\Models\VisitorAttribution;
use Illuminate\Support\Str;

/**
 * Link a first-party anonymous visitor attribution record to a registered user.
 */
class LinkVisitorAttributionToUserAction
{
    /**
     * Attach the matching visitor attribution to the newly registered user.
     */
    public function execute(?string $visitorId, User $user): void
    {
        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            return;
        }

        $attribution = VisitorAttribution::query()
            ->where('visitor_id', $visitorId)
            ->first();

        if (! $attribution) {
            return;
        }

        if ($attribution->user_id !== null && (int) $attribution->user_id !== (int) $user->id) {
            return;
        }

        $attribution->user_id = $user->id;
        $attribution->converted_at ??= now();
        $attribution->save();
    }
}
