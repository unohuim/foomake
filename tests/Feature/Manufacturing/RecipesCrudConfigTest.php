<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionLine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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
        $slugs = $slug === 'inventory-make-orders-execute'
            ? ['inventory-make-orders-view', $slug]
            : [$slug];

        foreach ($slugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $permissionSlug,
            ]);

            $role = Role::query()->firstOrCreate([
                'name' => $permissionSlug . '-' . $user->id,
            ]);

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
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

    $this->createRecipe = function (User $user, Item $outputItem, array $overrides = []) {
        return actingAs($user)->postJson(route('manufacturing.recipes.store'), array_merge([
            'item_id' => $outputItem->id,
            'recipe_type' => 'manufacturing',
            'name' => 'Recipe Parent',
            'output_quantity' => '12.500000',
            'is_active' => true,
            'is_default' => false,
        ], $overrides));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $page = $response->viewData('page');

        if (is_array($page) && ($page['component'] ?? null) === 'Manufacturing/Recipes/Index') {
            return [
                'crudConfig' => $page['props']['crudConfig'] ?? [],
                ...($page['props']['payload'] ?? []),
            ];
        }

        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '{}', true);

        return is_array($payload) ? $payload : [];
    };

    $this->createDraftVersion = function (User $user, Recipe $recipe, array $overrides = []) {
        return actingAs($user)->postJson(route('manufacturing.recipes.versions.store', $recipe), array_merge([
            'recipe_type' => 'manufacturing',
            'output_quantity' => '12.500000',
        ], $overrides));
    };

    $this->publishVersion = function (User $user, Recipe $recipe, RecipeVersion|int $version) {
        return actingAs($user)->patchJson(route('manufacturing.recipes.versions.publish', [
            'recipe' => $recipe,
            'version' => $version instanceof RecipeVersion ? $version->id : $version,
        ]));
    };

    $this->addRecipeVersionLine = function (Tenant $tenant, RecipeVersion $version, Item $item, string $quantity): RecipeVersionLine {
        return RecipeVersionLine::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_version_id' => $version->id,
            'input_item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => $quantity,
            'sort_order' => 1,
        ]);
    };
});

it('1. redirects guests from recipes index', function (): void {
    $this->get(route('manufacturing.recipes.index'))
        ->assertRedirect('/?auth=login');
});

it('2. forbids authenticated users without inventory recipes view permission', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    actingAs($user)->get(route('manufacturing.recipes.index'))
        ->assertForbidden();
});

it('3. recipes index renders the shared Inertia resource index contract', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $response = actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk();

    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Manufacturing/Recipes/Index')
        ->has('shell')
        ->has('crudConfig')
        ->has('payload.initial_rows')
        ->has('payload.manufacturable_items'));
});

it('4. recipes crud config uses the recipes resource name', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect($payload['crudConfig']['resource'] ?? null)->toBe('recipes');
});

it('5. recipes crud config includes list create update and version store endpoints', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect($payload['crudConfig']['endpoints'] ?? [])->toHaveKeys(['list', 'create', 'update', 'versionStore']);
});

it('6. recipes crud config defines only the useful recipes index columns', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect($payload['crudConfig']['columns'] ?? [])->toBe([
        'name',
        'output_item_name',
        'current_version_number',
        'output_quantity',
        'updated_at',
    ]);
});

it('7. recipes crud config headers keep current version wording without active wording', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect($payload['crudConfig']['headers']['current_version_number'] ?? null)->toBe('Current Version')
        ->and(json_encode($payload['crudConfig']['headers']))->not->toContain('Active');
});

it('7b. recipes crud config headers do not include status or default', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect(array_key_exists('version_status', $payload['crudConfig']['headers'] ?? []))->toBeFalse()
        ->and(array_key_exists('is_default', $payload['crudConfig']['headers'] ?? []))->toBeFalse();
});

it('8. recipes crud config toolbar only exposes create and no import export', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect($payload['crudConfig']['permissions'] ?? [])->toMatchArray([
        'showCreate' => true,
        'showImport' => false,
        'showExport' => false,
    ]);
});

it('9. recipes crud config exposes make order edit and archive actions in order', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect(collect($payload['crudConfig']['actions'] ?? [])->pluck('label')->all())
        ->toBe(['Make Order', 'Edit', 'Archive']);
});

it('10. recipes index Vue page still includes the create recipe drawer', function (): void {
    $source = File::get(resource_path('js/pages/Manufacturing/Recipes/Index.vue'));

    expect($source)->toContain('Create Recipe')
        ->and($source)->toContain('ResourceCreateDrawer');
});

it('11. recipes index page module mounts the shared Vue resource card renderer', function (): void {
    $source = File::get(resource_path('js/pages/Manufacturing/Recipes/Index.vue'));

    expect($source)->toContain('ResourceIndex')
        ->and($source)->toContain('ResourceCardGrid')
        ->and($source)->toContain('recipeDetailRows');
});

it('12. recipes index page module formats current version numbers in x.xx form', function (): void {
    $source = File::get(resource_path('js/pages/Manufacturing/Recipes/Index.vue'));

    expect($source)->toContain('current_version_number_display');
});

it('13. recipes index page module does not reference approved status labels', function (): void {
    $source = File::get(resource_path('js/pages/Manufacturing/Recipes/Index.vue'));

    expect($source)->not->toContain('APPROVED')
        ->and($source)->not->toContain('Approved');
});

it('14. initial rows include created recipes', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    ($this->createRecipe)($user, $output, ['name' => 'Payload Recipe'])->assertCreated();

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect(collect($payload['initial_rows'] ?? [])->pluck('name')->all())->toContain('Payload Recipe');
});

it('15. list endpoint filters by recipe name', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    ($this->createRecipe)($user, $output, ['name' => 'Alpha Recipe'])->assertCreated();
    ($this->createRecipe)($user, $output, ['name' => 'Beta Recipe'])->assertCreated();

    $rows = actingAs($user)->getJson(route('manufacturing.recipes.list', ['search' => 'Alpha']))->assertOk()->json('data');

    expect(collect($rows)->pluck('name')->all())->toBe(['Alpha Recipe']);
});

it('16. list endpoint filters by output item name', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $soup = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $sauce = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    ($this->createRecipe)($user, $soup, ['name' => 'Soup Recipe'])->assertCreated();
    ($this->createRecipe)($user, $sauce, ['name' => 'Sauce Recipe'])->assertCreated();

    $rows = actingAs($user)->getJson(route('manufacturing.recipes.list', ['search' => 'Sauce']))->assertOk()->json('data');

    expect(collect($rows)->pluck('output_item_name')->all())->toBe(['Sauce']);
});

it('17. recipes list payload includes make only when recipe has a current published version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup Output', ['is_manufacturable' => true]);
    $ingredient = ($this->makeItem)($tenant, $uom, 'Ingredient ' . Str::uuid());
    $recipeId = (int) ($this->createRecipe)($user, $output, ['name' => 'Rendered Recipe'])->assertCreated()->json('data.id');
    $recipe = Recipe::query()->findOrFail($recipeId);

    $row = actingAs($user)->getJson(route('manufacturing.recipes.list'))->assertOk()->json('data.0');

    expect($row['current_version_number_display'] ?? null)->toBe('—')
        ->and($row['available_actions'] ?? [])->not->toContain('make');

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->addRecipeVersionLine)($tenant, RecipeVersion::query()->findOrFail($draftVersionId), $ingredient, '1.000000');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $publishedRow = actingAs($user)->getJson(route('manufacturing.recipes.list'))->assertOk()->json('data.0');

    expect($publishedRow['current_version_number_display'] ?? null)->toBe('1.01')
        ->and($publishedRow['version_status'] ?? null)->toBe('PUBLISHED')
        ->and($publishedRow['available_actions'] ?? [])->toBe(['make', 'edit', 'archive'])
        ->and($publishedRow['make_url'] ?? null)->toBe(route('manufacturing.recipes.make-orders.store', $recipe));
});

it('17a. recipes index make action refreshes the list without redirecting to the make order detail', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/Manufacturing/Recipes/Index.vue'));

    expect($pageSource)->toContain('async function makeOrder(record)')
        ->and($pageSource)->toContain('await fetchRecipes();')
        ->and($pageSource)->toContain('showToast("Make order created.");')
        ->and($pageSource)->not->toContain('window.location.assign(data.data.show_url);');
});

it('18. recipes list payload is tenant scoped', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermission)($userA, 'inventory-recipes-view');
    ($this->grantPermission)($userA, 'inventory-make-orders-manage');
    ($this->grantPermission)($userB, 'inventory-make-orders-manage');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $outputA = ($this->makeItem)($tenantA, $uomA, 'Soup A', ['is_manufacturable' => true]);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Soup B', ['is_manufacturable' => true]);
    ($this->createRecipe)($userA, $outputA, ['name' => 'Tenant A Recipe'])->assertCreated();
    ($this->createRecipe)($userB, $outputB, ['name' => 'Tenant B Recipe'])->assertCreated();

    $rows = actingAs($userA)->getJson(route('manufacturing.recipes.list'))->assertOk()->json('data');

    expect(collect($rows)->pluck('name')->all())->toBe(['Tenant A Recipe']);
});

it('19. recipes list endpoint respects view permission', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    actingAs($user)->getJson(route('manufacturing.recipes.list'))
        ->assertForbidden();
});

it('20. recipes list payload includes show url and linked text contract', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipeId = (int) ($this->createRecipe)($user, $output, ['name' => 'Soup Parent'])->assertCreated()->json('data.id');

    $payload = ($this->extractPayload)(actingAs($user)->get(route('manufacturing.recipes.index'))->assertOk(), 'manufacturing-recipes-index-payload');

    expect($payload['crudConfig']['rowDisplay']['columns']['name'] ?? [])->toMatchArray([
        'kind' => 'linked-text',
        'urlExpression' => 'record.show_url',
    ]);

    $row = actingAs($user)->getJson(route('manufacturing.recipes.list'))->assertOk()->json('data.0');

    expect($row['show_url'] ?? null)->toBe(route('manufacturing.recipes.show', $recipeId));
});

it('21. recipes index make action creates a make order from recipes current version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipeId = (int) ($this->createRecipe)($user, $output, ['name' => 'Soup Parent'])->assertCreated()->json('data.id');
    $recipe = Recipe::query()->findOrFail($recipeId);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->addRecipeVersionLine)($tenant, RecipeVersion::query()->findOrFail($draftVersionId), $input, '1.250000');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $response = actingAs($user)->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
        'runs' => '2.000000',
    ])->assertCreated();

    $makeOrderId = (int) $response->json('data.id');

    expect($response->json('data.recipe_version_id'))->toBe($draftVersionId)
        ->and($response->json('data.show_url'))->toBe(route('manufacturing.make-orders.show', $makeOrderId))
        ->and(Recipe::query()->findOrFail($recipeId)->current_version_id)->toBe($draftVersionId);
});

it('22. recipes index make action is rejected for draft only recipes', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipeId = (int) ($this->createRecipe)($user, $output, ['name' => 'Soup Parent'])->assertCreated()->json('data.id');
    $recipe = Recipe::query()->findOrFail($recipeId);

    actingAs($user)->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
        'runs' => '2.000000',
    ])->assertStatus(422);
});
