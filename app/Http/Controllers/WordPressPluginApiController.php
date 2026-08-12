<?php

namespace App\Http\Controllers;

use App\Models\WordPressPluginConnection;
use App\Models\WordPressPluginPairingCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Handle stateless WordPress plugin pairing and token status requests.
 */
class WordPressPluginApiController extends Controller
{
    /**
     * Start a short-lived pairing request from an installed WordPress plugin.
     */
    public function startPairing(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plugin_uuid' => ['required', 'uuid'],
            'site_url' => ['required', 'url', 'max:2048', 'starts_with:http://,https://'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['required', 'url', 'max:2048', 'starts_with:http://,https://'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $this->callbackMatchesSite((string) $request->input('site_url'), (string) $request->input('callback_url'))) {
                $validator->errors()->add('callback_url', 'The callback URL must belong to the WordPress site URL.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();
        $code = Str::random(64);

        $pairing = WordPressPluginPairingCode::query()->create([
            'plugin_uuid' => $validated['plugin_uuid'],
            'site_url' => $validated['site_url'],
            'site_name' => $validated['site_name'] ?? null,
            'callback_url' => $validated['callback_url'],
            'code_hash' => WordPressPluginPairingCode::hashCode($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'pairing_url' => route('profile.connectors.wordpress.pair', ['code' => $code]),
            'expires_at' => $pairing->expires_at?->toAtomString(),
        ], 201);
    }

    /**
     * Exchange an approved pairing code for a plugin access token.
     */
    public function completePairing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:128'],
            'plugin_uuid' => ['required', 'uuid'],
        ]);

        $pairing = WordPressPluginPairingCode::query()
            ->where('code_hash', WordPressPluginPairingCode::hashCode($validated['code']))
            ->first();

        if (! $pairing || $pairing->plugin_uuid !== $validated['plugin_uuid']) {
            return response()->json(['message' => 'The pairing code is invalid.'], 422);
        }

        if ($pairing->isExpired()) {
            return response()->json(['message' => 'The pairing code has expired.'], 422);
        }

        if ($pairing->isConsumed()) {
            return response()->json(['message' => 'The pairing code has already been used.'], 409);
        }

        if (! $pairing->isApproved()) {
            return response()->json(['message' => 'The pairing code has not been approved.'], 409);
        }

        $plainToken = Str::random(80);
        $tokenHash = $this->hashToken($plainToken);
        $siteAccessToken = Str::random(80);

        $connection = DB::transaction(function () use ($pairing, $tokenHash, $siteAccessToken): WordPressPluginConnection {
            /** @var WordPressPluginConnection $connection */
            $connection = WordPressPluginConnection::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $pairing->tenant_id,
                    'plugin_uuid' => $pairing->plugin_uuid,
                ],
                [
                    'site_url' => $pairing->site_url,
                    'site_name' => $pairing->site_name,
                    'status' => WordPressPluginConnection::STATUS_CONNECTED,
                    'access_token_hash' => $tokenHash,
                    'site_access_token' => $siteAccessToken,
                    'connected_at' => now(),
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ]
            );

            $pairing->forceFill([
                'consumed_at' => now(),
            ])->save();

            return $connection->fresh('tenant');
        });

        return response()->json([
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'connection' => $this->connectionData($connection, $siteAccessToken),
        ]);
    }

    /**
     * Return plugin token status and update last-seen time.
     */
    public function status(Request $request): JsonResponse
    {
        $connection = $this->connectionFromBearerToken($request);

        if (! $connection) {
            return response()->json(['message' => 'Invalid or revoked plugin token.'], 401);
        }

        $siteAccessToken = (string) $connection->site_access_token;

        if ($siteAccessToken === '') {
            $siteAccessToken = Str::random(80);
        }

        $connection->forceFill([
            'last_seen_at' => now(),
            'site_access_token' => $siteAccessToken,
        ])->save();

        return response()->json([
            'connection' => $this->connectionData($connection->fresh('tenant'), $siteAccessToken),
        ]);
    }

    /**
     * Resolve an active plugin connection from the bearer token.
     */
    private function connectionFromBearerToken(Request $request): ?WordPressPluginConnection
    {
        $token = $request->bearerToken();

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        /** @var WordPressPluginConnection|null $connection */
        $connection = WordPressPluginConnection::withoutGlobalScopes()
            ->with('tenant')
            ->where('access_token_hash', $this->hashToken($token))
            ->where('status', WordPressPluginConnection::STATUS_CONNECTED)
            ->whereNull('revoked_at')
            ->first();

        return $connection?->isConnected() ? $connection : null;
    }

    /**
     * Hash a plugin token for lookup and persistence.
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Ensure the plugin callback returns to the same WordPress site host.
     */
    private function callbackMatchesSite(string $siteUrl, string $callbackUrl): bool
    {
        $siteHost = parse_url($siteUrl, PHP_URL_HOST);
        $callbackHost = parse_url($callbackUrl, PHP_URL_HOST);

        return is_string($siteHost)
            && is_string($callbackHost)
            && strtolower($siteHost) === strtolower($callbackHost);
    }

    /**
     * Build the public plugin connection API payload.
     *
     * @return array<string, mixed>
     */
    private function connectionData(WordPressPluginConnection $connection, ?string $siteAccessToken = null): array
    {
        $payload = [
            'status' => $connection->status,
            'connected' => $connection->isConnected(),
            'site_url' => $connection->site_url,
            'site_name' => $connection->site_name,
            'tenant_name' => $connection->tenant?->tenant_name,
            'last_seen_at' => $connection->last_seen_at?->toAtomString(),
            'connected_at' => $connection->connected_at?->toAtomString(),
        ];

        if (is_string($siteAccessToken) && $siteAccessToken !== '') {
            $payload['site_access_token'] = $siteAccessToken;
        }

        return $payload;
    }
}
