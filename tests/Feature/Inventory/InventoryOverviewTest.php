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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->uomCategory = UomCategory::query()->forceCreate([
        'name' => 'Mass ' . $this->tenant->id,
    ]);

    $this->uom = Uom::query()->forceCreate([
        'uom_category_id' => $this->uomCategory->id,
        'name' => 'Gram ' . $this->tenant->id,
        'symbol' => 'g' . $this->tenant->id,
    ]);

    $this->grantInventoryViewPermission = function (User $user): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'inventory-adjustments-view',
        ]);

        $role = Role::query()->forceCreate([
            'name' => 'inventory-viewer-' . $user->id,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };
});

test('guests are redirected to login for inventory overview', function () {
    $this->get(route('inventory.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user without permission is forbidden', function () {
    $this->actingAs($this->user)
        ->get(route('inventory.index'))
        ->assertForbidden();
});

test('authorized user sees inventory overview data', function () {
    ($this->grantInventoryViewPermission)($this->user);

    $item = Item::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'name' => 'Flour',
        'base_uom_id' => $this->uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    StockMove::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $this->uom->id,
        'quantity' => '10.000000',
        'type' => 'receipt',
        'created_at' => now(),
    ]);

    StockMove::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $this->uom->id,
        'quantity' => '-2.000000',
        'type' => 'issue',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee($item->name)
        ->assertSee($this->uom->name . ' (' . $this->uom->symbol . ')')
        ->assertSee($item->onHandQuantity());
});

test('inventory overview is tenant scoped', function () {
    ($this->grantInventoryViewPermission)($this->user);

    $item = Item::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'name' => 'Flour',
        'base_uom_id' => $this->uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    StockMove::query()->forceCreate([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $this->uom->id,
        'quantity' => '5.000000',
        'type' => 'receipt',
        'created_at' => now(),
    ]);

    $otherTenant = Tenant::factory()->create();

    // Use other tenant's own UoM to avoid any assumptions about global UoMs.
    $otherCategory = UomCategory::query()->forceCreate([
        'name' => 'Mass ' . $otherTenant->id,
    ]);

    $otherUom = Uom::query()->forceCreate([
        'uom_category_id' => $otherCategory->id,
        'name' => 'Gram ' . $otherTenant->id,
        'symbol' => 'g' . $otherTenant->id,
    ]);

    $otherItem = Item::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'name' => 'Sugar',
        'base_uom_id' => $otherUom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    StockMove::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'item_id' => $otherItem->id,
        'uom_id' => $otherUom->id,
        'quantity' => '3.000000',
        'type' => 'receipt',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee($item->name)
        ->assertDontSee($otherItem->name);
});

test('empty state renders when no items exist', function () {
    ($this->grantInventoryViewPermission)($this->user);

    $this->actingAs($this->user)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('No inventory items available.');
});
