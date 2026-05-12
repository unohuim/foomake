<?php

declare(strict_types=1);

use App\Models\ExternalProductSourceConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Customers Crud Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customers Crud User ' . $this->userCounter,
            'email' => 'customers-crud-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'customers-crud-role-' . $this->roleCounter,
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

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        static $customerCounter = 1;
        $status = $attributes['status'] ?? 'active';

        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customers Crud Customer ' . $customerCounter,
            'is_active' => $status === 'active',
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

        $customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createCustomerContact = function (Tenant $tenant, int $customerId, array $attributes = []): object {
        static $contactCounter = 1;

        $contactId = DB::table('customer_contacts')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'first_name' => 'Contact',
            'last_name' => 'Person ' . $contactCounter,
            'email' => 'contact-' . $contactCounter . '@example.test',
            'phone' => null,
            'role' => null,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $contactCounter++;

        return DB::table('customer_contacts')->where('id', $contactId)->first();
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

    $this->fakeWooCustomers = function (array $customers): void {
        Http::fake([
            'https://store.example.test/wp-json/wc/v3/customers?*' => Http::response($customers, 200),
        ]);
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($config) ? $config : [];
    };

    $this->extractImportConfig = function ($response): array {
        preg_match("/data-import-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

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

        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return is_array($payload) ? $payload : [];
    };

    $this->getCustomersIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.customers.index'));
    };

    $this->getCustomersList = function (User $user, array $query = []) {
        return $this->actingAs($user)->getJson(route('sales.customers.list', $query));
    };

    $this->postCustomer = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.customers.store'), $payload);
    };

    $this->previewImport = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.customers.import.preview'), array_merge([
            'source' => 'woocommerce',
        ], $payload));
    };

    $this->storeImport = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.customers.import.store'), $payload);
    };
});

it('1. customers page renders crud config', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->getCustomersIndex)($user)
        ->assertOk()
        ->assertSee('data-crud-config=', false);

    $config = ($this->extractCrudConfig)($response);

    expect($config)->toBeArray();
});

it('2. config includes list uri', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['list'] ?? null)->toBe(route('sales.customers.list'));
});

it('3. config includes create uri', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['create'] ?? null)->toBe(route('sales.customers.store'));
});

it('4. config includes import preview uri', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['importPreview'] ?? null)->toBe(route('sales.customers.import.preview'));
});

it('5. config includes import store uri', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['endpoints']['importStore'] ?? null)->toBe(route('sales.customers.import.store'));
});

it('6. config includes customer columns', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['columns'] ?? null)->toBe(['name', 'email', 'address_summary']);
});

it('7. config includes customer headers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['headers'] ?? null)->toBe([
        'name' => 'Name',
        'email' => 'Email',
        'address_summary' => 'Address',
    ]);
});

it('8. config includes customer sortable fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['sortable'] ?? [])->toContain('name')
        ->and($config['sortable'] ?? [])->toContain('email')
        ->and(collect($config['sortable'] ?? [])->diff($config['columns'] ?? [])->all())->toBe([]);
});

it('9. customer list still loads', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'List Customer']);

    ($this->createCustomerContact)($tenant, $customer->id, [
        'email' => 'list@example.test',
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->getCustomersList)($user)
        ->assertOk()
        ->assertJsonPath('data.0.name', 'List Customer')
        ->assertJsonPath('data.0.email', 'list@example.test');
});

it('10. customer create still works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postCustomer)($user, [
        'name' => 'Created Customer',
        'notes' => 'Imported later',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Created Customer');
});

it('11. customer sorting still works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');
    ($this->createCustomer)($tenant, ['name' => 'Zulu Customer']);
    ($this->createCustomer)($tenant, ['name' => 'Alpha Customer']);

    ($this->getCustomersList)($user, [
        'sort' => 'name',
        'direction' => 'asc',
    ])->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha Customer');
});

it('11a. customer email sorting works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customerA = ($this->createCustomer)($tenant, ['name' => 'Zulu Customer']);
    $customerB = ($this->createCustomer)($tenant, ['name' => 'Alpha Customer']);

    ($this->createCustomerContact)($tenant, $customerA->id, [
        'email' => 'zulu@example.test',
        'first_name' => 'Zulu',
        'last_name' => 'Person',
    ]);
    ($this->createCustomerContact)($tenant, $customerB->id, [
        'email' => 'alpha@example.test',
        'first_name' => 'Alpha',
        'last_name' => 'Person',
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->getCustomersList)($user, [
        'sort' => 'email',
        'direction' => 'asc',
    ])->assertOk()
        ->assertJsonPath('data.0.email', 'alpha@example.test')
        ->assertJsonPath('data.1.email', 'zulu@example.test');
});

it('12. import slideout contract exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->getCustomersIndex)($user)
        ->assertOk()
        ->assertSee('Import Customers')
        ->assertSee('data-import-config=', false);
});

it('13. woo customer preview requires woo admin permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->previewImport)($user)
        ->assertForbidden();
});

it('14. woo customer import requires woo admin permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [],
    ])->assertForbidden();
});

it('15. preview uses existing woo connection', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);
    ($this->fakeWooCustomers)([
        [
            'id' => 101,
            'email' => 'buyer@example.test',
            'first_name' => 'Avery',
            'last_name' => 'Buyer',
            'username' => 'avery-buyer',
            'billing' => [
                'address_1' => '90 Billing Avenue',
                'address_2' => '',
                'city' => 'New York',
                'state' => 'NY',
                'postcode' => '10001',
                'country' => 'US',
            ],
            'shipping' => [
                'address_1' => '123 King Street West',
                'address_2' => '',
                'city' => 'Toronto',
                'state' => 'ON',
                'postcode' => 'M5V 1J2',
                'country' => 'CA',
            ],
        ],
    ]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.source', 'woocommerce')
        ->assertJsonPath('data.is_connected', true);

    Http::assertSentCount(1);
});

it('16. missing woo connection returns json error', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->previewImport)($user)
        ->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => ['source'],
        ]);
});

it('17. preview returns woo customer rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);
    ($this->fakeWooCustomers)([
        [
            'id' => 202,
            'email' => 'preview@example.test',
            'first_name' => 'Parker',
            'last_name' => 'Preview',
            'username' => 'preview-user',
            'billing' => [
                'address_1' => '10 Billing Plaza',
                'address_2' => '',
                'city' => 'New York',
                'state' => 'NY',
                'postcode' => '10002',
                'country' => 'US',
            ],
            'shipping' => [
                'address_1' => '50 Queen Street',
                'address_2' => 'Suite 2',
                'city' => 'Toronto',
                'state' => 'ON',
                'postcode' => 'M5H 2N2',
                'country' => 'CA',
            ],
        ],
    ]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.0.external_id', '202')
        ->assertJsonPath('data.rows.0.email', 'preview@example.test')
        ->assertJsonPath('data.rows.0.name', 'Parker Preview')
        ->assertJsonPath('data.rows.0.city', 'Toronto')
        ->assertJsonPath('data.rows.0.is_active', true);
});

it('17a. file upload preview returns submitted customer rows without requiring a connection', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [
            [
                'external_id' => 'file-1-customer',
                'name' => 'Local File Customer',
                'email' => 'local@example.test',
                'city' => 'Toronto',
                'is_active' => true,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.source', 'file-upload')
        ->assertJsonPath('data.is_connected', false)
        ->assertJsonPath('data.rows.0.name', 'Local File Customer')
        ->assertJsonPath('data.rows.0.city', 'Toronto');
});

it('17b. customers preview flags duplicate woo rows before import', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Existing Duplicate Customer',
    ]);

    DB::table('external_customer_mappings')->insert([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => '801',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);
    ($this->fakeWooCustomers)([
        [
            'id' => 801,
            'email' => 'duplicate@example.test',
            'first_name' => 'Duplicate',
            'last_name' => 'Customer',
            'username' => 'duplicate-customer',
            'shipping' => [
                'address_1' => '123 King Street West',
                'address_2' => '',
                'city' => 'Toronto',
                'state' => 'ON',
                'postcode' => 'M5V 1J2',
                'country' => 'CA',
            ],
        ],
    ]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.0.external_source', 'woocommerce')
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.0.duplicate_reason', 'A customer with the same external source and external ID already exists.');
});

it('17c. customers file preview flags duplicate rows before import', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Existing File Duplicate Customer',
    ]);

    DB::table('external_customer_mappings')->insert([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => 'file-duplicate-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [
            [
                'external_id' => 'file-duplicate-1',
                'external_source' => 'woocommerce',
                'name' => 'File Duplicate Customer',
                'email' => 'file-duplicate@example.test',
                'city' => 'Toronto',
                'is_active' => true,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false);
});

it('18. import creates new customers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '301',
                'name' => 'Avery Buyer',
                'email' => 'imported@example.test',
                'phone' => '555-0101',
                'address_line_1' => '123 Imported Road',
                'city' => 'Toronto',
                'region' => 'ON',
                'postal_code' => 'M5V 1J2',
                'country_code' => 'CA',
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.imported_count', 1);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'Avery Buyer',
        'is_active' => 1,
    ]);

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Avery Buyer')
        ->value('id');

    $this->assertDatabaseHas('customer_contacts', [
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'first_name' => 'Avery',
        'last_name' => 'Buyer',
        'email' => 'imported@example.test',
        'phone' => '555-0101',
        'is_primary' => true,
    ]);
});

it('18a. file upload import can create customers without an external connection', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => null,
        'is_local_file_import' => true,
        'rows' => [
            [
                'external_id' => 'file-1-customer',
                'name' => 'CSV Customer',
                'email' => 'csv@example.test',
                'phone' => '555-0151',
                'city' => 'Toronto',
                'country_code' => 'CA',
                'is_active' => true,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.imported_count', 1);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'CSV Customer',
        'is_active' => 1,
        'status' => 'active',
    ]);
});

it('18b. duplicate customer preview rows are rejected if submitted anyway', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Prevent Duplicate Customer',
    ]);

    DB::table('external_customer_mappings')->insert([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => '811',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '811',
                'external_source' => 'woocommerce',
                'name' => 'Prevent Duplicate Customer',
                'email' => 'prevent-duplicate@example.test',
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['rows.0.external_id']);
});

it('18c. duplicate customer file rows are rejected if submitted anyway', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Prevent File Duplicate Customer',
    ]);

    DB::table('external_customer_mappings')->insert([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => 'file-duplicate-2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => null,
        'is_local_file_import' => true,
        'rows' => [
            [
                'external_id' => 'file-duplicate-2',
                'external_source' => 'woocommerce',
                'name' => 'Prevent File Duplicate Customer',
                'email' => 'prevent-file-duplicate@example.test',
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['rows.0.external_id']);
});

it('19. import rejects existing customers by woo mapping as duplicate preview rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Mapped Customer',
    ]);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    DB::table('external_customer_mappings')->insert([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => '401',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '401',
                'external_source' => 'woocommerce',
                'name' => 'Mapped Customer Updated',
                'email' => 'mapped@example.test',
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['rows.0.external_id']);

    expect(DB::table('customers')->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('20. import falls back to email match', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Email Match Customer',
    ]);

    DB::table('customer_contacts')->insert([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'first_name' => 'Email',
        'last_name' => 'Match',
        'email' => 'match@example.test',
        'phone' => null,
        'role' => null,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '501',
                'name' => 'Email Match Import',
                'email' => 'match@example.test',
            ],
        ],
    ])->assertCreated();

    expect(DB::table('customers')->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('21. import creates external customer mappings', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '601',
                'name' => 'Mapped Import Customer',
                'email' => 'mapped-import@example.test',
            ],
        ],
    ])->assertCreated();

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Mapped Import Customer')
        ->value('id');

    $this->assertDatabaseHas('external_customer_mappings', [
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'source' => 'woocommerce',
        'external_customer_id' => '601',
    ]);
});

it('21a. imported contact stores parsed first and last name', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '611',
                'name' => 'Parker Preview',
                'email' => 'preview@example.test',
                'phone' => '555-0111',
            ],
        ],
    ])->assertCreated();

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Parker Preview')
        ->value('id');

    $this->assertDatabaseHas('customer_contacts', [
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'first_name' => 'Parker',
        'last_name' => 'Preview',
    ]);
});

it('21b. imported contact stores email when available', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '612',
                'name' => 'Taylor Contact',
                'email' => 'taylor@example.test',
            ],
        ],
    ])->assertCreated();

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Taylor Contact')
        ->value('id');

    $this->assertDatabaseHas('customer_contacts', [
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'email' => 'taylor@example.test',
    ]);
});

it('21c. imported contact stores phone when available', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '613',
                'name' => 'Jordan Contact',
                'email' => 'jordan@example.test',
                'phone' => '555-0113',
            ],
        ],
    ])->assertCreated();

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Jordan Contact')
        ->value('id');

    $this->assertDatabaseHas('customer_contacts', [
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'phone' => '555-0113',
    ]);
});

it('22. import does not duplicate customers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    $payload = [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '701',
                'name' => 'No Duplicate Customer',
                'email' => 'no-duplicate@example.test',
            ],
        ],
    ];

    ($this->storeImport)($user, $payload)->assertCreated();
    ($this->storeImport)($user, $payload)->assertStatus(422)
        ->assertJsonValidationErrors(['rows.0.external_id']);

    expect(DB::table('customers')->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('22a. woo import skips contact creation when no human name can be extracted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '711',
                'name' => 'Mononym',
                'email' => 'mononym@example.test',
                'phone' => '555-0711',
            ],
        ],
    ])->assertCreated();

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Mononym')
        ->value('id');

    expect(DB::table('customer_contacts')->where('customer_id', $customerId)->count())->toBe(0);
});

it('22b. woo import does not create partial nameless contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '712',
                'name' => 'Singleword',
                'email' => 'singleword@example.test',
            ],
        ],
    ])->assertCreated();

    expect(DB::table('customer_contacts')->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('22c. manual customer creation does not auto create a contact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postCustomer)($user, [
        'name' => 'Manual Customer',
        'notes' => 'No auto contact expected',
    ])->assertCreated();

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Manual Customer')
        ->value('id');

    expect(DB::table('customer_contacts')->where('customer_id', $customerId)->count())->toBe(0);
});

it('22d. re importing the same woo customer is rejected before duplicating contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    $payload = [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '713',
                'name' => 'Repeat Person',
                'email' => 'repeat@example.test',
                'phone' => '555-0713',
            ],
        ],
    ];

    ($this->storeImport)($user, $payload)->assertCreated();
    ($this->storeImport)($user, $payload)->assertStatus(422)
        ->assertJsonValidationErrors(['rows.0.external_id']);

    $customerId = (int) DB::table('customers')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Repeat Person')
        ->value('id');

    expect(DB::table('customer_contacts')->where('customer_id', $customerId)->count())->toBe(1);
});

it('23. import is tenant scoped', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA, ['email' => 'tenant-a@example.test']);
    $userB = ($this->makeUser)($tenantB, ['email' => 'tenant-b@example.test']);

    ($this->grantPermissions)($userA, ['sales-customers-manage', 'system-users-manage']);
    ($this->grantPermissions)($userB, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenantA);
    ($this->connectWooCommerce)($tenantB);

    ($this->storeImport)($userA, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '801',
                'name' => 'Tenant A Imported Customer',
                'email' => 'shared@example.test',
            ],
        ],
    ])->assertCreated();

    ($this->storeImport)($userB, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '801',
                'name' => 'Tenant B Imported Customer',
                'email' => 'shared@example.test',
            ],
        ],
    ])->assertCreated();

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenantA->id,
        'name' => 'Tenant A Imported Customer',
    ]);
    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenantB->id,
        'name' => 'Tenant B Imported Customer',
    ]);
});

it('24. import handles empty woo response', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);
    ($this->fakeWooCustomers)([]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.rows', []);
});

it('25. import validation returns json 422', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => '',
                'name' => '',
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors',
        ]);
});

it('26. import errors do not redirect', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $response = ($this->previewImport)($user);

    expect(str_starts_with((string) $response->headers->get('content-type'), 'application/json'))->toBeTrue()
        ->and($response->status())->toBe(422);
});

it('27. existing products behavior remains untouched', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-crud-config=', false);

    preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

    expect($matches)->toHaveKey(1);

    $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

    expect($config['endpoints']['list'] ?? null)->toBe(route('sales.products.list'))
        ->and($config['endpoints']['create'] ?? null)->toBe(route('sales.products.store'));
});

it('28. customers blade contains no crud toolbar table card or action markup', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));

    expect($customersBlade)->toContain('data-crud-root')
        ->and($customersBlade)->not->toContain('<x-sales.crud-toolbar')
        ->and($customersBlade)->not->toContain('<x-sales.crud-action-cell')
        ->and($customersBlade)->not->toContain('No customers found.')
        ->and($customersBlade)->not->toContain('data-crud-toolbar-mobile')
        ->and($customersBlade)->not->toContain('data-crud-action-cell')
        ->and($customersBlade)->not->toContain('x-for="customer in customers"')
        ->and($customersBlade)->not->toContain('toggleSort(column)');
});

it('29. customers page root exposes a dedicated import config contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $response = ($this->getCustomersIndex)($user)
        ->assertOk()
        ->assertSee('data-import-config=', false);

    $config = ($this->extractImportConfig)($response);

    expect($config['resource'] ?? null)->toBe('customers')
        ->and($config['endpoints']['preview'] ?? null)->toBe(route('sales.customers.import.preview'))
        ->and($config['endpoints']['store'] ?? null)->toBe(route('sales.customers.import.store'));
});

it('30. customers import endpoints are exposed through data import config instead of inline page js', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $response = ($this->getCustomersIndex)($user);
    $config = ($this->extractImportConfig)($response);
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($config['endpoints']['preview'] ?? null)->toBe(route('sales.customers.import.preview'))
        ->and($config['endpoints']['store'] ?? null)->toBe(route('sales.customers.import.store'))
        ->and($customersScript)->not->toContain('previewUrl')
        ->and($customersScript)->not->toContain('importUrl');
});

it('31. customers import config includes customer specific labels and source contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['labels']['title'] ?? null)->toBe('Import Customers')
        ->and($config['labels']['source'] ?? null)->toBe('Source')
        ->and($config['labels']['submit'] ?? null)->toBe('Import Selected')
        ->and($config['labels']['loadingPreviewFile'] ?? null)->toBe('Loading file preview...')
        ->and(collect($config['sources'] ?? [])->pluck('value')->all())->toContain('woocommerce');
});

it('31a. customers import config includes file upload as a shared source option', function () {
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

it('31b. customers blade uses the shared hidden file input and cached file source rendering', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));

    expect($customersBlade)->toContain('x-ref="importFileInput"')
        ->and($customersBlade)->toContain('type="file"')
        ->and($customersBlade)->toContain('accept=".csv,text/csv"')
        ->and($customersBlade)->toContain('x-on:change="handleLocalFileChange($event)"')
        ->and($customersBlade)->toContain('<template x-for="fileSource in cachedFileSources" :key="fileSource.value">')
        ->and($customersBlade)->toContain('x-text="errors.file[0]"');
});

it('31c. shared import module keeps uploaded filenames available as cached source labels for the current session', function () {
    $importModuleSource = file_get_contents(base_path('resources/js/lib/import-module.js'));

    expect($importModuleSource)->toContain('selectedFileName: \'\'')
        ->and($importModuleSource)->toContain('cachedFileSources: []')
        ->and($importModuleSource)->toContain('this.selectedFileName = file.name || \'\';')
        ->and($importModuleSource)->toContain('label: this.selectedFileName')
        ->and($importModuleSource)->toContain('this.selectedSource = value;')
        ->and($importModuleSource)->toContain('this.selectedFileName = fileSource.label;');
});

it('31d. import module source no longer uses optional chaining assignment patterns', function () {
    $importModuleSource = file_get_contents(base_path('resources/js/lib/import-module.js'));

    expect($importModuleSource)->not->toContain('?.');
});

it('32. customers blade no longer contains the old custom import slide over markup', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));

    expect($customersBlade)->not->toContain('data-customers-import-panel')
        ->and($customersBlade)->not->toContain('Load Preview')
        ->and($customersBlade)->not->toContain('Confirm Import')
        ->and($customersBlade)->not->toContain('x-text="importPreviewError"')
        ->and($customersBlade)->not->toContain('x-text="importErrors.source[0]"');
});

it('32a. shared import preview no longer defaults missing active state to inactive', function () {
    $importModuleSource = file_get_contents(base_path('resources/js/lib/import-module.js'));

    expect($importModuleSource)->toContain("Object.prototype.hasOwnProperty.call(row, 'is_active')")
        ->and($importModuleSource)->toContain('has_active_state:')
        ->and($importModuleSource)->toContain('if (!this.rowHasActiveState(row)) {')
        ->and($importModuleSource)->not->toContain("return row.is_active ? 'Active' : 'Inactive';");
});

it('32b. customers import preview rows use the shared compact row contract', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));
    $importModuleSource = file_get_contents(base_path('resources/js/lib/import-module.js'));

    expect($customersBlade)->toContain('x-text="previewPrimaryLabel(row)"')
        ->and($customersBlade)->toContain('x-text="previewSecondaryLabel(row)"')
        ->and($customersBlade)->not->toContain('tracking-wide text-gray-500">Email')
        ->and($customersBlade)->not->toContain('tracking-wide text-gray-500">Phone')
        ->and($customersBlade)->not->toContain('tracking-wide text-gray-500">Address')
        ->and($importModuleSource)->toContain('previewPrimaryLabel(row)')
        ->and($importModuleSource)->toContain('previewSecondaryLabel(row)')
        ->and($importModuleSource)->toContain('rowHasSecondaryLabel(row)');
});

it('32c. woo customer preview uses shipping city and address fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);
    ($this->fakeWooCustomers)([
        [
            'id' => 203,
            'email' => 'shipping@example.test',
            'first_name' => 'Shipping',
            'last_name' => 'Preview',
            'username' => 'shipping-preview',
            'billing' => [
                'address_1' => '50 Billing Street',
                'address_2' => 'Suite 2',
                'city' => 'New York',
                'state' => 'NY',
                'postcode' => '10001',
                'country' => 'US',
            ],
            'shipping' => [
                'address_1' => '123 King Street West',
                'address_2' => 'Suite 400',
                'city' => 'Toronto',
                'state' => 'ON',
                'postcode' => 'M5V 1J2',
                'country' => 'CA',
            ],
        ],
    ]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.0.address_line_1', '123 King Street West')
        ->assertJsonPath('data.rows.0.city', 'Toronto')
        ->assertJsonPath('data.rows.0.region', 'ON')
        ->assertJsonPath('data.rows.0.country_code', 'CA');
});

it('32d. woo customer preview does not fall back to billing city when shipping city is absent', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);
    ($this->fakeWooCustomers)([
        [
            'id' => 204,
            'email' => 'no-ship-city@example.test',
            'first_name' => 'NoShip',
            'last_name' => 'City',
            'username' => 'no-ship-city',
            'billing' => [
                'address_1' => '90 Billing Avenue',
                'city' => 'New York',
                'state' => 'NY',
                'postcode' => '10002',
                'country' => 'US',
            ],
            'shipping' => [
                'address_1' => '789 Ship To Road',
                'address_2' => '',
                'city' => '',
                'state' => 'ON',
                'postcode' => 'M4B 1B3',
                'country' => 'CA',
            ],
        ],
    ]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.0.address_line_1', '789 Ship To Road')
        ->assertJsonPath('data.rows.0.city', '')
        ->assertJsonMissing(['city' => 'New York']);
});

it('32e. customers import config hides duplicates by default like products', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->getCustomersIndex)($user));

    expect($config['rowBehavior']['hideDuplicatesByDefault'] ?? null)->toBeTrue()
        ->and($config['rowBehavior']['selectVisibleNonDuplicateRowsOnly'] ?? null)->toBeTrue()
        ->and($config['rowBehavior']['submitSelectedVisibleRowsOnly'] ?? null)->toBeTrue();
});

it('33. customers blade uses the bounded height mount shell pattern', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));

    expect($customersBlade)->toContain('flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden')
        ->and($customersBlade)->toContain('mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8')
        ->and($customersBlade)->toContain('flex h-full min-h-0 flex-1 flex-col')
        ->and($customersBlade)->toContain('data-crud-root');
});

it('34. customers crud config includes the shared renderer contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['resource'] ?? null)->toBe('customers')
        ->and($config['rowDisplay'] ?? null)->toBeArray()
        ->and($config['mobileCard'] ?? null)->toBeArray()
        ->and($config['actions'] ?? null)->toBeArray()
        ->and($config['permissions'] ?? null)->toBeArray();
});

it('35. products and customers render identical toolbar and action contracts through the shared renderer', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));
    $productsScript = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-import-button')
        ->and($rendererSource)->toContain('data-crud-toolbar-create-button')
        ->and($rendererSource)->toContain('data-crud-records-scroll')
        ->and($rendererSource)->toContain('data-crud-action-trigger')
        ->and($rendererSource)->toContain('data-crud-action-menu')
        ->and($productsScript)->toContain('mountCrudRenderer(')
        ->and($customersScript)->toContain('mountCrudRenderer(');
});

it('36. customer action menu still exposes edit and archive behavior through configured actions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($config['actions'] ?? [])->toBe([
        ['id' => 'edit', 'label' => 'Edit', 'tone' => 'default'],
        ['id' => 'archive', 'label' => 'Archive', 'tone' => 'warning'],
    ])
        ->and($customersScript)->toContain("action.id === 'edit' ? 'openEdit(record)'")
        ->and($customersScript)->toContain("action.id === 'archive' ? 'archive(record)'")
        ->and($customersScript)->toContain('openEdit(record)')
        ->and($customersScript)->toContain('archive(record)');
});

it('37. customers page module imports and parses the shared import config', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersScript)->toContain("import { parseImportConfig } from '../lib/import-config';")
        ->and($customersScript)->toContain('const importConfig = parseImportConfig(rootEl);');
});

it('38. customers page module delegates import behavior to the shared import module', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersScript)->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($customersScript)->toContain('const importModule = createImportModule({')
        ->and($customersScript)->toContain('config: importConfig,');
});

it('39. customers page module no longer owns inline import state and preview handlers', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersScript)->not->toContain('isImportPanelOpen:')
        ->and($customersScript)->not->toContain('previewRows:')
        ->and($customersScript)->not->toContain('importPreviewError:')
        ->and($customersScript)->not->toContain('handleSourceChange() {')
        ->and($customersScript)->not->toContain('async loadPreview() {')
        ->and($customersScript)->not->toContain('async submitImport() {');
});

it('40. customers index payload no longer embeds customer records as source of truth', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);
    ($this->createCustomer)($tenant, ['name' => 'Payload Hidden Customer']);

    $response = ($this->getCustomersIndex)($user);
    $payload = ($this->extractPayload)($response, 'sales-customers-index-payload');

    expect($payload)->not->toHaveKey('customers')
        ->and($payload['storeUrl'] ?? null)->toBe(route('sales.customers.store'));
});

it('41. import and create buttons still open page specific panels through configured callbacks', function () {
    $productsScript = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($productsScript)->toContain("createHandler: 'openCreatePanel()'")
        ->and($productsScript)->toContain("importHandler: 'openImportPanel()'")
        ->and($customersScript)->toContain("createHandler: 'openCreatePanel()'")
        ->and($customersScript)->toContain("importHandler: 'openImportPanel()'");
});

it('42. customers action dropdown still works through configured actions', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($customersScript)->toContain("action.id === 'edit' ? 'openEdit(record)'")
        ->and($customersScript)->toContain("action.id === 'archive' ? 'archive(record)'")
        ->and($rendererSource)->toContain('data-crud-action-menu')
        ->and($rendererSource)->toContain('data-crud-action-item-${escapeHtml(action.id)}');
});

it('43. customers crud config does not advertise export while no customer export route exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['labels']['exportTitle'] ?? null)->toBe('Export Customers')
        ->and($config['labels']['exportAriaLabel'] ?? null)->toBe('Export Customers')
        ->and($config['permissions']['showExport'] ?? null)->toBeFalse();
});

it('44. customers page module no longer wires the fake export handler', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersScript)->not->toContain("exportHandler: 'handleExportUnavailable()'")
        ->and($customersScript)->not->toContain("export: 'handleExportUnavailable()'")
        ->and($customersScript)->not->toContain('handleExportUnavailable() {');
});

it('45. shared crud renderer still owns the optional export toolbar markup', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));

    expect($rendererSource)->toContain('data-crud-toolbar-export-button')
        ->and($customersBlade)->not->toContain('data-crud-toolbar-export-button')
        ->and($customersBlade)->not->toContain('Export Customers');
});
