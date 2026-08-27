<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Marketing\LinkVisitorAttributionToUserAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CaptureVisitorAttribution;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, LinkVisitorAttributionToUserAction $linkAttribution): RedirectResponse|Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $tenant = Tenant::create([
            'tenant_name' => $request->name . "'s Organization",
            'trial_ends_at' => now()->addDays(7),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
        ]);

        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        $linkAttribution->execute(
            $request->cookies->get(CaptureVisitorAttribution::COOKIE_NAME),
            $user,
        );

        event(new Registered($user));

        Auth::login($user);

        $dashboardUrl = route('dashboard', absolute: false);

        if ($request->headers->has('X-Inertia')) {
            return Inertia::location($dashboardUrl);
        }

        return redirect($dashboardUrl);
    }
}
