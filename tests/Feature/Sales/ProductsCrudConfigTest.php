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
use Inertia\Testing\AssertableInertia as Assert;

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
        $props = $response->viewData('page')['props'] ?? null;

        if (is_array($props) && isset($props['crudConfig'])) {
            return $props['crudConfig'];
        }

        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $decoded = html_entity_decode($matches[1], ENT_QUOTES);
        $config = json_decode($decoded, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };
    $this->extractImportConfig = function ($response): array {
        $props = $response->viewData('page')['props'] ?? null;

        if (is_array($props) && isset($props['importConfig'])) {
            return $props['importConfig'];
        }

        preg_match("/data-import-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $decoded = html_entity_decode($matches[1], ENT_QUOTES);
        $config = json_decode($decoded, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $props = $response->viewData('page')['props'] ?? null;

        if ($payloadId === 'sales-products-index-payload' && is_array($props) && isset($props['payload'])) {
            return $props['payload'];
        }

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

    $this->updateProduct = function (User $user, Item $item, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('sales.products.update', $item), $payload);
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
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index')
            ->has('crudConfig'));

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

it('4a. config does not include a detail redirect template because products do not have a configured detail route', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect(array_key_exists('detailUrlTemplate', $config))->toBeFalse();
});

it('4b. products page module refreshes after create without redirecting to detail', function () {
    $pageSource = file_get_contents(resource_path('js/pages/Sales/Products/Index.vue'));

    expect($pageSource)->toContain('await this.fetchProducts();')
        ->and($pageSource)->toContain('this.closeCreatePanel();')
        ->and($pageSource)->toContain("this.showToast('success', 'Product created.');")
        ->and($pageSource)->not->toContain('this.crud.buildDetailUrl(data?.data)')
        ->and($pageSource)->not->toContain('window.location.assign(redirectUrl);');
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

    expect(($this->extractCrudConfig)($response))->toBeArray();
});

it('13. products js root element exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index'));
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
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('props.crudConfig.endpoints.list')
        ->and($source)->not->toContain('safePayload.listUrl');
});

it('16. products create action uses the configured create uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('props.crudConfig.endpoints.create')
        ->and($source)->not->toContain('safePayload.storeUrl');
});

it('17. products import preview action uses the configured import preview uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('props.importConfig.endpoints.preview')
        ->and($source)->not->toContain('safePayload.previewUrl')
        ->and($source)->toContain('ResourceImportDrawer');
});

it('18. products import store action uses the configured import store uri', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('props.importConfig.endpoints.store')
        ->and($source)->not->toContain('safePayload.importUrl');
});

it('19. missing crud config fails safely without breaking page load', function () {
    $sharedSource = file_get_contents(base_path('resources/js/lib/crud-config.js'));
    $pageModuleSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($sharedSource)->toContain("return {}")
        ->and($sharedSource)->toContain('JSON.parse')
        ->and($pageModuleSource)->toContain('crudConfig')
        ->and($pageModuleSource)->toContain('required: true');
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
        'default_price_currency_code' => 'cad',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Created Product')
        ->assertJsonPath('data.base_uom.name', 'Each')
        ->assertJsonPath('data.price', '12.34')
        ->assertJsonPath('data.currency', 'CAD');

    $item = Item::query()->where('tenant_id', $tenant->id)->where('name', 'Created Product')->first();

    expect($item)->not->toBeNull()
        ->and($item?->is_sellable)->toBeTrue()
        ->and($item?->is_manufacturable)->toBeTrue()
        ->and($item?->default_price_cents)->toBe(1234)
        ->and($item?->default_price_currency_code)->toBe('CAD');
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

it('25. products vue page contains no legacy blade toolbar table card or action markup', function () {
    $productsPage = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($productsPage)->toContain('<ResourceIndex')
        ->and($productsPage)->toContain('<ResourceCardGrid')
        ->and($productsPage)->not->toContain('<x-sales.crud-toolbar')
        ->and($productsPage)->not->toContain('<x-sales.crud-action-cell')
        ->and($productsPage)->not->toContain('data-products-mobile')
        ->and($productsPage)->not->toContain('data-products-desktop')
        ->and($productsPage)->not->toContain('x-for="product in products"')
        ->and($productsPage)->not->toContain('toggleSort(column)');
});

it('25a. products page shell is height bounded and removes the large gray gap wrapper', function () {
    $productsPage = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $resourceIndex = file_get_contents(base_path('resources/js/components/ResourceIndex.vue'));

    expect($productsPage)->toContain('bounded-height-class="h-full"')
        ->and($productsPage)->toContain('class="flex h-full min-h-0 w-full"')
        ->and($resourceIndex)->toContain('data-resource-index-records-scroll')
        ->and($productsPage)->not->toContain('class="py-12"');
});

it('26. both migrated sales indexes use the shared vue card grid', function () {
    $productsPage = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $customersPage = file_get_contents(base_path('resources/js/pages/Sales/Customers/Index.vue'));

    expect($productsPage)->toContain('ResourceCardGrid')
        ->and($customersPage)->toContain('ResourceCardGrid');
});

it('27. products crud config includes the shared renderer contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['resource'] ?? null)->toBe('products')
        ->and($config['rowDisplay'] ?? null)->toBeArray()
        ->and($config['mobileCard'] ?? null)->toBeArray()
        ->and($config['mobileCard']['titleExpression'] ?? null)->toBe("record.name || '—'")
        ->and($config['actions'] ?? null)->toBeArray()
        ->and($config['permissions'] ?? null)->toBeArray();
});

it('28. shared crud card renderer owns the toolbar card grid empty state and action menu contracts', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar')
        ->and($rendererSource)->toContain('data-crud-card-grid')
        ->and($rendererSource)->toContain('data-crud-card')
        ->and($rendererSource)->toContain('data-crud-mobile-cards')
        ->and($rendererSource)->toContain('data-crud-records-scroll')
        ->and($rendererSource)->toContain('data-crud-empty-state')
        ->and($rendererSource)->toContain('role="menuitem"')
        ->and($rendererSource)->toContain('data-crud-card-renderer')
        ->and($rendererSource)->not->toContain('data-crud-table');
});

it('28a. shared crud mobile renderer keeps the primary label visible on mobile', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('<div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>')
        ->and($rendererSource)->toContain('class="flex h-full min-h-0 flex-col"')
        ->and($rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto p-0" data-crud-records-scroll')
        ->and($rendererSource)->toContain('class="border-t border-gray-300"')
        ->and($rendererSource)->toContain('class="relative flex items-center gap-3 overflow-visible border-b border-gray-300 bg-white px-4 py-2" data-crud-card')
        ->and($rendererSource)->not->toContain('rounded-lg border border-gray-100 bg-white p-4')
        ->and($rendererSource)->toContain('${renderToolbar(normalized)}')
        ->and($rendererSource)->toContain('class="min-w-0 flex-1"')
        ->and($rendererSource)->toContain('class="flex min-w-0 items-center gap-3"')
        ->and($rendererSource)->toContain('class="truncate text-sm font-semibold text-gray-900"')
        ->and($rendererSource)->toContain('${hasActions ? renderActionCell(config) : \'\'}');
});

it('29. toolbar remains outside and above the scrolling card container', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar')
        ->and($rendererSource)->toContain('data-crud-records-scroll')
        ->and($rendererSource)->toContain('class="hidden h-full min-h-0 md:block"')
        ->and($rendererSource)->toContain('border-b border-gray-100 bg-white')
        ->and($rendererSource)->toContain('px-4 py-3 sm:px-6')
        ->and($rendererSource)->toContain('class="flex h-full min-h-0 flex-col"')
        ->and($rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto p-6" data-crud-records-scroll');
});

it('30. desktop renderer uses a responsive card grid instead of sticky table headers', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('data-crud-card-grid')
        ->and($rendererSource)->toContain('grid gap-4 md:grid-cols-3')
        ->and($rendererSource)->toContain('rounded-lg border border-gray-200 bg-white p-4 shadow-sm')
        ->and($rendererSource)->not->toContain('<thead')
        ->and($rendererSource)->not->toContain('sticky top-0');
});

it('31. products config exposes the edit row action', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['actions'] ?? [])->toBe([
        ['id' => 'edit', 'label' => 'Edit', 'tone' => 'default'],
    ]);
});

it('32. products vertical dots render the shared card dropdown menu contract', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('renderActionCell')
        ->and($rendererSource)->toContain('role="menu"')
        ->and($rendererSource)->toContain('role="menuitem"')
        ->and($rendererSource)->toContain('x-on:click="toggle()"');
});

it('33. products vue page maps the edit action to the product edit handler', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain("id: 'edit'")
        ->and($source)->toContain('openEditDrawer(record)');
});

it('34. products edit action opens the edit slideout', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('formMode.value = "edit";')
        ->and($source)->toContain('editingProductId.value = product.id;')
        ->and($source)->toContain('formDrawerOpen.value = true;');
});

it('35. products edit slideout is populated from the selected row data', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('resetForm({')
        ->and($source)->toContain('v-model="form.name"')
        ->and($source)->toContain('v-model="form.base_uom_id"')
        ->and($source)->toContain('v-model="form.default_price_amount"');

    expect((bool) preg_match("/name:\\s*product\\??\\.name\\s*\\|\\|\\s*''/", $source))->toBeTrue()
        ->and((bool) preg_match("/base_uom_id:\\s*product\\??\\.base_uom\\??\\.id\\s*\\?\\s*String\\(product\\.base_uom\\.id\\)\\s*:\\s*''/", $source))->toBeTrue()
        ->and((bool) preg_match("/default_price_amount:\\s*product\\??\\.price\\s*\\|\\|\\s*''/", $source))->toBeTrue();
});

it('36. products edit submit updates the product', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $replacementUom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Editable Product',
        'is_sellable' => true,
        'is_purchasable' => false,
        'is_manufacturable' => false,
        'default_price_cents' => 1234,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->updateProduct)($user, $item, [
        'name' => 'Updated Product',
        'base_uom_id' => $replacementUom->id,
        'is_purchasable' => true,
        'is_manufacturable' => true,
        'default_price_amount' => '45.67',
        'default_price_currency_code' => 'eur',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Product')
        ->assertJsonPath('data.base_uom.id', $replacementUom->id)
        ->assertJsonPath('data.base_uom.name', $replacementUom->name)
        ->assertJsonPath('data.price', '45.67')
        ->assertJsonPath('data.currency', 'EUR');

    $item->refresh();

    expect($item->name)->toBe('Updated Product')
        ->and($item->base_uom_id)->toBe($replacementUom->id)
        ->and($item->is_purchasable)->toBeTrue()
        ->and($item->is_manufacturable)->toBeTrue()
        ->and($item->default_price_cents)->toBe(4567)
        ->and($item->default_price_currency_code)->toBe('EUR');
});

it('37. products create and import behavior remain preserved while edit support exists', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));

    expect($source)->toContain('openCreateDrawer()')
        ->and($source)->toContain('openImportDrawer()')
        ->and($source)->toContain('submitForm()')
        ->and($source)->toContain('ResourceImportDrawer')
        ->and($source)->toContain('submitImport()');
});

it('38. crud config includes the export endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['endpoints']['export'] ?? null)->toBe(route('sales.products.export'));
});

it('39. crud config includes export labels for the shared toolbar', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractCrudConfig)(
        $this->actingAs($user)->get(route('sales.products.index'))
    );

    expect($config['labels']['exportTitle'] ?? null)->toBe('Export Products')
        ->and($config['labels']['exportAriaLabel'] ?? null)->toBe('Export Products')
        ->and($config['labels']['exportCurrentOptionTitle'] ?? null)->toBe('Current filters and sort')
        ->and($config['labels']['exportAllOptionTitle'] ?? null)->toBe('All records');
});

it('40. toolbar order is search export import add in the shared renderer', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    $searchPosition = strpos($rendererSource, 'data-crud-toolbar-search');
    $exportPosition = strpos($rendererSource, 'data-crud-toolbar-export-button');
    $importPosition = strpos($rendererSource, 'data-crud-toolbar-import-button');
    $createPosition = strpos($rendererSource, 'data-crud-toolbar-create-button');

    expect($searchPosition)->not->toBeFalse()
        ->and($exportPosition)->not->toBeFalse()
        ->and($importPosition)->not->toBeFalse()
        ->and($createPosition)->not->toBeFalse()
        ->and($searchPosition < $exportPosition)->toBeTrue()
        ->and($exportPosition < $importPosition)->toBeTrue()
        ->and($importPosition < $createPosition)->toBeTrue();
});

it('41. import button uses the arrow up tray heroicon path', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-import-button')
        ->and($rendererSource)->toContain('M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5')
        ->and($rendererSource)->toContain('M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V16.5');
});

it('42. export button uses the arrow down on square heroicon path', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-export-button')
        ->and($rendererSource)->toContain('M9 8.25H7.5A2.25 2.25 0 0 0 5.25 10.5v9')
        ->and($rendererSource)->toContain('M12 15V3m0 12 3.75-3.75M12 15l-3.75-3.75');
});

it('43. shared import component keeps file upload first and preserves the config source list order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)->get(route('sales.products.index'))->assertOk();
    $importConfig = ($this->extractImportConfig)($response);
    $productsPage = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $pageModuleSource = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $importComponentSource = file_get_contents(base_path('resources/js/components/ResourceImportDrawer.vue'));

    expect($productsPage)->not->toContain('data-products-import-file-input')
        ->and($productsPage)->not->toContain('data-products-import-empty-state')
        ->and($productsPage)->not->toContain('rowProductErrors(index)')
        ->and($productsPage)->not->toContain('data-products-import-preview-card')
        ->and($productsPage)->not->toContain('data-products-import-preview-search')
        ->and($productsPage)->not->toContain('data-products-import-show-duplicates')
        ->and($productsPage)->not->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">')
        ->and($pageModuleSource)->toContain('ResourceImportDrawer')
        ->and($pageModuleSource)->toContain('handleLocalFileChange')
        ->and($pageModuleSource)->toContain('parseProductCsv(text)')
        ->and($pageModuleSource)->toContain('parseCsvRows(text)')
        ->and($pageModuleSource)->toContain('csvBoolean(value')
        ->and($pageModuleSource)->toContain('selected: true')
        ->and($pageModuleSource)->toContain("source: 'file-upload'")
        ->and($pageModuleSource)->toContain('is_local_file_import: selectedImportSource.value === "file-upload"')
        ->and($pageModuleSource)->toContain('default_price_cents: amountToCents(record.default_price_amount)')
        ->and($pageModuleSource)->toContain('image_url: row.image_url ?? null')
        ->and($importComponentSource)->toContain('data-resource-import-file-input')
        ->and($importComponentSource)->toContain('data-resource-import-show-duplicates')
        ->and($importComponentSource)->toContain('empty-source')
        ->and($importComponentSource)->toContain('preview-rows')
        ->and($importComponentSource)->toContain('data-resource-import-preview-search')
        ->and($importConfig['sources'][0]['label'] ?? null)->toBe('File Upload')
        ->and(collect($importConfig['sources'] ?? [])->contains(fn ($sourceConfig) => ($sourceConfig['label'] ?? null) === 'WooCommerce'))->toBeTrue()
        ->and($importConfig)->toBeArray()
        ->and($importComponentSource)->toContain('type="file"')
        ->and($importComponentSource)->toContain('accept=".csv,text/csv"')
        ->and($importComponentSource)->toContain('class="sr-only"');
});

it('43a. products import config keeps preview rows compact with name and price only', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)($this->actingAs($user)->get(route('sales.products.index'))->assertOk());
    expect($config['previewDisplay']['titleExpression'] ?? null)->toBe("row.name || '—'")
        ->and($config['previewDisplay']['subtitleExpression'] ?? null)->toBe("formattedProductPrice(row)")
        ->and($config['previewDisplay']['bodyExpression'] ?? null)->toBe('');
});

it('44. products page no longer renders export slide over markup server side', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('data-products-export-panel', false)
        ->assertDontSee('data-shared-export-panel', false);
});

it('45. shared export component includes current filters and all records options', function () {
    $source = file_get_contents(base_path('resources/js/lib/export-module.js'));

    expect($source)->toContain('Current filters and sort')
        ->and($source)->toContain('All records')
        ->and($source)->toContain('CSV');
});

it('46. export slide over js is reusable and config driven without page local export markup', function () {
    $source = file_get_contents(base_path('resources/js/pages/Sales/Products/Index.vue'));
    $exportComponentSource = file_get_contents(base_path('resources/js/components/ResourceExportDrawer.vue'));
    $importComponentSource = file_get_contents(base_path('resources/js/components/ResourceImportDrawer.vue'));

    expect($source)->not->toContain('slideOvers:')
        ->and($source)->not->toContain('openSlideOver(')
        ->and($source)->not->toContain('closeSlideOver(')
        ->and($source)->toContain('ResourceExportDrawer')
        ->and($source)->toContain('ResourceImportDrawer')
        ->and($exportComponentSource)->toContain('resource-export-drawer-title')
        ->and($importComponentSource)->toContain('resource-import-drawer-title');
});

it('47. products payload includes file upload as an explicit import mode', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('sales.products.index')),
        'sales-products-index-payload'
    );

    expect(collect($payload['sources'] ?? [])->firstWhere('value', 'file-upload'))
        ->toMatchArray([
            'value' => 'file-upload',
            'label' => 'File Upload',
            'enabled' => true,
        ]);
});
