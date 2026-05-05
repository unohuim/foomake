<?php

use App\Models\Item;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->uomCounter = 1;

    $this->makeTenant = function (string $name): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUom = function (Tenant $tenant, int $displayPrecision = 1): Uom {
        $suffix = (string) $this->uomCounter;
        $this->uomCounter++;

        $category = UomCategory::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $suffix,
            'symbol' => 'u' . $suffix,
            'display_precision' => $displayPrecision,
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

    $this->grantInventoryRecipesView = function (User $user): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'inventory-recipes-view',
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'Inventory',
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantMakeOrdersManage = function (User $user): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'inventory-make-orders-manage',
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'Inventory',
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };
});

test('guests are redirected to login for recipes index and detail', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->get(route('manufacturing.recipes.index'))
        ->assertRedirect(route('login'));

    $this->get(route('manufacturing.recipes.show', $recipe))
        ->assertRedirect(route('login'));
});

test('forbids users without inventory-recipes-view permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertForbidden();
});

test('forbids users with manage but without view permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertForbidden();
});

test('allows users with inventory-recipes-view permission to view recipes and recipe lines', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();

    expect(Gate::forUser($user)->allows('inventory-recipes-view'))->toBeFalse();

    ($this->grantInventoryRecipesView)($user);

    expect(Gate::forUser($user)->allows('inventory-recipes-view'))->toBeTrue();

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 3);

    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Flour', false);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Batch of Patties', '54.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.000000');

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Recipes')
        ->assertSee('Batch of Patties')
        ->assertSee('Output per Run')
        ->assertSee('54.0');

    $showResponse = $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe));

    $showResponse
        ->assertOk()
        ->assertSee('Batch of Patties')
        ->assertSee($input->name)
        ->assertSee('Output per Run')
        ->assertSee('54.0');

    $payload = ($this->extractPayload)($showResponse, 'manufacturing-recipes-show-payload');

    expect($payload['recipe']['name'] ?? null)->toBe('Batch of Patties');
    expect($payload['recipe']['output_quantity'] ?? null)->toBe('54.000000');
    expect($payload['recipe']['output_quantity_display'] ?? null)->toBe('54.0');
    expect($payload['lines'][0]['item_name'] ?? null)->toBe('Input Flour');
    expect($payload['lines'][0])->toHaveKey('quantity_display');
    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.000');
});

test('view permission shows pages but not manage controls and can_manage payload is false', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $indexResponse = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk();

    $indexResponse
        ->assertSee('data-page="manufacturing-recipes-index"', false)
        ->assertSee('data-payload="manufacturing-recipes-index-payload"', false)
        ->assertSee('<script type="application/json"', false)
        ->assertSee('"can_manage":false', false);

    $showResponse = $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk();

    $showResponse
        ->assertSee('data-page="manufacturing-recipes-show"', false)
        ->assertSee('data-payload="manufacturing-recipes-show-payload"', false)
        ->assertSee('<script type="application/json"', false)
        ->assertSee('"can_manage":false', false);
});

test('view and manage permissions include can_manage true in payload', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('"can_manage":true', false);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertSee('"can_manage":true', false);
});

test('recipes index payload includes the shared navigation state refresh url', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);
    ($this->grantMakeOrdersManage)($user);

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('manufacturing-recipes-index-payload', false);

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect($payload['navigationStateUrl'] ?? null)->toBe(url('/navigation/state'));
});

test('index payload includes recipe output quantity per run display', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $uom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    ($this->makeRecipe)($tenant, $output, true, 'Drum of Patties', '324.125000');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect($payload['recipes'][0]['name'] ?? null)->toBe('Drum of Patties');
    expect($payload['recipes'][0]['output_quantity'] ?? null)->toBe('324.125000');
    expect($payload['recipes'][0]['output_quantity_display'] ?? null)->toBe('324.125');
});

test('recipes index can distinguish multiple recipes for the same output item by name', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Patties', true);

    ($this->makeRecipe)($tenant, $output, true, 'Batch of Patties', '54.000000');
    ($this->makeRecipe)($tenant, $output, true, 'Drum of Patties', '324.000000');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Batch of Patties')
        ->assertSee('Drum of Patties');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('name')->all())
        ->toContain('Batch of Patties', 'Drum of Patties');
});

test('show payload uses output item uom precision for output quantity per run display', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 2);
    $lineUom = ($this->makeUom)($tenant, 6);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input A');
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Precision Batch', '12.345000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '0.500000');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['recipe']['name'] ?? null)->toBe('Precision Batch');
    expect($payload['recipe']['output_quantity_display'] ?? null)->toBe('12.35');
});

test('show page payload renders line quantities using each line item uom display precision', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUomZero = ($this->makeUom)($tenant, 0);
    $lineUomSix = ($this->makeUom)($tenant, 6);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $inputA = ($this->makeItem)($tenant, $lineUomZero, 'Input One', false);
    $inputB = ($this->makeItem)($tenant, $lineUomSix, 'Input Two', false);

    $recipe = ($this->makeRecipe)($tenant, $output, true);
    ($this->addRecipeLine)($tenant, $recipe, $inputA, '2.000000');
    ($this->addRecipeLine)($tenant, $recipe, $inputB, '1.250000');

    $showResponse = $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk();

    $payload = ($this->extractPayload)($showResponse, 'manufacturing-recipes-show-payload');

    $lineByItem = collect($payload['lines'] ?? [])->mapWithKeys(function (array $line): array {
        return [($line['item_name'] ?? '') => ($line['quantity_display'] ?? null)];
    });

    expect($lineByItem->get('Input One'))->toBe('2');
    expect($lineByItem->get('Input Two'))->toBe('1.250000');
});

test('show payload includes quantity_display key for every recipe line', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $inputA = ($this->makeItem)($tenant, $uom, 'Input One');
    $inputB = ($this->makeItem)($tenant, $uom, 'Input Two');
    $recipe = ($this->makeRecipe)($tenant, $output);

    ($this->addRecipeLine)($tenant, $recipe, $inputA, '1.000000');
    ($this->addRecipeLine)($tenant, $recipe, $inputB, '2.345000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    foreach ($payload['lines'] as $line) {
        expect($line)->toHaveKey('quantity_display');
    }
});

test('show payload renders precision 0 for recipe line quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 0);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Zero');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.900000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('3');
});

test('show payload renders precision 1 for recipe line quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 2);
    $lineUom = ($this->makeUom)($tenant, 1);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input One');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.140000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.1');
});

test('show payload rounds up at precision 2 for recipe line quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Round Up');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.345000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.35');
});

test('show payload rounds down at precision 2 for recipe line quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Round Down');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.344000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.34');
});

test('show payload preserves trailing zeros at precision 3 for recipe line quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Three');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.100000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.100');
});

test('show payload preserves trailing zeros at precision 6 for recipe line quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 6);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Six');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '0.005000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('0.005000');
});

test('show payload renders zero quantity with configured precision 3', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Zero Three');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '0.000000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('0.000');
});

test('show payload uses line item uom precision instead of output item uom precision', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 6);
    $lineUom = ($this->makeUom)($tenant, 0);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Uses Own Uom');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.100000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2');
});

test('show payload defaults recipe line quantity display to one decimal when uom precision omitted', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $lineUom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $lineUom, 'Input Default One');
    $recipe = ($this->makeRecipe)($tenant, $output);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.100000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['lines'][0]['quantity_display'] ?? null)->toBe('2.1');
});

test('show payload can render three recipe lines with distinct precision outputs', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantInventoryRecipesView)($user);

    $outputUom = ($this->makeUom)($tenant, 1);
    $uomZero = ($this->makeUom)($tenant, 0);
    $uomThree = ($this->makeUom)($tenant, 3);
    $uomSix = ($this->makeUom)($tenant, 6);
    $output = ($this->makeItem)($tenant, $outputUom, 'Output A', true);
    $inputA = ($this->makeItem)($tenant, $uomZero, 'Input Zero');
    $inputB = ($this->makeItem)($tenant, $uomThree, 'Input Three');
    $inputC = ($this->makeItem)($tenant, $uomSix, 'Input Six');
    $recipe = ($this->makeRecipe)($tenant, $output);

    ($this->addRecipeLine)($tenant, $recipe, $inputA, '2.900000');
    ($this->addRecipeLine)($tenant, $recipe, $inputB, '2.100000');
    ($this->addRecipeLine)($tenant, $recipe, $inputC, '0.005000');

    $response = $this->actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    $lineByItem = collect($payload['lines'] ?? [])->mapWithKeys(function (array $line): array {
        return [($line['item_name'] ?? '') => ($line['quantity_display'] ?? null)];
    });

    expect($lineByItem->get('Input Zero'))->toBe('3');
    expect($lineByItem->get('Input Three'))->toBe('2.100');
    expect($lineByItem->get('Input Six'))->toBe('0.005000');
});

test('recipes index is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $user = User::factory()->for($tenantA)->create();
    ($this->grantInventoryRecipesView)($user);

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);

    $outputA = ($this->makeItem)($tenantA, $uomA, 'Output A', true);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Output B', true);

    ($this->makeRecipe)($tenantA, $outputA, true);
    ($this->makeRecipe)($tenantB, $outputB, true);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee($outputA->name)
        ->assertDontSee($outputB->name);
});

test('returns 404 when accessing another tenant recipe', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $user = User::factory()->for($tenantA)->create();
    ($this->grantInventoryRecipesView)($user);

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Output B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipeB))
        ->assertNotFound();
});
