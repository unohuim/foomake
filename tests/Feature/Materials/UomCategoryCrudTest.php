<?php
// tests/Feature/Materials/UomCategoryCrudTest.php

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

    $permission = Permission::firstOrCreate([
        'slug' => 'inventory-materials-manage',
    ]);

    $role = Role::firstOrCreate([
        'name' => 'materials-manager',
    ]);

    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $this->authorizedUser->roles()->syncWithoutDetaching([$role->id]);
});

it('creates a uom category via ajax', function () {
    $response = $this->actingAs($this->authorizedUser)
        ->postJson(route('materials.uom-categories.store'), ['name' => 'Mass Custom']);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'name'])
        ->assertJson(['name' => 'Mass Custom']);

    $this->assertDatabaseHas('uom_categories', ['name' => 'Mass Custom']);
});

it('updates a uom category via ajax', function () {
    $category = UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mass Custom',
    ]);

    $response = $this->actingAs($this->authorizedUser)
        ->patchJson(route('materials.uom-categories.update', $category), ['name' => 'Weight']);

    $response->assertOk()
        ->assertJson([
            'id' => $category->id,
            'name' => 'Weight',
        ]);

    $this->assertDatabaseHas('uom_categories', [
        'id' => $category->id,
        'name' => 'Weight',
    ]);
});

it('deletes a uom category via ajax', function () {
    $category = UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Volume Custom',
    ]);

    $this->actingAs($this->authorizedUser)
        ->deleteJson(route('materials.uom-categories.destroy', $category))
        ->assertNoContent();

    $this->assertDatabaseMissing('uom_categories', ['id' => $category->id]);
});

it('validates required name on create', function () {
    $this->actingAs($this->authorizedUser)
        ->postJson(route('materials.uom-categories.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('validates required name on update', function () {
    $category = UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Count Custom',
    ]);

    $this->actingAs($this->authorizedUser)
        ->patchJson(route('materials.uom-categories.update', $category), ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate category names', function () {
    UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mass Custom',
    ]);

    $this->actingAs($this->authorizedUser)
        ->postJson(route('materials.uom-categories.store'), ['name' => 'Mass Custom'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate names on update', function () {
    UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mass Custom',
    ]);
    $otherCategory = UomCategory::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Volume Custom',
    ]);

    $this->actingAs($this->authorizedUser)
        ->patchJson(route('materials.uom-categories.update', $otherCategory), ['name' => 'Mass Custom'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
