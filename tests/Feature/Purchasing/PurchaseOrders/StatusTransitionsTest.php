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
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $slugs = $slug === 'purchasing-purchase-orders-receive'
            ? ['purchasing-purchase-orders-create', $slug]
            : [$slug];
        $role = Role::query()->create(['name' => 'role-' . $this->roleCounter]);

        $this->roleCounter++;

        foreach ($slugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $permissionSlug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

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
            'status' => 'CREATED',
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

it('rejects create action without receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->patchStatus)($user, $order, ['status' => 'CREATED'])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe('DRAFT');
});

it('rejects cancel action without receive permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->patchStatus)($user, $order, ['action' => 'cancel'])
        ->assertForbidden();

    expect($order->fresh()->status)->not->toBe('CANCELLED')
        ->and($order->fresh()->cancelled_at)->toBeNull();
});

it('blocks cross-tenant status updates', function () {
    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    $orderB = ($this->makeOrder)($tenantB, $userB, $supplierB);

    ($this->grantPermission)($userA, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($userA, $orderB, ['status' => 'CREATED'])
        ->assertNotFound();
});

it('allows draft to created transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'CREATED'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('CREATED');
});

it('create action response transitions draft to created and returns header state', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'CREATED'])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_CREATED)
        ->assertJsonPath('data.is_cancelled', false)
        ->assertJsonPath('data.is_back_ordered', false);

    expect($order->fresh()->status)->toBe('CREATED');
});

it('create action enters first purchasing workflow stage when purchase orders support workflow stages', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    if (! Schema::hasColumn('purchase_orders', 'workflow_stage_id')) {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('workflow_stage_id')
                ->nullable()
                ->after('status');
        });
    }

    $domain = WorkflowDomain::query()->firstOrCreate([
        'key' => 'purchasing',
    ], [
        'name' => 'Purchasing',
        'sort_order' => 20,
    ]);

    $firstStage = WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'first',
        'name' => 'First Stage',
        'action_verb' => 'FIRST',
        'sort_order' => 10,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'second',
        'name' => 'Second Stage',
        'action_verb' => 'SECOND',
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'CREATED'])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_CREATED);

    $row = DB::table('purchase_orders')->where('id', $order->id)->first();

    expect($row->status)->toBe('CREATED')
        ->and($row->current_workflow_stage_id)->not->toBeNull();
});

it('allows created to back-ordered action', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['action' => 'back_order'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('CREATED')
        ->and($order->fresh()->back_ordered_at)->not->toBeNull();
});

it('rejects legacy back-ordered status transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'BACK-ORDERED'])
        ->assertStatus(422);
});

it('allows created to cancelled action with no receipts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['action' => 'cancel'])
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED')
        ->assertJsonPath('data.is_cancelled', true);

    expect($order->fresh()->status)->toBe('CANCELLED')
        ->and($order->fresh()->cancelled_at)->not->toBeNull();
});

it('cancel action persists cancelled status and audit metadata', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['action' => 'cancel'])
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED')
        ->assertJsonPath('data.is_cancelled', true);

    $order->refresh();

    expect($order->status)->toBe('CANCELLED')
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->cancelled_by_user_id)->toBe($user->id);
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

    ($this->patchStatus)($user, $order, ['action' => 'cancel'])
        ->assertStatus(422);
});

it('rejects cancel action for completed purchase orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'COMPLETED');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['action' => 'cancel'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);

    expect($order->fresh()->status)->toBe('COMPLETED');
});

it('rejects draft to back-ordered transition', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['action' => 'back_order'])
        ->assertStatus(422);
});

it('allows draft to cancelled action with no receipts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['action' => 'cancel'])
        ->assertOk();

    expect($order->fresh()->status)->toBe('CANCELLED');
});

it('rejects direct cancelled status transitions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'DRAFT');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'CANCELLED'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()->status)->toBe('DRAFT');
});

it('rejects lifecycle transitions from cancelled purchase orders', function (string $targetStatus): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    ($this->setOrderStatus)($order, 'CANCELLED');

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => $targetStatus])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()->status)->toBe('CANCELLED');
})->with([
    'created' => 'CREATED',
    'received' => 'RECEIVED',
    'completed' => 'COMPLETED',
]);

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

it('rejects setting legacy hyphenated partially-received status directly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => 'PARTIALLY-RECEIVED'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects setting persisted partially received status directly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->patchStatus)($user, $order, ['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
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

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
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

it('derives received when balances are zero and any short-close exists', function () {
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

    expect($order->fresh()->status)->toBe('RECEIVED');
});

it('short-close history closes balances without becoming a status', function () {
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

    expect($order->fresh()->status)->toBe('RECEIVED');
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

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    ($this->postShortClose)($user, $order, [
        'short_closed_at' => '2026-02-04 11:25:00',
        'purchase_order_line_id' => $line->id,
        'short_closed_quantity' => '5.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('RECEIVED');
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

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    ($this->postReceipt)($user, $order, [
        'received_at' => '2026-02-04 12:00:00',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe('RECEIVED');
});

it('repeated partial receipts stay partially received until the final receipt', function () {
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
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '3.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    ($this->postReceipt)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '5.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED);
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
