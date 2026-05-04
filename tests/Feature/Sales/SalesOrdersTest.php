<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\Item;
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
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'role-' . $this->roleCounter]);

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
            'email' => 'contact' . $this->contactCounter . '@example.test',
            'phone' => '555-010' . $this->contactCounter,
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
        $symbol = $attributes['symbol'] ?? 'SO-UOM-' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Sales Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Sales UOM ' . $this->uomCounter,
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
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => 1000,
            'default_price_currency_code' => 'USD',
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->fetchSalesOrder = function (int $orderId): ?object {
        return DB::table('sales_orders')->where('id', $orderId)->first();
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };

    $this->orderPayloadIds = function (array $payload): array {
        return array_map(
            static fn (array $order): int => (int) ($order['id'] ?? 0),
            $payload['orders'] ?? []
        );
    };

    $this->assertStableErrors = function ($response): void {
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

    $this->renderDashboard = function (User $user) {
        return $this->actingAs($user)->get(route('dashboard'));
    };
});

it('1. guest cannot view the sales orders index', function () {
    $this->get(route('sales.orders.index'))
        ->assertRedirect(route('login'));
});

it('2. user without sales order permission cannot view the sales orders index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertForbidden();
});

it('3. authorized user can view the sales orders index and receives ajax payload config', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk()
        ->assertSee('Sales Orders')
        ->assertSee('sales-orders-index-payload', false);

    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');

    expect($payload['storeUrl'] ?? null)->toBe(route('sales.orders.store'))
        ->and($payload['updateUrlBase'] ?? null)->toBe(url('/sales/orders'))
        ->and($payload['statuses'] ?? null)->toBe(['DRAFT']);
});

it('3a. sales navigation appears before other domain navigation groups', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'inventory-materials-view');

    $content = ($this->renderDashboard)($user)
        ->assertOk()
        ->getContent();

    $salesPosition = strpos($content, 'data-nav-dropdown-trigger="sales"');
    $purchasingPosition = strpos($content, 'data-nav-dropdown-trigger="purchasing"');
    $manufacturingPosition = strpos($content, 'data-nav-dropdown-trigger="manufacturing"');

    expect($salesPosition)->not->toBeFalse()
        ->and($purchasingPosition)->not->toBeFalse()
        ->and($manufacturingPosition)->not->toBeFalse()
        ->and($salesPosition)->toBeLessThan($purchasingPosition)
        ->and($salesPosition)->toBeLessThan($manufacturingPosition);
});

it('3b. sales orders navigation is visible but disabled when there are zero customers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    ($this->createItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee('data-nav-dropdown-trigger="sales"', false)
        ->assertSee('cursor-not-allowed', false)
        ->assertSee('Orders');

    $content = $response->getContent();

    expect($content)->not->toContain('href="' . route('sales.orders.index') . '"');
});

it('3c. sales orders navigation is enabled when the tenant has at least one customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    ($this->createItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee('data-nav-dropdown-trigger="sales"', false)
        ->assertSee(route('sales.orders.index'), false);
});

it('3d. sales orders navigation remains gated by sales sales orders manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee(route('sales.customers.index'), false)
        ->assertDontSee(route('sales.orders.index'), false);
});

it('4. guest cannot create a sales order', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);

    $this->postJson(route('sales.orders.store'), [
        'customer_id' => $customer->id,
    ])->assertUnauthorized();
});

it('5. user without sales order permission cannot create a sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertForbidden();
});

it('6. create defaults status to draft and scopes the order to the authenticated tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'status' => 'CONFIRMED',
        ])->assertCreated();

    $orderId = (int) $response->json('data.id');
    $order = ($this->fetchSalesOrder)($orderId);

    expect((int) ($order?->tenant_id ?? 0))->toBe($tenant->id)
        ->and((string) ($order?->status ?? ''))->toBe('DRAFT');
});

it('7. create defaults contact to the selected customer primary contact when contact_id is omitted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, ['first_name' => 'Secondary', 'is_primary' => false]);
    $primaryContact = ($this->createContact)($tenant, $customer->id, ['first_name' => 'Primary', 'is_primary' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertCreated();

    $order = ($this->fetchSalesOrder)((int) $response->json('data.id'));

    expect((int) ($order?->contact_id ?? 0))->toBe($primaryContact->id);
});

it('8. create accepts an explicit valid contact for the selected customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $selectedContact = ($this->createContact)($tenant, $customer->id, ['first_name' => 'Chosen']);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $selectedContact->id,
        ])->assertCreated();

    $order = ($this->fetchSalesOrder)((int) $response->json('data.id'));

    expect((int) ($order?->contact_id ?? 0))->toBe($selectedContact->id);
});

it('9. create rejects a customer from another tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $otherCustomer->id,
        ])->assertStatus(422);

    ($this->assertStableErrors)($response);
    expect($response->json('errors.customer_id'))->not->toBe([]);
});

it('10. create rejects a contact that does not belong to the selected customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $otherCustomer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $otherCustomerContact = ($this->createContact)($tenant, $otherCustomer->id, ['is_primary' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $otherCustomerContact->id,
        ])->assertStatus(422);

    ($this->assertStableErrors)($response);
    expect($response->json('errors.contact_id'))->not->toBe([]);
});

it('11. create rejects a contact from another tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherTenantContact = ($this->createContact)($otherTenant, $otherCustomer->id, ['is_primary' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'contact_id' => $otherTenantContact->id,
        ])->assertStatus(422);

    ($this->assertStableErrors)($response);
    expect($response->json('errors.contact_id'))->not->toBe([]);
});

it('12. create returns json 422 with stable errors when customer_id is missing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [])
        ->assertStatus(422);

    ($this->assertStableErrors)($response);
    expect($response->json('message'))->toBe('The given data was invalid.');
});

it('13. create from the shared backend endpoint appears on the sales orders index without a browser refresh redirect contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Northwind Foods']);
    $primaryContact = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $storeResponse = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertCreated()
        ->assertHeader('content-type', 'application/json');

    $orderId = (int) $storeResponse->json('data.id');

    $indexResponse = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($indexResponse, 'sales-orders-index-payload');

    expect(($this->orderPayloadIds)($payload))->toContain($orderId)
        ->and(collect($payload['orders'] ?? [])->firstWhere('id', $orderId)['customer_name'] ?? null)->toBe('Northwind Foods')
        ->and(collect($payload['orders'] ?? [])->firstWhere('id', $orderId)['contact_name'] ?? null)->toBe(trim($primaryContact->first_name . ' ' . $primaryContact->last_name))
        ->and(collect($payload['orders'] ?? [])->firstWhere('id', $orderId)['status'] ?? null)->toBe('DRAFT');
});

it('14. create from the shared backend endpoint appears on the customer detail orders mini index only for that customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Customer A']);
    $otherCustomer = ($this->createCustomer)($tenant, ['name' => 'Customer B']);
    ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    ($this->createContact)($tenant, $otherCustomer->id, ['is_primary' => true]);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertCreated();

    $createdOrderId = (int) $response->json('data.id');

    $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $otherCustomer->id,
        ])->assertCreated();

    $showResponse = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('data-section="customer-orders"', false);

    $payload = ($this->extractPayload)($showResponse, 'sales-customers-show-payload');
    $orderIds = array_map(
        static fn (array $order): int => (int) ($order['id'] ?? 0),
        $payload['orders'] ?? []
    );

    expect($orderIds)->toContain($createdOrderId)
        ->and(count($orderIds))->toBe(1)
        ->and($payload['ordersStoreUrl'] ?? null)->toBe(route('sales.orders.store'))
        ->and($payload['ordersUpdateUrlBase'] ?? null)->toBe(url('/sales/orders'));
});

it('15. guest cannot update a sales order', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    $this->patchJson(route('sales.orders.update', $order->id), [
        'customer_id' => $customer->id,
    ])->assertUnauthorized();
});

it('16. user without sales order permission cannot update a sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $customer->id,
        ])->assertForbidden();
});

it('17. update can change contact within the same customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $primaryContact = ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $replacementContact = ($this->createContact)($tenant, $customer->id, ['first_name' => 'Replacement']);
    $order = ($this->createSalesOrder)($tenant, $customer->id, $primaryContact->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $customer->id,
            'contact_id' => $replacementContact->id,
        ])->assertOk()
        ->assertHeader('content-type', 'application/json');

    $updatedOrder = ($this->fetchSalesOrder)($order->id);

    expect((int) ($updatedOrder?->contact_id ?? 0))->toBe($replacementContact->id)
        ->and((int) ($response->json('data.contact_id') ?? 0))->toBe($replacementContact->id);
});

it('17a. updating a sales order does not change status away from draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $primaryContact = ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $order = ($this->createSalesOrder)($tenant, $customer->id, $primaryContact->id, [
        'status' => 'DRAFT',
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $customer->id,
            'contact_id' => $primaryContact->id,
            'status' => 'OPEN',
        ])->assertOk()
        ->assertHeader('content-type', 'application/json');

    $updatedOrder = ($this->fetchSalesOrder)($order->id);

    expect((string) ($updatedOrder?->status ?? ''))->toBe('DRAFT')
        ->and((string) ($response->json('data.status') ?? ''))->toBe('DRAFT');
});

it('18. update changing customer resets contact to the new customer primary contact when no contact_id is submitted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $firstCustomer = ($this->createCustomer)($tenant, ['name' => 'First Customer']);
    $secondCustomer = ($this->createCustomer)($tenant, ['name' => 'Second Customer']);
    $firstPrimary = ($this->createContact)($tenant, $firstCustomer->id, ['is_primary' => true]);
    $secondPrimary = ($this->createContact)($tenant, $secondCustomer->id, ['is_primary' => true]);
    $order = ($this->createSalesOrder)($tenant, $firstCustomer->id, $firstPrimary->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $secondCustomer->id,
        ])->assertOk();

    $updatedOrder = ($this->fetchSalesOrder)($order->id);

    expect((int) ($updatedOrder?->customer_id ?? 0))->toBe($secondCustomer->id)
        ->and((int) ($updatedOrder?->contact_id ?? 0))->toBe($secondPrimary->id);
});

it('19. update changing customer uses an explicitly submitted valid contact for the new customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $firstCustomer = ($this->createCustomer)($tenant);
    $secondCustomer = ($this->createCustomer)($tenant);
    $firstPrimary = ($this->createContact)($tenant, $firstCustomer->id, ['is_primary' => true]);
    ($this->createContact)($tenant, $secondCustomer->id, ['is_primary' => true]);
    $explicitSecondContact = ($this->createContact)($tenant, $secondCustomer->id, ['first_name' => 'Explicit']);
    $order = ($this->createSalesOrder)($tenant, $firstCustomer->id, $firstPrimary->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $secondCustomer->id,
            'contact_id' => $explicitSecondContact->id,
        ])->assertOk();

    $updatedOrder = ($this->fetchSalesOrder)($order->id);

    expect((int) ($updatedOrder?->customer_id ?? 0))->toBe($secondCustomer->id)
        ->and((int) ($updatedOrder?->contact_id ?? 0))->toBe($explicitSecondContact->id);
});

it('20. update changing customer never preserves the previous customer contact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $firstCustomer = ($this->createCustomer)($tenant);
    $secondCustomer = ($this->createCustomer)($tenant);
    $firstPrimary = ($this->createContact)($tenant, $firstCustomer->id, ['is_primary' => true]);
    $secondPrimary = ($this->createContact)($tenant, $secondCustomer->id, ['is_primary' => true]);
    $order = ($this->createSalesOrder)($tenant, $firstCustomer->id, $firstPrimary->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $secondCustomer->id,
            'contact_id' => $firstPrimary->id,
        ])->assertStatus(422);

    $updatedOrder = ($this->fetchSalesOrder)($order->id);

    expect((int) ($updatedOrder?->customer_id ?? 0))->toBe($firstCustomer->id)
        ->and((int) ($updatedOrder?->contact_id ?? 0))->toBe($firstPrimary->id)
        ->and($secondPrimary->id)->not->toBe($firstPrimary->id);
});

it('21. update rejects a contact that is not owned by the selected customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $otherCustomer = ($this->createCustomer)($tenant);
    $customerPrimary = ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $otherContact = ($this->createContact)($tenant, $otherCustomer->id, ['is_primary' => true]);
    $order = ($this->createSalesOrder)($tenant, $customer->id, $customerPrimary->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $customer->id,
            'contact_id' => $otherContact->id,
        ])->assertStatus(422);

    ($this->assertStableErrors)($response);
    expect($response->json('errors.contact_id'))->not->toBe([]);
});

it('22. update returns 404 for a cross tenant sales order', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $otherOrder->id), [
            'customer_id' => $otherCustomer->id,
        ])->assertNotFound();
});

it('23. guest cannot delete a sales order', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    $this->deleteJson(route('sales.orders.destroy', $order->id))
        ->assertUnauthorized();
});

it('24. user without sales order permission cannot delete a sales order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.destroy', $order->id))
        ->assertForbidden();
});

it('25. authorized user can delete a draft sales order and the index no longer shows it', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.destroy', $order->id))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJson([
            'message' => 'Deleted.',
        ]);

    expect(($this->fetchSalesOrder)($order->id))->toBeNull();

    $indexResponse = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($indexResponse, 'sales-orders-index-payload');

    expect(($this->orderPayloadIds)($payload))->not->toContain($order->id);
});

it('26. delete returns 404 for a cross tenant sales order', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherCustomer = ($this->createCustomer)($otherTenant);
    $otherOrder = ($this->createSalesOrder)($otherTenant, $otherCustomer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.destroy', $otherOrder->id))
        ->assertNotFound();
});

it('27. sales order index returns only sales orders for the authenticated tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Visible Customer']);
    $otherCustomer = ($this->createCustomer)($otherTenant, ['name' => 'Hidden Customer']);
    $visibleOrder = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createSalesOrder)($otherTenant, $otherCustomer->id);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk()
        ->assertSee('Visible Customer')
        ->assertDontSee('Hidden Customer');

    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');

    expect(($this->orderPayloadIds)($payload))->toContain($visibleOrder->id)
        ->and(count($payload['orders'] ?? []))->toBe(1);
});

it('28. customer detail orders mini index returns only that customers orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Target Customer']);
    $otherCustomer = ($this->createCustomer)($tenant, ['name' => 'Other Customer']);
    $targetOrder = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createSalesOrder)($tenant, $otherCustomer->id);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('Orders')
        ->assertSee('Target Customer');

    $payload = ($this->extractPayload)($response, 'sales-customers-show-payload');
    $orderIds = array_map(
        static fn (array $order): int => (int) ($order['id'] ?? 0),
        $payload['orders'] ?? []
    );

    expect($orderIds)->toContain($targetOrder->id)
        ->and(count($orderIds))->toBe(1);
});

it('29. customer detail orders section is hidden without sales sales orders manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-view');

    $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertDontSee('data-section="customer-orders"', false)
        ->assertDontSee('Add Order');
});

it('30. customer detail orders section is shown with sales sales orders manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertSee('data-section="customer-orders"', false)
        ->assertSee('Add Order');
});

it('31. customer detail orders mini index crud endpoints respect sales sales orders manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $primaryContact = ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);
    $order = ($this->createSalesOrder)($tenant, $customer->id, $primaryContact->id);

    ($this->grantPermission)($user, 'sales-customers-view');

    $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order->id), [
            'customer_id' => $customer->id,
            'contact_id' => $primaryContact->id,
        ])->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('sales.orders.destroy', $order->id))
        ->assertForbidden();
});

it('32. sales orders index payload only includes contacts for each selected customer without primary labels', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $firstCustomer = ($this->createCustomer)($tenant, ['name' => 'First Customer']);
    $secondCustomer = ($this->createCustomer)($tenant, ['name' => 'Second Customer']);
    $firstPrimary = ($this->createContact)($tenant, $firstCustomer->id, [
        'first_name' => 'Alice',
        'last_name' => 'Buyer',
        'is_primary' => true,
    ]);
    ($this->createContact)($tenant, $secondCustomer->id, [
        'first_name' => 'Bob',
        'last_name' => 'Other',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $firstCustomerPayload = collect($payload['customers'] ?? [])->firstWhere('id', $firstCustomer->id);
    $contactNames = array_map(
        static fn (array $contact): string => (string) ($contact['full_name'] ?? ''),
        $firstCustomerPayload['contacts'] ?? []
    );

    expect($contactNames)->toContain(trim($firstPrimary->first_name . ' ' . $firstPrimary->last_name))
        ->and($contactNames)->not->toContain('Bob Other')
        ->and(collect($contactNames)->contains(fn (string $name): bool => str_contains($name, 'Primary')))->toBeFalse();
});

it('33. customer detail order payload only includes contacts for each selected customer without primary labels', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Target Customer']);
    $otherCustomer = ($this->createCustomer)($tenant, ['name' => 'Other Customer']);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'is_primary' => true,
    ]);
    ($this->createContact)($tenant, $otherCustomer->id, [
        'first_name' => 'Hidden',
        'last_name' => 'Other',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'sales-customers-show-payload');
    $customerPayload = collect($payload['orderCustomers'] ?? [])->firstWhere('id', $customer->id);
    $contactNames = array_map(
        static fn (array $contact): string => (string) ($contact['full_name'] ?? ''),
        $customerPayload['contacts'] ?? []
    );

    expect($contactNames)->toContain('Jane Buyer')
        ->and($contactNames)->not->toContain('Hidden Other')
        ->and(collect($contactNames)->contains(fn (string $name): bool => str_contains($name, 'Primary')))->toBeFalse();
});

it('34. sales order contact dropdown text does not display the word Primary on the sales orders index page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->get(route('sales.orders.index'))
        ->assertOk()
        ->assertDontSee('Primary contact / none');
});

it('35. sales order contact dropdown text does not display the word Primary on the customer detail page order form', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, ['is_primary' => true]);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $this->actingAs($user)
        ->get(route('sales.customers.show', $customer->id))
        ->assertOk()
        ->assertDontSee('Primary contact / none');
});
