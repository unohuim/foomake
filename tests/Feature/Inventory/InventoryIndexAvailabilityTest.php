<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Support\Inventory\InventoryAvailabilityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->customerCounter = 1;
    $this->supplierCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Inventory Availability Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'inventory-availability-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (
        Tenant $tenant,
        string $name = 'Each',
        string $symbol = 'ea',
        int $displayPrecision = 1
    ): Uom {
        $suffix = (string) $this->uomCounter;
        $this->uomCounter++;

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Inventory Availability Category ' . $suffix,
        ]);

        return Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $name . ' ' . $suffix,
            'symbol' => $symbol . '-' . $suffix,
            'display_precision' => $displayPrecision,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Inventory Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        $customer = Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Inventory Customer ' . $this->customerCounter,
            'status' => Customer::STATUS_ACTIVE,
            'customer_type' => Customer::TYPE_BUSINESS,
        ], $attributes));

        $this->customerCounter++;

        return $customer;
    };

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        $supplier = Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Inventory Supplier ' . $this->supplierCounter,
            'currency_code' => 'USD',
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeStockMove = function (
        Tenant $tenant,
        Item $item,
        string $quantity,
        string $type = 'receipt',
        ?string $status = 'POSTED'
    ): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => $quantity,
            'type' => $type,
            'status' => $status,
        ]);
    };

    $this->makeSalesOrder = function (
        Tenant $tenant,
        Customer $customer,
        string $status = SalesOrder::STATUS_OPEN
    ): SalesOrder {
        return SalesOrder::query()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'contact_id' => null,
            'status' => $status,
            'order_date' => '2026-05-21',
        ]);
    };

    $this->makeSalesOrderLine = function (
        Tenant $tenant,
        SalesOrder $salesOrder,
        Item $item,
        string $quantity
    ): SalesOrderLine {
        return SalesOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $salesOrder->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price_cents' => 100,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '100.000000',
        ]);
    };

    $this->makeRecipe = function (
        Tenant $tenant,
        Item $outputItem,
        array $attributes = []
    ): Recipe {
        return Recipe::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Inventory Recipe ' . $outputItem->id,
            'output_quantity' => '1.000000',
            'is_active' => true,
            'is_default' => false,
        ], $attributes));
    };

    $this->makeRecipeLine = function (
        Tenant $tenant,
        Recipe $recipe,
        Item $component,
        string $quantity
    ): RecipeLine {
        return RecipeLine::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'item_id' => $component->id,
            'quantity' => $quantity,
        ]);
    };

    $this->makePurchaseOption = function (
        Tenant $tenant,
        Supplier $supplier,
        Item $item,
        Uom $packUom,
        string $packQuantity
    ): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => 'SKU-' . $item->id . '-' . $supplier->id,
            'pack_quantity' => $packQuantity,
            'pack_uom_id' => $packUom->id,
        ]);
    };

    $this->makePurchaseOrder = function (
        Tenant $tenant,
        User $user,
        Supplier $supplier,
        string $status = PurchaseOrder::STATUS_OPEN
    ): PurchaseOrder {
        return PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => '2026-05-21',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_subtotal_cents' => 100,
            'po_grand_total_cents' => 100,
            'po_number' => 'PO-' . $tenant->id . '-' . $status,
            'notes' => null,
            'status' => $status,
        ]);
    };

    $this->makePurchaseOrderLine = function (
        Tenant $tenant,
        PurchaseOrder $purchaseOrder,
        Item $item,
        ItemPurchaseOption $option,
        int $packCount
    ): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => $packCount,
            'unit_price_cents' => 100,
            'line_subtotal_cents' => 100,
            'unit_price_amount' => 100,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 100,
            'fx_rate' => '1.00000000',
            'fx_rate_as_of' => '2026-05-21',
        ]);
    };

    $this->makeMakeOrder = function (
        Tenant $tenant,
        Recipe $recipe,
        string $status,
        string $outputQuantity
    ): MakeOrder {
        return MakeOrder::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $recipe->item_id,
            'output_quantity' => $outputQuantity,
            'status' => $status,
            'due_date' => $status === MakeOrder::STATUS_SCHEDULED ? '2026-05-22' : null,
            'scheduled_at' => $status === MakeOrder::STATUS_SCHEDULED ? now() : null,
            'made_at' => $status === MakeOrder::STATUS_MADE ? now() : null,
            'created_by_user_id' => null,
            'made_by_user_id' => null,
        ]);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($config) ? $config : [];
    };

    $this->inventoryIndex = function (?User $user = null) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('inventory.index'));
    };

    $this->inventoryList = function (?User $user = null, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('inventory.list', $query));
    };

    $this->inventoryRow = function (array $rows, int $itemId): array {
        return collect($rows)->firstWhere('id', $itemId) ?? [];
    };
});

it('1. redirects guests away from the inventory index', function (): void {
    ($this->inventoryIndex)()
        ->assertRedirect(route('login'));
});

it('2. rejects guests from the inventory list endpoint', function (): void {
    ($this->inventoryList)()
        ->assertUnauthorized();
});

it('3. forbids authenticated users without the inventory view permission from the index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->inventoryIndex)($user)
        ->assertForbidden();
});

it('4. forbids authenticated users without the inventory view permission from the list endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->inventoryList)($user)
        ->assertForbidden();
});

it('5. authorized users can view the inventory index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    ($this->inventoryIndex)($user)
        ->assertOk()
        ->assertSee('Inventory');
});

it('6. authorized users can load the inventory list endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    ($this->inventoryList)($user)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('7. inventory index renders through the configured crud page module', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    ($this->inventoryIndex)($user)
        ->assertOk()
        ->assertSee('data-page="inventory-index"', false)
        ->assertSee('data-payload="inventory-index-payload"', false)
        ->assertSee('data-crud-config=', false)
        ->assertSee('data-crud-root', false);
});

it('8. inventory blade shell is mount only and config driven', function (): void {
    $source = file_get_contents(resource_path('views/inventory/index.blade.php'));

    expect($source)->toContain('data-crud-root')
        ->and($source)->toContain('data-crud-config')
        ->and($source)->not->toContain('<table class="min-w-full text-sm">')
        ->and($source)->not->toContain('No inventory items available.');
});

it('9. inventory payload does not embed inventory records', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $response = ($this->inventoryIndex)($user)->assertOk();
    $payload = ($this->extractPayload)($response, 'inventory-index-payload');

    expect($payload['csrfToken'] ?? null)->toBeString()
        ->and($payload)->not->toHaveKey('items')
        ->and($payload)->not->toHaveKey('inventory');
});

it('10. data crud config is present and identifies the inventory resource', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));

    expect($config['resource'] ?? null)->toBe('inventory')
        ->and($config['endpoints']['list'] ?? null)->toBe(route('inventory.list'));
});

it('11. inventory search is enabled in the shared toolbar contract', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));

    expect($config['labels']['searchPlaceholder'] ?? null)->toBe('Search inventory');
});

it('12. create action is disabled in the inventory crud config', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));

    expect($config['permissions']['showCreate'] ?? null)->toBeFalse();
});

it('13. import action is disabled in the inventory crud config', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));

    expect($config['permissions']['showImport'] ?? null)->toBeFalse();
});

it('14. export action is disabled in the inventory crud config', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));

    expect($config['permissions']['showExport'] ?? null)->toBeFalse();
});

it('15. inventory headings are exactly item on hand sell buy make and net', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));
    $headers = collect($config['columns'] ?? [])
        ->map(fn (string $column): ?string => $config['headers'][$column] ?? null)
        ->all();

    expect($config['columns'] ?? [])->toBe(['item', 'on_hand', 'sell', 'buy', 'make', 'net'])
        ->and($headers)->toBe(['Item', 'On-Hand', 'Sell', 'Buy', 'Make', 'Net']);
});

it('16. inventory config does not emit a dedicated uom heading', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $response = ($this->inventoryIndex)($user)->assertOk();
    $config = ($this->extractCrudConfig)($response);

    expect(array_values($config['headers'] ?? []))->not->toContain('UoM')
        ->and($response->getContent())->not->toContain('>UoM<');
});

it('17. inventory list rows include the item name', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Gram', 'g');
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Brown Rice Flour']);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $rows = ($this->inventoryList)($user)->assertOk()->json('data');
    $row = ($this->inventoryRow)($rows, $item->id);

    expect($row['item'] ?? null)->toBe('Brown Rice Flour');
});

it('18. inventory list rows include the uom name inside the item display contract', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Kilogram', 'kg');
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Bread Dough']);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $response = ($this->inventoryIndex)($user)->assertOk();
    $config = ($this->extractCrudConfig)($response);
    $rows = ($this->inventoryList)($user)->assertOk()->json('data');
    $row = ($this->inventoryRow)($rows, $item->id);

    expect($row['item_uom_name'] ?? null)->toBe($uom->name)
        ->and($config['rowDisplay']['columns']['item']['kind'] ?? null)->toBe('stacked-text')
        ->and($config['rowDisplay']['columns']['item']['subtitleExpression'] ?? null)->toBe("record.item_uom_name || '—'");
});

it('18a. inventory page module exposes the desktop crud state contract expected by the shared renderer', function (): void {
    $source = file_get_contents(resource_path('js/pages/inventory-index.js'));

    expect($source)->toContain("import { mountCrudRenderer } from '../lib/crud-page';")
        ->and($source)->toContain("import { createGenericCrud } from '../lib/generic-crud';")
        ->and($source)->toContain('const crud = createGenericCrud(parseCrudConfig(rootEl));')
        ->and($source)->toContain('columns: Array.isArray(crud.columns) ? crud.columns : [],')
        ->and($source)->toContain('headers: crud.headers || {},')
        ->and($source)->toContain('sortable: Array.isArray(crud.sortable) ? crud.sortable : [],')
        ->and($source)->toContain('columnHeader(column)')
        ->and($source)->toContain('isSortableColumn(column)')
        ->and($source)->toContain('mountCrudRenderer(crudRootEl, rendererConfig);');
});

it('18b. inventory desktop crud contract emits no row action ui when actions are empty', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $response = ($this->inventoryIndex)($user)->assertOk();
    $config = ($this->extractCrudConfig)($response);

    expect($config['actions'] ?? null)->toBe([])
        ->and(file_get_contents(resource_path('js/pages/inventory-index.js')))->not->toContain('openEdit(record)')
        ->and(file_get_contents(resource_path('js/pages/inventory-index.js')))->not->toContain('openDelete(record)');
});

it('18c. inventory page module renders quantity columns from backend formatted display fields', function (): void {
    $source = file_get_contents(resource_path('js/pages/inventory-index.js'));

    expect($source)->toContain("on_hand: 'on_hand_display'")
        ->and($source)->toContain("sell: 'sell_display'")
        ->and($source)->toContain("buy: 'buy_display'")
        ->and($source)->toContain("make: 'make_display'")
        ->and($source)->toContain("net: 'net_display'")
        ->and($source)->toContain("record?.on_hand_display || '0.000000'")
        ->and($source)->toContain("record?.sell_display || '0.000000'")
        ->and($source)->toContain("record?.buy_display || '0.000000'")
        ->and($source)->toContain("record?.make_display || '0.000000'")
        ->and($source)->toContain("record?.net_display || '0.000000'")
        ->and($source)->not->toContain("`On-Hand: \${record?.on_hand || '0.000000'}`")
        ->and($source)->not->toContain("`Sell: \${record?.sell || '0.000000'}`")
        ->and($source)->not->toContain("`Buy: \${record?.buy || '0.000000'}`")
        ->and($source)->not->toContain("`Make: \${record?.make || '0.000000'}`")
        ->and($source)->not->toContain("`Net: \${record?.net || '0.000000'}`");
});

it('18d. inventory list rows include backend formatted display quantities using the item base uom precision', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Each', 'ea', 2);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeStockMove)($tenant, $item, '5.256000', 'receipt', 'POSTED');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['on_hand'] ?? null)->toBe('5.256000')
        ->and($row['on_hand_display'] ?? null)->toBe('5.26')
        ->and($row['sell_display'] ?? null)->toBe('0.00')
        ->and($row['buy_display'] ?? null)->toBe('0.00')
        ->and($row['make_display'] ?? null)->toBe('0.00')
        ->and($row['net_display'] ?? null)->toBe('5.26');
});

it('19. posted receipt stock moves increase on hand', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeStockMove)($tenant, $item, '5.250000', 'receipt', 'POSTED');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['on_hand'] ?? null)->toBe('5.250000');
});

it('20. posted issue stock moves decrease on hand', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeStockMove)($tenant, $item, '7.000000', 'receipt', 'POSTED');
    ($this->makeStockMove)($tenant, $item, '-2.000000', 'issue', 'POSTED');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['on_hand'] ?? null)->toBe('5.000000');
});

it('21. non posted stock moves are ignored', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->makeStockMove)($tenant, $item, '4.000000', 'receipt', 'POSTED');
    ($this->makeStockMove)($tenant, $item, '9.000000', 'receipt', 'DRAFT');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['on_hand'] ?? null)->toBe('4.000000');
});

it('22. draft sales orders are excluded from sell', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeSalesOrder)($tenant, $customer, SalesOrder::STATUS_DRAFT);
    ($this->makeSalesOrderLine)($tenant, $order, $item, '3.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['sell'] ?? null)->toBe('0.000000');
});

it('23. completed sales orders are excluded from sell', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeSalesOrder)($tenant, $customer, SalesOrder::STATUS_COMPLETED);
    ($this->makeSalesOrderLine)($tenant, $order, $item, '4.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['sell'] ?? null)->toBe('0.000000');
});

it('24. non draft non completed direct sales line demand is included in sell', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeSalesOrder)($tenant, $customer, SalesOrder::STATUS_OPEN);
    ($this->makeSalesOrderLine)($tenant, $order, $item, '2.500000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['sell'] ?? null)->toBe('2.500000');
});

it('25. fulfillment recipe component demand is included in sell when the output item is on a qualifying sales order', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $componentUom = ($this->makeUom)($tenant, 'Gram', 'g');
    $outputUom = ($this->makeUom)($tenant, 'Each', 'ea');
    $component = ($this->makeItem)($tenant, $componentUom, ['name' => 'Yeast']);
    $output = ($this->makeItem)($tenant, $outputUom, [
        'name' => 'Pizza Kit',
        'is_sellable' => true,
    ]);
    $recipe = ($this->makeRecipe)($tenant, $output, [
        'recipe_type' => Recipe::TYPE_FULFILLMENT,
        'output_quantity' => '1.000000',
        'is_default' => true,
    ]);
    ($this->makeRecipeLine)($tenant, $recipe, $component, '0.750000');
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeSalesOrder)($tenant, $customer, SalesOrder::STATUS_OPEN);
    ($this->makeSalesOrderLine)($tenant, $order, $output, '2.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $component->id
    );

    expect($row['sell'] ?? null)->toBe('1.500000');
});

it('26. draft purchase orders are excluded from buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_DRAFT);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 3);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('0.000000');
});

it('27. completed or terminal purchase orders are excluded from buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.000000');
    $receivedOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_RECEIVED);
    $cancelledOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CANCELLED);
    ($this->makePurchaseOrderLine)($tenant, $receivedOrder, $item, $option, 3);
    ($this->makePurchaseOrderLine)($tenant, $cancelledOrder, $item, $option, 2);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('0.000000');
});

it('28. qualifying open purchase order quantities are included in buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '1.500000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_OPEN);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 4);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('6.000000');
});

it('29. purchase option pack quantity is applied when calculating buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.250000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_OPEN);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 3);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('6.750000');
});

it('30. draft make orders are excluded from make', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_DRAFT, '3.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['make'] ?? null)->toBe('0.000000');
});

it('31. made or completed make orders are excluded from make', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_MADE, '4.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['make'] ?? null)->toBe('0.000000');
});

it('32. one run multiplied by recipe output quantity is included in make', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_SCHEDULED, '1.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['make'] ?? null)->toBe('10.000000');
});

it('33. two runs multiplied by recipe output quantity are included in make', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_SCHEDULED, '2.000000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['make'] ?? null)->toBe('20.000000');
});

it('34. net uses the corrected make value', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_sellable' => true, 'is_manufacturable' => true]);
    $customer = ($this->makeCustomer)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.000000');
    $salesOrder = ($this->makeSalesOrder)($tenant, $customer, SalesOrder::STATUS_OPEN);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_OPEN);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    ($this->makeStockMove)($tenant, $item, '10.000000', 'receipt', 'POSTED');
    ($this->makeSalesOrderLine)($tenant, $salesOrder, $item, '3.000000');
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 2);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_SCHEDULED, '2.000000');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['on_hand'] ?? null)->toBe('10.000000')
        ->and($row['sell'] ?? null)->toBe('3.000000')
        ->and($row['buy'] ?? null)->toBe('4.000000')
        ->and($row['make'] ?? null)->toBe('20.000000')
        ->and($row['net'] ?? null)->toBe('31.000000');
});

it('35. make display respects the item base uom display precision', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Each', 'ea', 2);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_SCHEDULED, '1.234000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['make'] ?? null)->toBe('12.340000')
        ->and($row['make_display'] ?? null)->toBe('12.34');
});

it('36. calculator returns the corrected make quantity for a single item', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant, 'Each', 'ea', 1);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_SCHEDULED, '2.000000');

    $availability = app(InventoryAvailabilityCalculator::class)->forItem($item);

    expect($availability['make'] ?? null)->toBe('20.000000')
        ->and($availability['make_display'] ?? null)->toBe('20.0');
});

it('37. inventory results are tenant scoped with no cross tenant leakage', function (): void {
    $tenant = ($this->makeTenant)('Visible Tenant');
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Gram', 'g');
    $visibleItem = ($this->makeItem)($tenant, $uom, ['name' => 'Visible Item']);
    ($this->makeStockMove)($tenant, $visibleItem, '1.000000');

    $otherTenant = ($this->makeTenant)('Hidden Tenant');
    $otherUom = ($this->makeUom)($otherTenant, 'Gram', 'g');
    $hiddenItem = ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Hidden Item']);
    ($this->makeStockMove)($otherTenant, $hiddenItem, '9.000000');

    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $rows = ($this->inventoryList)($user)->assertOk()->json('data');
    $ids = collect($rows)->pluck('id');
    $names = collect($rows)->pluck('item');

    expect($ids)->toContain($visibleItem->id)
        ->and($ids)->not->toContain($hiddenItem->id)
        ->and($names)->toContain('Visible Item')
        ->and($names)->not->toContain('Hidden Item');
});

it('38. cross tenant make orders do not affect the visible tenants make quantity', function (): void {
    $tenant = ($this->makeTenant)('Visible Make Tenant');
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $visibleItem = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true, 'name' => 'Visible Make Item']);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $otherTenant = ($this->makeTenant)('Hidden Make Tenant');
    $otherUom = ($this->makeUom)($otherTenant);
    $hiddenItem = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true, 'name' => 'Hidden Make Item']);
    $hiddenRecipe = ($this->makeRecipe)($otherTenant, $hiddenItem, ['output_quantity' => '10.000000']);
    ($this->makeMakeOrder)($otherTenant, $hiddenRecipe, MakeOrder::STATUS_SCHEDULED, '2.000000');

    $rows = ($this->inventoryList)($user)->assertOk()->json('data');
    $visibleRow = ($this->inventoryRow)($rows, $visibleItem->id);
    $hiddenRow = ($this->inventoryRow)($rows, $hiddenItem->id);

    expect($visibleRow['make'] ?? null)->toBe('0.000000')
        ->and($hiddenRow)->toBe([]);
});

it('39. inventory quantities are returned as canonical quantity strings', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '2.000000']);
    ($this->makeStockMove)($tenant, $item, '2.500000');
    ($this->makeMakeOrder)($tenant, $recipe, MakeOrder::STATUS_SCHEDULED, '1.250000');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    foreach (['on_hand', 'sell', 'buy', 'make', 'net'] as $column) {
        expect($row[$column] ?? null)->toBeString()
            ->and($row[$column] ?? null)->toMatch('/^-?\d+\.\d{6}$/');
    }
});

it('40. inventory search filters rows by item name', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    ($this->makeItem)($tenant, $uom, ['name' => 'Vanilla Paste']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Cocoa Powder']);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $rows = ($this->inventoryList)($user, ['search' => 'Vanilla'])->assertOk()->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item'] ?? null)->toBe('Vanilla Paste');
});
