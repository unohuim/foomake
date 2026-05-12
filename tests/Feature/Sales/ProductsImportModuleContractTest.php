<?php

declare(strict_types=1);

use App\Models\ExternalProductSourceConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Products Import Contract Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Products Import Contract User ' . $this->userCounter,
            'email' => 'products-import-contract-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $slug,
            ]);

            $role = Role::query()->create([
                'name' => 'products-import-contract-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->connectWooCommerce = function (Tenant $tenant): ExternalProductSourceConnection {
        return ExternalProductSourceConnection::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            ],
            [
                'store_url' => 'https://store.example.test',
                'consumer_key' => 'ck_valid_readonly_key',
                'consumer_secret' => 'cs_valid_readonly_secret',
                'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
                'is_connected' => true,
                'connected_at' => now(),
                'last_verified_at' => now(),
                'last_error' => null,
            ]
        );
    };

    $this->extractImportConfig = function ($response): array {
        preg_match("/data-import-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };

    $this->getProductsIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.products.index'));
    };

    $this->importConfigPath = base_path('resources/js/lib/import-config.js');
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->pageModulePath = base_path('resources/js/pages/sales-products-index.js');
    $this->importConfigSource = file_exists($this->importConfigPath)
        ? file_get_contents($this->importConfigPath)
        : '';
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
    $this->pageModuleSource = file_get_contents($this->pageModulePath);
});

it('1. products page exposes import config on the page root', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    ($this->getProductsIndex)($user)
        ->assertOk()
        ->assertSee('data-import-config=', false);
});

it('2. import config json decodes successfully', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config)->toBeArray();
});

it('3. import config identifies the products resource', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['resource'] ?? null)->toBe('products');
});

it('4. import config includes the preview endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['endpoints']['preview'] ?? null)->toBe(route('sales.products.import.preview'));
});

it('5. import config includes the store endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['endpoints']['store'] ?? null)->toBe(route('sales.products.import.store'));
});

it('6. import config includes the products import title label', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['labels']['title'] ?? null)->toBe('Import Products');
});

it('7. import config includes the ecommerce store field label', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['labels']['source'] ?? null)->toBe('Ecommerce Store');
});

it('8. import config includes the import selected button label', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['labels']['submit'] ?? null)->toBe('Import Selected');
});

it('9. import config includes the preview search placeholder label', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['labels']['previewSearch'] ?? null)->toBe('Search preview records')
        ->and($config['labels']['loadingPreviewDefault'] ?? null)->toBe('Loading preview...')
        ->and($config['labels']['loadingPreviewFile'] ?? null)->toBe('Loading file preview...')
        ->and($config['labels']['loadingPreviewExternal'] ?? null)->toBe('Loading WooCommerce preview...');
});

it('10. import config includes the file upload source option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect(collect($config['sources'] ?? [])->firstWhere('value', 'file-upload'))
        ->toMatchArray([
            'value' => 'file-upload',
            'label' => 'File Upload',
            'enabled' => true,
        ]);
});

it('11. import config includes the woo commerce source option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect(collect($config['sources'] ?? [])->firstWhere('value', 'woocommerce'))
        ->toMatchArray([
            'value' => 'woocommerce',
            'label' => 'WooCommerce',
            'enabled' => true,
        ]);
});

it('12. import config carries tenant scoped source connection status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);
    ($this->connectWooCommerce)($tenant);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));
    $wooSource = collect($config['sources'] ?? [])->firstWhere('value', 'woocommerce');

    expect($wooSource['connected'] ?? null)->toBeTrue()
        ->and($wooSource['status'] ?? null)->toBe(ExternalProductSourceConnection::STATUS_CONNECTED);
});

it('13. import config includes create fulfillment recipes bulk option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['bulkOptions']['create_fulfillment_recipes'] ?? null)->toMatchArray([
        'label' => 'Create fulfillment recipes',
        'default' => true,
    ]);
});

it('14. import config includes import all as manufacturable bulk option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['bulkOptions']['import_all_as_manufacturable'] ?? null)->toBeArray();
});

it('15. import config includes import all as purchasable bulk option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['bulkOptions']['import_all_as_purchasable'] ?? null)->toBeArray();
});

it('16. import config includes the bulk base uom option contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['bulkOptions']['bulk_base_uom_id'] ?? null)->toBeArray();
});

it('17. import config includes hidden duplicates by default row behavior', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['rowBehavior']['hideDuplicatesByDefault'] ?? null)->toBeTrue();
});

it('18. import config includes visible non duplicate mass selection behavior', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['rowBehavior']['selectVisibleNonDuplicateRowsOnly'] ?? null)->toBeTrue();
});

it('19. import config includes selected visible rows only submit behavior', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['rowBehavior']['submitSelectedVisibleRowsOnly'] ?? null)->toBeTrue();
});

it('20. import config includes duplicate row metadata field names for the shared module', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $config = ($this->extractImportConfig)(($this->getProductsIndex)($user));

    expect($config['rowBehavior']['duplicateFlagField'] ?? null)->toBe('is_duplicate')
        ->and($config['rowBehavior']['selectionField'] ?? null)->toBe('selected');
});

it('21. products page module imports the shared import config parser', function () {
    expect($this->pageModuleSource)
        ->toContain("import { parseImportConfig } from '../lib/import-config';");
});

it('22. products page module imports the shared import module factory', function () {
    expect($this->pageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';");
});

it('23. shared import config parser file exists', function () {
    expect(file_exists($this->importConfigPath))->toBeTrue();
});

it('24. shared import module factory file exists', function () {
    expect(file_exists($this->importModulePath))->toBeTrue();
});

it('25. shared import config parser safely handles missing or invalid config', function () {
    expect($this->importConfigSource)
        ->toContain('data-import-config')
        ->and($this->importConfigSource)->toContain('JSON.parse')
        ->and($this->importConfigSource)->toContain('try {')
        ->and($this->importConfigSource)->toContain('catch')
        ->and($this->importConfigSource)->toContain('return {}');
});
