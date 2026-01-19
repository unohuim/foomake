<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->manageUser = User::factory()
        ->for($this->tenant)
        ->create();

    $this->viewUser = User::factory()
        ->for($this->tenant)
        ->create();

    $managePermission = Permission::firstOrCreate([
        'slug' => 'inventory-materials-manage',
    ]);

    $viewPermission = Permission::firstOrCreate([
        'slug' => 'inventory-materials-view',
    ]);

    $manageRole = Role::firstOrCreate([
        'name' => 'uom-manager',
    ]);

    $viewRole = Role::firstOrCreate([
        'name' => 'uom-viewer',
    ]);

    $manageRole->permissions()->syncWithoutDetaching([$managePermission->id]);
    $viewRole->permissions()->syncWithoutDetaching([$viewPermission->id]);

    $this->manageUser->roles()->syncWithoutDetaching([$manageRole->id]);
    $this->viewUser->roles()->syncWithoutDetaching([$viewRole->id]);
});

it('denies access to users without manage permission', function () {
    $this->actingAs($this->viewUser)
        ->get(route('manufacturing.uoms.index'))
        ->assertForbidden();
});

it('allows access to users with manage permission', function () {
    $this->actingAs($this->manageUser)
        ->get(route('manufacturing.uoms.index'))
        ->assertOk();
});

it('creates a uom via ajax', function () {
    $category = UomCategory::create(['name' => 'Mass']);

    $response = $this->actingAs($this->manageUser)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'uom_category_id', 'name', 'symbol'])
        ->assertJson([
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

    $this->assertDatabaseHas('uoms', [
        'uom_category_id' => $category->id,
        'name' => 'Gram',
        'symbol' => 'g',
    ]);
});

it('validates required fields on create', function () {
    $this->actingAs($this->manageUser)
        ->postJson(route('manufacturing.uoms.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['uom_category_id', 'name', 'symbol']);
});

it('requires a valid category on create', function () {
    $this->actingAs($this->manageUser)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => 9999,
            'name' => 'Liter',
            'symbol' => 'L',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['uom_category_id']);
});

it('updates a uom via ajax', function () {
    $category = UomCategory::create(['name' => 'Mass']);
    $newCategory = UomCategory::create(['name' => 'Volume']);

    $uom = Uom::create([
        'uom_category_id' => $category->id,
        'name' => 'Gram',
        'symbol' => 'g',
    ]);

    $response = $this->actingAs($this->manageUser)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $newCategory->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $uom->id,
            'uom_category_id' => $newCategory->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

    $this->assertDatabaseHas('uoms', [
        'id' => $uom->id,
        'uom_category_id' => $newCategory->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
    ]);
});

it('deletes a uom via ajax', function () {
    $category = UomCategory::create(['name' => 'Mass']);

    $uom = Uom::create([
        'uom_category_id' => $category->id,
        'name' => 'Gram',
        'symbol' => 'g',
    ]);

    $this->actingAs($this->manageUser)
        ->deleteJson(route('manufacturing.uoms.destroy', $uom))
        ->assertNoContent();

    $this->assertDatabaseMissing('uoms', ['id' => $uom->id]);
});
