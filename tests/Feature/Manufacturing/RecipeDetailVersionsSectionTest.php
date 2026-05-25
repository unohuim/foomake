<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->makeTenant = function (string $name): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUom = function (Tenant $tenant, int $displayPrecision = 2): Uom {
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

    $this->createRecipe = function (User $user, Item $outputItem, array $overrides = []): Recipe {
        $response = actingAs($user)->postJson(route('manufacturing.recipes.store'), array_merge([
            'item_id' => $outputItem->id,
            'recipe_type' => 'manufacturing',
            'name' => 'Soup Recipe',
            'output_quantity' => '8.000000',
            'is_active' => true,
            'is_default' => false,
        ], $overrides))->assertCreated();

        return Recipe::query()->findOrFail((int) $response->json('data.id'));
    };

    $this->createDraftVersion = function (User $user, Recipe $recipe, array $overrides = []) {
        return actingAs($user)->postJson(route('manufacturing.recipes.versions.store', $recipe), array_merge([
            'recipe_type' => 'manufacturing',
            'output_quantity' => '8.000000',
        ], $overrides));
    };

    $this->checkoutVersion = function (User $user, Recipe $recipe, RecipeVersion|int $version) {
        return actingAs($user)->postJson(route('manufacturing.recipes.versions.checkout', [
            'recipe' => $recipe,
            'version' => $version instanceof RecipeVersion ? $version->id : $version,
        ]));
    };

    $this->publishVersion = function (User $user, Recipe $recipe, RecipeVersion|int $version) {
        return actingAs($user)->patchJson(route('manufacturing.recipes.versions.publish', [
            'recipe' => $recipe,
            'version' => $version instanceof RecipeVersion ? $version->id : $version,
        ]));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '{}', true);

        return is_array($payload) ? $payload : [];
    };
});

test('1. guests are redirected from recipe detail', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = Recipe::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'item_id' => $output->id,
        'recipe_type' => 'manufacturing',
        'name' => 'Soup Recipe',
        'output_quantity' => '8.000000',
        'is_active' => true,
    ]);

    $this->get(route('manufacturing.recipes.show', $recipe))
        ->assertRedirect(route('login'));
});

test('2. recipe detail requires inventory recipes view permission', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)((function () use ($tenant): User {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'inventory-make-orders-manage']);
        $role = Role::query()->firstOrCreate(['name' => 'recipe-owner']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $owner->roles()->syncWithoutDetaching([$role->id]);

        return $owner;
    })(), $output);

    actingAs($user)->get(route('manufacturing.recipes.show', $recipe))
        ->assertForbidden();
});

test('3. recipes use the shared resource detail header breadcrumb component', function (): void {
    $source = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));

    expect($source)->toContain('x-resource-detail-header-breadcrumb')
        ->and($source)->not->toContain('data-resource-detail-header-container')
        ->and($source)->not->toContain('data-resource-detail-breadcrumbs-aligned')
        ->and($source)->not->toContain('data-resource-detail-title-aligned');
});

test('4. materials use the same shared resource detail header breadcrumb component', function (): void {
    $source = File::get(resource_path('views/materials/show.blade.php'));

    expect($source)->toContain('x-resource-detail-header-breadcrumb');
});

test('5. breadcrumb renders at the bottom of the shared header component contract', function (): void {
    $source = File::get(resource_path('views/components/resource-detail-header-breadcrumb.blade.php'));

    expect($source)->toContain('data-resource-detail-header')
        ->and($source)->toContain('data-resource-detail-breadcrumb')
        ->and($source)->toContain('order-last');
});

test('6. breadcrumb left edge aligns with title container via shared component classes', function (): void {
    $source = File::get(resource_path('views/components/resource-detail-header-breadcrumb.blade.php'));
    $breadcrumbSource = File::get(resource_path('views/components/resource-breadcrumbs.blade.php'));

    expect($source)->toContain('max-w-5xl')
        ->and($source)->toContain('px-1 sm:px-6 lg:px-8')
        ->and($breadcrumbSource)->toContain('border-y border-gray-200')
        ->and($breadcrumbSource)->toContain('data-breadcrumb-chevron-separator');
});

test('7. header renders the recipe name as the main title', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['name' => 'Header Recipe']);

    actingAs($user)->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Header Recipe');
});

test('8. header shows the output item pill', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    actingAs($user)->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Soup Output');
});

test('9. header removes the old version label text', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    actingAs($user)->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertDontSee('Version 1.00');
});

test('10. header displays version number in x.xx format', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    actingAs($user)->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertSee('1.00');
});

test('11. header uses current version metadata by default when one exists', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['output_quantity' => '8.000000']);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '14.000000',
    ])->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['recipe']['display_version_number'] ?? null)->toBe('1.01')
        ->and($payload['recipe']['display_output_quantity'] ?? null)->toBe('14.000000');
});

test('12. header falls back to the latest version metadata when no current version exists yet', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['recipe']['display_version_number'] ?? null)->toBe('1.00')
        ->and($payload['recipe']['current_published_version_id'] ?? null)->toBeNull();
});

test('13. header uses current users checked out version metadata when present', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '14.000000',
    ])->assertCreated()->json('data.id');

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.update', [$recipe, $draftVersionId]), [
        'recipe_type' => 'manufacturing',
        'output_quantity' => '14.500000',
    ])->assertOk();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['recipe']['display_version_number'] ?? null)->toBe('1.01')
        ->and($payload['recipe']['display_output_quantity'] ?? null)->toBe('14.500000');
});

test('14. ingredients accordion renders on recipe detail', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    actingAs($user)->get(route('manufacturing.recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Ingredients');
});

test('14b. recipe detail renders make orders before ingredients and versions at the bottom', function (): void {
    $source = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));

    $makeOrdersPosition = strpos($source, 'data-section-key="makeOrders"');
    $ingredientsPosition = strpos($source, '<x-ingredients-detail-section');
    $versionsPosition = strpos($source, 'data-section-key="versions"');

    expect($makeOrdersPosition)->not->toBeFalse()
        ->and($ingredientsPosition)->not->toBeFalse()
        ->and($versionsPosition)->not->toBeFalse()
        ->and($makeOrdersPosition)->toBeLessThan($ingredientsPosition)
        ->and($ingredientsPosition)->toBeLessThan($versionsPosition);
});

test('15. ingredients payload uses the current version by default when one exists', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['ingredients']['display_version_number'] ?? null)->toBe('1.01');
});

test('16. ingredients payload switches to the current users checked out version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['ingredients']['display_version_number'] ?? null)->toBe('1.01');
});

test('17. ingredients are read only when no editable checkout exists', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['ingredients']['can_edit'] ?? null)->toBeFalse();
});

test('18. ingredients are editable when checked out by the current user', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($payload['ingredients']['can_edit'] ?? null)->toBeTrue()
        ->and($payload['ingredients']['display_version_id'] ?? null)->toBe($draftVersionId);
});

test('19. ingredient qty display honors item uom display precision', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $outputUom = ($this->makeUom)($tenant);
    $ingredientUom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $outputUom, 'Soup Output', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $ingredientUom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    $lineId = (int) actingAs($user)->postJson(route('manufacturing.recipes.ingredients.store', [$recipe, $draftVersionId]), [
        'item_id' => $input->id,
    ])->assertCreated()->json('data.id');

    $response = actingAs($user)->patchJson(route('manufacturing.recipes.ingredients.update', [$recipe, $draftVersionId, $lineId]), [
        'quantity' => '3.250000',
    ])->assertOk();

    expect($response->json('data.quantity_display') ?? null)->toBe('3.250')
        ->and($response->json('data.quantity') ?? null)->toBe('3.250000');
});

test('20. ingredients add combobox and plus button only render in editable state', function (): void {
    $source = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));

    expect($source)->toContain('x-ingredients-detail-section')
        ->and($source)->not->toContain('data-ingredient-add-bar')
        ->and($source)->not->toContain('data-ingredient-add-button');
});

test('21. ingredients add bar uses compact aligned tailwind classes', function (): void {
    $source = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));

    expect($source)->toContain('data-ingredients-add-row')
        ->and($source)->toContain('data-ingredients-add-row-left')
        ->and($source)->toContain('data-ingredients-add-row-right')
        ->and($source)->not->toContain('label="Item"')
        ->and($source)->toContain('h-10 w-10');
});

test('22. parent level recipe line editing ui is not rendered on recipe detail', function (): void {
    $source = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));

    expect($source)->not->toContain('line-form-slide-over')
        ->and($source)->not->toContain('data-recipe-lines')
        ->and($source)->not->toContain('data-ingredients-section')
        ->and($source)->toContain('x-ingredients-detail-section');
});

test('23. archived versions are hidden by default and revealed only with the view archived toggle', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe, ['output_quantity' => '9.000000'])
        ->assertCreated()
        ->json('data.id');

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.archive', [$recipe, $draftVersionId]))
        ->assertOk();

    $hiddenRows = actingAs($user)
        ->getJson(route('manufacturing.recipes.versions.index', $recipe))
        ->assertOk()
        ->json('data');

    $visibleRows = actingAs($user)
        ->getJson(route('manufacturing.recipes.versions.index', [$recipe, 'include_archived' => 1]))
        ->assertOk()
        ->json('data');

    expect(collect($hiddenRows)->pluck('status')->all())->not->toContain('ARCHIVED')
        ->and(collect($visibleRows)->pluck('status')->all())->toContain('ARCHIVED');
});

test('24. versions payload exposes state appropriate dropdown actions and view archived toggle config', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $publishedDraftId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $publishedDraftId)->assertOk();
    $checkedOutDraftId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '11.000000',
    ])->assertCreated()->json('data.id');
    $openDraftId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '12.000000',
    ])->assertCreated()->json('data.id');
    actingAs($user)->postJson(route('manufacturing.recipes.versions.check-in', [$recipe, $openDraftId]))->assertOk();

    $rows = actingAs($user)
        ->getJson(route('manufacturing.recipes.versions.index', [$recipe, 'include_archived' => 1]))
        ->assertOk()
        ->json('data');

    $publishedRow = collect($rows)->firstWhere('id', $publishedDraftId);
    $checkedOutDraftRow = collect($rows)->firstWhere('id', $checkedOutDraftId);
    $openDraftRow = collect($rows)->firstWhere('id', $openDraftId);
    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect($publishedRow['availableActions'] ?? [])->toBe(['make', 'duplicate', 'archive'])
        ->and($checkedOutDraftRow['availableActions'] ?? [])->toBe(['check_in', 'publish', 'duplicate', 'delete'])
        ->and($openDraftRow['availableActions'] ?? [])->toBe(['checkout', 'publish', 'duplicate', 'delete'])
        ->and(data_get($payload, 'sections.versions.toolbarToggles.0.label'))->toBe('View Archived');
});

test('25. recipe detail renders the reusable make orders accordion crud section', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $publishedDraftId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $publishedDraftId)->assertOk();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect(data_get($payload, 'sections.makeOrders.resource'))->toBe('recipe-make-orders')
        ->and(data_get($payload, 'sections.makeOrders.title'))->toBe('Make Orders')
        ->and(data_get($payload, 'sections.makeOrders.permissions.canCreate'))->toBeTrue()
        ->and(data_get($payload, 'sections.makeOrders.defaultOpen'))->toBeTrue()
        ->and(data_get($payload, 'sections.makeOrders.mobilePageSize'))->toBe(3)
        ->and(data_get($payload, 'sections.makeOrders.rowLayout.primaryText.urlField'))->toBe('display.showUrl')
        ->and(data_get($payload, 'sections.makeOrders.createAction.type'))->toBe('form')
        ->and(data_get($payload, 'sections.makeOrders.createAction.title'))->toBe('Create Make Order')
        ->and(data_get($payload, 'sections.makeOrders.createAction.submitLabel'))->toBe('Create Make Order')
        ->and(data_get($payload, 'sections.makeOrders.createAction.description'))->toContain('Soup Recipe')
        ->and(data_get($payload, 'sections.makeOrders.endpoints.create'))->toBe(route('manufacturing.recipes.make-orders.store', $recipe))
        ->and(data_get($payload, 'sections.makeOrders.fields'))->toBe([
            [
                'name' => 'runs',
                'label' => 'Runs',
                'type' => 'text',
                'required' => true,
                'rowGroup' => '',
            ],
        ]);
});

test('25aa. recipe detail make orders section uses the shared mobile page size contract instead of bespoke mobile markup', function (): void {
    $source = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));
    $jsSource = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));
    $crudSectionSource = File::get(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('data-js-crud-section-root')
        ->and($source)->not->toContain('data-recipe-mobile-make-orders')
        ->and($jsSource)->toContain('mobilePageSize')
        ->and($crudSectionSource)->toContain('mobilePageSize')
        ->and($crudSectionSource)->toContain('globalThis.matchMedia');
});

test('25ab. recipe detail mobile make orders list returns the latest three rows first and keeps detail links', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();
    $currentVersionId = (int) $recipe->fresh()->current_version_id;

    foreach (range(1, 5) as $index) {
        MakeOrder::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $currentVersionId,
            'output_item_id' => $output->id,
            'output_quantity' => (string) $index . '.000000',
            'status' => MakeOrder::STATUS_DRAFT,
            'due_date' => null,
            'scheduled_at' => null,
            'made_at' => null,
            'created_by_user_id' => $user->id,
            'made_by_user_id' => null,
            'created_at' => now()->addMinutes($index),
            'updated_at' => now()->addMinutes($index),
        ]);
    }

    $response = actingAs($user)
        ->getJson(route('manufacturing.recipes.make-orders.index', [
            'recipe' => $recipe,
            'page' => 1,
            'per_page' => 3,
        ]))
        ->assertOk();

    $rows = $response->json('data');

    expect($rows)->toHaveCount(3)
        ->and($response->json('meta.per_page'))->toBe(3)
        ->and($response->json('meta.current_page'))->toBe(1)
        ->and($rows[0]['id'])->toBeGreaterThan($rows[1]['id'])
        ->and($rows[1]['id'])->toBeGreaterThan($rows[2]['id'])
        ->and($rows[0]['show_url'])->toBe(route('manufacturing.make-orders.show', $rows[0]['id']));
});

test('25ac. recipe detail mobile make orders remaining rows are accessible through pagination', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();
    $currentVersionId = (int) $recipe->fresh()->current_version_id;

    foreach (range(1, 5) as $index) {
        MakeOrder::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $currentVersionId,
            'output_item_id' => $output->id,
            'output_quantity' => (string) $index . '.000000',
            'status' => MakeOrder::STATUS_DRAFT,
            'due_date' => null,
            'scheduled_at' => null,
            'made_at' => null,
            'created_by_user_id' => $user->id,
            'made_by_user_id' => null,
            'created_at' => now()->addMinutes($index),
            'updated_at' => now()->addMinutes($index),
        ]);
    }

    $pageTwo = actingAs($user)
        ->getJson(route('manufacturing.recipes.make-orders.index', [
            'recipe' => $recipe,
            'page' => 2,
            'per_page' => 3,
        ]))
        ->assertOk();

    expect($pageTwo->json('data'))->toHaveCount(2)
        ->and($pageTwo->json('meta.current_page'))->toBe(2)
        ->and($pageTwo->json('meta.last_page'))->toBe(2)
        ->and($pageTwo->json('meta.total'))->toBe(5);
});

test('25b. recipe detail ingredients and versions default collapsed', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    $source = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));

    expect(data_get($payload, 'sections.versions.defaultOpen'))->toBeFalse()
        ->and($source)->toContain('x-ingredients-detail-section')
        ->and($source)->toContain(":default-open=\"false\"");
});

test('25c. recipe detail and make order detail both use the shared ingredients section abstraction', function (): void {
    $recipeSource = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));
    $makeOrderSource = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $componentSource = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));

    expect($recipeSource)->toContain('x-ingredients-detail-section')
        ->and($makeOrderSource)->toContain('x-ingredients-detail-section')
        ->and($componentSource)->toContain('data-ingredients-section-component')
        ->and($componentSource)->toContain('x-detail-section-card');
});

test('25d. recipe detail make orders plus button remains right aligned through the shared section create wrapper contract', function (): void {
    $source = File::get(resource_path('views/components/detail-section-card.blade.php'));
    $crudSectionSource = File::get(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('data-js-crud-section-create-wrapper')
        ->and($source)->toContain('sm:ml-auto')
        ->and($crudSectionSource)->toContain('data-js-crud-section-create-wrapper')
        ->and($crudSectionSource)->toContain('sm:ml-auto');
});

test('25e. recipe detail make orders create action uses the shared crud section slide over instead of page local make order create markup', function (): void {
    $viewSource = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));
    $pageSource = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));
    $crudSectionSource = File::get(resource_path('js/lib/js-crud-section.js'));

    expect($viewSource)->not->toContain('data-recipe-make-order-create-slide-over')
        ->and($viewSource)->not->toContain('data-recipe-make-order-create-button')
        ->and($pageSource)->not->toContain('handleCreateAction: async ({ section }) =>')
        ->and($pageSource)->not->toContain('section?.endpoints?.create || \'\'')
        ->and($pageSource)->toContain('handleCreateSuccess: async ({ data }) =>')
        ->and($crudSectionSource)->toContain('x-show="isFormOpen"')
        ->and($crudSectionSource)->toContain('createFormTitle()')
        ->and($crudSectionSource)->toContain('createFormDescription()');
});

test('25f. shared crud section keeps the create button outside the empty state box', function (): void {
    $crudSectionSource = File::get(resource_path('js/lib/js-crud-section.js'));

    $createWrapperPosition = strpos($crudSectionSource, 'data-js-crud-section-create-wrapper');
    $emptyStatePosition = strpos($crudSectionSource, 'border-dashed border-gray-300');

    expect($createWrapperPosition)->not->toBeFalse()
        ->and($emptyStatePosition)->not->toBeFalse()
        ->and($createWrapperPosition)->toBeLessThan($emptyStatePosition);
});

test('25da. recipe and make order ingredients share the dedicated add row contract without page local alignment markup', function (): void {
    $recipeSource = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));
    $makeOrderSource = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $componentSource = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));
    $cardSource = File::get(resource_path('views/components/detail-section-card.blade.php'));

    expect($cardSource)->toContain('data-detail-section-add-row')
        ->and($cardSource)->toContain('data-detail-section-add-row-left')
        ->and($cardSource)->toContain('data-detail-section-add-row-right')
        ->and($componentSource)->toContain('data-ingredients-add-row')
        ->and($componentSource)->toContain('data-ingredients-add-row-left')
        ->and($componentSource)->toContain('data-ingredients-add-row-right')
        ->and($recipeSource)->toContain('x-ingredients-detail-section')
        ->and($makeOrderSource)->toContain('x-ingredients-detail-section')
        ->and($recipeSource)->not->toContain('data-recipe-ingredient-add-row')
        ->and($makeOrderSource)->not->toContain('data-make-order-ingredient-add-row');
});

test('26. recipe detail make orders section lists only make orders for that recipe', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $outputA = ($this->makeItem)($tenant, $uom, 'Output A', ['is_manufacturable' => true]);
    $outputB = ($this->makeItem)($tenant, $uom, 'Output B', ['is_manufacturable' => true]);
    $recipeA = ($this->createRecipe)($user, $outputA);
    $recipeB = ($this->createRecipe)($user, $outputB, ['name' => 'Recipe B']);
    $versionAId = (int) ($this->createDraftVersion)($user, $recipeA)->assertCreated()->json('data.id');
    $versionBId = (int) ($this->createDraftVersion)($user, $recipeB)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipeA, $versionAId)->assertOk();
    ($this->publishVersion)($user, $recipeB, $versionBId)->assertOk();

    actingAs($user)->postJson(route('manufacturing.recipes.make-orders.store', $recipeA), ['runs' => '2.000000'])->assertCreated();
    actingAs($user)->postJson(route('manufacturing.recipes.make-orders.store', $recipeB), ['runs' => '3.000000'])->assertCreated();

    $response = actingAs($user)
        ->getJson(route('manufacturing.recipes.make-orders.index', $recipeA))
        ->assertOk();

    $rows = collect($response->json('data'));

    expect($rows->pluck('recipe_id')->unique()->all())->toBe([$recipeA->id])
        ->and($rows->first()['show_url'] ?? null)->toBe(route('manufacturing.make-orders.show', $rows->first()['id'] ?? 0));
});

test('27. recipe detail make orders section create is disabled for draft only recipes', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect(data_get($payload, 'sections.makeOrders.permissions.canCreate'))->toBeFalse();

    actingAs($user)->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
        'runs' => '1.000000',
    ])->assertStatus(422);
});

test('28. recipe detail make orders section is permission gated', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)((function () use ($tenant): User {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'inventory-make-orders-manage']);
        $role = Role::query()->firstOrCreate(['name' => 'recipe-owner-make-orders']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $owner->roles()->syncWithoutDetaching([$role->id]);

        return $owner;
    })(), $output);

    actingAs($user)->getJson(route('manufacturing.recipes.make-orders.index', $recipe))->assertForbidden();
    actingAs($user)->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
        'runs' => '1.000000',
    ])->assertForbidden();
});

test('29. versions section shared action contract includes publish and no bespoke version menu markup', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    $viewSource = File::get(resource_path('views/manufacturing/recipes/show.blade.php'));
    $crudSectionSource = File::get(resource_path('js/lib/js-crud-section.js'));

    $publishAction = collect(data_get($payload, 'sections.versions.actions', []))->firstWhere('id', 'publish');

    expect(collect(data_get($payload, 'sections.versions.actions', []))->pluck('id')->all())->toContain('publish')
        ->and(data_get($publishAction, 'type'))->toBe('custom')
        ->and(data_get($publishAction, 'handlerKey'))->toBe('publishVersion')
        ->and($viewSource)->not->toContain('data-recipe-version-row-actions')
        ->and($viewSource)->not->toContain('data-recipe-version-actions-menu')
        ->and($crudSectionSource)->toContain('visibleActions(record)');
});

test('30. draft version row dropdown includes publish while published and archived rows do not', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $publishedDraftId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $publishedDraftId)->assertOk();

    $archivedDraftId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '11.000000',
    ])->assertCreated()->json('data.id');

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.archive', [$recipe, $archivedDraftId]))
        ->assertOk();

    $openDraftId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '12.000000',
    ])->assertCreated()->json('data.id');
    actingAs($user)->postJson(route('manufacturing.recipes.versions.check-in', [$recipe, $openDraftId]))->assertOk();

    $rows = actingAs($user)
        ->getJson(route('manufacturing.recipes.versions.index', [$recipe, 'include_archived' => 1]))
        ->assertOk()
        ->json('data');

    $publishedRow = collect($rows)->firstWhere('id', $publishedDraftId);
    $archivedRow = collect($rows)->firstWhere('id', $archivedDraftId);
    $draftRow = collect($rows)->firstWhere('id', $openDraftId);

    expect($draftRow['availableActions'] ?? [])->toBe(['checkout', 'publish', 'duplicate', 'delete'])
        ->and($publishedRow['availableActions'] ?? [])->toBe(['make', 'duplicate', 'archive'])
        ->and($archivedRow['availableActions'] ?? [])->toBe(['view', 'duplicate']);
});

test('31. publish response includes reactive make orders eligibility and recently published version state', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Reactive Soup Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['name' => 'Reactive Soup Recipe']);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    $response = ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    expect(data_get($response->json(), 'recipe.current_version_id'))->toBe($draftVersionId)
        ->and(data_get($response->json(), 'recipe.current_published_version_id'))->toBe($draftVersionId)
        ->and(data_get($response->json(), 'sections.makeOrders.permissions.canCreate'))->toBeTrue()
        ->and(data_get($response->json(), 'sections.makeOrders.createAction.description'))->toContain('Reactive Soup Recipe')
        ->and(data_get($response->json(), 'ui.recently_published_version_id'))->toBe($draftVersionId)
        ->and(data_get($response->json(), 'ui.recently_published_highlight_ms'))->toBe(1000);
});

test('32. draft only recipe detail payload keeps make orders plus hidden until publish enables the shared section action', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Hidden Plus Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    $beforePayload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    $publishResponse = ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    expect(data_get($beforePayload, 'sections.makeOrders.permissions.canCreate'))->toBeFalse()
        ->and(data_get($publishResponse->json(), 'sections.makeOrders.permissions.canCreate'))->toBeTrue()
        ->and(data_get($publishResponse->json(), 'sections.makeOrders.endpoints.create'))->toBe(route('manufacturing.recipes.make-orders.store', $recipe))
        ->and(data_get($publishResponse->json(), 'sections.makeOrders.createAction.type'))->toBe('form');
});

test('33. recipe scoped make order create form stays recipe scoped with no recipe selector after publish', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Scoped Output', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.recipes.show', $recipe))->assertOk(),
        'manufacturing-recipes-show-payload'
    );

    expect(data_get($payload, 'sections.makeOrders.fields'))->toBe([
        [
            'name' => 'runs',
            'label' => 'Runs',
            'type' => 'text',
            'required' => true,
            'rowGroup' => '',
        ],
    ])
        ->and(json_encode(data_get($payload, 'sections.makeOrders.fields')) ?: '')->not->toContain('recipe_id')
        ->and(json_encode(data_get($payload, 'sections.makeOrders.fields')) ?: '')->not->toContain('recipe_version_id');
});

test('34. publish highlight is local page state driven, temporary, and targets only the newly published row', function (): void {
    $source = File::get(resource_path('js/pages/manufacturing-recipes-show.js'));

    expect($source)->toContain('recentlyPublishedVersionId')
        ->and($source)->toContain('recentlyPublishedHighlightTimeoutId')
        ->and($source)->toContain('border-lime-500')
        ->and($source)->toContain('setTimeout(() =>')
        ->and($source)->toContain('1000')
        ->and($source)->not->toContain('window.recentlyPublishedVersionId')
        ->and($source)->not->toContain('globalThis.recentlyPublishedVersionId');
});
