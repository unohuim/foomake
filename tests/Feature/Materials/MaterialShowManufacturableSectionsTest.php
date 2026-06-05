<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\MakeOrder;
use App\Models\MakeOrderLine;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RecipeVersion;
use App\Models\Recipe;
use App\Models\RecipeVersionLine;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->recipeCounter = 1;
    $this->makeOrderCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::factory()->create(array_merge([
            'tenant_name' => $attributes['tenant_name'] ?? 'Tenant ' . $this->tenantCounter,
            'currency_code' => $attributes['currency_code'] ?? 'USD',
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email' => 'materials-manufacturable-' . $this->userCounter . '@example.test',
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'materials-manufacturable-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $suffix = 'msm-' . $this->uomCounter . '-' . Str::lower(Str::random(4));
        $symbol = $attributes['symbol'] ?? $suffix;

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
            'name' => $attributes['category_name'] ?? 'Category ' . $suffix,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $suffix,
            'symbol' => $symbol,
            'display_precision' => $attributes['display_precision'] ?? 1,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Material ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_stockable' => false,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeRecipe = function (Tenant $tenant, Item $item, array $attributes = []): Recipe {
        $recipe = Recipe::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => $attributes['name'] ?? 'Recipe ' . $this->recipeCounter,
            'output_quantity' => $attributes['output_quantity'] ?? '1.000000',
            'is_active' => $attributes['is_active'] ?? true,
            'is_default' => $attributes['is_default'] ?? false,
        ], $attributes));

        $this->recipeCounter++;

        return $recipe;
    };

    $this->makeRecipeVersion = function (Tenant $tenant, Recipe $recipe, array $attributes = []): RecipeVersion {
        return RecipeVersion::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'version_number' => $attributes['version_number'] ?? 100,
            'status' => $attributes['status'] ?? RecipeVersion::STATUS_DRAFT,
            'recipe_type' => $attributes['recipe_type'] ?? Recipe::TYPE_MANUFACTURING,
            'output_quantity' => $attributes['output_quantity'] ?? '1.000000',
        ], $attributes));
    };

    $this->publishRecipeVersion = function (Recipe $recipe, RecipeVersion $version): void {
        $version->status = RecipeVersion::STATUS_PUBLISHED;
        $version->save();
        $recipe->current_version_id = $version->id;
        $recipe->save();
    };

    $this->addRecipeVersionLine = function (Tenant $tenant, RecipeVersion $version, Item $inputItem, string $quantity): RecipeVersionLine {
        return RecipeVersionLine::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_version_id' => $version->id,
            'input_item_id' => $inputItem->id,
            'uom_id' => $inputItem->base_uom_id,
            'quantity' => $quantity,
            'sort_order' => 1,
        ]);
    };

    $this->makeMakeOrder = function (Tenant $tenant, Recipe $recipe, array $attributes = []): MakeOrder {
        $runs = (string) ($attributes['runs'] ?? $attributes['output_quantity'] ?? '2.000000');
        $recipeOutputQuantity = (string) ($recipe->currentVersion?->output_quantity ?? $recipe->output_quantity ?? '0.000000');

        $makeOrder = MakeOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $recipe->current_version_id,
            'output_item_id' => $recipe->item_id,
            'runs' => $runs,
            'expected_output_qty' => bcmul($runs, $recipeOutputQuantity, 6),
            'actual_output_qty' => $attributes['actual_output_qty'] ?? $attributes['actual_output_quantity'] ?? null,
            'status' => $attributes['status'] ?? MakeOrder::STATUS_DRAFT,
            'created_by_user_id' => $attributes['created_by_user_id'] ?? null,
            'made_by_user_id' => $attributes['made_by_user_id'] ?? null,
        ], $attributes));

        $this->makeOrderCounter++;

        return $makeOrder;
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        return Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . Str::random(6),
            'status' => Customer::STATUS_ACTIVE,
            'customer_type' => Customer::TYPE_BUSINESS,
        ], $attributes));
    };

    $this->makeSalesOrder = function (Tenant $tenant, Customer $customer, array $attributes = []): SalesOrder {
        return SalesOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_OPEN,
        ], $attributes));
    };

    $this->makeSalesOrderLine = function (Tenant $tenant, SalesOrder $order, Item $item, string $quantity): SalesOrderLine {
        return SalesOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price_cents' => 100,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '100.000000',
        ]);
    };

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        return Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . Str::random(6),
        ], $attributes));
    };

    $this->makePurchaseOption = function (Tenant $tenant, Item $item, Uom $uom, Supplier $supplier, array $attributes = []): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'supplier_id' => $supplier->id,
            'pack_uom_id' => $uom->id,
            'pack_quantity' => '1.000000',
            'is_active' => true,
        ], $attributes));
    };

    $this->makePurchaseOrder = function (Tenant $tenant, Supplier $supplier, array $attributes = []): PurchaseOrder {
        return PurchaseOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_CREATED,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ], $attributes));
    };

    $this->makePurchaseOrderLine = function (
        Tenant $tenant,
        PurchaseOrder $purchaseOrder,
        Item $item,
        ItemPurchaseOption $purchaseOption,
        int $packCount = 1
    ): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $purchaseOption->id,
            'pack_count' => $packCount,
            'unit_price_cents' => 100,
            'line_subtotal_cents' => 100,
            'unit_price_amount' => 100,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 100,
            'fx_rate' => '1.000000',
            'fx_rate_as_of' => now()->toDateString(),
        ]);
    };

    $this->makeStockMove = function (Tenant $tenant, Item $item, string $quantity): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => $quantity,
            'type' => 'inventory_count_adjustment',
            'status' => 'POSTED',
        ]);
    };

    $this->makeInventoryCount = function (Tenant $tenant, User $assignedUser, array $attributes = []): InventoryCount {
        return InventoryCount::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Material section count',
            'created_by_user_id' => $assignedUser->id,
            'tasked_by_user_id' => $assignedUser->id,
            'assigned_to_user_id' => $assignedUser->id,
            'counted_at' => now()->subDay(),
            'notes' => null,
        ], $attributes));
    };

    $this->makeInventoryCountLine = function (
        Tenant $tenant,
        InventoryCount $count,
        Item $item,
        string $countedQuantity = '1.000000',
        array $attributes = []
    ): InventoryCountLine {
        return InventoryCountLine::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'inventory_count_id' => $count->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'counted_quantity' => $countedQuantity,
            'notes' => null,
        ], $attributes));
    };

    $this->getShow = function (?User $user, Item $item) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('materials.show', $item));
    };

    $this->getRecipesIndex = function (User $user, array $query = []) {
        return $this->actingAs($user)->get(route('manufacturing.recipes.index', $query));
    };

    $this->getMakeOrdersIndex = function (User $user, array $query = []) {
        return $this->actingAs($user)->get(route('manufacturing.make-orders.index', $query));
    };

    $this->postRecipe = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('manufacturing.recipes.store'), $payload);
    };

    $this->postMakeOrder = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('manufacturing.make-orders.store'), $payload);
    };

    $this->postMaterialInventoryCount = function (User $user, Item $item, array $payload = []) {
        return $this->actingAs($user)->postJson(route('materials.inventory-counts.store', $item), $payload);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        if (! array_key_exists(1, $matches)) {
            return [];
        }

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractMaterialPayload = function ($response): array {
        return ($this->extractPayload)($response, 'materials-show-payload');
    };

    $this->extractSection = function ($response, string $sectionKey): array {
        $payload = ($this->extractMaterialPayload)($response);
        $section = $payload['sections'][$sectionKey] ?? null;

        return is_array($section) ? $section : [];
    };

    $this->extractInventoryStats = function ($response): array {
        $payload = ($this->extractMaterialPayload)($response);
        $stats = $payload['inventoryStats'] ?? null;

        return is_array($stats) ? $stats : [];
    };

    $this->getSectionList = function (User $user, array $sectionConfig) {
        $endpoint = $sectionConfig['endpoints']['list'] ?? null;

        expect(is_string($endpoint))->toBeTrue();

        return $this->actingAs($user)->getJson($endpoint);
    };
});

it('1. redirects guests to login for the material detail page', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->getShow)(null, $item)->assertRedirect(route('login'));
});

it('2. forbids material detail access without inventory materials view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->getShow)($user, $item)->assertForbidden();
});

it('3. allows authorized users to access the material detail with the manufacturable sections', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Manufacturable Material',
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-view',
    ]);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Manufacturable Material');
});

it('4. manufacturable material shows the recipes section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes'))->not->toBe([]);
});

it('5. manufacturable material shows the make orders section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders'))->not->toBe([]);
});

it('6. non manufacturable material hides the recipes section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes'))->toBe([]);
});

it('7. non manufacturable material hides the make orders section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders'))->toBe([]);
});

it('8. supplier packages section is configured near the top and default open', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
    ]);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'supplierPackages');

    expect($section['defaultOpen'] ?? null)->toBeTrue();

    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $supplierPackagesPosition = strpos($viewSource, 'data-section-key="supplierPackages"');
    $purchaseOrdersPosition = strpos($viewSource, 'data-section-key="purchaseOrders"');

    expect($supplierPackagesPosition)->not->toBeFalse()
        ->and($purchaseOrdersPosition)->not->toBeFalse()
        ->and($supplierPackagesPosition)->toBeLessThan($purchaseOrdersPosition);
});

it('9. recipes section is configured near the top and default open', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'recipes');

    expect($section['defaultOpen'] ?? null)->toBeTrue();

    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $recipesPosition = strpos($viewSource, 'data-section-key="recipes"');
    $purchaseOrdersPosition = strpos($viewSource, 'data-section-key="purchaseOrders"');

    expect($recipesPosition)->not->toBeFalse()
        ->and($purchaseOrdersPosition)->not->toBeFalse()
        ->and($recipesPosition)->toBeLessThan($purchaseOrdersPosition);
});

it('10. purchase orders section is configured near the bottom and default collapsed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'purchaseOrders');

    expect($section['defaultOpen'] ?? null)->toBeFalse();
});

it('11. make orders section is configured near the bottom and default collapsed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'makeOrders');

    expect($section['defaultOpen'] ?? null)->toBeFalse();

    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $makeOrdersPosition = strpos($viewSource, 'data-section-key="makeOrders"');
    $recipesPosition = strpos($viewSource, 'data-section-key="recipes"');

    expect($makeOrdersPosition)->not->toBeFalse()
        ->and($recipesPosition)->not->toBeFalse()
        ->and($makeOrdersPosition)->toBeGreaterThan($recipesPosition);
});

it('12. recipes section uses data js crud section root', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('data-js-crud-section-root', false)
        ->assertSee('data-section-key="recipes"', false);
});

it('13. make orders section uses data js crud section root', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('data-js-crud-section-root', false)
        ->assertSee('data-section-key="makeOrders"', false);
});

it('14. recipes section uses a stable recipes section key', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes')['resource'] ?? null)
        ->toBe('material-recipes');
});

it('15. make orders section uses a stable makeOrders section key', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders')['resource'] ?? null)
        ->toBe('material-make-orders');
});

it('16. no new bespoke accordion or section abstraction is introduced', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));
    $sectionSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($viewSource)->toContain('data-js-crud-section-root')
        ->and($pageSource)->toContain('mountCrudSection')
        ->and($sectionSource)->toContain('data-js-crud-section-card')
        ->and($pageSource)->not->toContain('mountRecipesAccordion')
        ->and($pageSource)->not->toContain('mountMakeOrdersAccordion');
});

it('16a. material detail header renders all material type toggle buttons', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($viewSource)->toContain('data-material-type-toggles')
        ->and($viewSource)->toContain('<x-slot name="titleSuffix">')
        ->and($viewSource)->toContain('data-material-name-editor')
        ->and($viewSource)->toContain('x-data="materialTypeToggles"')
        ->and($viewSource)->toContain('data-material-base-uom-dropdown')
        ->and($viewSource)->toContain('sm:justify-start')
        ->and($viewSource)->toContain('sm:ml-8')
        ->and($viewSource)->toContain('materialTypeToggles')
        ->and($viewSource)->toContain("typeToggle.icon === 'shopping-cart'")
        ->and($viewSource)->toContain("typeToggle.icon === 'credit-card'")
        ->and($viewSource)->toContain("typeToggle.icon === 'cog'")
        ->and($viewSource)->toContain("typeToggle.icon === 'rectangle-group'")
        ->and($viewSource)->toContain('rounded-full border-2 bg-white')
        ->and($viewSource)->toContain("'border-blue-600 text-blue-600 hover:border-blue-500 hover:text-blue-500'")
        ->and($viewSource)->toContain("'border-gray-300 text-gray-300 hover:border-blue-600 hover:text-blue-600'")
        ->and($viewSource)->toContain('h-[1.125rem] w-[1.125rem]')
        ->and($viewSource)->toContain('stroke-width="2"')
        ->and($viewSource)->toContain('<x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />')
        ->and($viewSource)->not->toContain('materialTypeToast.message');
});

it('16b. material detail payload exposes type toggle state and update authorization', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_stockable' => true,
        'is_sellable' => true,
        'is_purchasable' => false,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-materials-manage',
        'inventory-recipes-view',
        'inventory-adjustments-view',
    ]);

    $payload = ($this->extractPayload)(($this->getShow)($user, $item), 'materials-show-payload');

    expect(data_get($payload, 'item.base_uom_id'))->toBe($uom->id)
        ->and(data_get($payload, 'item.is_stockable'))->toBeTrue()
        ->and(data_get($payload, 'item.is_sellable'))->toBeTrue()
        ->and(data_get($payload, 'item.is_purchasable'))->toBeFalse()
        ->and(data_get($payload, 'item.is_manufacturable'))->toBeTrue()
        ->and(data_get($payload, 'item.can_manage'))->toBeTrue()
        ->and(data_get($payload, 'item.update_url'))->toBe(route('materials.update', $item))
        ->and(data_get($payload, 'item.csrf_token'))->not->toBeEmpty()
        ->and(data_get($payload, 'sections.supplierPackages'))->toBeNull()
        ->and(data_get($payload, 'sections.recipes'))->not->toBeNull()
        ->and(data_get($payload, 'sections.inventoryCounts'))->not->toBeNull();
});

it('16c. material detail page module toggles material type flags through ajax', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($pageSource)->toContain('materialTypeToggles')
        ->and($pageSource)->toContain('const materialTypeState = () => ({')
        ->and($pageSource)->toContain("Alpine.data('materialTypeToggles', materialTypeState)")
        ->and($pageSource)->toContain("{ field: 'is_sellable', label: 'Sellable', icon: 'shopping-cart' }")
        ->and($pageSource)->toContain("{ field: 'is_purchasable', label: 'Purchasable', icon: 'credit-card' }")
        ->and($pageSource)->toContain("{ field: 'is_manufacturable', label: 'Makeable', icon: 'cog' }")
        ->and($pageSource)->toContain("{ field: 'is_stockable', label: 'Stockable', icon: 'rectangle-group' }")
        ->and($pageSource)->toContain('async toggleMaterialType(field)')
        ->and($pageSource)->toContain("pageState?.showToast('success'")
        ->and($pageSource)->not->toContain('showMaterialTypeToast(message)')
        ->and($pageSource)->not->toContain('this.showMaterialTypeToast')
        ->and($pageSource)->toContain("method: 'PATCH'")
        ->and($pageSource)->toContain('[field]: nextValue')
        ->and($pageSource)->toContain('syncMaterialDetailPayload(materialDetail)')
        ->and($pageSource)->toContain("document.querySelector('[data-resource-detail-header-title]')")
        ->and($pageSource)->toContain("document.querySelector('[data-resource-detail-breadcrumb] [aria-current=\"page\"]')")
        ->and($pageSource)->toContain("pageState?.showToast('success', 'Material name updated.')")
        ->and($pageSource)->toContain('mountMaterialSection(sectionKey, sectionConfig, true)')
        ->and($pageSource)->toContain('Alpine.initTree(sectionRootEl)')
        ->and($pageSource)->toContain("sectionRootEl.innerHTML = '';")
        ->and($pageSource)->toContain('inventoryStatsEl.hidden = !nextPayload.inventoryStats')
        ->and($pageSource)->toContain('this.materialTypes[field] = previousValue;');
});

it('16d. material detail always renders dependent section mount roots for live toggle updates', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($viewSource)->toContain('data-section-key="supplierPackages"')
        ->and($viewSource)->toContain('data-section-key="recipes"')
        ->and($viewSource)->toContain('data-section-key="inventoryCounts"')
        ->and($viewSource)->toContain('data-section-key="purchaseOrders"')
        ->and($viewSource)->toContain('data-section-key="makeOrders"')
        ->and($viewSource)->not->toContain('@if (($payload[\'sections\'][\'supplierPackages\'] ?? null))')
        ->and($viewSource)->not->toContain('@if (($payload[\'sections\'][\'recipes\'] ?? null))');
});

it('17. existing material detail sections still render when applicable', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
        'inventory-recipes-view',
        'inventory-make-orders-view',
    ]);

    $payload = ($this->extractMaterialPayload)(($this->getShow)($user, $item));

    expect($payload['sections']['supplierPackages'] ?? null)->toBeArray()
        ->and($payload['sections']['recipes'] ?? null)->toBeArray()
        ->and($payload['sections']['purchaseOrders'] ?? null)->toBeArray()
        ->and($payload['sections']['makeOrders'] ?? null)->toBeArray();
});

it('18. recipes section lists only recipes whose output item matches the current material', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material, ['name' => 'Sauce Recipe']);
    ($this->makeRecipe)($tenant, $otherMaterial, ['name' => 'Other Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$recipe->id]);
});

it('19. recipes section excludes recipes for other output materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $otherMaterial, ['name' => 'Other Output Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->not->toContain('Other Output Recipe');
});

it('20. recipes section is tenant scoped', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $material, ['name' => 'Current Tenant Recipe']);
    $otherMaterial = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($otherTenant, $otherMaterial, ['name' => 'Other Tenant Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toContain('Current Tenant Recipe')
        ->not->toContain('Other Tenant Recipe');
});

it('21. recipes section shows an empty state when no matching recipes exist', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $section = ($this->extractSection)(($this->getShow)($user, $material), 'recipes');
    ($this->getSectionList)($user, $section)->assertOk()->assertJsonCount(0, 'data');

    expect($section['emptyState'] ?? null)->toBe('No recipes produce this material yet.');
});

it('22. recipes section has a plus create action when the user can manage recipes', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');

    expect($section['permissions']['canCreate'] ?? null)->toBeTrue()
        ->and($section['createAction']['type'] ?? null)->toBe('custom')
        ->and($section['createAction']['handlerKey'] ?? null)->toBe('openRecipeCreate');
});

it('23. recipe create action reuses the existing recipe create slide over in place', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $recipesViewSource = file_get_contents(resource_path('views/manufacturing/recipes/index.blade.php'));
    $materialsViewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($section['createAction']['type'] ?? null)->toBe('custom')
        ->and($section['createAction']['handlerKey'] ?? null)->toBe('openRecipeCreate')
        ->and(array_key_exists('url', $section['createAction'] ?? []))->toBeFalse()
        ->and($recipesViewSource)->toContain("@include('manufacturing.recipes.partials.create-recipe-slide-over')")
        ->and($materialsViewSource)->toContain("@include('manufacturing.recipes.partials.create-recipe-slide-over')");
});

it('24. recipe create action preselects the current material as the output item without leaving the detail page', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($section['createAction']['prefill']['itemId'] ?? null)->toBe($item->id)
        ->and($pageSource)->toContain('openRecipeCreate');
});

it('24a. recipe create success redirects to the created recipe detail view after in place create', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $response = ($this->postRecipe)($user, [
        'item_id' => $item->id,
        'recipe_type' => Recipe::TYPE_MANUFACTURING,
        'name' => 'Redirect Recipe',
        'output_quantity' => '1.000000',
        'is_active' => true,
    ])->assertCreated();

    $recipeId = $response->json('data.id');

    expect($response->json('data.show_url'))->toBe(route('manufacturing.recipes.show', $recipeId))
        ->and($pageSource)->toContain("window.location.assign(data.data.show_url);");
});

it('25. material detail recipe row menu includes make when the recipe has a current published version', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item);
    ($this->publishRecipeVersion)($recipe, ($this->makeRecipeVersion)($tenant, $recipe, [
        'status' => RecipeVersion::STATUS_PUBLISHED,
    ]));

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-execute',
    ]);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $item), 'recipes')
    )->assertOk();

    expect($response->json('data.0.id'))->toBe($recipe->id)
        ->and($response->json('data.0.available_actions'))->toContain('make')
        ->and($response->json('data.0.make_url'))->toBe(route('manufacturing.recipes.make-orders.store', $recipe));
});

it('26. material detail recipe make action creates a make order directly and returns the created detail url', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, [
        'status' => RecipeVersion::STATUS_PUBLISHED,
        'output_quantity' => '4.000000',
    ]);
    ($this->publishRecipeVersion)($recipe, $version);
    $input = ($this->makeItem)($tenant, $uom, ['name' => 'Salt']);
    ($this->addRecipeVersionLine)($tenant, $version, $input, '1.500000');

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-execute',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
            'runs' => '2.000000',
        ])
        ->assertCreated();

    $makeOrderId = (int) $response->json('data.id');

    expect($response->json('data.recipe_id'))->toBe($recipe->id)
        ->and($response->json('data.recipe_version_id'))->toBe($version->id)
        ->and($response->json('data.show_url'))->toBe(route('manufacturing.make-orders.show', $makeOrderId));

    expect(DB::table('make_order_lines')->where('make_order_id', $makeOrderId)->count())->toBe(1)
        ->and(bcadd((string) DB::table('make_order_lines')->where('make_order_id', $makeOrderId)->value('planned_quantity'), '0', 6))->toBe('3.000000');
});

it('26aa. material detail recipe make action uses direct create and redirect instead of opening the make order slide over', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($pageSource)->toContain("await pageState?.createMakeOrderFromUrl(record.make_url || '');")
        ->and($pageSource)->not->toContain("action.handlerKey === 'createMakeOrder') {\n                    pageState?.openMakeOrderCreate");
});

it('26a. material detail recipe make is hidden when the recipe has no current published version', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-execute',
    ]);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $item), 'recipes')
    )->assertOk();

    expect($response->json('data.0.available_actions'))->not->toContain('make')
        ->and(array_key_exists('make_url', $response->json('data.0') ?? []))->toBeFalse();

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
            'runs' => '2.000000',
        ])
        ->assertStatus(422);
});

it('26b. material detail recipe rows show published status and current version badges instead of the old active badge', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2]);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['name' => 'Badge Recipe']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, [
        'status' => RecipeVersion::STATUS_PUBLISHED,
        'version_number' => 102,
    ]);
    ($this->publishRecipeVersion)($recipe, $version);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $response = ($this->getSectionList)($user, $section)->assertOk();

    expect($section['rowLayout']['badges'][0]['field'] ?? null)->toBe('display.statusText')
        ->and($section['rowLayout']['badges'][1]['field'] ?? null)->toBe('display.versionText')
        ->and($response->json('data.0.version_status'))->toBe(RecipeVersion::STATUS_PUBLISHED)
        ->and($response->json('data.0.current_version_number_display'))->toBe('1.02');

    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));
    $recipeStateBlockStart = strpos($pageSource, 'const recipeStateDisplay = (record) => {');
    $makeOrderStatusBlockStart = strpos($pageSource, 'const makeOrderStatusDisplay = (record) => {');

    expect($recipeStateBlockStart)->not->toBeFalse()
        ->and($makeOrderStatusBlockStart)->not->toBeFalse();

    $recipeStateBlock = substr($pageSource, $recipeStateBlockStart, $makeOrderStatusBlockStart - $recipeStateBlockStart);

    expect($pageSource)->toContain('versionText')
        ->and($recipeStateBlock)->not->toContain("text: 'Active'");
});

it('26c. material detail recipe rows keep the vertical dots menu by exposing view as an available action on every row', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $item, ['name' => 'View Only Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $response = ($this->getSectionList)($user, $section)->assertOk();

    expect($response->json('data.0.available_actions'))->toContain('view')
        ->and($section['actions'][0]['id'] ?? null)->toBe('view');

    $sharedSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($sharedSource)->toContain('visibleActions(record).length > 0')
        ->and($sharedSource)->toContain('stroke-linecap="round"')
        ->and($sharedSource)->toContain('M12 6.75');
});

it('27. make orders section lists only make orders whose recipe outputs this material', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);
    $otherRecipe = ($this->makeRecipe)($tenant, $otherMaterial);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe);
    ($this->makeMakeOrder)($tenant, $otherRecipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$makeOrder->id]);
});

it('28. make orders section excludes make orders for recipes producing other materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherRecipe = ($this->makeRecipe)($tenant, $otherMaterial, ['name' => 'Other Material Recipe']);
    ($this->makeMakeOrder)($tenant, $otherRecipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('recipe_name')->all())->not->toContain('Other Material Recipe');
});

it('29. make orders section is tenant scoped', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material, ['name' => 'Tenant Recipe']);
    ($this->makeMakeOrder)($tenant, $recipe);
    $otherMaterial = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true]);
    $otherRecipe = ($this->makeRecipe)($otherTenant, $otherMaterial, ['name' => 'Other Tenant Recipe']);
    ($this->makeMakeOrder)($otherTenant, $otherRecipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('recipe_name')->all())
        ->toContain('Tenant Recipe')
        ->not->toContain('Other Tenant Recipe');
});

it('30. make orders section shows an empty state when no matching make orders exist', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $section = ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders');
    ($this->getSectionList)($user, $section)->assertOk()->assertJsonCount(0, 'data');

    expect($section['emptyState'] ?? null)->toBe('No make orders produce this material yet.');
});

it('31. make order rows link to the minimal read only make order detail view', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect($response->json('data.0.show_url'))->toBe(route('manufacturing.make-orders.show', $makeOrder));

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.show', $makeOrder))
        ->assertOk();
});

it('31a. recipe rows expose tenant scoped recipe detail links', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect($response->json('data.0.show_url'))->toBe(route('manufacturing.recipes.show', $recipe));
});

it('31b. recipe detail links do not expose cross tenant records through the material detail section list', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $material, ['name' => 'Visible Recipe']);
    $otherMaterial = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true]);
    $otherRecipe = ($this->makeRecipe)($otherTenant, $otherMaterial, ['name' => 'Hidden Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('show_url')->filter()->all())
        ->not->toContain(route('manufacturing.recipes.show', $otherRecipe));
});

it('32. make order rows show total output quantity as runs multiplied by recipe output quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2]);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material, ['output_quantity' => '1.250000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, [
        'status' => RecipeVersion::STATUS_PUBLISHED,
        'output_quantity' => '1.250000',
    ]);
    ($this->publishRecipeVersion)($recipe, $version);
    ($this->makeMakeOrder)($tenant, $recipe, ['runs' => '2.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect($response->json('data.0.total_output_quantity'))->toBe('2.500000')
        ->and($response->json('data.0.total_output_quantity_display'))->toBe('2.50');
});

it('33. make order runs display with zero decimals', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);
    ($this->makeMakeOrder)($tenant, $recipe, ['runs' => '3.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect($response->json('data.0.runs_display'))->toBe('3');
});

it('34. recipes section visibility is gated by inventory recipes view', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes'))->toBe([]);
});

it('35. make orders section visibility is gated by inventory make orders view', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders'))->toBe([]);
});

it('35a. non stockable material detail hides the inventory counts section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-adjustments-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'inventoryCounts'))->toBe([]);
});

it('35aa. non stockable material detail hides the inventory stats strip', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $response = ($this->getShow)($user, $item)->assertOk();

    expect(($this->extractInventoryStats)($response))->toBe([])
        ->and($response->getContent())->not->toContain('data-material-inventory-stats');
});

it('35ab. stockable material detail shows the inventory stats strip under the header', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $content = $response->getContent();

    expect(($this->extractInventoryStats)($response)['cards'] ?? null)->toBeArray()
        ->and($content)->toContain('data-material-inventory-stats')
        ->and(strpos($content, 'data-material-inventory-stats'))->toBeGreaterThan(
            strpos($content, 'data-resource-detail-header')
        );
});

it('35ac. stockable material inventory stats always show on hand and net qty cards', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $cards = collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? []);

    expect($cards->pluck('label')->all())->toContain('On hand')
        ->and($cards->pluck('label')->all())->toContain('Net Qty');
});

it('35aca. material inventory stats use compact mobile labels, grouped quantities, and even desktop columns', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_stockable' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);
    ($this->makeStockMove)($tenant, $item, '1234.500000');
    $customer = ($this->makeCustomer)($tenant);
    $salesOrder = ($this->makeSalesOrder)($tenant, $customer);
    ($this->makeSalesOrderLine)($tenant, $salesOrder, $item, '1000.000000');
    $supplier = ($this->makeSupplier)($tenant);
    $purchaseOption = ($this->makePurchaseOption)($tenant, $item, $uom, $supplier, ['pack_quantity' => '1000.000000']);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $supplier);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $purchaseOption, 1);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '1000.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, [
        'status' => RecipeVersion::STATUS_PUBLISHED,
        'output_quantity' => '1000.000000',
    ]);
    ($this->publishRecipeVersion)($recipe, $version);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '1.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $cards = collect(($this->extractInventoryStats)($response)['cards'] ?? []);
    $content = $response->getContent();

    expect($cards->firstWhere('key', 'on_hand')['quantity_display_grouped'] ?? null)->toBe('1,234.5')
        ->and($cards->firstWhere('key', 'on_hand')['compact_label'] ?? null)->toBe('Hand')
        ->and($cards->firstWhere('key', 'open_sales')['compact_label'] ?? null)->toBe('SO')
        ->and($cards->firstWhere('key', 'open_purchase')['compact_label'] ?? null)->toBe('PO')
        ->and($cards->firstWhere('key', 'open_make')['compact_label'] ?? null)->toBe('MO')
        ->and($cards->firstWhere('key', 'net')['compact_label'] ?? null)->toBe('Net')
        ->and($cards->firstWhere('key', 'open_sales')['mobile_label'] ?? null)->toBe('SO Qty')
        ->and($cards->firstWhere('key', 'open_purchase')['mobile_label'] ?? null)->toBe('PO Qty')
        ->and($cards->firstWhere('key', 'open_make')['mobile_label'] ?? null)->toBe('MO Qty')
        ->and($content)->toContain('space-y-0 px-1 pb-8 sm:space-y-6 sm:px-6 sm:py-12')
        ->and($content)->toContain('-mx-1 overflow-hidden border-y border-slate-200 bg-white shadow-sm sm:mx-0 sm:rounded-2xl sm:border')
        ->and($content)->toContain('role="tablist"')
        ->and($content)->toContain('aria-label="Inventory stats"')
        ->and($content)->toContain('x-data="materialInventoryStats"')
        ->and($content)->toContain('activeStat ===')
        ->and($content)->toContain('-rotate-90 whitespace-nowrap')
        ->and($content)->toContain('flex h-full items-center justify-between')
        ->and($content)->toContain('px-2 py-2 sm:px-6 sm:py-5')
        ->and($content)->toContain('text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl')
        ->and($content)->toContain('desktopGridClass()')
        ->and($content)->toContain('hidden truncate whitespace-nowrap sm:block')
        ->and($content)->not->toContain('lg:grid-cols-5')
        ->and($content)->toContain('SO Qty')
        ->and($content)->toContain('PO Qty')
        ->and($content)->toContain('MO Qty');
});

it('35ad. sellable stockable material shows an open sales orders qty card', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_sellable' => true]);
    $customer = ($this->makeCustomer)($tenant);
    $order = ($this->makeSalesOrder)($tenant, $customer, ['status' => SalesOrder::STATUS_OPEN]);
    ($this->makeSalesOrderLine)($tenant, $order, $item, '2.500000');

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $cards = collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? []);

    expect($cards->pluck('label')->all())->toContain('Open SO')
        ->and($cards->firstWhere('key', 'open_sales')['quantity'])->toBe('2.500000');
});

it('35ae. non sellable stockable material does not show a sales orders card', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_sellable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? [])->pluck('label')->all())
        ->not->toContain('Open SO');
});

it('35af. purchasable stockable material shows an open purchase orders qty card', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_purchasable' => true]);
    $supplier = ($this->makeSupplier)($tenant);
    $purchaseOption = ($this->makePurchaseOption)($tenant, $item, $uom, $supplier, ['pack_quantity' => '2.000000']);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $purchaseOption, 3);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $cards = collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? []);

    expect($cards->pluck('label')->all())->toContain('Open PO')
        ->and($cards->firstWhere('key', 'open_purchase')['quantity'])->toBe('6.000000');
});

it('35ag. non purchasable stockable material does not show a purchase orders card', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_purchasable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? [])->pluck('label')->all())
        ->not->toContain('Open PO');
});

it('35ah. manufacturable stockable material shows an open make orders impact card', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '3.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '3.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '2.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $cards = collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? []);

    expect($cards->pluck('label')->all())->toContain('Open MO')
        ->and($cards->firstWhere('key', 'open_make')['quantity'])->toBe('6.000000');
});

it('35ai. non manufacturable item with no open make-order relevance does not show a make-order card', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? [])->pluck('label')->all())
        ->not->toContain('Open MO');
});

it('35aj. multi flag stockable material shows all qualifying inventory stats cards', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_stockable' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);
    $customer = ($this->makeCustomer)($tenant);
    $salesOrder = ($this->makeSalesOrder)($tenant, $customer);
    ($this->makeSalesOrderLine)($tenant, $salesOrder, $item, '1.000000');
    $supplier = ($this->makeSupplier)($tenant);
    $purchaseOption = ($this->makePurchaseOption)($tenant, $item, $uom, $supplier, ['pack_quantity' => '2.000000']);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $supplier);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $purchaseOption, 1);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '2.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '2.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '1.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(collect(($this->extractInventoryStats)(($this->getShow)($user, $item))['cards'] ?? [])->pluck('label')->all())
        ->toContain('On hand')
        ->toContain('Net Qty')
        ->toContain('Open SO')
        ->toContain('Open PO')
        ->toContain('Open MO');
});

it('35ak. inventory stats net qty equals on hand minus open sales plus open purchase', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_sellable' => true, 'is_purchasable' => true]);
    ($this->makeStockMove)($tenant, $item, '10.000000');
    $customer = ($this->makeCustomer)($tenant);
    $salesOrder = ($this->makeSalesOrder)($tenant, $customer);
    ($this->makeSalesOrderLine)($tenant, $salesOrder, $item, '2.500000');
    $supplier = ($this->makeSupplier)($tenant);
    $purchaseOption = ($this->makePurchaseOption)($tenant, $item, $uom, $supplier, ['pack_quantity' => '2.000000']);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $supplier);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $purchaseOption, 3);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $stats = ($this->extractInventoryStats)(($this->getShow)($user, $item));

    expect($stats['net_quantity'] ?? null)->toBe('13.500000')
        ->and(collect($stats['cards'] ?? [])->firstWhere('key', 'net')['quantity_display'])->toBe('13.5');
});

it('35al. open make-order output quantity adds to material net qty', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '4.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '4.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '2.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractInventoryStats)(($this->getShow)($user, $item))['net_quantity'] ?? null)->toBe('8.000000');
});

it('35am. open make-order ingredient quantity subtracts from material net qty', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);
    $outputItem = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $outputItem, ['output_quantity' => '1.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '1.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '1.000000']);
    MakeOrderLine::query()->create([
        'tenant_id' => $tenant->id,
        'make_order_id' => $makeOrder->id,
        'input_item_id' => $item->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '5.500000',
        'line_type' => MakeOrderLine::TYPE_RECIPE,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    $stats = ($this->extractInventoryStats)(($this->getShow)($user, $item));

    expect($stats['net_quantity'] ?? null)->toBe('-5.500000')
        ->and(collect($stats['cards'] ?? [])->firstWhere('key', 'open_make')['quantity'])->toBe('-5.500000');
});

it('35an. mixed make-order input and output impact calculates correctly', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => true]);

    $outputRecipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '3.000000']);
    $outputVersion = ($this->makeRecipeVersion)($tenant, $outputRecipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '3.000000']);
    ($this->publishRecipeVersion)($outputRecipe, $outputVersion);
    ($this->makeMakeOrder)($tenant, $outputRecipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '2.000000']);

    $otherOutputItem = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $inputRecipe = ($this->makeRecipe)($tenant, $otherOutputItem, ['output_quantity' => '1.000000']);
    $inputVersion = ($this->makeRecipeVersion)($tenant, $inputRecipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '1.000000']);
    ($this->publishRecipeVersion)($inputRecipe, $inputVersion);
    $inputMakeOrder = ($this->makeMakeOrder)($tenant, $inputRecipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '1.000000']);
    MakeOrderLine::query()->create([
        'tenant_id' => $tenant->id,
        'make_order_id' => $inputMakeOrder->id,
        'input_item_id' => $item->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '1.500000',
        'line_type' => MakeOrderLine::TYPE_RECIPE,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractInventoryStats)(($this->getShow)($user, $item))['net_quantity'] ?? null)->toBe('4.500000');
});

it('35ao. completed and cancelled records are excluded from material inventory stats and tenant isolation is preserved', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $otherUom = ($this->makeUom)($otherTenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_stockable' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom, [
        'is_stockable' => true,
        'name' => $item->name,
    ]);
    ($this->makeStockMove)($tenant, $item, '5.000000');
    ($this->makeStockMove)($otherTenant, $otherItem, '99.000000');

    $customer = ($this->makeCustomer)($tenant);
    ($this->makeSalesOrderLine)(
        $tenant,
        ($this->makeSalesOrder)($tenant, $customer, ['status' => SalesOrder::STATUS_OPEN]),
        $item,
        '1.000000'
    );
    ($this->makeSalesOrderLine)(
        $tenant,
        ($this->makeSalesOrder)($tenant, $customer, ['status' => SalesOrder::STATUS_COMPLETED]),
        $item,
        '8.000000'
    );
    ($this->makeSalesOrderLine)(
        $tenant,
        ($this->makeSalesOrder)($tenant, $customer, ['status' => SalesOrder::STATUS_CANCELLED]),
        $item,
        '9.000000'
    );

    $supplier = ($this->makeSupplier)($tenant);
    $purchaseOption = ($this->makePurchaseOption)($tenant, $item, $uom, $supplier, ['pack_quantity' => '2.000000']);
    ($this->makePurchaseOrderLine)(
        $tenant,
        ($this->makePurchaseOrder)($tenant, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]),
        $item,
        $purchaseOption,
        2
    );
    ($this->makePurchaseOrderLine)(
        $tenant,
        ($this->makePurchaseOrder)($tenant, $supplier, ['status' => PurchaseOrder::STATUS_RECEIVED]),
        $item,
        $purchaseOption,
        10
    );
    ($this->makePurchaseOrderLine)(
        $tenant,
        tap(($this->makePurchaseOrder)($tenant, $supplier, ['status' => PurchaseOrder::STATUS_CREATED]), function ($order): void {
            $order->forceFill(['cancelled_at' => now()])->save();
        }),
        $item,
        $purchaseOption,
        11
    );

    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '2.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '2.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_DRAFT, 'runs' => '2.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_MADE, 'runs' => '9.000000']);
    ($this->makeMakeOrder)($tenant, $recipe, ['status' => MakeOrder::STATUS_CANCELLED, 'runs' => '10.000000']);

    $otherCustomer = ($this->makeCustomer)($otherTenant);
    ($this->makeSalesOrderLine)(
        $otherTenant,
        ($this->makeSalesOrder)($otherTenant, $otherCustomer, ['status' => SalesOrder::STATUS_OPEN]),
        $otherItem,
        '50.000000'
    );

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractInventoryStats)(($this->getShow)($user, $item))['net_quantity'] ?? null)->toBe('32.000000');
});

it('35ap. material inventory stats use actual make-order output when a made order stores it', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '10.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'runs' => '3.000000',
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-execute']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder), [
            'actual_output_qty' => '27.125000',
        ])
        ->assertOk();

    $stats = ($this->extractInventoryStats)(($this->getShow)($user, $item));

    expect($stats['on_hand_quantity'] ?? null)->toBe('27.125000')
        ->and($stats['net_quantity'] ?? null)->toBe('27.125000')
        ->and(collect($stats['cards'] ?? [])->firstWhere('key', 'net')['quantity_display'])->toBe('27.1');
});

it('35aq. material inventory stats fall back to expected make-order output when a made order has no actual output quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '4.000000']);
    $version = ($this->makeRecipeVersion)($tenant, $recipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '4.000000']);
    ($this->publishRecipeVersion)($recipe, $version);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'runs' => '2.000000',
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-execute']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    $stats = ($this->extractInventoryStats)(($this->getShow)($user, $item));

    expect($makeOrder->fresh()->actual_output_qty)->toBeNull()
        ->and($stats['on_hand_quantity'] ?? null)->toBe('8.000000')
        ->and($stats['net_quantity'] ?? null)->toBe('8.000000');
});

it('35ar. material inventory stats keep expected ingredient demand while actual make-order output increases net quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2, 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'is_manufacturable' => true]);

    $outputRecipe = ($this->makeRecipe)($tenant, $item, ['output_quantity' => '10.000000']);
    $outputVersion = ($this->makeRecipeVersion)($tenant, $outputRecipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '10.000000']);
    ($this->publishRecipeVersion)($outputRecipe, $outputVersion);
    $madeOrder = ($this->makeMakeOrder)($tenant, $outputRecipe, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'runs' => '3.000000',
    ]);

    $otherOutputItem = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $inputRecipe = ($this->makeRecipe)($tenant, $otherOutputItem, ['output_quantity' => '1.000000']);
    $inputVersion = ($this->makeRecipeVersion)($tenant, $inputRecipe, ['status' => RecipeVersion::STATUS_PUBLISHED, 'output_quantity' => '1.000000']);
    ($this->publishRecipeVersion)($inputRecipe, $inputVersion);
    $openOrder = ($this->makeMakeOrder)($tenant, $inputRecipe, [
        'status' => MakeOrder::STATUS_DRAFT,
        'runs' => '1.000000',
    ]);

    MakeOrderLine::query()->create([
        'tenant_id' => $tenant->id,
        'make_order_id' => $openOrder->id,
        'input_item_id' => $item->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '1.500000',
        'line_type' => MakeOrderLine::TYPE_RECIPE,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-execute']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $madeOrder), [
            'actual_output_qty' => '27.125000',
        ])
        ->assertOk();

    $stats = ($this->extractInventoryStats)(($this->getShow)($user, $item));

    expect($stats['on_hand_quantity'] ?? null)->toBe('27.125000')
        ->and($stats['open_make_output_quantity'] ?? null)->toBe('0.000000')
        ->and($stats['open_make_ingredient_quantity'] ?? null)->toBe('1.500000')
        ->and($stats['net_quantity'] ?? null)->toBe('25.625000');
});

it('35b. stockable material detail shows an inventory counts section using the shared reusable detail section pattern', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-adjustments-view', 'inventory-adjustments-execute']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'inventoryCounts');
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($section['resource'] ?? null)->toBe('material-inventory-counts')
        ->and($section['title'] ?? null)->toBe('Inventory Counts')
        ->and($section['permissions']['canCreate'] ?? null)->toBeTrue()
        ->and($section['createAction']['submitLabel'] ?? null)->toBe('Create Count')
        ->and($section['createAction']['handlerKey'] ?? null)->toBe('openInventoryCountCreate')
        ->and($section['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($section['inlineActionsOnMobile'] ?? null)->toBeTrue()
        ->and($section['recordClass'] ?? null)->toBe('rounded-lg border border-gray-200 bg-gray-50 px-3 py-1 sm:px-4 sm:py-1')
        ->and($section['rowClass'] ?? null)->toBe('flex flex-row items-start justify-between gap-4')
        ->and($section['rightMetaClass'] ?? null)
        ->toBe('flex min-h-[3.25rem] min-w-[5rem] flex-col items-end justify-between gap-4 self-stretch text-right')
        ->and($section['mobileRowUrlField'] ?? null)->toBe('display.showUrl')
        ->and($section['secondaryFieldsClass'] ?? null)
        ->toBe('mt-px flex flex-wrap items-center gap-x-3 gap-y-1')
        ->and(data_get($section, 'rowLayout.primaryText.field'))->toBe('display.nameText')
        ->and(data_get($section, 'rowLayout.primaryText.urlField'))->toBe('display.showUrl')
        ->and(data_get($section, 'rowLayout.primaryText.linkClass'))
        ->toBe('truncate text-sm font-semibold text-gray-900 transition hover:text-gray-700')
        ->and(data_get($section, 'rowLayout.secondaryFields.0.label'))->toBe('')
        ->and(data_get($section, 'rowLayout.secondaryFields.0.field'))->toBe('display.assignedToText')
        ->and(data_get($section, 'rowLayout.secondaryFields.0.textClass'))->toBe('text-xs leading-none text-gray-600')
        ->and(data_get($section, 'rowLayout.secondaryFields.0.textValueClass'))->toBe('text-xs leading-none text-gray-600')
        ->and(data_get($section, 'rowLayout.badges.0.field'))->toBe('display.statusText')
        ->and(data_get($section, 'rowLayout.badges.0.toneField'))->toBe('display.statusTone')
        ->and(data_get($section, 'rowLayout.badges.0.textClass'))->toBe('text-[0.55rem] uppercase tracking-wide')
        ->and(data_get($section, 'rowLayout.rightMeta.0.field'))->toBe('display.countedAtText')
        ->and(data_get($section, 'rowLayout.rightMeta.0.textClass'))->toBe('text-xs font-medium text-gray-500')
        ->and(data_get($section, 'rowLayout.rightMeta.1.field'))->toBe('display.countedQuantityText')
        ->and(data_get($section, 'rowLayout.rightMeta.1.suffixField'))->toBe('display.uomSymbolText')
        ->and(data_get($section, 'rowLayout.rightMeta.1.strong'))->toBeTrue()
        ->and(data_get($section, 'rowLayout.rightMeta.1.textClass'))->toBe('text-xs')
        ->and(data_get($section, 'rowLayout.rightMeta.1.suffixClass'))->toBe('text-[0.65rem]')
        ->and($section['actions'] ?? null)->toBe([])
        ->and($viewSource)->toContain('data-section-key="inventoryCounts"');
});

it('35ba. shared crud badge renderer supports section configured badge font classes', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain("badge.textClass || 'text-xs'")
        ->and($source)->toContain('textClass: entry.textClass');
});

it('35c. stockable material inventory counts rows show date assignee and counted quantity with uom name', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant, ['name' => 'Counter User']);
    $uom = ($this->makeUom)($tenant, ['name' => 'Gram', 'symbol' => 'g', 'display_precision' => 0]);
    $uom->forceFill(['tenant_id' => null])->save();
    $newBaseUom = ($this->makeUom)($tenant, ['name' => 'Ounce', 'symbol' => 'oz', 'display_precision' => 2]);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);
    $count = ($this->makeInventoryCount)($tenant, $user, ['counted_at' => now()->setDate(2026, 5, 10)->setTime(9, 30)]);
    ($this->makeInventoryCountLine)($tenant, $count, $item, '1234.500000');
    $item->forceFill(['base_uom_id' => $newBaseUom->id])->save();

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-adjustments-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $item), 'inventoryCounts')
    )->assertOk();

    expect($response->json('data.0.counted_at'))->toBe('May 10, 2026')
        ->and($response->json('data.0.name'))->toBe('Material section count')
        ->and($response->json('data.0.assigned_to_user_name'))->toBe('Counter User')
        ->and($response->json('data.0.uom_name'))->toBe('Gram')
        ->and($response->json('data.0.uom_symbol'))->toBe('g')
        ->and($response->json('data.0.status_label'))->toBe('Draft')
        ->and($response->json('data.0.status_tone'))->toBe('muted')
        ->and($response->json('data.0.counted_quantity_display'))->toBe('1,234.5')
        ->and($response->json('data.0.available_actions'))->toBe([]);
});

it('35ca. material inventory count row adapter does not render labels or a separate uom value', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($pageSource)->toContain("nameText: asString(record.name, '—'),")
        ->and($pageSource)->toContain('assignedToText: asString(record.assigned_to_user_name),')
        ->and($pageSource)->toContain('countedQuantityText: asString(record.counted_quantity_display),')
        ->and($pageSource)->toContain('uomNameText: asString(record.uom_name),')
        ->and($pageSource)->toContain('uomSymbolText: asString(record.uom_symbol),')
        ->and($pageSource)->toContain('statusText: asString(record.status_label),')
        ->and($pageSource)->toContain("statusTone: asString(record.status_tone, 'muted'),")
        ->and($pageSource)->not->toContain('uomText')
        ->and($pageSource)->not->toContain("assignedToText: asString(record.assigned_to_user_name, 'Unassigned')")
        ->and($pageSource)->not->toContain("countedQuantityText: asString(record.counted_quantity_display, '—')");
});

it('35d. material detail inventory counts create endpoint scopes the created count to the current material and returns the detail redirect url', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2]);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-adjustments-execute']);

    $response = ($this->postMaterialInventoryCount)($user, $item, [
        'name' => 'Material shelf count',
        'counted_at' => '2026-05-20T10:15',
        'assigned_to_user_id' => $user->id,
    ])->assertCreated();

    $countId = (int) $response->json('count.id');
    $line = InventoryCountLine::query()->where('inventory_count_id', $countId)->first();

    expect($line)->not->toBeNull()
        ->and($line?->item_id)->toBe($item->id)
        ->and($line?->uom_id)->toBe($item->base_uom_id)
        ->and($line?->inventoryCount?->name)->toBe('Material shelf count')
        ->and($line?->counted_quantity)->toBeNull()
        ->and($response->json('count.show_url'))->toBe(route('inventory.counts.show', $countId));
});

it('35e. empty stockable material inventory counts section uses the normal empty state and not the load error message contract', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-adjustments-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'inventoryCounts');
    $listResponse = ($this->getSectionList)($user, $section)->assertOk();
    $sharedSectionSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($section['emptyState'] ?? null)->toBe('No inventory counts include this material yet.')
        ->and($listResponse->json('data'))->toBe([])
        ->and($sharedSectionSource)->toContain("x-show=\"!isLoading && records.length === 0\"")
        ->and($sharedSectionSource)->toContain("x-show=\"sectionError\"")
        ->and($sharedSectionSource)->toContain("if (!response.ok) {\n                this.sectionError = 'Unable to load records.';")
        ->and($sharedSectionSource)->toContain("this.records = asArray(data.data).map((record) => this.normalizeRow(record));");
});

it('35f. material detail inventory counts only show an error message when the shared section load actually fails', function (): void {
    $sharedSectionSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($sharedSectionSource)->toContain("if (!response.ok) {\n                this.sectionError = 'Unable to load records.';")
        ->and($sharedSectionSource)->toContain("} catch (error) {\n            this.sectionError = 'Unable to load records.';")
        ->and($sharedSectionSource)->not->toContain("this.sectionError = 'Unable to load records.';\n            this.records = [];")
        ->and($sharedSectionSource)->toContain('action: this.section.createAction,');
});

it('35g. material detail inventory counts section reuses the inventory counts shared create slide-over contract from the index page', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant, ['name' => 'Counter User']);
    $uom = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true, 'name' => 'Scoped Material']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-adjustments-view', 'inventory-adjustments-execute']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $payload = ($this->extractMaterialPayload)($response);
    $section = ($this->extractSection)($response, 'inventoryCounts');
    $materialShowSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $inventoryIndexSource = file_get_contents(resource_path('views/inventory/counts/index.blade.php'));
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($section['createAction']['type'] ?? null)->toBe('custom')
        ->and($section['createAction']['handlerKey'] ?? null)->toBe('openInventoryCountCreate')
        ->and($payload['inventoryCountCreate']['storeUrl'] ?? null)->toBe(route('materials.inventory-counts.store', $item))
        ->and(data_get($payload, 'inventoryCountCreate.users.0.id'))->toBe($user->id)
        ->and($payload['inventoryCountCreate']['scopedItem']['id'] ?? null)->toBe($item->id)
        ->and($payload['inventoryCountCreate']['scopedItem']['name'] ?? null)->toBe('Scoped Material')
        ->and($payload['inventoryCountCreate']['scopedItem']['uomSymbol'] ?? null)->toBe('kg')
        ->and($materialShowSource)->toContain("inventory.counts.partials.count-form")
        ->and($inventoryIndexSource)->toContain("inventory.counts.partials.count-form")
        ->and($materialShowSource)->toContain('<x-slot name="overlays">')
        ->and($materialShowSource)->toContain('data-material-inventory-count-create-root')
        ->and($materialShowSource)->toContain('x-data="materialInventoryCountCreate"')
        ->and($response->getContent())->toContain('role="dialog"')
        ->and($response->getContent())->toContain('Inventory Count')
        ->and($pageSource)->toContain("let inventoryCountCreateState = null;")
        ->and($pageSource)->toContain("Alpine.data('materialInventoryCountCreate', () => ({")
        ->and($pageSource)->toContain('inventoryCountCreateState = this;')
        ->and($pageSource)->toContain("const openInventoryCountCreate = () => {")
        ->and($pageSource)->toContain("inventoryCountCreateState.openCreate();")
        ->and($pageSource)->toContain("if (!action || action.handlerKey !== 'openInventoryCountCreate') {")
        ->and($pageSource)->toContain('openInventoryCountCreate();')
        ->and($pageSource)->toContain('showCountForm: false,')
        ->and($pageSource)->toContain('this.showCountForm = Boolean(this.form.action);')
        ->and($pageSource)->toContain("action: this.inventoryCountStoreUrl || ''")
        ->and($pageSource)->not->toContain('counted_quantity:');
});

it('35h. material detail inventory counts create contract redirects to the created inventory count detail page after success', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($pageSource)->toContain("const showUrl = asString(data?.count?.show_url);")
        ->and($pageSource)->toContain('window.location.assign(showUrl);');
});

it('35ha. material detail inventory counts does not introduce a material only simplified create field into the shared slide over', function (): void {
    $materialShowSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $inventoryIndexSource = file_get_contents(resource_path('views/inventory/counts/index.blade.php'));

    expect($materialShowSource)->not->toContain("'showCountedQuantity' => true")
        ->and($inventoryIndexSource)->not->toContain("'showCountedQuantity' => true");
});

it('35i. material detail inventory counts section enforces permissions and tenant isolation', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($otherTenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_stockable' => true]);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom, ['is_stockable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);
    ($this->grantPermissions)($otherUser, ['inventory-materials-view', 'inventory-adjustments-view', 'inventory-adjustments-execute']);

    $this->actingAs($user)
        ->getJson(route('materials.inventory-counts.index', $item))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->getJson(route('materials.inventory-counts.index', $item))
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->postJson(route('materials.inventory-counts.store', $item), [
            'name' => 'Cross tenant count',
            'counted_at' => '2026-05-20T10:15',
            'counted_quantity' => '1.000000',
        ])
        ->assertNotFound();

    expect(route('materials.inventory-counts.index', $otherItem))->not->toBe('');
});

it('36. resource detail pages use a shared resource detail layout component', function (): void {
    $detailViews = [
        'materials/show.blade.php',
        'manufacturing/recipes/show.blade.php',
        'manufacturing/make-orders/show.blade.php',
        'purchasing/orders/show.blade.php',
        'purchasing/suppliers/show.blade.php',
        'sales/orders/show.blade.php',
        'sales/customers/show.blade.php',
        'inventory/counts/show.blade.php',
    ];

    foreach ($detailViews as $viewPath) {
        $source = file_get_contents(resource_path('views/' . $viewPath));

        expect($source)->toContain('<x-resource-detail-layout');
    }
});

it('37. shared resource detail layout exposes a dedicated scrollable content region', function (): void {
    $source = file_get_contents(resource_path('views/components/resource-detail-layout.blade.php'));

    expect($source)->toContain('h-full min-h-0')
        ->and($source)->toContain('overflow-y-auto')
        ->and($source)->toContain('data-resource-detail-layout')
        ->and($source)->toContain('data-resource-detail-scroll');
});

it('38. shared app layout keeps sticky shell navigation and header above the detail scroller', function (): void {
    $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($source)->toContain('h-screen')
        ->and($source)->toContain('overflow-hidden')
        ->and($source)->toContain('sticky top-0 z-40')
        ->and($source)->toContain('sticky top-16 z-30');
});

it('39. recipe create slide over stays fixed above sticky shell chrome and closes on backdrop click', function (): void {
    $source = file_get_contents(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));
    $shellSource = file_get_contents(resource_path('views/components/slide-over-shell.blade.php'));

    expect($source)->toContain('<x-slide-over-shell')
        ->and($source)->toContain('close="closeCreate()"')
        ->and($shellSource)->toContain('fixed inset-0 z-50')
        ->and($shellSource)->toContain('absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity')
        ->and($shellSource)->toContain('x-on:click="{{ $close }}"');
});

it('40. make order create slide over stays fixed above sticky shell chrome and closes on backdrop click', function (): void {
    $source = file_get_contents(resource_path('views/manufacturing/make-orders/partials/create-make-order-slide-over.blade.php'));
    $shellSource = file_get_contents(resource_path('views/components/slide-over-shell.blade.php'));

    expect($source)->toContain('<x-slide-over-shell')
        ->and($source)->toContain('close="closeMakeOrderForm()"')
        ->and($shellSource)->toContain('fixed inset-0 z-50')
        ->and($shellSource)->toContain('absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity')
        ->and($shellSource)->toContain('x-on:click="{{ $close }}"');
});

it('41. material detail renders slide over layers outside the scrollable detail content container', function (): void {
    $source = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($source)->toContain('data-material-detail-content')
        ->and($source)->toContain('data-material-detail-overlays');

    $contentPosition = strpos($source, 'data-material-detail-content');
    $overlayPosition = strpos($source, 'data-material-detail-overlays');

    expect($contentPosition)->not->toBeFalse()
        ->and($overlayPosition)->not->toBeFalse()
        ->and($overlayPosition)->toBeGreaterThan($contentPosition);
});

it('42. material detail uses the shared resource detail header breadcrumb component', function (): void {
    $source = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($source)->toContain('x-resource-detail-header-breadcrumb')
        ->and($source)->not->toContain('<x-resource-breadcrumbs :items="$breadcrumbItems" />');
});

it('43. shared resource detail header breadcrumb component keeps the breadcrumb above the material header body', function (): void {
    $source = file_get_contents(resource_path('views/components/resource-detail-header-breadcrumb.blade.php'));

    expect($source)->toContain('data-resource-detail-header-body')
        ->and($source)->toContain('data-resource-detail-breadcrumb')
        ->and($source)->toContain('order-first');
});

it('43b. shared breadcrumb component preserves connected chevron separator line styling', function (): void {
    $source = file_get_contents(resource_path('views/components/ui/breadcrumbs.blade.php'));

    expect($source)->toContain('border-y border-gray-200')
        ->and($source)->toContain('viewBox="0 0 24 44"');
});
