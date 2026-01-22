// tests/Feature/Manufacturing/RecipesReadOnlyTest.php

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
});

test('guests are redirected to login for recipes index and detail', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)();
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

    $uom = ($this->makeUom)();
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

    $uom = ($this->makeUom)();

    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $input = ($this->makeItem)($tenant, $uom, 'Input Flour', false);

    $recipe = ($this->makeRecipe)($tenant, $output, true);
    ($this->addRecipeLine)($tenant, $recipe, $input, '2.000000');

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Recipes')
        ->assertSee($output->name);

    $showResponse = $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe));

    $showResponse
        ->assertOk()
        ->assertSee($output->name)
        ->assertSee($input->name);

    /**
     * Quantity assertion:
     * - We only require 2 decimals for now.
     * - We also require the quantity to appear "with" the input item (same local HTML context),
     *   so we don't get a false positive from some unrelated "2.00" elsewhere.
     */
    $html = $showResponse->getContent();

    expect($html)->toMatch(
        '/Input Flour[\s\S]{0,800}2\.00|2\.00[\s\S]{0,800}Input Flour/'
    );
});

test('recipes index is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $user = User::factory()->for($tenantA)->create();
    ($this->grantInventoryRecipesView)($user);

    $uomA = ($this->makeUom)();
    $uomB = ($this->makeUom)();

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

    $uomB = ($this->makeUom)();
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Output B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipeB))
        ->assertNotFound();
});
