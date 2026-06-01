<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantUserInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Register users through a tenant invitation without creating a new tenant.
 */
class InvitedRegisteredUserController extends Controller
{
    /**
     * Display the invited registration form.
     */
    public function create(string $token): RedirectResponse|View
    {
        $invitation = $this->validInvitationOrFail($token);
        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser?->hasVerifiedEmail() && ! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        return view('auth.invited-register', [
            'invitation' => $invitation,
            'token' => $token,
            'requiresPassword' => ! ($existingUser?->hasVerifiedEmail() && Auth::check()),
        ]);
    }

    /**
     * Handle an invited registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->validInvitationOrFail($token);
        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser?->hasVerifiedEmail() && ! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $rules = [];

        if (! $existingUser?->hasVerifiedEmail()) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules);

        $user = DB::transaction(function () use ($request, $invitation, $existingUser): User {
            $lockedInvitation = TenantUserInvitation::withoutGlobalScopes()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->isAccepted() || $lockedInvitation->isExpired() || $lockedInvitation->isRevoked()) {
                abort(403);
            }

            $user = $existingUser;

            if ($user) {
                $attributes = [
                    'tenant_id' => $lockedInvitation->tenant_id,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ];

                if (! $user->hasVerifiedEmail()) {
                    $attributes['name'] = $request->string('name')->toString();
                    $attributes['password'] = Hash::make($request->string('password')->toString());
                }

                $user->forceFill($attributes)->save();
            } else {
                $user = User::query()->create([
                    'tenant_id' => $lockedInvitation->tenant_id,
                    'name' => $request->string('name')->toString(),
                    'email' => $lockedInvitation->email,
                    'email_verified_at' => now(),
                    'password' => Hash::make($request->string('password')->toString()),
                ]);
            }

            $user->roles()->syncWithoutDetaching([$lockedInvitation->role_id]);

            $lockedInvitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            return $user;
        });

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Resolve an invitation token and reject unsafe states.
     */
    private function validInvitationOrFail(string $token): TenantUserInvitation
    {
        $invitation = TenantUserInvitation::findByPlainToken($token);

        if (! $invitation) {
            abort(404);
        }

        if ($invitation->isAccepted() || $invitation->isExpired() || $invitation->isRevoked()) {
            abort(403);
        }

        return $invitation->load('tenant', 'role');
    }
}
