<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
        ->assertRedirect(route('login'));
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
