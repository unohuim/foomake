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
        ->and($this->customersScript)->toContain('exportModule.mount(rootEl);')
        ->and($this->customersScript)->toContain('...exportModule,');
});

it('10. customers page wires export through openExportPanel on the shared crud renderer', function () {
    expect($this->customersScript)
        ->toContain("exportHandler: 'openExportPanel()'")
        ->and($this->customersScript)->toContain("export: 'openExportPanel()'");
});

it('11. customers page no longer owns the export unavailable stub', function () {
    expect($this->customersScript)
        ->not->toContain('handleExportUnavailable() {')
        ->and($this->customersScript)->not->toContain('slideOvers:')
        ->and($this->customersScript)->not->toContain('slideOverTitle(');
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

it('14. customers page no longer renders export slide over markup server side', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage']);

    ($this->getCustomersIndex)($user)
        ->assertOk()
        ->assertDontSee('data-customers-export-panel', false)
        ->assertDontSee('data-shared-export-panel', false);
});

it('15. shared export component includes current filters and all records options', function () {
    expect($this->exportModuleSource)
        ->toContain('Current filters and sort')
        ->and($this->exportModuleSource)->toContain('All records')
        ->and($this->exportModuleSource)->toContain('x-model="exportScope"');
});

it('16. shared export component displays export errors', function () {
    expect($this->exportModuleSource)
        ->toContain('x-text="exportError"');
});

it('17. shared export component binds submitting state on the export button', function () {
    expect($this->exportModuleSource)
        ->toContain('x-bind:disabled="isExportSubmitting"')
        ->and($this->exportModuleSource)->toContain("x-bind:class=\"isExportSubmitting ? 'cursor-not-allowed opacity-60' : ''\"");
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
        'external_id',
        'external_source',
        'name',
        'email',
        'phone',
        'is_active',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country_code',
    ]);
});

it('19a. customers export csv headers are accepted by customers file upload import preview', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Round Trip Customer',
        'status' => 'active',
        'address_line_1' => '10 Export Lane',
        'city' => 'Toronto',
        'region' => 'ON',
        'postal_code' => 'M5V1A1',
        'country_code' => 'CA',
    ]);
    ($this->createCustomerContact)($tenant, $customer->id, [
        'email' => 'roundtrip@example.test',
        'phone' => '555-0105',
    ]);

    $rows = ($this->csvRows)(($this->exportCustomers)($user, ['scope' => 'all']));
    $header = $rows[0];
    $record = $rows[1];
    $payload = array_combine($header, $record);

    $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => [$payload],
    ])->assertOk()
        ->assertJsonPath('data.source', 'file-upload')
        ->assertJsonPath('data.rows.0.name', 'Round Trip Customer');
});

it('19b. customers export csv round trips active state through file upload preview', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $activeCustomer = ($this->createCustomer)($tenant, [
        'name' => 'Active Export Customer',
        'status' => 'active',
    ]);
    ($this->createCustomerContact)($tenant, $activeCustomer->id, [
        'email' => 'active-export@example.test',
    ]);

    $inactiveCustomer = ($this->createCustomer)($tenant, [
        'name' => 'Inactive Export Customer',
        'status' => 'inactive',
    ]);
    ($this->createCustomerContact)($tenant, $inactiveCustomer->id, [
        'email' => 'inactive-export@example.test',
    ]);

    $records = ($this->csvRecords)(($this->exportCustomers)($user, ['scope' => 'all']));

    expect(collect($records)->firstWhere('name', 'Active Export Customer')['is_active'] ?? null)->toBe('1')
        ->and(collect($records)->firstWhere('name', 'Inactive Export Customer')['is_active'] ?? null)->toBe('0');

    $response = $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => $records,
    ])->assertOk();

    $previewRows = collect($response->json('data.rows', []));

    expect($previewRows->firstWhere('name', 'Active Export Customer')['is_active'] ?? null)->toBeTrue()
        ->and($previewRows->firstWhere('name', 'Active Export Customer')['status_label'] ?? null)->toBe('Active')
        ->and($previewRows->firstWhere('name', 'Inactive Export Customer')['is_active'] ?? null)->toBeFalse()
        ->and($previewRows->firstWhere('name', 'Inactive Export Customer')['status_label'] ?? null)->toBe('Inactive');
});

it('19c. customers export csv re import preview flags duplicate existing rows by external source and external id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Duplicate Export Customer',
        'status' => 'active',
    ]);
    ($this->createCustomerContact)($tenant, $customer->id, [
        'email' => 'duplicate-export@example.test',
    ]);

    \App\Models\ExternalCustomerMapping::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => 'woo-customer-1001',
    ]);

    $records = ($this->csvRecords)(($this->exportCustomers)($user, ['scope' => 'all']));

    expect(collect($records)->firstWhere('name', 'Duplicate Export Customer')['external_source'] ?? null)->toBe('woocommerce')
        ->and(collect($records)->firstWhere('name', 'Duplicate Export Customer')['external_id'] ?? null)->toBe('woo-customer-1001');

    $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => $records,
    ])->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.0.duplicate_reason', 'A customer with the same external source and external ID already exists.');
});

it('19d. customers export csv re import preview flags duplicate existing rows by canonical email fallback when no external source is present', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Email Duplicate Export Customer',
        'status' => 'active',
    ]);
    ($this->createCustomerContact)($tenant, $customer->id, [
        'email' => 'email-duplicate@example.test',
    ]);

    $records = ($this->csvRecords)(($this->exportCustomers)($user, ['scope' => 'all']));

    expect(collect($records)->firstWhere('name', 'Email Duplicate Export Customer')['external_source'] ?? null)->toBe('');

    $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => $records,
    ])->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.0.duplicate_reason', 'A customer with the same email already exists.');
});

it('19e. customers file upload preview parses canonical is_active string variants explicitly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $rows = [
        [
            'external_id' => 'csv-active-1',
            'external_source' => 'file-upload',
            'name' => 'CSV One',
            'email' => 'csv-one@example.test',
            'phone' => null,
            'is_active' => '1',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-inactive-0',
            'external_source' => 'file-upload',
            'name' => 'CSV Zero',
            'email' => 'csv-zero@example.test',
            'phone' => null,
            'is_active' => '0',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-active-true',
            'external_source' => 'file-upload',
            'name' => 'CSV True',
            'email' => 'csv-true@example.test',
            'phone' => null,
            'is_active' => 'true',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-inactive-false',
            'external_source' => 'file-upload',
            'name' => 'CSV False',
            'email' => 'csv-false@example.test',
            'phone' => null,
            'is_active' => 'false',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-active-yes',
            'external_source' => 'file-upload',
            'name' => 'CSV Yes',
            'email' => 'csv-yes@example.test',
            'phone' => null,
            'is_active' => 'yes',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-inactive-no',
            'external_source' => 'file-upload',
            'name' => 'CSV No',
            'email' => 'csv-no@example.test',
            'phone' => null,
            'is_active' => 'no',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-active-active',
            'external_source' => 'file-upload',
            'name' => 'CSV Active',
            'email' => 'csv-active@example.test',
            'phone' => null,
            'is_active' => 'active',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
        [
            'external_id' => 'csv-inactive-inactive',
            'external_source' => 'file-upload',
            'name' => 'CSV Inactive',
            'email' => 'csv-inactive@example.test',
            'phone' => null,
            'is_active' => 'inactive',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ],
    ];

    $response = $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => $rows,
    ])->assertOk();

    $previewRows = collect($response->json('data.rows', []));

    expect($previewRows->firstWhere('name', 'CSV One')['is_active'] ?? null)->toBeTrue()
        ->and($previewRows->firstWhere('name', 'CSV Zero')['is_active'] ?? null)->toBeFalse()
        ->and($previewRows->firstWhere('name', 'CSV True')['is_active'] ?? null)->toBeTrue()
        ->and($previewRows->firstWhere('name', 'CSV False')['is_active'] ?? null)->toBeFalse()
        ->and($previewRows->firstWhere('name', 'CSV Yes')['is_active'] ?? null)->toBeTrue()
        ->and($previewRows->firstWhere('name', 'CSV No')['is_active'] ?? null)->toBeFalse()
        ->and($previewRows->firstWhere('name', 'CSV Active')['is_active'] ?? null)->toBeTrue()
        ->and($previewRows->firstWhere('name', 'CSV Inactive')['is_active'] ?? null)->toBeFalse()
        ->and($previewRows->firstWhere('name', 'CSV Inactive')['status_label'] ?? null)->toBe('Inactive');

    $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => [[
            'external_id' => 'csv-empty-active',
            'external_source' => 'file-upload',
            'name' => 'CSV Empty',
            'email' => 'csv-empty@example.test',
            'phone' => null,
            'is_active' => '',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ], [
            'external_id' => 'csv-null-active',
            'external_source' => 'file-upload',
            'name' => 'CSV Null',
            'email' => 'csv-null@example.test',
            'phone' => null,
            'is_active' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
        ]],
    ])->assertOk()
        ->assertJsonPath('data.rows.0.is_active', false)
        ->assertJsonPath('data.rows.1.is_active', false);
});

it('19f. customers file upload preview accepts the shared component payload shape with boolean is_active and nullable optional fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $response = $this->actingAs($user)->postJson(route('sales.customers.import.preview'), [
        'source' => 'file-upload',
        'rows' => [[
            'external_id' => 'browser-payload-customer-1',
            'external_source' => '',
            'name' => 'Browser Payload Customer',
            'email' => 'browser-payload@example.test',
            'phone' => null,
            'is_active' => false,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => 'CA',
            'is_duplicate' => false,
            'selected' => true,
        ]],
    ])->assertOk();

    $previewRows = collect($response->json('data.rows', []));

    expect($previewRows->firstWhere('name', 'Browser Payload Customer')['is_active'] ?? null)->toBeFalse()
        ->and($previewRows->firstWhere('name', 'Browser Payload Customer')['status_label'] ?? null)->toBe('Inactive');
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
