<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->supplierCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => null,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

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

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $this->uomCounter,
            'symbol' => $attributes['symbol'] ?? ('u' . $this->uomCounter),
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        $supplier = Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $this->supplierCounter,
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeOption = function (Tenant $tenant, Supplier $supplier, Item $item, Uom $uom, array $attributes = []): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => $attributes['supplier_sku'] ?? 'SKU-1',
            'pack_quantity' => $attributes['pack_quantity'] ?? '5.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));
    };

    $this->createOrder = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson('/purchasing/orders', $payload);
    };

    $this->addLine = function (User $user, int $orderId, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$orderId}/lines", $payload);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\s*(.*?)\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };
});

it('rejects guests on delete', function () {
    $this->deleteJson('/purchasing/orders/1')
        ->assertUnauthorized();
});

it('rejects authed users without permission on delete', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->deleteJson('/purchasing/orders/1')
        ->assertForbidden();
});

it('rejects users with only view permission on delete', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-view');

    $this->actingAs($user)
        ->deleteJson('/purchasing/orders/1')
        ->assertForbidden();
});

it('deletes a purchase order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-06',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();

    $this->assertDatabaseMissing('purchase_orders', [
        'id' => $orderId,
    ]);
});

it('delete removes order from index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-06',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    expect(collect($orders)->pluck('id')->all())
        ->not()
        ->toContain($orderId);
});

it('delete makes show return not found', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-07',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertNotFound();
});

it('delete removes associated lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $this->assertDatabaseCount('purchase_order_lines', 1);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();

    $this->assertDatabaseCount('purchase_order_lines', 0);
});

it('delete works for order without lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('delete works for order with lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('delete returns not found for missing order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->deleteJson('/purchasing/orders/99999')
        ->assertNotFound();
});

it('delete does not remove other tenant orders', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($userB, [
        'order_date' => '2026-02-08',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertNotFound();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $orderId,
        'tenant_id' => $tenantB->id,
    ]);
});

it('delete does not remove other orders in same tenant', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderA = ($this->createOrder)($user, [
        'order_date' => '2026-02-09',
    ])->assertCreated();

    $orderB = ($this->createOrder)($user, [
        'order_date' => '2026-02-10',
    ])->assertCreated();

    $orderIdA = (int) ($orderA->json('data.id') ?? 0);
    $orderIdB = (int) ($orderB->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderIdA}")
        ->assertOk();

    $this->assertDatabaseMissing('purchase_orders', ['id' => $orderIdA]);
    $this->assertDatabaseHas('purchase_orders', ['id' => $orderIdB]);
});

it('delete keeps other order lines intact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderA = ($this->createOrder)($user, ['supplier_id' => $supplier->id])->assertCreated();
    $orderB = ($this->createOrder)($user, ['supplier_id' => $supplier->id])->assertCreated();

    $orderIdA = (int) ($orderA->json('data.id') ?? 0);
    $orderIdB = (int) ($orderB->json('data.id') ?? 0);

    ($this->addLine)($user, $orderIdA, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderIdB, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderIdA}")
        ->assertOk();

    $this->assertDatabaseHas('purchase_order_lines', [
        'purchase_order_id' => $orderIdB,
    ]);
});

it('delete index reflects remaining orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderA = ($this->createOrder)($user, [
        'order_date' => '2026-02-11',
    ])->assertCreated();

    $orderB = ($this->createOrder)($user, [
        'order_date' => '2026-02-12',
    ])->assertCreated();

    $orderIdA = (int) ($orderA->json('data.id') ?? 0);
    $orderIdB = (int) ($orderB->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderIdA}")
        ->assertOk();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    expect(collect($orders)->pluck('id')->all())
        ->not()->toContain($orderIdA)
        ->and(collect($orders)->pluck('id')->all())->toContain($orderIdB);
});

it('delete does not change other order status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderA = ($this->createOrder)($user, [
        'order_date' => '2026-02-13',
    ])->assertCreated();

    $orderB = ($this->createOrder)($user, [
        'order_date' => '2026-02-14',
    ])->assertCreated();

    $orderIdA = (int) ($orderA->json('data.id') ?? 0);
    $orderIdB = (int) ($orderB->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderIdA}")
        ->assertOk();

    $other = DB::table('purchase_orders')->where('id', $orderIdB)->first();
    expect((string) ($other->status ?? ''))->toBe('DRAFT');
});

it('delete keeps other order headers intact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderA = ($this->createOrder)($user, [
        'order_date' => '2026-02-15',
    ])->assertCreated();

    $orderB = ($this->createOrder)($user, [
        'order_date' => '2026-02-16',
        'po_number' => 'PO-KEEP',
    ])->assertCreated();

    $orderIdA = (int) ($orderA->json('data.id') ?? 0);
    $orderIdB = (int) ($orderB->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderIdA}")
        ->assertOk();

    $other = DB::table('purchase_orders')->where('id', $orderIdB)->first();
    expect((string) ($other->po_number ?? ''))->toBe('PO-KEEP');
});

it('delete returns ok status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-17',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('delete leaves unrelated records untouched', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
    ]);
});

it('delete returns not found for cross-tenant order', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($userB, [
        'order_date' => '2026-02-18',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertNotFound();
});

it('delete makes index empty when only order is removed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-19',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    expect($orders)->toBeArray();
    expect(count($orders))->toBe(0);
});
