<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('allows users with inventory-materials-view permission to view materials index', function () {
    $permission = Permission::create([
        'slug' => 'inventory-materials-view',
    ]);

    $role = Role::create([
        'name' => 'Inventory',
    ]);

    $role->permissions()->attach($permission->id);

    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $user->roles()->attach($role->id);

    // Guard against missing gate registration
    expect(
        Gate::forUser($user)->allows('inventory-materials-view')
    )->toBeTrue();

    $response = $this->actingAs($user)->get('/materials');

    $response->assertOk();
    $response->assertSee('Materials');
});

it('forbids users without inventory-materials-view permission from viewing materials index', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    expect(
        Gate::forUser($user)->allows('inventory-materials-view')
    )->toBeFalse();

    $response = $this->actingAs($user)->get('/materials');

    $response->assertForbidden();
});
