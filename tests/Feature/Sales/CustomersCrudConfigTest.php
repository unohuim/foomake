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

        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customers Crud Customer ' . $customerCounter,
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
        ->assertSee('data-customers-import-panel', false);
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
        ->assertJsonPath('data.rows.0.name', 'Parker Preview');
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

it('19. import matches existing customers by woo mapping', function () {
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
                'name' => 'Mapped Customer Updated',
                'email' => 'mapped@example.test',
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.imported_count', 1);

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
    ($this->storeImport)($user, $payload)->assertCreated();

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

it('22d. re importing the same woo customer does not duplicate contacts', function () {
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
    ($this->storeImport)($user, $payload)->assertCreated();

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

it('29. customers crud config includes the shared renderer contract', function () {
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

it('30. products and customers render identical toolbar and action contracts through the shared renderer', function () {
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

it('31. customer action menu still exposes edit and archive behavior through configured actions', function () {
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

it('32. customer import preview button loading contract prevents duplicate requests', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersBlade)->toContain('x-bind:disabled="isLoadingPreview"')
        ->and($customersBlade)->toContain("x-on:click=\"loadPreview()\"")
        ->and($customersScript)->toContain('if (this.isLoadingPreview) {')
        ->and($customersScript)->toContain('return;')
        ->and($customersScript)->toContain('this.isLoadingPreview = true;')
        ->and($customersScript)->toContain('this.isLoadingPreview = false;');
});

it('33. customer import preview errors render in the slideout without requiring retry', function () {
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersBlade)->toContain('x-text="importPreviewError"')
        ->and($customersBlade)->toContain('x-text="importErrors.source[0]"')
        ->and($customersScript)->toContain('const parseJsonResponse = async (response) => {')
        ->and($customersScript)->toContain('this.importPreviewError =')
        ->and($customersScript)->toContain('this.importErrors.source =')
        ->and($customersScript)->toContain('await parseJsonResponse(response)');
});

it('34. import and create buttons still open page specific panels through configured callbacks', function () {
    $productsScript = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($productsScript)->toContain("createHandler: 'openCreatePanel()'")
        ->and($productsScript)->toContain("importHandler: 'openImportPanel()'")
        ->and($customersScript)->toContain("createHandler: 'openCreatePanel()'")
        ->and($customersScript)->toContain("importHandler: 'openImportPanel()'");
});

it('35. customers action dropdown still works through configured actions', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));

    expect($customersScript)->toContain("action.id === 'edit' ? 'openEdit(record)'")
        ->and($customersScript)->toContain("action.id === 'archive' ? 'archive(record)'")
        ->and($rendererSource)->toContain('data-crud-action-menu')
        ->and($rendererSource)->toContain('data-crud-action-item-${escapeHtml(action.id)}');
});

it('36. customers crud config can enable export through shared labels and permissions only', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['sales-customers-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->getCustomersIndex)($user));

    expect($config['labels']['exportTitle'] ?? null)->toBe('Export Customers')
        ->and($config['labels']['exportAriaLabel'] ?? null)->toBe('Export Customers')
        ->and($config['permissions']['showExport'] ?? null)->toBeTrue();
});

it('37. customers page module wires export through the shared crud renderer contract', function () {
    $customersScript = file_get_contents(base_path('resources/js/pages/sales-customers-index.js'));

    expect($customersScript)->toContain("exportHandler: 'handleExportUnavailable()'")
        ->and($customersScript)->toContain("export: 'handleExportUnavailable()'")
        ->and($customersScript)->toContain('handleExportUnavailable() {');
});

it('38. shared crud renderer owns the customers export toolbar button markup', function () {
    $rendererSource = file_get_contents(base_path('resources/js/lib/crud-page.js'));
    $customersBlade = file_get_contents(base_path('resources/views/sales/customers/index.blade.php'));

    expect($rendererSource)->toContain('data-crud-toolbar-export-button')
        ->and($customersBlade)->not->toContain('data-crud-toolbar-export-button')
        ->and($customersBlade)->not->toContain('Export Customers');
});
