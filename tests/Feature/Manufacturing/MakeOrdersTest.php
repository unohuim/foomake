<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeTenant = function (string $name): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $suffix = (string) Str::uuid();

        $category = UomCategory::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $suffix,
            'symbol' => 'u' . str_replace('-', '', $suffix),
        ]);
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, string $name, bool $manufacturable = false): Item {
        return Item::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => $manufacturable,
        ]);
    };

    $this->makeRecipe = function (
        Tenant $tenant,
        Item $outputItem,
        bool $isActive = true,
        string $name = 'Recipe A',
        string $outputQuantity = '1.000000'
    ): Recipe {
        return Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'recipe_type' => 'manufacturing',
            'name' => $name,
            'is_active' => $isActive,
            'output_quantity' => $outputQuantity,
        ]);
    };

    $this->addRecipeLine = function (Tenant $tenant, Recipe $recipe, Item $inputItem, string $quantity): RecipeLine {
        return RecipeLine::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'item_id' => $inputItem->id,
            'quantity' => $quantity,
        ]);
    };

    $this->makeOrder = function (Tenant $tenant, Recipe $recipe, User $user, array $overrides = []): MakeOrder {
        return MakeOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $recipe->item_id,
            'output_quantity' => '1.000000',
            'status' => 'DRAFT',
            'due_date' => null,
            'scheduled_at' => null,
            'made_at' => null,
            'created_by_user_id' => $user->id,
            'made_by_user_id' => null,
        ], $overrides));
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $slug . '-' . $user->id,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\s*(.*?)\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        expect($matches)->not->toBeEmpty();

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };

    $this->listMakeOrders = function (User $user, array $query = []) {
        return $this->actingAs($user)->getJson(route('manufacturing.make-orders.list', $query));
    };

    $this->updateMakeOrder = function (User $user, MakeOrder $makeOrder, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('manufacturing.make-orders.update', $makeOrder), $payload);
    };

    $this->archiveMakeOrder = function (User $user, MakeOrder $makeOrder) {
        return $this->actingAs($user)->deleteJson(route('manufacturing.make-orders.destroy', $makeOrder));
    };
});

test('guests are redirected to login for make orders routes', function () {
    $this->get(route('manufacturing.make-orders.index'))
        ->assertRedirect(route('login'));

    $this->post(route('manufacturing.make-orders.store'))
        ->assertRedirect(route('login'));

    $this->post(route('manufacturing.make-orders.schedule', 1))
        ->assertRedirect(route('login'));

    $this->post(route('manufacturing.make-orders.make', 1))
        ->assertRedirect(route('login'));
});

test('users without inventory-make-orders-view cannot access make orders index', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();
});

test('execute permission allows create and schedule but not view access', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();

    $storeResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.000000',
        ])
        ->assertCreated();

    $makeOrderId = $storeResponse->json('data.id');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrderId), [
            'due_date' => '2026-02-01',
        ])
        ->assertOk();
});

test('view permission can access make orders index and payload lists tenant scoped orders', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Batch of Patties');

    ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '2.500000',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $response
        ->assertSee('Runs')
        ->assertDontSee('Output quantity')
        ->assertSee('data-page="manufacturing-make-orders"', false)
        ->assertSee('data-payload="manufacturing-make-orders-payload"', false)
        ->assertSee('<script type="application/json"', false);

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload)->toHaveKey('recipes')
        ->and($payload)->not->toHaveKey('make_orders')
        ->and($payload['storeUrl'] ?? null)->toBe(route('manufacturing.make-orders.store'));
});

test('make order selection payload uses recipe names for same-item recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Patties', true);

    ($this->makeRecipe)($tenant, $output, true, 'Batch of Patties', '54.000000');
    ($this->makeRecipe)($tenant, $output, true, 'Drum of Patties', '324.000000');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk()
        ->assertSee('Batch of Patties')
        ->assertSee('Drum of Patties');

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('name')->all())
        ->toContain('Batch of Patties', 'Drum of Patties');
});

test('make orders index is tenant scoped and empty state returns empty payload list', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-view');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);

    $outputA = ($this->makeItem)($tenantA, $uomA, 'Tenant A Output', true);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Tenant B Output', true);

    $recipeA = ($this->makeRecipe)($tenantA, $outputA, true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);

    ($this->makeOrder)($tenantB, $recipeB, ($this->makeUser)($tenantB), [
        'output_quantity' => '1.000000',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($userA)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload['recipes'])->toBeArray();

    ($this->makeOrder)($tenantA, $recipeA, $userA, [
        'output_quantity' => '3.000000',
        'status' => 'DRAFT',
    ]);

    $second = $this->actingAs($userA)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $payloadTwo = ($this->extractPayload)($second, 'manufacturing-make-orders-payload');

    expect($payloadTwo['recipes'])->toHaveCount(1);
    expect($payloadTwo['recipes'][0]['item_name'])->toBe($outputA->name);
});

test('list endpoint requires make order view permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    ($this->listMakeOrders)($user)
        ->assertForbidden();
});

test('list payload includes due date recipe name runs output item status and calculated qty', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Patties', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Patty Batch', '12.500000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '2.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-12',
        'scheduled_at' => now(),
    ]);

    $response = ($this->listMakeOrders)($user)
        ->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row)->toHaveKeys([
        'id',
        'recipe_id',
        'recipe_name',
        'output_item_id',
        'output_item_name',
        'runs',
        'status',
        'due_date',
        'qty',
        'show_url',
    ]);

    expect($row['due_date'])->toBe('2026-02-12');
    expect($row['recipe_name'])->toBe('Patty Batch');
    expect($row['runs'])->toBe('2.000000');
    expect($row['output_item_name'])->toBe('Patties');
    expect($row['status'])->toBe('SCHEDULED');
    expect($row['qty'])->toBe('25.000000');
    expect($row['show_url'])->toBe(route('manufacturing.make-orders.show', $makeOrder));
});

test('list qty calculation uses recipe output quantity multiplied by runs with canonical string math', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Sauce Batch', '1.234500');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '2.500000',
        'status' => 'DRAFT',
    ]);

    $response = ($this->listMakeOrders)($user)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row['qty'])->toBe(bcmul('1.234500', '2.500000', 6));
    expect($row['qty'])->toBe('3.086250');
});

test('list qty calculation does not assume float math for small decimals', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Spice Mix', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Spice Batch', '0.100000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '0.200000',
        'status' => 'DRAFT',
    ]);

    $response = ($this->listMakeOrders)($user)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row['qty'])->toBe('0.020000')
        ->and($row['qty'])->not->toBe('0.02');
});

test('edit updates the existing make order and returns the selected record payload', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $outputTwo = ($this->makeItem)($tenant, $uom, 'Buns', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Bread Batch', '10.000000');
    $recipeTwo = ($this->makeRecipe)($tenant, $outputTwo, true, 'Bun Batch', '6.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '1.500000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrder)($user, $makeOrder, [
        'recipe_id' => $recipeTwo->id,
        'runs' => '3.250000',
        'due_date' => '2026-02-18',
    ])
        ->assertOk()
        ->assertJsonPath('data.recipe_id', $recipeTwo->id)
        ->assertJsonPath('data.recipe_name', 'Bun Batch')
        ->assertJsonPath('data.output_item_name', 'Buns')
        ->assertJsonPath('data.runs', '3.250000')
        ->assertJsonPath('data.due_date', '2026-02-18')
        ->assertJsonPath('data.qty', '19.500000');

    $makeOrder->refresh();

    expect($makeOrder->recipe_id)->toBe($recipeTwo->id);
    expect($makeOrder->output_item_id)->toBe($outputTwo->id);
    expect($makeOrder->output_quantity)->toBe('3.250000');
    expect($makeOrder->due_date?->format('Y-m-d'))->toBe('2026-02-18');
});

test('edit preserves validation behavior for recipe and runs fields', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Bread Batch', '10.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '1.500000',
        'status' => 'DRAFT',
    ]);

    ($this->updateMakeOrder)($user, $makeOrder, [
        'recipe_id' => $recipe->id,
        'runs' => '0',
        'due_date' => '2026-02-18',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    ($this->updateMakeOrder)($user, $makeOrder, [
        'recipe_id' => 999999,
        'runs' => '2.000000',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

test('archive transitions an eligible make order to cancelled and removes it from the active list', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-10',
        'scheduled_at' => now(),
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertOk()
        ->assertJsonPath('data.status', MakeOrder::STATUS_CANCELLED);

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe(MakeOrder::STATUS_CANCELLED);

    $listResponse = ($this->listMakeOrders)($user)->assertOk();

    expect(collect($listResponse->json('data'))->pluck('id')->all())->not->toContain($makeOrder->id);
});

test('archive requires execute permission even when view permission exists', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'DRAFT',
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertForbidden();
});

test('archive is tenant scoped and returns not found across tenants', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);
    $userB = ($this->makeUser)($tenantB);
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => 'SCHEDULED',
        'scheduled_at' => now(),
        'due_date' => '2026-02-02',
    ]);

    ($this->archiveMakeOrder)($userA, $makeOrderB)
        ->assertNotFound();
});

test('archive rejects made make orders as an invalid terminal state change', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'MADE',
        'made_at' => now(),
        'made_by_user_id' => $user->id,
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Made make orders cannot be archived.',
        ]);

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe('MADE');
});

test('cancelled make orders remain terminal for schedule and make actions', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_CANCELLED,
        'due_date' => '2026-02-12',
        'scheduled_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-02-13',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Cancelled make orders cannot be scheduled.',
        ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Cancelled make orders cannot be made.',
        ]);
});

test('create draft make order validates payload and does not create stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id', 'runs']);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => 999999,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '-1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '0',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.0000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    $beforeMoves = StockMove::query()->count();

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '2.000000',
        ])
        ->assertCreated();

    $makeOrderId = $response->json('data.id');
    $makeOrder = MakeOrder::query()->findOrFail($makeOrderId);

    expect($makeOrder->status)->toBe('DRAFT');
    expect($makeOrder->output_item_id)->toBe($output->id);
    expect($makeOrder->output_quantity)->toBe('2.000000');
    expect($makeOrder->created_by_user_id)->toBe($user->id);
    expect($makeOrder->made_at)->toBeNull();
    expect($response->json('data.runs'))->toBe('2.000000');

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('create rejects inactive recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

test('create accepts fractional runs and stores them without float conversion', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Fractional Runs Recipe', '54.000000');

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '2.500000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.runs', '2.500000');

    $makeOrder = MakeOrder::query()->findOrFail($response->json('data.id'));

    expect($makeOrder->output_quantity)->toBe('2.500000');
});

test('schedule sets due date and status without creating stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'DRAFT',
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-02-10',
        ])
        ->assertOk();

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe('SCHEDULED');
    expect($makeOrder->due_date?->format('Y-m-d'))->toBe('2026-02-10');
    expect($makeOrder->scheduled_at)->not->toBeNull();
    expect($makeOrder->made_at)->toBeNull();

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('schedule rejects inactive recipe and invalid due date', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, false);

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'DRAFT',
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => 'not-a-date',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-02-10',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

test('make treats make order quantity as runs and scales recipe inputs and outputs correctly', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $inputA = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $inputB = ($this->makeItem)($tenant, $uom, 'Water', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Run Scaling Recipe', '10.000000');
    ($this->addRecipeLine)($tenant, $recipe, $inputA, '2.000000');
    ($this->addRecipeLine)($tenant, $recipe, $inputB, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '3.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe('MADE');
    expect($makeOrder->made_at)->not->toBeNull();
    expect($makeOrder->made_by_user_id)->toBe($user->id);

    $moves = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->orderBy('id')
        ->get();

    expect($moves)->toHaveCount($beforeMoves + 3);

    $newMoves = $moves->slice(-3)->values();
    $moveByItem = $newMoves->keyBy('item_id');

    expect((string) $moveByItem[$inputA->id]->quantity)->toBe('-6.000000');
    expect($moveByItem[$inputA->id]->type)->toBe('issue');
    expect($moveByItem[$inputA->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$inputA->id]->uom_id)->toBe($inputA->base_uom_id);

    expect((string) $moveByItem[$inputB->id]->quantity)->toBe('-3.000000');
    expect($moveByItem[$inputB->id]->type)->toBe('issue');
    expect($moveByItem[$inputB->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$inputB->id]->uom_id)->toBe($inputB->base_uom_id);

    expect((string) $moveByItem[$output->id]->quantity)->toBe('30.000000');
    expect($moveByItem[$output->id]->type)->toBe('receipt');
    expect($moveByItem[$output->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$output->id]->uom_id)->toBe($output->base_uom_id);
});

test('make is blocked when already made and creates no additional stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true);
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Make order is already made.',
        ]);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('make rejects inactive recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, false);
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('tenant isolation is enforced for store, schedule, and make', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);
    $userB = ($this->makeUser)($tenantB);

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipeB->id,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => 'DRAFT',
    ]);

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrderB), [
            'due_date' => '2026-02-01',
        ])
        ->assertNotFound();

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.make', $makeOrderB))
        ->assertNotFound();
});

test('make blocks execution when recipe output quantity is zero', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Zero Output Recipe', '0.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('make blocks execution when runs are negative or zero on persisted orders', function (string $runs) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Blocked Runs Recipe', '5.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => $runs,
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
})->with([
    '0.000000',
    '-1.000000',
]);
