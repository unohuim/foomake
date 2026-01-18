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

function onHandFor(Tenant $tenant, Item $item): string
{
    $sum = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->sum('quantity');

    return number_format((float) $sum, 6, '.', '');
}

function adjustmentMoveFor(Tenant $tenant, InventoryCount $count, Item $item): ?StockMove
{
    return StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('type', 'inventory_count_adjustment')
        ->where('source_type', InventoryCount::class)
        ->where('source_id', $count->id)
        ->where('item_id', $item->id)
        ->first();
}

it('posts inventory count variances and creates an adjustment stock move', function () {
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

    expect(onHandFor($tenant, $item))->toBe('2.000000');

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

    $stockMove = adjustmentMoveFor($tenant, $count, $item);

    expect($stockMove)->not->toBeNull();
    expect($stockMove->tenant_id)->toBe($tenant->id);
    expect($stockMove->uom_id)->toBe($item->base_uom_id);
    expect($stockMove->quantity)->toBe('3.000000');

    // Ledger-derived on-hand after posting equals counted quantity.
    expect(onHandFor($tenant, $item))->toBe('5.000000');
});

it('creates a negative variance adjustment move when counted is less than on-hand', function () {
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

    expect(onHandFor($tenant, $item))->toBe('5.000000');

    $count = InventoryCount::create([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    InventoryCountLine::create([
        'tenant_id' => $tenant->id,
        'inventory_count_id' => $count->id,
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
    ]);

    $action = new PostInventoryCountAction();
    $action->execute($count, $user->id);

    $count->refresh();

    expect($count->posted_at)->not->toBeNull();
    expect($count->posted_by_user_id)->toBe($user->id);

    $stockMove = adjustmentMoveFor($tenant, $count, $item);

    expect($stockMove)->not->toBeNull();
    expect($stockMove->tenant_id)->toBe($tenant->id);
    expect($stockMove->uom_id)->toBe($item->base_uom_id);
    expect($stockMove->quantity)->toBe('-3.000000');

    expect(onHandFor($tenant, $item))->toBe('2.000000');
});

it('skips zero variance lines when posting and still marks the count as posted', function () {
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

    expect(
        StockMove::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'inventory_count_adjustment')
            ->count()
    )->toBe(0);

    $count->refresh();
    expect($count->posted_at)->not->toBeNull();
    expect($count->posted_by_user_id)->toBe($user->id);

    expect(onHandFor($tenant, $item))->toBe('5.000000');
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

    $count->refresh();
    expect($count->posted_at)->not->toBeNull();

    expect(fn () => $action->execute($count, $user->id))->toThrow(\DomainException::class);

});

it('hard-fails when a count line item belongs to a different tenant and does not post', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = makeTenantUser($tenantA);

    $uom = makeUom();
    $itemA = makeItem($tenantA, $uom);
    $itemB = makeItem($tenantB, $uom);

    $countA = InventoryCount::create([
        'tenant_id' => $tenantA->id,
        'counted_at' => now(),
    ]);

    // Mismatch case #2: line.tenant_id matches count, but item belongs to another tenant.
    InventoryCountLine::create([
        'tenant_id' => $tenantA->id,
        'inventory_count_id' => $countA->id,
        'item_id' => $itemB->id,
        'counted_quantity' => '1.000000',
    ]);

    $action = new PostInventoryCountAction();

    expect(fn () => $action->execute($countA, $userA->id))->toThrow(DomainException::class);

    $countA->refresh();
    expect($countA->posted_at)->toBeNull();

    expect(
        StockMove::query()
            ->where('tenant_id', $tenantA->id)
            ->where('type', 'inventory_count_adjustment')
            ->count()
    )->toBe(0);

    // Ensure we didn't accidentally create moves in tenant B either.
    expect(
        StockMove::query()
            ->where('tenant_id', $tenantB->id)
            ->where('type', 'inventory_count_adjustment')
            ->count()
    )->toBe(0);
});
