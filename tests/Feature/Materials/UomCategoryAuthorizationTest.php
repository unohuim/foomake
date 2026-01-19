<?php
// tests/Feature/Materials/UomCategoryAuthorizationTest.php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('denies users without permission from uom category routes', function () {
    $category = UomCategory::create(['name' => 'Volume']);

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
