<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Configure shared Inertia behavior for routes migrated to Vue.
 */
final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first Inertia page visit.
     *
     * @var string
     */
    protected $rootView = 'inertia';

    /**
     * Resolve the root template for the current Inertia request.
     */
    public function rootView(Request $request): string
    {
        if ($request->routeIs('marketing.pages.show') || $request->routeIs('privacy')) {
            return 'marketing.inertia';
        }

        return parent::rootView($request);
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define props shared by every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->routeIs('marketing.pages.show') || $request->routeIs('privacy')) {
            return [
                'auth' => [
                    'user' => null,
                ],
                'flash' => [
                    'status' => null,
                ],
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            'flash' => [
                'status' => fn (): mixed => $request->session()->get('status'),
            ],
        ];
    }
}
