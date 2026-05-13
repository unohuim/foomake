<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->contactCounter = 1;

    $this->makeTenant = function (): Tenant {
        $tenant = Tenant::query()->create([
            'tenant_name' => 'Customers Export Module Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Customers Export Module User ' . $this->userCounter,
            'email' => 'customers-export-module-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $slug,
            ]);

            $role = Role::query()->create([
                'name' => 'customers-export-module-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        $customerId = \DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customers Export Customer ' . $this->customerCounter,
            'status' => 'active',
            'notes' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
            'formatted_address' => null,
            'latitude' => null,
            'longitude' => null,
            'address_provider' => null,
            'address_provider_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->customerCounter++;

        return \DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createCustomerContact = function (Tenant $tenant, int $customerId, array $attributes = []): object {
        $contactId = \DB::table('customer_contacts')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'first_name' => 'Export',
            'last_name' => 'Contact ' . $this->contactCounter,
            'email' => 'export-contact-' . $this->contactCounter . '@example.test',
            'phone' => null,
            'role' => null,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->contactCounter++;

        return \DB::table('customer_contacts')->where('id', $contactId)->first();
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };

    $this->getCustomersIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.customers.index'));
    };

    $this->exportCustomers = function (?User $user = null, array $query = []): TestResponse {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('sales.customers.export', $query));
    };

    $this->csvRows = function (TestResponse $response): array {
        $content = $response->streamedContent();
        $lines = preg_split("/\\r\\n|\\n|\\r/", trim($content)) ?: [];

        return array_values(array_filter(array_map(static function (string $line): ?array {
            if ($line === '') {
                return null;
            }

            return str_getcsv($line);
        }, $lines)));
    };

    $this->csvHeader = function (TestResponse $response): array {
        $rows = ($this->csvRows)($response);

        return $rows[0] ?? [];
    };

    $this->csvRecords = function (TestResponse $response): array {
        $rows = ($this->csvRows)($response);
        $header = $rows[0] ?? [];
        $dataRows = array_slice($rows, 1);

        return array_map(static fn (array $row): array => array_combine($header, $row), $dataRows);
    };

    $this->customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));
    $this->customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));
    $this->productsScript = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $this->customerControllerSource = file_get_contents(base_path('app/Http/Controllers/CustomerController.php'));
    $this->exportModulePath = base_path('resources/js/lib/export-module.js');
    $this->exportModuleSource = file_exists($this->exportModulePath)
        ? file_get_contents($this->exportModulePath)
        : '';
});

it('1. customers export route requires authentication', function () {
    ($this->exportCustomers)()
        ->assertRedirect(route('login'));
});

it('2. customers export route denies authenticated users without customers manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->exportCustomers)($user)
        ->assertForbidden();
});

it('3. customers export route allows customers manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    ($this->exportCustomers)($user)
        ->assertOk();
});

it('4. customers crud config includes the export endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['export'] ?? null)->toBe(route('sales.customers.export'));
});

it('5. customers crud config still exposes export labels', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['labels']['exportTitle'] ?? null)->toBe('Export Customers')
        ->and($config['labels']['exportAriaLabel'] ?? null)->toBe('Export Customers');
});

it('6. customers crud config keeps export visibility permission driven', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['permissions']['showExport'] ?? null)->toBeTrue();
});

it('7. controller source keeps customers export visibility tied to sales customers manage', function () {
    expect($this->customerControllerSource)
        ->toContain("'showExport' => Gate::allows('sales-customers-manage')");
});

it('8. customers page imports the shared export module', function () {
    expect($this->customersScript)
        ->toContain("import { createExportModule } from '../lib/export-module';");
});

it('9. customers page composes the shared export module', function () {
    expect($this->customersScript)
        ->toContain('const exportModule = createExportModule(')
        ->and($this->customersScript)->toContain('...exportModule,');
});

it('10. customers page wires export through openExportPanel on the shared crud renderer', function () {
    expect($this->customersScript)
        ->toContain("exportHandler: 'openExportPanel()'")
        ->and($this->customersScript)->toContain("export: 'openExportPanel()'");
});

it('11. customers page no longer owns the export unavailable stub', function () {
    expect($this->customersScript)
        ->not->toContain('handleExportUnavailable() {');
});

it('12. customers page no longer owns export url construction directly', function () {
    expect($this->customersScript)
        ->not->toContain('buildExportUrl() {');
});

it('13. customers page no longer owns export submission directly', function () {
    expect($this->customersScript)
        ->not->toContain('submitExport() {')
        ->and($this->customersScript)->not->toContain('window.location.assign(exportUrl);');
});

it('14. customers export blade renders an export slide over root', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    ($this->getCustomersIndex)($user)
        ->assertOk()
        ->assertSee('data-customers-export-panel', false);
});

it('15. customers export blade includes current filters and all records options', function () {
    expect($this->customersBlade)
        ->toContain('Current filters and sort')
        ->and($this->customersBlade)->toContain('All records')
        ->and($this->customersBlade)->toContain('x-model="exportScope"');
});

it('16. customers export blade displays export errors', function () {
    expect($this->customersBlade)
        ->toContain('x-text="exportError"');
});

it('17. customers export blade binds submitting state on the export button', function () {
    expect($this->customersBlade)
        ->toContain('x-bind:disabled="isExportSubmitting"')
        ->and($this->customersBlade)->toContain("x-bind:class=\"isExportSubmitting ? 'cursor-not-allowed opacity-60' : ''\"");
});

it('18. customers export route responds with a csv attachment filename', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    ($this->exportCustomers)($user)
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=customers-export.csv');
});

it('19. customers export csv includes expected headers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $header = ($this->csvHeader)(($this->exportCustomers)($user));

    expect($header)->toBe([
        'name',
        'email',
        'status',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country_code',
        'formatted_address',
    ]);
});

it('20. customers export current filters uses the current search text', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $matching = ($this->createCustomer)($tenant, ['name' => 'Acme Bakery']);
    ($this->createCustomerContact)($tenant, $matching->id, ['email' => 'orders@acme.test']);
    $other = ($this->createCustomer)($tenant, ['name' => 'Beacon Foods']);
    ($this->createCustomerContact)($tenant, $other->id, ['email' => 'sales@beacon.test']);

    $records = ($this->csvRecords)(($this->exportCustomers)($user, [
        'scope' => 'current',
        'search' => 'Acme',
    ]));

    expect(array_column($records, 'name'))->toBe(['Acme Bakery']);
});

it('21. customers export current filters preserves email descending sort', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $alpha = ($this->createCustomer)($tenant, ['name' => 'Alpha']);
    ($this->createCustomerContact)($tenant, $alpha->id, ['email' => 'a@example.test']);
    $zeta = ($this->createCustomer)($tenant, ['name' => 'Zeta']);
    ($this->createCustomerContact)($tenant, $zeta->id, ['email' => 'z@example.test']);

    $records = ($this->csvRecords)(($this->exportCustomers)($user, [
        'scope' => 'current',
        'sort' => 'email',
        'direction' => 'desc',
    ]));

    expect(array_column($records, 'email'))->toBe(['z@example.test', 'a@example.test']);
});

it('22. customers export all records ignores current search filters', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    $first = ($this->createCustomer)($tenant, ['name' => 'Acme Bakery']);
    ($this->createCustomerContact)($tenant, $first->id, ['email' => 'orders@acme.test']);
    $second = ($this->createCustomer)($tenant, ['name' => 'Beacon Foods']);
    ($this->createCustomerContact)($tenant, $second->id, ['email' => 'sales@beacon.test']);

    $records = ($this->csvRecords)(($this->exportCustomers)($user, [
        'scope' => 'all',
        'search' => 'Acme',
    ]));

    expect(array_column($records, 'name'))->toBe(['Acme Bakery', 'Beacon Foods']);
});

it('23. customers export remains tenant scoped', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();
    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermissions)($userA, ['sales-customers-manage']);

    $own = ($this->createCustomer)($tenantA, ['name' => 'Tenant A Customer']);
    ($this->createCustomerContact)($tenantA, $own->id, ['email' => 'tenant-a@example.test']);
    $other = ($this->createCustomer)($tenantB, ['name' => 'Tenant B Customer']);
    ($this->createCustomerContact)($tenantB, $other->id, ['email' => 'tenant-b@example.test']);

    $records = ($this->csvRecords)(($this->exportCustomers)($userA, ['scope' => 'all']));

    expect(array_column($records, 'name'))->toBe(['Tenant A Customer']);
});

it('24. products export module integration remains intact', function () {
    expect($this->productsScript)
        ->toContain("import { createExportModule } from '../lib/export-module';")
        ->and($this->productsScript)->toContain('const exportModule = createExportModule(')
        ->and($this->productsScript)->toContain('...exportModule,')
        ->and($this->productsScript)->toContain("exportHandler: 'openExportPanel()'")
        ->and($this->productsScript)->toContain("export: 'openExportPanel()'");
});

it('25. customers import behavior remains independent from export extraction', function () {
    expect($this->customersScript)
        ->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($this->customersScript)->toContain("import { createExportModule } from '../lib/export-module';")
        ->and($this->customersScript)->toContain('const importModule = createImportModule(')
        ->and($this->customersScript)->toContain('const exportModule = createExportModule(')
        ->and($this->customersScript)->toContain('...importModule,')
        ->and($this->customersScript)->toContain('...exportModule,')
        ->and($this->exportModuleSource)->not->toContain('importPreview')
        ->and($this->exportModuleSource)->not->toContain('importStore')
        ->and($this->exportModuleSource)->not->toContain('loadPreview')
        ->and($this->exportModuleSource)->not->toContain('submitImport');
});
