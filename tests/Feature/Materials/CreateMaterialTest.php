<?php

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->grantPermission = function (User $user, string $permissionSlug): void {
        $permission = Permission::query()->create([
            'slug' => $permissionSlug,
        ]);
        $role = Role::query()->create([
            'name' => Str::uuid()->toString(),
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
    };

    $this->makeUom = function (): Uom {
        $category = UomCategory::query()->create([
            'name' => Str::random(12),
        ]);

        return Uom::query()->create([
            'uom_category_id' => $category->id,
            'name' => Str::random(8),
            'symbol' => Str::random(4),
        ]);
    };
});

test('user with permission can create a material', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = $this->actingAs($this->user)->postJson(route('materials.store'), [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
        'is_purchasable' => 'on',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Flour')
        ->assertJsonPath('data.base_uom_id', $uom->id);

    $item = Item::withoutGlobalScopes()->where('name', 'Flour')->firstOrFail();

    expect($item->tenant_id)->toBe($this->tenant->id)
        ->and($item->is_purchasable)->toBeTrue();
});

test('user without permission is denied', function () {
    $uom = ($this->makeUom)();

    $response = $this->actingAs($this->user)->postJson(route('materials.store'), [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertForbidden();
});

test('creation fails when no uoms exist', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $response = $this->actingAs($this->user)->postJson(route('materials.store'), [
        'name' => 'Flour',
        'base_uom_id' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);

    expect(Item::withoutGlobalScopes()->count())->toBe(0);
});

test('validation errors are returned for invalid input', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    ($this->makeUom)();

    $response = $this->actingAs($this->user)->postJson(route('materials.store'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'base_uom_id']);
});

test('material is created under the authenticated tenant', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $otherTenant = Tenant::factory()->create();

    $response = $this->actingAs($this->user)->postJson(route('materials.store'), [
        'name' => 'Sugar',
        'base_uom_id' => $uom->id,
        'tenant_id' => $otherTenant->id,
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Sugar')->firstOrFail();

    expect($item->tenant_id)->toBe($this->tenant->id)
        ->and(Item::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->count())->toBe(0);
});
