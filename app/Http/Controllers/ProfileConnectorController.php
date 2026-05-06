<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\StoreWooCommerceConnectionRequest;
use App\Integrations\WooCommerce\WooCommerceException;
use App\Models\ExternalProductSourceConnection;
use App\Services\WooCommerceProductPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Manage tenant connector credentials from the authenticated profile area.
 */
class ProfileConnectorController extends Controller
{
    /**
     * Display the connector management page.
     */
    public function index(): View
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $connection = ExternalProductSourceConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('source', ExternalProductSourceConnection::SOURCE_WOOCOMMERCE)
            ->first();

        return view('profile.connectors.index', [
            'payload' => [
                'wooCommerce' => $this->connectionData($connection),
                'storeUrl' => route('profile.connectors.woocommerce.store'),
                'disconnectUrl' => route('profile.connectors.woocommerce.destroy'),
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * Verify and save WooCommerce credentials for the current tenant.
     */
    public function storeWooCommerce(
        StoreWooCommerceConnectionRequest $request,
        WooCommerceProductPreviewService $previewService
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $previewService->verifyCredentials(
                $validated['store_url'],
                $validated['consumer_key'],
                $validated['consumer_secret']
            );
        } catch (WooCommerceException $exception) {
            return response()->json([
                'message' => 'The WooCommerce connection could not be verified.',
                'errors' => [
                    'store_url' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $connection = ExternalProductSourceConnection::query()->updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            ],
            [
                'store_url' => $validated['store_url'],
                'consumer_key' => $validated['consumer_key'],
                'consumer_secret' => $validated['consumer_secret'],
                'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
                'is_connected' => true,
                'connected_at' => now(),
                'last_verified_at' => now(),
                'last_error' => null,
            ]
        );

        return response()->json([
            'data' => $this->connectionData($connection),
        ]);
    }

    /**
     * Disconnect WooCommerce while preserving the tenant record.
     */
    public function destroyWooCommerce(): JsonResponse
    {
        Gate::authorize('system-users-manage');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $connection = ExternalProductSourceConnection::query()->firstOrNew([
            'tenant_id' => $user->tenant_id,
            'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
        ]);

        if (! $connection->exists) {
            $connection->tenant_id = $user->tenant_id;
            $connection->source = ExternalProductSourceConnection::SOURCE_WOOCOMMERCE;
        }

        $connection->fill([
            'store_url' => null,
            'consumer_key' => null,
            'consumer_secret' => null,
            'status' => ExternalProductSourceConnection::STATUS_DISCONNECTED,
            'is_connected' => false,
            'connected_at' => null,
            'last_error' => null,
        ]);
        $connection->save();

        return response()->json([
            'data' => $this->connectionData($connection),
        ]);
    }

    /**
     * Build the safe connection payload returned to the UI.
     *
     * @return array<string, mixed>
     */
    private function connectionData(?ExternalProductSourceConnection $connection): array
    {
        return [
            'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            'status' => $connection?->status ?? ExternalProductSourceConnection::STATUS_DISCONNECTED,
            'connected' => $connection?->isConnected() ?? false,
            'is_connected' => $connection?->isConnected() ?? false,
            'last_verified_at' => $connection?->last_verified_at?->toAtomString(),
            'last_error' => $connection?->last_error,
        ];
    }
}
