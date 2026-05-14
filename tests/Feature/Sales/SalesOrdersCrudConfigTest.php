<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->contactCounter = 1;
    $this->itemCounter = 1;
    $this->orderCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Sales Orders Crud Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Crud User ' . $this->userCounter,
            'email' => 'sales-orders-crud-user-' . $this->userCounter . '@example.test',
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
                'name' => 'sales-orders-crud-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        $customer = Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Crud Customer ' . $this->customerCounter,
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
            'email' => 'sales-orders-crud-contact-' . $this->contactCounter . '@example.test',
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
            'name' => 'Sales Orders Crud Category ' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = \DB::table('uoms')->insertGetId([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $uomCategoryId,
            'name' => 'Sales Orders Crud UoM ' . $this->itemCounter,
            'symbol' => 'soc-' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Orders Crud Item ' . $this->itemCounter,
            'base_uom_id' => $uomId,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1299,
            'default_price_currency_code' => 'USD',
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeOrder = function (Tenant $tenant, Customer $customer, ?CustomerContact $contact = null, array $attributes = []): SalesOrder {
        $order = SalesOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'status' => SalesOrder::STATUS_OPEN,
            'order_date' => '2026-05-14',
        ], $attributes));

        $this->orderCounter++;

        return $order;
    };

    $this->makeLine = function (Tenant $tenant, SalesOrder $order, Item $item, array $attributes = []): void {
        \DB::table('sales_order_lines')->insert(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => '2.000000',
            'unit_price_cents' => 1299,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '2598.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
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

    $this->listOrders = function (User $user, array $query = []) {
        return $this->actingAs($user)->getJson(route('sales.orders.list', $query));
    };

    $this->exportOrders = function (?User $user = null, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('sales.orders.export', $query));
    };

    $this->indexResponse = function (User $user) {
        return $this->actingAs($user)->get(route('sales.orders.index'));
    };

    $this->csvRows = function ($response): array {
        $content = $response->streamedContent();
        $lines = preg_split("/\\r\\n|\\n|\\r/", trim($content)) ?: [];

        return array_values(array_filter(array_map(static function (string $line): ?array {
            if ($line === '') {
                return null;
            }

            return str_getcsv($line);
        }, $lines)));
    };
});

it('1. sales orders index requires authentication', function () {
    $this->get(route('sales.orders.index'))
        ->assertRedirect(route('login'));
});

it('2. sales orders index denies authenticated users without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->indexResponse)($user)->assertForbidden();
});

it('3. sales orders index renders the shared crud mount contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->indexResponse)($user)
        ->assertOk()
        ->assertSee('data-crud-config=', false)
        ->assertSee('data-import-config=', false)
        ->assertSee('data-crud-root', false);
});

it('4. sales orders index payload keeps the page module contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->indexResponse)($user)
        ->assertSee('data-page="sales-orders-index"', false)
        ->assertSee('data-payload="sales-orders-index-payload"', false)
        ->assertSee('sales-orders-index-payload', false);
});

it('5. crud config identifies the orders resource', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['resource'] ?? null)->toBe('orders');
});

it('6. crud config includes the list endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['endpoints']['list'] ?? null)->toBe(route('sales.orders.list'));
});

it('7. crud config includes the import preview endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['endpoints']['importPreview'] ?? null)->toBe(route('sales.orders.import.preview'));
});

it('8. crud config includes the import store endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['endpoints']['importStore'] ?? null)->toBe(route('sales.orders.import.store'));
});

it('9. crud config includes the export endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['endpoints']['export'] ?? null)->toBe(route('sales.orders.export'));
});

it('10. crud config uses the required order level columns', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['columns'] ?? null)->toBe(['id', 'date', 'customer_name', 'city', 'status']);
});

it('11. crud config uses view action for row navigation', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect(collect($config['actions'] ?? [])->pluck('id')->all())->toContain('view');
});

it('12. crud config visibility is permission driven by existing sales order permissions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractCrudConfig)(($this->indexResponse)($user));

    expect($config['permissions'] ?? [])->toMatchArray([
        'showExport' => true,
        'showImport' => true,
        'showCreate' => true,
    ]);
});

it('13. sales orders index does not render workflow ui', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->indexResponse)($user)
        ->assertDontSee('Checklist')
        ->assertDontSee('current_stage_tasks', false)
        ->assertDontSee('Complete');
});

it('14. sales orders index does not render order line ui', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->indexResponse)($user)
        ->assertDontSee('Add Line')
        ->assertDontSee('lineForms', false)
        ->assertDontSee('Line editing is unavailable');
});

it('15. list endpoint returns order id, date, customer name, city, and status only', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Long Customer Name Incorporated', 'city' => 'Ottawa']);
    $contact = ($this->makeContact)($tenant, $customer);
    $order = ($this->makeOrder)($tenant, $customer, $contact, ['order_date' => '2026-05-01', 'status' => SalesOrder::STATUS_OPEN]);
    $item = ($this->makeItem)($tenant);
    ($this->makeLine)($tenant, $order, $item);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $response = ($this->listOrders)($user)
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.date', '2026-05-01')
        ->assertJsonPath('data.0.customer_name', 'Long Customer Name Incorporated')
        ->assertJsonPath('data.0.city', 'Ottawa')
        ->assertJsonPath('data.0.status', SalesOrder::STATUS_OPEN);

    expect($response->json('data.0.lines'))->toBeNull()
        ->and($response->json('data.0.current_stage_tasks'))->toBeNull();
});

it('16. list endpoint is tenant scoped', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $user = ($this->makeUser)($tenantA);
    $customerA = ($this->makeCustomer)($tenantA, ['name' => 'Tenant A Customer']);
    $customerB = ($this->makeCustomer)($tenantB, ['name' => 'Tenant B Customer']);
    ($this->makeOrder)($tenantA, $customerA, null, ['order_date' => '2026-05-02']);
    ($this->makeOrder)($tenantB, $customerB, null, ['order_date' => '2026-05-03']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $response = ($this->listOrders)($user)->assertOk();

    expect(collect($response->json('data'))->pluck('customer_name')->all())
        ->toBe(['Tenant A Customer']);
});

it('17. list endpoint supports search and current filter export flow', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customerA = ($this->makeCustomer)($tenant, ['name' => 'Alpha Trading', 'city' => 'Montreal']);
    $customerB = ($this->makeCustomer)($tenant, ['name' => 'Beta Foods', 'city' => 'Calgary']);
    ($this->makeOrder)($tenant, $customerA, null, ['order_date' => '2026-05-04']);
    ($this->makeOrder)($tenant, $customerB, null, ['order_date' => '2026-05-05']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->listOrders)($user, ['search' => 'Alpha'])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Alpha Trading');
});

it('18. index row view action points to the detail route', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['order_date' => '2026-05-06']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $response = ($this->listOrders)($user)->assertOk();

    expect($response->json('data.0.show_url'))->toBe(route('sales.orders.show', $order));
});

it('19. export endpoint requires authentication', function () {
    ($this->exportOrders)()
        ->assertRedirect(route('login'));
});

it('20. export endpoint denies authenticated users without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->exportOrders)($user)->assertForbidden();
});

it('21. export endpoint outputs one csv row per order line with repeated order header fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Export Customer', 'city' => 'Vancouver']);
    $contact = ($this->makeContact)($tenant, $customer, ['first_name' => 'Jane', 'last_name' => 'Buyer']);
    $order = ($this->makeOrder)($tenant, $customer, $contact, [
        'order_date' => '2026-05-07',
        'status' => SalesOrder::STATUS_COMPLETED,
        'external_source' => 'legacy_csv',
        'external_id' => 'order-1001',
        'external_status' => 'completed',
    ]);
    $itemA = ($this->makeItem)($tenant, ['name' => 'Export Item A', 'external_source' => 'legacy_csv', 'external_id' => 'product-2001']);
    $itemB = ($this->makeItem)($tenant, ['name' => 'Export Item B', 'external_source' => 'legacy_csv', 'external_id' => 'product-2002']);
    ($this->makeLine)($tenant, $order, $itemA, [
        'external_id' => 'line-3001',
        'quantity' => '2.000000',
        'unit_price_cents' => 1299,
        'line_total_cents' => '2598.000000',
    ]);
    ($this->makeLine)($tenant, $order, $itemB, [
        'external_id' => 'line-3002',
        'quantity' => '1.500000',
        'unit_price_cents' => 2500,
        'line_total_cents' => '3750.000000',
    ]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $rows = ($this->csvRows)((($this->exportOrders)($user))->assertOk());
    $header = $rows[0] ?? [];
    $records = array_map(static fn (array $row): array => array_combine($header, $row), array_slice($rows, 1));

    expect($header)->toBe([
        'external_source',
        'order_external_id',
        'order_date',
        'customer_name',
        'contact_name',
        'city',
        'status',
        'external_status',
        'line_external_id',
        'product_external_id',
        'product_name',
        'quantity',
        'unit_price',
    ])->and($records)->toHaveCount(2)
        ->and($records[0]['external_source'] ?? null)->toBe('legacy_csv')
        ->and($records[0]['order_external_id'] ?? null)->toBe('order-1001')
        ->and($records[0]['order_date'] ?? null)->toBe('2026-05-07')
        ->and($records[0]['customer_name'] ?? null)->toBe('Export Customer')
        ->and($records[0]['contact_name'] ?? null)->toBe('Jane Buyer')
        ->and($records[0]['city'] ?? null)->toBe('Vancouver')
        ->and($records[0]['status'] ?? null)->toBe(SalesOrder::STATUS_COMPLETED)
        ->and($records[0]['external_status'] ?? null)->toBe('completed')
        ->and($records[0]['line_external_id'] ?? null)->toBe('line-3001')
        ->and($records[0]['product_external_id'] ?? null)->toBe('product-2001')
        ->and($records[0]['product_name'] ?? null)->toBe('Export Item A')
        ->and($records[0]['quantity'] ?? null)->toBe('2.000000')
        ->and($records[0]['unit_price'] ?? null)->toBe('12.99')
        ->and($records[1]['order_external_id'] ?? null)->toBe('order-1001')
        ->and($records[1]['line_external_id'] ?? null)->toBe('line-3002');
});

it('22. export endpoint excludes json in csv and app internal order or line ids', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'No Json Customer']);
    $order = ($this->makeOrder)($tenant, $customer, null, [
        'order_date' => '2026-05-08',
        'external_source' => 'legacy_csv',
        'external_id' => 'order-2001',
        'external_status' => 'processing',
    ]);
    $item = ($this->makeItem)($tenant, ['name' => 'Line Item', 'external_source' => 'legacy_csv', 'external_id' => 'product-3001']);
    ($this->makeLine)($tenant, $order, $item, ['external_id' => 'line-4001']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $rows = ($this->csvRows)((($this->exportOrders)($user))->assertOk());
    $header = $rows[0] ?? [];
    $record = array_combine($header, $rows[1] ?? []);
    $content = implode("\n", array_map(static fn (array $row): string => implode(',', $row), $rows));

    expect($header)->not->toContain('id')
        ->and($header)->not->toContain('sales_order_id')
        ->and($header)->not->toContain('line_id')
        ->and($content)->not->toContain('[')
        ->and($content)->not->toContain('{')
        ->and($record['order_external_id'] ?? null)->toBe('order-2001')
        ->and($record['line_external_id'] ?? null)->toBe('line-4001');
});

it('23. export endpoint respects current search filters while returning one row per line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customerA = ($this->makeCustomer)($tenant, ['name' => 'Filter Alpha']);
    $customerB = ($this->makeCustomer)($tenant, ['name' => 'Filter Beta']);
    $orderA = ($this->makeOrder)($tenant, $customerA, null, ['order_date' => '2026-05-09', 'external_source' => 'legacy_csv', 'external_id' => 'order-alpha']);
    $orderB = ($this->makeOrder)($tenant, $customerB, null, ['order_date' => '2026-05-10', 'external_source' => 'legacy_csv', 'external_id' => 'order-beta']);
    $itemA = ($this->makeItem)($tenant, ['name' => 'Filter Item A', 'external_source' => 'legacy_csv', 'external_id' => 'product-alpha']);
    $itemB = ($this->makeItem)($tenant, ['name' => 'Filter Item B', 'external_source' => 'legacy_csv', 'external_id' => 'product-beta']);
    ($this->makeLine)($tenant, $orderA, $itemA, ['external_id' => 'line-alpha']);
    ($this->makeLine)($tenant, $orderB, $itemB, ['external_id' => 'line-beta']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $rows = ($this->csvRows)((($this->exportOrders)($user, [
        'scope' => 'current',
        'search' => 'Alpha',
        'sort' => 'customer_name',
        'direction' => 'asc',
    ]))->assertOk());

    expect($rows)->toHaveCount(2)
        ->and($rows[1][1] ?? null)->toBe('order-alpha')
        ->and($rows[1][3] ?? null)->toBe('Filter Alpha');
});

it('24. import config identifies the orders resource and endpoints', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->indexResponse)($user));

    expect($config['resource'] ?? null)->toBe('orders')
        ->and($config['endpoints']['preview'] ?? null)->toBe(route('sales.orders.import.preview'))
        ->and($config['endpoints']['store'] ?? null)->toBe(route('sales.orders.import.store'));
});

it('25. import config exposes the exact shared import module keys used by products and customers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->indexResponse)($user));

    expect($config)->toHaveKeys([
        'resource',
        'endpoints',
        'labels',
        'permissions',
        'messages',
        'connectorsPageUrl',
        'sources',
        'rowBehavior',
        'previewDisplay',
    ])->and($config['labels'] ?? [])->toHaveKeys([
        'title',
        'source',
        'submit',
        'previewSearch',
        'loadingPreviewDefault',
        'loadingPreviewFile',
        'loadingPreviewExternal',
        'emptyStateDescription',
        'noBulkOptions',
        'previewDescription',
    ])->and($config['messages'] ?? [])->toHaveKeys([
        'previewUnavailable',
        'importUnavailable',
        'fileReadError',
        'filePreviewUnavailable',
        'emptyFileRows',
        'missingFileHeaders',
        'emptySelection',
    ])->and($config['permissions'] ?? [])->toHaveKeys([
        'canManageImports',
        'canManageConnections',
    ])->and($config['rowBehavior'] ?? [])->toHaveKeys([
        'hideDuplicatesByDefault',
        'selectVisibleNonDuplicateRowsOnly',
        'submitSelectedVisibleRowsOnly',
        'duplicateFlagField',
        'selectionField',
    ])->and($config['previewDisplay'] ?? [])->toHaveKeys([
        'titleExpression',
        'subtitleExpression',
        'bodyExpression',
        'searchExpressions',
        'errorFields',
    ]);
});

it('26. import config exposes the shared file upload source option for orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->indexResponse)($user));

    expect(collect($config['sources'] ?? [])->firstWhere('value', 'file-upload'))
        ->toMatchArray([
            'value' => 'file-upload',
            'label' => 'File Upload',
            'enabled' => true,
        ]);
});

it('27. import config keeps customer name as the primary preview title and date city as compact metadata', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->indexResponse)($user));

    expect($config['previewDisplay']['titleExpression'] ?? null)->toBe('truncatedPreviewCustomerName(row)')
        ->and($config['previewDisplay']['subtitleExpression'] ?? null)->toBe('compactPreviewMeta(row)')
        ->and($config['previewDisplay']['bodyExpression'] ?? null)->toBe('');
});

it('28. orders preview display search keeps external identity searchable without rendering raw metadata', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->indexResponse)($user));

    expect($config['previewDisplay']['searchExpressions'] ?? [])->toContain('row.external_id')
        ->and($config['previewDisplay']['searchExpressions'] ?? [])->toContain('row.external_source')
        ->and($config['previewDisplay']['searchExpressions'] ?? [])->toContain('(row.customer && row.customer.city)')
        ->and($config['previewDisplay']['subtitleExpression'] ?? null)->not->toContain('external_status');
});

it('29. compact preview helpers map customer name to the title and date city to the metadata line', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-orders-index.js'));

    expect($source)->toContain('truncatedPreviewCustomerName(row)')
        ->and($source)->toContain('compactPreviewMeta(row)')
        ->and($source)->toContain('return this.truncatedCustomerName(customer.name || \'\');')
        ->and($source)->toContain("const date = row && typeof row.date === 'string' && row.date.trim() !== ''")
        ->and($source)->toContain("const city = typeof customer.city === 'string' && customer.city.trim() !== ''")
        ->and($source)->toContain('return `${date} • ${city}`;');
});

it('30. sales orders preview card contract does not render order line names or raw woo metadata expressions', function () {
    $config = file_get_contents(base_path('app/Http/Controllers/SalesOrderController.php'));

    expect($config)->toContain("'titleExpression' => 'truncatedPreviewCustomerName(row)'")
        ->and($config)->toContain("'subtitleExpression' => 'compactPreviewMeta(row)'")
        ->and($config)->not->toContain("'bodyExpression' => 'row.external_status'")
        ->and($config)->not->toContain("'titleExpression' => '`Order #\${row.external_id}`'");
});

it('31. sales orders page module mounts the shared import module through the shared adapter path', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-orders-index.js'));

    expect($source)->toContain("import { createImportModule } from '../lib/import-module';")
        ->and($source)->toContain('const importModule = createImportModule(')
        ->and($source)->toContain('config: importConfig')
        ->and($source)->toContain('parseLocalRows: (text, helpers) => {')
        ->and($source)->toContain('truncatedPreviewCustomerName(row)')
        ->and($source)->toContain('compactPreviewMeta(row)')
        ->and($source)->toContain("source: defaultBody.is_local_file_import ? 'file-upload' : importSource")
        ->and($source)->toContain('importModule.mount(rootEl);');
});

it('32. sales orders page uses the shared export module without page local export markup', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-orders-index.js'));
    $exportModuleSource = file_get_contents(base_path('resources/js/lib/export-module.js'));

    expect($source)->toContain("import { createExportModule } from '../lib/export-module';")
        ->and($source)->toContain('const exportModule = createExportModule(')
        ->and($source)->toContain('exportModule.mount(rootEl);')
        ->and($source)->toContain("exportHandler: 'openExportPanel()'")
        ->and($source)->toContain("export: 'openExportPanel()'")
        ->and($source)->not->toContain('buildExportUrl() {')
        ->and($source)->not->toContain('submitExport() {')
        ->and($exportModuleSource)->toContain('data-shared-export-panel');
});

it('33. exported orders csv headers are accepted by the orders file upload parser contract', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-orders-index.js'));

    expect($source)->toContain("const exportHeaders = [")
        ->and($source)->toContain("'external_source'")
        ->and($source)->toContain("'order_external_id'")
        ->and($source)->toContain("'order_date'")
        ->and($source)->toContain("'customer_name'")
        ->and($source)->toContain("'contact_name'")
        ->and($source)->toContain("'city'")
        ->and($source)->toContain("'status'")
        ->and($source)->toContain("'external_status'")
        ->and($source)->toContain("'line_external_id'")
        ->and($source)->toContain("'product_external_id'")
        ->and($source)->toContain("'product_name'")
        ->and($source)->toContain("'quantity'")
        ->and($source)->toContain("'unit_price'")
        ->and($source)->toContain('const hasExportHeaders = exportHeaders.every((header) => headers.includes(header));');
});

it('34. orders preview endpoint accepts grouped file upload rows from the exported csv contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $this->actingAs($user)
        ->postJson(route('sales.orders.import.preview'), [
            'source' => 'file-upload',
            'rows' => [[
                'external_id' => 'order-42',
                'external_source' => 'legacy_csv',
                'external_status' => 'processing',
                'date' => '2026-05-14',
                'contact_name' => 'Round Trip Buyer',
                'customer' => [
                    'name' => 'Round Trip Buyer',
                    'city' => 'Ottawa',
                ],
                'lines' => [[
                    'external_id' => 'line-42',
                    'product_external_id' => 'product-42',
                    'name' => 'Preview Item',
                    'quantity' => '2.000000',
                    'unit_price' => '12.99',
                ]],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.rows.0.date', '2026-05-14')
        ->assertJsonPath('data.rows.0.external_source', 'legacy_csv')
        ->assertJsonPath('data.rows.0.customer.name', 'Round Trip Buyer')
        ->assertJsonPath('data.rows.0.customer.city', 'Ottawa');
});

it('35. sales orders index does not render shared export markup server side', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    ($this->indexResponse)($user)
        ->assertOk()
        ->assertDontSee('data-shared-export-panel', false)
        ->assertDontSee('data-shared-import-panel', false);
});

it('36. sales orders payload includes file upload as an explicit import mode', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $config = ($this->extractImportConfig)(($this->indexResponse)($user));

    expect(collect($config['sources'] ?? [])->firstWhere('value', 'file-upload'))
        ->toMatchArray([
            'value' => 'file-upload',
            'label' => 'File Upload',
            'enabled' => true,
        ]);
});

it('37. sales orders page source does not contain optional chaining assignment patterns', function () {
    $source = file_get_contents(base_path('resources/js/pages/sales-orders-index.js'));

    expect($source)->not->toMatch('/\\?\\.[A-Za-z0-9_]+\\s*=/')
        ->and($source)->not->toContain('row?.customer?.city =')
        ->and($source)->toContain("const customer = row && typeof row.customer === 'object' && !Array.isArray(row.customer)");
});

it('38. import and list endpoints deny authenticated users without existing sales order permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)->getJson(route('sales.orders.list'))->assertForbidden();
    $this->actingAs($user)->postJson(route('sales.orders.import.preview'), ['source' => 'woocommerce'])->assertForbidden();
    $this->actingAs($user)->postJson(route('sales.orders.import.store'), ['source' => 'woocommerce', 'rows' => []])->assertForbidden();
});
