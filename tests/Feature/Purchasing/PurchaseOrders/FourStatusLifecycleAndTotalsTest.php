<?php

declare(strict_types=1);

use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemUomConversion;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\UomConversion;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
            'email' => 'po-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'po-role-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $tenantId = $attributes['tenant_id'] ?? $tenant->id;
        $symbol = $attributes['symbol'] ?? 'u' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenantId)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->firstOrCreate([
            'tenant_id' => $attributes['category_tenant_id'] ?? $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenantId,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $this->uomCounter,
            'symbol' => $symbol,
            'display_precision' => $attributes['display_precision'] ?? 6,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $baseUom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'PO Item ' . $this->itemCounter,
            'base_uom_id' => $baseUom->id,
            'is_stockable' => true,
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
            'company_name' => 'PO Supplier ' . $this->supplierCounter,
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeOption = function (
        Tenant $tenant,
        Supplier $supplier,
        Item $item,
        Uom $packUom,
        array $attributes = []
    ): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => 'PO-SKU-' . $item->id,
            'pack_quantity' => '1.000000',
            'pack_uom_id' => $packUom->id,
            'is_active' => true,
        ], $attributes));
    };

    $this->makeOrder = function (
        Tenant $tenant,
        User $user,
        Supplier $supplier,
        array $attributes = []
    ): PurchaseOrder {
        return PurchaseOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => '2026-05-27',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
            'po_number' => 'PO-STRICT-' . $tenant->id,
            'notes' => 'Notes',
            'status' => PurchaseOrder::STATUS_DRAFT,
        ], $attributes));
    };

    $this->makeLine = function (
        Tenant $tenant,
        PurchaseOrder $order,
        Item $item,
        ItemPurchaseOption $option,
        array $attributes = []
    ): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $order->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => 1,
            'unit_price_cents' => 1000,
            'line_subtotal_cents' => 1000,
            'line_tax_rate_bps' => 0,
            'unit_price_amount' => 1000,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 1000,
            'fx_rate' => '1.00000000',
            'fx_rate_as_of' => '2026-05-27',
        ], $attributes));
    };

    $this->createDraft = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson('/purchasing/orders', $payload);
    };

    $this->updateHeader = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->patchJson("/purchasing/orders/{$order->id}", $payload);
    };

    $this->updateStatus = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->patchJson("/purchasing/orders/{$order->id}/status", $payload);
    };

    $this->addLine = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/lines", $payload);
    };

    $this->receive = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/receipts", $payload);
    };

    $this->shortClose = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/short-closures", $payload);
    };

    $this->seedWorkflow = function (Tenant $tenant): void {
        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
    };

    $this->purchasingStage = function (Tenant $tenant, string $key): WorkflowStage {
        $domain = WorkflowDomain::query()->where('key', 'purchasing')->firstOrFail();

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', $domain->id)
            ->where('key', $key)
            ->firstOrFail();
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '', true);

        return is_array($payload) ? $payload : [];
    };
});

it('1. purchase order schema can persist cancellation audit metadata and back-order markers', function (): void {
    expect(Schema::hasColumn('purchase_orders', 'cancelled_at'))->toBeTrue()
        ->and(Schema::hasColumn('purchase_orders', 'cancelled_by_user_id'))->toBeTrue()
        ->and(Schema::hasColumn('purchase_orders', 'back_ordered_at'))->toBeTrue()
        ->and(Schema::hasColumn('purchase_orders', 'back_ordered_by_user_id'))->toBeTrue();
});

it('2. purchase order lines schema stores line tax rate as basis points', function (): void {
    expect(Schema::hasColumn('purchase_order_lines', 'line_tax_rate_bps'))->toBeTrue();
});

it('3. new purchase orders start as draft', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createDraft)($user)->assertCreated();

    expect(PurchaseOrder::query()->firstOrFail()->status)->toBe(PurchaseOrder::STATUS_DRAFT);
});

it('4. draft purchase orders can be created', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->updateStatus)($user, $order, ['status' => PurchaseOrder::STATUS_CREATED])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_CREATED);

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_CREATED);
});

it('5. created purchase orders can be marked received after all balances are closed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 2]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED);
});

it('6. received purchase orders can be completed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_RECEIVED]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->updateStatus)($user, $order, ['status' => PurchaseOrder::STATUS_COMPLETED])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_COMPLETED);

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_COMPLETED);
});

it('7. invalid persisted status transitions are rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->updateStatus)($user, $order, ['status' => PurchaseOrder::STATUS_COMPLETED])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_DRAFT);
});

it('7b. purchase order status helper includes persisted partially received status', function (): void {
    expect(PurchaseOrder::statuses())->toContain(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and(PurchaseOrder::statuses())->toContain(PurchaseOrder::STATUS_CREATED)
        ->and(PurchaseOrder::statuses())->not->toContain('SENT')
        ->and(PurchaseOrder::statuses())->not->toContain('PARTIAL')
        ->and(PurchaseOrder::statuses())->not->toContain('RECEIVING');
});

it('8. legacy action values are rejected as persisted statuses', function (string $legacyStatus): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->updateStatus)($user, $order, ['status' => $legacyStatus])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
})->with([
    'OPEN',
    'PARTIALLY-RECEIVED',
    'BACK-ORDERED',
    'SHORT-CLOSED',
]);

it('9. cancel records terminal cancelled status and audit metadata', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->updateStatus)($user, $order, ['action' => 'cancel'])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_CANCELLED)
        ->assertJsonPath('data.is_cancelled', true);

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(PurchaseOrder::STATUS_CANCELLED)
        ->and($fresh->cancelled_at)->not->toBeNull()
        ->and($fresh->cancelled_by_user_id)->toBe($user->id);
});

it('10. back order records a receiving-stage event without changing persisted status', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->updateStatus)($user, $order, ['action' => 'back_order'])
        ->assertOk()
        ->assertJsonPath('data.status', PurchaseOrder::STATUS_CREATED)
        ->assertJsonPath('data.is_back_ordered', true);

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(PurchaseOrder::STATUS_CREATED)
        ->and($fresh->back_ordered_at)->not->toBeNull()
        ->and($fresh->back_ordered_by_user_id)->toBe($user->id);
});

it('11. shipping dollars are normalized to cents when creating a draft order', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createDraft)($user, ['shipping_amount' => '12.34'])->assertCreated();

    expect((int) PurchaseOrder::query()->firstOrFail()->shipping_cents)->toBe(1234);
});

it('12. old cents-style shipping UI input is rejected at the request boundary', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createDraft)($user, ['shipping_cents' => 1234])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_amount']);
});

it('13. invalid shipping dollars format returns validation errors', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->createDraft)($user, ['shipping_amount' => '12.345'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_amount']);
});

it('14. line tax percentage is stored as basis points', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 1000,
        'tax_percent' => '13.5',
    ])->assertCreated();

    expect((int) PurchaseOrderLine::query()->firstOrFail()->line_tax_rate_bps)->toBe(1350);
});

it('14b. line tax percentage allows only one decimal place', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 2,
        'unit_price_cents' => 1000,
        'tax_percent' => '13.55',
    ])->assertJsonValidationErrors(['tax_percent']);
});

it('15. mixed taxable and non-taxable lines calculate tax from each line', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Taxed']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Untaxed']);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['shipping_cents' => 500]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 10000,
        'tax_percent' => '13.0',
    ])->assertCreated();
    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 5000,
        'tax_percent' => '0',
    ])->assertCreated();

    $fresh = $order->fresh();

    expect((int) $fresh->po_subtotal_cents)->toBe(15000)
        ->and((int) $fresh->tax_cents)->toBe(1300)
        ->and((int) $fresh->po_grand_total_cents)->toBe(16800);
});

it('16. deleting a taxable line recalculates subtotal tax and grand total', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Taxed']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Remaining']);
    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $optionA->id,
        'pack_count' => 1,
        'unit_price_cents' => 10000,
        'tax_percent' => '13.0',
    ])->assertCreated();
    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $optionB->id,
        'pack_count' => 1,
        'unit_price_cents' => 5000,
        'tax_percent' => '0',
    ])->assertCreated();

    $taxedLine = PurchaseOrderLine::query()->where('item_id', $itemA->id)->firstOrFail();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$order->id}/lines/{$taxedLine->id}")
        ->assertOk();

    $fresh = $order->fresh();

    expect((int) $fresh->po_subtotal_cents)->toBe(5000)
        ->and((int) $fresh->tax_cents)->toBe(0)
        ->and((int) $fresh->po_grand_total_cents)->toBe(5000);
});

it('17. receiving different supplier package uom uses item-specific conversion first', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['symbol' => 'g']);
    $packUom = ($this->makeUom)($tenant, ['symbol' => 'ea', 'category_name' => 'Count']);
    $item = ($this->makeItem)($tenant, $baseUom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $packUom, ['pack_quantity' => '40.000000']);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 2]);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $packUom->id,
        'to_uom_id' => $baseUom->id,
        'conversion_factor' => '113.000000',
    ]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    expect((string) DB::table('stock_moves')->value('quantity'))->toBe('9040.000000');
});

it('18. receiving uses tenant conversion before global conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['symbol' => 'g', 'category_name' => 'Mass']);
    $packUom = ($this->makeUom)($tenant, ['symbol' => 'kg', 'category_name' => 'Mass']);
    $item = ($this->makeItem)($tenant, $baseUom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $packUom, ['pack_quantity' => '2.000000']);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 1]);

    UomConversion::query()->create([
        'tenant_id' => null,
        'from_uom_id' => $packUom->id,
        'to_uom_id' => $baseUom->id,
        'multiplier' => '999.00000000',
    ]);
    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $packUom->id,
        'to_uom_id' => $baseUom->id,
        'multiplier' => '1000.00000000',
    ]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();

    expect((string) DB::table('stock_moves')->value('quantity'))->toBe('2000.000000');
});

it('19. missing supplier package conversion blocks receiving safely', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['symbol' => 'g']);
    $packUom = ($this->makeUom)($tenant, ['symbol' => 'case', 'category_name' => 'Count']);
    $item = ($this->makeItem)($tenant, $baseUom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $packUom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['conversion']);

    expect(DB::table('stock_moves')->count())->toBe(0);
});

it('20. supplier package creation with different uom requires an existing conversion and returns modal metadata', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['symbol' => 'g']);
    $packUom = ($this->makeUom)($tenant, ['symbol' => 'case', 'category_name' => 'Count']);
    $item = ($this->makeItem)($tenant, $baseUom);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $this->actingAs($user)
        ->postJson("/materials/{$item->id}/supplier-packages", [
            'supplier_id' => $supplier->id,
            'pack_quantity' => '1.000000',
            'pack_uom_id' => $packUom->id,
            'supplier_sku' => 'CASE',
            'price_amount' => '10.00',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_uom_id'])
        ->assertJsonPath('meta.requires_conversion', true)
        ->assertJsonPath('meta.conversion_create_url', route('manufacturing.uom-conversions.items.store'));
});

it('21. purchase order details source conditionally opens when required header values are missing', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $detailsSource = substr($source, strpos($source, '<x-detail-section-card title="Details"'));
    $detailsSource = substr($detailsSource, 0, strpos($detailsSource, '<x-detail-section-card title="Items"'));

    expect($source)->toContain('$detailsDefaultOpen')
        ->and($source)->toContain('data-purchase-order-detail-grid')
        ->and($source)->toContain('mx-auto w-full min-w-0 max-w-7xl')
        ->and($source)->toContain('grid w-full min-w-0 gap-4')
        ->and($source)->toContain('max-w-7xl')
        ->and($source)->toContain('lg:grid-cols-4')
        ->and($source)->toContain('data-purchase-order-main-column')
        ->and($source)->toContain('min-w-0 space-y-4 sm:space-y-6 lg:col-span-3')
        ->and($source)->toContain('lg:col-span-3')
        ->and($source)->toContain('data-purchase-order-side-column')
        ->and($source)->toContain('min-w-0 space-y-4 sm:space-y-6 lg:col-span-1')
        ->and($source)->toContain('data-purchase-order-main-lower-column')
        ->and($detailsSource)->toContain(':default-open="$detailsDefaultOpen"')
        ->and($detailsSource)->not->toContain('Delete draft')
        ->and($detailsSource)->not->toContain('Save details')
        ->and($detailsSource)->not->toContain('Shipping');
});

it('21a. purchase order responsive layout stacks totals under items before notes and tasks', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));

    $mainColumnPosition = strpos($source, 'data-purchase-order-main-column');
    $itemsPosition = strpos($source, '<x-detail-section-card title="Items"');
    $sideColumnPosition = strpos($source, 'data-purchase-order-side-column');
    $totalsPosition = strpos($source, '<x-detail-section-card title="Totals"');
    $lowerColumnPosition = strpos($source, 'data-purchase-order-main-lower-column');
    $notesPosition = strpos($source, '<x-notes-feed');
    $tasksPosition = strpos($source, 'title="Tasks"');
    $receiptHistoryPosition = strpos($source, 'title="Receipt History"');
    $shortCloseHistoryPosition = strpos($source, 'title="Short-Close History"');

    expect($mainColumnPosition)->not->toBeFalse()
        ->and($itemsPosition)->not->toBeFalse()
        ->and($notesPosition)->not->toBeFalse()
        ->and($tasksPosition)->not->toBeFalse()
        ->and($receiptHistoryPosition)->not->toBeFalse()
        ->and($shortCloseHistoryPosition)->not->toBeFalse()
        ->and($sideColumnPosition)->not->toBeFalse()
        ->and($totalsPosition)->not->toBeFalse()
        ->and($lowerColumnPosition)->not->toBeFalse()
        ->and($mainColumnPosition)->toBeLessThan($itemsPosition)
        ->and($itemsPosition)->toBeLessThan($sideColumnPosition)
        ->and($sideColumnPosition)->toBeLessThan($totalsPosition)
        ->and($totalsPosition)->toBeLessThan($lowerColumnPosition)
        ->and($lowerColumnPosition)->toBeLessThan($notesPosition)
        ->and($notesPosition)->toBeLessThan($tasksPosition)
        ->and($tasksPosition)->toBeLessThan($receiptHistoryPosition)
        ->and($receiptHistoryPosition)->toBeLessThan($shortCloseHistoryPosition)
        ->and($source)->not->toContain("</div>\n            </div>\n\n            <x-detail-section-card title=\"Receipt History\"");
});

it('22. purchase order details source wires individual autosave fields with success icons', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $detailsSource = substr($source, strpos($source, '<x-detail-section-card title="Details"'));
    $detailsSource = substr($detailsSource, 0, strpos($detailsSource, '<x-detail-section-card title="Items"'));

    expect($detailsSource)->toContain("autosaveField('supplier_id')")
        ->and($detailsSource)->toContain("autosaveField('order_date')")
        ->and($detailsSource)->toContain("autosaveField('po_number')")
        ->and($detailsSource)->not->toContain("autosaveField('notes')")
        ->and($detailsSource)->not->toContain('x-model="form.notes"')
        ->and($detailsSource)->toContain('data-autosave-success-icon')
        ->and($detailsSource)->toContain('text-lime-400');
});

it('23. purchase order totals source renders editable shipping autosave in totals', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $totalsSource = substr($source, strpos($source, '<x-detail-section-card title="Totals"'));
    $totalsSource = substr($totalsSource, 0, strpos($totalsSource, 'data-purchase-order-main-lower-column'));

    expect($totalsSource)->toContain('Shipping')
        ->and($totalsSource)->toContain('inputmode="decimal"')
        ->and($totalsSource)->not->toContain(':emit-on-input="false"')
        ->and($totalsSource)->toContain("x-bind:class=\"savedFields.shipping_amount ? 'opacity-100' : 'opacity-0'\"")
        ->and($totalsSource)->toContain("x-on:smart-number-input:changed=\"autosaveField('shipping_amount', \$event.detail)\"")
        ->and($totalsSource)->toContain("autosaveField('shipping_amount')");
});

it('24. purchase order items source uses supplier package combobox add pattern', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $itemsSource = substr($source, strpos($source, '<x-detail-section-card title="Items"'));
    $itemsSource = substr($itemsSource, 0, strpos($itemsSource, 'data-purchase-order-side-column'));

    expect($itemsSource)->toContain('<x-combobox')
        ->and($itemsSource)->toContain('supplierPackageComboboxOptions')
        ->and($itemsSource)->toContain('Add supplier package')
        ->and($itemsSource)->not->toContain('x-on:click="openReceive()"')
        ->and($itemsSource)->not->toContain('openReceiveLine(line)')
        ->and($itemsSource)->not->toContain('canReceiveLine(line)')
        ->and($itemsSource)->not->toContain('x-model="lineForm.item_id"')
        ->and($itemsSource)->not->toContain('handleItemChange()')
        ->and($itemsSource)->not->toContain('>Pack option');
});

it('25. supplier changes autosave and clears existing purchase order lines', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $newSupplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->makeLine)($tenant, $order, $item, $option);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->updateHeader)($user, $order, ['supplier_id' => $newSupplier->id])
        ->assertOk()
        ->assertJsonPath('data.purchase_order.supplier_id', $newSupplier->id)
        ->assertJsonPath('data.lines', []);

    expect(PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->count())->toBe(0);
});

it('26. shipping autosave stores dollars as cents and returns refreshed totals', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->updateHeader)($user, $order, ['shipping_amount' => '12.34'])
        ->assertOk()
        ->assertJsonPath('data.purchase_order.shipping_cents', 1234)
        ->assertJsonPath('data.purchase_order.shipping_amount', '12.34')
        ->assertJsonPath('data.purchase_order.po_grand_total_cents', 1234);

    expect((int) $order->fresh()->shipping_cents)->toBe(1234);
});

it('27. material supplier package draft purchase order redirects to detail with supplier selected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    DB::table('item_purchase_option_prices')->insert([
        'tenant_id' => $tenant->id,
        'item_purchase_option_id' => $option->id,
        'price_cents' => 425,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 425,
        'fx_rate' => '1.000000',
        'fx_rate_as_of' => '2026-05-28',
        'effective_at' => now(),
        'ended_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = $this->actingAs($user)
        ->postJson("/materials/{$item->id}/purchase-orders", [
            'supplier_id' => $supplier->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => 2,
        ])->assertCreated()
        ->assertJsonPath('data.supplier_id', $supplier->id);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get($response->json('data.show_url'))->assertOk(),
        'purchasing-orders-show-payload'
    );

    expect($payload['purchaseOrder']['supplier_id'] ?? null)->toBe($supplier->id)
        ->and($payload['lines'][0]['item_purchase_option_id'] ?? null)->toBe($option->id);
});

it('28. purchase order page module has no global state and collapses workflow dropdown before submit', function (): void {
    $workflowButton = File::get(resource_path('views/components/workflow-action-button.blade.php'));
    $pageModule = File::get(resource_path('js/pages/purchasing-orders-show.js'));

    expect($workflowButton)->toContain('$dispatch(\'close\'); submit(action)')
        ->and($pageModule)->not->toContain('window.purchase')
        ->and($pageModule)->not->toContain('window.purchasingOrdersShow');
});

it('29. purchase order index displays workflow draft instead of legacy created', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'last_completed_workflow_stage_id' => null,
    ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get('/purchasing/orders')->assertOk(),
        'purchasing-orders-index-payload'
    );

    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['status'] ?? null)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($orderPayload['workflow_status'] ?? null)->not->toBe(PurchaseOrder::STATUS_CREATED);
});

it('30. purchase order index displays persisted created status during receiving stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $creatingStage = ($this->purchasingStage)($tenant, 'creating');
    $creatingStage->forceFill(['status_complete_label' => 'CREATED'])->save();

    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'last_completed_workflow_stage_id' => $creatingStage->id,
    ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get('/purchasing/orders')->assertOk(),
        'purchasing-orders-index-payload'
    );

    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['status'] ?? null)->toBe(PurchaseOrder::STATUS_CREATED);
});

it('31. purchase order detail payload uses workflow derived status instead of legacy status', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'last_completed_workflow_stage_id' => null,
    ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$order->id}")->assertOk(),
        'purchasing-orders-show-payload'
    );

    expect($payload['purchaseOrder']['status'] ?? null)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($payload['workflow']['status'] ?? null)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($payload['purchaseOrder']['workflow_status'] ?? null)->not->toBe(PurchaseOrder::STATUS_CREATED);
});

it('32. purchase order detail payload includes selected supplier and supplier options after material package create', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Selected Package Supplier']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    DB::table('item_purchase_option_prices')->insert([
        'tenant_id' => $tenant->id,
        'item_purchase_option_id' => $option->id,
        'price_cents' => 425,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 425,
        'fx_rate' => '1.000000',
        'fx_rate_as_of' => '2026-05-28',
        'effective_at' => now(),
        'ended_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = $this->actingAs($user)
        ->postJson("/materials/{$item->id}/purchase-orders", [
            'supplier_id' => (string) $supplier->id,
            'item_purchase_option_id' => (string) $option->id,
            'pack_count' => 2,
        ])->assertCreated();

    $order = PurchaseOrder::query()->findOrFail((int) $response->json('data.id'));
    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get($response->json('data.show_url'))->assertOk(),
        'purchasing-orders-show-payload'
    );

    expect((int) $order->supplier_id)->toBe($supplier->id)
        ->and((int) ($payload['purchaseOrder']['supplier_id'] ?? 0))->toBe($supplier->id)
        ->and(collect($payload['suppliers'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all())
        ->toContain($supplier->id);
});

it('32c. purchase order editability remains true through creating and receiving workflow stages', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $creatingStage = ($this->purchasingStage)($tenant, 'creating');
    $receivingStage = ($this->purchasingStage)($tenant, 'receiving');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'current_workflow_stage_id' => $creatingStage->id,
        'last_completed_workflow_stage_id' => null,
    ]);

    $draftPayload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$order->id}")->assertOk(),
        'purchasing-orders-show-payload'
    );

    $order->forceFill([
        'status' => PurchaseOrder::STATUS_CREATED,
        'current_workflow_stage_id' => $receivingStage->id,
        'last_completed_workflow_stage_id' => $creatingStage->id,
    ])->save();

    $receivingPayload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$order->id}")->assertOk(),
        'purchasing-orders-show-payload'
    );

    expect($draftPayload['purchaseOrder']['is_editable'] ?? null)->toBeTrue()
        ->and($receivingPayload['purchaseOrder']['is_editable'] ?? null)->toBeTrue();
});

it('32d. purchase order editability locks after inventory impacting stage completion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $receivingStage = ($this->purchasingStage)($tenant, 'receiving');
    $completingStage = ($this->purchasingStage)($tenant, 'completing');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_RECEIVED,
        'current_workflow_stage_id' => $completingStage->id,
        'last_completed_workflow_stage_id' => $receivingStage->id,
    ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$order->id}")->assertOk(),
        'purchasing-orders-show-payload'
    );

    expect($payload['purchaseOrder']['is_editable'] ?? null)->toBeFalse();
});

it('32e. purchase order editability locks when workflow is complete or cancelled', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $completingStage = ($this->purchasingStage)($tenant, 'completing');
    $completedOrder = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_COMPLETED,
        'current_workflow_stage_id' => null,
        'last_completed_workflow_stage_id' => $completingStage->id,
    ]);
    $cancelledOrder = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'workflow_cancelled_at' => now(),
    ]);

    $completedPayload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$completedOrder->id}")->assertOk(),
        'purchasing-orders-show-payload'
    );
    $cancelledPayload = ($this->extractPayload)(
        $this->actingAs($user)->get("/purchasing/orders/{$cancelledOrder->id}")->assertOk(),
        'purchasing-orders-show-payload'
    );

    expect($completedPayload['purchaseOrder']['is_editable'] ?? null)->toBeFalse()
        ->and($cancelledPayload['purchaseOrder']['is_editable'] ?? null)->toBeFalse();
});

it('32f. purchase order header autosaves during receiving before inventory stage completion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $creatingStage = ($this->purchasingStage)($tenant, 'creating');
    $receivingStage = ($this->purchasingStage)($tenant, 'receiving');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'current_workflow_stage_id' => $receivingStage->id,
        'last_completed_workflow_stage_id' => $creatingStage->id,
    ]);

    ($this->updateHeader)($user, $order, [
        'po_number' => 'PO-RECEIVING-EDIT',
    ])->assertOk()
        ->assertJsonPath('data.purchase_order.is_editable', true);

    expect($order->fresh()->po_number)->toBe('PO-RECEIVING-EDIT');
});

it('32g. purchase order line add update and remove are allowed while receiving remains editable', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $creatingStage = ($this->purchasingStage)($tenant, 'creating');
    $receivingStage = ($this->purchasingStage)($tenant, 'receiving');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'current_workflow_stage_id' => $receivingStage->id,
        'last_completed_workflow_stage_id' => $creatingStage->id,
    ]);

    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 1000,
        'tax_percent' => '0',
    ])->assertCreated();

    $line = PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->firstOrFail();

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$order->id}/lines/{$line->id}", [
            'pack_count' => 2,
            'unit_price_cents' => 1000,
            'tax_percent' => '0',
        ])->assertOk();

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$order->id}/lines/{$line->id}")
        ->assertOk();
});

it('32h. purchase order edit endpoints reject locked workflow states with JSON errors', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->seedWorkflow)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $receivingStage = ($this->purchasingStage)($tenant, 'receiving');
    $completingStage = ($this->purchasingStage)($tenant, 'completing');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_RECEIVED,
        'current_workflow_stage_id' => $completingStage->id,
        'last_completed_workflow_stage_id' => $receivingStage->id,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->updateHeader)($user, $order, [
        'po_number' => 'LOCKED',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['purchase_order']);

    ($this->addLine)($user, $order, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 1000,
        'tax_percent' => '0',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['purchase_order']);

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$order->id}/lines/{$line->id}", [
            'pack_count' => 2,
            'unit_price_cents' => 1000,
            'tax_percent' => '0',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['purchase_order']);

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$order->id}/lines/{$line->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['purchase_order']);
});

it('32b. purchase order detail supplier select normalizes option ids as strings for alpine selection', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $pageModule = File::get(resource_path('js/pages/purchasing-orders-show.js'));

    expect($source)->toContain('x-bind:value="supplier.id"')
        ->and($source)->toContain('$el.value = form.supplier_id')
        ->and($pageModule)->toContain('const initialSupplierId = normalizeId(safePayload.purchaseOrder?.supplier_id)')
        ->and($pageModule)->toContain('suppliers: normalizedSuppliers')
        ->and($pageModule)->toContain('this.form.supplier_id = normalizeId(this.purchaseOrder?.supplier_id)')
        ->and($source)->toContain('x-model="form.supplier_id"');
});

it('33. purchase order items add row source is clean combobox plus button without label or dashed wrapper', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $itemsSource = substr($source, strpos($source, '<x-detail-section-card title="Items"'));
    $itemsSource = substr($itemsSource, 0, strpos($itemsSource, 'data-purchase-order-side-column'));

    expect($itemsSource)->toContain('<x-combobox')
        ->and($itemsSource)->toContain('supplierPackageComboboxOptions')
        ->and($itemsSource)->toContain('sm:flex-row')
        ->and($itemsSource)->toContain('aria-label="Add supplier package"')
        ->and($itemsSource)->not->toContain('Supplier package</label>')
        ->and($itemsSource)->not->toContain('rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4" x-show="isEditable"')
        ->and($itemsSource)->not->toContain('shadow" x-show="isEditable"');
});

it('34. purchase order lines source renders inline quantity tax inputs and icon only remove', function (): void {
    $source = File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $pageModule = File::get(resource_path('js/pages/purchasing-orders-show.js'));

    $itemsSection = Str::between(
        $source,
        '<x-detail-section-card title="Items" :default-open="true">',
        'data-purchase-order-side-column'
    );

    expect($itemsSection)->toContain("autosaveLineField(line, 'pack_count', \$event.detail)")
        ->and($itemsSection)->toContain('x-on:smart-number-input:changed="autosaveLineField(line, \'pack_count\', $event.detail)"')
        ->and($itemsSection)->toContain('x-on:smart-number-input:changed="autosaveLineField(line, \'tax_percent\', $event.detail)"')
        ->and($source)->toContain('inputmode="numeric"')
        ->and($itemsSection)->toContain('after-focus="$el.setSelectionRange($el.value.length, $el.value.length)"')
        ->and($itemsSection)->not->toContain('step="1"')
        ->and($itemsSection)->not->toContain(':step="quantityStep(line)"')
        ->and($pageModule)->not->toContain('quantityStep(line)')
        ->and($pageModule)->toContain('pack_count: this.normalizeNullableInt(line.pack_count)')
        ->and($pageModule)->toContain('async autosaveLineField(line, field, detail = null)')
        ->and($pageModule)->toContain("Object.prototype.hasOwnProperty.call(detail, 'rawValue')")
        ->and($pageModule)->toContain('payloadData.pack_count = this.normalizeNullableInt(fieldValue)')
        ->and($pageModule)->toContain('payloadData.tax_percent = this.normalizeNullable(fieldValue)')
        ->and($pageModule)->toContain('line.pack_count = payloadData.pack_count')
        ->and($pageModule)->toContain('line.tax_percent = payloadData.tax_percent')
        ->and($pageModule)->toContain('savedLineFieldValues: {}')
        ->and($pageModule)->toContain('savingLineFields: {}')
        ->and($pageModule)->toContain('pendingLineFieldValues: {}')
        ->and($pageModule)->toContain('this.pendingLineFieldValues')
        ->and($pageModule)->toContain('const hasPendingValue = Object.prototype.hasOwnProperty.call(this.pendingLineFieldValues, key)')
        ->and($pageModule)->toContain('const previousValue = line[field]')
        ->and($pageModule)->toContain('this.applyLineFieldValue(line, field, previousValue)')
        ->and($pageModule)->toContain('if (!hasPendingValue) {')
        ->and(File::get(app_path('Http/Controllers/PurchaseOrderController.php')))->toContain("'pack_count' => (int) \$line->pack_count")
        ->and(File::get(app_path('Http/Controllers/PurchaseOrderLineController.php')))->toContain("'pack_count' => (int) \$line->pack_count")
        ->and($pageModule)->not->toContain('Math.trunc(Number(line.pack_count')
        ->and($source)->toContain('data-line-autosave-success-icon')
        ->and($source)->toContain('inline-flex h-5 w-5 shrink-0 items-center justify-center')
        ->and($source)->toContain('text-lime-400')
        ->and($source)->toContain('M9 12.75 11.25 15 15 9.75')
        ->and($source)->toContain('lineFieldSaved(line, \'pack_count\')')
        ->and($source)->toContain('lineFieldSaved(line, \'tax_percent\')')
        ->and($pageModule)->toContain('savedLineFields: {}')
        ->and($pageModule)->toContain('savedLineFieldTimeouts: {}')
        ->and($pageModule)->toContain('showLineFieldSaved(updatedLine || line, field)')
        ->and($pageModule)->toContain('}, 1500)')
        ->and($source)->toContain("autosaveLineField(line, 'tax_percent', \$event.detail)")
        ->and($source)->toContain('inputmode="decimal"')
        ->and($source)->not->toContain('step="0.1"')
        ->and($source)->toContain('aria-label="Remove purchase order line"')
        ->and($source)->toContain('M6 18 18 6M6 6l12 12')
        ->and($source)->not->toContain('>Actions</th>')
        ->and($source)->not->toContain('>Edit</button>')
        ->and($source)->not->toContain('>Remove</button>');
});

it('35. purchase order line quantity autosave updates subtotal and totals', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, [
        'unit_price_cents' => 500,
        'line_subtotal_cents' => 500,
    ]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$order->id}/lines/{$line->id}", [
            'pack_count' => 3,
            'unit_price_cents' => 500,
            'tax_percent' => '0',
        ])->assertOk()
        ->assertJsonPath('data.line.pack_count', 3)
        ->assertJsonPath('data.line.line_subtotal_cents', 1500)
        ->assertJsonPath('data.purchase_order.po_subtotal_cents', 1500);
});

it('36. purchase order line tax autosave updates tax and grand total', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, [
        'unit_price_cents' => 1000,
        'line_subtotal_cents' => 1000,
    ]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->patchJson("/purchasing/orders/{$order->id}/lines/{$line->id}", [
            'pack_count' => 1,
            'unit_price_cents' => 1000,
            'tax_percent' => '13.5',
        ])->assertOk()
        ->assertJsonPath('data.line.tax_percent', '13.5')
        ->assertJsonPath('data.purchase_order.tax_cents', 135)
        ->assertJsonPath('data.purchase_order.po_grand_total_cents', 1135);
});

it('37. purchase order line remove endpoint deletes and refreshes totals', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, [
        'unit_price_cents' => 1000,
        'line_subtotal_cents' => 1000,
    ]);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$order->id}/lines/{$line->id}")
        ->assertOk()
        ->assertJsonPath('data.lines', [])
        ->assertJsonPath('data.purchase_order.po_subtotal_cents', 0)
        ->assertJsonPath('data.purchase_order.po_grand_total_cents', 0);

    expect(PurchaseOrderLine::query()->whereKey($line->id)->exists())->toBeFalse();
});

it('38. purchase order line remove remains permission and tenant scoped', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($otherTenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantPermission)($otherUser, 'purchasing-purchase-orders-create');

    $this->actingAs($user)
        ->deleteJson("/purchasing/orders/{$order->id}/lines/{$line->id}")
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->deleteJson("/purchasing/orders/{$order->id}/lines/{$line->id}")
        ->assertNotFound();
});

it('39. purchase order status docs and schema document persisted partially received status', function (): void {
    $enumDocs = file_get_contents(base_path('docs/ENUMS.md'));
    $schemaDocs = file_get_contents(base_path('docs/DB_SCHEMA.md'));
    $architectureDocs = file_get_contents(base_path('docs/architecture/purchasing/PurchaseOrderLifecycle.yaml'));
    $workflowSeeder = file_get_contents(base_path('app/Actions/Workflows/SeedDefaultWorkflowStagesForTenantAction.php'));

    expect($enumDocs)->toContain('PARTIALLY_RECEIVED')
        ->and($enumDocs)->not->toContain('PARTIALLY-RECEIVED')
        ->and($enumDocs)->not->toContain('SENT')
        ->and($schemaDocs)->not->toContain('SENT')
        ->and($architectureDocs)->not->toContain('SENT')
        ->and($schemaDocs)->toContain('PARTIALLY_RECEIVED')
        ->and($architectureDocs)->toContain('PARTIALLY_RECEIVED')
        ->and($workflowSeeder)->toContain("'purchasing' => [")
        ->and($workflowSeeder)->toContain("'status_complete_label' => 'CREATED'")
        ->and($workflowSeeder)->not->toContain("'key' => 'partially_received'")
        ->and(PurchaseOrder::statuses())->toContain(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
});
