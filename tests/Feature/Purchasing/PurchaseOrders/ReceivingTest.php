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

    $this->makeLine = function (Tenant $tenant, PurchaseOrder $order, Item $item, ItemPurchaseOption $option): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $order->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => 10,
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

    $this->postReceipt = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/receipts", $payload);
    };

    $this->postShortClose = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/short-closures", $payload);
    };
});

it('rejects guests on receipt creation', function () {
    $this->postJson('/purchasing/orders/1/receipts', [])
        ->assertUnauthorized();
});

it('rejects authed users without receive permission on receipts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->postJson('/purchasing/orders/1/receipts', [])
        ->assertForbidden();
});

it('allows receipt creation with receive permission', function () {
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
        'received_quantity' => '2.500000',
    ])->assertCreated();
});

it('returns receipt id in response payload', function () {
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
        'received_at' => '2026-02-04 10:05:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);
});

it('defaults received_at when omitted', function () {
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
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();

    $receipt = DB::table('purchase_order_receipts')
        ->where('purchase_order_id', $order->id)
        ->first();

    expect($receipt)->not->toBeNull();
    expect($receipt->received_at)->not->toBeNull();
});

it('blocks cross-tenant receipt creation', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $supplierB = ($this->makeSupplier)($tenantB);
    $uomB = ($this->makeUom)($tenantB, ['symbol' => 'b1']);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);
    $orderB = ($this->makeOrder)($tenantB, $userA, $supplierB);
    $lineB = ($this->makeLine)($tenantB, $orderB, $itemB, $optionB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($userA, $orderB, [
        'purchase_order_line_id' => $lineB->id,
        'received_quantity' => '1.000000',
    ])->assertNotFound();
});

it('rejects receipts when status is draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('rejects receipts when status is received', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'RECEIVED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('rejects receipts when status is short-closed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'SHORT-CLOSED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('rejects receipts when status is cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'CANCELLED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('allows receipts when status is open', function () {
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
        'received_at' => '2026-02-04 10:15:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();
});

it('allows receipts when status is back-ordered', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'BACK-ORDERED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 11:00:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();
});

it('allows receipts when status is partially received', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'PARTIALLY-RECEIVED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 11:30:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();
});

it('creates receipt headers and lines', function () {
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
        'received_at' => '2026-02-04 12:00:00',
        'reference' => 'RCPT-1',
        'notes' => 'First receipt',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '3.000000',
    ])->assertCreated();

    $receipt = DB::table('purchase_order_receipts')
        ->where('purchase_order_id', $order->id)
        ->first();

    expect($receipt)->not->toBeNull();
    expect((int) $receipt->received_by_user_id)->toBe($user->id);
    expect((string) ($receipt->reference ?? ''))->toBe('RCPT-1');
    expect((string) ($receipt->notes ?? ''))->toBe('First receipt');

    $receiptLine = DB::table('purchase_order_receipt_lines')
        ->where('purchase_order_receipt_id', $receipt->id)
        ->first();

    expect($receiptLine)->not->toBeNull();
    expect((int) $receiptLine->purchase_order_line_id)->toBe($line->id);
    expect((string) $receiptLine->received_quantity)->toBe('3.000000');
});

it('creates stock moves linked to receipt lines', function () {
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
        'received_at' => '2026-02-04 12:10:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    $receiptLine = DB::table('purchase_order_receipt_lines')
        ->where('purchase_order_line_id', $line->id)
        ->first();

    $stockMove = DB::table('stock_moves')
        ->where('source_type', 'purchase_order_receipt_line')
        ->where('source_id', $receiptLine->id)
        ->first();

    expect($stockMove)->not->toBeNull();
    expect((int) $stockMove->item_id)->toBe($item->id);
    expect((int) $stockMove->uom_id)->toBe($uom->id);
    expect((string) $stockMove->quantity)->toBe('4.000000');
    expect((string) $stockMove->type)->toBe('receipt');
    expect((string) $stockMove->status)->toBe('POSTED');
    expect((string) $stockMove->source_type)->toBe('purchase_order_receipt_line');
    expect((int) $stockMove->source_id)->toBe((int) $receiptLine->id);
});

it('supports multi-line receipt payloads', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Item A']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Item B']);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $lineA = ($this->makeLine)($tenant, $order, $itemA, $optionA);
    $lineB = ($this->makeLine)($tenant, $order, $itemB, $optionB);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 12:30:00',
        'lines' => [
            ['purchase_order_line_id' => $lineA->id, 'received_quantity' => '1.500000'],
            ['purchase_order_line_id' => $lineB->id, 'received_quantity' => '2.000000'],
        ],
    ])->assertCreated();

    $receipt = DB::table('purchase_order_receipts')
        ->where('purchase_order_id', $order->id)
        ->first();

    $linesCount = DB::table('purchase_order_receipt_lines')
        ->where('purchase_order_receipt_id', $receipt->id)
        ->count();

    expect($linesCount)->toBe(2);
});

it('validates receipt quantity must be greater than zero', function () {
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
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '0.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['received_quantity']);
});

it('validates receipt quantity against remaining balance', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    DB::table('purchase_order_receipts')->insert([
        'tenant_id' => $tenant->id,
        'purchase_order_id' => $order->id,
        'received_at' => '2026-02-04 08:00:00',
        'received_by_user_id' => $user->id,
        'created_at' => '2026-02-04 08:00:00',
        'updated_at' => '2026-02-04 08:00:00',
    ]);

    $receiptId = (int) DB::table('purchase_order_receipts')
        ->where('purchase_order_id', $order->id)
        ->value('id');

    DB::table('purchase_order_receipt_lines')->insert([
        'tenant_id' => $tenant->id,
        'purchase_order_receipt_id' => $receiptId,
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '9.000000',
        'created_at' => '2026-02-04 08:00:00',
        'updated_at' => '2026-02-04 08:00:00',
    ]);

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['received_quantity']);
});

it('returns nested receipt errors for invalid multi-line payloads', function () {
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
        'lines' => [
            ['purchase_order_line_id' => $line->id, 'received_quantity' => '0.000000'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['lines.0.received_quantity']);
});

it('rejects guests on short-close creation', function () {
    $this->postJson('/purchasing/orders/1/short-closures', [])
        ->assertUnauthorized();
});

it('rejects authed users without receive permission on short-closures', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->postJson('/purchasing/orders/1/short-closures', [])
        ->assertForbidden();
});

it('allows short-close creation with receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 13:00:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '2.000000',
    ])->assertCreated();
});

it('returns short-close id in response payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 13:05:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);
});

it('defaults short_closed_at when omitted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertCreated();

    $shortClose = DB::table('purchase_order_short_closures')
        ->where('purchase_order_id', $order->id)
        ->first();

    expect($shortClose)->not->toBeNull();
    expect($shortClose->short_closed_at)->not->toBeNull();
});

it('blocks cross-tenant short-close creation', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $supplierB = ($this->makeSupplier)($tenantB);
    $uomB = ($this->makeUom)($tenantB, ['symbol' => 'b2']);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);
    $orderB = ($this->makeOrder)($tenantB, $userA, $supplierB);
    $lineB = ($this->makeLine)($tenantB, $orderB, $itemB, $optionB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($userA, $orderB, [
        'purchase_order_line_id' => $lineB->id,
        'short_closed_quantity' => '1.000000',
    ])->assertNotFound();
});

it('supports multi-line short-close payloads', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Item A']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Item B']);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $lineA = ($this->makeLine)($tenant, $order, $itemA, $optionA);
    $lineB = ($this->makeLine)($tenant, $order, $itemB, $optionB);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 13:10:00',
        'lines' => [
            ['purchase_order_line_id' => $lineA->id, 'short_closed_quantity' => '1.250000'],
            ['purchase_order_line_id' => $lineB->id, 'short_closed_quantity' => '2.000000'],
        ],
    ])->assertCreated();

    $shortClose = DB::table('purchase_order_short_closures')
        ->where('purchase_order_id', $order->id)
        ->first();

    $linesCount = DB::table('purchase_order_short_closure_lines')
        ->where('purchase_order_short_closure_id', $shortClose->id)
        ->count();

    expect($linesCount)->toBe(2);
});

it('allows short-close when status is back-ordered', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'BACK-ORDERED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 13:15:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertCreated();
});

it('allows short-close when status is partially received', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'PARTIALLY-RECEIVED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 13:20:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertCreated();
});

it('creates short-close headers and lines without stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 13:30:00',
        'reference' => 'SC-1',
        'notes' => 'Short close',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '3.000000',
    ])->assertCreated();

    $shortClose = DB::table('purchase_order_short_closures')
        ->where('purchase_order_id', $order->id)
        ->first();

    expect($shortClose)->not->toBeNull();
    expect((int) $shortClose->short_closed_by_user_id)->toBe($user->id);
    expect((string) ($shortClose->reference ?? ''))->toBe('SC-1');
    expect((string) ($shortClose->notes ?? ''))->toBe('Short close');

    $shortCloseLine = DB::table('purchase_order_short_closure_lines')
        ->where('purchase_order_short_closure_id', $shortClose->id)
        ->first();

    expect($shortCloseLine)->not->toBeNull();
    expect((int) $shortCloseLine->purchase_order_line_id)->toBe($line->id);
    expect((string) $shortCloseLine->short_closed_quantity)->toBe('3.000000');

    $moves = DB::table('stock_moves')
        ->where('source_type', 'purchase_order_short_closure_line')
        ->count();

    expect($moves)->toBe(0);
});

it('validates short-close quantity must be greater than zero', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '0.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['short_closed_quantity']);
});

it('returns nested short-close errors for invalid multi-line payloads', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'lines' => [
            ['purchase_order_line_id' => $line->id, 'short_closed_quantity' => '0.000000'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['lines.0.short_closed_quantity']);
});

it('validates short-close quantity against remaining balance', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    DB::table('purchase_order_short_closures')->insert([
        'tenant_id' => $tenant->id,
        'purchase_order_id' => $order->id,
        'short_closed_at' => '2026-02-04 09:00:00',
        'short_closed_by_user_id' => $user->id,
        'created_at' => '2026-02-04 09:00:00',
        'updated_at' => '2026-02-04 09:00:00',
    ]);

    $shortCloseId = (int) DB::table('purchase_order_short_closures')
        ->where('purchase_order_id', $order->id)
        ->value('id');

    DB::table('purchase_order_short_closure_lines')->insert([
        'tenant_id' => $tenant->id,
        'purchase_order_short_closure_id' => $shortCloseId,
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '9.000000',
        'created_at' => '2026-02-04 09:00:00',
        'updated_at' => '2026-02-04 09:00:00',
    ]);

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '2.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['short_closed_quantity']);
});

it('rejects short-close when status is draft', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('rejects short-close when status is received', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'RECEIVED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('rejects short-close when status is short-closed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'SHORT-CLOSED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('rejects short-close when status is cancelled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'CANCELLED');
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->postShortClose)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '1.000000',
    ])->assertStatus(422);
});
