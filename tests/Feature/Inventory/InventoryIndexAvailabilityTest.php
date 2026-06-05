<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\ItemUomConversion;
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
use App\Models\UomConversion;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Support\Inventory\InventoryAvailabilityCalculator;
use App\Support\Inventory\InventoryBuyUomConversionResolver;
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

    $this->makeRelatedUom = function (
        Tenant $tenant,
        Uom $baseUom,
        string $name,
        string $symbol,
        int $displayPrecision = 1
    ): Uom {
        $suffix = (string) $this->uomCounter;
        $this->uomCounter++;

        return Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $baseUom->uom_category_id,
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
        string $status = PurchaseOrder::STATUS_CREATED
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

    $this->makePurchasingWorkflowStages = function (Tenant $tenant): array {
        $domain = WorkflowDomain::query()->firstOrCreate(
            ['key' => 'purchasing'],
            ['name' => 'Purchasing', 'sort_order' => 20]
        );

        $creating = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'creating',
        ], [
            'name' => 'Creating',
            'action_verb' => 'CREATE',
            'status_complete_label' => 'CREATED',
            'completion_mode' => 'manual',
            'sort_order' => 10,
            'is_active' => true,
            'is_inventory_effect_stage' => false,
        ]);

        $receiving = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'receiving',
        ], [
            'name' => 'Receiving',
            'action_verb' => 'RECEIVE',
            'status_complete_label' => 'RECEIVED',
            'completion_mode' => 'manual',
            'sort_order' => 20,
            'is_active' => true,
            'is_inventory_effect_stage' => true,
        ]);

        $completing = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'completing',
        ], [
            'name' => 'Completing',
            'action_verb' => 'COMPLETE',
            'status_complete_label' => 'COMPLETED',
            'completion_mode' => 'automatic',
            'sort_order' => 30,
            'is_active' => true,
            'is_inventory_effect_stage' => false,
        ]);

        return [
            'creating' => $creating,
            'receiving' => $receiving,
            'completing' => $completing,
        ];
    };

    $this->makeMakeOrder = function (
        Tenant $tenant,
        Recipe $recipe,
        string $status,
        string $runs
    ): MakeOrder {
        $recipeOutputQuantity = bcadd((string) ($recipe->output_quantity ?? '0.000000'), '0', 6);
        $canonicalRuns = bcadd($runs, '0', 6);

        return MakeOrder::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $recipe->item_id,
            'runs' => $canonicalRuns,
            'expected_output_qty' => bcmul($canonicalRuns, $recipeOutputQuantity, 6),
            'actual_output_qty' => null,
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

it('3. forbids authenticated users without the inventory stock view permission from the index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->inventoryIndex)($user)
        ->assertForbidden();
});

it('4. forbids authenticated users without the inventory stock view permission from the list endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->inventoryList)($user)
        ->assertForbidden();
});

it('5. users with inventory stock view can view the inventory index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-stock-view');

    ($this->inventoryIndex)($user)
        ->assertOk()
        ->assertSee('Inventory');
});

it('6. users with inventory stock view can load the inventory list endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-stock-view');

    ($this->inventoryList)($user)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('6a. users with inventory adjustments view can still view the inventory index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    ($this->inventoryIndex)($user)
        ->assertOk()
        ->assertSee('Inventory');
});

it('6b. users with inventory adjustments view can still load the inventory list endpoint', function (): void {
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

it('18aa. inventory item rows expose the material detail link inside the stacked item cell contract', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Each', 'ea');
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Linked Inventory Item']);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $response = ($this->inventoryIndex)($user)->assertOk();
    $config = ($this->extractCrudConfig)($response);
    $rows = ($this->inventoryList)($user)->assertOk()->json('data');
    $row = ($this->inventoryRow)($rows, $item->id);

    expect($config['rowDisplay']['columns']['item']['kind'] ?? null)->toBe('stacked-text')
        ->and($config['rowDisplay']['columns']['item']['urlExpression'] ?? null)->toBe("record.show_url || ''")
        ->and($row['show_url'] ?? null)->toBe(route('materials.show', $item));
});

it('18ab. inventory mobile rows expose material badges and active toggle config', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-materials-manage');

    $config = ($this->extractCrudConfig)(($this->inventoryIndex)($user));

    expect($config['mobileCard']['titleAsideExpression'] ?? null)
        ->toBe("record.item_uom_name || record.item_uom_symbol || '—'")
        ->and($config['mobileCard']['badgesExpression'] ?? null)->toBe('inventoryMaterialFlagBadges(record)')
        ->and($config['mobileCard']['urlExpression'] ?? null)->toBe('record.show_url')
        ->and($config['mobileCard']['showActions'] ?? null)->toBeFalse()
        ->and($config['mobileCard']['toggle']['name'] ?? null)->toBe('is_active')
        ->and($config['mobileCard']['toggle']['checkedExpression'] ?? null)->toBe('Boolean(record.is_active)')
        ->and($config['mobileCard']['toggle']['disabledExpression'] ?? null)->toBe('!canManageMaterials()')
        ->and($config['mobileCard']['toggle']['handler'] ?? null)->toBe('toggleInventoryMaterialActive(toggleDetail)')
        ->and($config['permissions']['canManageMaterials'] ?? null)->toBeTrue();
});

it('18ac. inventory list rows expose material state for the mobile toggle row', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Gram', 'g');
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Toggle Inventory Item',
        'is_active' => false,
        'is_stockable' => true,
        'is_purchasable' => true,
        'is_sellable' => true,
        'is_manufacturable' => false,
        'default_price_cents' => 1234,
        'default_price_currency_code' => 'USD',
    ]);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $rows = ($this->inventoryList)($user)->assertOk()->json('data');
    $row = ($this->inventoryRow)($rows, $item->id);

    expect($row['base_uom_id'] ?? null)->toBe($uom->id)
        ->and($row['item_uom_symbol'] ?? null)->toBe($uom->symbol)
        ->and($row['is_active'] ?? null)->toBeFalse()
        ->and($row['is_stockable'] ?? null)->toBeTrue()
        ->and($row['is_purchasable'] ?? null)->toBeTrue()
        ->and($row['is_sellable'] ?? null)->toBeTrue()
        ->and($row['is_manufacturable'] ?? null)->toBeFalse()
        ->and($row['default_price_amount'] ?? null)->toBe('12.34')
        ->and($row['default_price_currency_code'] ?? null)->toBe('USD')
        ->and($row['update_url'] ?? null)->toBe(route('materials.update', $item));
});

it('18ad. inventory page module wires the shared mobile active toggle handler', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/inventory-index.js'));
    $rendererSource = file_get_contents(resource_path('js/lib/crud-page.js'));
    $viewSource = file_get_contents(resource_path('views/inventory/index.blade.php'));

    expect($rendererSource)->toContain("import { renderToggle } from '../components/toggle';")
        ->and($pageSource)->toContain('inventoryMaterialFlagBadges(record)')
        ->and($pageSource)->toContain('canManageMaterials()')
        ->and($pageSource)->toContain('async toggleInventoryMaterialActive(toggleDetail)')
        ->and($pageSource)->toContain("is_active: nextValue")
        ->and($pageSource)->toContain('default_price_amount: record.default_price_amount ||')
        ->and($pageSource)->toContain('${record.item || \'Material\'} ${record.is_active ? \'Active\' : \'Inactive\'}')
        ->and($viewSource)->toContain('<x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />');
});

it('18ae. inventory page renderer keeps the stacked item link and uom subtext without a dedicated uom column', function (): void {
    $source = file_get_contents(resource_path('js/lib/crud-page.js'));

    expect($source)->toContain("if (kind === 'stacked-text')")
        ->and($source)->toContain(':href="${urlExpression}"')
        ->and($source)->toContain('x-text="${cellTextExpression}"')
        ->and($source)->toContain('x-text="${subtitleExpression}"');
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

it('26. draft purchase orders without workflow state are included in buy when not cancelled or complete', function (): void {
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

    expect($row['buy'] ?? null)->toBe('6.000000');
});

it('27. completed or terminal purchase orders are excluded from buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.000000');
    $completedOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_COMPLETED);
    $cancelledOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    $cancelledOrder->forceFill(['cancelled_at' => now(), 'cancelled_by_user_id' => $user->id])->save();
    ($this->makePurchaseOrderLine)($tenant, $completedOrder, $item, $option, 3);
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
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
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
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 3);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('6.750000');
});

it('29a. open purchase order buy uses package quantity when pack uom matches item base uom', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 2);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('40.000000')
        ->and($row['buy_display'] ?? null)->toBe('40');
});

it('29b. open purchase order buy converts supplier package uom into item base uom', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('20000.000000')
        ->and($row['buy_display'] ?? null)->toBe('20000');
});

it('29ba. workflow-open purchase order buy is included even when legacy status is stale draft', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $stages = ($this->makePurchasingWorkflowStages)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_DRAFT);
    $purchaseOrder->forceFill([
        'current_workflow_stage_id' => $stages['receiving']->id,
        'last_completed_workflow_stage_id' => $stages['creating']->id,
    ])->save();
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($purchaseOrder->fresh()->workflowStatus())->toBe('CREATED')
        ->and($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($row['buy'] ?? null)->toBe('20000.000000')
        ->and($row['buy_display'] ?? null)->toBe('20000');
});

it('29bb. diagnostic trace proves material supplier package purchase order buy reaches rendered inventory output', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $stages = ($this->makePurchasingWorkflowStages)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram, ['name' => 'Brown Rice']);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    ItemPurchaseOptionPrice::query()->create([
        'tenant_id' => $tenant->id,
        'item_purchase_option_id' => $option->id,
        'price_cents' => 100,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 100,
        'fx_rate' => '1.00000000',
        'fx_rate_as_of' => '2026-05-21',
        'effective_at' => now(),
        'ended_at' => null,
    ]);
    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $createResponse = $this->actingAs($user)->postJson(route('materials.purchase-orders.store', $item), [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
    ])->assertCreated();

    $purchaseOrder = PurchaseOrder::query()->findOrFail((int) $createResponse->json('data.id'));
    $line = PurchaseOrderLine::query()
        ->where('purchase_order_id', $purchaseOrder->id)
        ->firstOrFail();

    $conversion = UomConversion::query()
        ->where('tenant_id', $tenant->id)
        ->where('from_uom_id', $kilogram->id)
        ->where('to_uom_id', $gram->id)
        ->first();
    expect($conversion)->not->toBeNull('Expected tenant-visible kg to g conversion for Inventory BUY diagnostics.');

    $calculatedLineQuantity = bcmul(
        bcadd((string) $line->pack_count, '0', 6),
        bcadd((string) $option->pack_quantity, '0', 6),
        6
    );
    $calculatedLineQuantity = bcmul($calculatedLineQuantity, (string) $conversion?->multiplier, 6);
    $readModelRow = app(InventoryAvailabilityCalculator::class)->forItem($item);
    $listRow = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );
    $jsSource = file_get_contents(resource_path('js/pages/inventory-index.js'));

    expect((int) $item->base_uom_id)->toBe((int) $gram->id)
        ->and($item->baseUom?->name)->toContain('Gram')
        ->and((int) $option->pack_uom_id)->toBe((int) $kilogram->id)
        ->and($option->packUom?->name)->toContain('Kilogram')
        ->and(bcadd((string) $option->pack_quantity, '0', 6))->toBe('20.000000')
        ->and((int) $line->pack_count)->toBe(1)
        ->and((int) $line->item_id)->toBe((int) $item->id)
        ->and((int) $line->item_purchase_option_id)->toBe((int) $option->id)
        ->and(bcadd((string) $option->pack_quantity, '0', 6))->toBe('20.000000')
        ->and((string) $conversion?->multiplier)->toBe('1000.00000000')
        ->and($calculatedLineQuantity)->toBe('20000.000000')
        ->and($purchaseOrder->fresh()->workflow_cancelled_at)->toBeNull()
        ->and($purchaseOrder->fresh()->current_workflow_stage_id)->toBe($stages['creating']->id)
        ->and($readModelRow['buy'] ?? null)->toBe('20000.000000')
        ->and($listRow['buy'] ?? null)->toBe('20000.000000')
        ->and($listRow['buy_display'] ?? null)->toBe('20000')
        ->and($jsSource)->toContain("buy: 'buy_display'");
});

it('29bc. inventory buy resolves global direct conversions by uom symbol instead of matching uom ids', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $tenantCategory = UomCategory::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Mass 29bc',
    ]);
    $globalCategory = UomCategory::query()->create([
        'tenant_id' => null,
        'name' => 'Global Mass 29bc',
    ]);
    $tenantGram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'g')->firstOrFail();
    $tenantKilogram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'kg')->firstOrFail();
    $globalGram = Uom::query()->firstOrCreate([
        'tenant_id' => null,
        'symbol' => 'g',
    ], [
        'uom_category_id' => $globalCategory->id,
        'name' => 'Global Gram 29bc',
        'display_precision' => 0,
    ]);
    $globalKilogram = Uom::query()->firstOrCreate([
        'tenant_id' => null,
        'symbol' => 'kg',
    ], [
        'uom_category_id' => $globalCategory->id,
        'name' => 'Global Kilogram 29bc',
        'display_precision' => 3,
    ]);
    $item = ($this->makeItem)($tenant, $tenantGram, ['name' => 'Brown Rice']);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->updateOrCreate([
        'tenant_id' => null,
        'from_uom_id' => $globalKilogram->id,
        'to_uom_id' => $globalGram->id,
    ], [
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $tenantKilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    $line = ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $resolvedQuantity = app(InventoryBuyUomConversionResolver::class)->baseQuantityFor($option, '1.000000');
    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect((int) $item->base_uom_id)->toBe((int) $tenantGram->id)
        ->and($item->baseUom?->symbol)->toBe('g')
        ->and((int) $option->pack_uom_id)->toBe((int) $tenantKilogram->id)
        ->and($option->packUom?->symbol)->toBe('kg')
        ->and((int) $option->pack_uom_id)->not->toBe((int) $globalKilogram->id)
        ->and((int) $item->base_uom_id)->not->toBe((int) $globalGram->id)
        ->and(bcadd((string) $option->pack_quantity, '0', 6))->toBe('20.000000')
        ->and((int) $line->pack_count)->toBe(1)
        ->and((string) UomConversion::query()
            ->whereNull('tenant_id')
            ->where('from_uom_id', $globalKilogram->id)
            ->where('to_uom_id', $globalGram->id)
            ->value('multiplier'))->toBe('1000.00000000')
        ->and($resolvedQuantity)->toBe('20000.000000')
        ->and($row['buy'] ?? null)->toBe('20000.000000')
        ->and($row['buy_display'] ?? null)->toBe('20000.0');
});

it('29bd. inventory buy resolves global reverse conversions by symbol using reciprocal math', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $tenantCategory = UomCategory::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Mass 29bd',
    ]);
    $globalCategory = UomCategory::query()->create([
        'tenant_id' => null,
        'name' => 'Global Mass 29bd',
    ]);
    $tenantGram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'g')->firstOrFail();
    $tenantKilogram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'kg')->firstOrFail();
    $globalGram = Uom::query()->firstOrCreate([
        'tenant_id' => null,
        'symbol' => 'g',
    ], [
        'uom_category_id' => $globalCategory->id,
        'name' => 'Global Gram 29bd',
        'display_precision' => 0,
    ]);
    $globalKilogram = Uom::query()->firstOrCreate([
        'tenant_id' => null,
        'symbol' => 'kg',
    ], [
        'uom_category_id' => $globalCategory->id,
        'name' => 'Global Kilogram 29bd',
        'display_precision' => 3,
    ]);
    $item = ($this->makeItem)($tenant, $tenantGram, ['name' => 'Brown Rice Reverse']);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->updateOrCreate([
        'tenant_id' => null,
        'from_uom_id' => $globalGram->id,
        'to_uom_id' => $globalKilogram->id,
    ], [
        'multiplier' => '0.00100000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $tenantKilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    $line = ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $resolvedQuantity = app(InventoryBuyUomConversionResolver::class)->baseQuantityFor($option, '1.000000');
    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($item->baseUom?->symbol)->toBe('g')
        ->and($option->packUom?->symbol)->toBe('kg')
        ->and(bcadd((string) $option->pack_quantity, '0', 6))->toBe('20.000000')
        ->and((int) $line->pack_count)->toBe(1)
        ->and((string) UomConversion::query()
            ->whereNull('tenant_id')
            ->where('from_uom_id', $globalGram->id)
            ->where('to_uom_id', $globalKilogram->id)
            ->value('multiplier'))->toBe('0.00100000')
        ->and($resolvedQuantity)->toBe('20000.000000')
        ->and($row['buy'] ?? null)->toBe('20000.000000')
        ->and($row['buy_display'] ?? null)->toBe('20000.0');
});

it('29be. inventory buy resolves indirect generic conversion paths by symbol', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $category = UomCategory::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Mass 29be',
    ]);
    $gram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'g')->firstOrFail();
    $kilogram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'kg')->firstOrFail();
    $pound = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'lb')->firstOrFail();
    $item = ($this->makeItem)($tenant, $pound, ['name' => 'Brown Rice Pounds']);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);
    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $pound->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '453.59200000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $resolvedQuantity = app(InventoryBuyUomConversionResolver::class)->baseQuantityFor($option, '1.000000');
    $expectedFactor = bcmul(
        '1000.000000000000',
        bcdiv('1', '453.592000000000', 12),
        12
    );
    $expectedQuantity = bcmul('20.000000', $expectedFactor, 6);
    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($option->packUom?->symbol)->toBe('kg')
        ->and($item->baseUom?->symbol)->toBe('lb')
        ->and($resolvedQuantity)->toBe($expectedQuantity)
        ->and($row['buy'] ?? null)->toBe($expectedQuantity)
        ->and($row['net'] ?? null)->toBe($expectedQuantity);
});

it('29c. generic conversion beats item specific conversion for open purchase order buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'conversion_factor' => '900.000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('20000.000000');
});

it('29d. tenant general conversion is used when no item conversion exists for open purchase order buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '3.500000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 2);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('7000.000000');
});

it('29e. global conversion is used when no item or tenant conversion exists for open purchase order buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => null,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '2.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 4);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('8000.000000');
});

it('29ea. reciprocal conversion records use package uom to item base uom direction for buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $gram->id,
        'to_uom_id' => $kilogram->id,
        'multiplier' => '0.00100000',
    ]);
    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('20000.000000');
});

it('29f. missing package uom conversion excludes the open purchase order line safely', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $resolvedQuantity = app(InventoryBuyUomConversionResolver::class)->baseQuantityFor($option, '1.000000');
    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($resolvedQuantity)->toBeNull()
        ->and($row['buy'] ?? null)->toBe('0.000000');
});

it('29g. multiple open purchase order lines aggregate after package uom conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $gramOption = ($this->makePurchaseOption)($tenant, $supplier, $item, $gram, '500.000000');
    $kilogramOption = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '2.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $gramOption, 3);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $kilogramOption, 2);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('5500.000000');
});

it('29h. other tenant open purchase order lines are excluded before package uom conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $otherGram = ($this->makeUom)($otherTenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $otherKilogram = ($this->makeRelatedUom)($otherTenant, $otherGram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $otherItem = ($this->makeItem)($otherTenant, $otherGram, ['name' => $item->name]);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);
    UomConversion::query()->create([
        'tenant_id' => $otherTenant->id,
        'from_uom_id' => $otherKilogram->id,
        'to_uom_id' => $otherGram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '1.000000');
    $otherOption = ($this->makePurchaseOption)($otherTenant, $otherSupplier, $otherItem, $otherKilogram, '99.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    $otherPurchaseOrder = ($this->makePurchaseOrder)(
        $otherTenant,
        ($this->makeUser)($otherTenant),
        $otherSupplier,
        PurchaseOrder::STATUS_CREATED
    );
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->makePurchaseOrderLine)($otherTenant, $otherPurchaseOrder, $otherItem, $otherOption, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('1000.000000');
});

it('29i. completed and cancelled purchase orders are excluded from converted buy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $completedOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_COMPLETED);
    $cancelledOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CANCELLED);
    ($this->makePurchaseOrderLine)($tenant, $completedOrder, $item, $option, 1);
    ($this->makePurchaseOrderLine)($tenant, $cancelledOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('0.000000');
});

it('29ia. completed workflow purchase order buy is excluded even when legacy status is stale created', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $stages = ($this->makePurchasingWorkflowStages)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '20.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    $purchaseOrder->forceFill([
        'current_workflow_stage_id' => null,
        'last_completed_workflow_stage_id' => $stages['completing']->id,
    ])->save();
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($purchaseOrder->fresh()->workflowStatus())->toBe('COMPLETED')
        ->and($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_CREATED)
        ->and($row['buy'] ?? null)->toBe('0.000000');
});

it('29j. inventory net calculation uses converted open purchase order buy quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $item = ($this->makeItem)($tenant, $gram, ['is_sellable' => true]);
    $customer = ($this->makeCustomer)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    ($this->makeStockMove)($tenant, $item, '500.000000');
    $salesOrder = ($this->makeSalesOrder)($tenant, $customer, SalesOrder::STATUS_OPEN);
    ($this->makeSalesOrderLine)($tenant, $salesOrder, $item, '250.000000');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '1.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 2);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('2000.000000')
        ->and($row['net'] ?? null)->toBe('2250.000000');
});

it('29ja. rendered inventory index contract uses corrected buy display field', function (): void {
    $source = file_get_contents(resource_path('js/pages/inventory-index.js'));

    expect($source)->toContain("buy: 'buy_display'");
});

it('29k. inventory calculator preserves canonical scale for converted open purchase order buy', function (): void {
    $tenant = ($this->makeTenant)();
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $kilogram = ($this->makeRelatedUom)($tenant, $gram, 'Kilogram', 'kg', 3);
    $user = ($this->makeUser)($tenant);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $kilogram, '1.250000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);

    $availability = app(InventoryAvailabilityCalculator::class)->forItem($item);

    expect($availability['buy'] ?? null)->toBe('1250.000000');
});

it('29l. open purchase order buy falls back to item specific conversion when no generic conversion exists', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant, 'Gram', 'g', 0);
    $patty = ($this->makeUom)($tenant, 'Patty', 'patty', 0);
    $item = ($this->makeItem)($tenant, $gram);
    $supplier = ($this->makeSupplier)($tenant);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $patty->id,
        'to_uom_id' => $gram->id,
        'conversion_factor' => '113.000000',
    ]);

    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $patty, '40.000000');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, 1);
    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $row = ($this->inventoryRow)(
        ($this->inventoryList)($user)->assertOk()->json('data'),
        $item->id
    );

    expect($row['buy'] ?? null)->toBe('4520.000000');
});

it('29m. receiving conversion keeps its existing item specific first resolver separate from buy availability', function (): void {
    $source = file_get_contents(app_path('Actions/Inventory/ReceivePurchaseOptionAction.php'));

    expect($source)->toContain('UomConversionPathResolver::PRECEDENCE_ITEM_FIRST');
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
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, PurchaseOrder::STATUS_CREATED);
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
