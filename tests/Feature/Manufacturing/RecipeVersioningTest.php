<?php

declare(strict_types=1);

use App\Models\Item;
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

    $this->createRecipe = function (User $user, Item $outputItem, array $overrides = []): array {
        $response = actingAs($user)->postJson(route('manufacturing.recipes.store'), array_merge([
            'item_id' => $outputItem->id,
            'recipe_type' => 'manufacturing',
            'name' => 'Recipe Parent',
            'output_quantity' => '12.500000',
            'is_active' => true,
            'is_default' => false,
        ], $overrides))->assertCreated();

        $recipe = Recipe::query()->findOrFail((int) $response->json('data.id'));

        return [$response, $recipe];
    };

    $this->createDraftVersion = function (User $user, Recipe $recipe, array $overrides = []) {
        return actingAs($user)->postJson(route('manufacturing.recipes.versions.store', $recipe), array_merge([
            'recipe_type' => 'manufacturing',
            'output_quantity' => '12.500000',
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

    $this->checkInVersion = function (User $user, Recipe $recipe, RecipeVersion|int $version) {
        return actingAs($user)->postJson(route('manufacturing.recipes.versions.check-in', [
            'recipe' => $recipe,
            'version' => $version instanceof RecipeVersion ? $version->id : $version,
        ]));
    };
});

it('1. recipe_versions table exists', function (): void {
    expect(Schema::hasTable('recipe_versions'))->toBeTrue();
});

it('2. recipe_version_checkouts table exists', function (): void {
    expect(Schema::hasTable('recipe_version_checkouts'))->toBeTrue();
});

it('3. recipes table keeps current_version_id', function (): void {
    expect(Schema::hasColumn('recipes', 'current_version_id'))->toBeTrue();
});

it('4. recipe version status enum supports draft published and archived', function (): void {
    expect(RecipeVersion::STATUS_DRAFT)->toBe('DRAFT')
        ->and(RecipeVersion::STATUS_PUBLISHED)->toBe('PUBLISHED')
        ->and(RecipeVersion::STATUS_ARCHIVED)->toBe('ARCHIVED');
});

it('5. recipe create auto creates version 1.00 as draft', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);

    [, $recipe] = ($this->createRecipe)($user, $output);
    $version = $recipe->fresh()->versions()->orderBy('id')->firstOrFail();

    expect($version->status)->toBe(RecipeVersion::STATUS_DRAFT)
        ->and((int) $version->version_number)->toBe(100);
});

it('6. recipe create does not check out the auto created draft', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    expect(DB::table('recipe_version_checkouts')
        ->where('recipe_id', $recipe->id)
        ->count())->toBe(0);
});

it('7. recipe create leaves recipes current_version_id unset until publish', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    expect($recipe->fresh()->current_version_id)->toBeNull();
});

it('8. recipe create safely normalizes legacy approved input to draft version creation', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $response = actingAs($user)->postJson(route('manufacturing.recipes.versions.store', $recipe), [
        'recipe_type' => 'manufacturing',
        'output_quantity' => '9.000000',
        'status' => 'APPROVED',
    ])->assertCreated();

    expect($response->json('data.status'))->toBe(RecipeVersion::STATUS_DRAFT);
});

it('9. next created draft increments to 1.01 and is checked out to creator', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $response = ($this->createDraftVersion)($user, $recipe)->assertCreated();

    expect($response->json('data.status'))->toBe(RecipeVersion::STATUS_DRAFT)
        ->and($response->json('data.version_number_display'))->toBe('1.01')
        ->and(DB::table('recipe_version_checkouts')->whereNull('checked_in_at')->count())->toBe(1);
});

it('10. currentness comes from recipes current_version_id even when other published versions exist', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $firstDraftId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $firstDraftId)->assertOk();
    $firstPublishedId = (int) $recipe->fresh()->current_version_id;

    $secondDraftId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '13.500000',
    ])->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $secondDraftId)->assertOk();

    expect((int) $recipe->fresh()->current_version_id)->toBe($secondDraftId)
        ->and(RecipeVersion::query()->findOrFail($firstPublishedId)->status)->toBe(RecipeVersion::STATUS_PUBLISHED);
});

it('11. same user can checkout multiple different draft versions of the same recipe', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $draftVersionIdA = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    $draftVersionIdB = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '18.000000',
    ])->assertCreated()->json('data.id');

    expect(DB::table('recipe_version_checkouts')
        ->where('recipe_version_id', $draftVersionIdA)
        ->where('user_id', $user->id)
        ->whereNull('checked_in_at')
        ->exists())->toBeTrue();
    expect(DB::table('recipe_version_checkouts')
        ->where('recipe_version_id', $draftVersionIdB)
        ->where('user_id', $user->id)
        ->whereNull('checked_in_at')
        ->exists())->toBeTrue();

    expect(DB::table('recipe_version_checkouts')->whereNull('checked_in_at')->count())->toBe(2);
});

it('11a. same user cannot checkout the same draft version twice while an open checkout exists', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    ($this->checkoutVersion)($user, $recipe, $draftVersionId)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);

    expect(DB::table('recipe_version_checkouts')
        ->where('recipe_version_id', $draftVersionId)
        ->where('user_id', $user->id)
        ->whereNull('checked_in_at')
        ->count())->toBe(1);
});

it('11b. different users can checkout different draft versions of the same recipe', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $userA = ($this->makeUser)($tenant);
    $userB = ($this->makeUser)($tenant);
    ($this->grantPermission)($userA, 'inventory-make-orders-manage');
    ($this->grantPermission)($userB, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($userA, $output);

    $draftVersionIdA = (int) ($this->createDraftVersion)($userA, $recipe)->assertCreated()->json('data.id');
    $draftVersionIdB = (int) ($this->createDraftVersion)($userA, $recipe, [
        'output_quantity' => '18.000000',
    ])->assertCreated()->json('data.id');
    ($this->checkInVersion)($userA, $recipe, $draftVersionIdB)->assertOk();

    ($this->checkoutVersion)($userB, $recipe, $draftVersionIdB)->assertCreated();

    expect(DB::table('recipe_version_checkouts')
        ->where('recipe_version_id', $draftVersionIdA)
        ->where('user_id', $userA->id)
        ->whereNull('checked_in_at')
        ->exists())->toBeTrue()
        ->and(DB::table('recipe_version_checkouts')
            ->where('recipe_version_id', $draftVersionIdB)
            ->where('user_id', $userB->id)
            ->whereNull('checked_in_at')
            ->exists())->toBeTrue();
});

it('12. checkout of an archived version is blocked', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');
    $draftVersion = RecipeVersion::query()->findOrFail($draftVersionId);

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.archive', [$recipe, $draftVersion]))
        ->assertOk();

    ($this->checkoutVersion)($user, $recipe, $draftVersion)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);
});

it('13. publishing a checked out draft sets the draft to published', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    expect(RecipeVersion::query()->findOrFail($draftVersionId)->status)->toBe(RecipeVersion::STATUS_PUBLISHED);
});

it('14. publishing a draft keeps the old current version published but not current', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $firstDraftId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '7.000000',
    ])->assertCreated()->json('data.id');
    ($this->publishVersion)($user, $recipe, $firstDraftId)->assertOk();

    $oldCurrentId = (int) $recipe->fresh()->current_version_id;

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe, [
        'output_quantity' => '9.000000',
    ])->assertCreated()->json('data.id');

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    expect(RecipeVersion::query()->findOrFail($oldCurrentId)->status)->toBe(RecipeVersion::STATUS_PUBLISHED)
        ->and((int) $recipe->fresh()->current_version_id)->toBe($draftVersionId);
});

it('15. publishing a draft updates recipes current_version_id', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    expect((int) $recipe->fresh()->current_version_id)->toBe($draftVersionId);
});

it('16. publishing checks in the publishing users checkout when present', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    expect(DB::table('recipe_version_checkouts')
        ->where('recipe_version_id', $draftVersionId)
        ->where('user_id', $user->id)
        ->whereNull('checked_in_at')
        ->exists())->toBeFalse();
});

it('17. only checkout owner can update a checked out draft version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $owner = ($this->makeUser)($tenant);
    $other = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');
    ($this->grantPermission)($other, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($owner, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($owner, $recipe)->assertCreated()->json('data.id');
    ($this->checkInVersion)($owner, $recipe, $draftVersionId)->assertOk();
    ($this->checkoutVersion)($owner, $recipe, $draftVersionId)->assertCreated();

    actingAs($other)->patchJson(route('manufacturing.recipes.versions.update', [$recipe, $draftVersionId]), [
        'recipe_type' => 'manufacturing',
        'output_quantity' => '33.000000',
    ])->assertForbidden();
});

it('18. checkout owner can update a checked out draft version', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $owner = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($owner, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($owner, $recipe)->assertCreated()->json('data.id');
    ($this->checkInVersion)($owner, $recipe, $draftVersionId)->assertOk();
    ($this->checkoutVersion)($owner, $recipe, $draftVersionId)->assertCreated();

    actingAs($owner)->patchJson(route('manufacturing.recipes.versions.update', [$recipe, $draftVersionId]), [
        'recipe_type' => 'manufacturing',
        'output_quantity' => '33.000000',
    ])->assertOk();

    expect(RecipeVersion::query()->findOrFail($draftVersionId)->output_quantity)->toBe('33.000000');
});

it('19. check in removes editable state for the current user', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $owner = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($owner, $output);

    $draftVersionId = (int) ($this->createDraftVersion)($owner, $recipe)->assertCreated()->json('data.id');
    ($this->checkInVersion)($owner, $recipe, $draftVersionId)->assertOk();
    ($this->checkoutVersion)($owner, $recipe, $draftVersionId)->assertCreated();

    ($this->checkInVersion)($owner, $recipe, $draftVersionId)->assertOk();

    actingAs($owner)->patchJson(route('manufacturing.recipes.versions.update', [$recipe, $draftVersionId]), [
        'recipe_type' => 'manufacturing',
        'output_quantity' => '40.000000',
    ])->assertForbidden();
});

it('19a. other users do not inherit another users checkout display context after refresh', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $owner = ($this->makeUser)($tenant);
    $other = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');
    ($this->grantPermission)($owner, 'inventory-recipes-view');
    ($this->grantPermission)($other, 'inventory-make-orders-manage');
    ($this->grantPermission)($other, 'inventory-recipes-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($owner, $output);
    $publishedDraftId = (int) ($this->createDraftVersion)($owner, $recipe)->assertCreated()->json('data.id');
    ($this->publishVersion)($owner, $recipe, $publishedDraftId)->assertOk();

    $ownerDraftId = (int) ($this->createDraftVersion)($owner, $recipe, [
        'output_quantity' => '5.000000',
    ])->assertCreated()->json('data.id');

    $ownerRows = actingAs($owner)
        ->getJson(route('manufacturing.recipes.versions.index', $recipe))
        ->assertOk()
        ->json('data');

    $otherRows = actingAs($other)
        ->getJson(route('manufacturing.recipes.versions.index', $recipe))
        ->assertOk()
        ->json('data');

    $ownerDraftRowForOwner = collect($ownerRows)->firstWhere('id', $ownerDraftId);
    $ownerDraftRowForOther = collect($otherRows)->firstWhere('id', $ownerDraftId);

    expect(data_get($ownerDraftRowForOwner, 'display.contextText'))->toBe('Checked out by you')
        ->and(data_get($ownerDraftRowForOther, 'display.contextText'))->toBe('Checked out');
});

it('20. tenant isolation hides cross tenant checkout actions', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermission)($userA, 'inventory-make-orders-manage');
    ($this->grantPermission)($userB, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenantB);
    $output = ($this->makeItem)($tenantB, $uom, 'Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($userB, $output);
    $versionId = (int) $recipe->fresh()->versions()->orderBy('id')->value('id');

    actingAs($userA)->postJson(route('manufacturing.recipes.versions.checkout', [
        'recipe' => $recipe,
        'version' => $versionId,
    ]))->assertNotFound();
});

it('21. users without manage permission cannot checkout versions', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', ['is_manufacturable' => true]);

    [$response, $recipe] = ($this->createRecipe)((function () use ($tenant): User {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'inventory-make-orders-manage']);
        $role = Role::query()->firstOrCreate(['name' => 'owner-manage']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $owner->roles()->syncWithoutDetaching([$role->id]);

        return $owner;
    })(), $output);

    expect($response->json('data.id'))->not->toBeNull();

    actingAs($user)->postJson(route('manufacturing.recipes.versions.checkout', [
        'recipe' => $recipe,
        'version' => $recipe->fresh()->versions()->orderBy('id')->firstOrFail(),
    ]))->assertForbidden();
});

it('22. legacy approved rows are treated as published in list payloads', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Legacy Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);

    $legacyVersionId = (int) $recipe->fresh()->versions()->orderBy('id')->value('id');

    DB::table('recipe_versions')
        ->where('id', $legacyVersionId)
        ->update(['status' => 'APPROVED']);

    $row = actingAs($user)
        ->getJson(route('manufacturing.recipes.list'))
        ->assertOk()
        ->json('data.0');

    expect($row['version_status'] ?? null)->toBe('PUBLISHED');
});

it('23. users without manage permission cannot publish versions', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $owner = ($this->makeUser)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Publish Guard Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($owner, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($owner, $recipe)->assertCreated()->json('data.id');

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.publish', [$recipe, $draftVersionId]))
        ->assertForbidden();
});

it('24. tenant isolation rejects cross tenant publish attempts', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermission)($userA, 'inventory-make-orders-manage');
    ($this->grantPermission)($userB, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenantB);
    $output = ($this->makeItem)($tenantB, $uom, 'Cross Tenant Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($userB, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($userB, $recipe)->assertCreated()->json('data.id');

    actingAs($userA)->patchJson(route('manufacturing.recipes.versions.publish', [$recipe, $draftVersionId]))
        ->assertNotFound();
});

it('25. publishing an archived version is rejected', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Archived Publish Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.archive', [$recipe, $draftVersionId]))
        ->assertOk();

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.publish', [$recipe, $draftVersionId]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);
});

it('26. publishing an already published version is rejected and keeps current_version_id stable', function (): void {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-manage');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Published Publish Sauce', ['is_manufacturable' => true]);
    [, $recipe] = ($this->createRecipe)($user, $output);
    $draftVersionId = (int) ($this->createDraftVersion)($user, $recipe)->assertCreated()->json('data.id');

    ($this->publishVersion)($user, $recipe, $draftVersionId)->assertOk();

    actingAs($user)->patchJson(route('manufacturing.recipes.versions.publish', [$recipe, $draftVersionId]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);

    expect((int) $recipe->fresh()->current_version_id)->toBe($draftVersionId)
        ->and(RecipeVersion::query()->findOrFail($draftVersionId)->status)->toBe(RecipeVersion::STATUS_PUBLISHED);
});
