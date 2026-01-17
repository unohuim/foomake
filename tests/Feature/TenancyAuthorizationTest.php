<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('returns unscoped records when unauthenticated', function () {
    $tenant = Tenant::create([
        'tenant_name' => 'FooMake',
    ]);

    User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    expect(User::count())->toBe(2);
});

it('scopes users to the authenticated tenant', function () {
    $tenantA = Tenant::create([
        'tenant_name' => 'FooMake',
    ]);

    $tenantB = Tenant::create([
        'tenant_name' => 'BarMake',
    ]);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
    ]);

    $userB = User::factory()->create([
        'tenant_id' => $tenantB->id,
    ]);

    $this->actingAs($userA);

    $visibleUserIds = User::query()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($visibleUserIds)->toBe([$userA->id]);
    expect($visibleUserIds)->not->toContain($userB->id);
});

it('allows super-admin to bypass tenant scoping', function () {
    $tenantA = Tenant::create([
        'tenant_name' => 'FooMake',
    ]);

    $tenantB = Tenant::create([
        'tenant_name' => 'BarMake',
    ]);

    $superAdminRole = Role::create([
        'name' => 'super-admin',
    ]);

    $superAdmin = User::factory()->create([
        'tenant_id' => $tenantA->id,
    ]);

    $superAdmin->roles()->attach($superAdminRole);

    $otherTenantUser = User::factory()->create([
        'tenant_id' => $tenantB->id,
    ]);

    $this->actingAs($superAdmin);

    $visibleUserIds = User::query()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($visibleUserIds)->toContain($superAdmin->id);
    expect($visibleUserIds)->toContain($otherTenantUser->id);
});

it('enforces permission gates via role permissions', function () {
    $tenant = Tenant::create([
        'tenant_name' => 'FooMake',
    ]);

    $role = Role::create([
        'name' => 'sales',
    ]);

    $permission = Permission::create([
        'slug' => 'sales-customers-view',
    ]);

    $role->permissions()->attach($permission);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $user->roles()->attach($role);

    $this->actingAs($user);

    expect(Gate::allows('sales-customers-view'))->toBeTrue();
    expect(Gate::allows('inventory-products-manage'))->toBeFalse();
});
