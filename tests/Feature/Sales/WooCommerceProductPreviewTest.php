<?php

declare(strict_types=1);

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'woo-preview-role-' . $this->roleCounter,
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

    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Woo Preview Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Woo Preview UoM ' . $this->uomCounter,
            'symbol' => 'woo-' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->storeWooConnection = function (
        Tenant $tenant,
        array $attributes = []
    ): \App\Models\ExternalProductSourceConnection {
        return \App\Models\ExternalProductSourceConnection::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'source' => 'woocommerce',
            'store_url' => 'https://store.example.test',
            'consumer_key' => 'ck_valid_readonly_key',
            'consumer_secret' => 'cs_valid_readonly_secret',
            'status' => 'connected',
            'last_verified_at' => now(),
            'last_error' => null,
        ], $attributes));
    };

    $this->previewWoo = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.products.import.preview'), array_merge([
            'source' => 'woocommerce',
        ], $payload));
    };

    $this->importRows = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('sales.products.import.store'), $payload);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->fakeWooPreviewResponses = function (): void {
        Http::fake([
            'https://store.example.test/wp-json/wc/v3/products/202/variations?*' => Http::response([
                [
                    'id' => 2021,
                    'status' => 'publish',
                    'sku' => 'HOODIE-BLACK-M',
                    'price' => '34.95',
                    'attributes' => [
                        ['name' => 'Color', 'option' => 'Black'],
                        ['name' => 'Size', 'option' => 'M'],
                    ],
                ],
                [
                    'id' => 2022,
                    'status' => 'private',
                    'sku' => 'HOODIE-BLACK-L',
                    'price' => '35.95',
                    'attributes' => [
                        ['name' => 'Color', 'option' => 'Black'],
                        ['name' => 'Size', 'option' => 'L'],
                    ],
                ],
            ], 200),
            'https://store.example.test/wp-json/wc/v3/products?*' => Http::response([
                [
                    'id' => 101,
                    'name' => 'Simple Tee',
                    'type' => 'simple',
                    'status' => 'publish',
                    'sku' => 'SIMPLE-TEE',
                    'price' => '12.50',
                ],
                [
                    'id' => 202,
                    'name' => 'Variable Hoodie',
                    'type' => 'variable',
                    'status' => 'publish',
                    'sku' => 'HOODIE-PARENT',
                    'price' => '',
                ],
                [
                    'id' => 303,
                    'name' => 'Archived Mug',
                    'type' => 'simple',
                    'status' => 'draft',
                    'sku' => 'ARCHIVED-MUG',
                    'price' => '8.00',
                ],
            ], 200),
        ]);
    };
});

it('1. sales users can see WooCommerce connection status in the products payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);
    ($this->storeWooConnection)($tenant);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'sales-products-index-payload');
    $wooSource = collect($payload['sources'] ?? [])->firstWhere('value', 'woocommerce');

    expect($wooSource['status'] ?? null)->toBe('connected')
        ->and($wooSource['connected'] ?? null)->toBeTrue();
});

it('2. sales users cannot see credentials on the products page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);
    ($this->storeWooConnection)($tenant);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();

    expect($response->getContent())->not->toContain('ck_valid_readonly_key')
        ->and($response->getContent())->not->toContain('cs_valid_readonly_secret')
        ->and($response->getContent())->not->toContain('https://store.example.test');
});

it('3. sales users cannot edit credentials from the products page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('consumer_key')
        ->assertDontSee('consumer_secret')
        ->assertDontSee('store_url');
});

it('4. authorized sales users can preview only when WooCommerce is connected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    ($this->previewWoo)($user)->assertOk();
});

it('5. preview without a connection returns a clear JSON error', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->previewWoo)($user)
        ->assertUnprocessable()
        ->assertJsonPath('meta.connect_required', true);
});

it('6. preview with a disconnected connection returns a clear JSON error', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant, [
        'status' => 'disconnected',
        'store_url' => null,
        'consumer_key' => null,
        'consumer_secret' => null,
    ]);

    ($this->previewWoo)($user)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The selected source is not connected.');
});

it('7. preview calls the WooCommerce API through the client abstraction', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $state = (object) ['called' => false];

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    $client = new class($state) extends WooCommerceClient
    {
        public object $state;

        public function __construct(object $state)
        {
            $this->state = $state;
        }

        public function verifyCredentials(string $storeUrl, string $consumerKey, string $consumerSecret): void
        {
        }

        public function listProducts(string $storeUrl, string $consumerKey, string $consumerSecret): array
        {
            $this->state->called = true;

            return [[
                'id' => 501,
                'name' => 'Mocked Product',
                'type' => 'simple',
                'status' => 'publish',
                'sku' => 'MOCK-501',
                'price' => '7.00',
            ]];
        }

        public function listVariations(string $storeUrl, string $consumerKey, string $consumerSecret, int $productId): array
        {
            return [];
        }
    };

    app()->instance(WooCommerceClient::class, $client);

    ($this->previewWoo)($user)->assertOk();

    expect($state->called)->toBeTrue();
});

it('8. the WooCommerce client can be replaced in tests', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    $client = new class extends WooCommerceClient
    {
        public function verifyCredentials(string $storeUrl, string $consumerKey, string $consumerSecret): void
        {
        }

        public function listProducts(string $storeUrl, string $consumerKey, string $consumerSecret): array
        {
            return [[
                'id' => 777,
                'name' => 'Faked Client Product',
                'type' => 'simple',
                'status' => 'publish',
                'sku' => 'FAKE-777',
                'price' => '10.00',
            ]];
        }

        public function listVariations(string $storeUrl, string $consumerKey, string $consumerSecret, int $productId): array
        {
            return [];
        }
    };

    app()->instance(WooCommerceClient::class, $client);

    ($this->previewWoo)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.0.external_id', '777');
});

it('9. simple WooCommerce products become preview rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();

    expect(collect($response->json('data.rows'))->pluck('external_id')->all())->toContain('101');
});

it('10. variable products are expanded into variation preview rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();

    expect(collect($response->json('data.rows'))->pluck('external_id')->all())
        ->toContain('2021')
        ->toContain('2022')
        ->not->toContain('202');
});

it('10a. real WooCommerce preview returns two simple rows and two variation rows from the fixture', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $rows = collect($response->json('data.rows'));

    expect($rows)->toHaveCount(4)
        ->and($rows->pluck('external_id')->all())->toBe(['101', '2021', '2022', '303']);
});

it('11. variations use the WooCommerce variation id as external id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    ($this->previewWoo)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.1.external_id', '2021');
});

it('12. inactive WooCommerce products map to inactive preview status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $inactive = collect($response->json('data.rows'))->firstWhere('external_id', '303');

    expect($inactive['is_active'] ?? null)->toBeFalse();
});

it('13. WooCommerce prices map into preview rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $simple = collect($response->json('data.rows'))->firstWhere('external_id', '101');

    expect($simple['price'] ?? null)->toBe('12.50');
});

it('14. WooCommerce sku maps into preview rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $simple = collect($response->json('data.rows'))->firstWhere('external_id', '101');

    expect($simple['sku'] ?? null)->toBe('SIMPLE-TEE');
});

it('15. WooCommerce names map into preview rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $simple = collect($response->json('data.rows'))->firstWhere('external_id', '101');

    expect($simple['name'] ?? null)->toBe('Simple Tee');
});

it('16. variation attributes are normalized into deterministic preview metadata', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $variation = collect($response->json('data.rows'))->firstWhere('external_id', '2021');

    expect($variation['name'] ?? null)->toContain('Variable Hoodie')
        ->and($variation['name'] ?? null)->toContain('Black')
        ->and($variation['name'] ?? null)->toContain('M')
        ->and($variation['variation_attributes'] ?? null)->toBeArray();
});

it('17. malformed WooCommerce responses return a safe JSON error', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    Http::fake([
        'https://store.example.test/wp-json/wc/v3/products?*' => Http::response([
            ['name' => 'Missing ID'],
        ], 200),
    ]);

    $response = ($this->previewWoo)($user)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);

    expect($response->getContent())->not->toContain('ck_valid_readonly_key')
        ->and($response->getContent())->not->toContain('cs_valid_readonly_secret');
});

it('18. unreachable WooCommerce stores return a safe JSON error', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
    });

    ($this->previewWoo)($user)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});

it('19. preview rows remain compatible with the import endpoint', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();
    $rows = collect($response->json('data.rows'))
        ->take(2)
        ->map(fn (array $row): array => [
            'external_id' => $row['external_id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'base_uom_id' => $uom->id,
            'is_active' => $row['is_active'],
            'is_manufacturable' => false,
            'is_purchasable' => false,
        ])
        ->values()
        ->all();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => $rows,
    ])->assertCreated();
});

it('20. importing preview rows creates normal items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $preview = ($this->previewWoo)($user)->assertOk()->json('data.rows');
    $simple = collect($preview)->firstWhere('external_id', '101');

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => $simple['external_id'],
            'name' => $simple['name'],
            'sku' => $simple['sku'],
            'base_uom_id' => $uom->id,
            'is_active' => $simple['is_active'],
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_source', 'woocommerce')->exists())->toBeTrue();
});

it('21. imported preview rows always set is sellable to true', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $preview = ($this->previewWoo)($user)->assertOk()->json('data.rows');
    $simple = collect($preview)->firstWhere('external_id', '101');

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => $simple['external_id'],
            'name' => $simple['name'],
            'sku' => $simple['sku'],
            'base_uom_id' => $uom->id,
            'is_active' => $simple['is_active'],
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', '101')->value('is_sellable'))->toBeTrue();
});

it('22. imported simple products store external source woocommerce', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '101',
            'name' => 'Simple Tee',
            'sku' => 'SIMPLE-TEE',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', '101')->value('external_source'))->toBe('woocommerce');
});

it('23. imported simple products store the WooCommerce product id as external id', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '101',
            'name' => 'Simple Tee',
            'sku' => 'SIMPLE-TEE',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('name', 'Simple Tee')->value('external_id'))->toBe('101');
});

it('24. imported variations store external source woocommerce', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '2021',
            'name' => 'Variable Hoodie - Color: Black / Size: M',
            'sku' => 'HOODIE-BLACK-M',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', '2021')->value('external_source'))->toBe('woocommerce');
});

it('25. imported variations store the WooCommerce variation id as external id', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '2021',
            'name' => 'Variable Hoodie - Color: Black / Size: M',
            'sku' => 'HOODIE-BLACK-M',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('name', 'Variable Hoodie - Color: Black / Size: M')->value('external_id'))
        ->toBe('2021');
});

it('26. duplicate source and external id handling still matches and updates the existing WooCommerce item', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '101',
            'name' => 'Simple Tee',
            'sku' => 'SIMPLE-TEE',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '101',
            'name' => 'Updated Simple Tee',
            'sku' => 'SIMPLE-TEE-2',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_not_attempted_existing_item', 1);

    expect(Item::query()
        ->where('tenant_id', $tenant->id)
        ->where('external_source', 'woocommerce')
        ->where('external_id', '101')
        ->count())->toBe(1)
        ->and(Item::query()
            ->where('tenant_id', $tenant->id)
            ->where('external_source', 'woocommerce')
            ->where('external_id', '101')
            ->value('name'))->toBe('Updated Simple Tee');
});

it('27. tenant A cannot use tenant B WooCommerce connection', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermission)($userA, 'inventory-products-manage');
    ($this->storeWooConnection)($tenantB);

    ($this->previewWoo)($userA)->assertUnprocessable();
});

it('28. tenant A products payload does not expose tenant B connection state', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermissions)($userA, ['inventory-products-view', 'inventory-products-manage']);
    ($this->storeWooConnection)($tenantB);

    $response = $this->actingAs($userA)
        ->get(route('sales.products.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'sales-products-index-payload');
    $wooSource = collect($payload['sources'] ?? [])->firstWhere('value', 'woocommerce');

    expect($wooSource['connected'] ?? null)->toBeFalse();
});

it('29. the same WooCommerce product id can exist across tenants', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'inventory-products-manage');
    ($this->grantPermission)($userB, 'inventory-products-manage');
    ($this->storeWooConnection)($tenantA);
    ($this->storeWooConnection)($tenantB);

    ($this->importRows)($userA, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '101',
            'name' => 'Tenant A Tee',
            'sku' => 'TENANT-A-TEE',
            'base_uom_id' => $uomA->id,
        ]],
    ])->assertCreated();

    ($this->importRows)($userB, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '101',
            'name' => 'Tenant B Tee',
            'sku' => 'TENANT-B-TEE',
            'base_uom_id' => $uomB->id,
        ]],
    ])->assertCreated();

    expect(Item::withoutGlobalScopes()
        ->where('external_source', 'woocommerce')
        ->where('external_id', '101')
        ->count())->toBe(2);
});

it('30. reconnect and disconnect affect only the authenticated tenant', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    ($this->storeWooConnection)($tenantA);
    ($this->storeWooConnection)($tenantB);

    \App\Models\ExternalProductSourceConnection::query()
        ->where('tenant_id', $tenantA->id)
        ->where('source', 'woocommerce')
        ->update([
            'status' => 'disconnected',
            'store_url' => null,
            'consumer_key' => null,
            'consumer_secret' => null,
        ]);

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenantA->id)
        ->where('source', 'woocommerce')
        ->value('status'))->toBe('disconnected')
        ->and(DB::table('external_product_source_connections')
            ->where('tenant_id', $tenantB->id)
            ->where('source', 'woocommerce')
            ->value('status'))->toBe('connected');
});

it('31. preview responses never return credentials or secrets', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->storeWooConnection)($tenant);
    ($this->fakeWooPreviewResponses)();

    $response = ($this->previewWoo)($user)->assertOk();

    expect($response->getContent())->not->toContain('ck_valid_readonly_key')
        ->and($response->getContent())->not->toContain('cs_valid_readonly_secret')
        ->and($response->getContent())->not->toContain('https://store.example.test');
});
