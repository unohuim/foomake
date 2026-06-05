<?php

namespace App\Http\Middleware;

use App\Models\VisitorAttribution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture minimal first-party attribution for anonymous public-page visitors.
 */
class CaptureVisitorAttribution
{
    public const COOKIE_NAME = 'foomake_visitor_id';

    private const COOKIE_MINUTES = 129600;

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $this->visitorId($request);

        if ($this->shouldCapture($request)) {
            $this->capture($request, $visitorId);
        }

        $response = $next($request);
        $response->headers->setCookie(cookie(
            name: self::COOKIE_NAME,
            value: $visitorId,
            minutes: self::COOKIE_MINUTES,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));

        return $response;
    }

    /**
     * Resolve the current visitor id or generate a new one.
     */
    private function visitorId(Request $request): string
    {
        $visitorId = $request->cookies->get(self::COOKIE_NAME);

        if (is_string($visitorId) && Str::isUuid($visitorId)) {
            return $visitorId;
        }

        return (string) Str::uuid();
    }

    /**
     * Determine whether this request should update attribution.
     */
    private function shouldCapture(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->user() === null;
    }

    /**
     * Store first- and latest-touch attribution values.
     */
    private function capture(Request $request, string $visitorId): void
    {
        $now = now();
        $attribution = VisitorAttribution::query()->firstOrNew([
            'visitor_id' => $visitorId,
        ]);

        if (! $attribution->exists) {
            $attribution->first_seen_at = $now;
        }

        $landingPage = $this->limitText($request->fullUrl(), 2048);
        $referrer = $this->limitText($request->headers->get('referer'), 2048);
        $utm = $this->utm($request);

        if ($attribution->first_landing_page === null) {
            $attribution->first_landing_page = $landingPage;
        }

        if ($attribution->first_referrer === null && $referrer !== null) {
            $attribution->first_referrer = $referrer;
        }

        foreach ($utm as $key => $value) {
            $firstColumn = 'first_' . $key;
            $latestColumn = 'latest_' . $key;

            if ($attribution->{$firstColumn} === null && $value !== null) {
                $attribution->{$firstColumn} = $value;
            }

            if ($value !== null) {
                $attribution->{$latestColumn} = $value;
            }
        }

        $attribution->latest_landing_page = $landingPage;

        if ($referrer !== null) {
            $attribution->latest_referrer = $referrer;
        }

        $attribution->last_seen_at = $now;
        $attribution->save();
    }

    /**
     * @return array<string, string|null>
     */
    private function utm(Request $request): array
    {
        return [
            'utm_source' => $this->limitText($request->query('utm_source'), 255),
            'utm_medium' => $this->limitText($request->query('utm_medium'), 255),
            'utm_campaign' => $this->limitText($request->query('utm_campaign'), 255),
            'utm_content' => $this->limitText($request->query('utm_content'), 255),
            'utm_term' => $this->limitText($request->query('utm_term'), 255),
        ];
    }

    /**
     * Normalize and limit captured text fields.
     */
    private function limitText(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
