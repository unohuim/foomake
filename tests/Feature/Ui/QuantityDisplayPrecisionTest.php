<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Support\QuantityFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function PHPUnit\Framework\assertNotNull;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->categoryCounter = 1;
    $this->uomCounter = 1;
    $this->skuCounter = 1;

    $this->makeTenant = function (string $name): Tenant {
        return Tenant::query()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUser = function (Tenant $tenant, string $email): User {
        return User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $email,
            'email' => $email,
            'email_verified_at' => null,
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        $role = Role::query()->firstOrCreate([
            'name' => 'quantity-precision-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $permissionIds = [];

        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $slug,
            ]);

            $permissionIds[] = $permission->id;
        }

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeCategory = function (Tenant $tenant, string $name): UomCategory {
        $suffix = (string) $this->categoryCounter;
        $this->categoryCounter++;

        return UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name . '-' . $suffix,
        ]);
    };

    $this->makeUom = function (
        Tenant $tenant,
        UomCategory $category,
        string $name,
        string $symbol,
        int $displayPrecision
    ): Uom {
        $suffix = (string) $this->uomCounter;
        $this->uomCounter++;

        return Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $name . '-' . $suffix,
            'symbol' => $symbol . '-' . $suffix,
            'display_precision' => $displayPrecision,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, string $name, bool $manufacturable = false): Item {
        return Item::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $uom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => $manufacturable,
        ]);
    };

    $this->addStockMove = function (Tenant $tenant, Item $item, Uom $uom, string $quantity): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $uom->id,
            'quantity' => $quantity,
            'type' => 'receipt',
            'status' => 'POSTED',
        ]);
    };

    $this->makeRecipe = function (Tenant $tenant, Item $output): Recipe {
        return Recipe::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $output->id,
            'name' => 'Simple Recipe',
            'is_active' => true,
            'is_default' => false,
        ]);
    };

    $this->addRecipeLine = function (Tenant $tenant, Recipe $recipe, Item $input, string $quantity): RecipeLine {
        return RecipeLine::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'item_id' => $input->id,
            'quantity' => $quantity,
        ]);
    };

    $this->makeSupplier = function (Tenant $tenant, string $name): Supplier {
        return Supplier::query()->create([
            'tenant_id' => $tenant->id,
            'company_name' => $name,
            'currency_code' => 'USD',
        ]);
    };

    $this->makePurchaseOption = function (
        Tenant $tenant,
        Supplier $supplier,
        Item $item,
        Uom $uom,
        string $packQuantity
    ): ItemPurchaseOption {
        $sku = 'SKU-' . $this->skuCounter;
        $this->skuCounter++;

        return ItemPurchaseOption::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => $sku,
            'pack_quantity' => $packQuantity,
            'pack_uom_id' => $uom->id,
        ]);
    };

    $this->makePurchaseOrderFixture = function (
        Tenant $tenant,
        User $user,
        Supplier $supplier,
        Item $item,
        ItemPurchaseOption $option,
        string $receivedQuantity = '1.200000'
    ): PurchaseOrder {
        $order = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => '2026-02-01',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_subtotal_cents' => 1200,
            'po_grand_total_cents' => 1200,
            'po_number' => 'PO-' . $tenant->id . '-' . $option->id,
            'notes' => 'Quantity display precision fixture',
            'status' => PurchaseOrder::STATUS_OPEN,
        ]);

        $line = PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $order->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => 4,
            'unit_price_cents' => 300,
            'line_subtotal_cents' => 1200,
            'unit_price_amount' => 300,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 300,
            'fx_rate' => '1.00000000',
            'fx_rate_as_of' => '2026-02-01',
        ]);

        $receipt = PurchaseOrderReceipt::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $order->id,
            'received_at' => '2026-02-02 12:00:00',
            'received_by_user_id' => $user->id,
            'reference' => 'REF-' . $tenant->id . '-' . $line->id,
            'notes' => 'Receipt fixture',
        ]);

        PurchaseOrderReceiptLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $line->id,
            'received_quantity' => $receivedQuantity,
        ]);

        return $order;
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };

    $this->inventoryOnHandForItem = function (string $html, string $itemName): ?string {
        $dom = new \DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//tbody/tr');

        if ($rows === false) {
            return null;
        }

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);

            if ($cells === false || $cells->length < 3) {
                continue;
            }

            $name = trim((string) $cells->item(0)?->textContent);

            if ($name === $itemName) {
                return trim((string) $cells->item(2)?->textContent);
            }
        }

        return null;
    };
});

it('formatter returns a whole number string at precision 0', function () {
    expect(QuantityFormatter::format('2.345000', 0))->toBe('2');
});

it('formatter returns one decimal at precision 1', function () {
    expect(QuantityFormatter::format('2.345000', 1))->toBe('2.3');
});

it('formatter rounds half up at precision 2', function () {
    expect(QuantityFormatter::format('2.345000', 2))->toBe('2.35');
});

it('formatter rounds down below threshold at precision 2', function () {
    expect(QuantityFormatter::format('2.344000', 2))->toBe('2.34');
});

it('formatter preserves trailing zeros at precision 3', function () {
    expect(QuantityFormatter::format('2.100000', 3))->toBe('2.100');
});

it('formatter supports precision 4', function () {
    expect(QuantityFormatter::format('2.100000', 4))->toBe('2.1000');
});

it('formatter supports precision 5', function () {
    expect(QuantityFormatter::format('2.100000', 5))->toBe('2.10000');
});

it('formatter supports precision 6', function () {
    expect(QuantityFormatter::format('2.100000', 6))->toBe('2.100000');
});

it('formatter rounds negative values using the same rounding rule', function () {
    expect(QuantityFormatter::format('-1.235000', 2))->toBe('-1.24');
});

it('formatter rounds very small values at precision 6', function () {
    expect(QuantityFormatter::format('0.0000049', 6))->toBe('0.000005');
});

it('formatter accepts numeric strings', function () {
    expect(QuantityFormatter::format('123.456789', 3))->toBe('123.457');
});

it('formatter clamps precision above six to six decimals', function () {
    expect(QuantityFormatter::format('1.234567', 9))->toBe('1.234567');
});

it('formatter clamps precision below zero to zero decimals', function () {
    expect(QuantityFormatter::format('1.900000', -2))->toBe('2');
});

it('formatter uses uom display_precision in formatForUom', function () {
    $tenant = ($this->makeTenant)('Formatter Uom Tenant');
    $category = ($this->makeCategory)($tenant, 'Formatter Uom Category');
    $uom = ($this->makeUom)($tenant, $category, 'Formatter Uom', 'fmt-uom', 3);

    expect(QuantityFormatter::formatForUom('2.100000', $uom, 6))->toBe('2.100');
});

it('inventory representative page displays precision 0 using item base uom precision', function () {
    $tenant = ($this->makeTenant)('Inventory Precision 0 Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-precision-0@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Each', 'ea', 0);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Precision 0 Item');
    ($this->addStockMove)($tenant, $item, $uom, '2.345000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect($display)->toBe('2');
});

it('inventory representative page displays precision 1 using item base uom precision', function () {
    $tenant = ($this->makeTenant)('Inventory Precision 1 Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-precision-1@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Tenth', 'tenth', 1);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Precision 1 Item');
    ($this->addStockMove)($tenant, $item, $uom, '2.100000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect($display)->toBe('2.1');
});

it('inventory representative page output changes when only uom display_precision changes for the same item', function () {
    $tenant = ($this->makeTenant)('Inventory Same Item Precision Change Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-same-item-precision-change@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Mutable Precision', 'mutable-precision', 0);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Same Item Precision');
    ($this->addStockMove)($tenant, $item, $uom, '2.100000');

    $firstResponse = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $firstDisplay = ($this->inventoryOnHandForItem)($firstResponse->getContent(), $item->name);

    assertNotNull($firstDisplay);
    expect($firstDisplay)->toBe('2');

    $uom->update([
        'display_precision' => 3,
    ]);

    $secondResponse = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $secondDisplay = ($this->inventoryOnHandForItem)($secondResponse->getContent(), $item->name);

    assertNotNull($secondDisplay);
    expect($secondDisplay)->toBe('2.100');
});

it('inventory representative page uses default display_precision 1 when uom display_precision is omitted', function () {
    $tenant = ($this->makeTenant)('Inventory Default Precision Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-default-precision@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');

    $uom = Uom::query()->create([
        'tenant_id' => $tenant->id,
        'uom_category_id' => $category->id,
        'name' => 'Default Precision Uom',
        'symbol' => 'default-precision-uom',
    ]);

    $item = ($this->makeItem)($tenant, $uom, 'Inventory Default Precision Item');
    ($this->addStockMove)($tenant, $item, $uom, '2.100000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect((int) $uom->fresh()->display_precision)->toBe(1);
    expect($display)->toBe('2.1');
});

it('inventory representative page displays precision 2 with half-up rounding', function () {
    $tenant = ($this->makeTenant)('Inventory Precision 2 Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-precision-2@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Hundredth', 'hundredth', 2);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Precision 2 Item');
    ($this->addStockMove)($tenant, $item, $uom, '2.345000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect($display)->toBe('2.35');
});

it('inventory representative page displays precision 3 with trailing zeros', function () {
    $tenant = ($this->makeTenant)('Inventory Precision 3 Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-precision-3@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Thousandth', 'thousandth', 3);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Precision 3 Item');
    ($this->addStockMove)($tenant, $item, $uom, '2.100000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect($display)->toBe('2.100');
});

it('inventory representative page displays zero with trailing zeros at precision 3', function () {
    $tenant = ($this->makeTenant)('Inventory Precision 3 Zero Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-precision-3-zero@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Thousandth Zero', 'thousandth-zero', 3);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Precision 3 Zero Item');
    ($this->addStockMove)($tenant, $item, $uom, '0.000000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect($display)->toBe('0.000');
});

it('inventory representative page displays precision 6 with trailing zeros', function () {
    $tenant = ($this->makeTenant)('Inventory Precision 6 Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-precision-6@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uom = ($this->makeUom)($tenant, $category, 'Micro', 'micro', 6);
    $item = ($this->makeItem)($tenant, $uom, 'Inventory Precision 6 Item');
    ($this->addStockMove)($tenant, $item, $uom, '2.000000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();
    $display = ($this->inventoryOnHandForItem)($response->getContent(), $item->name);

    assertNotNull($display);
    expect($display)->toBe('2.000000');
});

it('inventory representative page changes output when two items use different uom precision', function () {
    $tenant = ($this->makeTenant)('Inventory Mixed Precision Tenant');
    $user = ($this->makeUser)($tenant, 'inventory-mixed-precision@example.test');
    ($this->grantPermissions)($user, ['inventory-adjustments-view']);

    $category = ($this->makeCategory)($tenant, 'Inventory Category');
    $uomZero = ($this->makeUom)($tenant, $category, 'Zero Precision', 'zero-precision', 0);
    $uomThree = ($this->makeUom)($tenant, $category, 'Three Precision', 'three-precision', 3);

    $itemZero = ($this->makeItem)($tenant, $uomZero, 'Inventory Mixed Item 0');
    $itemThree = ($this->makeItem)($tenant, $uomThree, 'Inventory Mixed Item 3');

    ($this->addStockMove)($tenant, $itemZero, $uomZero, '2.100000');
    ($this->addStockMove)($tenant, $itemThree, $uomThree, '2.100000');

    $response = $this->actingAs($user)->get(route('inventory.index'))->assertOk();

    $displayZero = ($this->inventoryOnHandForItem)($response->getContent(), $itemZero->name);
    $displayThree = ($this->inventoryOnHandForItem)($response->getContent(), $itemThree->name);

    assertNotNull($displayZero);
    assertNotNull($displayThree);

    expect($displayZero)->toBe('2');
    expect($displayThree)->toBe('2.100');
});

it('recipe representative payload includes quantity_display key', function () {
    $tenant = ($this->makeTenant)('Recipe Key Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-key@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uom = ($this->makeUom)($tenant, $category, 'Recipe Uom', 'recipe-uom', 2);
    $output = ($this->makeItem)($tenant, $uom, 'Recipe Key Output', true);
    $input = ($this->makeItem)($tenant, $uom, 'Recipe Key Input');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.345000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0])->toHaveKey('quantity_display');
});

it('recipe representative payload displays precision 0 based on line item base uom', function () {
    $tenant = ($this->makeTenant)('Recipe Precision 0 Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-precision-0@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uom = ($this->makeUom)($tenant, $category, 'Recipe Uom 0', 'recipe-uom-0', 0);
    $output = ($this->makeItem)($tenant, $uom, 'Recipe Output 0', true);
    $input = ($this->makeItem)($tenant, $uom, 'Recipe Input 0');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.345000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2');
});

it('recipe representative payload displays precision 1 based on line item base uom', function () {
    $tenant = ($this->makeTenant)('Recipe Precision 1 Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-precision-1@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uom = ($this->makeUom)($tenant, $category, 'Recipe Uom 1', 'recipe-uom-1', 1);
    $output = ($this->makeItem)($tenant, $uom, 'Recipe Output 1', true);
    $input = ($this->makeItem)($tenant, $uom, 'Recipe Input 1');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.100000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.1');
});

it('recipe representative payload displays precision 2 with half-up rounding', function () {
    $tenant = ($this->makeTenant)('Recipe Precision 2 Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-precision-2@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uom = ($this->makeUom)($tenant, $category, 'Recipe Uom 2', 'recipe-uom-2', 2);
    $output = ($this->makeItem)($tenant, $uom, 'Recipe Output 2', true);
    $input = ($this->makeItem)($tenant, $uom, 'Recipe Input 2');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.345000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.35');
});

it('recipe representative payload displays precision 3 with trailing zeros', function () {
    $tenant = ($this->makeTenant)('Recipe Precision 3 Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-precision-3@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uom = ($this->makeUom)($tenant, $category, 'Recipe Uom 3', 'recipe-uom-3', 3);
    $output = ($this->makeItem)($tenant, $uom, 'Recipe Output 3', true);
    $input = ($this->makeItem)($tenant, $uom, 'Recipe Input 3');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.100000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.100');
});

it('recipe representative payload displays precision 6 with trailing zeros', function () {
    $tenant = ($this->makeTenant)('Recipe Precision 6 Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-precision-6@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uom = ($this->makeUom)($tenant, $category, 'Recipe Uom 6', 'recipe-uom-6', 6);
    $output = ($this->makeItem)($tenant, $uom, 'Recipe Output 6', true);
    $input = ($this->makeItem)($tenant, $uom, 'Recipe Input 6');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '0.005000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('0.005000');
});

it('recipe representative payload changes output when lines have different uom precision', function () {
    $tenant = ($this->makeTenant)('Recipe Mixed Precision Tenant');
    $user = ($this->makeUser)($tenant, 'recipe-mixed-precision@example.test');
    ($this->grantPermissions)($user, ['inventory-recipes-view']);

    $category = ($this->makeCategory)($tenant, 'Recipe Category');
    $uomZero = ($this->makeUom)($tenant, $category, 'Recipe Uom 0', 'recipe-uom-0', 0);
    $uomThree = ($this->makeUom)($tenant, $category, 'Recipe Uom 3', 'recipe-uom-3', 3);
    $output = ($this->makeItem)($tenant, $uomThree, 'Recipe Mixed Output', true);
    $inputZero = ($this->makeItem)($tenant, $uomZero, 'Recipe Mixed Input 0');
    $inputThree = ($this->makeItem)($tenant, $uomThree, 'Recipe Mixed Input 3');
    $recipe = ($this->makeRecipe)($tenant, $output);

    ($this->addRecipeLine)($tenant, $recipe, $inputZero, '2.100000');
    ($this->addRecipeLine)($tenant, $recipe, $inputThree, '2.100000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'])->toHaveCount(2);
    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2');
    expect($payload['lines'][1]['quantity_display'] ?? null)->toBe('2.100');
});

it('purchase order representative payload includes display keys for quantity fields', function () {
    $tenant = ($this->makeTenant)('PO Key Tenant');
    $user = ($this->makeUser)($tenant, 'po-key@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uom = ($this->makeUom)($tenant, $category, 'PO Uom Key', 'po-uom-key', 2);
    $item = ($this->makeItem)($tenant, $uom, 'PO Key Item');
    $supplier = ($this->makeSupplier)($tenant, 'PO Key Supplier');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.345000');
    $order = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $item, $option, '1.200000');

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['lines'][0])->toHaveKeys([
        'pack_quantity_display',
        'received_sum_display',
        'remaining_balance_display',
    ]);
});

it('purchase order representative payload displays precision 0 from pack uom precision', function () {
    $tenant = ($this->makeTenant)('PO Precision 0 Tenant');
    $user = ($this->makeUser)($tenant, 'po-precision-0@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uom = ($this->makeUom)($tenant, $category, 'PO Uom 0', 'po-uom-0', 0);
    $item = ($this->makeItem)($tenant, $uom, 'PO Item 0');
    $supplier = ($this->makeSupplier)($tenant, 'PO Supplier 0');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.345000');
    $order = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $item, $option, '1.200000');

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['lines'][0]['pack_quantity_display'] ?? null)->toBe('2');
    expect($payload['lines'][0]['received_sum_display'] ?? null)->toBe('1');
    expect($payload['lines'][0]['remaining_balance_display'] ?? null)->toBe('3');
});

it('purchase order representative payload displays precision 1 from pack uom precision', function () {
    $tenant = ($this->makeTenant)('PO Precision 1 Tenant');
    $user = ($this->makeUser)($tenant, 'po-precision-1@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uom = ($this->makeUom)($tenant, $category, 'PO Uom 1', 'po-uom-1', 1);
    $item = ($this->makeItem)($tenant, $uom, 'PO Item 1');
    $supplier = ($this->makeSupplier)($tenant, 'PO Supplier 1');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.100000');
    $order = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $item, $option, '1.200000');

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['lines'][0]['pack_quantity_display'] ?? null)->toBe('2.1');
    expect($payload['lines'][0]['received_sum_display'] ?? null)->toBe('1.2');
    expect($payload['lines'][0]['remaining_balance_display'] ?? null)->toBe('2.8');
});

it('purchase order representative payload displays precision 2 with half-up rounding', function () {
    $tenant = ($this->makeTenant)('PO Precision 2 Tenant');
    $user = ($this->makeUser)($tenant, 'po-precision-2@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uom = ($this->makeUom)($tenant, $category, 'PO Uom 2', 'po-uom-2', 2);
    $item = ($this->makeItem)($tenant, $uom, 'PO Item 2');
    $supplier = ($this->makeSupplier)($tenant, 'PO Supplier 2');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.345000');
    $order = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $item, $option, '1.200000');

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['lines'][0]['pack_quantity_display'] ?? null)->toBe('2.35');
    expect($payload['lines'][0]['received_sum_display'] ?? null)->toBe('1.20');
    expect($payload['lines'][0]['remaining_balance_display'] ?? null)->toBe('2.80');
});

it('purchase order representative payload displays precision 3 with trailing zeros', function () {
    $tenant = ($this->makeTenant)('PO Precision 3 Tenant');
    $user = ($this->makeUser)($tenant, 'po-precision-3@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uom = ($this->makeUom)($tenant, $category, 'PO Uom 3', 'po-uom-3', 3);
    $item = ($this->makeItem)($tenant, $uom, 'PO Item 3');
    $supplier = ($this->makeSupplier)($tenant, 'PO Supplier 3');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '2.100000');
    $order = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $item, $option, '1.200000');

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['lines'][0]['pack_quantity_display'] ?? null)->toBe('2.100');
    expect($payload['lines'][0]['received_sum_display'] ?? null)->toBe('1.200');
    expect($payload['lines'][0]['remaining_balance_display'] ?? null)->toBe('2.800');
});

it('purchase order representative payload displays precision 6 with trailing zeros', function () {
    $tenant = ($this->makeTenant)('PO Precision 6 Tenant');
    $user = ($this->makeUser)($tenant, 'po-precision-6@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uom = ($this->makeUom)($tenant, $category, 'PO Uom 6', 'po-uom-6', 6);
    $item = ($this->makeItem)($tenant, $uom, 'PO Item 6');
    $supplier = ($this->makeSupplier)($tenant, 'PO Supplier 6');
    $option = ($this->makePurchaseOption)($tenant, $supplier, $item, $uom, '0.005000');
    $order = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $item, $option, '0.000000');

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'purchasing-orders-show-payload');

    expect($payload['lines'][0]['pack_quantity_display'] ?? null)->toBe('0.005000');
    expect($payload['lines'][0]['received_sum_display'] ?? null)->toBe('0.000000');
    expect($payload['lines'][0]['remaining_balance_display'] ?? null)->toBe('4.000000');
});

it('purchase order representative payload changes output when pack uom precision changes', function () {
    $tenant = ($this->makeTenant)('PO Mixed Precision Tenant');
    $user = ($this->makeUser)($tenant, 'po-mixed-precision@example.test');
    ($this->grantPermissions)($user, ['purchasing-purchase-orders-create']);

    $category = ($this->makeCategory)($tenant, 'PO Category');
    $uomZero = ($this->makeUom)($tenant, $category, 'PO Uom 0', 'po-uom-0', 0);
    $uomThree = ($this->makeUom)($tenant, $category, 'PO Uom 3', 'po-uom-3', 3);
    $supplier = ($this->makeSupplier)($tenant, 'PO Mixed Supplier');

    $itemZero = ($this->makeItem)($tenant, $uomZero, 'PO Item 0');
    $itemThree = ($this->makeItem)($tenant, $uomThree, 'PO Item 3');

    $optionZero = ($this->makePurchaseOption)($tenant, $supplier, $itemZero, $uomZero, '2.100000');
    $optionThree = ($this->makePurchaseOption)($tenant, $supplier, $itemThree, $uomThree, '2.100000');

    $orderZero = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $itemZero, $optionZero, '1.200000');
    $orderThree = ($this->makePurchaseOrderFixture)($tenant, $user, $supplier, $itemThree, $optionThree, '1.200000');

    $responseZero = $this->actingAs($user)->get(route('purchasing.orders.show', $orderZero))->assertOk();
    $responseThree = $this->actingAs($user)->get(route('purchasing.orders.show', $orderThree))->assertOk();

    $payloadZero = ($this->extractPayload)($responseZero, 'purchasing-orders-show-payload');
    $payloadThree = ($this->extractPayload)($responseThree, 'purchasing-orders-show-payload');

    expect($payloadZero['lines'][0]['pack_quantity_display'] ?? null)->toBe('2');
    expect($payloadThree['lines'][0]['pack_quantity_display'] ?? null)->toBe('2.100');
});
