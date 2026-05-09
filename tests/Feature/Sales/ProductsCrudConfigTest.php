<?php

declare(strict_types=1);

use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'name' => 'products-crud-config-role-' . $this->roleCounter,
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

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = (string) ($attributes['symbol'] ?? 'pcfg-' . $this->uomCounter);
        $categoryName = (string) ($attributes['category_name'] ?? 'Products Crud Config Category ' . $this->uomCounter);
        $name = (string) ($attributes['name'] ?? 'Products Crud Config UoM ' . $this->uomCounter);

        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $categoryName,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $name,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Products Crud Config Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $decoded = html_entity_decode($matches[1], ENT_QUOTES);
        $config = json_decode($decoded, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
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

    $this->connectWooCommerce = function (Tenant $tenant): ExternalProductSourceConnection {
        return ExternalProductSourceConnection::query()->create([
            'tenant_id' => $tenant->id,
            'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            'store_url' => 'https://store.example.test',
            'consumer_key' => 'ck_valid_readonly_key',
            'consumer_secret' => 'cs_valid_readonly_secret',
            'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
            'is_connected' => true,
            'connected_at' => now(),
            'last_verified_at' => now(),
            'last_error' => null,
        ]);
    };

    $this->listProducts = function (?User $user = null, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('sales.products.list', $query));
    };

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
        ], 200),
    ]);
});

it('1. products page renders crud config json on the root element', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-crud-config=', false);

    $config = ($this->extractCrudConfig)($response);

    expect($config)->toBeArray();
});

it('2. crud config includes the list endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['endpoints']['list'] ?? null)->toBe(route('sales.products.list'));
});

it('3. crud config includes the create endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['endpoints']['create'] ?? null)->toBe(route('sales.products.store'));
});

it('4. crud config includes the import preview endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['endpoints']['importPreview'] ?? null)->toBe(route('sales.products.import.preview'));
});

it('5. crud config includes the import store endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['endpoints']['importStore'] ?? null)->toBe(route('sales.products.import.store'));
});

it('6. crud config includes columns', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['columns'] ?? null)->toBeArray();
});

it('7. crud config includes headers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['headers'] ?? null)->toBeArray();
});

it('8. crud config includes sortable fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['sortable'] ?? null)->toBeArray();
});

it('9. crud config sortable fields only reference configured columns', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    $columns = collect($config['columns'] ?? []);
    $sortable = collect($config['sortable'] ?? []);

    expect($sortable->diff($columns)->all())->toBe([]);
});

it('10. crud config column keys are stable and non empty', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['columns'] ?? [])->toBe(['name', 'base_uom', 'price']);
});

it('11. crud config headers are stable and non empty', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['headers'] ?? [])->toBe([
        'name' => 'Name',
        'base_uom' => 'Base UoM',
        'price' => 'Price',
    ]);
});

it('12. crud config is valid json', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();

    preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

    expect($matches)->toHaveKey(1);
    expect(json_decode(html_entity_decode($matches[1], ENT_QUOTES), true))->toBeArray();
});

it('13. products js root element exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-page="sales-products-index"', false)
        ->assertSee('data-crud-config=', false);
});

it('14. product list endpoint still returns the expected json shape', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Stable Product',
        'is_sellable' => true,
        'default_price_cents' => 1234,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'base_uom' => ['id', 'name', 'symbol'],
                    'price',
                    'currency',
                    'image_url',
                ],
            ],
            'meta' => [
                'search',
                'sort' => ['column', 'direction'],
                'allowed_sort_columns',
                'total',
            ],
        ]);
});

it('15. products list javascript uses the configured list uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($source)->toContain('endpoints.list')
        ->and($source)->not->toContain('safePayload.listUrl');
});

it('16. products create action uses the configured create uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($source)->toContain('endpoints.create')
        ->and($source)->not->toContain('safePayload.storeUrl');
});

it('17. products import preview action uses the configured import preview uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($source)->toContain('endpoints.importPreview')
        ->and($source)->not->toContain('safePayload.previewUrl');
});

it('18. products import store action uses the configured import store uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($source)->toContain('endpoints.importStore')
        ->and($source)->not->toContain('safePayload.importUrl');
});

it('19. missing crud config fails safely without breaking page load', function () {
    $sharedSource = file_get_contents(base_path('resources/js/lib/crud-config.js'));
    $pageModuleSource = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($sharedSource)->toContain("return {}")
        ->and($sharedSource)->toContain('JSON.parse')
        ->and($pageModuleSource)->toContain('if (!this.endpoints.list)')
        ->and($pageModuleSource)->toContain('return;');
});

it('20. invalid crud config fails safely without breaking page load', function () {
    $sharedSource = file_get_contents(base_path('resources/js/lib/crud-config.js'));

    expect($sharedSource)->toContain('try {')
        ->and($sharedSource)->toContain('catch')
        ->and($sharedSource)->toContain("return {}");
});

it('21. authorization still applies to the products page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertForbidden();
});

it('22. existing products import behavior is preserved', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant, [
        'name' => 'Each',
        'symbol' => 'ea',
    ]);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWooCommerce)($tenant);

    $response = $this->actingAs($user)->postJson(route('sales.products.import.store'), [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => false,
        'rows' => [
            [
                'external_id' => 'woo-101',
                'name' => 'Imported Product',
                'sku' => 'IMPORTED-101',
                'base_uom_id' => $uom->id,
                'is_active' => true,
                'is_manufacturable' => false,
                'is_purchasable' => true,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.imported_count', 1)
        ->assertJsonPath('data.imported.0.name', 'Imported Product');

    $item = Item::query()->where('tenant_id', $tenant->id)->where('external_id', 'woo-101')->first();

    expect($item)->not->toBeNull()
        ->and($item?->is_sellable)->toBeTrue()
        ->and($item?->is_purchasable)->toBeTrue();
});

it('23. existing products create behavior is preserved', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant, [
        'name' => 'Each',
        'symbol' => 'ea',
    ]);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $response = $this->actingAs($user)->postJson(route('sales.products.store'), [
        'name' => 'Created Product',
        'base_uom_id' => $uom->id,
        'is_purchasable' => true,
        'is_manufacturable' => true,
        'default_price_amount' => '12.34',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Created Product')
        ->assertJsonPath('data.base_uom.name', 'Each')
        ->assertJsonPath('data.price', '12.34');

    $item = Item::query()->where('tenant_id', $tenant->id)->where('name', 'Created Product')->first();

    expect($item)->not->toBeNull()
        ->and($item?->is_sellable)->toBeTrue()
        ->and($item?->is_manufacturable)->toBeTrue()
        ->and($item?->default_price_cents)->toBe(1234);
});

it('24. existing products sorting behavior is preserved for configured sortable fields', function () {
    $tenant = ($this->makeTenant)();
    $each = ($this->makeUom)($tenant, ['name' => 'Each', 'symbol' => 'ea']);
    $kilogram = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $kilogram, [
        'name' => 'Zulu Product',
        'is_sellable' => true,
        'default_price_cents' => 500,
        'default_price_currency_code' => 'USD',
    ]);
    ($this->makeItem)($tenant, $each, [
        'name' => 'Alpha Product',
        'is_sellable' => true,
        'default_price_cents' => 100,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->listProducts)($user, ['sort' => 'name', 'direction' => 'asc'])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha Product');

    ($this->listProducts)($user, ['sort' => 'base_uom', 'direction' => 'asc'])
        ->assertOk()
        ->assertJsonPath('data.0.base_uom.name', 'Each');

    ($this->listProducts)($user, ['sort' => 'price', 'direction' => 'asc'])
        ->assertOk()
        ->assertJsonPath('data.0.price', '1.00');
});
