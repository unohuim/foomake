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

    $this->makeItem = function (Uom $uom): Item {
        return Item::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Material ' . Str::random(6),
            'base_uom_id' => $uom->id,
        ]);
    };

    $this->createStockMove = function (Item $item): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $this->tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => '1.000000',
            'type' => 'receipt',
        ]);
    };

    $this->deleteMaterial = function (User $user, Item $item) {
        return $this->actingAs($user)->deleteJson(route('materials.destroy', $item));
    };
});

test('deletes a material when no stock moves exist', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->deleteMaterial)($this->user, $item);

    $response->assertOk()
        ->assertJsonPath('message', 'Deleted.');

    expect(Item::withoutGlobalScopes()->whereKey($item->id)->exists())->toBeFalse();
});

test('blocks deletion when stock moves exist', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);
    ($this->createStockMove)($item);

    $response = ($this->deleteMaterial)($this->user, $item);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Material cannot be deleted because stock moves exist.');

    expect(Item::withoutGlobalScopes()->whereKey($item->id)->exists())->toBeTrue();
});

test('denies deletion without inventory-materials-manage permission', function () {
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->deleteMaterial)($this->user, $item);

    $response->assertForbidden();

    expect(Item::withoutGlobalScopes()->whereKey($item->id)->exists())->toBeTrue();
});
