<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->recipeCounter = 1;

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
            'email' => 'sales-order-packing-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'sales-order-packing-role-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $this->customerCounter,
            'status' => 'active',
            'notes' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
            'formatted_address' => null,
            'latitude' => null,
            'longitude' => null,
            'address_provider' => null,
            'address_provider_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createSalesOrder = function (Tenant $tenant, int $customerId, array $attributes = []): SalesOrder {
        return SalesOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => null,
            'status' => SalesOrder::STATUS_OPEN,
        ], $attributes));
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? 'SOP-UOM-' . $this->uomCounter;
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Packing Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Packing UOM ' . $this->uomCounter,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->createItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => 1000,
            'default_price_currency_code' => 'USD',
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->createLine = function (
        Tenant $tenant,
        SalesOrder $order,
        Item $item,
        array $attributes = []
    ): SalesOrderLine {
        return SalesOrderLine::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => '1.000000',
            'unit_price_cents' => 1000,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '1000.000000',
        ], $attributes));
    };

    $this->createReceipt = function (Tenant $tenant, Item $item, string|int $quantity): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => ($this->asSixDecimals)($quantity),
            'type' => 'receipt',
            'status' => 'POSTED',
        ]);
    };

    $this->createAdjustmentIssue = function (Tenant $tenant, Item $item, string|int $quantity): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => ($this->negativeSixDecimals)($quantity),
            'type' => 'adjustment',
            'status' => 'POSTED',
        ]);
    };

    $this->createFulfillmentRecipe = function (
        Tenant $tenant,
        Item $outputItem,
        array $attributes = []
    ): Recipe {
        $recipe = Recipe::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => 'Fulfillment Recipe ' . $this->recipeCounter,
            'output_quantity' => Recipe::FULFILLMENT_OUTPUT_QUANTITY,
            'is_active' => true,
            'is_default' => true,
        ], $attributes));

        $this->recipeCounter++;

        return $recipe;
    };

    $this->createRecipeLine = function (Tenant $tenant, Recipe $recipe, Item $item, string|int $quantity): RecipeLine {
        return RecipeLine::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'item_id' => $item->id,
            'quantity' => ($this->asSixDecimals)($quantity),
        ]);
    };

    $this->transitionOrder = function (User $user, SalesOrder $order, string $status) {
        return $this->actingAs($user)->patchJson(route('sales.orders.status.update', $order), [
            'status' => $status,
        ]);
    };

    $this->fetchOrder = fn (SalesOrder $order): SalesOrder => SalesOrder::query()->findOrFail($order->id);

    $this->fetchOrderMoves = function (SalesOrder $order): Collection {
        $lineIds = SalesOrderLine::query()
            ->where('sales_order_id', $order->id)
            ->pluck('id');

        return StockMove::query()
            ->where('source_type', SalesOrderLine::class)
            ->whereIn('source_id', $lineIds->all() === [] ? [0] : $lineIds->all())
            ->orderBy('id')
            ->get();
    };

    $this->asSixDecimals = fn (string|int $quantity): string => bcadd((string) $quantity, '0', 6);
    $this->negativeSixDecimals = fn (string|int $quantity): string => bcsub('0.000000', bcadd((string) $quantity, '0', 6), 6);
});

it('12. normal stocked item must have enough inventory before open moves to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKING);
});

it('13. fulfillment recipe item must have complete recipe lines before open moves to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom, ['name' => 'Bundle']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle);
    ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'Fulfillment recipe must have at least one line.');

    expect(($this->fetchOrder)($order)->status)->toBe(SalesOrder::STATUS_OPEN);
});

it('14. fulfillment recipe components must have enough inventory before open moves to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom, ['name' => 'Snack Pack']);
    $component = ($this->createItem)($tenant, $uom, ['name' => 'Wrapper', 'is_sellable' => false]);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle, ['quantity' => '2.000000']);
    $recipe = ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->createRecipeLine)($tenant, $recipe, $component, '2.000000');
    ($this->createReceipt)($tenant, $component, '3.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'Insufficient inventory for Wrapper.');

    expect(($this->fetchOrder)($order)->status)->toBe(SalesOrder::STATUS_OPEN);
});

it('15. no stock moves are created when order moves to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    expect(($this->fetchOrderMoves)($order))->toHaveCount(0);
});

it('16. if any normal stocked line is unavailable the order stays open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Single Bar']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '3.000000']);
    ($this->createReceipt)($tenant, $item, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertStatus(422);

    expect(($this->fetchOrder)($order)->status)->toBe(SalesOrder::STATUS_OPEN)
        ->and(($this->fetchOrderMoves)($order))->toHaveCount(0);
});

it('17. if any fulfillment component is unavailable the order stays open', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom);
    $component = ($this->createItem)($tenant, $uom, ['name' => 'Component', 'is_sellable' => false]);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle, ['quantity' => '2.000000']);
    $recipe = ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->createRecipeLine)($tenant, $recipe, $component, '1.500000');
    ($this->createReceipt)($tenant, $component, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertStatus(422);

    expect(($this->fetchOrder)($order)->status)->toBe(SalesOrder::STATUS_OPEN)
        ->and(($this->fetchOrderMoves)($order))->toHaveCount(0);
});

it('18. no inventory reservation occurs when order moves to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '5.000000');
    $startingOnHand = $item->onHandQuantity();
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    expect($item->fresh()->onHandQuantity())->toBe($startingOnHand);
});

it('19. packing to packed creates issue stock moves for normal stocked lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Shippable Unit']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $line = ($this->createLine)($tenant, $order, $item, ['quantity' => '2.500000']);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    $move = ($this->fetchOrderMoves)($order)->sole();

    expect($move->item_id)->toBe($item->id)
        ->and($move->quantity)->toBe(($this->negativeSixDecimals)('2.500000'))
        ->and($move->type)->toBe('issue')
        ->and($move->source_type)->toBe(SalesOrderLine::class)
        ->and($move->source_id)->toBe($line->id);
});

it('20. packing to packed creates issue stock moves for fulfillment recipe components', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom, ['name' => 'Gift Box']);
    $paper = ($this->createItem)($tenant, $uom, ['name' => 'Paper', 'is_sellable' => false]);
    $ribbon = ($this->createItem)($tenant, $uom, ['name' => 'Ribbon', 'is_sellable' => false]);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $line = ($this->createLine)($tenant, $order, $bundle, ['quantity' => '2.000000']);
    $recipe = ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->createRecipeLine)($tenant, $recipe, $paper, '1.000000');
    ($this->createRecipeLine)($tenant, $recipe, $ribbon, '0.500000');
    ($this->createReceipt)($tenant, $paper, '5.000000');
    ($this->createReceipt)($tenant, $ribbon, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    $moves = ($this->fetchOrderMoves)($order);

    expect($moves)->toHaveCount(2)
        ->and($moves->pluck('item_id')->all())->toBe([$paper->id, $ribbon->id])
        ->and($moves->pluck('source_id')->unique()->all())->toBe([$line->id]);
});

it('21. inventory consumption at packed is all or nothing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->createItem)($tenant, $uom, ['name' => 'A']);
    $itemB = ($this->createItem)($tenant, $uom, ['name' => 'B']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $itemA, ['quantity' => '1.000000']);
    ($this->createLine)($tenant, $order, $itemB, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $itemA, '5.000000');
    ($this->createReceipt)($tenant, $itemB, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->createAdjustmentIssue)($tenant, $itemB, '1.500000');
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertStatus(422);

    expect(($this->fetchOrderMoves)($order))->toHaveCount(0)
        ->and(($this->fetchOrder)($order)->status)->toBe(SalesOrder::STATUS_PACKING);
});

it('22. if any line fails at packed no stock moves are created', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'Critical Item']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->createAdjustmentIssue)($tenant, $item, '4.500000');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertStatus(422);

    expect(($this->fetchOrderMoves)($order))->toHaveCount(0);
});

it('23. if any line fails at packed the order stays packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->createAdjustmentIssue)($tenant, $item, '1.500000');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertStatus(422);

    expect(($this->fetchOrder)($order)->status)->toBe(SalesOrder::STATUS_PACKING);
});

it('24. stock moves link to the sales order line as the available granular source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $line = ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    $move = ($this->fetchOrderMoves)($order)->sole();

    expect($move->source_type)->toBe(SalesOrderLine::class)
        ->and($move->source_id)->toBe($line->id);
});

it('25. stock movement quantities use canonical scale six math', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '1.234567']);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    expect(($this->fetchOrderMoves)($order)->sole()->quantity)->toBe('-1.234567');
});

it('26. fulfillment recipe with no lines blocks open to packing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle);
    ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'Fulfillment recipe must have at least one line.');
});

it('27. fulfillment recipe with lines allows open to packing when inventory exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom);
    $component = ($this->createItem)($tenant, $uom, ['is_sellable' => false]);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle);
    $recipe = ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->createRecipeLine)($tenant, $recipe, $component, '2.000000');
    ($this->createReceipt)($tenant, $component, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKING);
});

it('28. fulfillment recipe with lines allows packing to packed when inventory exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom);
    $component = ($this->createItem)($tenant, $uom, ['is_sellable' => false]);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle);
    $recipe = ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->createRecipeLine)($tenant, $recipe, $component, '2.000000');
    ($this->createReceipt)($tenant, $component, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKED);
});

it('29. manufacturing recipes are not used for sales fulfillment', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom, ['is_manufacturable' => true]);
    $component = ($this->createItem)($tenant, $uom, ['name' => 'Manufacturing Component', 'is_sellable' => false]);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $bundle, ['quantity' => '1.000000']);
    $recipe = Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $bundle->id,
        'recipe_type' => Recipe::TYPE_MANUFACTURING,
        'name' => 'Manufacturing Only',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => true,
    ]);
    ($this->createRecipeLine)($tenant, $recipe, $component, '5.000000');
    ($this->createReceipt)($tenant, $bundle, '1.000000');
    ($this->createReceipt)($tenant, $component, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    expect(($this->fetchOrderMoves)($order)->pluck('item_id')->all())->toBe([$bundle->id]);
});

it('30. normal stocked item without a fulfillment recipe consumes itself explicitly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom, ['name' => 'No Recipe SKU']);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    expect(($this->fetchOrderMoves)($order)->sole()->item_id)->toBe($item->id);
});

it('33. packed to shipping consumes no inventory', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();
    $moveCount = ($this->fetchOrderMoves)($order)->count();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)->assertOk();

    expect(($this->fetchOrderMoves)($order))->toHaveCount($moveCount);
});

it('34. shipping to completed consumes no inventory', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)->assertOk();
    $moveCount = ($this->fetchOrderMoves)($order)->count();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_COMPLETED)->assertOk();

    expect(($this->fetchOrderMoves)($order))->toHaveCount($moveCount);
});

it('39. packed to cancelled creates reversing stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    $beforeCancelCount = ($this->fetchOrderMoves)($order)->count();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertOk();
    $moves = ($this->fetchOrderMoves)($order);

    expect($moves)->toHaveCount($beforeCancelCount * 2)
        ->and($moves->last()->quantity)->toBe('2.000000');
});

it('42. cancellation before packed creates no reversal moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $openOrder = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_OPEN]);
    $packingOrder = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_PACKING]);
    ($this->createLine)($tenant, $openOrder, $item);
    ($this->createLine)($tenant, $packingOrder, $item);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $openOrder, SalesOrder::STATUS_CANCELLED)->assertOk();
    ($this->transitionOrder)($user, $packingOrder, SalesOrder::STATUS_CANCELLED)->assertOk();

    expect(($this->fetchOrderMoves)($openOrder))->toHaveCount(0)
        ->and(($this->fetchOrderMoves)($packingOrder))->toHaveCount(0);
});

it('43. reversal moves preserve audit trail instead of deleting original moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '2.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();
    $originalMoveIds = ($this->fetchOrderMoves)($order)->pluck('id')->all();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertOk();

    expect(($this->fetchOrderMoves)($order)->pluck('id')->take(count($originalMoveIds))->all())->toBe($originalMoveIds);
});

it('44. packed cancellation reversal restores inventory mathematically', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '5.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    $startingOnHand = $item->onHandQuantity();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertOk();

    expect($item->fresh()->onHandQuantity())->toBe($startingOnHand);
});

it('58. make orders remain manufacturing only while sales order packing uses fulfillment recipes separately', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $bundle = ($this->createItem)($tenant, $uom, ['is_manufacturable' => true, 'is_sellable' => true]);
    $component = ($this->createItem)($tenant, $uom, ['is_sellable' => false]);
    $recipe = ($this->createFulfillmentRecipe)($tenant, $bundle);
    ($this->createRecipeLine)($tenant, $recipe, $component, '1.000000');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    expect($response->getContent())->not->toContain($recipe->name);
});
