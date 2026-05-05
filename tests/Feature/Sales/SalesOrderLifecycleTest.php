<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->contactCounter = 1;
    $this->orderCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'sales-order-lifecycle-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'role-lifecycle-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $this->customerCounter,
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

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createContact = function (Tenant $tenant, int $customerId, array $attributes = []): object {
        $contactId = DB::table('customer_contacts')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'first_name' => 'Contact',
            'last_name' => (string) $this->contactCounter,
            'email' => 'lifecycle-contact' . $this->contactCounter . '@example.test',
            'phone' => '555-100' . $this->contactCounter,
            'role' => 'Buyer',
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->contactCounter++;

        return DB::table('customer_contacts')->where('id', $contactId)->first();
    };

    $this->createSalesOrder = function (
        Tenant $tenant,
        int $customerId,
        ?int $contactId = null,
        array $attributes = []
    ): object {
        $orderId = DB::table('sales_orders')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => $contactId,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->orderCounter++;

        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? 'SOLC-UOM-' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'SOLC Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'SOLC UOM ' . $this->uomCounter,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->createItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1000,
            'default_price_currency_code' => 'USD',
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->createSalesOrderLine = function (
        Tenant $tenant,
        int $salesOrderId,
        int $itemId,
        array $attributes = []
    ): object {
        $lineId = DB::table('sales_order_lines')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $salesOrderId,
            'item_id' => $itemId,
            'quantity' => '1.000000',
            'unit_price_cents' => 1000,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '1000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return DB::table('sales_order_lines')->where('id', $lineId)->first();
    };

    $this->fetchSalesOrder = function (int $orderId): ?object {
        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->countStockMovesForTenant = function (Tenant $tenant): int {
        return DB::table('stock_moves')->where('tenant_id', $tenant->id)->count();
    };

    $this->assertStatusValidationErrors = function ($response): void {
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'status',
            ],
        ]);

        expect($response->json('errors.status'))->toBeArray();
    };

    $this->assertOrderValidationErrors = function ($response): void {
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'customer_id',
                'contact_id',
            ],
        ]);

        expect($response->json('errors.customer_id'))->toBeArray()
            ->and($response->json('errors.contact_id'))->toBeArray();
    };

    $this->assertLineValidationErrors = function ($response): void {
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'item_id',
                'quantity',
            ],
        ]);

        expect($response->json('errors.item_id'))->toBeArray()
            ->and($response->json('errors.quantity'))->toBeArray();
    };
});

it('1. sales order may be created with draft status by default', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertCreated();

    $order = ($this->fetchSalesOrder)((int) $response->json('data.id'));

    expect($order?->status)->toBe('DRAFT');
});

it('2. allowed statuses include draft open completed and cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk();

    preg_match('/<script type="application\\/json" id="sales-orders-index-payload">\\s*(.*?)\\s*<\\/script>/s', $response->getContent(), $matches);
    $payload = json_decode($matches[1] ?? '[]', true);

    expect($payload['statuses'] ?? null)->toBe([
        'DRAFT',
        'OPEN',
        'COMPLETED',
        'CANCELLED',
    ]);
});

it('3. invalid statuses are rejected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'INVALID',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('4. old roadmap statuses confirmed and fulfilled are rejected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $confirmedResponse = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CONFIRMED',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($confirmedResponse);

    $fulfilledResponse = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'FULFILLED',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($fulfilledResponse);
});

it('5. authorized user can change draft to open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertOk();

    expect($response->json('data.status'))->toBe('OPEN')
        ->and(($this->fetchSalesOrder)($order->id)?->status)->toBe('OPEN');
});

it('6. authorized user can change draft to cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CANCELLED',
        ])->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED');

    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('CANCELLED');
});

it('7. authorized user can change open to completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'COMPLETED',
        ])->assertOk()
        ->assertJsonPath('data.status', 'COMPLETED');

    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('COMPLETED');
});

it('8. authorized user can change open to cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CANCELLED',
        ])->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED');

    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('CANCELLED');
});

it('9. draft cannot change directly to completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'COMPLETED',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('DRAFT');
});

it('10. open cannot change back to draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'DRAFT',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('OPEN');
});

it('11. completed cannot change to draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'DRAFT',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('12. completed cannot change to open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('13. completed cannot change to cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CANCELLED',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('14. cancelled cannot change to draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'DRAFT',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('15. cancelled cannot change to open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('16. cancelled cannot change to completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'COMPLETED',
        ])->assertStatus(422);

    ($this->assertStatusValidationErrors)($response);
});

it('17. unauthorized user cannot change sales order status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertForbidden();
});

it('18. cross tenant user cannot change another tenants sales order status', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $otherOrder->id), [
            'status' => 'OPEN',
        ])->assertNotFound();
});

it('19. valid status change returns json success and does not redirect', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertOk()
        ->assertHeader('content-type', 'application/json');
});

it('20. invalid status change returns json error validation response', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'COMPLETED',
        ])->assertStatus(422)
        ->assertHeader('content-type', 'application/json');

    ($this->assertStatusValidationErrors)($response);
});

it('21. sales order header can still be edited while draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $newCustomer = ($this->createCustomer)($tenant, ['name' => 'Updated Customer']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $newCustomer->id,
        ])->assertOk()
        ->assertJsonPath('data.customer_id', $newCustomer->id);
});

it('22. sales order header can still be edited while open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $replacementContact = ($this->createContact)($tenant, $customer->id, ['first_name' => 'Replacement']);
    $order = ($this->createSalesOrder)($tenant, $customer->id, $contact->id, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $customer->id,
            'contact_id' => $replacementContact->id,
        ])->assertOk()
        ->assertJsonPath('data.contact_id', $replacementContact->id);
});

it('23. sales order header cannot be edited while completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $newCustomer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $newCustomer->id,
        ])->assertStatus(422);

    ($this->assertOrderValidationErrors)($response);
    expect(($this->fetchSalesOrder)($order->id)?->customer_id)->toBe($customer->id);
});

it('24. sales order header cannot be edited while cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $newCustomer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $newCustomer->id,
        ])->assertStatus(422);

    ($this->assertOrderValidationErrors)($response);
    expect(($this->fetchSalesOrder)($order->id)?->customer_id)->toBe($customer->id);
});

it('25. sales order lines can be added while draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertCreated();

    expect(DB::table('sales_order_lines')->where('sales_order_id', $order->id)->count())->toBe(1);
});

it('26. sales order lines can be added while open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertCreated();

    expect(DB::table('sales_order_lines')->where('sales_order_id', $order->id)->count())->toBe(1);
});

it('27. sales order lines cannot be added while completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertStatus(422);

    ($this->assertLineValidationErrors)($response);
    expect(DB::table('sales_order_lines')->where('sales_order_id', $order->id)->count())->toBe(0);
});

it('28. sales order lines cannot be added while cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order->id), [
            'item_id' => $item->id,
            'quantity' => '1.000000',
        ])->assertStatus(422);

    ($this->assertLineValidationErrors)($response);
    expect(DB::table('sales_order_lines')->where('sales_order_id', $order->id)->count())->toBe(0);
});

it('29. sales order line quantity can be updated while draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '3.000000',
        ])->assertOk()
        ->assertJsonPath('data.line.quantity', '3.000000');
});

it('30. sales order line quantity can be updated while open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '3.000000',
        ])->assertOk()
        ->assertJsonPath('data.line.quantity', '3.000000');
});

it('31. sales order line quantity cannot be updated while completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '3.000000',
        ])->assertStatus(422);

    ($this->assertLineValidationErrors)($response);
});

it('32. sales order line quantity cannot be updated while cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.lines.update', [$order->id, $line->id]), [
            'quantity' => '3.000000',
        ])->assertStatus(422);

    ($this->assertLineValidationErrors)($response);
});

it('33. sales order lines can be deleted while draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertOk();

    expect(DB::table('sales_order_lines')->where('id', $line->id)->exists())->toBeFalse();
});

it('34. sales order lines can be deleted while open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertOk();

    expect(DB::table('sales_order_lines')->where('id', $line->id)->exists())->toBeFalse();
});

it('35. sales order lines cannot be deleted while completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'COMPLETED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertStatus(422);

    ($this->assertLineValidationErrors)($response);
    expect(DB::table('sales_order_lines')->where('id', $line->id)->exists())->toBeTrue();
});

it('36. sales order lines cannot be deleted while cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'CANCELLED']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $line = ($this->createSalesOrderLine)($tenant, $order->id, $item->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->deleteJson(route('sales.orders.lines.destroy', [$order->id, $line->id]))
        ->assertStatus(422);

    ($this->assertLineValidationErrors)($response);
    expect(DB::table('sales_order_lines')->where('id', $line->id)->exists())->toBeTrue();
});

it('37. status changes create no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    expect(($this->countStockMovesForTenant)($tenant))->toBe(0);

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'OPEN',
        ])->assertOk();

    expect(($this->countStockMovesForTenant)($tenant))->toBe(0);
});

it('38. completing a zero line sales order creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'COMPLETED',
        ])->assertOk();

    expect(($this->countStockMovesForTenant)($tenant))->toBe(0);
});

it('39. cancelling a sales order creates no stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'CANCELLED',
        ])->assertOk();

    expect(($this->countStockMovesForTenant)($tenant))->toBe(0);
});

it('40. status only lifecycle does not require inventory availability', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, null, ['status' => 'OPEN']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, [
        'name' => 'Sellable Item Without Stock',
        'default_price_cents' => 2500,
    ]);

    ($this->createSalesOrderLine)($tenant, $order->id, $item->id, [
        'quantity' => '5.000000',
        'unit_price_cents' => 2500,
        'line_total_cents' => '12500.000000',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.status.update', $order->id), [
            'status' => 'COMPLETED',
        ])->assertOk()
        ->assertJsonPath('data.status', 'COMPLETED');

    expect(($this->fetchSalesOrder)($order->id)?->status)->toBe('COMPLETED')
        ->and(($this->countStockMovesForTenant)($tenant))->toBe(1);
});
