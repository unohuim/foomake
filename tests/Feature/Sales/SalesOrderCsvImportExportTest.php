<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Schema;

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
            'tenant_name' => 'Sales Orders CSV Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders CSV User ' . $this->userCounter,
            'email' => 'sales-orders-csv-user-' . $this->userCounter . '@example.test',
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
            $role = Role::query()->create(['name' => 'sales-orders-csv-role-' . $this->roleCounter]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        $customer = Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders CSV Customer ' . $this->customerCounter,
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
            'first_name' => 'Primary',
            'last_name' => 'Contact ' . $this->contactCounter,
            'email' => 'sales-orders-csv-contact-' . $this->contactCounter . '@example.test',
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
            'name' => 'Sales Orders CSV Category ' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = \DB::table('uoms')->insertGetId([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $uomCategoryId,
            'name' => 'Sales Orders CSV UoM ' . $this->itemCounter,
            'symbol' => 'socsv-' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders CSV Item ' . $this->itemCounter,
            'base_uom_id' => $uomId,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1500,
            'default_price_currency_code' => 'USD',
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeOrder = function (Tenant $tenant, Customer $customer, ?CustomerContact $contact = null, array $attributes = []): SalesOrder {
        return SalesOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'status' => SalesOrder::STATUS_OPEN,
            'order_date' => '2026-05-14',
        ], $attributes));
    };

    $this->makeLine = function (Tenant $tenant, SalesOrder $order, Item $item, array $attributes = []): void {
        \DB::table('sales_order_lines')->insert(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'external_id' => null,
            'quantity' => '2.000000',
            'unit_price_cents' => 1500,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '3000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    };

    $this->exportOrders = function (User $user, array $query = []): TestResponse {
        return $this->actingAs($user)->get(route('sales.orders.export', $query));
    };

    $this->previewImport = function (User $user, array $payload): TestResponse {
        return $this->actingAs($user)->postJson(route('sales.orders.import.preview'), $payload);
    };

    $this->storeImport = function (User $user, array $payload): TestResponse {
        return $this->actingAs($user)->postJson(route('sales.orders.import.store'), $payload);
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

    $this->csvRecords = function (TestResponse $response): array {
        $rows = ($this->csvRows)($response);
        $header = $rows[0] ?? [];
        $dataRows = array_slice($rows, 1);

        return array_map(static fn (array $row): array => array_combine($header, $row), $dataRows);
    };

    $this->groupPreviewRowsFromCsvRecords = function (array $records): array {
        $grouped = [];

        foreach ($records as $record) {
            $externalSource = trim((string) ($record['external_source'] ?? ''));
            $orderExternalId = trim((string) ($record['order_external_id'] ?? ''));
            $groupKey = mb_strtolower($externalSource) . '|' . $orderExternalId;

            if (! array_key_exists($groupKey, $grouped)) {
                $grouped[$groupKey] = [
                    'external_id' => $orderExternalId,
                    'external_source' => $externalSource,
                    'external_status' => (string) ($record['external_status'] ?? ''),
                    'date' => (string) ($record['order_date'] ?? ''),
                    'contact_name' => (string) ($record['contact_name'] ?? ''),
                    'customer' => [
                        'external_id' => '',
                        'name' => (string) ($record['customer_name'] ?? ''),
                        'email' => null,
                        'phone' => null,
                        'address_line_1' => null,
                        'address_line_2' => null,
                        'city' => (string) ($record['city'] ?? ''),
                        'region' => null,
                        'postal_code' => null,
                        'country_code' => null,
                    ],
                    'lines' => [],
                ];
            }

            $grouped[$groupKey]['lines'][] = [
                'external_id' => (string) ($record['line_external_id'] ?? ''),
                'product_external_id' => (string) ($record['product_external_id'] ?? ''),
                'name' => (string) ($record['product_name'] ?? ''),
                'quantity' => (string) ($record['quantity'] ?? ''),
                'unit_price' => (string) ($record['unit_price'] ?? ''),
            ];
        }

        return array_values($grouped);
    };

    $this->csvOrderRow = function (array $overrides = []): array {
        return array_merge([
            'external_id' => 'order-1001',
            'external_source' => 'legacy_csv',
            'external_status' => 'processing',
            'date' => '2026-05-11',
            'contact_name' => 'Jane Buyer',
            'customer' => [
                'external_id' => '',
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
                    'name' => 'Imported Line Product',
                    'quantity' => '2.000000',
                    'unit_price' => '15.00',
                ],
            ],
            'is_duplicate' => false,
            'selected' => true,
        ], $overrides);
    };
});

it('1. export outputs one csv row per sales order line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['external_source' => 'legacy_csv', 'external_id' => 'order-1001']);
    $itemA = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-1']);
    $itemB = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-2']);
    ($this->makeLine)($tenant, $order, $itemA, ['external_id' => 'line-1']);
    ($this->makeLine)($tenant, $order, $itemB, ['external_id' => 'line-2']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $records = ($this->csvRecords)((($this->exportOrders)($user))->assertOk());

    expect($records)->toHaveCount(2);
});

it('2. export repeats order header fields on every line row', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Repeat Customer', 'city' => 'Ottawa']);
    $contact = ($this->makeContact)($tenant, $customer, ['first_name' => 'Repeat', 'last_name' => 'Buyer']);
    $order = ($this->makeOrder)($tenant, $customer, $contact, [
        'order_date' => '2026-05-12',
        'status' => SalesOrder::STATUS_OPEN,
        'external_source' => 'legacy_csv',
        'external_id' => 'order-repeat',
        'external_status' => 'processing',
    ]);
    $itemA = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-a']);
    $itemB = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-b']);
    ($this->makeLine)($tenant, $order, $itemA, ['external_id' => 'line-a']);
    ($this->makeLine)($tenant, $order, $itemB, ['external_id' => 'line-b']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $records = ($this->csvRecords)((($this->exportOrders)($user))->assertOk());

    expect($records[0]['external_source'] ?? null)->toBe('legacy_csv')
        ->and($records[1]['external_source'] ?? null)->toBe('legacy_csv')
        ->and($records[0]['order_external_id'] ?? null)->toBe('order-repeat')
        ->and($records[1]['order_external_id'] ?? null)->toBe('order-repeat')
        ->and($records[0]['order_date'] ?? null)->toBe('2026-05-12')
        ->and($records[1]['order_date'] ?? null)->toBe('2026-05-12')
        ->and($records[0]['customer_name'] ?? null)->toBe('Repeat Customer')
        ->and($records[1]['customer_name'] ?? null)->toBe('Repeat Customer')
        ->and($records[0]['contact_name'] ?? null)->toBe('Repeat Buyer')
        ->and($records[1]['contact_name'] ?? null)->toBe('Repeat Buyer');
});

it('3. export includes required line fields and formatted unit price', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['external_source' => 'legacy_csv', 'external_id' => 'order-1002']);
    $item = ($this->makeItem)($tenant, ['name' => 'Formatted Product', 'external_source' => 'legacy_csv', 'external_id' => 'product-1002']);
    ($this->makeLine)($tenant, $order, $item, [
        'external_id' => 'line-1002',
        'quantity' => '3.250000',
        'unit_price_cents' => 1899,
        'line_total_cents' => '6171.750000',
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $record = (($this->csvRecords)((($this->exportOrders)($user))->assertOk()))[0];

    expect($record['line_external_id'] ?? null)->toBe('line-1002')
        ->and($record['product_external_id'] ?? null)->toBe('product-1002')
        ->and($record['product_name'] ?? null)->toBe('Formatted Product')
        ->and($record['quantity'] ?? null)->toBe('3.250000')
        ->and($record['unit_price'] ?? null)->toBe('18.99');
});

it('4. export excludes json in csv and internal ids', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['external_source' => 'legacy_csv', 'external_id' => 'order-1003']);
    $item = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-1003']);
    ($this->makeLine)($tenant, $order, $item, ['external_id' => 'line-1003']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $rows = ($this->csvRows)((($this->exportOrders)($user))->assertOk());
    $header = $rows[0] ?? [];
    $content = implode("\n", array_map(static fn (array $row): string => implode(',', $row), $rows));

    expect($header)->not->toContain('id')
        ->and($header)->not->toContain('line_id')
        ->and($header)->not->toContain('sales_order_id')
        ->and($content)->not->toContain('[')
        ->and($content)->not->toContain('{');
});

it('5. export includes one row for each line across multiple orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $orderA = ($this->makeOrder)($tenant, $customer, null, ['external_source' => 'legacy_csv', 'external_id' => 'order-a']);
    $orderB = ($this->makeOrder)($tenant, $customer, null, ['external_source' => 'legacy_csv', 'external_id' => 'order-b']);
    $itemA = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-a']);
    $itemB = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-b']);
    $itemC = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-c']);
    ($this->makeLine)($tenant, $orderA, $itemA, ['external_id' => 'line-a1']);
    ($this->makeLine)($tenant, $orderA, $itemB, ['external_id' => 'line-a2']);
    ($this->makeLine)($tenant, $orderB, $itemC, ['external_id' => 'line-b1']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $records = ($this->csvRecords)((($this->exportOrders)($user))->assertOk());

    expect(collect($records)->pluck('line_external_id')->all())
        ->toBe(['line-b1', 'line-a1', 'line-a2']);
});

it('6. exported csv can be transformed into preview rows accepted by the file upload preview path', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Round Trip Customer', 'city' => 'Montreal']);
    $contact = ($this->makeContact)($tenant, $customer, ['first_name' => 'Round', 'last_name' => 'Trip']);
    $order = ($this->makeOrder)($tenant, $customer, $contact, [
        'order_date' => '2026-05-13',
        'status' => SalesOrder::STATUS_OPEN,
        'external_source' => 'legacy_csv',
        'external_id' => 'order-roundtrip',
        'external_status' => 'processing',
    ]);
    $itemA = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-roundtrip-a']);
    $itemB = ($this->makeItem)($tenant, ['external_source' => 'legacy_csv', 'external_id' => 'product-roundtrip-b']);
    ($this->makeLine)($tenant, $order, $itemA, ['external_id' => 'line-roundtrip-a']);
    ($this->makeLine)($tenant, $order, $itemB, ['external_id' => 'line-roundtrip-b']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $records = ($this->csvRecords)((($this->exportOrders)($user))->assertOk());
    $rows = ($this->groupPreviewRowsFromCsvRecords)($records);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => $rows,
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.rows')
        ->assertJsonPath('data.rows.0.customer.name', 'Round Trip Customer')
        ->assertJsonPath('data.rows.0.customer.city', 'Montreal')
        ->assertJsonPath('data.rows.0.lines.1.external_id', 'line-roundtrip-b');
});

it('7. file upload preview rejects missing external source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [[
            'external_id' => 'order-2001',
            'external_status' => 'processing',
            'date' => '2026-05-11',
            'contact_name' => 'Jane Buyer',
            'customer' => ['name' => 'Missing Source', 'city' => 'Toronto'],
            'lines' => [[
                'name' => 'Item',
                'quantity' => '1.000000',
                'unit_price' => '10.00',
            ]],
        ]],
    ])->assertStatus(422);
});

it('8. file upload preview rejects blank external source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [[
            'external_id' => 'order-2002',
            'external_source' => '   ',
            'external_status' => 'processing',
            'date' => '2026-05-11',
            'contact_name' => 'Jane Buyer',
            'customer' => ['name' => 'Blank Source', 'city' => 'Toronto'],
            'lines' => [[
                'name' => 'Item',
                'quantity' => '1.000000',
                'unit_price' => '10.00',
            ]],
        ]],
    ])->assertStatus(422);
});

it('9. file upload preview rejects mixed external source values in one file', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [
            ($this->csvOrderRow)(),
            ($this->csvOrderRow)([
                'external_id' => 'order-2003',
                'external_source' => 'shopify',
            ]),
        ],
    ])->assertStatus(422);
});

it('10. grouped preview keeps one compact record for repeated line rows of the same order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)([
            'lines' => [
                [
                    'external_id' => 'line-1',
                    'product_external_id' => 'sku-1',
                    'name' => 'First Item',
                    'quantity' => '1.000000',
                    'unit_price' => '10.00',
                ],
                [
                    'external_id' => 'line-2',
                    'product_external_id' => 'sku-2',
                    'name' => 'Second Item',
                    'quantity' => '2.000000',
                    'unit_price' => '5.00',
                ],
            ],
        ])],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.rows')
        ->assertJsonPath('data.rows.0.lines.1.external_id', 'line-2');
});

it('11. multiple grouped orders produce multiple preview records', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [
            ($this->csvOrderRow)(),
            ($this->csvOrderRow)([
                'external_id' => 'order-1002',
                'customer' => array_merge(($this->csvOrderRow)()['customer'], ['name' => 'Other Buyer']),
            ]),
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.rows');
});

it('12. file upload preview body stays empty while order date customer and city remain in the record payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])
        ->assertOk()
        ->assertJsonPath('data.rows.0.date', '2026-05-11')
        ->assertJsonPath('data.rows.0.customer.name', 'Ada Buyer')
        ->assertJsonPath('data.rows.0.customer.city', 'Toronto')
        ->assertJsonMissingPath('data.rows.0.body');
});

it('13. unknown status fails safely for file upload preview', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)(['external_status' => 'mystery-status'])],
    ])->assertStatus(422);
});

it('14. import creates one sales order per unique external source and order external id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [
            ($this->csvOrderRow)([
                'lines' => [
                    [
                        'external_id' => 'line-a',
                        'product_external_id' => 'sku-a',
                        'name' => 'Line A',
                        'quantity' => '1.000000',
                        'unit_price' => '10.00',
                    ],
                    [
                        'external_id' => 'line-b',
                        'product_external_id' => 'sku-b',
                        'name' => 'Line B',
                        'quantity' => '2.000000',
                        'unit_price' => '5.00',
                    ],
                ],
            ]),
        ],
    ])->assertCreated();

    $order = SalesOrder::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect(SalesOrder::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($order->external_source)->toBe('legacy_csv')
        ->and($order->external_id)->toBe('order-1001')
        ->and(\DB::table('sales_order_lines')->where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('15. import creates multiple orders when grouped rows contain multiple order external ids', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [
            ($this->csvOrderRow)(),
            ($this->csvOrderRow)([
                'external_id' => 'order-1002',
                'customer' => array_merge(($this->csvOrderRow)()['customer'], ['name' => 'Second Buyer']),
                'lines' => [[
                    'external_id' => 'line-1002-1',
                    'product_external_id' => 'sku-1002',
                    'name' => 'Second Item',
                    'quantity' => '3.000000',
                    'unit_price' => '7.00',
                ]],
            ]),
        ],
    ])->assertCreated();

    expect(SalesOrder::query()->where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('16. import creates missing customer and first contact from customer and contact names', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)([
            'customer' => array_merge(($this->csvOrderRow)()['customer'], ['name' => 'Created Customer']),
            'contact_name' => 'Jane Buyer',
        ])],
    ])->assertCreated();

    $customer = Customer::query()->where('tenant_id', $tenant->id)->where('name', 'Created Customer')->firstOrFail();

    expect(CustomerContact::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(CustomerContact::query()->where('customer_id', $customer->id)->value('is_primary'))->toBeTrue();
});

it('17. import maps existing product by tenant scoped external source and product external id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $item = ($this->makeItem)($tenant, [
        'name' => 'Existing Source Product',
        'external_source' => 'legacy_csv',
        'external_id' => 'sku-2001',
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])->assertCreated();

    expect(\DB::table('sales_order_lines')->where('tenant_id', $tenant->id)->value('item_id'))->toBe($item->id);
});

it('18. import creates a missing inactive sellable product without a fulfillment recipe', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])->assertCreated();

    $item = Item::query()->where('tenant_id', $tenant->id)->where('external_id', 'sku-2001')->firstOrFail();

    expect($item->is_active)->toBeFalse()
        ->and($item->is_sellable)->toBeTrue()
        ->and(\DB::table('recipes')->where('item_id', $item->id)->exists())->toBeFalse();
});

it('19. import creates no stock moves and preserves scale six quantity strings', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)([
            'lines' => [[
                'external_id' => 'line-qty',
                'product_external_id' => 'sku-qty',
                'name' => 'Qty Item',
                'quantity' => '2.125000',
                'unit_price' => '10.25',
            ]],
        ])],
    ])->assertCreated();

    $line = SalesOrderLine::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect(\DB::table('stock_moves')->count())->toBe(0)
        ->and($line->quantity)->toBe('2.125000');
});

it('20. same external source and order external id is allowed in another tenant', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermissions)($userA, ['sales-sales-orders-manage', 'system-users-manage']);
    ($this->grantPermissions)($userB, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($userA, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])->assertCreated();

    ($this->storeImport)($userB, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])->assertCreated();

    expect(SalesOrder::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(SalesOrder::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count())->toBe(1);
});

it('21. reimport updates only external status fields and does not change local status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'legacy_csv',
        'external_id' => 'order-1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now()->subDay(),
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)(['external_status' => 'completed'])],
    ])->assertOk();

    $order = SalesOrder::query()->firstOrFail();

    expect($order->status)->toBe(SalesOrder::STATUS_OPEN)
        ->and($order->external_status)->toBe('completed');
});

it('22. missing line external id still imports while product duplicate matching remains tenant scoped', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->makeItem)($tenant, [
        'name' => 'Scoped Product',
        'external_source' => 'legacy_csv',
        'external_id' => 'sku-shared',
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)([
            'lines' => [[
                'external_id' => '',
                'product_external_id' => 'sku-shared',
                'name' => 'Scoped Product',
                'quantity' => '1.000000',
                'unit_price' => '9.99',
            ]],
        ])],
    ])->assertCreated();

    expect(\DB::table('sales_order_lines')->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Item::query()->where('tenant_id', $tenant->id)->where('external_id', 'sku-shared')->count())->toBe(1);
});

it('23. import stores line external identity when the migration supports it', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)([
            'lines' => [[
                'external_id' => 'line-source-5001',
                'product_external_id' => 'sku-5001',
                'name' => 'Tracked Line',
                'quantity' => '1.000000',
                'unit_price' => '11.00',
            ]],
        ])],
    ])->assertCreated();

    expect(Schema::hasColumn('sales_order_lines', 'external_id'))->toBeTrue()
        ->and(SalesOrderLine::query()->where('tenant_id', $tenant->id)->value('external_id'))->toBe('line-source-5001');
});

it('24. store import returns useful json when persistence fails', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    Schema::table('sales_order_lines', function ($table): void {
        $table->dropIndex('sales_order_lines_tenant_order_external_idx');
        $table->dropColumn('external_id');
    });

    ($this->storeImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Unable to import orders because the database write failed.')
        ->assertJsonStructure([
            'message',
            'errors' => [
                'import',
            ],
        ]);
});

it('25. round trip export preview and import preserves one order with two lines and no stock moves', function () {
    $sourceTenant = ($this->makeTenant)(['tenant_name' => 'Source Tenant']);
    $targetTenant = ($this->makeTenant)(['tenant_name' => 'Target Tenant']);
    $sourceUser = ($this->makeUser)($sourceTenant);
    $targetUser = ($this->makeUser)($targetTenant);
    $customer = ($this->makeCustomer)($sourceTenant, ['name' => 'Roundtrip Customer', 'city' => 'Halifax']);
    $contact = ($this->makeContact)($sourceTenant, $customer, ['first_name' => 'Roundtrip', 'last_name' => 'Buyer']);
    $order = ($this->makeOrder)($sourceTenant, $customer, $contact, [
        'order_date' => '2026-05-14',
        'status' => SalesOrder::STATUS_OPEN,
        'external_source' => 'legacy_csv',
        'external_id' => 'roundtrip-order',
        'external_status' => 'processing',
    ]);
    $itemA = ($this->makeItem)($sourceTenant, ['name' => 'Roundtrip A', 'external_source' => 'legacy_csv', 'external_id' => 'roundtrip-product-a']);
    $itemB = ($this->makeItem)($sourceTenant, ['name' => 'Roundtrip B', 'external_source' => 'legacy_csv', 'external_id' => 'roundtrip-product-b']);
    ($this->makeLine)($sourceTenant, $order, $itemA, ['external_id' => 'roundtrip-line-a']);
    ($this->makeLine)($sourceTenant, $order, $itemB, ['external_id' => 'roundtrip-line-b', 'quantity' => '3.000000', 'unit_price_cents' => 900, 'line_total_cents' => '2700.000000']);
    ($this->grantPermissions)($sourceUser, ['sales-sales-orders-manage', 'system-users-manage']);
    ($this->grantPermissions)($targetUser, ['sales-sales-orders-manage', 'system-users-manage']);

    $records = ($this->csvRecords)((($this->exportOrders)($sourceUser))->assertOk());
    $rows = ($this->groupPreviewRowsFromCsvRecords)($records);

    ($this->previewImport)($targetUser, [
        'source' => 'file-upload',
        'rows' => $rows,
    ])->assertOk()->assertJsonCount(1, 'data.rows');

    ($this->storeImport)($targetUser, [
        'source' => 'file-upload',
        'rows' => $rows,
    ])->assertCreated();

    $importedOrder = SalesOrder::withoutGlobalScopes()->where('tenant_id', $targetTenant->id)->firstOrFail();

    expect($importedOrder->external_id)->toBe('roundtrip-order')
        ->and(\DB::table('sales_order_lines')->where('tenant_id', $targetTenant->id)->count())->toBe(2)
        ->and(Customer::query()->where('tenant_id', $targetTenant->id)->where('name', 'Roundtrip Customer')->exists())->toBeTrue()
        ->and(CustomerContact::query()->where('tenant_id', $targetTenant->id)->exists())->toBeTrue()
        ->and(Item::query()->where('tenant_id', $targetTenant->id)->where('external_id', 'roundtrip-product-a')->exists())->toBeTrue()
        ->and(\DB::table('stock_moves')->count())->toBe(0);
});

it('26. file upload preview remains tenant scoped for duplicate detection', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    SalesOrder::query()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
        'order_date' => '2026-05-11',
        'external_source' => 'legacy_csv',
        'external_id' => 'order-1001',
        'external_status' => 'processing',
        'external_status_synced_at' => now(),
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->previewImport)($user, [
        'source' => 'file-upload',
        'rows' => [($this->csvOrderRow)()],
    ])
        ->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false);
});
