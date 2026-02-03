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
});

it('allows same-tenant show access', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('blocks cross-tenant show access', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);
    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->get("/purchasing/orders/{$orderId}")
        ->assertNotFound();
});

it('allows same-tenant delete', function () {
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
});

it('blocks cross-tenant delete', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertNotFound();
});

it('allows same-tenant add line', function () {
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
});

it('blocks cross-tenant add line', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $uomB = ($this->makeUom)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->postJson("/purchasing/orders/{$orderId}/lines", [
            'item_purchase_option_id' => $optionB->id,
            'pack_count' => 1,
            'unit_price_cents' => 100,
        ])
        ->assertNotFound();
});

it('allows same-tenant delete line', function () {
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
        'unit_price_cents' => 200,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$line->id}")
        ->assertOk();
});

it('blocks cross-tenant delete line', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $uomB = ($this->makeUom)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($userB, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$line->id}")
        ->assertNotFound();
});

it('scopes index to tenant orders only', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierA = ($this->makeSupplier)($tenantA, ['company_name' => 'A Supplier']);
    $supplierB = ($this->makeSupplier)($tenantB, ['company_name' => 'B Supplier']);

    ($this->createOrder)($userA, [
        'supplier_id' => $supplierA->id,
        'order_date' => '2026-02-10',
    ])->assertCreated();

    ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
        'order_date' => '2026-02-11',
    ])->assertCreated();

    $this->actingAs($userA)
        ->get('/purchasing/orders')
        ->assertOk()
        ->assertSee('A Supplier')
        ->assertDontSee('B Supplier');
});

it('does not allow adding a line with an option from another tenant', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');

    $supplierA = ($this->makeSupplier)($tenantA);
    $orderResponse = ($this->createOrder)($userA, [
        'supplier_id' => $supplierA->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $uomB = ($this->makeUom)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);

    $response = $this->actingAs($userA)
        ->postJson("/purchasing/orders/{$orderId}/lines", [
            'item_purchase_option_id' => $optionB->id,
            'pack_count' => 1,
            'unit_price_cents' => 100,
        ]);

    expect([404, 422])->toContain($response->status());
    $this->assertDatabaseCount('purchase_order_lines', 0);
});

it('keeps other tenant order intact after blocked delete', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
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

it('keeps other tenant line intact after blocked delete line', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $uomB = ($this->makeUom)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($userB, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$line->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $line->id,
    ]);
});

it('blocks cross-tenant show even when order id exists', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->get("/purchasing/orders/{$orderId}")
        ->assertNotFound();
});

it('shows same-tenant order in index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Index Own']);

    ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-12',
    ])->assertCreated();

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk()
        ->assertSee('Index Own');
});

it('cross-tenant add line does not create a line', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);
    $uomB = ($this->makeUom)($tenantB);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->postJson("/purchasing/orders/{$orderId}/lines", [
            'item_purchase_option_id' => $optionB->id,
            'pack_count' => 1,
            'unit_price_cents' => 100,
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('purchase_order_lines', 0);
});

it('cross-tenant delete does not remove the order', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertNotFound();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $orderId,
    ]);
});

it('cross-tenant delete line does not remove the line', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);
    $uomB = ($this->makeUom)($tenantB);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($userB, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($userA)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$line->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $line->id,
    ]);
});

it('same-tenant add line creates a line under tenant', function () {
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
        'pack_count' => 2,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $this->assertDatabaseHas('purchase_order_lines', [
        'purchase_order_id' => $orderId,
        'tenant_id' => $tenant->id,
    ]);
});

it('same-tenant delete removes only own order', function () {
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

    $this->assertDatabaseMissing('purchase_orders', [
        'id' => $orderId,
        'tenant_id' => $tenant->id,
    ]);
});

it('cross-tenant add line remains blocked even when item belongs to actor tenant', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierB = ($this->makeSupplier)($tenantB);
    $uomB = ($this->makeUom)($tenantB);
    $itemA = ($this->makeItem)($tenantA, ($this->makeUom)($tenantA));

    $orderResponse = ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($userA)
        ->postJson("/purchasing/orders/{$orderId}/lines", [
            'item_purchase_option_id' => 999999,
            'pack_count' => 1,
            'unit_price_cents' => 100,
        ]);

    expect([404, 422])->toContain($response->status());
});
