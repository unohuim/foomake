<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
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

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        $supplier = Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $this->supplierCounter,
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->createOrder = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson('/purchasing/orders', $payload);
    };

    $this->fetchLatestOrder = function (int $tenantId) {
        return DB::table('purchase_orders')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
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

it('rejects guests on create', function () {
    $this->postJson('/purchasing/orders', [])
        ->assertUnauthorized();
});

it('rejects authed users without permission on create', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->createOrder)($user, [])
        ->assertForbidden();
});

it('creates a draft purchase order with nullable header fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = ($this->createOrder)($user, [])
        ->assertCreated();

    $orderId = $response->json('data.id');
    $order = $orderId
        ? DB::table('purchase_orders')->where('id', $orderId)->first()
        : ($this->fetchLatestOrder)($tenant->id);

    expect($order)->not->toBeNull();
    expect((string) ($order->status ?? ''))->toBe('DRAFT');
    expect($order->supplier_id ?? null)->toBeNull();
    expect($order->order_date ?? null)->toBeNull();
    expect($order->shipping_cents ?? null)->toBeNull();
    expect($order->po_number ?? null)->toBeNull();
    expect($order->notes ?? null)->toBeNull();
});

it('persists supplier_id when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $orderId,
        'supplier_id' => $supplier->id,
    ]);
});

it('persists order_date when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-02',
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((string) ($order->order_date ?? ''))->toBe('2026-02-02');
});

it('persists shipping_cents when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'shipping_cents' => 250,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((int) ($order->shipping_cents ?? 0))->toBe(250);
});

it('allows shipping_cents to be zero', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'shipping_cents' => 0,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((int) ($order->shipping_cents ?? 0))->toBe(0);
});

it('persists po_number when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'po_number' => 'PO-200',
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((string) ($order->po_number ?? ''))->toBe('PO-200');
});

it('persists notes when provided', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'notes' => 'Draft notes',
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((string) ($order->notes ?? ''))->toBe('Draft notes');
});

it('keeps status as DRAFT even if payload includes status', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'status' => 'OPEN',
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((string) ($order->status ?? ''))->toBe('DRAFT');
});

it('ignores tenant_id from payload', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'tenant_id' => $otherTenant->id,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);

    expect((int) ($order->tenant_id ?? 0))->toBe($tenant->id);
});

it('rejects invalid supplier_id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'supplier_id' => 999999,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_id']);
});

it('rejects supplier_id from another tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'supplier_id' => $otherSupplier->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_id']);
});

it('rejects invalid order_date format', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'order_date' => 'not-a-date',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['order_date']);
});

it('rejects invalid shipping_cents format', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'shipping_cents' => '12.50',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_cents']);
});

it('accepts nullable order_date', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'order_date' => null,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);
    expect($order->order_date ?? null)->toBeNull();
});

it('accepts nullable shipping_cents', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'shipping_cents' => null,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);
    expect($order->shipping_cents ?? null)->toBeNull();
});

it('accepts nullable po_number', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'po_number' => null,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);
    expect($order->po_number ?? null)->toBeNull();
});

it('accepts nullable notes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createOrder)($user, [
        'notes' => null,
    ])->assertCreated();

    $order = ($this->fetchLatestOrder)($tenant->id);
    expect($order->notes ?? null)->toBeNull();
});

it('end-to-end: create draft then show and index reflect it', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'E2E Supplier']);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = ($this->createOrder)($user, [
        'supplier_id' => $supplier->id,
        'order_date' => '2026-02-03',
        'po_number' => 'PO-E2E',
        'notes' => 'E2E notes',
    ])->assertCreated();

    $orderId = (int) ($response->json('data.id') ?? 0);

    $show = $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();

    $showPayload = ($this->extractPayload)($show, 'purchasing-orders-show-payload');
    $order = $showPayload['order']
        ?? $showPayload['purchaseOrder']
        ?? $showPayload['purchase_order']
        ?? [];

    expect($order['po_number'] ?? null)->toBe('PO-E2E');
    expect($order['order_date'] ?? null)->toBe('2026-02-03');

    $index = $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk();

    $indexPayload = ($this->extractPayload)($index, 'purchasing-orders-index-payload');
    $orders = $indexPayload['orders'] ?? $indexPayload['purchase_orders'] ?? [];

    expect(collect($orders)->pluck('id')->all())
        ->toContain($orderId);
});
