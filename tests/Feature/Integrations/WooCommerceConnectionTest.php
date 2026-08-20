<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email_verified_at' => now(),
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'woo-connection-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->adminConnectPayload = function (array $overrides = []): array {
        return array_merge([
            'store_url' => 'https://store.example.test',
            'consumer_key' => 'ck_valid_readonly_key',
            'consumer_secret' => 'cs_valid_readonly_secret',
        ], $overrides);
    };

    $this->fakeWooVerificationSuccess = function (): void {
        Http::fake([
            'https://store.example.test/wp-json/wc/v3/products*' => Http::response([
                ['id' => 1001, 'name' => 'Verified'],
            ], 200),
            'https://replacement.example.test/wp-json/wc/v3/products*' => Http::response([
                ['id' => 1001, 'name' => 'Verified'],
            ], 200),
        ]);
    };

    $this->fakeWooVerificationFailure = function (int $status = 401, array $body = []): void {
        Http::fake([
            'https://store.example.test/wp-json/wc/v3/products*' => Http::response(
                $body === [] ? ['message' => 'Invalid signature'] : $body,
                $status
            ),
            'https://broken.example.test/wp-json/wc/v3/products*' => Http::response(
                $body === [] ? ['message' => 'Invalid signature'] : $body,
                $status
            ),
        ]);
    };

    $this->connectWoo = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('profile.connectors.woocommerce.store'), ($this->adminConnectPayload)($payload));
    };

    $this->disconnectWoo = function (User $user) {
        return $this->actingAs($user)->deleteJson(route('profile.connectors.woocommerce.destroy'));
    };
});

it('1. admin can view the connector page', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertSee('Connectors')
        ->assertSee('WooCommerce');
});

it('2. non admin cannot view the connector page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $this->actingAs($user)
        ->get(route('profile.connectors.index'))
        ->assertForbidden();
});

it('3. unauthenticated users cannot access the connector page', function () {
    $this->get(route('profile.connectors.index'))
        ->assertRedirect('/?auth=login');
});

it('4. profile dropdown shows Connectors only for admin', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant, ['name' => 'Admin User']);
    $salesUser = ($this->makeUser)($tenant, ['name' => 'Sales User']);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->grantPermission)($salesUser, 'inventory-products-manage');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('profile.connectors.index'), false)
        ->assertSee('Connectors');

    $this->actingAs($salesUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('profile.connectors.index'), false)
        ->assertDontSee('Connectors');
});

it('5. connector page route is not under the sales navigation path', function () {
    expect(route('profile.connectors.index'))->not->toContain('/sales/');
});

it('6. admin can connect WooCommerce with valid credentials', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)
        ->assertOk()
        ->assertJsonPath('data.source', 'woocommerce')
        ->assertJsonPath('data.status', 'connected');
});

it('7. credentials are encrypted at rest', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    $connection = DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->first();

    expect((string) $connection->store_url)->not->toBe('https://store.example.test')
        ->and((string) $connection->consumer_key)->not->toBe('ck_valid_readonly_key')
        ->and((string) $connection->consumer_secret)->not->toBe('cs_valid_readonly_secret');
});

it('8. only one WooCommerce connection exists per tenant', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();
    ($this->connectWoo)($admin, ['store_url' => 'https://store.example.test'])->assertOk();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->count())->toBe(1);
});

it('9. reconnect overwrites the existing tenant WooCommerce connection', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();
    ($this->connectWoo)($admin, [
        'store_url' => 'https://replacement.example.test',
        'consumer_key' => 'ck_replacement_key',
        'consumer_secret' => 'cs_replacement_secret',
    ])->assertOk();

    $connection = \App\Models\ExternalProductSourceConnection::query()
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->firstOrFail();

    expect($connection->store_url)->toBe('https://replacement.example.test')
        ->and($connection->consumer_key)->toBe('ck_replacement_key')
        ->and($connection->consumer_secret)->toBe('cs_replacement_secret');
});

it('10. successful connection stores connected status', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->value('status'))->toBe('connected');
});

it('11. successful connection stores last verified at', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->value('last_verified_at'))->not->toBeNull();
});

it('12. successful connection clears prior last error', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    DB::table('external_product_source_connections')->insert([
        'tenant_id' => $tenant->id,
        'source' => 'woocommerce',
        'status' => 'disconnected',
        'last_error' => 'Old error',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->value('last_error'))->toBeNull();
});

it('13. invalid credentials are rejected', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationFailure)();

    ($this->connectWoo)($admin)->assertUnprocessable();
});

it('14. failed verification does not save credentials', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationFailure)();

    ($this->connectWoo)($admin)->assertUnprocessable();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->exists())->toBeFalse();
});

it('15. failed verification returns a JSON validation error', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationFailure)();

    ($this->connectWoo)($admin)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['store_url']);
});

it('16. failed verification does not overwrite a previously valid connection', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    $connectionId = DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->value('id');

    ($this->fakeWooVerificationFailure)();

    ($this->connectWoo)($admin, [
        'store_url' => 'https://broken.example.test',
        'consumer_key' => 'ck_broken_key',
        'consumer_secret' => 'cs_broken_secret',
    ])->assertUnprocessable();

    $connection = \App\Models\ExternalProductSourceConnection::query()->findOrFail($connectionId);

    expect($connection->store_url)->toBe('https://store.example.test')
        ->and($connection->consumer_key)->toBe('ck_valid_readonly_key')
        ->and($connection->consumer_secret)->toBe('cs_valid_readonly_secret')
        ->and($connection->status)->toBe('connected');
});

it('17. failed verification returns safe error feedback without exposing secrets', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationFailure)(401, [
        'message' => 'Consumer secret is invalid for ck_valid_readonly_key',
    ]);

    $response = ($this->connectWoo)($admin)->assertUnprocessable();

    expect($response->getContent())->not->toContain('cs_valid_readonly_secret');
});

it('18. admin can disconnect WooCommerce', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    ($this->disconnectWoo)($admin)
        ->assertOk()
        ->assertJsonPath('data.status', 'disconnected');
});

it('19. disconnect keeps the connection record', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();
    ($this->disconnectWoo)($admin)->assertOk();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->exists())->toBeTrue();
});

it('20. disconnect sets disconnected status', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();
    ($this->disconnectWoo)($admin)->assertOk();

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->value('status'))->toBe('disconnected');
});

it('21. disconnect clears encrypted credentials', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();
    ($this->disconnectWoo)($admin)->assertOk();

    $connection = DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->first();

    expect($connection->store_url)->toBeNull()
        ->and($connection->consumer_key)->toBeNull()
        ->and($connection->consumer_secret)->toBeNull();
});

it('22. only admin can connect WooCommerce', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($user)->assertForbidden();
});

it('23. only admin can disconnect WooCommerce', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    $salesUser = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->grantPermission)($salesUser, 'inventory-products-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    ($this->disconnectWoo)($salesUser)->assertForbidden();
});

it('24. invalid store URLs are rejected', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->postJson(route('profile.connectors.woocommerce.store'), [
            'store_url' => 'not-a-url',
            'consumer_key' => 'ck_valid_readonly_key',
            'consumer_secret' => 'cs_valid_readonly_secret',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['store_url']);
});

it('25. missing consumer key is rejected', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->postJson(route('profile.connectors.woocommerce.store'), [
            'store_url' => 'https://store.example.test',
            'consumer_secret' => 'cs_valid_readonly_secret',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['consumer_key']);
});

it('26. missing consumer secret is rejected', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->postJson(route('profile.connectors.woocommerce.store'), [
            'store_url' => 'https://store.example.test',
            'consumer_key' => 'ck_valid_readonly_key',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['consumer_secret']);
});

it('27. credentials and secrets are never returned in JSON responses', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    $response = ($this->connectWoo)($admin)->assertOk();

    expect($response->getContent())->not->toContain('ck_valid_readonly_key')
        ->and($response->getContent())->not->toContain('cs_valid_readonly_secret');
});

it('28. credentials and secrets are never rendered back into blade', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');
    ($this->fakeWooVerificationSuccess)();

    ($this->connectWoo)($admin)->assertOk();

    $response = $this->actingAs($admin)
        ->get(route('profile.connectors.index'))
        ->assertOk();

    expect($response->getContent())->not->toContain('ck_valid_readonly_key')
        ->and($response->getContent())->not->toContain('cs_valid_readonly_secret')
        ->and($response->getContent())->not->toContain('https://store.example.test');
});

it('29. connector page includes Google Search Console state for admins', function () {
    $this->withoutVite();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile/Connectors/Index')
            ->where('connectors.googleSearchConsole.connected', false)
            ->where('connectors.googleSearchConsoleConnectUrl', route('profile.connectors.google-search-console.connect'))
            ->where('connectors.googleSearchConsoleRefreshUrl', route('profile.connectors.google-search-console.refresh'))
            ->where('connectors.googleSearchConsoleReportUrl', route('profile.connectors.google-search-console.report'))
        );
});

it('30. unauthenticated users cannot start Google Search Console OAuth', function () {
    $this->get(route('profile.connectors.google-search-console.connect'))
        ->assertRedirect('/?auth=login');
});

it('31. non admins cannot start Google Search Console OAuth', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $this->actingAs($user)
        ->get(route('profile.connectors.google-search-console.connect'))
        ->assertForbidden();
});

it('32. Google Search Console connect redirects to Google with readonly offline scope', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => 'http://localhost:8000/integrations/google/search-console/callback',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $response = $this->actingAs($admin)
        ->get(route('profile.connectors.google-search-console.connect'))
        ->assertRedirect();

    $location = (string) $response->headers->get('Location');

    expect($location)->toContain('https://accounts.google.com/AccountChooser')
        ->and(urldecode($location))->toContain('https://accounts.google.com/o/oauth2/v2/auth')
        ->and(urldecode($location))->toContain('client_id=google-client-id')
        ->and(urldecode($location))->toContain('access_type=offline')
        ->and(urldecode($location))->toContain('prompt=consent')
        ->and(urldecode($location))->toContain('https://www.googleapis.com/auth/webmasters.readonly')
        ->and(urldecode($location))->toContain('http://localhost:8000/integrations/google/search-console/callback');
});

it('32b. Google Search Console local connect uses localhost callback when redirect is not configured', function () {
    config([
        'app.env' => 'local',
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => null,
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $response = $this->actingAs($admin)
        ->get(route('profile.connectors.google-search-console.connect'))
        ->assertRedirect();

    expect(urldecode((string) $response->headers->get('Location')))
        ->toContain('http://localhost:8000/integrations/google/search-console/callback');
});

it('32c. Google Search Console production connect requires configured redirect or generated public route', function () {
    config([
        'app.env' => 'production',
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => null,
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $response = $this->actingAs($admin)
        ->get(route('profile.connectors.google-search-console.connect'))
        ->assertRedirect();

    expect(urldecode((string) $response->headers->get('Location')))
        ->toContain(route('profile.connectors.google-search-console.callback'));
});


it('33. Google Search Console callback rejects invalid state', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->withSession([
            'google_search_console_oauth_state' => [
                'state' => 'expected-state',
                'tenant_id' => $tenant->id,
            ],
        ])
        ->get(route('profile.connectors.google-search-console.callback', [
            'state' => 'wrong-state',
            'code' => 'valid-code',
        ]))
        ->assertRedirect(route('profile.connectors.index'));

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

it('34. Google Search Console callback stores encrypted offline tokens', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => 'http://localhost:8000/integrations/google/search-console/callback',
        'services.google_search_console.site_url' => 'sc-domain:foomake.com',
    ]);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        ], 200),
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'sc-domain:foomake.com', 'permissionLevel' => 'siteOwner'],
            ],
        ], 200),
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->withSession([
            'google_search_console_oauth_state' => [
                'state' => 'expected-state',
                'tenant_id' => $tenant->id,
            ],
        ])
        ->get(route('profile.connectors.google-search-console.callback', [
            'state' => 'expected-state',
            'code' => 'valid-code',
        ]))
        ->assertRedirect(route('profile.connectors.index'));

    $raw = DB::table('google_search_console_connections')
        ->where('tenant_id', $tenant->id)
        ->first();
    $connection = GoogleSearchConsoleConnection::query()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect((string) $raw->access_token)->not->toBe('access-token')
        ->and((string) $raw->refresh_token)->not->toBe('refresh-token')
        ->and($connection->access_token)->toBe('access-token')
        ->and($connection->refresh_token)->toBe('refresh-token')
        ->and($connection->site_url)->toBe('sc-domain:foomake.com')
        ->and($connection->status)->toBe(GoogleSearchConsoleConnection::STATUS_CONNECTED);
});

it('35. Google Search Console reconnect upserts one tenant row', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => 'http://localhost:8000/integrations/google/search-console/callback',
    ]);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::sequence()
            ->push([
                'access_token' => 'first-access-token',
                'refresh_token' => 'first-refresh-token',
                'expires_in' => 3600,
            ])
            ->push([
                'access_token' => 'second-access-token',
                'refresh_token' => 'second-refresh-token',
                'expires_in' => 3600,
            ]),
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'https://foomake.com/', 'permissionLevel' => 'siteOwner'],
            ],
        ], 200),
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    foreach (['first-state', 'second-state'] as $state) {
        $this->actingAs($admin)
            ->withSession([
                'google_search_console_oauth_state' => [
                    'state' => $state,
                    'tenant_id' => $tenant->id,
                ],
            ])
            ->get(route('profile.connectors.google-search-console.callback', [
                'state' => $state,
                'code' => 'valid-code',
            ]))
            ->assertRedirect(route('profile.connectors.index'));
    }

    $connection = GoogleSearchConsoleConnection::query()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($connection->refresh_token)->toBe('second-refresh-token');
});

it('36. Google Search Console reconnect preserves existing refresh token when Google omits one', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => 'http://localhost:8000/integrations/google/search-console/callback',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'https://foomake.com/',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'refresh_token' => 'existing-refresh-token',
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
        ], 200),
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'https://foomake.com/', 'permissionLevel' => 'siteOwner'],
            ],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->withSession([
            'google_search_console_oauth_state' => [
                'state' => 'expected-state',
                'tenant_id' => $tenant->id,
            ],
        ])
        ->get(route('profile.connectors.google-search-console.callback', [
            'state' => 'expected-state',
            'code' => 'valid-code',
        ]))
        ->assertRedirect(route('profile.connectors.index'));

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->refresh_token)
        ->toBe('existing-refresh-token');
});

it('36b. Google Search Console reconnect prefers configured property over existing site url', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => 'http://localhost:8000/integrations/google/search-console/callback',
        'services.google_search_console.site_url' => 'sc-domain:foomake.com',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'https://farmlycanine.ca/',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'refresh_token' => 'existing-refresh-token',
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'new-access-token',
            'expires_in' => 3600,
        ], 200),
    ]);

    $this->actingAs($admin)
        ->withSession([
            'google_search_console_oauth_state' => [
                'state' => 'expected-state',
                'tenant_id' => $tenant->id,
            ],
        ])
        ->get(route('profile.connectors.google-search-console.callback', [
            'state' => 'expected-state',
            'code' => 'valid-code',
        ]))
        ->assertRedirect(route('profile.connectors.index'));

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->site_url)
        ->toBe('sc-domain:foomake.com');
});

it('37. Google Search Console callback stores first verified site when no property is configured', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.redirect_uri' => 'http://localhost:8000/integrations/google/search-console/callback',
        'services.google_search_console.site_url' => null,
    ]);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ], 200),
        'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [
                ['siteUrl' => 'https://unverified.example.com/', 'permissionLevel' => 'siteUnverifiedUser'],
                ['siteUrl' => 'sc-domain:foomake.com', 'permissionLevel' => 'siteOwner'],
            ],
        ], 200),
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->withSession([
            'google_search_console_oauth_state' => [
                'state' => 'expected-state',
                'tenant_id' => $tenant->id,
            ],
        ])
        ->get(route('profile.connectors.google-search-console.callback', [
            'state' => 'expected-state',
            'code' => 'valid-code',
        ]));

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->site_url)
        ->toBe('sc-domain:foomake.com');
});

it('38. Google Search Console disconnect clears stored tokens', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->deleteJson(route('profile.connectors.google-search-console.destroy'))
        ->assertOk()
        ->assertJsonPath('data.connected', false);

    $connection = GoogleSearchConsoleConnection::query()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($connection->access_token)->toBeNull()
        ->and($connection->refresh_token)->toBeNull()
        ->and($connection->status)->toBe(GoogleSearchConsoleConnection::STATUS_DISCONNECTED);
});

it('39. Google Search Console performance requires a connected tenant row', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->getJson(route('profile.connectors.google-search-console.performance'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Google Search Console is not connected.');
});

it('39b. Google Search Console refresh switches to configured property', function () {
    config([
        'services.google_search_console.site_url' => 'sc-domain:foomake.com',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'https://farmlycanine.ca/',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'refresh_token' => 'refresh-token',
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->patchJson(route('profile.connectors.google-search-console.refresh'))
        ->assertOk()
        ->assertJsonPath('data.site_url', 'sc-domain:foomake.com')
        ->assertJsonPath('data.connected', true);

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->site_url)
        ->toBe('sc-domain:foomake.com');
});

it('39c. Google Search Console refresh requires a connected tenant row', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->patchJson(route('profile.connectors.google-search-console.refresh'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Google Search Console is not connected.');
});

it('40. Google Search Console performance refreshes token and returns top query rows', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'expired-access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->subMinute(),
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'fresh-access-token',
            'expires_in' => 3600,
        ], 200),
        'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Afoomake.com/searchAnalytics/query' => Http::response([
            'rows' => [
                [
                    'keys' => ['recipe management software'],
                    'clicks' => 1,
                    'impressions' => 107,
                    'ctr' => 0.0093,
                    'position' => 18.2,
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->getJson(route('profile.connectors.google-search-console.performance', ['days' => 28]))
        ->assertOk()
        ->assertJsonPath('data.rows.0.query', 'recipe management software')
        ->assertJsonPath('data.rows.0.clicks', 1)
        ->assertJsonPath('data.rows.0.impressions', 107);

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->access_token)
        ->toBe('fresh-access-token');
});

it('41. Google Search Console performance stores safe API errors', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'valid-access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');
    Http::fake([
        'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Afoomake.com/searchAnalytics/query' => Http::response([
            'error' => [
                'message' => 'User does not have sufficient permission for site.',
            ],
        ], 403),
    ]);

    $this->actingAs($admin)
        ->getJson(route('profile.connectors.google-search-console.performance'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'User does not have sufficient permission for site.');

    expect(GoogleSearchConsoleConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->last_error)
        ->toBe('User does not have sufficient permission for site.');
});

it('42. Google Search Console JSON responses never expose OAuth tokens', function () {
    $this->withoutVite();

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'secret-access-token',
        'refresh_token' => 'secret-refresh-token',
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->get(route('profile.connectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('connectors.googleSearchConsole.connected', true)
            ->missing('connectors.googleSearchConsole.access_token')
            ->missing('connectors.googleSearchConsole.refresh_token')
        );
});

it('43. Google Search Console connect handles missing client configuration', function () {
    config([
        'services.google_search_console.client_id' => null,
        'services.google_search_console.client_secret' => null,
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->get(route('profile.connectors.google-search-console.connect'))
        ->assertRedirect(route('profile.connectors.index'));

    expect(session('errors')->get('google_search_console')[0])
        ->toBe('Google Search Console client ID is not configured.');
});

it('44. Google Search Console report command writes markdown report', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
    ]);

    $tenant = ($this->makeTenant)();
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'expired-access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->subMinute(),
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    $path = sys_get_temp_dir() . '/foomake-search-console-report.md';
    File::delete($path);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'fresh-access-token',
            'expires_in' => 3600,
        ], 200),
        'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Afoomake.com/searchAnalytics/query' => Http::sequence()
            ->push([
                'rows' => [
                    [
                        'keys' => ['recipe management software'],
                        'clicks' => 1,
                        'impressions' => 121,
                        'ctr' => 0.0083,
                        'position' => 16.4,
                    ],
                ],
            ], 200)
            ->push([
                'rows' => [
                    [
                        'keys' => ['recipe management software'],
                        'clicks' => 1,
                        'impressions' => 107,
                        'ctr' => 0.0093,
                        'position' => 15.2,
                    ],
                    [
                        'keys' => ['food production software'],
                        'clicks' => 0,
                        'impressions' => 26,
                        'ctr' => 0.0,
                        'position' => 22.1,
                    ],
                ],
            ], 200)
            ->push([
                'rows' => [
                    [
                        'keys' => ['recipe management software'],
                        'clicks' => 0,
                        'impressions' => 13,
                        'ctr' => 0.0,
                        'position' => 18.2,
                    ],
                ],
            ], 200),
    ]);

    $exitCode = Artisan::call('search-console:report', [
        '--path' => $path,
    ]);

    expect($exitCode)->toBe(0)
        ->and(File::get($path))
        ->toContain('# Google Search Console Report')
        ->toContain('Last 28 Days')
        ->toContain('Last 7 Days')
        ->toContain('Last 24 Hours')
        ->toContain('recipe management software')
        ->toContain('Next-Step Recommendations');

    File::delete($path);
});

it('45. Google Search Console report command fails without a connected row', function () {
    $path = sys_get_temp_dir() . '/foomake-search-console-empty-report.md';
    File::delete($path);

    $exitCode = Artisan::call('search-console:report', [
        '--path' => $path,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('No connected FooMake Google Search Console connection found.')
        ->and(File::exists($path))->toBeFalse();
});

it('46. Google Search Console report command uses the configured site when multiple connections exist', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
        'services.google_search_console.site_url' => 'sc-domain:foomake.com',
    ]);

    $firstTenant = ($this->makeTenant)('First Tenant');
    $secondTenant = ($this->makeTenant)('Second Tenant');

    foreach ([$firstTenant, $secondTenant] as $index => $tenant) {
        GoogleSearchConsoleConnection::query()->create([
            'tenant_id' => $tenant->id,
            'site_url' => $index === 0 ? 'sc-domain:foomake.com' : 'sc-domain:example.com',
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
            'refresh_token' => 'refresh-token-' . $tenant->id,
            'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);
    }

    $path = sys_get_temp_dir() . '/foomake-search-console-configured-site-report.md';
    File::delete($path);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'fresh-access-token',
            'expires_in' => 3600,
        ], 200),
        'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Afoomake.com/searchAnalytics/query' => Http::sequence()
            ->push(['rows' => []], 200)
            ->push(['rows' => []], 200)
            ->push(['rows' => []], 200),
    ]);

    $exitCode = Artisan::call('search-console:report', [
        '--path' => $path,
    ]);

    expect($exitCode)->toBe(0)
        ->and(File::get($path))->toContain('sc-domain:foomake.com');

    File::delete($path);
});

it('47. Google Search Console report downloads as markdown from the connector page', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
    ]);

    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'valid-access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    ($this->grantPermission)($admin, 'system-users-manage');

    Http::fake([
        'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Afoomake.com/searchAnalytics/query' => Http::sequence()
            ->push(['rows' => []], 200)
            ->push(['rows' => []], 200)
            ->push(['rows' => []], 200),
    ]);

    $this->actingAs($admin)
        ->get(route('profile.connectors.google-search-console.report'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="search_console_report.md"')
        ->assertSee('# Google Search Console Report', false);
});

it('48. Google Search Console report download requires a connection', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->get(route('profile.connectors.google-search-console.report'))
        ->assertStatus(409);
});

it('49. super admins can view the marketing page with Search Console options', function () {
    $this->withoutVite();

    $tenant = ($this->makeTenant)();
    $superAdmin = ($this->makeUser)($tenant);
    $role = Role::query()->create(['name' => 'super-admin']);
    $superAdmin->roles()->syncWithoutDetaching([$role->id]);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'refresh_token' => 'refresh-token',
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
        'last_verified_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.marketing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/Marketing')
            ->where('searchConsole.connected', true)
            ->where('searchConsole.siteUrl', 'sc-domain:foomake.com')
            ->where('searchConsole.reportUrl', route('profile.connectors.google-search-console.report', absolute: false))
            ->where('searchConsole.dataUrl', route('admin.marketing.search-console.data', absolute: false))
            ->where('searchConsole.views.0.key', 'query')
            ->where('searchConsole.views.0.label', 'Query Performance')
            ->where('searchConsole.timeframes.0.key', '24h')
            ->where('searchConsole.timeframes.2.key', '28d')
        );
});

it('50. super admins can load marketing Search Console data', function () {
    config([
        'services.google_search_console.client_id' => 'google-client-id',
        'services.google_search_console.client_secret' => 'google-client-secret',
    ]);

    $tenant = ($this->makeTenant)();
    $superAdmin = ($this->makeUser)($tenant);
    $role = Role::query()->create(['name' => 'super-admin']);
    $superAdmin->roles()->syncWithoutDetaching([$role->id]);
    GoogleSearchConsoleConnection::query()->create([
        'tenant_id' => $tenant->id,
        'site_url' => 'sc-domain:foomake.com',
        'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
        'access_token' => 'valid-access-token',
        'refresh_token' => 'refresh-token',
        'token_expires_at' => now()->addHour(),
        'status' => GoogleSearchConsoleConnection::STATUS_CONNECTED,
        'connected_at' => now(),
    ]);

    Http::fake([
        'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Afoomake.com/searchAnalytics/query' => Http::response([
            'rows' => [
                [
                    'keys' => ['recipe management software'],
                    'clicks' => 1,
                    'impressions' => 88,
                    'ctr' => 0.0114,
                    'position' => 57.38,
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($superAdmin)
        ->getJson(route('admin.marketing.search-console.data', ['view' => 'query', 'timeframe' => '7d']))
        ->assertOk()
        ->assertJsonPath('data.view', 'query')
        ->assertJsonPath('data.timeframe.key', '7d')
        ->assertJsonPath('data.rows.0.query', 'recipe management software')
        ->assertJsonPath('data.rows.0.impressions', 88);
});

it('51. non super admins cannot view the marketing page', function () {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);

    ($this->grantPermission)($admin, 'system-users-manage');

    $this->actingAs($admin)
        ->get(route('admin.marketing.index'))
        ->assertForbidden();
});
