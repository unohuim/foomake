<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
            'tenant_name' => 'Sales Order Detail Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Order Detail User ' . $this->userCounter,
            'email' => 'sales-order-detail-user-' . $this->userCounter . '@example.test',
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
            $role = Role::query()->create(['name' => 'sales-order-detail-role-' . $this->roleCounter]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        $customer = Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Order Detail Customer ' . $this->customerCounter,
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
            'first_name' => 'Detail',
            'last_name' => 'Contact ' . $this->contactCounter,
            'email' => 'sales-order-detail-contact-' . $this->contactCounter . '@example.test',
            'phone' => '555-111' . $this->contactCounter,
            'role' => 'Buyer',
            'is_primary' => true,
        ], $attributes));

        $this->contactCounter++;

        return $contact;
    };

    $this->makeItem = function (Tenant $tenant, array $attributes = []): Item {
        $uomCategoryId = \DB::table('uom_categories')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Order Detail Category ' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = \DB::table('uoms')->insertGetId([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $uomCategoryId,
            'name' => 'Sales Order Detail UoM ' . $this->itemCounter,
            'symbol' => 'sod-' . $this->itemCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Sales Order Detail Item ' . $this->itemCounter,
            'base_uom_id' => $uomId,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1550,
            'default_price_currency_code' => 'USD',
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
            'quantity' => '1.500000',
            'unit_price_cents' => 1550,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '2325.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    };

    $this->makeWorkflowTask = function (Tenant $tenant, SalesOrder $order, User $assignedUser, array $attributes = []): Task {
        $domain = WorkflowDomain::query()->firstOrCreate(
            ['key' => 'sales'],
            ['name' => 'Sales']
        );

        $stage = WorkflowStage::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'packing',
        ], [
            'name' => 'Packing',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Task::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'workflow_stage_id' => $stage->id,
            'workflow_task_template_id' => null,
            'domain_type' => SalesOrder::class,
            'domain_record_id' => $order->id,
            'assigned_to_user_id' => $assignedUser->id,
            'title' => 'Pack order',
            'description' => 'Pack the order before shipment.',
            'sort_order' => 1,
            'status' => 'open',
            'completed_at' => null,
            'completed_by_user_id' => null,
        ], $attributes));
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
});

it('1. sales order detail route requires authentication', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);

    $this->get(route('sales.orders.show', $order))
        ->assertRedirect(route('login'));
});

it('2. sales order detail route denies authenticated users without sales order permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);

    $this->actingAs($user)
        ->get(route('sales.orders.show', $order))
        ->assertForbidden();
});

it('3. sales order detail route exists and responds for authorized users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->get(route('sales.orders.show', $order))
        ->assertOk()
        ->assertSee('data-page="sales-orders-show"', false)
        ->assertSee('sales-orders-show-payload', false);
});

it('4. detail route is tenant scoped and blocks cross tenant access', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();
    $user = ($this->makeUser)($tenantA);
    $customer = ($this->makeCustomer)($tenantB);
    $order = ($this->makeOrder)($tenantB, $customer);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->get(route('sales.orders.show', $order))
        ->assertNotFound();
});

it('5. detail page shows order header information', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Header Customer']);
    $order = ($this->makeOrder)($tenant, $customer, null, ['order_date' => '2026-05-10']);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $response = $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');

    expect($payload['order']['id'] ?? null)->toBe($order->id)
        ->and($payload['order']['date'] ?? null)->toBe('2026-05-10');
});

it('6. detail page shows customer information', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant, ['name' => 'Customer Name', 'city' => 'Kingston']);
    $contact = ($this->makeContact)($tenant, $customer, ['first_name' => 'Jane', 'last_name' => 'Buyer']);
    $order = ($this->makeOrder)($tenant, $customer, $contact);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $response = $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');

    expect($payload['order']['customer_name'] ?? null)->toBe('Customer Name')
        ->and($payload['order']['contact_name'] ?? null)->toBe('Jane Buyer')
        ->and($payload['order']['city'] ?? null)->toBe('Kingston');
});

it('7. detail page shows status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $response = $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');

    expect($payload['order']['status'] ?? null)->toBe(SalesOrder::STATUS_PACKING);
});

it('8. detail page shows order lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);
    $item = ($this->makeItem)($tenant, ['name' => 'Detail Line Item']);
    ($this->makeLine)($tenant, $order, $item);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $response = $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');

    expect($payload['order']['lines'][0]['item_name'] ?? null)->toBe('Detail Line Item');
});

it('9. detail page shows workflow ui when current stage tasks exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->makeWorkflowTask)($tenant, $order, $user);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->get(route('sales.orders.show', $order))
        ->assertOk()
        ->assertSee('Checklist')
        ->assertSee('Pack order');
});

it('10. detail page shows line and workflow actions for editable orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_OPEN]);
    $item = ($this->makeItem)($tenant);
    ($this->makeLine)($tenant, $order, $item);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $response = $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');

    expect($payload['order']['can_edit'] ?? null)->toBeTrue()
        ->and($payload['order']['can_manage_lines'] ?? null)->toBeTrue()
        ->and($payload['order']['status_update_url'] ?? null)->toBe(route('sales.orders.status.update', $order));
});

it('11. index page does not show workflow ui after move', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->makeWorkflowTask)($tenant, $order, $user);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk()
        ->assertDontSee('Checklist')
        ->assertDontSee('Pack order');
});

it('12. index page does not show order lines after move', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);
    $item = ($this->makeItem)($tenant, ['name' => 'Hidden Index Line']);
    ($this->makeLine)($tenant, $order, $item);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk()
        ->assertDontSee('Hidden Index Line')
        ->assertDontSee('Add Line');
});

it('13. completed orders are not editable on detail', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_COMPLETED]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    expect($payload['order']['can_edit'] ?? null)->toBeFalse()
        ->and($payload['order']['can_manage_lines'] ?? null)->toBeFalse();
});

it('14. cancelled orders are not editable on detail', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_CANCELLED]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    expect($payload['order']['can_edit'] ?? null)->toBeFalse()
        ->and($payload['order']['can_manage_lines'] ?? null)->toBeFalse();
});

it('15. open orders remain editable on detail', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    expect($payload['order']['can_edit'] ?? null)->toBeTrue()
        ->and($payload['order']['can_manage_lines'] ?? null)->toBeTrue();
});

it('16. draft orders remain editable on detail', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_DRAFT]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    expect($payload['order']['can_edit'] ?? null)->toBeTrue()
        ->and($payload['order']['can_manage_lines'] ?? null)->toBeTrue();
});

it('17. existing status endpoint still works from detail context', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_DRAFT]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order), ['status' => SalesOrder::STATUS_OPEN])
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_OPEN);
});

it('18. existing status validation remains enforced', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order), ['status' => SalesOrder::STATUS_PACKED])
        ->assertStatus(422);
});

it('19. existing line mutations remain available through detail context only', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer, null, ['status' => SalesOrder::STATUS_DRAFT]);
    $item = ($this->makeItem)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])
        ->assertCreated();
});

it('20. detail view link from index resolves correctly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage', 'system-users-manage']);

    $response = $this->actingAs($user)->getJson(route('sales.orders.list'))->assertOk();

    expect($response->json('data.0.show_url'))->toBe(route('sales.orders.show', $order));
});

it('21. detail route uses safe route model binding behavior', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $this->actingAs($user)
        ->get('/sales/orders/999999')
        ->assertNotFound();
});

it('22. viewing detail creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeOrder)($tenant, $customer);
    ($this->grantPermissions)($user, ['sales-sales-orders-manage']);

    $beforeCount = \DB::table('stock_moves')->count();

    $this->actingAs($user)->get(route('sales.orders.show', $order))->assertOk();

    expect(\DB::table('stock_moves')->count())->toBe($beforeCount);
});
