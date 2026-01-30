<?php

use App\Models\Item;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    $this->post(route('manufacturing.make-orders.execute'))
        ->assertRedirect(route('login'));
});

test('users without inventory-make-orders-view cannot access make orders page', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();
});

test('execute permission allows execute but not view access', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.000000',
        ])
        ->assertOk();
});

test('view permission can access make orders page and payload lists active recipes only', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)();
    $activeItem = ($this->makeItem)($tenant, $uom, 'Active Output', true);
    $inactiveItem = ($this->makeItem)($tenant, $uom, 'Inactive Output', true);

    $activeRecipe = ($this->makeRecipe)($tenant, $activeItem, true);
    ($this->makeRecipe)($tenant, $inactiveItem, false);

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $response
        ->assertSee('data-page="manufacturing-make-orders"', false)
        ->assertSee('data-payload="manufacturing-make-orders-payload"', false)
        ->assertSee('<script type="application/json"', false);

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload)->toHaveKey('recipes');
    expect($payload['recipes'])->toHaveCount(1);

    $recipePayload = $payload['recipes'][0] ?? [];

    expect($recipePayload)->toHaveKeys(['id', 'item_id', 'item_name']);
    expect($recipePayload['id'])->toBe($activeRecipe->id);
    expect($recipePayload['item_id'])->toBe($activeItem->id);
    expect($recipePayload['item_name'])->toBe($activeItem->name);
});

test('make orders index is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-view');

    $uomA = ($this->makeUom)();
    $uomB = ($this->makeUom)();

    $itemA = ($this->makeItem)($tenantA, $uomA, 'Tenant A Output', true);
    $itemB = ($this->makeItem)($tenantB, $uomB, 'Tenant B Output', true);

    ($this->makeRecipe)($tenantA, $itemA, true);
    ($this->makeRecipe)($tenantB, $itemB, true);

    $response = $this->actingAs($userA)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    $recipeNames = array_map(static function (array $recipe): string {
        return $recipe['item_name'] ?? '';
    }, $payload['recipes'] ?? []);

    expect($recipeNames)->toContain($itemA->name);
    expect($recipeNames)->not->toContain($itemB->name);
});

test('view permission cannot execute make orders', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.000000',
        ])
        ->assertForbidden();
});

test('execute endpoint validates missing and invalid payloads', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id', 'output_quantity']);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => 999999,
            'output_quantity' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '-1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '0',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.0000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);
});

test('execute rejects inactive recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, false);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Recipe must be active to execute.',
        ]);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('execute endpoint creates stock moves and returns summary payload', function () {
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

    $makeOrderTables = ['make_orders', 'manufacturing_orders', 'manufacturing_make_orders'];
    $existingMakeOrderTables = array_values(array_filter($makeOrderTables, static function (string $table): bool {
        return Schema::hasTable($table);
    }));

    $makeOrderCounts = [];

    foreach ($existingMakeOrderTables as $table) {
        $makeOrderCounts[$table] = DB::table($table)->count();
    }

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipe->id,
            'output_quantity' => '3.000000',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'success',
            'toast' => ['message', 'type'],
            'summary' => [
                'recipe_id',
                'output_item_id',
                'output_item_name',
                'output_quantity',
                'issue_count',
                'receipt_count',
                'move_count',
            ],
        ]);

    $moves = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('source_type', Recipe::class)
        ->where('source_id', $recipe->id)
        ->orderBy('id')
        ->get();

    expect($moves)->toHaveCount(3);

    $moveByItem = $moves->keyBy('item_id');

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

    expect($moves->first()->type)->toBe('issue');
    expect($moves->last()->type)->toBe('receipt');

    foreach ($existingMakeOrderTables as $table) {
        expect(DB::table($table)->count())->toBe($makeOrderCounts[$table]);
    }
});

test('execute endpoint enforces tenant isolation', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');

    $uomB = ($this->makeUom)();
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.execute'), [
            'recipe_id' => $recipeB->id,
            'output_quantity' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});
