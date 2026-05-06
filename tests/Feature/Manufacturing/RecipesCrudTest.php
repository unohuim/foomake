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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        bool $isDefault = false,
        string $name = 'Recipe A',
        string $outputQuantity = '1.000000'
    ): Recipe {
        return Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'name' => $name,
            'is_active' => $isActive,
            'is_default' => $isDefault,
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
    $uom = ($this->makeUom)($tenant);

    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');
    $recipe = ($this->makeRecipe)($tenant, $output, true, false);
    $line = ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Batch A',
            'output_quantity' => '12.500000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'name' => 'Batch A',
            'output_quantity' => '12.500000',
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

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $createResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Batch A',
            'output_quantity' => '12.500000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated();

    $recipeId = $createResponse->json('data.id');
    $recipe = Recipe::query()->findOrFail($recipeId);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'name' => 'Batch B',
            'output_quantity' => '24.750000',
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

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $nonManufacturable = ($this->makeItem)($tenant, $uom, 'Output B');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Batch A',
            'output_quantity' => '10.000000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Batch A')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.output_quantity', '10.000000');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $nonManufacturable->id,
            'name' => 'Batch B',
            'output_quantity' => '10.000000',
        ])
        ->assertStatus(422);

    expect(Recipe::query()->where('item_id', $output->id)->exists())->toBeTrue();
    expect(Recipe::query()->where('item_id', $nonManufacturable->id)->exists())->toBeFalse();
});

test('prevents changing output item when recipe has lines and returns stable error shape', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $otherOutput = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A');

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $otherOutput->id,
            'name' => 'Batch B',
            'output_quantity' => '8.000000',
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

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $otherOutput = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);

    $recipe = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $otherOutput->id,
            'name' => 'Batch B',
            'output_quantity' => '8.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.item_id', $otherOutput->id)
        ->assertJsonPath('data.name', 'Batch B');
});

test('recipes schema includes output_quantity and database default preserves scale for legacy-aligned inserts', function () {
    expect(Schema::hasColumn('recipes', 'output_quantity'))->toBeTrue();

    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Legacy Output', ['is_manufacturable' => true]);

    DB::table('recipes')->insert([
        'tenant_id' => $tenant->id,
        'item_id' => $output->id,
        'name' => 'Simple Recipe',
        'is_active' => true,
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stored = Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $output->id)
        ->firstOrFail();

    expect($stored->output_quantity)->toBe('0.000000');
});

test('recipe create requires output_quantity', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);
});

test('recipe create requires name', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'output_quantity' => '10.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('recipe create persists exact output_quantity string with canonical scale', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Batch of Patties',
            'output_quantity' => '54.125000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Batch of Patties')
        ->assertJsonPath('data.output_quantity', '54.125000');

    $recipe = Recipe::query()->findOrFail($response->json('data.id'));

    expect($recipe->name)->toBe('Batch of Patties');
    expect($recipe->output_quantity)->toBe('54.125000');
});

test('recipe create accepts zero output_quantity for legacy-compatible save flows', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Legacy Zero Batch',
            'output_quantity' => '0.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Legacy Zero Batch')
        ->assertJsonPath('data.output_quantity', '0.000000');

    $recipe = Recipe::query()->findOrFail($response->json('data.id'));

    expect($recipe->output_quantity)->toBe('0.000000');
});

test('recipe update persists exact output_quantity string with canonical scale', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, true, false, 'Batch A', '10.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'name' => 'Drum of Patties',
            'output_quantity' => '324.500000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Drum of Patties')
        ->assertJsonPath('data.output_quantity', '324.500000');

    $recipe->refresh();

    expect($recipe->name)->toBe('Drum of Patties');
    expect($recipe->output_quantity)->toBe('324.500000');
});

test('recipe update requires name', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, true, false, 'Batch A', '10.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'output_quantity' => '12.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('recipe update accepts zero output_quantity for legacy-compatible save flows', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, true, false, 'Batch A', '10.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'name' => 'Zero Output Batch',
            'output_quantity' => '0.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Zero Output Batch')
        ->assertJsonPath('data.output_quantity', '0.000000');

    $recipe->refresh();

    expect($recipe->output_quantity)->toBe('0.000000');
});

test('recipe create validation rejects invalid name payloads', function (?string $name) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $payload = [
        'item_id' => $output->id,
        'output_quantity' => '10.000000',
        'is_active' => true,
        'is_default' => false,
    ];

    if ($name !== null) {
        $payload['name'] = $name;
    }

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
})->with([
    null,
    '',
    str_repeat('a', 256),
]);

test('recipe creation still works for an item without an existing recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Fresh Output', ['is_manufacturable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Fresh Recipe',
            'output_quantity' => '6.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.item_id', $output->id)
        ->assertJsonPath('data.name', 'Fresh Recipe');
});

test('creating another recipe for an item with an existing recipe still works', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Repeatable Output', ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $output, true, false, 'Existing Recipe', '4.000000');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Second Recipe',
            'output_quantity' => '8.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.item_id', $output->id)
        ->assertJsonPath('data.name', 'Second Recipe');

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $output->id)
        ->count())->toBe(2);
});

test('recipe output_quantity validation rejects invalid create payloads', function (string|null $outputQuantity) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $payload = [
        'item_id' => $output->id,
        'name' => 'Batch A',
        'is_active' => true,
        'is_default' => false,
    ];

    if ($outputQuantity !== null) {
        $payload['output_quantity'] = $outputQuantity;
    }

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);
})->with([
    null,
    '-1.000000',
    'abc',
    '1.0000000',
    '1.1234567',
    ' ',
]);

test('recipe update validation rejects invalid name payloads', function (string $name) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, true, false, 'Batch A', '10.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'name' => $name,
            'output_quantity' => '10.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
})->with([
    '',
    str_repeat('a', 256),
]);

test('recipe output_quantity validation rejects invalid update payloads', function (string $outputQuantity) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, true, false, 'Batch A', '10.000000');

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'name' => 'Batch A',
            'output_quantity' => $outputQuantity,
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['output_quantity']);
})->with([
    '-1.000000',
    'abc',
    '1.0000000',
    '1.1234567',
    ' ',
]);

test('rejects line item when it equals output item on create', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
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

    $uom = ($this->makeUom)($tenant);
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

    $uom = ($this->makeUom)($tenant);
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

    $uom = ($this->makeUom)($tenant);
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

    $uom = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uom, 'Output B', ['is_manufacturable' => true]);
    $inputB = ($this->makeItem)($tenantB, $uom, 'Input B');

    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true, false);
    $lineB = ($this->addRecipeLine)($tenantB, $recipeB, $inputB, '1.000000');

    $this->actingAs($userA)
        ->patchJson(route('manufacturing.recipes.update', $recipeB), [
            'item_id' => $outputB->id,
            'name' => 'Other Tenant Batch',
            'output_quantity' => '1.000000',
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

    $uom = ($this->makeUom)($tenant);
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

test('allows multiple recipes for the same output item when names differ', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Batch One',
            'output_quantity' => '10.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Batch Two',
            'output_quantity' => '20.000000',
            'is_active' => true,
            'is_default' => false,
        ])
        ->assertCreated();

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $output->id)
        ->pluck('name')
        ->all())->toBe(['Batch One', 'Batch Two']);
});

test('is_default: setting a recipe default unsets prior default for same output item in same tenant (update)', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = User::factory()->for($tenant)->create();
    ($this->grantMakeOrdersManage)($user);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $recipeA = ($this->makeRecipe)($tenant, $output, true, true);
    $recipeB = ($this->makeRecipe)($tenant, $output, true, false);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipeB), [
            'item_id' => $output->id,
            'name' => 'Batch B',
            'output_quantity' => '1.000000',
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

    $uom = ($this->makeUom)($tenant);
    $outputA = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $outputB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);

    $a1 = ($this->makeRecipe)($tenant, $outputA, true, true);
    $a2 = ($this->makeRecipe)($tenant, $outputA, true, false);

    $b1 = ($this->makeRecipe)($tenant, $outputB, true, true);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $a2), [
            'item_id' => $outputA->id,
            'name' => 'Batch A2',
            'output_quantity' => '1.000000',
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

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);

    $outputA = ($this->makeItem)($tenantA, $uomA, 'Output A', ['is_manufacturable' => true]);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Output A (Other Tenant)', ['is_manufacturable' => true]);

    $a1 = ($this->makeRecipe)($tenantA, $outputA, true, true);
    $a2 = ($this->makeRecipe)($tenantA, $outputA, true, false);

    $b1 = ($this->makeRecipe)($tenantB, $outputB, true, true);

    $this->actingAs($userA)
        ->patchJson(route('manufacturing.recipes.update', $a2), [
            'item_id' => $outputA->id,
            'name' => 'Tenant A Batch',
            'output_quantity' => '1.000000',
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

    $uom = ($this->makeUom)($tenant);
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

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);

    $existingDefault = ($this->makeRecipe)($tenant, $output, true, true);

    $createResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Replacement Default',
            'output_quantity' => '1.000000',
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
