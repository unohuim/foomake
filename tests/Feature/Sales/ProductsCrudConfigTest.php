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
    $source = file_exists(base_path('resources/js/lib/import-module.js'))
        ? file_get_contents(base_path('resources/js/lib/import-module.js'))
        : '';

    expect($source)->toContain('endpoints.importPreview')
        ->and($source)->not->toContain('safePayload.previewUrl')
        ->and(file_get_contents(base_path('resources/js/pages/sales-products-index.js')))
            ->toContain("import { createImportModule } from '../lib/import-module';");
});

it('18. products import store action uses the configured import store uri', function () {
    $source = file_exists(base_path('resources/js/lib/import-module.js'))
        ? file_get_contents(base_path('resources/js/lib/import-module.js'))
        : '';

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

it('25. products blade contains no crud toolbar table card or action markup', function () {
    $productsBlade = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));

    expect($productsBlade)->toContain('data-crud-root')
        ->and($productsBlade)->not->toContain('<x-sales.crud-toolbar')
        ->and($productsBlade)->not->toContain('<x-sales.crud-action-cell')
        ->and($productsBlade)->not->toContain('data-products-mobile')
        ->and($productsBlade)->not->toContain('data-products-desktop')
        ->and($productsBlade)->not->toContain('No products found.')
        ->and($productsBlade)->not->toContain('x-for="product in products"')
        ->and($productsBlade)->not->toContain('toggleSort(column)');
});

it('25a. products page shell is height bounded and removes the large gray gap wrapper', function () {
    $productsBlade = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));

    expect($productsBlade)->toContain('class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"')
        ->and($productsBlade)->toContain('class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8"')
        ->and($productsBlade)->toContain('class="flex h-full min-h-0 flex-1 flex-col" data-crud-root')
        ->and($productsBlade)->not->toContain('class="py-12"');
});

it('26. both sales pages mount the shared crud js renderer', function () {
    $productsBlade = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));
    $productsScript = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($productsBlade)->toContain('data-crud-root')
        ->and($customersBlade)->toContain('data-crud-root')
        ->and($productsScript)->toContain("import { mountCrudRenderer } from '../lib/crud-page';")
        ->and($customersScript)->toContain("import { mountCrudRenderer } from '../lib/crud-page';")
        ->and($productsScript)->toContain('mountCrudRenderer(')
        ->and($customersScript)->toContain('mountCrudRenderer(');
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

it('28. shared crud renderer owns the toolbar list cards empty state and action menu contracts', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-mobile')
        ->and($rendererSource)->toContain('data-crud-toolbar-desktop')
        ->and($rendererSource)->toContain('data-crud-table')
        ->and($rendererSource)->toContain('data-crud-mobile-cards')
        ->and($rendererSource)->toContain('data-crud-records-scroll')
        ->and($rendererSource)->toContain('data-crud-empty-state')
        ->and($rendererSource)->toContain('data-crud-action-cell')
        ->and($rendererSource)->toContain('data-crud-action-trigger')
        ->and($rendererSource)->toContain('data-crud-action-menu')
        ->and($rendererSource)->toContain('class="flex h-full min-h-0 flex-col overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm" data-crud-renderer');
});

it('28a. shared crud mobile renderer keeps the primary label visible on mobile', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('<div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>')
        ->and($rendererSource)->toContain('class="flex h-full min-h-0 flex-col"')
        ->and($rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto p-4" data-crud-records-scroll')
        ->and($rendererSource)->toContain('${renderToolbar(config, \'mobile\')}')
        ->and($rendererSource)->toContain('class="min-w-0 flex flex-1 flex-col"')
        ->and($rendererSource)->toContain('class="flex min-w-0 items-start gap-3"')
        ->and($rendererSource)->toContain('class="min-w-0 flex-1 overflow-hidden"')
        ->and($rendererSource)->toContain('class="block truncate text-sm font-medium text-gray-900"')
        ->and($rendererSource)->toContain("renderActionCell(config, 'ml-auto shrink-0')");
});

it('29. toolbar remains outside and above the scrolling list container', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-desktop')
        ->and($rendererSource)->toContain('data-crud-records-scroll')
        ->and($rendererSource)->toContain('class="hidden h-full min-h-0 md:block"')
        ->and($rendererSource)->toContain('border-b border-gray-100 bg-white')
        ->and($rendererSource)->toContain('px-6 py-4')
        ->and($rendererSource)->toContain('class="flex h-full min-h-0 flex-col"')
        ->and($rendererSource)->toContain('class="min-h-0 flex-1 overflow-y-auto" data-crud-records-scroll');
});

it('30. desktop headers remain sticky beneath the toolbar', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('<thead class="bg-white">')
        ->and($rendererSource)->toContain("        ? 'border-b border-gray-100 bg-white p-4'")
        ->and($rendererSource)->toContain("        : 'border-b border-gray-100 bg-white px-6 py-4';")
        ->and($rendererSource)->toContain('class="sticky top-0 z-10 bg-white px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"')
        ->and($rendererSource)->toContain('class="sticky top-0 z-10 bg-white px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"');
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

it('32. products vertical dots render the shared dropdown menu contract', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('data-crud-action-trigger')
        ->and($rendererSource)->toContain('data-crud-action-menu')
        ->and($rendererSource)->toContain("x-bind:aria-expanded=\"open ? 'true' : 'false'\"")
        ->and($rendererSource)->toContain('x-on:click="open = !open"');
});

it('33. products page module maps the edit action id to the product edit handler', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($source)->toContain("action.id === 'edit'")
        ->and($source)->toContain('openEdit(record)');
});

it('34. products edit action opens the edit slideout', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($source)->toContain("this.panelMode = 'edit';")
        ->and($source)->toContain('this.editingProductId = product.id;')
        ->and($source)->toContain('this.isCreatePanelOpen = true;');
});

it('35. products edit slideout is populated from the selected row data', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $blade = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));

    expect($source)->toContain('const productToForm = (product) => ({')
        ->and($blade)->toContain("x-text=\"panelMode === 'create' ? 'Add New Product' : 'Edit Product'\"")
        ->and($blade)->toContain("x-model=\"createForm.name\"")
        ->and($blade)->toContain("x-model=\"createForm.base_uom_id\"")
        ->and($blade)->toContain("x-model=\"createForm.default_price_amount\"");

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
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Product')
        ->assertJsonPath('data.base_uom.id', $replacementUom->id)
        ->assertJsonPath('data.base_uom.name', $replacementUom->name)
        ->assertJsonPath('data.price', '45.67');

    $item->refresh();

    expect($item->name)->toBe('Updated Product')
        ->and($item->base_uom_id)->toBe($replacementUom->id)
        ->and($item->is_purchasable)->toBeTrue()
        ->and($item->is_manufacturable)->toBeTrue()
        ->and($item->default_price_cents)->toBe(4567);
});

it('37. products create and import behavior remain preserved while edit support exists', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $importModuleSource = file_exists(base_path('resources/js/lib/import-module.js'))
        ? file_get_contents(base_path('resources/js/lib/import-module.js'))
        : '';

    expect($source)->toContain('openCreatePanel()')
        ->and($source)->toContain('openImportPanel()')
        ->and($source)->toContain('submitCreate()')
        ->and($source)->toContain('createImportModule(')
        ->and($importModuleSource)->toContain('submitImport()');
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
        ->and($config['labels']['exportAriaLabel'] ?? null)->toBe('Export Products');
});

it('40. toolbar order is search export import add in the shared renderer', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

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
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-import-button')
        ->and($rendererSource)->toContain('M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5')
        ->and($rendererSource)->toContain('M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V16.5');
});

it('42. export button uses the arrow down on square heroicon path', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-export-button')
        ->and($rendererSource)->toContain('M9 8.25H7.5A2.25 2.25 0 0 0 5.25 10.5v9')
        ->and($rendererSource)->toContain('M12 15V3m0 12 3.75-3.75M12 15l-3.75-3.75');
});

it('43. import slide over label is ecommerce store and includes hidden file upload mode', function () {
    $blade = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));
    $source = file_exists(base_path('resources/js/lib/import-module.js'))
        ? file_get_contents(base_path('resources/js/lib/import-module.js'))
        : '';
    $pageModuleSource = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($blade)->toContain('Ecommerce Store')
        ->and($blade)->toContain('data-products-import-file-input')
        ->and($blade)->toContain('type="file"')
        ->and($blade)->toContain('accept=".csv,text/csv"')
        ->and($blade)->toContain('class="sr-only"')
        ->and($blade)->toContain('x-text="sourceOptionLabel(source)"')
        ->and($blade)->not->toContain('>Source<')
        ->and($blade)->not->toContain('Choose File')
        ->and($blade)->toContain("x-show=\"selectedSource && !isFileUploadMode() && selectedSourceEnabled() && !sourceConnected()\"")
        ->and($blade)->toContain('data-products-import-empty-state')
        ->and($pageModuleSource)->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($source)->toContain('handleLocalFileChange(event)')
        ->and($source)->toContain('parseLocalCsv(text)')
        ->and($source)->toContain('parseCsvRows(text)')
        ->and($source)->toContain('csvBooleanOrNull(value)')
        ->and($source)->toContain('selected: true')
        ->and($source)->toContain("return this.selectedSource === 'file-upload';")
        ->and($source)->toContain("return this.selectedSource.startsWith('file-upload-cached:');")
        ->and($source)->toContain('cachedFileSources: []')
        ->and($source)->toContain('nextCachedFileSourceId: 1')
        ->and($source)->toContain('return this.isFileUploadMode() || this.isCachedFileSource() ? null : this.selectedSource;')
        ->and($source)->toContain('sourceOptionLabel(source)')
        ->and($source)->toContain('openImportFilePicker()')
        ->and($source)->toContain('this.$refs.importFileInput?.click();')
        ->and($source)->toContain('restoreCachedFilePreview()')
        ->and($source)->toContain('cacheCurrentFilePreviewRows(rows)')
        ->and($source)->toContain('source: importSource')
        ->and($source)->toContain('is_local_file_import: this.hasLocalFileRows')
        ->and($source)->toContain('buildImportRowPayload(row, importSource)')
        ->and($source)->toContain("this.rowError(index, 'base_uom_id')")
        ->and($source)->toContain("this.importError = data.message || 'Unable to import products.'")
        ->and($source)->toContain("default_price_cents: Object.prototype.hasOwnProperty.call(row, 'default_price_cents')")
        ->and($source)->toContain("image_url: Object.prototype.hasOwnProperty.call(row, 'image_url')")
        ->and($source)->toContain("&& typeof row.image_url === 'string'")
        ->and($source)->toContain("&& row.image_url.trim() !== ''")
        ->and($source)->toContain("source: 'file-upload'")
        ->and($blade)->toContain('rowProductErrors(index)')
        ->and($blade)->toContain('data-products-import-preview-card')
        ->and($blade)->toContain('data-products-import-preview-search')
        ->and($blade)->toContain('data-products-import-show-duplicates')
        ->and($blade)->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">')
        ->and($source)->toContain("config.labels?.loadingPreviewFile || 'Loading file preview...'")
        ->and($source)->toContain('loadingMessage: loadingFilePreviewLabel');
});

it('44. products page renders an export slide over root', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-products-export-panel', false);
});

it('45. export slide over includes current filters and all records options', function () {
    $blade = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));

    expect($blade)->toContain('Current filters and sort')
        ->and($blade)->toContain('All records')
        ->and($blade)->toContain('CSV');
});

it('46. import export slide over js is reusable and config driven', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $importModuleSource = file_exists(base_path('resources/js/lib/import-module.js'))
        ? file_get_contents(base_path('resources/js/lib/import-module.js'))
        : '';

    expect($source)->toContain('slideOvers:')
        ->and($source)->toContain("export: {")
        ->and($source)->toContain('openSlideOver(')
        ->and($source)->toContain('closeSlideOver(')
        ->and($source)->toContain('createImportModule(')
        ->and($importModuleSource)->toContain('buildImportRowPayload(row, importSource)');
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
