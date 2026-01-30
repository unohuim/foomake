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

    $this->makeItem = function (Tenant $tenant, Uom $uom, string $name, array $overrides = []): Item {
        return Item::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->makeRecipe = function (
        Tenant $tenant,
        Item $outputItem,
        bool $isActive = true,
        bool $isDefault = false
    ): Recipe {
        return Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'is_active' => $isActive,
            'is_default' => $isDefault,
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
});

test('forbids recipe and line writes without inventory-make-orders-manage permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    $uom = ($this->makeUom)();

    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');
    $recipe = ($this->makeRecipe)($tenant, $output, true, false);
    $line = ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'is_active' => false,
            'is_default' => false,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.recipes.destroy', $recipe))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $input->id,
            'quantity' => '1.000000',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.lines.update', [$recipe, $line]), [
            'item_id' => $input->id,
            'quantity' => '1.000000',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.recipes.lines.destroy', [$recipe, $line]))
        ->assertForbidden();
});

test('allows recipe and line writes with inventory-make-orders-manage permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $createResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated();

    $recipeId = $createResponse->json('data.id');
    $recipe = Recipe::query()->findOrFail($recipeId);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'is_active' => false,
            'is_default' => false,
        ])
        ->assertOk();

    $lineResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $input->id,
            'quantity' => '2.000000',
        ])
        ->assertCreated();

    $lineId = $lineResponse->json('data.id');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.lines.update', [$recipe, $lineId]), [
            'item_id' => $input->id,
            'quantity' => '3.000000',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.recipes.lines.destroy', [$recipe, $lineId]))
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.recipes.destroy', $recipe))
        ->assertOk();
});

test('creates recipes with default active flag, default is_default false, and requires manufacturable output', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $nonManufacturable = ($this->makeItem)($tenant, $uom, 'Output B');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_default', false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $nonManufacturable->id,
        ])
        ->assertStatus(422);

    expect(Recipe::query()->where('item_id', $output->id)->exists())->toBeTrue();
    expect(Recipe::query()->where('item_id', $nonManufacturable->id)->exists())->toBeFalse();
});

test('prevents changing output item when recipe has lines and returns stable error shape', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $otherOutput = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $otherOutput->id,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                'item_id',
            ],
        ]);
});

test('allows changing output item when recipe has zero lines', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $otherOutput = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $otherOutput->id,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.item_id', $otherOutput->id);
});

test('rejects line item when it equals output item on create', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $output->id,
            'quantity' => '1.000000',
        ])
        ->assertStatus(422);
});

test('rejects line item when it equals output item on update', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);
    $line = ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.lines.update', [$recipe, $line]), [
            'item_id' => $output->id,
            'quantity' => '1.000000',
        ])
        ->assertStatus(422);
});

test('accepts quantity at canonical minimum positive and rejects zero at canonical scale', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $input->id,
            'quantity' => '0.000001',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $input->id,
            'quantity' => '0.000000',
        ])
        ->assertStatus(422);
});

test('rejects invalid recipe line quantities and returns stable error shape', function (string $quantity) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $input->id,
            'quantity' => $quantity,
        ])
        ->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                'quantity',
            ],
        ]);
})->with([
    '0',
    '0.000000',
    '1.0000000',
    '-1.000000',
    '1.1234567',
    'abc',
    '',
    ' ',
]);

test('returns 404 for cross-tenant recipe and line writes', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = User::factory()->for($tenantA)->create();
    ($this->grantMakeOrdersManage)($userA);

    $uom = ($this->makeUom)();
    $outputB = ($this->makeItem)($tenantB, $uom, 'Output B', ['is_manufacturable' => true]);
    $inputB = ($this->makeItem)($tenantB, $uom, 'Input B');

    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true, false);
    $lineB = ($this->addRecipeLine)($tenantB, $recipeB, $inputB, '1.000000');

    $this->actingAs($userA)
        ->patchJson(route('manufacturing.recipes.update', $recipeB), [
            'item_id' => $outputB->id,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertNotFound();

    $this->actingAs($userA)
        ->deleteJson(route('manufacturing.recipes.destroy', $recipeB))
        ->assertNotFound();

    $this->actingAs($userA)
        ->postJson(route('manufacturing.recipes.lines.store', $recipeB), [
            'item_id' => $inputB->id,
            'quantity' => '1.000000',
        ])
        ->assertNotFound();

    $this->actingAs($userA)
        ->patchJson(route('manufacturing.recipes.lines.update', [$recipeB, $lineB]), [
            'item_id' => $inputB->id,
            'quantity' => '2.000000',
        ])
        ->assertNotFound();

    $this->actingAs($userA)
        ->deleteJson(route('manufacturing.recipes.lines.destroy', [$recipeB, $lineB]))
        ->assertNotFound();
});

test('deleting a recipe removes its lines', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);
    $line = ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.recipes.destroy', $recipe))
        ->assertOk();

    expect(Recipe::query()->whereKey($recipe->id)->exists())->toBeFalse();
    expect(RecipeLine::query()->whereKey($line->id)->exists())->toBeFalse();
});

test('is_default: setting a recipe default unsets prior default for same output item in same tenant (update)', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $recipeA = ($this->makeRecipe)($tenant, $output, true, true);
    $recipeB = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipeB), [
            'item_id' => $output->id,
            'is_active' => true,
            'is_default' => true,
        ])
        ->assertOk();

    $recipeA->refresh();
    $recipeB->refresh();

    expect((bool) $recipeB->is_default)->toBeTrue();
    expect((bool) $recipeA->is_default)->toBeFalse();
});

test('is_default: setting default does not affect other output items', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $outputA = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $outputB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);

    $a1 = ($this->makeRecipe)($tenant, $outputA, true, true);
    $a2 = ($this->makeRecipe)($tenant, $outputA, true, false);

    $b1 = ($this->makeRecipe)($tenant, $outputB, true, true);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $a2), [
            'item_id' => $outputA->id,
            'is_active' => true,
            'is_default' => true,
        ])
        ->assertOk();

    $a1->refresh();
    $a2->refresh();
    $b1->refresh();

    expect((bool) $a2->is_default)->toBeTrue();
    expect((bool) $a1->is_default)->toBeFalse();
    expect((bool) $b1->is_default)->toBeTrue();
});

test('is_default: setting default does not affect other tenants', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = User::factory()->for($tenantA)->create();
    ($this->grantMakeOrdersManage)($userA);

    $uom = ($this->makeUom)();

    $outputA = ($this->makeItem)($tenantA, $uom, 'Output A', ['is_manufacturable' => true]);
    $outputB = ($this->makeItem)($tenantB, $uom, 'Output A (Other Tenant)', ['is_manufacturable' => true]);

    $a1 = ($this->makeRecipe)($tenantA, $outputA, true, true);
    $a2 = ($this->makeRecipe)($tenantA, $outputA, true, false);

    $b1 = ($this->makeRecipe)($tenantB, $outputB, true, true);

    $this->actingAs($userA)
        ->patchJson(route('manufacturing.recipes.update', $a2), [
            'item_id' => $outputA->id,
            'is_active' => true,
            'is_default' => true,
        ])
        ->assertOk();

    $a1->refresh();
    $a2->refresh();
    $b1->refresh();

    expect((bool) $a2->is_default)->toBeTrue();
    expect((bool) $a1->is_default)->toBeFalse();
    expect((bool) $b1->is_default)->toBeTrue();
});

test('is_default: deleting the default recipe leaves no default (no auto-promotion)', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $defaultRecipe = ($this->makeRecipe)($tenant, $output, true, true);
    $otherRecipe = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.recipes.destroy', $defaultRecipe))
        ->assertOk();

    $otherRecipe->refresh();

    expect((bool) $otherRecipe->is_default)->toBeFalse();
    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $output->id)
        ->where('is_default', true)
        ->exists()
    )->toBeFalse();
});

test('is_default: can set default on create and it unsets prior default for same output item in same tenant', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)();
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $existingDefault = ($this->makeRecipe)($tenant, $output, true, true);

    $createResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'is_active' => true,
            'is_default' => true,
        ])
        ->assertCreated();

    $newId = $createResponse->json('data.id');

    $existingDefault->refresh();
    $newDefault = Recipe::query()->findOrFail($newId);

    expect((bool) $newDefault->is_default)->toBeTrue();
    expect((bool) $existingDefault->is_default)->toBeFalse();
});
