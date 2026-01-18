<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

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

it('does not break authentication', function () {
    $tenantA = Tenant::create([
        'tenant_name' => 'FooMake',
    ]);

    $tenantB = Tenant::create([
        'tenant_name' => 'BarMake',
    ]);

    $password = 'password';

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'password' => Hash::make($password),
    ]);

    User::factory()->create([
        'tenant_id' => $tenantB->id,
        'password' => Hash::make($password),
    ]);

    $ok = Auth::attempt([
        'email' => $userA->email,
        'password' => $password,
    ]);

    expect($ok)->toBeTrue();
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($userA->id);

    // User is intentionally NOT tenant-scoped globally (auth identity resolution safety).
    // Therefore, do not assert tenant scoping via User::query() here.
});

it('does not globally scope users, even when authenticated', function () {
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

    expect($visibleUserIds)->toBe([$userA->id, $userB->id]);
});

it('allows super-admin to see all users', function () {
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

    // If User is not globally tenant-scoped, super-admin is not a special case here.
    // Keep the assertion that both are visible.
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
