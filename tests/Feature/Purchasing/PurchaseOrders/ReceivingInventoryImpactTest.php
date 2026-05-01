<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Model::clearBootedModels();
});

beforeEach(function (): void {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->supplierCounter = 1;
    $this->poCounter = 1;
    $this->purchaseReceiptLineSourceType = 'purchase_order_receipt_line';

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

    $this->grantReceivePermission = function (User $user): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'purchasing-purchase-orders-receive',
        ]);

        $role = Role::query()->create([
            'name' => 'role-' . $this->roleCounter,
        ]);

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
            'symbol' => $attributes['symbol'] ?? 'u' . $this->uomCounter,
            'display_precision' => $attributes['display_precision'] ?? 6,
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

    $this->makeOption = function (
        Tenant $tenant,
        Supplier $supplier,
        Item $item,
        Uom $uom,
        array $attributes = []
    ): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => 'SKU-' . $item->id,
            'pack_quantity' => '1.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));
    };

    $this->makeOrder = function (
        Tenant $tenant,
        User $user,
        Supplier $supplier,
        array $attributes = []
    ): PurchaseOrder {
        $order = PurchaseOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => '2026-02-04',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_number' => 'PO-' . $this->poCounter,
            'notes' => null,
            'status' => PurchaseOrder::STATUS_OPEN,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ], $attributes));

        $this->poCounter++;

        return $order;
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
            'pack_count' => 10,
            'unit_price_cents' => 100,
            'line_subtotal_cents' => 1000,
            'unit_price_amount' => 100,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 100,
            'fx_rate' => '1.00000000',
            'fx_rate_as_of' => '2026-02-04',
        ], $attributes));
    };

    $this->receive = function (User $user, PurchaseOrder $order, array $payload = []) {
        return $this->actingAs($user)->postJson("/purchasing/orders/{$order->id}/receipts", $payload);
    };

    $this->findReceipt = function (PurchaseOrder $order): ?PurchaseOrderReceipt {
        return PurchaseOrderReceipt::query()
            ->where('purchase_order_id', $order->id)
            ->first();
    };

    $this->findReceiptLine = function (PurchaseOrderLine $line): ?PurchaseOrderReceiptLine {
        return PurchaseOrderReceiptLine::query()
            ->where('purchase_order_line_id', $line->id)
            ->first();
    };

    $this->forceStockMoveCreateFailure = function (string $message = 'Simulated stock move failure.'): void {
        StockMove::flushEventListeners();

        StockMove::creating(static function () use ($message): void {
            throw new \DomainException($message);
        });
    };
});

it('1. receiving creates a purchase_order_receipt', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'reference' => 'RCPT-001',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    $receipt = ($this->findReceipt)($order);

    expect($receipt)->not->toBeNull()
        ->and($receipt?->tenant_id)->toBe($tenant->id)
        ->and($receipt?->purchase_order_id)->toBe($order->id)
        ->and($receipt?->received_by_user_id)->toBe($user->id)
        ->and($receipt?->reference)->toBe('RCPT-001');
});

it('2. receiving creates purchase_order_receipt_lines', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '3.000000',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);

    expect($receiptLine)->not->toBeNull()
        ->and($receiptLine?->tenant_id)->toBe($tenant->id)
        ->and($receiptLine?->purchase_order_line_id)->toBe($line->id)
        ->and((string) $receiptLine?->received_quantity)->toBe('3.000000');
});

it('3. each receipt line creates a stock_move', function (): void {
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

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'lines' => [
            ['purchase_order_line_id' => $lineA->id, 'received_quantity' => '1.250000'],
            ['purchase_order_line_id' => $lineB->id, 'received_quantity' => '2.750000'],
        ],
    ])->assertCreated();

    $receiptLineA = ($this->findReceiptLine)($lineA);
    $receiptLineB = ($this->findReceiptLine)($lineB);

    $moves = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->whereIn('source_id', [$receiptLineA?->id, $receiptLineB?->id])
        ->count();

    expect($receiptLineA)->not->toBeNull()
        ->and($receiptLineB)->not->toBeNull()
        ->and($moves)->toBe(2);
});

it('4. stock move type is receipt', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->where('source_id', $receiptLine?->id)
        ->first();

    expect($stockMove)->not->toBeNull()
        ->and($stockMove?->type)->toBe('receipt');
});

it('5. stock move status is POSTED', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->where('source_id', $receiptLine?->id)
        ->first();

    expect($stockMove)->not->toBeNull()
        ->and($stockMove?->status)->toBe('POSTED');
});

it('6. stock move quantity matches received quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.333333',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->where('source_id', $receiptLine?->id)
        ->first();

    expect($stockMove)->not->toBeNull()
        ->and((string) $stockMove?->quantity)->toBe('2.333333');
});

it('7. stock move item matches purchase order line item', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->where('source_id', $receiptLine?->id)
        ->first();

    expect($stockMove)->not->toBeNull()
        ->and($stockMove?->item_id)->toBe($line->item_id);
});

it('8. stock move UoM matches item base UoM', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->where('source_id', $receiptLine?->id)
        ->first();

    expect($stockMove)->not->toBeNull()
        ->and($stockMove?->uom_id)->toBe($item->base_uom_id);
});

it('9. stock move tenant matches receipt tenant', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '4.000000',
    ])->assertCreated();

    $receipt = ($this->findReceipt)($order);
    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->where('source_id', $receiptLine?->id)
        ->first();

    expect($receipt)->not->toBeNull()
        ->and($stockMove)->not->toBeNull()
        ->and($stockMove?->tenant_id)->toBe($receipt?->tenant_id);
});

it('10. receipt line links to its stock move', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();

    expect(Schema::hasColumn('purchase_order_receipt_lines', 'stock_move_id'))->toBeTrue();

    $receiptLine = PurchaseOrderReceiptLine::query()->with('stockMove')->first();

    expect($receiptLine)->not->toBeNull()
        ->and($receiptLine?->stock_move_id)->toBeInt()
        ->and($receiptLine?->stockMove)->not->toBeNull()
        ->and($receiptLine?->stockMove?->id)->toBe($receiptLine?->stock_move_id);
});

it('11. inventory on-hand increases after receipt', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.500000',
    ])->assertCreated();

    $item->refresh();

    expect($item->onHandQuantity())->toBe('2.500000');
});

it('12. multi-line receipt creates one stock move per line', function (): void {
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

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'lines' => [
            ['purchase_order_line_id' => $lineA->id, 'received_quantity' => '1.000000'],
            ['purchase_order_line_id' => $lineB->id, 'received_quantity' => '2.000000'],
        ],
    ])->assertCreated();

    $receipt = ($this->findReceipt)($order);
    $receiptLineCount = PurchaseOrderReceiptLine::query()
        ->where('purchase_order_receipt_id', $receipt?->id)
        ->count();
    $stockMoveCount = StockMove::query()
        ->where('source_type', $this->purchaseReceiptLineSourceType)
        ->count();

    expect($receiptLineCount)->toBe(2)
        ->and($stockMoveCount)->toBe(2);
});

it('13. partial receipt increases inventory only by received amount', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 10]);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.500000',
    ])->assertCreated();

    $item->refresh();
    $order->refresh();

    expect($item->onHandQuantity())->toBe('2.500000')
        ->and($order->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
});

it('14. full receipt updates purchase order to RECEIVED', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 10]);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '10.000000',
    ])->assertCreated();

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrder::STATUS_RECEIVED);
});

it('15. purchase order cannot become RECEIVED if stock move creation fails', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 10]);

    ($this->grantReceivePermission)($user);
    ($this->forceStockMoveCreateFailure)('Receipt inventory impact could not be created.');

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '10.000000',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Receipt inventory impact could not be created.');

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrder::STATUS_OPEN);
});

it('16. receipt transaction rolls back if stock move creation fails', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);
    ($this->forceStockMoveCreateFailure)();

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);

    expect(PurchaseOrderReceipt::query()->count())->toBe(0)
        ->and(PurchaseOrderReceiptLine::query()->count())->toBe(0)
        ->and(StockMove::query()->count())->toBe(0);
});

it('17. duplicate receipt submission cannot create duplicate stock moves', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 5]);
    $payload = [
        'reference' => 'RCPT-DUPLICATE',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '5.000000',
    ];

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, $payload)->assertCreated();
    ($this->receive)($user, $order, $payload)->assertStatus(422);

    expect(PurchaseOrderReceipt::query()->count())->toBe(1)
        ->and(PurchaseOrderReceiptLine::query()->count())->toBe(1)
        ->and(StockMove::query()->count())->toBe(1);
});

it('18. already-linked receipt line cannot create a second stock move', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 1]);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'reference' => 'RCPT-LINK-1',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();

    ($this->receive)($user, $order, [
        'reference' => 'RCPT-LINK-2',
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['received_quantity']);

    expect(PurchaseOrderReceipt::query()->count())->toBe(1)
        ->and(PurchaseOrderReceiptLine::query()->count())->toBe(1)
        ->and(StockMove::query()->count())->toBe(1);
});

it('19. receiving requires purchasing-purchase-orders-receive', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherTenant = ($this->makeTenant)(['tenant_name' => 'Other Tenant']);
    $otherTenantUser = ($this->makeUser)($otherTenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    $this->actingAs($user)
        ->postJson("/purchasing/orders/{$order->id}/receipts", [
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '1.000000',
        ])
        ->assertForbidden();

    ($this->grantReceivePermission)($otherTenantUser);

    $this->actingAs($otherTenantUser)
        ->postJson("/purchasing/orders/{$order->id}/receipts", [
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '1.000000',
        ])
        ->assertNotFound();
});

it('20. unauthorized user cannot receive', function (): void {
    $this->postJson('/purchasing/orders/1/receipts', [])
        ->assertUnauthorized();

    $tenantA = ($this->makeTenant)(['tenant_name' => 'Tenant A']);
    $tenantB = ($this->makeTenant)(['tenant_name' => 'Tenant B']);
    $userA = ($this->makeUser)($tenantA);
    $supplierA = ($this->makeSupplier)($tenantA);
    $supplierB = ($this->makeSupplier)($tenantB);
    $uomA = ($this->makeUom)($tenantA, ['symbol' => 'a-uom']);
    $uomB = ($this->makeUom)($tenantB, ['symbol' => 'b-uom']);
    $itemA = ($this->makeItem)($tenantA, $uomA);
    $itemB = ($this->makeItem)($tenantB, $uomB);
    $optionA = ($this->makeOption)($tenantA, $supplierA, $itemA, $uomA);
    $optionB = ($this->makeOption)($tenantB, $supplierB, $itemB, $uomB);
    $orderA = ($this->makeOrder)($tenantA, $userA, $supplierA);
    $lineB = ($this->makeLine)($tenantB, ($this->makeOrder)($tenantB, ($this->makeUser)($tenantB), $supplierB), $itemB, $optionB);
    ($this->makeLine)($tenantA, $orderA, $itemA, $optionA);

    ($this->grantReceivePermission)($userA);

    $this->actingAs($userA)
        ->postJson("/purchasing/orders/{$orderA->id}/receipts", [
            'purchase_order_line_id' => $lineB->id,
            'received_quantity' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['purchase_order_line_id']);

    expect(PurchaseOrderReceipt::query()->count())->toBe(0)
        ->and(PurchaseOrderReceiptLine::query()->count())->toBe(0)
        ->and(StockMove::query()->count())->toBe(0);
});

it('21. terminal purchase order statuses cannot receive', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_RECEIVED,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('22. cancelled purchase orders cannot receive', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CANCELLED,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('23. short-closed purchase orders cannot receive', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_SHORT_CLOSED,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertStatus(422);
});

it('24. quantity math uses BCMath and string precision', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 1]);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '0.333333',
    ])->assertCreated();

    $receiptLine = ($this->findReceiptLine)($line);
    $stockMove = StockMove::query()->first();
    $item->refresh();

    expect((string) $receiptLine?->received_quantity)->toBe('0.333333')
        ->and((string) $stockMove?->quantity)->toBe('0.333333')
        ->and($item->onHandQuantity())->toBe('0.333333');
});

it('25. receiving has no float rounding errors in quantities', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $lineA = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 1]);
    $lineB = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 1]);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'lines' => [
            ['purchase_order_line_id' => $lineA->id, 'received_quantity' => '0.100000'],
            ['purchase_order_line_id' => $lineB->id, 'received_quantity' => '0.200000'],
        ],
    ])->assertCreated();

    $item->refresh();

    expect($item->onHandQuantity())->toBe('0.300000');
});

it('26. stock move source references the receipt line correctly', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    $receiptLine = PurchaseOrderReceiptLine::query()->first();
    $stockMove = StockMove::query()->with('source')->first();

    expect($receiptLine)->not->toBeNull()
        ->and($stockMove)->not->toBeNull()
        ->and($stockMove?->source_type)->toBe($this->purchaseReceiptLineSourceType)
        ->and($stockMove?->source_id)->toBe($receiptLine?->id)
        ->and($stockMove?->source)->not->toBeNull()
        ->and($stockMove?->source?->id)->toBe($receiptLine?->id);
});

it('27. back-ordered purchase order receipt creates stock moves', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_BACK_ORDERED,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    expect(StockMove::query()->count())->toBe(1);
});

it('28. partially-received purchase order receipt creates stock moves', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 10]);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ])->assertCreated();

    $order->refresh();
    expect($order->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    expect(StockMove::query()->count())->toBe(2);
});

it('29. status transition depends on receipt plus stock move success', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_OPEN,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option, ['pack_count' => 10]);

    ($this->grantReceivePermission)($user);
    ($this->forceStockMoveCreateFailure)('Stock move creation blocked.');

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Stock move creation blocked.');

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrder::STATUS_OPEN);
});

it('30. ajax receive endpoint returns JSON success and JSON error responses only', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    $successResponse = ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '1.000000',
    ]);

    $errorResponse = ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '20.000000',
    ]);

    $successContentType = (string) $successResponse->headers->get('content-type');
    $errorContentType = (string) $errorResponse->headers->get('content-type');

    $successResponse->assertCreated()->assertJsonStructure(['data' => ['id']]);
    $errorResponse->assertStatus(422)->assertJsonValidationErrors(['received_quantity']);

    expect(str_starts_with($successContentType, 'application/json'))->toBeTrue()
        ->and(str_starts_with($errorContentType, 'application/json'))->toBeTrue();
});

it('converts received pack quantity into base-unit stock move quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'pack_quantity' => '500.000000',
    ]);
    $order = ($this->makeOrder)($tenant, $user, $supplier);
    $line = ($this->makeLine)($tenant, $order, $item, $option);

    ($this->grantReceivePermission)($user);

    ($this->receive)($user, $order, [
        'purchase_order_line_id' => $line->id,
        'received_quantity' => '2.000000',
    ])->assertCreated();

    $stockMove = StockMove::query()->first();
    $item->refresh();

    expect($stockMove)->not->toBeNull()
        ->and((string) $stockMove?->quantity)->toBe('1000.000000')
        ->and($item->onHandQuantity())->toBe('1000.000000');
});
