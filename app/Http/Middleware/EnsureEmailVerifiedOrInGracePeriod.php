<?php

namespace App\Http\Middleware;

use App\Support\Auth\EmailVerificationGracePeriod;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow unverified users through only during the soft verification grace window.
 */
class EnsureEmailVerifiedOrInGracePeriod
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if (app(EmailVerificationGracePeriod::class)->isActive($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Email verification is required.');
        }

        return redirect()->guest(route('verification.notice'));
    }
}
