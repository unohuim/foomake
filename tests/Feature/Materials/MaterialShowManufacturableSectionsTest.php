<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->recipeCounter = 1;
    $this->makeOrderCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::factory()->create(array_merge([
            'tenant_name' => $attributes['tenant_name'] ?? 'Tenant ' . $this->tenantCounter,
            'currency_code' => $attributes['currency_code'] ?? 'USD',
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email' => 'materials-manufacturable-' . $this->userCounter . '@example.test',
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'materials-manufacturable-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $suffix = 'msm-' . $this->uomCounter . '-' . Str::lower(Str::random(4));

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $suffix,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $suffix,
            'symbol' => $attributes['symbol'] ?? $suffix,
            'display_precision' => $attributes['display_precision'] ?? 1,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Material ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeRecipe = function (Tenant $tenant, Item $item, array $attributes = []): Recipe {
        $recipe = Recipe::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => $attributes['name'] ?? 'Recipe ' . $this->recipeCounter,
            'output_quantity' => $attributes['output_quantity'] ?? '1.000000',
            'is_active' => $attributes['is_active'] ?? true,
            'is_default' => $attributes['is_default'] ?? false,
        ], $attributes));

        $this->recipeCounter++;

        return $recipe;
    };

    $this->makeMakeOrder = function (Tenant $tenant, Recipe $recipe, array $attributes = []): MakeOrder {
        $makeOrder = MakeOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $recipe->item_id,
            'output_quantity' => $attributes['output_quantity'] ?? '2.000000',
            'status' => $attributes['status'] ?? MakeOrder::STATUS_DRAFT,
            'created_by_user_id' => $attributes['created_by_user_id'] ?? null,
            'made_by_user_id' => $attributes['made_by_user_id'] ?? null,
        ], $attributes));

        $this->makeOrderCounter++;

        return $makeOrder;
    };

    $this->getShow = function (?User $user, Item $item) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('materials.show', $item));
    };

    $this->getRecipesIndex = function (User $user, array $query = []) {
        return $this->actingAs($user)->get(route('manufacturing.recipes.index', $query));
    };

    $this->getMakeOrdersIndex = function (User $user, array $query = []) {
        return $this->actingAs($user)->get(route('manufacturing.make-orders.index', $query));
    };

    $this->postRecipe = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('manufacturing.recipes.store'), $payload);
    };

    $this->postMakeOrder = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('manufacturing.make-orders.store'), $payload);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        if (! array_key_exists(1, $matches)) {
            return [];
        }

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractMaterialPayload = function ($response): array {
        return ($this->extractPayload)($response, 'materials-show-payload');
    };

    $this->extractSection = function ($response, string $sectionKey): array {
        $payload = ($this->extractMaterialPayload)($response);
        $section = $payload['sections'][$sectionKey] ?? null;

        return is_array($section) ? $section : [];
    };

    $this->getSectionList = function (User $user, array $sectionConfig) {
        $endpoint = $sectionConfig['endpoints']['list'] ?? null;

        expect(is_string($endpoint))->toBeTrue();

        return $this->actingAs($user)->getJson($endpoint);
    };
});

it('1. redirects guests to login for the material detail page', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->getShow)(null, $item)->assertRedirect(route('login'));
});

it('2. forbids material detail access without inventory materials view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->getShow)($user, $item)->assertForbidden();
});

it('3. allows authorized users to access the material detail with the manufacturable sections', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Manufacturable Material',
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-view',
    ]);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Manufacturable Material');
});

it('4. manufacturable material shows the recipes section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes'))->not->toBe([]);
});

it('5. manufacturable material shows the make orders section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders'))->not->toBe([]);
});

it('6. non manufacturable material hides the recipes section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes'))->toBe([]);
});

it('7. non manufacturable material hides the make orders section', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => false]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders'))->toBe([]);
});

it('8. supplier packages section is configured near the top and default open', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
    ]);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'supplierPackages');

    expect($section['defaultOpen'] ?? null)->toBeTrue();

    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $supplierPackagesPosition = strpos($viewSource, 'data-section-key="supplierPackages"');
    $purchaseOrdersPosition = strpos($viewSource, 'data-section-key="purchaseOrders"');

    expect($supplierPackagesPosition)->not->toBeFalse()
        ->and($purchaseOrdersPosition)->not->toBeFalse()
        ->and($supplierPackagesPosition)->toBeLessThan($purchaseOrdersPosition);
});

it('9. recipes section is configured near the top and default open', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'recipes');

    expect($section['defaultOpen'] ?? null)->toBeTrue();

    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $recipesPosition = strpos($viewSource, 'data-section-key="recipes"');
    $purchaseOrdersPosition = strpos($viewSource, 'data-section-key="purchaseOrders"');

    expect($recipesPosition)->not->toBeFalse()
        ->and($purchaseOrdersPosition)->not->toBeFalse()
        ->and($recipesPosition)->toBeLessThan($purchaseOrdersPosition);
});

it('10. purchase orders section is configured near the bottom and default collapsed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'purchaseOrders');

    expect($section['defaultOpen'] ?? null)->toBeFalse();
});

it('11. make orders section is configured near the bottom and default collapsed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSection)($response, 'makeOrders');

    expect($section['defaultOpen'] ?? null)->toBeFalse();

    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $makeOrdersPosition = strpos($viewSource, 'data-section-key="makeOrders"');
    $recipesPosition = strpos($viewSource, 'data-section-key="recipes"');

    expect($makeOrdersPosition)->not->toBeFalse()
        ->and($recipesPosition)->not->toBeFalse()
        ->and($makeOrdersPosition)->toBeGreaterThan($recipesPosition);
});

it('12. recipes section uses data js crud section root', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('data-js-crud-section-root', false)
        ->assertSee('data-section-key="recipes"', false);
});

it('13. make orders section uses data js crud section root', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('data-js-crud-section-root', false)
        ->assertSee('data-section-key="makeOrders"', false);
});

it('14. recipes section uses a stable recipes section key', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes')['resource'] ?? null)
        ->toBe('material-recipes');
});

it('15. make orders section uses a stable makeOrders section key', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders')['resource'] ?? null)
        ->toBe('material-make-orders');
});

it('16. no new bespoke accordion or section abstraction is introduced', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));
    $sectionSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($viewSource)->toContain('data-js-crud-section-root')
        ->and($pageSource)->toContain('mountCrudSection')
        ->and($sectionSource)->toContain('data-js-crud-section-card')
        ->and($pageSource)->not->toContain('mountRecipesAccordion')
        ->and($pageSource)->not->toContain('mountMakeOrdersAccordion');
});

it('17. existing material detail sections still render when applicable', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
        'inventory-recipes-view',
        'inventory-make-orders-view',
    ]);

    $payload = ($this->extractMaterialPayload)(($this->getShow)($user, $item));

    expect($payload['sections']['supplierPackages'] ?? null)->toBeArray()
        ->and($payload['sections']['recipes'] ?? null)->toBeArray()
        ->and($payload['sections']['purchaseOrders'] ?? null)->toBeArray()
        ->and($payload['sections']['makeOrders'] ?? null)->toBeArray();
});

it('18. recipes section lists only recipes whose output item matches the current material', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material, ['name' => 'Sauce Recipe']);
    ($this->makeRecipe)($tenant, $otherMaterial, ['name' => 'Other Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$recipe->id]);
});

it('19. recipes section excludes recipes for other output materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $otherMaterial, ['name' => 'Other Output Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->not->toContain('Other Output Recipe');
});

it('20. recipes section is tenant scoped', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $material, ['name' => 'Current Tenant Recipe']);
    $otherMaterial = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($otherTenant, $otherMaterial, ['name' => 'Other Tenant Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toContain('Current Tenant Recipe')
        ->not->toContain('Other Tenant Recipe');
});

it('21. recipes section shows an empty state when no matching recipes exist', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $section = ($this->extractSection)(($this->getShow)($user, $material), 'recipes');
    ($this->getSectionList)($user, $section)->assertOk()->assertJsonCount(0, 'data');

    expect($section['emptyState'] ?? null)->toBe('No recipes produce this material yet.');
});

it('22. recipes section has a plus create action when the user can manage recipes', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');

    expect($section['permissions']['canCreate'] ?? null)->toBeTrue()
        ->and($section['createAction']['type'] ?? null)->toBe('custom')
        ->and($section['createAction']['handlerKey'] ?? null)->toBe('openRecipeCreate');
});

it('23. recipe create action reuses the existing recipe create slide over in place', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $recipesViewSource = file_get_contents(resource_path('views/manufacturing/recipes/index.blade.php'));
    $materialsViewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($section['createAction']['type'] ?? null)->toBe('custom')
        ->and($section['createAction']['handlerKey'] ?? null)->toBe('openRecipeCreate')
        ->and(array_key_exists('url', $section['createAction'] ?? []))->toBeFalse()
        ->and($recipesViewSource)->toContain("@include('manufacturing.recipes.partials.create-recipe-slide-over')")
        ->and($materialsViewSource)->toContain("@include('manufacturing.recipes.partials.create-recipe-slide-over')");
});

it('24. recipe create action preselects the current material as the output item without leaving the detail page', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($section['createAction']['prefill']['itemId'] ?? null)->toBe($item->id)
        ->and($pageSource)->toContain('openRecipeCreate');
});

it('24a. recipe create success redirects to the created recipe detail view after in place create', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-manage',
    ]);

    $response = ($this->postRecipe)($user, [
        'item_id' => $item->id,
        'recipe_type' => Recipe::TYPE_MANUFACTURING,
        'name' => 'Redirect Recipe',
        'output_quantity' => '1.000000',
        'is_active' => true,
    ])->assertCreated();

    $recipeId = $response->json('data.id');

    expect($response->json('data.show_url'))->toBe(route('manufacturing.recipes.show', $recipeId))
        ->and($pageSource)->toContain("window.location.assign(data.data.show_url);");
});

it('25. recipe row menu includes make when the user can execute make orders', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $item);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-execute',
    ]);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $item), 'recipes')
    )->assertOk();

    expect($response->json('data.0.available_actions'))->toContain('make');
});

it('26. make action opens the existing make order slide over with the recipe prefilled', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-recipes-view',
        'inventory-make-orders-execute',
        'inventory-make-orders-view',
    ]);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $item), 'recipes')
    )->assertOk();

    $section = ($this->extractSection)(($this->getShow)($user, $item), 'recipes');
    $makeOrdersViewSource = file_get_contents(resource_path('views/manufacturing/make-orders/index.blade.php'));
    $materialsViewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($section['actions'][1]['id'] ?? null)->toBe('make')
        ->and($section['actions'][1]['type'] ?? null)->toBe('custom')
        ->and($section['actions'][1]['handlerKey'] ?? null)->toBe('openMakeOrderCreate')
        ->and($response->json('data.0.make_prefill.recipe_id'))->toBe($recipe->id)
        ->and(array_key_exists('make_url', $response->json('data.0') ?? []))->toBeFalse()
        ->and($makeOrdersViewSource)->toContain('Create make order')
        ->and($materialsViewSource)->toContain("@include('manufacturing.make-orders.partials.create-make-order-slide-over')");
});

it('26a. make order create success redirects to the created make order detail view after in place create', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item);
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-make-orders-execute',
    ]);

    $response = ($this->postMakeOrder)($user, [
        'recipe_id' => $recipe->id,
        'runs' => '2.000000',
    ])->assertCreated();

    $makeOrderId = $response->json('data.id');

    expect($response->json('data.show_url'))->toBe(route('manufacturing.make-orders.show', $makeOrderId))
        ->and($pageSource)->toContain("window.location.assign(data.data.show_url);");
});

it('27. make orders section lists only make orders whose recipe outputs this material', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);
    $otherRecipe = ($this->makeRecipe)($tenant, $otherMaterial);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe);
    ($this->makeMakeOrder)($tenant, $otherRecipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$makeOrder->id]);
});

it('28. make orders section excludes make orders for recipes producing other materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherMaterial = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $otherRecipe = ($this->makeRecipe)($tenant, $otherMaterial, ['name' => 'Other Material Recipe']);
    ($this->makeMakeOrder)($tenant, $otherRecipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('recipe_name')->all())->not->toContain('Other Material Recipe');
});

it('29. make orders section is tenant scoped', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material, ['name' => 'Tenant Recipe']);
    ($this->makeMakeOrder)($tenant, $recipe);
    $otherMaterial = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true]);
    $otherRecipe = ($this->makeRecipe)($otherTenant, $otherMaterial, ['name' => 'Other Tenant Recipe']);
    ($this->makeMakeOrder)($otherTenant, $otherRecipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('recipe_name')->all())
        ->toContain('Tenant Recipe')
        ->not->toContain('Other Tenant Recipe');
});

it('30. make orders section shows an empty state when no matching make orders exist', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $section = ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders');
    ($this->getSectionList)($user, $section)->assertOk()->assertJsonCount(0, 'data');

    expect($section['emptyState'] ?? null)->toBe('No make orders produce this material yet.');
});

it('31. make order rows link to the minimal read only make order detail view', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);
    $makeOrder = ($this->makeMakeOrder)($tenant, $recipe);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect($response->json('data.0.show_url'))->toBe(route('manufacturing.make-orders.show', $makeOrder));

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.show', $makeOrder))
        ->assertOk();
});

it('31a. recipe rows expose tenant scoped recipe detail links', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect($response->json('data.0.show_url'))->toBe(route('manufacturing.recipes.show', $recipe));
});

it('31b. recipe detail links do not expose cross tenant records through the material detail section list', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    ($this->makeRecipe)($tenant, $material, ['name' => 'Visible Recipe']);
    $otherMaterial = ($this->makeItem)($otherTenant, $otherUom, ['is_manufacturable' => true]);
    $otherRecipe = ($this->makeRecipe)($otherTenant, $otherMaterial, ['name' => 'Hidden Recipe']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-recipes-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'recipes')
    )->assertOk();

    expect(collect($response->json('data'))->pluck('show_url')->filter()->all())
        ->not->toContain(route('manufacturing.recipes.show', $otherRecipe));
});

it('32. make order rows show total output quantity as runs multiplied by recipe output quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['display_precision' => 2]);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material, ['output_quantity' => '1.250000']);
    ($this->makeMakeOrder)($tenant, $recipe, ['output_quantity' => '2.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect($response->json('data.0.total_output_quantity'))->toBe('2.500000')
        ->and($response->json('data.0.total_output_quantity_display'))->toBe('2.50');
});

it('33. make order runs display with zero decimals', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $material = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $material);
    ($this->makeMakeOrder)($tenant, $recipe, ['output_quantity' => '3.000000']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-make-orders-view']);

    $response = ($this->getSectionList)(
        $user,
        ($this->extractSection)(($this->getShow)($user, $material), 'makeOrders')
    )->assertOk();

    expect($response->json('data.0.runs_display'))->toBe('3');
});

it('34. recipes section visibility is gated by inventory recipes view', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'recipes'))->toBe([]);
});

it('35. make orders section visibility is gated by inventory make orders view', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermissions)($user, ['inventory-materials-view']);

    expect(($this->extractSection)(($this->getShow)($user, $item), 'makeOrders'))->toBe([]);
});

it('36. resource detail pages use a shared resource detail layout component', function (): void {
    $detailViews = [
        'materials/show.blade.php',
        'manufacturing/recipes/show.blade.php',
        'manufacturing/make-orders/show.blade.php',
        'purchasing/orders/show.blade.php',
        'purchasing/suppliers/show.blade.php',
        'sales/orders/show.blade.php',
        'sales/customers/show.blade.php',
        'inventory/counts/show.blade.php',
    ];

    foreach ($detailViews as $viewPath) {
        $source = file_get_contents(resource_path('views/' . $viewPath));

        expect($source)->toContain('<x-resource-detail-layout');
    }
});

it('37. shared resource detail layout exposes a dedicated scrollable content region', function (): void {
    $source = file_get_contents(resource_path('views/components/resource-detail-layout.blade.php'));

    expect($source)->toContain('h-full min-h-0')
        ->and($source)->toContain('overflow-y-auto')
        ->and($source)->toContain('data-resource-detail-layout')
        ->and($source)->toContain('data-resource-detail-scroll');
});

it('38. shared app layout keeps sticky shell navigation and header above the detail scroller', function (): void {
    $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($source)->toContain('h-screen')
        ->and($source)->toContain('overflow-hidden')
        ->and($source)->toContain('sticky top-0 z-40')
        ->and($source)->toContain('sticky top-16 z-30');
});

it('39. recipe create slide over stays fixed above sticky shell chrome and closes on backdrop click', function (): void {
    $source = file_get_contents(resource_path('views/manufacturing/recipes/partials/create-recipe-slide-over.blade.php'));

    expect($source)->toContain('fixed inset-0 z-50')
        ->and($source)->toContain('absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity')
        ->and($source)->toContain('x-on:click="closeCreate()"');
});

it('40. make order create slide over stays fixed above sticky shell chrome and closes on backdrop click', function (): void {
    $source = file_get_contents(resource_path('views/manufacturing/make-orders/partials/create-make-order-slide-over.blade.php'));

    expect($source)->toContain('fixed inset-0 z-50')
        ->and($source)->toContain('absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity')
        ->and($source)->toContain('x-on:click="closeMakeOrderCreate()"');
});

it('41. material detail renders slide over layers outside the scrollable detail content container', function (): void {
    $source = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($source)->toContain('data-material-detail-content')
        ->and($source)->toContain('data-material-detail-overlays');

    $contentPosition = strpos($source, 'data-material-detail-content');
    $overlayPosition = strpos($source, 'data-material-detail-overlays');

    expect($contentPosition)->not->toBeFalse()
        ->and($overlayPosition)->not->toBeFalse()
        ->and($overlayPosition)->toBeGreaterThan($contentPosition);
});
