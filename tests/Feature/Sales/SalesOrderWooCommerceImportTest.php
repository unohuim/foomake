<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\ExternalCustomerMapping;
use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->contactCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Sales Orders Import Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Import User ' . $this->userCounter,
            'email' => 'sales-orders-import-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
            $role = Role::query()->create(['name' => 'sales-orders-import-role-' . $this->roleCounter]);

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

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        $customer = Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Import Customer ' . $this->customerCounter,
            'status' => Customer::STATUS_ACTIVE,
            'city' => 'Toronto',
        ], $attributes));

        $this->customerCounter++;

        return $customer;
    };

    $this->makeContact = function (Tenant $tenant, Customer $customer, array $attributes = []): CustomerContact {
        $contact = CustomerContact::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'first_name' => 'Import',
            'last_name' => 'Contact ' . $this->contactCounter,
            'email' => 'sales-orders-import-contact-' . $this->contactCounter . '@example.test',
            'phone' => null,
            'role' => null,
            'is_primary' => true,
        ], $attributes));

        $this->contactCounter++;

        return $contact;
    };

    $this->makeItem = function (Tenant $tenant, array $attributes = []): Item {
        $uomCategoryId = \DB::table('uom_categories')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Import Category ' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = \DB::table('uoms')->insertGetId([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $uomCategoryId,
            'name' => 'Sales Orders Import UoM ' . $this->itemCounter,
            'symbol' => 'soi-' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Import Item ' . $this->itemCounter,
            'base_uom_id' => $uomId,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1899,
            'default_price_currency_code' => 'USD',
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->previewImport = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.orders.import.preview'), array_merge([
            'source' => 'woocommerce',
        ], $payload));
    };

    $this->storeImport = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.orders.import.store'), $payload);
    };

    $this->defaultWooOrderRow = function (array $overrides = []): array {
        return array_merge([
            'external_id' => '1001',
            'external_source' => 'woocommerce',
            'external_status' => 'processing',
            'date' => '2026-05-11',
            'customer' => [
                'external_id' => '501',
                'name' => 'Ada Buyer',
                'email' => 'ada@example.test',
                'phone' => '555-4001',
                'address_line_1' => '123 Queen St',
                'address_line_2' => '',
                'city' => 'Toronto',
                'region' => 'ON',
                'postal_code' => 'M5H 2N2',
                'country_code' => 'CA',
            ],
            'lines' => [
                [
                    'external_id' => 'line-1001-1',
                    'product_external_id' => 'sku-2001',
                    'name' => 'Woo Line Product',
                    'quantity' => '2.000000',
                    'unit_price_cents' => 1500,
                    'currency_code' => 'USD',
                ],
            ],
            'is_duplicate' => false,
            'selected' => true,
        ], $overrides);
    };
});

it('1. order import preview requires authentication', function () {
    $this->postJson(route('sales.orders.import.preview'), ['source' => 'woocommerce'])
        ->assertUnauthorized();
});

it('2. order import store requires authentication', function () {
    $this->postJson(route('sales.orders.import.store'), ['source' => 'woocommerce', 'rows' => []])
        ->assertUnauthorized();
});

it('3. order import preview denies users without sales order permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->previewImport)($user)->assertForbidden();
});

it('4. order import store denies users without sales order permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertForbidden();
});

it('5. order import preview accepts a Woo payload fixture and returns normalized rows', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);
    ($this->connectWooCommerce)($tenant);

    Http::fake([
        'https://store.example.test/wp-json/wc/v3/orders*' => Http::response([
            [
                'id' => 1001,
                'status' => 'processing',
                'date_created' => '2026-05-11T10:00:00',
                'customer_id' => 501,
                'billing' => [
                    'first_name' => 'Ada',
                    'last_name' => 'Buyer',
                    'email' => 'ada@example.test',
                    'phone' => '555-4001',
                    'address_1' => '123 Queen St',
                    'address_2' => '',
                    'city' => 'Toronto',
                    'state' => 'ON',
                    'postcode' => 'M5H 2N2',
                    'country' => 'CA',
                ],
                'line_items' => [
                    [
                        'id' => 1,
                        'product_id' => 2001,
                        'name' => 'Woo Line Product',
                        'quantity' => 2,
                        'price' => 15,
                    ],
                ],
            ],
        ], 200),
    ]);

    ($this->previewImport)($user)
        ->assertOk()
        ->assertJsonPath('data.rows.0.external_id', '1001')
        ->assertJsonPath('data.rows.0.external_status', 'processing')
        ->assertJsonPath('data.rows.0.date', '2026-05-11');
});

it('6. import store creates a sales order header', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(SalesOrder::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('7. import store creates sales order lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(\DB::table('sales_order_lines')->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('8. import creates a missing customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(Customer::query()->where('tenant_id', $tenant->id)->where('name', 'Ada Buyer')->exists())->toBeTrue();
});

it('9. import creates the first customer contact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    $customer = Customer::query()->where('tenant_id', $tenant->id)->where('name', 'Ada Buyer')->firstOrFail();

    expect(CustomerContact::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(CustomerContact::query()->where('customer_id', $customer->id)->value('is_primary'))->toBeTrue();
});

it('10. import maps an existing customer by external identity', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Existing External Customer']);
    ExternalCustomerMapping::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'source' => 'woocommerce',
        'external_customer_id' => '501',
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(Customer::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(SalesOrder::query()->firstOrFail()->customer_id)->toBe($customer->id);
});

it('11. import maps an existing customer by existing import matching rule when external identity is missing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Existing Email Match']);
    ($this->makeContact)($tenant, $customer, ['email' => 'ada@example.test']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $row = ($this->defaultWooOrderRow)([
        'customer' => array_merge(($this->defaultWooOrderRow)()['customer'], ['external_id' => '']),
    ]);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [$row],
    ])->assertCreated();

    expect(Customer::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(SalesOrder::query()->firstOrFail()->customer_id)->toBe($customer->id);
});

it('12. import maps an existing product by external source and external id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $item = ($this->makeItem)($tenant, [
        'name' => 'Existing Woo Product',
        'external_source' => 'woocommerce',
        'external_id' => 'sku-2001',
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(\DB::table('sales_order_lines')->value('item_id'))->toBe($item->id);
});

it('13. import creates a missing sellable item from line metadata', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(Item::query()->where('tenant_id', $tenant->id)->where('external_id', 'sku-2001')->exists())->toBeTrue();
});

it('14. created missing sellable item is inactive', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(Item::query()->where('tenant_id', $tenant->id)->where('external_id', 'sku-2001')->value('is_active'))->toBeFalse();
});

it('15. created missing sellable item does not create a fulfillment recipe', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    $item = Item::query()->where('tenant_id', $tenant->id)->where('external_id', 'sku-2001')->firstOrFail();

    expect(\DB::table('recipes')->where('item_id', $item->id)->exists())->toBeFalse();
});

it('16. imported order stores external source, external id, external status, and synced timestamp', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    $order = SalesOrder::query()->firstOrFail();

    expect($order->external_source)->toBe('woocommerce')
        ->and($order->external_id)->toBe('1001')
        ->and($order->external_status)->toBe('processing')
        ->and($order->external_status_synced_at)->not->toBeNull();
});

it('17. woo status completed maps to local completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)(['external_status' => 'completed'])],
    ])->assertCreated();

    expect(SalesOrder::query()->firstOrFail()->status)->toBe(SalesOrder::STATUS_COMPLETED);
});

it('18. woo cancelled style statuses map to local cancelled', function () {
    $statuses = ['cancelled', 'canceled', 'refunded', 'failed'];

    foreach ($statuses as $status) {
        \DB::table('sales_order_lines')->delete();
        SalesOrder::query()->delete();
        Item::query()->delete();
        CustomerContact::query()->delete();
        Customer::query()->delete();

        $tenant = ($this->makeTenant)(['tenant_name' => 'Tenant ' . $status]);
        $user = ($this->makeUser)($tenant, ['email' => $status . '@example.test']);
        ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

        ($this->storeImport)($user, [
            'source' => 'woocommerce',
            'rows' => [($this->defaultWooOrderRow)(['external_id' => 'id-' . $status, 'external_status' => $status])],
        ])->assertCreated();

        expect(SalesOrder::query()->latest('id')->firstOrFail()->status)->toBe(SalesOrder::STATUS_CANCELLED);
    }
});

it('19. woo open style statuses map to local open', function () {
    $statuses = ['processing', 'pending payment', 'pending', 'on hold', 'on-hold', 'draft', 'new'];

    foreach ($statuses as $status) {
        \DB::table('sales_order_lines')->delete();
        SalesOrder::query()->delete();
        Item::query()->delete();
        CustomerContact::query()->delete();
        Customer::query()->delete();

        $tenant = ($this->makeTenant)(['tenant_name' => 'Tenant open ' . $status]);
        $user = ($this->makeUser)($tenant, ['email' => 'open-' . md5($status) . '@example.test']);
        ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

        ($this->storeImport)($user, [
            'source' => 'woocommerce',
            'rows' => [($this->defaultWooOrderRow)(['external_id' => 'id-open-' . md5($status), 'external_status' => $status])],
        ])->assertCreated();

        expect(SalesOrder::query()->latest('id')->firstOrFail()->status)->toBe(SalesOrder::STATUS_OPEN);
    }
});

it('20. imported completed, cancelled, and open orders create no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    foreach (['completed', 'cancelled', 'processing'] as $status) {
        ($this->storeImport)($user, [
            'source' => 'woocommerce',
            'rows' => [($this->defaultWooOrderRow)(['external_id' => 'stock-' . $status, 'external_status' => $status])],
        ])->assertCreated();
    }

    expect(\DB::table('stock_moves')->count())->toBe(0);
});

it('21. reimport updates only external status fields and does not change local status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now()->subDay(),
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)(['external_status' => 'completed'])],
    ])->assertOk();

    $order->refresh();

    expect($order->status)->toBe(SalesOrder::STATUS_OPEN)
        ->and($order->external_status)->toBe('completed');
});

it('22. duplicate preview flags existing external identity and keeps duplicate rows visible but unselected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])
        ->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.0.external_id', '1001');
});

it('23. store endpoint prevents duplicate order creation and duplicate matching is tenant scoped', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    $customerA = ($this->makeCustomer)($tenantA);
    $customerB = ($this->makeCustomer)($tenantB);
    SalesOrder::query()->create([
        'tenant_id' => $tenantA->id,
        'customer_id' => $customerA->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'woocommerce',
        'external_id' => '1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);
    ($this->grantPermissions)($userA, ['sales-sales-orders-manage', 'system-users-manage']);
    ($this->grantPermissions)($userB, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($userA, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertOk();

    ($this->storeImport)($userB, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)()],
    ])->assertCreated();

    expect(SalesOrder::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(SalesOrder::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count())->toBe(1);
});

it('24. unsupported woo status is handled safely instead of silently mapping wrong', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'woocommerce',
        'rows' => [($this->defaultWooOrderRow)(['external_status' => 'mystery-status'])],
    ])->assertStatus(422);
});
