<?php

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

    $permission = Permission::create(['slug' => 'inventory-materials-manage']);
    $role = Role::create(['name' => 'materials-manager']);
    $role->permissions()->attach($permission);

    $this->authorizedUser->roles()->attach($role);
});

it('creates a uom category via ajax', function () {
    $response = $this->actingAs($this->authorizedUser)
        ->postJson(route('materials.uom-categories.store'), ['name' => 'Mass']);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'name'])
        ->assertJson(['name' => 'Mass']);

    $this->assertDatabaseHas('uom_categories', ['name' => 'Mass']);
});

it('updates a uom category via ajax', function () {
    $category = UomCategory::create(['name' => 'Mass']);

    $response = $this->actingAs($this->authorizedUser)
        ->patchJson(route('materials.uom-categories.update', $category), ['name' => 'Weight']);

    $response->assertOk()
        ->assertJson([
            'id' => $category->id,
            'name' => 'Weight',
        ]);

    $this->assertDatabaseHas('uom_categories', ['id' => $category->id, 'name' => 'Weight']);
});

it('deletes a uom category via ajax', function () {
    $category = UomCategory::create(['name' => 'Volume']);

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
    $category = UomCategory::create(['name' => 'Count']);

    $this->actingAs($this->authorizedUser)
        ->patchJson(route('materials.uom-categories.update', $category), ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate category names', function () {
    UomCategory::create(['name' => 'Mass']);

    $this->actingAs($this->authorizedUser)
        ->postJson(route('materials.uom-categories.store'), ['name' => 'Mass'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate names on update', function () {
    UomCategory::create(['name' => 'Mass']);
    $otherCategory = UomCategory::create(['name' => 'Volume']);

    $this->actingAs($this->authorizedUser)
        ->patchJson(route('materials.uom-categories.update', $otherCategory), ['name' => 'Mass'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
