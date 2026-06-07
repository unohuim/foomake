<?php

namespace App\Http\Middleware;

use App\Support\Billing\TenantBillingEntitlement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block protected application routes when the tenant lacks platform access.
 */
class EnsureTenantBillingAccess
{
    /**
     * Route name patterns that remain available when tenant billing access has expired.
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTE_PATTERNS = [
        'billing.*',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'verification.*',
        'logout',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        if ($tenant === null || $this->routeIsExempt($request)) {
            return $next($request);
        }

        if (app(TenantBillingEntitlement::class)->hasAccess($tenant)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(402, 'Billing is required to access this tenant.');
        }

        return redirect()->route('billing.index');
    }

    /**
     * Determine whether the current route should remain reachable without tenant billing access.
     */
    private function routeIsExempt(Request $request): bool
    {
        foreach (self::EXEMPT_ROUTE_PATTERNS as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
