<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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
        $symbol = $attributes['symbol'] ?? ('u' . $this->uomCounter);

        if (array_key_exists('symbol', $attributes)) {
            $existing = Uom::query()
                ->where('tenant_id', $tenant->id)
                ->where('symbol', $symbol)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $this->uomCounter,
            'symbol' => $symbol,
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

    $this->makeOption = function (Tenant $tenant, Supplier $supplier, Item $item, Uom $uom): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => 'SKU-' . $item->id,
            'pack_quantity' => '1.000000',
            'pack_uom_id' => $uom->id,
        ]);
    };

    $this->makeOrder = function (Tenant $tenant, User $user, Supplier $supplier): PurchaseOrder {
        return PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => '2026-02-04',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_number' => 'PO-' . $tenant->id,
            'notes' => null,
            'status' => 'OPEN',
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ]);
    };

    $this->makeLine = function (Tenant $tenant, PurchaseOrder $order, Item $item, ItemPurchaseOption $option, int $packCount = 10): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $order->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => $packCount,
            'unit_price_cents' => 100,
            'line_subtotal_cents' => 1000,
            'unit_price_amount' => 100,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 100,
            'fx_rate' => '1.00000000',
            'fx_rate_as_of' => '2026-02-04',
        ]);
    };

    $this->setOrderStatus = function (PurchaseOrder $order, string $status): void {
        $order->forceFill(['status' => $status])->save();
    };

    $this->patchStatus = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->patchJson("/purchasing/orders/{$order->id}/status", $payload);
    };

    $this->postReceipt = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/receipts", $payload);
    };

    $this->postShortClose = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/short-closures", $payload);
    };
});

it('rejects guests on status changes', function () {
    $this->patchJson('/purchasing/orders/1/status', [])
        ->assertUnauthorized();
});

it('rejects authed users without receive permission on status changes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->patchJson('/purchasing/orders/1/status', [])
        ->assertForbidden();
});

it('blocks cross-tenant status updates', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    $orderB = ($this->makeOrder)($tenantB, $userB, $supplierB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($userA, $orderB, ['status' => 'OPEN'])
        ->assertNotFound();
});

it('allows draft to open transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'OPEN'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('OPEN');
});

it('allows open to back-ordered transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'BACK-ORDERED'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('BACK-ORDERED');
});

it('allows back-ordered to open transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'BACK-ORDERED');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'OPEN'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('OPEN');
});

it('allows open to cancelled transition with no receipts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'CANCELLED'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('CANCELLED');
});

it('blocks open to cancelled transition when receipts exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 10:00:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();

    ($this->patchStatus)($user, $order, ['status' => 'CANCELLED'])
        ->assertStatus(422);
});

it('rejects draft to back-ordered transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'BACK-ORDERED'])
        ->assertStatus(422);
});

it('rejects draft to cancelled transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'CANCELLED'])
        ->assertStatus(422);
});

it('rejects setting received status directly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'RECEIVED'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects setting partially-received status directly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'PARTIALLY-RECEIVED'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects setting short-closed status directly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'SHORT-CLOSED'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('returns validation errors for invalid status payloads', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'NOT-A-STATUS'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('derives partially-received after first receipt with remaining balance', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 10);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 10:30:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '3.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('PARTIALLY-RECEIVED');
});

it('derives received when all balances are zero and no short-close exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 5);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 10:45:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '5.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('RECEIVED');
});

it('derives short-closed when balances are zero and any short-close exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 5);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 11:00:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '5.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('SHORT-CLOSED');
});

it('short-close status takes precedence over receipts when fully balanced', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 6);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 11:15:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 11:30:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '2.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('SHORT-CLOSED');
});

it('short-close after partial receipt closes remaining balance', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 8);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 11:20:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '3.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('PARTIALLY-RECEIVED');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 11:25:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '5.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('SHORT-CLOSED');
});

it('accumulates multiple receipts to reach received status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 6);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 11:45:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('PARTIALLY-RECEIVED');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 12:00:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('RECEIVED');
});

it('requires create permission for index even with receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertForbidden();
});

it('requires create permission for show even with receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    $this->actingAs($user)
        ->get("/purchasing/orders/{$order->id}")
        ->assertForbidden();
});

it('allows index with create permission even without receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk();
});

it('allows show with create permission even without receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->get("/purchasing/orders/{$order->id}")
        ->assertOk();
});
