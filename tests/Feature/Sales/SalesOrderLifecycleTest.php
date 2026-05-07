<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
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
        $role = Role::query()->create(['name' => 'sales-order-lifecycle-role-' . $this->roleCounter]);

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

    $this->createSalesOrder = function (
        Tenant $tenant,
        int $customerId,
        array $attributes = []
    ): SalesOrder {
        return SalesOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => null,
            'status' => SalesOrder::STATUS_DRAFT,
        ], $attributes));
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? 'SOL-UOM-' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Lifecycle Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Lifecycle UOM ' . $this->uomCounter,
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
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1000,
            'default_price_currency_code' => 'USD',
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->createLine = function (Tenant $tenant, SalesOrder $order, Item $item, array $attributes = []): void {
        DB::table('sales_order_lines')->insert(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => '1.000000',
            'unit_price_cents' => 1000,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '1000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    };

    $this->createReceipt = function (Tenant $tenant, Item $item, string $quantity): void {
        DB::table('stock_moves')->insert([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => bcadd($quantity, '0', 6),
            'type' => 'receipt',
            'status' => 'POSTED',
            'source_type' => null,
            'source_id' => null,
            'created_at' => now(),
        ]);
    };

    $this->fetchOrder = fn (SalesOrder $order): SalesOrder => SalesOrder::query()->findOrFail($order->id);

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '[]', true);

        return is_array($payload) ? $payload : [];
    };

    $this->transitionOrder = function (User $user, SalesOrder $order, string $status) {
        return $this->actingAs($user)->patchJson(route('sales.orders.status.update', $order), [
            'status' => $status,
        ]);
    };
});

it('1. draft to open still works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_OPEN)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_OPEN);
});

it('2. open to packing works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKING);
});

it('3. packing to packed works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKED);
});

it('4. packed to shipping works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_SHIPPING);
});

it('5. shipping to completed works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_COMPLETED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_COMPLETED);
});

it('6. open to packed is rejected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'Status transition is not allowed.');
});

it('7. open to shipping is rejected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)->assertStatus(422);
});

it('8. packing to shipping is rejected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)->assertStatus(422);
});

it('9. packed to completed is rejected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_COMPLETED)->assertStatus(422);
});

it('10. completed is terminal', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_COMPLETED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertStatus(422);
});

it('11. cancelled is terminal', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_CANCELLED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_OPEN)->assertStatus(422);
});

it('31. packed to shipping is manual with no extra payload requirements', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_SHIPPING);
});

it('32. shipping to completed is manual with no extra payload requirements', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_COMPLETED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_COMPLETED);
});

it('35. shipping cannot be cancelled in this pr', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertStatus(422);
});

it('36. open to cancelled works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_CANCELLED);
});

it('37. packing to cancelled works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_CANCELLED);
});

it('38. packed to cancelled works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_CANCELLED);
});

it('40. cancellation from shipping is blocked', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertStatus(422);
});

it('41. cancellation from completed is blocked', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_COMPLETED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertStatus(422);
});

it('45. sales order manage permission allows lifecycle transitions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_OPEN)->assertOk();
});

it('46. unauthorized user cannot transition lifecycle statuses', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_OPEN)->assertForbidden();
});

it('47. unauthenticated user cannot transition lifecycle statuses', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    $this->patchJson(route('sales.orders.status.update', $order), [
        'status' => SalesOrder::STATUS_OPEN,
    ])->assertUnauthorized();
});

it('48. no new permission is required for lifecycle transitions', function () {
    $permissionSlugs = Permission::query()->pluck('slug')->all();

    expect($permissionSlugs)->not->toContain('sales-sales-orders-pack')
        ->and($permissionSlugs)->not->toContain('sales-sales-orders-ship')
        ->and($permissionSlugs)->not->toContain('sales-sales-orders-complete');
});

it('49. sales order view payload exposes only valid next lifecycle buttons', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $draft = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_DRAFT]);
    $open = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    $packing = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKING]);
    $packed = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    $shipping = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orders = collect($payload['orders'] ?? [])->keyBy('id');

    expect($orders[$draft->id]['available_status_transitions'] ?? null)->toBe([SalesOrder::STATUS_OPEN])
        ->and($orders[$open->id]['available_status_transitions'] ?? null)->toBe([SalesOrder::STATUS_PACKING, SalesOrder::STATUS_CANCELLED])
        ->and($orders[$packing->id]['available_status_transitions'] ?? null)->toBe([SalesOrder::STATUS_PACKED, SalesOrder::STATUS_CANCELLED])
        ->and($orders[$packed->id]['available_status_transitions'] ?? null)->toBe([SalesOrder::STATUS_SHIPPING, SalesOrder::STATUS_CANCELLED])
        ->and($orders[$shipping->id]['available_status_transitions'] ?? null)->toBe([SalesOrder::STATUS_COMPLETED]);
});

it('50. open shows move to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_PACKING);
});

it('51. packing shows move to packed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_PACKED);
});

it('52. packed shows move to shipping', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_SHIPPING);
});

it('53. shipping shows move to completed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_COMPLETED);
});

it('54. cancel action appears only where allowed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $open = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    $packing = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKING]);
    $packed = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKED]);
    $shipping = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orders = collect($payload['orders'] ?? [])->keyBy('id');

    expect($orders[$open->id]['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_CANCELLED)
        ->and($orders[$packing->id]['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_CANCELLED)
        ->and($orders[$packed->id]['available_status_transitions'] ?? [])->toContain(SalesOrder::STATUS_CANCELLED)
        ->and($orders[$shipping->id]['available_status_transitions'] ?? [])->not->toContain(SalesOrder::STATUS_CANCELLED);
});

it('55. blocked transitions return clear json user facing errors', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Status transition is not allowed.',
        ])
        ->assertJsonStructure([
            'message',
            'errors' => [
                'status',
            ],
        ]);
});

it('56. existing sales order create edit behavior still works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Original']);
    $replacementCustomer = ($this->createCustomer)($tenant, ['name' => 'Replacement']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $createResponse = $this->actingAs($user)
        ->postJson(route('sales.orders.store'), [
            'customer_id' => $customer->id,
        ])->assertCreated();

    $order = SalesOrder::query()->findOrFail((int) $createResponse->json('data.id'));

    $this->actingAs($user)
        ->patchJson(route('sales.orders.update', $order), [
            'customer_id' => $replacementCustomer->id,
        ])->assertOk();

    expect($order->fresh()->customer_id)->toBe($replacementCustomer->id);
});

it('57. existing sales order line behavior still works', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Sellable']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $response = $this->actingAs($user)
        ->postJson(route('sales.orders.lines.store', $order), [
            'item_id' => $item->id,
            'quantity' => '2.000000',
        ])->assertCreated();

    expect($response->json('data.line.item_name'))->toBe('Sellable')
        ->and($response->json('data.line.quantity'))->toBe('2.000000');
});

it('59. future quantity display is not introduced', function () {
    $source = file_get_contents(base_path('resources/views/sales/orders/index.blade.php'));

    expect($source)->not->toContain('Future quantity')
        ->and($source)->not->toContain('future_quantity');
});

it('60. task checklist system is introduced on the sales orders page', function () {
    $source = file_get_contents(base_path('resources/views/sales/orders/index.blade.php'));

    expect($source)->toContain('Checklist')
        ->and($source)->toContain('current_stage_tasks');
});
