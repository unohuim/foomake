<?php

namespace App\Integrations\GoogleSearchConsole;

use App\Models\GoogleSearchConsoleConnection;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Call Google OAuth and Search Console APIs for tenant-owned connections.
 */
class GoogleSearchConsoleAdapter
{
    public const READONLY_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /**
     * Build the Google OAuth authorization URL.
     */
    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $clientId = $this->clientId();

        $authorizationUrl = config('apiurls.google.oauth_authorize_url') . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::READONLY_SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return config('apiurls.google.account_chooser_url') . '?' . http_build_query([
            'continue' => $authorizationUrl,
        ]);
    }

    /**
     * Exchange an OAuth authorization code for tokens.
     *
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->tokenRequest([
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Return Search Console sites visible to the connection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sites(GoogleSearchConsoleConnection $connection): array
    {
        $response = Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->get($this->baseUrl() . '/sites');

        if (! $response->successful()) {
            throw new GoogleSearchConsoleException($this->errorMessage($response->json(), 'Unable to load Search Console sites.'));
        }

        return $response->json('siteEntry') ?? [];
    }

    /**
     * Query Search Console performance rows.
     *
     * @return array<string, mixed>
     */
    public function searchAnalytics(
        GoogleSearchConsoleConnection $connection,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        array $dimensions = ['query'],
        int $rowLimit = 25,
        ?string $siteUrl = null
    ): array {
        $propertyUrl = $siteUrl ?: $connection->site_url ?: config('services.google_search_console.site_url');

        if (! is_string($propertyUrl) || trim($propertyUrl) === '') {
            throw new GoogleSearchConsoleException('Set a Search Console property URL before fetching performance data.');
        }

        $response = Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->post($this->baseUrl() . '/sites/' . rawurlencode($propertyUrl) . '/searchAnalytics/query', [
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'dimensions' => array_values($dimensions),
                'rowLimit' => $rowLimit,
            ]);

        if (! $response->successful()) {
            throw new GoogleSearchConsoleException($this->errorMessage($response->json(), 'Unable to fetch Search Console performance.'));
        }

        return $response->json();
    }

    /**
     * Return a valid access token, refreshing when needed.
     */
    private function accessToken(GoogleSearchConsoleConnection $connection): string
    {
        if (
            filled($connection->access_token)
            && $connection->token_expires_at !== null
            && $connection->token_expires_at->gt(now()->addMinute())
        ) {
            return (string) $connection->access_token;
        }

        if (! filled($connection->refresh_token)) {
            throw new GoogleSearchConsoleException('Search Console is not connected.');
        }

        $tokens = $this->tokenRequest([
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        $accessToken = (string) ($tokens['access_token'] ?? '');

        if ($accessToken === '') {
            throw new GoogleSearchConsoleException('Google did not return an access token.');
        }

        $connection->forceFill([
            'access_token' => $accessToken,
            'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'last_error' => null,
        ])->save();

        return $accessToken;
    }

    /**
     * Request tokens from Google's OAuth token endpoint.
     *
     * @param array<string, string> $payload
     * @return array<string, mixed>
     */
    private function tokenRequest(array $payload): array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->post(config('apiurls.google.oauth_token_url'), $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new GoogleSearchConsoleException($this->errorMessage(
                $exception->response?->json(),
                'Google OAuth token exchange failed.'
            ));
        }

        return $response->json();
    }

    /**
     * Return the configured Google client ID.
     */
    private function clientId(): string
    {
        $clientId = (string) config('services.google_search_console.client_id');

        if ($clientId === '') {
            throw new GoogleSearchConsoleException('Google Search Console client ID is not configured.');
        }

        return $clientId;
    }

    /**
     * Return the configured Google client secret.
     */
    private function clientSecret(): string
    {
        $clientSecret = (string) config('services.google_search_console.client_secret');

        if ($clientSecret === '') {
            throw new GoogleSearchConsoleException('Google Search Console client secret is not configured.');
        }

        return $clientSecret;
    }

    /**
     * Return the configured Search Console API base URL.
     */
    private function baseUrl(): string
    {
        return rtrim((string) config('apiurls.google.search_console_base_url'), '/');
    }

    /**
     * Normalize a Google API error into a safe message.
     *
     * @param array<string, mixed>|null $body
     */
    private function errorMessage(?array $body, string $fallback): string
    {
        $message = data_get($body, 'error.message');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
