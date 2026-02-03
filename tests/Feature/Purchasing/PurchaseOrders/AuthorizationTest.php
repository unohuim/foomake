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

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => null,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

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

    $this->seedOrderWithLine = function (Tenant $tenant): array {
        $user = ($this->makeUser)($tenant);
        ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

        $uom = ($this->makeUom)($tenant);
        $supplier = ($this->makeSupplier)($tenant);
        $item = ($this->makeItem)($tenant, $uom);
        $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

        $orderResponse = ($this->createOrder)($user, [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-02-01',
        ])->assertCreated();

        $orderId = (int) ($orderResponse->json('data.id') ?? 0);

        ($this->addLine)($user, $orderId, [
            'item_purchase_option_id' => $option->id,
            'pack_count' => 1,
            'unit_price_cents' => 100,
        ])->assertCreated();

        $line = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->first();

        return [$user, $orderId, (int) ($line->id ?? 0), $supplier, $item, $option];
    };
});

it('redirects guests from index', function () {
    $this->get('/purchasing/orders')
        ->assertRedirect(route('login'));
});

it('redirects guests from show', function () {
    $this->get('/purchasing/orders/1')
        ->assertRedirect(route('login'));
});

it('returns unauthorized for guests requesting json on index', function () {
    $this->getJson('/purchasing/orders')
        ->assertUnauthorized();
});

it('returns unauthorized for guests requesting json on show', function () {
    $this->getJson('/purchasing/orders/1')
        ->assertUnauthorized();
});

it('returns unauthorized for guests on create', function () {
    $this->postJson('/purchasing/orders', [])
        ->assertUnauthorized();
});

it('returns unauthorized for guests on delete', function () {
    $this->deleteJson('/purchasing/orders/1')
        ->assertUnauthorized();
});

it('returns unauthorized for guests on add line', function () {
    $this->postJson('/purchasing/orders/1/lines', [])
        ->assertUnauthorized();
});

it('returns unauthorized for guests on delete line', function () {
    $this->deleteJson('/purchasing/orders/1/lines/1')
        ->assertUnauthorized();
});

it('forbids index without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertForbidden();
});

it('forbids index without permission when requesting json', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson('/purchasing/orders')
        ->assertForbidden();
});

it('forbids show without permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId] = ($this->seedOrderWithLine)($tenant);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get("/purchasing/orders/{$orderId}")
        ->assertForbidden();
});

it('forbids show without permission when requesting json', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId] = ($this->seedOrderWithLine)($tenant);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson("/purchasing/orders/{$orderId}")
        ->assertForbidden();
});

it('forbids create without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->postJson('/purchasing/orders', [])
        ->assertForbidden();
});

it('forbids delete without permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId] = ($this->seedOrderWithLine)($tenant);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertForbidden();
});

it('forbids add line without permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId, $lineId, $supplier, $item, $option] = ($this->seedOrderWithLine)($tenant);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$orderId}/lines", [
            'item_purchase_option_id' => $option->id,
            'pack_count' => 1,
            'unit_price_cents' => 100,
        ])
        ->assertForbidden();
});

it('forbids delete line without permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId, $lineId] = ($this->seedOrderWithLine)($tenant);

    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$lineId}")
        ->assertForbidden();
});

it('allows index with permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->get('/purchasing/orders')
        ->assertOk();
});

it('allows index with permission when requesting json', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->getJson('/purchasing/orders')
        ->assertOk();
});

it('allows show with permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId] = ($this->seedOrderWithLine)($tenant);

    $this->actingAs($authorizedUser)
        ->get("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('allows show with permission when requesting json', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId] = ($this->seedOrderWithLine)($tenant);

    $this->actingAs($authorizedUser)
        ->getJson("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('allows create with permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->postJson('/purchasing/orders', [])
        ->assertCreated();
});

it('allows delete with permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId] = ($this->seedOrderWithLine)($tenant);

    $this->actingAs($authorizedUser)
        ->deleteJson("/purchasing/orders/{$orderId}")
        ->assertOk();
});

it('allows add line with permission', function () {
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
});

it('allows delete line with permission', function () {
    $tenant = ($this->makeTenant)();
    [$authorizedUser, $orderId, $lineId] = ($this->seedOrderWithLine)($tenant);

    $this->actingAs($authorizedUser)
        ->deleteJson("/purchasing/orders/{$orderId}/lines/{$lineId}")
        ->assertOk();
});
