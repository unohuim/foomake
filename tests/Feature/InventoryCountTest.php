<?php

use App\Actions\Inventory\PostInventoryCountAction;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeUom(): Uom
{
    $category = UomCategory::create([
        'name' => 'Category-' . Str::uuid(),
    ]);

    return Uom::create([
        'uom_category_id' => $category->id,
        'name' => 'Unit-' . Str::uuid(),
        'symbol' => 'u' . Str::uuid(),
    ]);
}

function makeTenantUser(Tenant $tenant): User
{
    return User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
}

function makeItem(Tenant $tenant, Uom $uom): Item
{
    return Item::create([
        'tenant_id' => $tenant->id,
        'name' => 'Item-' . Str::uuid(),
        'base_uom_id' => $uom->id,
    ]);
}

it('posts inventory count variances and creates stock moves', function () {
    $tenant = Tenant::factory()->create();
    $user = makeTenantUser($tenant);
    $uom = makeUom();
    $item = makeItem($tenant, $uom);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '2.000000',
        'type' => 'receipt',
    ]);

    $count = InventoryCount::create([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    InventoryCountLine::create([
        'tenant_id' => $tenant->id,
        'inventory_count_id' => $count->id,
        'item_id' => $item->id,
        'counted_quantity' => '5.000000',
    ]);

    $action = new PostInventoryCountAction();
    $action->execute($count, $user->id);

    $count->refresh();

    expect($count->posted_at)->not->toBeNull();
    expect($count->posted_by_user_id)->toBe($user->id);

    $stockMove = StockMove::where('type', 'inventory_count_adjustment')->first();

    expect($stockMove)->not->toBeNull();
    expect($stockMove->quantity)->toBe('3.000000');
    expect($stockMove->source_type)->toBe(InventoryCount::class);
    expect($stockMove->source_id)->toBe($count->id);
});

it('skips zero variance lines when posting', function () {
    $tenant = Tenant::factory()->create();
    $user = makeTenantUser($tenant);
    $uom = makeUom();
    $item = makeItem($tenant, $uom);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '5.000000',
        'type' => 'receipt',
    ]);

    $count = InventoryCount::create([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    InventoryCountLine::create([
        'tenant_id' => $tenant->id,
        'inventory_count_id' => $count->id,
        'item_id' => $item->id,
        'counted_quantity' => '5.000000',
    ]);

    $action = new PostInventoryCountAction();
    $action->execute($count, $user->id);

    expect(StockMove::where('type', 'inventory_count_adjustment')->count())->toBe(0);
});

it('hard-fails when posting an inventory count twice', function () {
    $tenant = Tenant::factory()->create();
    $user = makeTenantUser($tenant);
    $uom = makeUom();
    $item = makeItem($tenant, $uom);

    $count = InventoryCount::create([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    InventoryCountLine::create([
        'tenant_id' => $tenant->id,
        'inventory_count_id' => $count->id,
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    $action = new PostInventoryCountAction();
    $action->execute($count, $user->id);

    expect(fn () => $action->execute($count, $user->id))->toThrow(DomainException::class);
});
