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

    $this->uomCategory = UomCategory::create([
        'name' => 'Mass '.$this->tenant->id,
    ]);

    $this->uom = Uom::create([
        'uom_category_id' => $this->uomCategory->id,
        'name' => 'Gram '.$this->tenant->id,
        'symbol' => 'g'.$this->tenant->id,
    ]);

    $this->grantInventoryViewPermission = function (User $user): void {
        $permission = Permission::create([
            'slug' => 'inventory-adjustments-view',
        ]);

        $role = Role::create([
            'name' => 'inventory-viewer-'.$user->id,
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
    };
});

test('guests are redirected to login for inventory overview', function () {
    $response = $this->get(route('inventory.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated user without permission is forbidden', function () {
    $response = $this->actingAs($this->user)->get(route('inventory.index'));

    $response->assertForbidden();
});

test('authorized user sees inventory overview data', function () {
    ($this->grantInventoryViewPermission)($this->user);

    $item = Item::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Flour',
        'base_uom_id' => $this->uom->id,
    ]);

    StockMove::create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $this->uom->id,
        'quantity' => '10.000000',
        'type' => 'receipt',
        'created_at' => now(),
    ]);

    StockMove::create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $this->uom->id,
        'quantity' => '-2.000000',
        'type' => 'issue',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('inventory.index'));

    $response->assertOk();
    $response->assertSee($item->name);
    $response->assertSee($this->uom->name.' ('.$this->uom->symbol.')');
    $response->assertSee('8.000000');
});

test('inventory overview is tenant scoped', function () {
    ($this->grantInventoryViewPermission)($this->user);

    $item = Item::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Flour',
        'base_uom_id' => $this->uom->id,
    ]);

    StockMove::create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $this->uom->id,
        'quantity' => '5.000000',
        'type' => 'receipt',
        'created_at' => now(),
    ]);

    $otherTenant = Tenant::factory()->create();

    $otherItem = Item::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Sugar',
        'base_uom_id' => $this->uom->id,
    ]);

    StockMove::create([
        'tenant_id' => $otherTenant->id,
        'item_id' => $otherItem->id,
        'uom_id' => $this->uom->id,
        'quantity' => '3.000000',
        'type' => 'receipt',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('inventory.index'));

    $response->assertOk();
    $response->assertSee($item->name);
    $response->assertDontSee($otherItem->name);
});

test('empty state renders when no items exist', function () {
    ($this->grantInventoryViewPermission)($this->user);

    $response = $this->actingAs($this->user)->get(route('inventory.index'));

    $response->assertOk();
    $response->assertSee('No inventory items available.');
});
