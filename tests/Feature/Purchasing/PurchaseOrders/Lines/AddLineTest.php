<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            'currency_code' => 'USD',
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
            'currency_code' => $attributes['currency_code'] ?? 'USD',
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

    $this->makePrice = function (Tenant $tenant, ItemPurchaseOption $option, array $attributes = []): ItemPurchaseOptionPrice {
        return ItemPurchaseOptionPrice::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_purchase_option_id' => $option->id,
            'price_cents' => 1200,
            'price_currency_code' => 'USD',
            'converted_price_cents' => 1200,
            'fx_rate' => '1.000000',
            'fx_rate_as_of' => '2026-02-01',
            'effective_at' => '2026-02-01 00:00:00',
            'ended_at' => null,
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

    $this->assertStableErrors = function ($response): void {
        $response->assertJsonStructure([
            'errors',
        ]);

        expect($response->json('errors'))->toBeArray();
    };
});

it('rejects guests on add line', function () {
    $this->postJson('/purchasing/orders/1/lines', [])
        ->assertUnauthorized();
});

it('rejects authed users without permission on add line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->postJson('/purchasing/orders/1/lines', [])
        ->assertForbidden();
});

it('allows adding a line without item_id', function () {
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

    expect((int) ($line->item_id ?? 0))->toBe($item->id);
});

it('validates item_purchase_option_id required when adding line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_purchase_option_id']);

    ($this->assertStableErrors)($response);
});

it('validates pack_count required when adding line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'unit_price_cents' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_count']);

    ($this->assertStableErrors)($response);
});

it('validates pack_count must be greater than zero', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'pack_count' => 0,
        'unit_price_cents' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_count']);

    ($this->assertStableErrors)($response);
});

it('validates pack_count numeric', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'pack_count' => 'not-a-number',
        'unit_price_cents' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_count']);

    ($this->assertStableErrors)($response);
});

it('validates unit_price_cents required when adding line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'pack_count' => 1,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['unit_price_cents']);

    ($this->assertStableErrors)($response);
});

it('validates unit_price_cents non-negative when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'pack_count' => 1,
        'unit_price_cents' => -10,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['unit_price_cents']);

    ($this->assertStableErrors)($response);
});

it('validates unit_price_cents must be integer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'pack_count' => 1,
        'unit_price_cents' => 10.5,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['unit_price_cents']);

    ($this->assertStableErrors)($response);
});

it('returns validation error when supplier not selected', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => 1,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_id']);
});

it('rejects item mismatch when item_id is provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $other = ($this->makeItem)($tenant, $uom, ['name' => 'Other']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_id' => $other->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);
});

it('rejects supplier mismatch when order supplier is set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplierA = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier A']);
    $supplierB = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier B']);
    $item = ($this->makeItem)($tenant, $uom);
    $optionB = ($this->makeOption)($tenant, $supplierB, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplierA->id,
        'order_date' => '2026-02-05',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ]);

    expect([422, 404])->toContain($response->status());
});

it('persists line snapshot money fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    Carbon::setTestNow('2026-02-01 10:00:00');

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
        'pack_count' => 3,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')
        ->where('purchase_order_id', $orderId)
        ->first();

    expect($line)->not->toBeNull();
    expect((int) $line->unit_price_cents)->toBe(100);
    expect((int) $line->unit_price_amount)->toBe(100);
    expect((string) $line->unit_price_currency_code)->toBe('USD');
    expect((int) $line->converted_unit_price_amount)->toBe(100);

    Carbon::setTestNow();
});

it('updates totals on add line', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 50,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 3,
        'unit_price_cents' => 100,
    ])->assertCreated();

    $order = DB::table('purchase_orders')->where('id', $orderId)->first();

    expect((int) $order->po_subtotal_cents)->toBe(300);
    expect((int) $order->po_grand_total_cents)->toBe(350);
});

it('stores line_subtotal as quantity times unit price', function () {
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
        'pack_count' => 4,
        'unit_price_cents' => 125,
    ])->assertCreated();

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    expect((int) $line->line_subtotal_cents)->toBe(500);
});

it('stores item and option ids', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'supplier_sku' => 'SNAP-1',
        'pack_quantity' => '10.000000',
    ]);

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

    expect((int) ($line->item_purchase_option_id ?? 0))->toBe($option->id);
    expect((int) ($line->item_id ?? 0))->toBe($item->id);
});

it('keeps line snapshots immutable after price changes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->makePrice)($tenant, $option, [
        'price_cents' => 800,
        'converted_price_cents' => 800,
    ]);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 800,
    ])->assertCreated();

    ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->update([
        'price_cents' => 1200,
        'converted_price_cents' => 1200,
    ]);

    $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

    expect((int) $line->unit_price_cents)->toBe(800);
});

it('allows adding a line for non-purchasable item', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_purchasable' => false]);
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

it('shows line snapshot in show payload after add', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['pack_quantity' => '12.000000']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $line = $lines[0] ?? [];
    expect((int) ($line['item_purchase_option_id'] ?? 0))->toBe($option->id);
    expect((int) ($line['item_id'] ?? 0))->toBe($item->id);
    expect($line['pack_quantity'] ?? null)->toBe('12.000000');
    expect($line['pack_uom_symbol'] ?? null)->toBe('kg');
});

it('shows line quantity, price, and totals in payload', function () {
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
        'pack_count' => 4,
        'unit_price_cents' => 125,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $line = $lines[0] ?? [];

    expect((int) ($line['pack_count'] ?? 0))->toBe(4);
    expect((int) ($line['unit_price_cents'] ?? 0))->toBe(125);
    expect((int) ($line['line_subtotal_cents'] ?? 0))->toBe(500);
});
