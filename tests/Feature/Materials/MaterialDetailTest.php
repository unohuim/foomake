<?php

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeUom = function (): Uom {
        $suffix = Str::random(12);

        $category = UomCategory::query()->forceCreate([
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $suffix,
            'symbol' => 'u' . $suffix,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, array $overrides = []): array {
        $uom = $overrides['uom'] ?? ($this->makeUom)();

        $item = Item::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Material ' . Str::random(10),
            'base_uom_id' => $uom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => true,
        ], Arr::except($overrides, ['uom'])));

        return [$item, $uom];
    };

    $this->grantMaterialsViewPermission = function (User $user): void {
        // This MUST match the canonical permission slug enforced by Gates.
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'inventory-materials-view',
        ]);

        $role = Role::query()->forceCreate([
            'name' => 'materials-viewer-' . Str::random(12),
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };
});

it('redirects guests to login for the material detail page', function () {
    $tenant = Tenant::factory()->create();
    [$item] = ($this->makeItem)($tenant);

    $this->get(route('materials.show', $item))
        ->assertRedirect(route('login'));
});

it('forbids authenticated users without inventory-materials-view permission', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    [$item] = ($this->makeItem)($tenant);

    $this->actingAs($user)
        ->get(route('materials.show', $item))
        ->assertForbidden();
});

it('allows users with inventory-materials-view permission to view the material detail page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    ($this->grantMaterialsViewPermission)($user);

    [$item, $uom] = ($this->makeItem)($tenant);

    $this->actingAs($user)
        ->get(route('materials.show', $item))
        ->assertOk()
        ->assertSee($item->name)
        ->assertSee($uom->name . ' (' . $uom->symbol . ')')
        ->assertSee('Flags')
        ->assertSee('Purchasable')
        ->assertSee('Sellable')
        ->assertSee('Manufacturable')
        ->assertSee('Back to Materials');
});

it('returns 404 for cross-tenant material access (tenant scope hides other-tenant items)', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $user = User::factory()->for($tenant)->create();
    ($this->grantMaterialsViewPermission)($user);

    [$item] = ($this->makeItem)($otherTenant);

    $this->actingAs($user)
        ->get(route('materials.show', $item))
        ->assertNotFound();
});
