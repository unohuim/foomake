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

    $this->deleteLine = function (User $user, int $orderId, int $lineId) {
        return $this->actingAs($user)->deleteJson("/purchasing/orders/{$orderId}/lines/{$lineId}");
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

it('rejects guests on delete line', function () {
    $this->deleteJson('/purchasing/orders/1/lines/1')
        ->assertUnauthorized();
});

it('rejects authed users without permission on delete line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->deleteJson('/purchasing/orders/1/lines/1')
        ->assertForbidden();
});

it('removes a line and updates totals', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Line Item A']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Line Item B']);

    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom, ['supplier_sku' => 'A-1']);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom, ['supplier_sku' => 'B-1']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 100,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 2,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 400,
    ])->assertCreated();

    $lines = DB::table('purchase_order_lines')
        ->where('purchase_order_id', $orderId)
        ->orderBy('id')
        ->get();

    $lineToDelete = $lines[0];
    $lineToKeep = $lines[1];

    ($this->deleteLine)($user, $orderId, (int) $lineToDelete->id)
        ->assertOk();

    $this->assertDatabaseMissing('purchase_order_lines', [
        'id' => $lineToDelete->id,
    ]);

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();

    expect((int) $order->po_subtotal_cents)->toBe(400);
    expect((int) $order->po_grand_total_cents)->toBe(500);

    $response = $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');
    $payloadLines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect($payloadLines)->toHaveCount(1);

    $remaining = $payloadLines[0] ?? [];
    $remainingId = $remaining['id'] ?? null;

    if ($remainingId !== null) {
        expect((int) $remainingId)->toBe((int) $lineToKeep->id);
    }
});

it('removing the last line zeroes totals', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 0,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();

    expect((int) $order->po_subtotal_cents)->toBe(0);
    expect((int) $order->po_grand_total_cents)->toBe(0);
});

it('delete line returns not found for missing line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/99999")
        ->assertNotFound();
});

it('delete line returns not found when line does not belong to order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderA = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderB = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderIdA = (int) ($orderA->json('data.id') ?? 0);
    $orderIdB = (int) ($orderB->json('data.id') ?? 0);

    ($this->addLine)($user, $orderIdA, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderIdA)->first();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderIdB}/lines/{$line->id}")
        ->assertNotFound();
});

it('removes line from show payload', function () {
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

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $response = $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');
    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect(count($lines))->toBe(0);
});

it('removing one line preserves the other', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Keep A']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Keep B']);

    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->orderBy('id')->get();

    $lineToDelete = $lines[0];
    $lineToKeep = $lines[1];

    ($this->deleteLine)($user, $orderId, (int) $lineToDelete->id)
        ->assertOk();

    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $lineToKeep->id,
    ]);
});

it('deleting line keeps order status DRAFT', function () {
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

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();
    expect((string) ($order->status ?? ''))->toBe('DRAFT');
});

it('deleting line updates subtotal to remaining sum', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom);
    $itemB = ($this->makeItem)($tenant, $uom);

    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 20,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->orderBy('id')->get();

    ($this->deleteLine)($user, $orderId, (int) $lines[0]->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();

    expect((int) $order->po_subtotal_cents)->toBe(200);
    expect((int) $order->po_grand_total_cents)->toBe(220);
});

it('delete line returns ok for valid line', function () {
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

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$line->id}")
        ->assertOk();
});

it('delete line does not delete the order', function () {
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

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $orderId,
    ]);
});

it('delete line keeps shipping in grand total when other lines remain', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom);
    $itemB = ($this->makeItem)($tenant, $uom);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 30,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->orderBy('id')->get();

    ($this->deleteLine)($user, $orderId, (int) $lines[0]->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();

    expect((int) $order->po_grand_total_cents)->toBe(230);
});

it('delete line returns not found for missing order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->deleteJson('/purchasing/orders/99999/lines/1')
        ->assertNotFound();
});

it('delete line does not affect other orders', function () {
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

    $lineA = DB::table('purchase_order_lines')->where('purchase_order_id', $orderIdA)->first();

    ($this->deleteLine)($user, $orderIdA, (int) $lineA->id)
        ->assertOk();

    $this->assertDatabaseHas('purchase_order_lines', [
        'purchase_order_id' => $orderIdB,
    ]);
});

it('delete line with shipping null sets grand total to remaining subtotal', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => null,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 150,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();

    expect((int) $order->po_grand_total_cents)->toBe(0);
});

it('delete line reduces line count in database', function () {
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

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    $this->assertDatabaseCount('purchase_order_lines', 1);

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $this->assertDatabaseCount('purchase_order_lines', 0);
});

it('delete line does not change order_date', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-20',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();
    expect((string) ($order->order_date ?? ''))->toBe('2026-02-20');
});

it('delete line keeps notes unchanged', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'notes' => 'Keep notes',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    ($this->deleteLine)($user, $orderId, (int) $line->id)
        ->assertOk();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();
    expect((string) ($order->notes ?? ''))->toBe('Keep notes');
});
