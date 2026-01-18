<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('allows users with permission to view materials index', function () {
    $permission = Permission::create(['slug' => 'inventory-materials-view']);
    $role = Role::create(['name' => 'Inventory']);
    $role->permissions()->attach($permission->id);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->roles()->attach($role->id);

    $response = $this->actingAs($user)->get('/materials');

    $response->assertOk();
    $response->assertSee('Materials');
});

it('forbids users without permission from viewing materials index', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->actingAs($user)->get('/materials');

    $response->assertForbidden();
});
