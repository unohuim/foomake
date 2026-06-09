<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionLine;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Support\QuantityFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
            'is_stockable' => true,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->createRecipe = function (User $user, Item $outputItem, array $overrides = [], bool $publishCurrent = true): Recipe {
        $response = actingAs($user)->postJson(route('manufacturing.recipes.store'), array_merge([
            'item_id' => $outputItem->id,
            'recipe_type' => 'manufacturing',
            'name' => 'Recipe Parent',
            'output_quantity' => '10.000000',
            'is_active' => true,
            'is_default' => false,
        ], $overrides))->assertCreated();

        $recipe = Recipe::query()->findOrFail((int) $response->json('data.id'));

        if (! $publishCurrent) {
            return $recipe;
        }

        $draftVersion = $recipe->versions()->orderByDesc('id')->firstOrFail();
        actingAs($user)->postJson(route('manufacturing.recipes.versions.checkout', [$recipe, $draftVersion->id]))
            ->assertCreated();
        actingAs($user)->patchJson(route('manufacturing.recipes.versions.publish', [$recipe, $draftVersion->id]))
            ->assertOk();

        return $recipe->fresh(['currentVersion', 'item.baseUom']);
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

    $this->createDraftVersion = function (User $user, Recipe $recipe, RecipeVersion|int|null $sourceVersion = null): int {
        $source = $sourceVersion;

        if ($source === null) {
            $source = $recipe->fresh()->currentVersion()->firstOrFail();
        }

        return (int) actingAs($user)->postJson(route('manufacturing.recipes.versions.duplicate', [
            'recipe' => $recipe,
            'version' => $source instanceof RecipeVersion ? $source->id : $source,
        ]))
            ->assertCreated()
            ->json('data.id');
    };

    $this->addIngredient = function (User $user, Recipe $recipe, int $versionId, Item $item, string $quantity = '2.000000'): int {
        $lineId = (int) actingAs($user)->postJson(route('manufacturing.recipes.ingredients.store', [$recipe, $versionId]), [
            'item_id' => $item->id,
        ])->assertCreated()->json('data.id');

        actingAs($user)->patchJson(route('manufacturing.recipes.ingredients.update', [$recipe, $versionId, $lineId]), [
            'quantity' => $quantity,
        ])->assertOk();

        return $lineId;
    };

    $this->createMakeOrder = function (User $user, Recipe $recipe, array $overrides = []) {
        return actingAs($user)->postJson(route('manufacturing.make-orders.store'), array_merge([
            'recipe_id' => $recipe->id,
            'runs' => '2.000000',
        ], $overrides));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '{}', true);

        return is_array($payload) ? $payload : [];
    };
});

it('1. make_orders table keeps recipe_version_id', function (): void {
    expect(Schema::hasColumn('make_orders', 'recipe_version_id'))->toBeTrue();
});

it('2. make_order_lines table exists', function (): void {
    expect(Schema::hasTable('make_order_lines'))->toBeTrue();
});

it('3. make order create stores the recipe id', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    ($this->createMakeOrder)($user, $recipe)
        ->assertCreated()
        ->assertJsonPath('data.recipe_id', $recipe->id);
});

it('4. make order create uses recipes current_version_id by default', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    ($this->createMakeOrder)($user, $recipe)
        ->assertCreated()
        ->assertJsonPath('data.recipe_version_id', $recipe->fresh()->current_version_id);
});

it('5. make orders ignore the current users checked out draft and still use the current published version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    ($this->createDraftVersion)($user, $recipe);

    ($this->createMakeOrder)($user, $recipe)
        ->assertCreated()
        ->assertJsonPath('data.recipe_version_id', $recipe->fresh()->current_version_id);
});

it('6. make order create rejects archived versions even when explicitly submitted', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $oldCurrentVersionId = (int) $recipe->fresh()->current_version_id;
    $draftVersionId = ($this->createDraftVersion)($user, $recipe);
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.archive', [$recipe, $oldCurrentVersionId]))
        ->assertOk();

    ($this->createMakeOrder)($user, $recipe, ['recipe_version_id' => $oldCurrentVersionId])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);
});

it('7. make order create rejects recipes without a current published version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    Recipe::query()->whereKey($recipe->id)->update(['current_version_id' => null]);

    ($this->createMakeOrder)($user, $recipe)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);
});

it('8. make order lines snapshot recipe_version_lines into make_order_lines', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);

    $versionId = (int) $recipe->fresh()->current_version_id;
    $draftVersionId = ($this->createDraftVersion)($user, $recipe);
    ($this->addIngredient)($user, $recipe, $draftVersionId, $input, '1.500000');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    expect(DB::table('make_order_lines')->where('make_order_id', $makeOrderId)->count())->toBe(1)
        ->and((int) $recipe->fresh()->current_version_id)->not->toBe($versionId);
});

it('9. snapshot line quantities use bcmul string math with runs', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);

    $draftVersionId = ($this->createDraftVersion)($user, $recipe);
    ($this->addIngredient)($user, $recipe, $draftVersionId, $input, '1.234500');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe, ['runs' => '2.500000'])->assertCreated()->json('data.id');
    $line = DB::table('make_order_lines')->where('make_order_id', $makeOrderId)->first();

    expect(bccomp((string) $line->planned_quantity, bcmul('1.234500', '2.500000', 6), 6))->toBe(0)
        ->and(QuantityFormatter::format((string) $line->planned_quantity, 6))->toBe('3.086250');
});

it('10. later checkout drafts do not mutate existing make orders', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);

    $draftVersionId = ($this->createDraftVersion)($user, $recipe);
    ($this->addIngredient)($user, $recipe, $draftVersionId, $input, '1.000000');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    ($this->createDraftVersion)($user, $recipe);

    expect((int) DB::table('make_orders')->where('id', $makeOrderId)->value('recipe_version_id'))
        ->toBe((int) DB::table('make_orders')->where('id', $makeOrderId)->value('recipe_version_id'));
});

it('11. publishing a new draft changes future make orders to the new current version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $before = (int) $recipe->fresh()->current_version_id;
    $draftVersionId = ($this->createDraftVersion)($user, $recipe);

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    ($this->createMakeOrder)($user, $recipe)
        ->assertCreated()
        ->assertJsonPath('data.recipe_version_id', $draftVersionId);

    expect($before)->not->toBe($draftVersionId);
});

it('12. make order detail uses the snapshotted recipe version output quantity', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['output_quantity' => '4.500000']);

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe, ['runs' => '3.000000'])->assertCreated()->json('data.id');

    actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))
        ->assertOk()
        ->assertSee('13.50');
});

it('13. make order execution posts stock moves from make_order_lines rather than live recipe_version_lines', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);

    $draftVersionId = ($this->createDraftVersion)($user, $recipe);
    ($this->addIngredient)($user, $recipe, $draftVersionId, $input, '2.000000');
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $lineId = (int) DB::table('make_order_lines')->where('make_order_id', $makeOrderId)->value('id');
    actingAs($user)->patchJson(route('manufacturing.make-orders.lines.update', [$makeOrderId, $lineId]), [
        'planned_quantity' => '9.000000',
    ])->assertOk();

    actingAs($user)->postJson(route('manufacturing.make-orders.make', $makeOrderId))->assertOk();

    expect(StockMove::query()->where('source_id', $makeOrderId)->count())->toBe(2)
        ->and((string) StockMove::query()->where('source_id', $makeOrderId)->where('type', 'issue')->firstOrFail()->quantity)->toBe('-9.000000');
});

it('13a. make order execution creates stock moves only for stockable input and output items', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', [
        'is_manufacturable' => true,
        'is_stockable' => false,
    ]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt', ['is_stockable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['output_quantity' => '4.000000'], false);
    $currentVersion = $recipe->versions()->orderByDesc('id')->firstOrFail();
    ($this->checkoutVersion)($user, $recipe, $currentVersion)->assertCreated();
    ($this->addIngredient)($user, $recipe, $currentVersion->id, $input, '1.500000');
    ($this->publishVersion)($user, $recipe, $currentVersion)->assertOk();

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe, ['runs' => '2.000000'])->assertCreated()->json('data.id');

    actingAs($user)->postJson(route('manufacturing.make-orders.make', $makeOrderId))->assertOk();

    $moves = StockMove::query()
        ->where('source_id', $makeOrderId)
        ->orderBy('id')
        ->get();

    expect($moves)->toHaveCount(1)
        ->and($moves->first()->item_id)->toBe($input->id)
        ->and($moves->first()->type)->toBe('issue')
        ->and((string) $moves->first()->quantity)->toBe('-3.000000');
});

it('14. make order create requires inventory make orders execute permission', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);

    $owner = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');
    $recipe = ($this->createRecipe)($owner, $output);

    ($this->createMakeOrder)($user, $recipe)->assertForbidden();
});

it('15. make order view requires inventory make orders view permission', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $owner = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');
    ($this->grantPermission)($owner, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($owner, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($owner, $recipe)->assertCreated()->json('data.id');

    actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))
        ->assertForbidden();
});

it('16. make order line mutation is tenant isolated', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermission)($userA, 'inventory-make-orders-manage');
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');
    ($this->grantPermission)($userB, 'inventory-make-orders-manage');
    ($this->grantPermission)($userB, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenantB);
    $output = ($this->makeItem)($tenantB, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenantB, $uom, 'Salt');
    $recipe = ($this->createRecipe)($userB, $output);
    ($this->createDraftVersion)($userB, $recipe);
    $draftVersionId = (int) DB::table('recipe_version_checkouts')->where('user_id', $userB->id)->latest('id')->value('recipe_version_id');
    ($this->addIngredient)($userB, $recipe, $draftVersionId, $input, '2.000000');
    ($this->publishVersion)($userB, $recipe, $draftVersionId)->assertOk();
    $makeOrderId = (int) ($this->createMakeOrder)($userB, $recipe)->assertCreated()->json('data.id');
    $lineId = (int) DB::table('make_order_lines')->where('make_order_id', $makeOrderId)->value('id');

    actingAs($userA)->patchJson(route('manufacturing.make-orders.lines.update', [$makeOrderId, $lineId]), [
        'planned_quantity' => '5.000000',
    ])->assertNotFound();
});

it('17. make order create uses the published current version after a legacy approved row is normalized', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    DB::table('recipe_versions')
        ->where('id', $recipe->fresh()->current_version_id)
        ->update(['status' => 'APPROVED']);

    ($this->createMakeOrder)($user, $recipe)->assertCreated();
});

it('18. make order creation does not accept a users checked out draft as the selected explicit version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $draftVersionId = ($this->createDraftVersion)($user, $recipe);

    ($this->createMakeOrder)($user, $recipe, ['recipe_version_id' => $draftVersionId])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);
});

it('19. make order snapshot references remain stable after old current version becomes archived', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $currentVersionId = (int) $recipe->fresh()->current_version_id;

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $draftVersionId = ($this->createDraftVersion)($user, $recipe);
    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.archive', [$recipe, $currentVersionId]))
        ->assertOk();

    expect((int) DB::table('make_orders')->where('id', $makeOrderId)->value('recipe_version_id'))->toBe($currentVersionId)
        ->and(RecipeVersion::query()->findOrFail($currentVersionId)->status)->toBe('ARCHIVED');
});

it('20. make order execution output quantity equals runs times recipe version output quantity', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['output_quantity' => '6.250000']);

    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe, ['runs' => '2.000000'])->assertCreated()->json('data.id');

    actingAs($user)->postJson(route('manufacturing.make-orders.make', $makeOrderId))->assertOk();

    $receipt = StockMove::query()->where('source_id', $makeOrderId)->where('type', 'receipt')->firstOrFail();

    expect((string) $receipt->quantity)->toBe('12.500000');
});

it('21. make order detail uses the shared resource detail header breadcrumb component', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->toContain('x-resource-detail-header-breadcrumb')
        ->and($source)->toContain('<x-slot name="metadata">')
        ->and($source)->toContain('<x-slot name="actions">')
        ->and($source)->toContain("x-ingredients-detail-section");
});

it('22. make order detail breadcrumb renders home make orders and id label', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'breadcrumbs.0.label'))->toBe('Make Orders')
        ->and(data_get($payload, 'breadcrumbs.1.label'))->toBe('Make Order ' . $makeOrderId)
        ->and(data_get($payload, 'makeOrder.title'))->toBe('Make Order ' . $makeOrderId);
});

it('23. make order detail removes the old core section and surfaces core metadata in header payload', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->not->toContain('Output Item')
        ->and($source)->not->toContain('Total Output')
        ->and($source)->toContain('manufacturing-make-orders-show-payload');
});

it('24. make order detail renders ingredients section with reusable detail section card contract', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $componentSource = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));

    expect($source)->toContain('x-ingredients-detail-section')
        ->and($componentSource)->toContain('x-detail-section-card')
        ->and($source)->toContain('item-header="Ingredient"')
        ->and($source)->toContain('context-text-expression="\'\'"')
        ->and($source)->not->toContain(':context-text-expression="\'\'"')
        ->and($componentSource)->toContain("trim((string) \$contextTextExpression) !== ''")
        ->and($componentSource)->toContain('UOM')
        ->and($componentSource)->toContain('On Hand');
});

it('24a. make order ingredients section does not render the removed helper sentence', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->not->toContain('Editing make order ingredient snapshot lines.')
        ->and($source)->toContain('Make Order ingredient lines are editable snapshot rows and do not mutate the source recipe version.');
});

it('25. make order detail ingredients payload supports add remove and linked view actions', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant, 3);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt', ['is_purchasable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $lineResponse = actingAs($user)->postJson(route('manufacturing.make-orders.lines.store', $makeOrderId), [
        'input_item_id' => $input->id,
        'planned_quantity' => '3.250000',
        'line_type' => 'manual_adjustment',
    ])->assertCreated();

    $lineId = (int) $lineResponse->json('data.id');
    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    $line = collect(data_get($payload, 'ingredients.lines', []))->firstWhere('id', $lineId);

    expect($line['quantity'] ?? null)->toBe('3.250000')
        ->and($line['quantity_display'] ?? null)->toBe('3.250')
        ->and($line['view_url'] ?? null)->toBe(route('materials.show', $input))
        ->and($line['remove_url'] ?? null)->toBe(route('manufacturing.make-orders.lines.destroy', [$makeOrderId, $lineId]))
        ->and($line['purchase_url'] ?? null)->toBe(route('materials.show', $input));
});

it('26. make order detail ingredients payload includes on hand from tenant stock truth', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $input->stockMoves()->create([
        'tenant_id' => $tenant->id,
        'uom_id' => $uom->id,
        'quantity' => '5.250000',
        'type' => 'receipt',
        'status' => 'POSTED',
    ]);

    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $lineId = (int) actingAs($user)->postJson(route('manufacturing.make-orders.lines.store', $makeOrderId), [
        'input_item_id' => $input->id,
        'planned_quantity' => '1.000000',
        'line_type' => 'manual_adjustment',
    ])->assertCreated()->json('data.id');

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    $line = collect(data_get($payload, 'ingredients.lines', []))->firstWhere('id', $lineId);

    expect($line['on_hand'] ?? null)->toBe('5.250000')
        ->and($line['on_hand_display'] ?? null)->toBe('5.25');
});

it('27. make order line remove deletes the make order line', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');
    $lineId = (int) actingAs($user)->postJson(route('manufacturing.make-orders.lines.store', $makeOrderId), [
        'input_item_id' => $input->id,
        'planned_quantity' => '2.000000',
        'line_type' => 'manual_adjustment',
    ])->assertCreated()->json('data.id');

    actingAs($user)->deleteJson(route('manufacturing.make-orders.lines.destroy', [$makeOrderId, $lineId]))
        ->assertOk();

    expect(DB::table('make_order_lines')->where('id', $lineId)->exists())->toBeFalse();
});

it('27b. make order line remove returns a row-removal payload without the deleted ingredient', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $inputA = ($this->makeItem)($tenant, $uom, 'Salt');
    $inputB = ($this->makeItem)($tenant, $uom, 'Pepper');
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $lineAId = (int) actingAs($user)->postJson(route('manufacturing.make-orders.lines.store', $makeOrderId), [
        'input_item_id' => $inputA->id,
        'planned_quantity' => '2.000000',
        'line_type' => 'manual_adjustment',
    ])->assertCreated()->json('data.id');

    $lineBId = (int) actingAs($user)->postJson(route('manufacturing.make-orders.lines.store', $makeOrderId), [
        'input_item_id' => $inputB->id,
        'planned_quantity' => '1.000000',
        'line_type' => 'manual_adjustment',
    ])->assertCreated()->json('data.id');

    $response = actingAs($user)->deleteJson(route('manufacturing.make-orders.lines.destroy', [$makeOrderId, $lineAId]))
        ->assertOk()
        ->assertJsonPath('deleted_line_id', $lineAId);

    expect(collect($response->json('lines'))->pluck('id')->all())
        ->toBe([$lineBId])
        ->and(collect($response->json('lines'))->pluck('id')->all())
        ->not->toContain($lineAId);
});

it('28. make order detail ingredients default open comes from the shared detail section contract', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->toContain('x-ingredients-detail-section')
        ->and($source)->toContain(":default-open=\"true\"")
        ->and($source)->not->toContain('data-make-order-ingredient-add-bar');
});

it('29. make order detail ingredients use the shared add row contract with left search and right add button', function (): void {
    $source = File::get(resource_path('views/components/detail-section-card.blade.php'));
    $ingredientSource = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));

    expect($source)->toContain('data-detail-section-add-row')
        ->and($source)->toContain('data-detail-section-add-row-left')
        ->and($source)->toContain('data-detail-section-add-row-right')
        ->and($ingredientSource)->toContain('data-ingredients-add-row')
        ->and($ingredientSource)->toContain('data-ingredients-add-row-left')
        ->and($ingredientSource)->toContain('data-ingredients-add-row-right')
        ->and($ingredientSource)->not->toContain('label="Item"')
        ->and($ingredientSource)->not->toContain('data-make-order-ingredient-add-bar');
});

it('30. make order detail ingredients opt out of the vertical dots menu and use a direct x mark remove button', function (): void {
    $showSource = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $ingredientSource = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));

    expect($showSource)->toContain(':show-row-actions-menu="false"')
        ->and($ingredientSource)->toContain("'showRowActionsMenu' => true")
        ->and($ingredientSource)->toContain('data-ingredients-remove-button')
        ->and($ingredientSource)->toContain('aria-label="Remove ingredient"')
        ->and($ingredientSource)->toContain('rounded-full border border-gray-300')
        ->and($ingredientSource)->toContain('d="M6 18 18 6M6 6l12 12"');
});

it('30b. shared ingredient section preserves non clipping overflow for comboboxes and optional row actions', function (): void {
    $cardSource = File::get(resource_path('views/components/detail-section-card.blade.php'));
    $ingredientSource = File::get(resource_path('views/components/ingredients-detail-section.blade.php'));

    expect($cardSource)->toContain('-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500')
        ->and($cardSource)->toContain('first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200')
        ->and($cardSource)->toContain('border-t border-gray-100 bg-white')
        ->and($ingredientSource)->toContain('overflow-x-auto overflow-y-visible')
        ->and($ingredientSource)->toContain('x-dropdown')
        ->and($ingredientSource)->not->toContain('data-make-order-ingredient-add-bar');
});

it('30c. make order ingredient remove stays ajax first and only surfaces error feedback on failure', function (): void {
    $source = File::get(resource_path('js/pages/manufacturing-make-orders-show.js'));

    expect($source)->toContain('const deletedLineId = data?.deleted_line_id ?? line.id;')
        ->and($source)->toContain("this.ingredients.lines = nextLines.length > 0")
        ->and($source)->toContain("this.showToast('error', 'Unable to remove ingredient.');")
        ->and($source)->not->toContain("this.showToast('success', 'Ingredient removed.');");
});

it('31. make order detail header payload stays compact and avoids the old id only title contract', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'makeOrder.title'))->toBe('Make Order ' . $makeOrderId)
        ->and(data_get($payload, 'makeOrder.recipe_name'))->toBe($recipe->name)
        ->and(data_get($payload, 'makeOrder.output_item_name'))->toBe($output->name)
        ->and(data_get($payload, 'makeOrder.status'))->toBe('DRAFT')
        ->and(data_get($payload, 'makeOrder.workflow_state'))->toBe('DRAFT')
        ->and(data_get($payload, 'makeOrder.runs_text'))->toBe('2')
        ->and(data_get($payload, 'makeOrder.expected_output_qty_text'))->not->toBe('')
        ->and(data_get($payload, 'makeOrder.actual_output_qty_text'))->toBe('');
});

it('31a. make order detail header renders recipe and runs before output item and expected output quantity', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->toContain('data-resource-detail-header-metadata-group="primary"')
        ->and($source)->toContain('data-resource-detail-header-metadata-group="secondary"')
        ->and($source)->toContain("{{ \$makeOrderPayload['recipe_name'] ?? '—' }}")
        ->and($source)->toContain("{{ __('Runs') }} {{ \$makeOrderPayload['runs_text'] ?? '—' }}")
        ->and($source)->not->toContain("{{ \$makeOrderPayload['workflow_state'] ?? 'DRAFT' }}")
        ->and($source)->toContain("{{ \$makeOrderPayload['output_item_name'] ?? '—' }}")
        ->and($source)->toContain("{{ __('Expected Output') }} {{ \$makeOrderPayload['expected_output_qty_text'] ?? '—' }}");
});

it('31b. make order detail header does not duplicate output item or expected output quantity in the first metadata group', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->not->toContain("{{ __('Qty') }} {{ \$makeOrderPayload['expected_output_qty_text'] ?? '—' }}")
        ->and($source)->not->toContain('data-resource-detail-header-metadata-group="primary"' . "\n" . '                >' . "\n" . '                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">' . "\n" . "                        {{ \$makeOrderPayload['output_item_name'] ?? '—' }}");
});

it('31c. make order detail header renders the next valid workflow stage action from the shared header actions slot', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->toContain('<x-slot name="actions">')
        ->and($source)->toContain('<x-slot name="titleSuffix">')
        ->and($source)->toContain("x-show=\"workflow.next_stage_action && workflow.next_stage_action.label\"")
        ->and($source)->toContain('x-on:click.prevent="performHeaderWorkflowAction()"')
        ->and($source)->toContain("x-text=\"workflow.next_stage_action?.label || ''\"")
        ->and($source)->toContain('x-on:click="if (!$el.disabled) { $el.showPicker?.() }"')
        ->and($source)->not->toContain('data-make-order-header-workflow-button')
        ->and($source)->not->toContain("\$makeOrderPayload['workflow_stage_name'] ?? \$makeOrderPayload['status'] ?? '—'");
});

it('32. existing make order ingredient lines are visible on initial payload render', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrderId = (int) ($this->createMakeOrder)($user, $recipe)->assertCreated()->json('data.id');

    actingAs($user)->postJson(route('manufacturing.make-orders.lines.store', $makeOrderId), [
        'item_id' => $input->id,
        'quantity' => '2.500000',
    ])->assertCreated();

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrderId))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'ingredients.lines'))->toHaveCount(1)
        ->and(data_get($payload, 'ingredients.can_edit'))->toBeTrue();
});

it('33. make order detail renders details then tasks then ingredients through the shared detail section contract', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    $workflowPosition = strpos($source, "title=\"Details\"");
    $tasksPosition = strpos($source, "title=\"Tasks\"");
    $ingredientsPosition = strpos($source, 'x-ingredients-detail-section');

    expect($source)->toContain('x-detail-section-card')
        ->and($workflowPosition)->not->toBeFalse()
        ->and($tasksPosition)->not->toBeFalse()
        ->and($ingredientsPosition)->not->toBeFalse()
        ->and($workflowPosition)->toBeLessThan($tasksPosition)
        ->and($tasksPosition)->toBeLessThan($ingredientsPosition);
});

it('34. make order detail workflow payload includes due date assignee and available stages without duplicating workflow details in the header', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($assignee, 'inventory-make-orders-view');
    ($this->grantPermission)($assignee, 'inventory-make-orders-execute');

    $domain = WorkflowDomain::query()->firstOrCreate([
        'key' => 'manufacturing',
    ], [
        'name' => 'Manufacturing',
    ]);

    $stageA = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'production',
    ], [
        'name' => 'Production',
        'action_verb' => 'PRODUCE',
        'description' => 'Build the batch.',
        'sort_order' => 10,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    $stageB = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'completed',
    ], [
        'name' => 'Completed',
        'action_verb' => 'COMPLETE',
        'description' => 'Close the batch.',
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrder = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => $stageA->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $assignee->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrder))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'workflow.default_open'))->toBeFalse()
        ->and(data_get($payload, 'workflow.current_stage.id'))->toBe($stageA->id)
        ->and(data_get($payload, 'workflow.current_stage.name'))->toBe('Production')
        ->and(data_get($payload, 'workflow.made_by_user_id'))->toBe($assignee->id)
        ->and(data_get($payload, 'workflow.owner_user_name'))->toBe($assignee->name)
        ->and(data_get($payload, 'workflow.can_edit_due_date'))->toBeTrue()
        ->and(data_get($payload, 'workflow.due_date_update_url'))->toBe(route('manufacturing.make-orders.due-date.update', $makeOrder))
        ->and(data_get($payload, 'workflow.assignment_update_url'))->toBe(route('manufacturing.make-orders.assignment.update', $makeOrder))
        ->and(data_get($payload, 'workflow.assignee_options.0.label'))->toBe('Unassigned')
        ->and(data_get($payload, 'workflow.assignee_options.0.value'))->toBe('')
        ->and(data_get($payload, 'workflow.due_date'))->toBe('2026-06-01')
        ->and(data_get($payload, 'workflow.available_stages'))->toHaveCount(1)
        ->and(data_get($payload, 'workflow.available_stages.0.id'))->toBe($stageB->id)
        ->and(data_get($payload, 'workflow.next_stage_action.id'))->toBe($stageA->id)
        ->and(data_get($payload, 'workflow.next_stage_action.label'))->toBe('PRODUCE')
        ->and(data_get($payload, 'workflow.next_stage_action.type'))->toBe('make')
        ->and(data_get($payload, 'workflow.next_stage_action.endpoint'))->toBe(route('manufacturing.make-orders.make', $makeOrder))
        ->and(data_get($payload, 'makeOrder.due_date'))->toBe('2026-06-01')
        ->and(data_get($payload, 'makeOrder'))->not->toHaveKey('owner_user_name')
        ->and(data_get($payload, 'makeOrder'))->not->toHaveKey('workflow_tasks');
});

it('34a. make order workflow payload exposes editable tenant scoped assignee options and unassigned state', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $otherTenant = ($this->makeTenant)('Tenant B');
    $user = ($this->makeUser)($tenant);
    $tenantAssignee = ($this->makeUser)($tenant);
    $foreignAssignee = ($this->makeUser)($otherTenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($tenantAssignee, 'inventory-make-orders-execute');

    $domain = WorkflowDomain::query()->firstOrCreate([
        'key' => 'manufacturing',
    ], [
        'name' => 'Manufacturing',
    ]);

    $stageA = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'production',
    ], [
        'name' => 'Production',
        'action_verb' => 'PRODUCE',
        'description' => 'Build the batch.',
        'sort_order' => 10,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    $stageB = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'completed',
    ], [
        'name' => 'Completed',
        'action_verb' => 'COMPLETE',
        'description' => 'Close the batch.',
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrder = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => $stageA->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => null,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrder))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    $optionLabels = collect(data_get($payload, 'workflow.assignee_options', []))
        ->pluck('label')
        ->values()
        ->all();

    expect(data_get($payload, 'workflow.default_open'))->toBeFalse()
        ->and(data_get($payload, 'workflow.made_by_user_id'))->toBeNull()
        ->and(data_get($payload, 'workflow.owner_user_name'))->toBeNull()
        ->and(data_get($payload, 'workflow.current_stage.name'))->toBe('Production')
        ->and(data_get($payload, 'workflow.next_stage_action.id'))->toBe($stageA->id)
        ->and(data_get($payload, 'workflow.next_stage_action.type'))->toBe('make')
        ->and(data_get($payload, 'workflow.due_date'))->toBe('2026-06-01')
        ->and($optionLabels)->toContain('Unassigned')
        ->and($optionLabels)->toContain($tenantAssignee->name)
        ->and($optionLabels)->not->toContain($foreignAssignee->name);
});

it('35. make order detail tasks payload includes current stage tasks and completion links', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->grantPermission)($assignee, 'inventory-make-orders-view');
    ($this->grantPermission)($assignee, 'inventory-make-orders-execute');

    $domain = WorkflowDomain::query()->firstOrCreate([
        'key' => 'manufacturing',
    ], [
        'name' => 'Manufacturing',
    ]);

    $stage = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'production',
    ], [
        'name' => 'Production',
        'action_verb' => 'PRODUCE',
        'description' => 'Build the batch.',
        'sort_order' => 10,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrder = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => $stage->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $assignee->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $task = Task::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'domain_record_id' => $makeOrder->id,
        'workflow_stage_id' => $stage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $assignee->id,
        'title' => 'Sanitize vat',
        'description' => 'Confirm vat is clean.',
        'sort_order' => 10,
        'status' => Task::STATUS_OPEN,
        'completed_at' => null,
        'completed_by_user_id' => null,
    ]);

    $payload = ($this->extractPayload)(
        actingAs($assignee)->get(route('manufacturing.make-orders.show', $makeOrder))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'workflow.current_stage_tasks'))->toHaveCount(1)
        ->and(data_get($payload, 'workflow.current_stage_tasks.0.id'))->toBe($task->id)
        ->and(data_get($payload, 'workflow.current_stage_tasks.0.title'))->toBe('Sanitize vat')
        ->and(data_get($payload, 'workflow.current_stage_tasks.0.can_complete'))->toBeTrue()
        ->and(data_get($payload, 'workflow.current_stage_tasks.0.complete_url'))->toBe(route('tasks.complete', $task));
});

it('35a. make order detail header payload uses workflow stage names and not lifecycle status for workflow labels', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $domain = WorkflowDomain::query()->firstOrCreate([
        'key' => 'manufacturing',
    ], [
        'name' => 'Manufacturing',
    ]);

    $stageA = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'production',
    ], [
        'name' => 'Production',
        'description' => 'Build the batch.',
        'sort_order' => 10,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    $stageB = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'completed',
    ], [
        'name' => 'Completed',
        'action_verb' => 'COMPLETE',
        'description' => 'Check the batch.',
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $withStage = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => $stageA->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $withStage))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'makeOrder.title'))->toBe('Make Order ' . $withStage->id)
        ->and(data_get($payload, 'makeOrder.recipe_name'))->toBe($recipe->name)
        ->and(data_get($payload, 'makeOrder.runs_text'))->toBe('2')
        ->and(data_get($payload, 'makeOrder.expected_output_qty_text'))->not->toBe('')
        ->and(data_get($payload, 'makeOrder.actual_output_qty_text'))->toBe('')
        ->and(data_get($payload, 'makeOrder.workflow_stage_name'))->toBe('Production')
        ->and(data_get($payload, 'makeOrder.workflow_state'))->toBe('Production')
        ->and(data_get($payload, 'makeOrder.status'))->toBe(MakeOrder::STATUS_SCHEDULED)
        ->and(data_get($payload, 'workflow.next_stage_action.label'))->toBe('PRODUCE');

    $stageA->forceFill(['name' => 'Cook', 'action_verb' => 'COOK'])->save();
    $stageB->forceFill(['name' => 'Ready for QA', 'action_verb' => 'QA READY'])->save();

    $renamedPayload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $withStage->fresh()))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($renamedPayload, 'makeOrder.workflow_stage_name'))->toBe('Cook')
        ->and(data_get($renamedPayload, 'makeOrder.workflow_state'))->toBe('Cook')
        ->and(data_get($renamedPayload, 'workflow.next_stage_action.label'))->toBe('COOK')
        ->and(data_get($renamedPayload, 'makeOrder.status'))->toBe(MakeOrder::STATUS_SCHEDULED);

    $withoutStage = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => null,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '3.000000',
        'expected_output_qty' => '30.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-02',
        'scheduled_at' => now(),
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $withoutStagePayload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $withoutStage))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($withoutStagePayload, 'makeOrder.workflow_stage_name'))->toBeNull()
        ->and(data_get($withoutStagePayload, 'makeOrder.workflow_state'))->toBe(MakeOrder::STATUS_DRAFT)
        ->and(data_get($withoutStagePayload, 'makeOrder.status'))->toBe(MakeOrder::STATUS_SCHEDULED)
        ->and(data_get($withoutStagePayload, 'workflow.next_stage_action.label'))->toBe('COOK');
});

it('35b. make order detail header source and controller payload do not hardcode workflow labels', function (): void {
    $viewSource = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $controllerSource = File::get(app_path('Http/Controllers/MakeOrderController.php'));
    $pageModuleSource = File::get(resource_path('js/pages/manufacturing-make-orders-show.js'));
    $detailSectionDocSource = File::get(base_path('docs/architecture/ui/ReusableDetailSection.yaml'));

    expect($viewSource)->not->toContain("\$makeOrderPayload['workflow_stage_name'] ?? \$makeOrderPayload['status'] ?? '—'")
        ->and($viewSource)->not->toContain("{{ \$makeOrderPayload['status'] }}")
        ->and($viewSource)->toContain('<x-dropdown-select')
        ->and($viewSource)->toContain("x-text=\"makeOrder.workflow_state || 'DRAFT'\"")
        ->and($viewSource)->toContain('x-model="workflow.made_by_user_id"')
        ->and($viewSource)->toContain('type="date"')
        ->and($viewSource)->toContain('x-model="workflow.due_date"')
        ->and($viewSource)->toContain('data-make-order-workflow-metadata-row')
        ->and($viewSource)->not->toContain('Save Assignee')
        ->and($viewSource)->not->toContain('Save Due Date')
        ->and($viewSource)->not->toContain('{{ __(\'Move Stage\') }}')
        ->and($viewSource)->not->toContain('{{ __(\'Current Stage\') }}')
        ->and($viewSource)->not->toContain('x-on:click="saveWorkflowAssignment()"')
        ->and($viewSource)->not->toContain('x-on:click="moveWorkflowStage()"')
        ->and($controllerSource)->not->toContain("'status_tone'")
        ->and($controllerSource)->not->toContain("'workflow_stage_name' => 'Production'")
        ->and($controllerSource)->not->toContain("'workflow_stage_name' => 'Completed'")
        ->and($controllerSource)->not->toContain("'next_stage_action' => ['label' => 'Production']")
        ->and($controllerSource)->not->toContain("'next_stage_action' => ['label' => 'Completed']")
        ->and($controllerSource)->toContain("'assignment_update_url'")
        ->and($controllerSource)->toContain("'due_date_update_url'")
        ->and($controllerSource)->toContain("'assignee_options'")
        ->and($controllerSource)->toContain("'can_edit_due_date'")
        ->and($controllerSource)->toContain("'made_by_user_id'")
        ->and($controllerSource)->toContain("'expected_output_qty_text'")
        ->and($controllerSource)->toContain("'actual_output_qty_text'")
        ->and($controllerSource)->toContain("'details_update_url'")
        ->and($controllerSource)->not->toContain("\$makeOrder->assigned_to_user_id")
        ->and($pageModuleSource)->toContain('saveWorkflowDueDate')
        ->and($pageModuleSource)->toContain('workflowDueDateSaving')
        ->and($pageModuleSource)->toContain('workflow.due_date_update_url')
        ->and($pageModuleSource)->not->toContain('syncHeaderState')
        ->and($pageModuleSource)->not->toContain("new CustomEvent('make-order-header-sync'")
        ->and($pageModuleSource)->toContain('next_stage_action: asRecord(workflowPayload.next_stage_action)')
        ->and($pageModuleSource)->toContain('next_stage_action: asRecord(data.next_stage_action)')
        ->and($pageModuleSource)->toContain('performHeaderWorkflowAction')
        ->and($pageModuleSource)->toContain('makeCurrentOrder')
        ->and($pageModuleSource)->toContain('saveWorkflowAssignment')
        ->and($pageModuleSource)->toContain('saveMakeOrderDetailQuantity')
        ->and($pageModuleSource)->toContain('$watch(\'workflow.made_by_user_id\'')
        ->and($pageModuleSource)->not->toContain('Save Assignee')
        ->and($pageModuleSource)->not->toContain('saveWorkflowStage')
        ->and($pageModuleSource)->not->toContain("this.workflow.current_stage =")
        ->and($detailSectionDocSource)->toContain('Single-field selects should autosave on change when the update is safe')
        ->and($detailSectionDocSource)->toContain('must not add separate save buttons for safe single-field dropdown/select updates');
});

it('35c. make order detail omits the header workflow action when no valid next stage exists', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $domain = WorkflowDomain::query()->firstOrCreate([
        'key' => 'manufacturing',
    ], [
        'name' => 'Manufacturing',
    ]);

    $finalStage = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'completed',
    ], [
        'name' => 'Completed',
        'description' => 'Close the batch.',
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    WorkflowStage::withoutGlobalScopes()->updateOrCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'key' => 'production',
    ], [
        'name' => 'Production',
        'description' => 'Build the batch.',
        'sort_order' => 10,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);
    $makeOrder = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => $finalStage->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrder))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'workflow.next_stage_action'))->toBeNull();
});

it('35d. make order detail header renders visible workflow state beside the title instead of in metadata rows', function (): void {
    $headerSource = File::get(resource_path('views/components/resource-detail-header-breadcrumb.blade.php'));
    $showSource = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($headerSource)->toContain('@isset($titleSuffix)')
        ->and($headerSource)->toContain('data-resource-detail-header-title-row')
        ->and($showSource)->not->toContain("x-on:make-order-header-sync.window=\"syncHeader(\$event.detail)\"")
        ->and($showSource)->toContain('<x-slot name="titleSuffix">')
        ->and($showSource)->toContain("x-text=\"makeOrder.workflow_state || 'DRAFT'\"");
});

it('35e. make order detail details section keeps runs expected output actual output due date and assignee in one compact shared grid', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $pageSource = File::get(resource_path('js/pages/manufacturing-make-orders-show.js'));

    expect($source)->toContain('data-make-order-workflow-metadata-row')
        ->and($source)->toContain('grid grid-cols-3 gap-2')
        ->and($source)->toContain('grid grid-cols-2 gap-2')
        ->and($source)->toContain("title=\"Details\"")
        ->and($source)->toContain("{{ __('Runs') }}")
        ->and($source)->toContain("{{ __('Expected Output') }}")
        ->and($source)->toContain("{{ __('Actual Output') }}")
        ->and($source)->toContain("x-model=\"makeOrder.runs_text\"")
        ->and($source)->toContain("after-input=\"recalculateExpectedOutputQtyFromRuns()\"")
        ->and($source)->toContain("x-model=\"makeOrder.expected_output_qty_text\"")
        ->and($source)->toContain('readonly')
        ->and($source)->not->toContain("saveMakeOrderDetailQuantity('expected_output_qty')")
        ->and($source)->toContain("x-model=\"makeOrder.actual_output_qty_text\"")
        ->and($source)->toContain("{{ __('Due Date') }}")
        ->and($source)->toContain("{{ __('Assigned To') }}")
        ->and($source)->toContain('button-class=')
        ->and($source)->not->toContain('{{ __(\'Move Stage\') }}')
        ->and($source)->not->toContain('{{ __(\'Current Stage\') }}')
        ->and($pageSource)->toContain('recalculateExpectedOutputQtyFromRuns()')
        ->and($pageSource)->not->toContain('expected_output_qty: this.makeOrder.expected_output_qty');
});

it('35eb. make order detail payload uses user friendly runs text and output uom precision formatted expected and actual output text', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output, ['output_quantity' => '4.521000']);

    $makeOrder = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => null,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.500000',
        'expected_output_qty' => '11.302500',
        'actual_output_qty' => '11.257500',
        'status' => MakeOrder::STATUS_DRAFT,
        'due_date' => null,
        'scheduled_at' => null,
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $payload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrder))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'makeOrder.runs_text'))->toBe('2.5')
        ->and(data_get($payload, 'makeOrder.expected_output_qty_text'))->toBe('11.30')
        ->and(data_get($payload, 'makeOrder.actual_output_qty_text'))->toBe('11.26');
});

it('35ea. make order detail shows actual output value when present and leaves it blank safely when null', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-execute');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant, 2);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    $recipe = ($this->createRecipe)($user, $output);

    $withActual = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => null,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'actual_output_qty' => '19.250000',
        'status' => MakeOrder::STATUS_MADE,
        'due_date' => null,
        'scheduled_at' => null,
        'made_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $withoutActual = MakeOrder::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'recipe_id' => $recipe->id,
        'recipe_version_id' => $recipe->current_version_id,
        'workflow_stage_id' => null,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
        'output_item_id' => $recipe->item_id,
        'runs' => '2.000000',
        'expected_output_qty' => '20.000000',
        'actual_output_qty' => null,
        'status' => MakeOrder::STATUS_DRAFT,
        'due_date' => null,
        'scheduled_at' => null,
        'made_at' => null,
        'created_by_user_id' => $user->id,
    ]);

    $withActualPayload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $withActual))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );
    $withoutActualPayload = ($this->extractPayload)(
        actingAs($user)->get(route('manufacturing.make-orders.show', $withoutActual))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($withActualPayload, 'makeOrder.actual_output_qty_text'))->toBe('19.25')
        ->and(data_get($withoutActualPayload, 'makeOrder.actual_output_qty_text'))->toBe('');
});

it('35f. make order detail renders current stage tasks in a separate tasks section that defaults closed', function (): void {
    $source = File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));

    expect($source)->toContain("title=\"Tasks\"")
        ->and($source)->toContain(":description=\"__('Complete current stage tasks separately from workflow metadata editing.')\"")
        ->and($source)->toContain(":default-open=\"false\"")
        ->and($source)->toContain("{{ __('Current Stage Tasks') }}")
        ->and($source)->toContain('workflow.current_stage_tasks.length === 0')
        ->and($source)->toContain('x-on:click="completeWorkflowTask(task)"');
});
