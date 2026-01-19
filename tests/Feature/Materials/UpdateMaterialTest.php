<?php

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->grantPermission = function (User $user, string $permissionSlug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $permissionSlug,
        ]);

        $role = Role::query()->create([
            'name' => Str::uuid()->toString(),
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (): Uom {
        $category = UomCategory::query()->create([
            'name' => Str::uuid()->toString(),
        ]);

        return Uom::query()->create([
            'uom_category_id' => $category->id,
            'name' => Str::uuid()->toString(),
            'symbol' => Str::upper(Str::random(6)),
        ]);
    };

    $this->makeItem = function (Uom $uom, array $overrides = []): Item {
        return Item::query()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Flour',
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->patchUpdate = function (User $user, Item $item, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('materials.update', $item), $payload);
    };
});

test('denies updates for users without inventory-materials-manage permission', function () {
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertForbidden();
});

test('updates a material for users with inventory-materials-manage permission', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $newUom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $newUom->id,
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Flour')
        ->assertJsonPath('data.base_uom_id', $newUom->id);

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->name)->toBe('Updated Flour')
        ->and($updated->base_uom_id)->toBe($newUom->id)
        ->and($updated->is_purchasable)->toBeTrue()
        ->and($updated->is_sellable)->toBeFalse()
        ->and($updated->is_manufacturable)->toBeFalse();
});

test('returns not found when attempting to update another tenant item', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $otherItem = Item::query()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Flour',
        'base_uom_id' => $uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    $response = ($this->patchUpdate)($this->user, $otherItem, [
        'name' => 'Blocked',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertNotFound();
});

test('returns validation errors for missing required fields', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'base_uom_id']);
});

test('returns validation error when name is missing', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'base_uom_id' => $uom->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('returns validation error when base_uom_id is missing', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);
});

test('locks base_uom_id when stock moves exist', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $newUom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    StockMove::query()->create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '1.000000',
        'type' => 'receipt',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Locked Flour',
        'base_uom_id' => $newUom->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->base_uom_id)->toBe($uom->id);
});

test('allows base_uom_id updates when no stock moves exist', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $newUom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Unlocked Flour',
        'base_uom_id' => $newUom->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.base_uom_id', $newUom->id);

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->base_uom_id)->toBe($newUom->id);
});
