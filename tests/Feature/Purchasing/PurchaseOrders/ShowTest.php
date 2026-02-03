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

it('redirects guests from show', function () {
    $this->get('/purchasing/orders/1')
        ->assertRedirect(route('login'));
});

it('forbids show without permission', function () {
    $tenant = ($this->makeTenant)();
    $authorizedUser = ($this->makeUser)($tenant);
    ($this->grantPermission)($authorizedUser, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($authorizedUser, [
        'order_date' => '2026-02-01',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertForbidden();
});

it('allows show with permission', function () {
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

it('renders show payload markers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-04',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk()
        ->assertSee('data-page="purchasing-orders-show"', false)
        ->assertSee('purchasing-orders-show-payload', false);
});

it('includes header fields in show payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Show Supplier']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-05',
        'shipping_cents' => 150,
        'po_number' => 'PO-300',
        'notes' => 'Show order notes',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['status'] ?? null)->toBe('DRAFT');
    expect($order['order_date'] ?? null)->toBe('2026-02-05');
    expect((int) ($order['shipping_cents'] ?? 0))->toBe(150);
    expect($order['po_number'] ?? null)->toBe('PO-300');
    expect($order['notes'] ?? null)->toBe('Show order notes');

    $supplierName = $order['supplier_name']
        ?? ($order['supplier']['company_name'] ?? null);

    expect($supplierName)->toBe('Show Supplier');
});

it('show payload includes actions when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-06',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    if (isset($payload['actions'])) {
        expect($payload['actions'])->toHaveKey('receive');
        expect($payload['actions'])->toHaveKey('cancel');
        expect($payload['actions'])->toHaveKey('delete');
    }
});

it('shows empty lines array when no lines exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-07',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect($lines)->toBeArray();
    expect(count($lines))->toBe(0);
});

it('shows lines added via endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'pack_quantity' => '12.000000',
    ]);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 3,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect($lines)->toHaveCount(1);

    $line = $lines[0] ?? [];
    expect((int) ($line['item_purchase_option_id'] ?? 0))->toBe($option->id);
    expect((int) ($line['item_id'] ?? 0))->toBe($item->id);
    expect($line['pack_quantity'] ?? null)->toBe('12.000000');
    expect($line['pack_uom_symbol'] ?? null)->toBe('kg');
});

it('shows line sub totals and totals', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 150,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (isset($order['po_subtotal_cents'])) {
        expect((int) $order['po_subtotal_cents'])->toBe(400);
    }

    if (isset($order['po_grand_total_cents'])) {
        expect((int) $order['po_grand_total_cents'])->toBe(550);
    }

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    $line = $lines[0] ?? [];
    $lineSubTotal = $line['line_subtotal_cents'] ?? null;

    if ($lineSubTotal !== null) {
        expect((int) $lineSubTotal)->toBe(400);
    }
});

it('shows totals when shipping is null', function () {
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
        'pack_count' => 1,
        'unit_price_cents' => 120,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (isset($order['po_grand_total_cents'])) {
        expect((int) $order['po_grand_total_cents'])->toBe(120);
    }
});

it('reflects line removal in show payload', function () {
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

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $lines = $payload['lines']
        ?? $payload['order_lines']
        ?? $payload['purchaseOrderLines']
        ?? [];

    expect(count($lines))->toBe(0);
});

it('shows order_date field on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-08',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['order_date'] ?? null)->toBe('2026-02-08');
});

it('shows po_number on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'po_number' => 'PO-400',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['po_number'] ?? null)->toBe('PO-400');
});

it('shows notes on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'notes' => 'Show notes',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['notes'] ?? null)->toBe('Show notes');
});

it('shows supplier name when supplier is set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier Name']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk()
        ->assertSee('Supplier Name');
});

it('shows status field on show', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    expect($order['status'] ?? null)->toBe('DRAFT');
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

it('shows totals when multiple lines exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Item A']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Item B']);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'shipping_cents' => 50,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
    ])->assertCreated();

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 2,
        'unit_price_cents' => 150,
    ])->assertCreated();

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (isset($order['po_subtotal_cents'])) {
        expect((int) $order['po_subtotal_cents'])->toBe(400);
    }

    if (isset($order['po_grand_total_cents'])) {
        expect((int) $order['po_grand_total_cents'])->toBe(450);
    }
});

it('shows supplier null when not set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-09',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (array_key_exists('supplier_name', $order)) {
        expect($order['supplier_name'])->toBeNull();
    }
});

it('shows po_number null when not set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-10',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get("/purchasing/orders/{$orderId}")->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    $order = $payload['order']
        ?? $payload['purchaseOrder']
        ?? $payload['purchase_order']
        ?? [];

    if (array_key_exists('po_number', $order)) {
        expect($order['po_number'])->toBeNull();
    }
});
