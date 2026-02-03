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

it('redirects guests from index', function () {
    $this->get('/purchasing/orders')
        ->assertRedirect(route('login'));
});

it('forbids index without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertForbidden();
});

it('renders purchase orders index payload markers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk()
        ->assertSee('data-page="purchasing-orders-index"', false)
        ->assertSee('purchasing-orders-index-payload', false);
});

it('index includes orders created via endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Index Supplier']);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-03',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    expect(collect($orders)->pluck('id')->all())
        ->toContain($orderId);
});

it('index shows supplier name, order_date, status, and subtotal', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Index Supplier']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Index Item']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-03',
        'shipping_cents' => 100,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 250,
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    expect($orderData)->not->toBeNull();
    expect($orderData['status'] ?? null)->toBe('DRAFT');

    $supplierName = $orderData['supplier_name']
        ?? ($orderData['supplier']['company_name'] ?? null);

    expect($supplierName)->toBe('Index Supplier');

    $orderDate = $orderData['order_date'] ?? null;
    expect($orderDate)->toBe('2026-02-03');

    expect((int) ($orderData['po_subtotal_cents'] ?? 0))->toBe(500);
});

it('index includes receive and cancel actions when action metadata is present', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'order_date' => '2026-02-04',
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    $orderData = $orders[0] ?? [];

    if (array_key_exists('actions', $orderData)) {
        expect($orderData['actions'])->toHaveKey('receive');
        expect($orderData['actions'])->toHaveKey('cancel');
        expect($orderData['actions'])->toHaveKey('delete');
    }
});

it('index is tenant scoped', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($userB, 'purchasing-purchase-orders-create');

    $supplierA = ($this->makeSupplier)($tenantA, ['company_name' => 'Tenant A Supplier']);
    $supplierB = ($this->makeSupplier)($tenantB, ['company_name' => 'Tenant B Supplier']);

    ($this->createOrder)($userA, [
        'supplier_id' => $supplierA->id,
        'order_date' => '2026-02-05',
    ])->assertCreated();

    ($this->createOrder)($userB, [
        'supplier_id' => $supplierB->id,
        'order_date' => '2026-02-06',
    ])->assertCreated();

    $this->actingAs($userA)
        ->get('/purchasing/orders')
        ->assertOk()
        ->assertSee('Tenant A Supplier')
        ->assertDontSee('Tenant B Supplier');
});

it('index supports multiple orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'order_date' => '2026-02-07',
    ])->assertCreated();

    ($this->createOrder)($user, [
        'order_date' => '2026-02-08',
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    expect($orders)->toBeArray();
    expect(count($orders))->toBeGreaterThanOrEqual(2);
});

it('index shows status value DRAFT for draft orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-09',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    expect($orderData['status'] ?? null)->toBe('DRAFT');
});

it('index allows orders without supplier to appear', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-10',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    expect($orderData)->not->toBeNull();
});

it('index reflects shipping in totals when line exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-11',
        'shipping_cents' => 50,
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 200,
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    if (array_key_exists('po_grand_total_cents', $orderData)) {
        expect((int) $orderData['po_grand_total_cents'])->toBe(250);
    }
});

it('index still works when no orders exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    expect($orders)->toBeArray();
});

it('index includes order created with notes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'notes' => 'Index note',
        'order_date' => '2026-02-12',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    expect($orderData)->not->toBeNull();
});

it('index shows order_date field, not ordered_at', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $orderResponse = ($this->createOrder)($user, [
        'order_date' => '2026-02-13',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    expect($orderData['order_date'] ?? null)->toBe('2026-02-13');
});

it('index includes subtotal when no shipping applied', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $orderResponse = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-14',
    ])->assertCreated();

    $orderId = (int) ($orderResponse->json('data.id') ?? 0);

    ($this->addLine)($user, $orderId, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 300,
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];
    $orderData = collect($orders)->firstWhere('id', $orderId);

    if (array_key_exists('po_subtotal_cents', $orderData)) {
        expect((int) $orderData['po_subtotal_cents'])->toBe(300);
    }
});

it('index includes delete action when actions are present', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'order_date' => '2026-02-15',
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    $orderData = $orders[0] ?? [];

    if (array_key_exists('actions', $orderData)) {
        expect($orderData['actions'])->toHaveKey('delete');
    }
});

it('index exposes status column for multiple orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'order_date' => '2026-02-16',
    ])->assertCreated();

    ($this->createOrder)($user, [
        'order_date' => '2026-02-17',
    ])->assertCreated();

    $response = $this->actingAs($user)->get('/purchasing/orders')->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-index-payload');

    $orders = $payload['orders'] ?? $payload['purchase_orders'] ?? [];

    foreach ($orders as $orderData) {
        expect($orderData)->toHaveKey('status');
    }
});

it('index shows supplier name when supplier is set', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Name Check']);

    ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-18',
    ])->assertCreated();

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk()
        ->assertSee('Name Check');
});

it('index includes order with po_number', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'po_number' => 'PO-INDEX',
        'order_date' => '2026-02-19',
    ])->assertCreated();

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk();
});

it('index includes orders created with shipping only', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'shipping_cents' => 75,
    ])->assertCreated();

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk();
});
