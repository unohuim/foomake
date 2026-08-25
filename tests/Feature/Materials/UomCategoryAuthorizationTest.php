<?php
// tests/Feature/Materials/UomCategoryAuthorizationTest.php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->authorizedUser = User::factory()
        ->for($this->tenant)
        ->create();

    $this->unauthorizedUser = User::factory()
        ->for($this->tenant)
        ->create();

    $permission = Permission::firstOrCreate([
        'slug' => 'inventory-materials-manage',
    ]);

    $role = Role::firstOrCreate([
        'name' => 'materials-manager',
    ]);

    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $this->authorizedUser->roles()->syncWithoutDetaching([$role->id]);
});

it('allows users with permission to access the uom categories index', function () {
    $this->actingAs($this->authorizedUser)
        ->get(route('materials.uom-categories.index'))
        ->assertOk();
});

it('renders the uom categories index as an inertia page', function () {
    UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mass Custom',
    ]);

    $this->actingAs($this->authorizedUser)
        ->get(route('materials.uom-categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Materials/UomCategories/Index')
            ->where('crudConfig.resource', 'uom-categories')
            ->where('payload.storeUrl', route('materials.uom-categories.store', absolute: false))
            ->where('payload.categories.0.name', 'Mass Custom'));
});

it('denies users without permission from uom category routes', function () {
    $category = UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Volume Custom',
    ]);

    $this->actingAs($this->unauthorizedUser)
        ->get(route('materials.uom-categories.index'))
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->postJson(route('materials.uom-categories.store'), ['name' => 'Mass'])
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->patchJson(route('materials.uom-categories.update', $category), ['name' => 'Weight'])
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->deleteJson(route('materials.uom-categories.destroy', $category))
        ->assertForbidden();
});
