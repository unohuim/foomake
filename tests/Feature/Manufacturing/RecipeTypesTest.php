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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manufacturingType = 'manufacturing';
    $this->fulfillmentType = 'fulfillment';

    $this->makeTenant = function (string $name): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUom = function (Tenant $tenant, int $displayPrecision = 1): Uom {
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
            'display_precision' => $displayPrecision,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, string $name, bool|array $manufacturable = false): Item {
        $overrides = is_array($manufacturable)
            ? $manufacturable
            : ['is_manufacturable' => $manufacturable];

        return Item::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
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

    $this->makeRecipe = function (
        Tenant $tenant,
        Item $outputItem,
        string $recipeType,
        bool $isActive = true,
        string $name = 'Recipe A',
        string $outputQuantity = '1.000000'
    ): Recipe {
        return Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'recipe_type' => $recipeType,
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
            'status' => MakeOrder::STATUS_DRAFT,
            'due_date' => null,
            'scheduled_at' => null,
            'made_at' => null,
            'created_by_user_id' => $user->id,
            'made_by_user_id' => null,
        ], $overrides));
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

it('1. recipes schema includes recipe_type', function () {
    expect(Schema::hasColumn('recipes', 'recipe_type'))->toBeTrue();
});

it('2. recipe_type is required on create', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'name' => 'Recipe A',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('3. only manufacturing and fulfillment recipe types are accepted', function (string $recipeType) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => $recipeType,
            'name' => 'Recipe A',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
})->with([
    'assembly',
    'kit',
    '',
]);

it('4. recipe create form defaults recipe_type to manufacturing', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $createPartialSource = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain("recipe_type: 'manufacturing'");
    expect($createPartialSource)->toContain('selected-value="manufacturing"');
});

it('5. invalid recipe type is rejected on update', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, $this->manufacturingType);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'recipe_type' => 'assembly',
            'name' => 'Recipe A',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('6. create recipe with manufacturing type succeeds', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => $this->manufacturingType,
            'name' => 'Manufacturing Recipe',
            'output_quantity' => '2.000000',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.recipe_type', $this->manufacturingType);

    $recipe = Recipe::query()->findOrFail($response->json('data.id'));

    expect($recipe->recipe_type)->toBe($this->manufacturingType)
        ->and($recipe->output_quantity)->toBe('2.000000');
});

it('7. create recipe with fulfillment type succeeds', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_sellable' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => $this->fulfillmentType,
            'name' => 'Fulfillment Recipe',
            'output_quantity' => '2.000000',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.recipe_type', $this->fulfillmentType);

    $recipe = Recipe::query()->findOrFail($response->json('data.id'));

    expect($recipe->recipe_type)->toBe($this->fulfillmentType)
        ->and($recipe->output_quantity)->toBe('1.000000');
});

it('8. edit recipe type from manufacturing to fulfillment', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, $this->manufacturingType);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'recipe_type' => $this->fulfillmentType,
            'name' => 'Recipe A',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.recipe_type', $this->fulfillmentType);

    expect($recipe->fresh()->recipe_type)->toBe($this->fulfillmentType);
});

it('9. edit recipe type from fulfillment to manufacturing', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $output, $this->fulfillmentType);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $output->id,
            'recipe_type' => $this->manufacturingType,
            'name' => 'Recipe A',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.recipe_type', $this->manufacturingType);

    expect($recipe->fresh()->recipe_type)->toBe($this->manufacturingType);
});

it('10. recipe create validation error display exists for invalid or missing type', function () {
    $createSource = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));
    $pageSource = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($createSource)->toContain('createErrors.recipe_type[0]')
        ->and($pageSource)->toContain('recipe_type: []');
});

it('11. recipe edit validation error display exists for invalid or missing type', function () {
    $editSource = File::get(resource_path('views/manufacturing/recipes/partials/edit-recipe-slide-over.blade.php'));
    $pageSource = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $showPageSource = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));

    expect($editSource)->toContain('editErrors.recipe_type[0]')
        ->and($pageSource)->toContain('recipe_type: []')
        ->and($showPageSource)->toContain('recipe_type: []');
});

it('12. recipes index shows both manufacturing and fulfillment recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $itemB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenant, $itemA, $this->manufacturingType, true, 'Manufacturing Recipe');
    ($this->makeRecipe)($tenant, $itemB, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Manufacturing Recipe')
        ->assertSee('Fulfillment Recipe');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('recipe_type')->all())
        ->toContain($this->manufacturingType, $this->fulfillmentType);
});

it('13. recipes index displays a Type column', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/index.blade.php'));

    expect($source)->toContain("{{ __('Type') }}");
});

it('14. recipe type labels render as Manufacturing and Fulfillment', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $itemB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenant, $itemA, $this->manufacturingType, true, 'Manufacturing Recipe');
    ($this->makeRecipe)($tenant, $itemB, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Manufacturing')
        ->assertSee('Fulfillment');
});

it('15. recipes index includes a type filter', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/index.blade.php'));

    expect($source)->toContain('recipe_type')
        ->and($source)->toContain('All Types');
});

it('16. filtering by manufacturing shows only manufacturing recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $itemB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenant, $itemA, $this->manufacturingType, true, 'Manufacturing Recipe');
    ($this->makeRecipe)($tenant, $itemB, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index', ['recipe_type' => $this->manufacturingType]))
        ->assertOk()
        ->assertSee('Manufacturing Recipe')
        ->assertDontSee('Fulfillment Recipe');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('recipe_type')->unique()->all())
        ->toBe([$this->manufacturingType]);
});

it('17. filtering by fulfillment shows only fulfillment recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $itemB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenant, $itemA, $this->manufacturingType, true, 'Manufacturing Recipe');
    ($this->makeRecipe)($tenant, $itemB, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index', ['recipe_type' => $this->fulfillmentType]))
        ->assertOk()
        ->assertSee('Fulfillment Recipe')
        ->assertDontSee('Manufacturing Recipe');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('recipe_type')->unique()->all())
        ->toBe([$this->fulfillmentType]);
});

it('18. recipe detail page shows recipe type', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Fulfillment');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-show-payload');

    expect($payload['recipe']['recipe_type'] ?? null)->toBe($this->fulfillmentType);
});

it('19. tenant isolation is respected for recipe type visibility', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-recipes-view');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);

    $itemA = ($this->makeItem)($tenantA, $uomA, 'Output A', true);
    $itemB = ($this->makeItem)($tenantB, $uomB, 'Output B', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenantA, $itemA, $this->manufacturingType, true, 'Tenant A Recipe');
    ($this->makeRecipe)($tenantB, $itemB, $this->fulfillmentType, true, 'Tenant B Recipe');

    $response = $this->actingAs($userA)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Tenant A Recipe')
        ->assertDontSee('Tenant B Recipe');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('name')->all())
        ->toBe(['Tenant A Recipe']);
});

it('20. make order recipe selector only includes manufacturing recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $itemB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenant, $itemA, $this->manufacturingType, true, 'Manufacturing Recipe');
    ($this->makeRecipe)($tenant, $itemB, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk()
        ->assertSee('Manufacturing Recipe')
        ->assertDontSee('Fulfillment Recipe');

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('recipe_type')->unique()->all())
        ->toBe([$this->manufacturingType]);
});

it('21. fulfillment recipes are excluded from make orders', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);

    ($this->makeRecipe)($tenant, $item, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.make-orders.index'))->assertOk(),
        'manufacturing-make-orders-payload'
    );

    expect(collect($payload['recipes'] ?? [])->pluck('name')->all())
        ->not->toContain('Fulfillment Recipe');
});

it('22. attempting to create a make order with a fulfillment recipe is rejected', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

it('23. attempting to schedule a make order with a fulfillment recipe is rejected', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, $this->fulfillmentType, true, 'Fulfillment Recipe');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-05-20',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

it('24. attempting to execute a make order with a fulfillment recipe is rejected', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, 'Output A', ['is_sellable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A', false);
    $recipe = ($this->makeRecipe)($tenant, $item, $this->fulfillmentType, true, 'Fulfillment Recipe');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-05-20',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

it('25. existing manufacturing recipe execution still works', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, $this->manufacturingType, true, 'Bread Recipe', '10.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '2.000000',
        ])
        ->assertCreated();

    $makeOrder = MakeOrder::query()->findOrFail($response->json('data.id'));

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-05-20',
        ])
        ->assertOk();

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    expect(StockMove::query()->count())->toBeGreaterThan($beforeMoves);
});

it('26. ecommerce import does not auto create fulfillment recipes in this pr', function () {
    $source = File::get(app_path('Http/Controllers/SalesProductController.php'));

    expect($source)->not->toContain('recipe_type')
        ->and($source)->not->toContain('Recipe::');
});

it('27. no sales order fulfillment behavior is introduced in routes', function () {
    $source = File::get(base_path('routes/web.php'));

    expect($source)->not->toContain('sales.fulfillment')
        ->and($source)->not->toContain('/sales/fulfillment');
});

it('28. recipe lines behavior remains intact for fulfillment recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_sellable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Input A', false);
    $recipe = ($this->makeRecipe)($tenant, $output, $this->fulfillmentType, true, 'Fulfillment Recipe');

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.lines.store', $recipe), [
            'item_id' => $input->id,
            'quantity' => '2.000000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.item_id', $input->id)
        ->assertJsonPath('data.quantity', '2.000000');

    expect($recipe->lines()->count())->toBe(1);
});

it('29. direct recipe create without recipe_type defaults to manufacturing', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);

    $recipe = Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $output->id,
        'name' => 'Default Type Recipe',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($recipe->recipe_type)->toBe(Recipe::TYPE_MANUFACTURING);
});

it('30. direct recipe create with explicit fulfillment type persists fulfillment', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', ['is_sellable' => true]);

    $recipe = Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $output->id,
        'recipe_type' => Recipe::TYPE_FULFILLMENT,
        'name' => 'Fulfillment Type Recipe',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($recipe->recipe_type)->toBe(Recipe::TYPE_FULFILLMENT);
});

it('31. direct recipe create with invalid explicit recipe_type is rejected', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);

    expect(function () use ($tenant, $output): void {
        Recipe::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $output->id,
            'recipe_type' => 'assembly',
            'name' => 'Invalid Type Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
            'is_default' => false,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Recipe type is invalid.');
});

it('32. manufacturable-only items appear in the recipe output picker', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    ($this->makeItem)($tenant, $uom, 'Manufacturable Only', ['is_manufacturable' => true, 'is_sellable' => false]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(),
        'manufacturing-recipes-index-payload'
    );

    expect(collect($payload['manufacturable_items'] ?? [])->pluck('name')->all())
        ->toContain('Manufacturable Only');
});

it('33. sellable-only items appear in the recipe output picker', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    ($this->makeItem)($tenant, $uom, 'Sellable Only', ['is_manufacturable' => false, 'is_sellable' => true]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(),
        'manufacturing-recipes-index-payload'
    );

    expect(collect($payload['manufacturable_items'] ?? [])->pluck('name')->all())
        ->toContain('Sellable Only');
});

it('34. items with both sellable and manufacturable flags appear in the recipe output picker', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    ($this->makeItem)($tenant, $uom, 'Both Flags', ['is_manufacturable' => true, 'is_sellable' => true]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(),
        'manufacturing-recipes-index-payload'
    );

    expect(collect($payload['manufacturable_items'] ?? [])->pluck('name')->all())
        ->toContain('Both Flags');
});

it('35. items with neither sellable nor manufacturable flags are excluded from the recipe output picker', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    ($this->makeItem)($tenant, $uom, 'Neither Flag', ['is_manufacturable' => false, 'is_sellable' => false]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(),
        'manufacturing-recipes-index-payload'
    );

    expect(collect($payload['manufacturable_items'] ?? [])->pluck('name')->all())
        ->not->toContain('Neither Flag');
});

it('36. tenant isolation is respected for recipe output picker items', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-recipes-view');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);

    ($this->makeItem)($tenantA, $uomA, 'Tenant A Output', ['is_manufacturable' => true]);
    ($this->makeItem)($tenantB, $uomB, 'Tenant B Output', ['is_sellable' => true]);

    $payload = ($this->extractPayload)(
        $this->actingAs($userA)->get(route('manufacturing.recipes.index'))->assertOk(),
        'manufacturing-recipes-index-payload'
    );

    expect(collect($payload['manufacturable_items'] ?? [])->pluck('name')->all())
        ->toContain('Tenant A Output')
        ->not->toContain('Tenant B Output');
});

it('37. manufacturable-only item allows manufacturing recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Manufacturable Only', ['is_manufacturable' => true, 'is_sellable' => false]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Manufacturing Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertCreated();
});

it('38. manufacturable-only item rejects fulfillment recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Manufacturable Only', ['is_manufacturable' => true, 'is_sellable' => false]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => 'Fulfillment Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('39. sellable-only item allows fulfillment recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sellable Only', ['is_manufacturable' => false, 'is_sellable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => 'Fulfillment Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertCreated();
});

it('40. sellable-only item rejects manufacturing recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sellable Only', ['is_manufacturable' => false, 'is_sellable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Manufacturing Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('41. items with both flags allow manufacturing recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Both Flags', ['is_manufacturable' => true, 'is_sellable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Manufacturing Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertCreated();
});

it('42. items with both flags allow fulfillment recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Both Flags', ['is_manufacturable' => true, 'is_sellable' => true]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => 'Fulfillment Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertCreated();
});

it('43. items with neither flag reject manufacturing recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Neither Flag', ['is_manufacturable' => false, 'is_sellable' => false]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Manufacturing Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('44. items with neither flag reject fulfillment recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Neither Flag', ['is_manufacturable' => false, 'is_sellable' => false]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => 'Fulfillment Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('45. create recipe page module limits type options to manufacturing for manufacturable-only items', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($source)->toContain("if (allowedValues.includes('manufacturing')) {")
        ->and($source)->toContain('availableCreateRecipeTypeOptions()')
        ->and($source)->toContain('allowed_recipe_types');
});

it('46. create recipe page module supports fulfillment-only item type options', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($source)->toContain("return allowedValues[0];")
        ->and($source)->toContain("return 'Fulfillment';")
        ->and($source)->toContain('syncCreateRecipeType()');
});

it('47. create recipe page module preserves both recipe type options for dual-flag items', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($source)->toContain('allRecipeTypeOptions()')
        ->and($source)->toContain("value: 'manufacturing'")
        ->and($source)->toContain("value: 'fulfillment'");
});

it('48. direct ajax create cannot bypass item and recipe type eligibility', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Neither Flag', ['is_manufacturable' => false, 'is_sellable' => false]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Blocked Recipe',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('49. direct ajax update cannot bypass item and recipe type eligibility', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $recipeItem = ($this->makeItem)($tenant, $uom, 'Both Flags', ['is_manufacturable' => true, 'is_sellable' => true]);
    $invalidItem = ($this->makeItem)($tenant, $uom, 'Sellable Only', ['is_manufacturable' => false, 'is_sellable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $recipeItem, Recipe::TYPE_MANUFACTURING);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.recipes.update', $recipe), [
            'item_id' => $invalidItem->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Blocked Update',
            'output_quantity' => '1.000000',
            'is_active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_type']);
});

it('50. create recipe dropdown renders manufacturing and fulfillment before item selection', function () {
    $source = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('<x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>')
        ->and($source)->toContain('<x-dropdown-option value="fulfillment">Fulfillment</x-dropdown-option>')
        ->and($source)->toContain('options-expression="availableCreateRecipeTypeOptions()"');
});

it('51. selected item dynamically limits recipe type options', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($source)->toContain('recipeTypeOptionsForItem(itemId)')
        ->and($source)->toContain('allowed_recipe_types')
        ->and($source)->toContain('syncCreateRecipeType()');
});

it('52. fulfillment selection defaults output quantity to one in the recipes page modules', function () {
    $indexSource = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $showSource = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));

    expect($indexSource)->toContain('resolvedCreateOutputQuantity()')
        ->and($indexSource)->toContain("? '1.000000'")
        ->and($showSource)->toContain('resolvedEditOutputQuantity()')
        ->and($showSource)->toContain("? '1.000000'");
});

it('53. fulfillment output quantity input is disabled in create and edit forms', function () {
    $createSource = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));
    $editSource = File::get(resource_path('views/manufacturing/recipes/partials/edit-recipe-slide-over.blade.php'));

    expect($createSource)->toContain(':disabled="isFulfillmentRecipeType(createForm.recipe_type)"')
        ->and($editSource)->toContain(':disabled="isFulfillmentRecipeType(editForm.recipe_type)"');
});

it('54. fulfillment recipe stores output quantity as 1.000000 regardless of submitted value', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $uom, 'Sellable Only', ['is_manufacturable' => false, 'is_sellable' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_FULFILLMENT,
            'name' => 'Fulfillment Recipe',
            'output_quantity' => '9.875000',
            'is_active' => true,
        ])
        ->assertCreated();

    $recipe = Recipe::query()->findOrFail($response->json('data.id'));

    expect($recipe->output_quantity)->toBe('1.000000');
});

it('55. manufacturing recipe output quantity remains editable', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $uom, 'Manufacturable Only', ['is_manufacturable' => true, 'is_sellable' => false]);

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.store'), [
            'item_id' => $output->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Manufacturing Recipe',
            'output_quantity' => '2.125000',
            'is_active' => true,
        ])
        ->assertCreated();

    $recipe = Recipe::query()->findOrFail($response->json('data.id'));

    expect($recipe->output_quantity)->toBe('2.125000');
});

it('56. recipe output picker payload includes selected item base uom display precision', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant, 3);
    ($this->makeItem)($tenant, $uom, 'Precision Item', ['is_manufacturable' => true, 'is_sellable' => true]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(),
        'manufacturing-recipes-index-payload'
    );

    $item = collect($payload['manufacturable_items'] ?? [])->firstWhere('name', 'Precision Item');

    expect($item['uom_display_precision'] ?? null)->toBe(3);
});

it('57. output quantity precision follows selected output item base uom display precision in recipes page modules', function () {
    $indexSource = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $showSource = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));

    expect($indexSource)->toContain('selectedOutputItemPrecision(itemId)')
        ->and($indexSource)->toContain('String(Math.round(parsedValue))')
        ->and($indexSource)->toContain('parsedValue.toFixed(normalizedPrecision)')
        ->and($showSource)->toContain('selectedOutputItemPrecision(itemId)')
        ->and($showSource)->toContain('String(Math.round(parsedValue))')
        ->and($showSource)->toContain('parsedValue.toFixed(normalizedPrecision)');
});

it('58. selecting output item sets create recipe name to the selected item display name', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($source)->toContain('syncCreateNameFromSelectedItem()')
        ->and($source)->toContain('this.createForm.name = this.selectedOutputItemDisplayName(this.createForm.item_id);');
});

it('59. selecting a different output item updates create recipe name to the new item display name', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $createSource = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('this.$watch(\'createForm.item_id\', () => {')
        ->and($source)->toContain('this.syncCreateNameFromSelectedItem();')
        ->and($createSource)->toContain('x-model="createForm.name"');
});

it('60. edit form does not auto rename existing recipes when output item changes', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $showSource = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));

    expect($source)->not->toContain('editForm.name = this.selectedOutputItemDisplayName(this.editForm.item_id)')
        ->and($showSource)->not->toContain('editForm.name = this.selectedOutputItemDisplayName(this.editForm.item_id)');
});

it('61. create recipe slide-over focuses the output item combobox input when opened', function () {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-index.js'));
    $createSource = File::get(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('const input = this.$refs.createOutputItemCombobox?.querySelector(\'input[role="combobox"]\');')
        ->and($source)->toContain('input?.focus();')
        ->and($createSource)->toContain('x-ref="createOutputItemCombobox"');
});
