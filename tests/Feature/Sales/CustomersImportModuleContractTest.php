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
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Customers Import Contract Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customers Import Contract User ' . $this->userCounter,
            'email' => 'customers-import-contract-user-' . $this->userCounter . '@example.test',
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
                'name' => 'customers-import-contract-role-' . $this->roleCounter,
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

    $this->getCustomersIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.customers.index'));
    };

    $this->customersBladeSource = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));
    $this->customersPageModuleSource = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));
    $this->productsPageModuleSource = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $this->importModulePath = base_path('resources/js/lib/import-module.js');
    $this->importModuleSource = file_exists($this->importModulePath)
        ? file_get_contents($this->importModulePath)
        : '';
});

it('1. customers page exposes import config on the page root', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->getCustomersIndex)($user)
        ->assertOk()
        ->assertSee('data-import-config=', false);
});

it('2. customers import config json decodes successfully', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config)->toBeArray();
});

it('3. customers import config identifies the customers resource', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['resource'] ?? null)->toBe('customers');
});

it('4. customers import config includes the preview endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['preview'] ?? null)->toBe(route('sales.customers.import.preview'));
});

it('5. customers import config includes the store endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['store'] ?? null)->toBe(route('sales.customers.import.store'));
});

it('6. customers import config keeps import visibility permission driven', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['permissions']['canManageImports'] ?? null)->toBeTrue()
        ->and($config['permissions']['canManageConnections'] ?? null)->toBeTrue();
});

it('7. customers import config exposes the shared file upload source option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect(collect($config['sources'] ?? [])->firstWhere('value', 'file-upload'))
        ->toMatchArray([
            'value' => 'file-upload',
            'label' => 'File Upload',
            'enabled' => true,
        ]);
});

it('8. customers import config still exposes the woo commerce source option', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));
    $wooSource = collect($config['sources'] ?? [])->firstWhere('value', 'woocommerce');

    expect($wooSource['connected'] ?? null)->toBeTrue()
        ->and($wooSource['status'] ?? null)->toBe(ExternalProductSourceConnection::STATUS_CONNECTED);
});

it('9. customers import config keeps duplicate hiding disabled by default', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['rowBehavior']['hideDuplicatesByDefault'] ?? null)->toBeFalse();
});

it('10. customers import config keeps submit selected rows behavior resource specific', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['rowBehavior']['submitSelectedVisibleRowsOnly'] ?? null)->toBeFalse();
});

it('11. customers import config exposes preview display expressions for the shared component', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['previewDisplay']['titleExpression'] ?? null)->toBe("row.name || '—'")
        ->and($config['previewDisplay']['subtitleExpression'] ?? null)->toBe("row.email || row.external_id || ''")
        ->and($config['previewDisplay']['bodyExpression'] ?? null)->toContain('row.address_line_1');
});

it('12. customers import config exposes customer specific messages through config', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['messages']['importUnavailable'] ?? null)->toBe('Unable to import customers.')
        ->and($config['messages']['emptySelection'] ?? null)->toBe('Select at least one customer to import.');
});

it('13. customers page module imports the shared import config parser', function () {
    expect($this->customersPageModuleSource)
        ->toContain("import { parseImportConfig } from '../lib/import-config';");
});

it('14. customers page module imports the shared import module factory', function () {
    expect($this->customersPageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';");
});

it('15. customers page module composes the shared import module with adapters', function () {
    expect($this->customersPageModuleSource)
        ->toContain('const importModule = createImportModule(')
        ->and($this->customersPageModuleSource)->toContain('adapters: {')
        ->and($this->customersPageModuleSource)->toContain('parseLocalRows:')
        ->and($this->customersPageModuleSource)->toContain('normalizePreviewRow:')
        ->and($this->customersPageModuleSource)->toContain('buildImportRowPayload:')
        ->and($this->customersPageModuleSource)->toContain('buildSubmitBody:');
});

it('16. customers page module mounts the shared import ui', function () {
    expect($this->customersPageModuleSource)
        ->toContain('importModule.mount(rootEl);');
});

it('17. customers page module no longer owns import lifecycle overrides', function () {
    expect($this->customersPageModuleSource)
        ->not->toContain('importModule.resetImportState =')
        ->and($this->customersPageModuleSource)->not->toContain('importModule.handleSourceChange =')
        ->and($this->customersPageModuleSource)->not->toContain('importModule.loadPreview =')
        ->and($this->customersPageModuleSource)->not->toContain('importModule.submitImport =');
});

it('18. customers page module no longer owns import view helper overrides', function () {
    expect($this->customersPageModuleSource)
        ->not->toContain('selectedSourceConnectionLabel =')
        ->and($this->customersPageModuleSource)->not->toContain('previewSearchText =')
        ->and($this->customersPageModuleSource)->not->toContain('previewEmptyStateMessage =');
});

it('19. customers blade no longer contains import slide over markup', function () {
    expect($this->customersBladeSource)
        ->toContain('data-import-config')
        ->and($this->customersBladeSource)->not->toContain('data-customers-import-panel')
        ->and($this->customersBladeSource)->not->toContain('data-customers-import-preview-card')
        ->and($this->customersBladeSource)->not->toContain('x-show="slideOvers.import.open"');
});

it('20. customers blade no longer contains manual preview button markup', function () {
    expect($this->customersBladeSource)
        ->not->toContain('Load Preview')
        ->and($this->customersBladeSource)->not->toContain('x-on:click="loadPreview()"');
});

it('21. shared import module owns the shared panel markup for customers too', function () {
    expect($this->importModuleSource)
        ->toContain('data-shared-import-panel')
        ->and($this->importModuleSource)->toContain('data-shared-import-preview-card')
        ->and($this->importModuleSource)->toContain('data-shared-import-preview-search');
});

it('22. shared import module supports customer specific adapters safely', function () {
    expect($this->importModuleSource)
        ->toContain('const adapters = options.adapters')
        ->and($this->importModuleSource)->toContain('adapters.parseLocalRows')
        ->and($this->importModuleSource)->toContain('adapters.buildImportRowPayload')
        ->and($this->importModuleSource)->toContain('adapters.buildSubmitBody');
});

it('23. shared import module supports config driven preview display expressions', function () {
    expect($this->importModuleSource)
        ->toContain('const previewDisplay = config.previewDisplay || {};')
        ->and($this->importModuleSource)->toContain('titleExpression')
        ->and($this->importModuleSource)->toContain('subtitleExpression')
        ->and($this->importModuleSource)->toContain('bodyExpression');
});

it('24. customers and products now mount the same shared import component path', function () {
    expect($this->customersPageModuleSource)
        ->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->customersPageModuleSource)->toContain('importModule.mount(rootEl);')
        ->and($this->productsPageModuleSource)->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->productsPageModuleSource)->toContain('importModule.mount(rootEl);');
});
