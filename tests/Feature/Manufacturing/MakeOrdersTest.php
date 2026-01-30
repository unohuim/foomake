<?php

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

    $this->makeUom = function (): Uom {
        $suffix = (string) Str::uuid();

        $category = UomCategory::query()->forceCreate([
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
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

    $this->makeRecipe = function (Tenant $tenant, Item $outputItem, bool $isActive = true): Recipe {
        return Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'is_active' => $isActive,
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

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();

    $storeResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.000000',
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

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    ($this->makeOrder)($tenant, $recipe, $user, [
        'output_quantity' => '2.500000',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $response
        ->assertSee('data-page="manufacturing-make-orders"', false)
        ->assertSee('data-payload="manufacturing-make-orders-payload"', false)
        ->assertSee('<script type="application/json"', false);

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload)->toHaveKey('make_orders');
    expect($payload['make_orders'])->toHaveCount(1);

    $order = $payload['make_orders'][0] ?? [];

    expect($order)->toHaveKeys(['id', 'recipe_id', 'output_item_id', 'output_item_name', 'output_quantity', 'status']);
    expect($order['output_item_name'])->toBe($output->name);
    expect($order['output_quantity'])->toBe('2.500000');
    expect($order['status'])->toBe('DRAFT');
});

test('make orders index is tenant scoped and empty state returns empty payload list', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-view');

    $uomA = ($this->makeUom)();
    $uomB = ($this->makeUom)();

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

    expect($payload['make_orders'])->toBeArray();
    expect($payload['make_orders'])->toHaveCount(0);

    ($this->makeOrder)($tenantA, $recipeA, $userA, [
        'output_quantity' => '3.000000',
        'status' => 'DRAFT',
    ]);

    $second = $this->actingAs($userA)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $payloadTwo = ($this->extractPayload)($second, 'manufacturing-make-orders-payload');

    expect($payloadTwo['make_orders'])->toHaveCount(1);
    expect($payloadTwo['make_orders'][0]['output_item_name'])->toBe($outputA->name);
});

test('create draft make order validates payload and does not create stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id', 'output_quantity']);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => 999999,
            'output_quantity' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '-1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '0',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.0000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);

    $beforeMoves = StockMove::query()->count();

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '2.000000',
        ])
        ->assertCreated();

    $makeOrderId = $response->json('data.id');
    $makeOrder = MakeOrder::query()->findOrFail($makeOrderId);

    expect($makeOrder->status)->toBe('DRAFT');
    expect($makeOrder->output_item_id)->toBe($output->id);
    expect($makeOrder->output_quantity)->toBe('2.000000');
    expect($makeOrder->created_by_user_id)->toBe($user->id);
    expect($makeOrder->made_at)->toBeNull();

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('create rejects inactive recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

test('schedule sets due date and status without creating stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)();
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

    $uom = ($this->makeUom)();
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

test('make creates stock moves once and sets made fields', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)();
    $inputA = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $inputB = ($this->makeItem)($tenant, $uom, 'Water', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true);
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

    expect((string) $moveByItem[$output->id]->quantity)->toBe('3.000000');
    expect($moveByItem[$output->id]->type)->toBe('receipt');
    expect($moveByItem[$output->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$output->id]->uom_id)->toBe($output->base_uom_id);
});

test('make is blocked when already made and creates no additional stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)();
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

    $uom = ($this->makeUom)();
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

    $uomB = ($this->makeUom)();
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);
    $userB = ($this->makeUser)($tenantB);

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipeB->id,
            'output_quantity' => '1.000000',
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
