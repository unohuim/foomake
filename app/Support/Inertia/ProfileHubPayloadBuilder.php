<?php

namespace App\Support\Inertia;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Build the personal profile payload for the authenticated Inertia profile page.
 */
class ProfileHubPayloadBuilder
{
    /**
     * Build the personal profile payload for the current request.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'status' => (string) $request->session()->get('status', ''),
            'profile' => $this->profilePayload($user),
        ];
    }

    /**
     * Build profile form payload.
     *
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'currency_code' => $user->tenant?->currency_code ?? 'USD',
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            'updateUrl' => route('profile.update', absolute: false),
            'passwordUpdateUrl' => route('password.update', absolute: false),
            'destroyUrl' => route('profile.destroy', absolute: false),
            'verificationUrl' => route('verification.send', absolute: false),
            'csrfToken' => csrf_token(),
        ];
    }
}
