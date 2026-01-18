<?php

use App\Models\Item;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Normalize a numeric string (e.g. "7.5") into a fixed 6-decimal string
 * (e.g. "7.500000") for stable assertions.
 */
function asSixDecimals(string $value): string
{
    return number_format((float) $value, 6, '.', '');
}

/**
 * Creates a tenant-scoped user, a Mass UoM category, a grams UoM, and an Item
 * with base_uom_id = grams.
 *
 * Returns: [$tenant, $user, $grams, $item]
 */
function makeTenantItemWithGrams(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $category = UomCategory::create(['name' => 'Mass']);

    $grams = Uom::create([
        'uom_category_id' => $category->id,
        'name' => 'Gram',
        'symbol' => 'g',
    ]);

    $item = Item::create([
        'tenant_id' => $tenant->id,
        'name' => 'Flour',
        'base_uom_id' => $grams->id,
    ]);

    return [$tenant, $user, $grams, $item];
}

it('receipt increases on-hand quantity', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '10.000000',
        'type' => 'receipt',
    ]);

    expect(asSixDecimals($item->onHandQuantity()))->toBe('10.000000');
});

it('adjustment changes on-hand quantity', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '10.000000',
        'type' => 'receipt',
    ]);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '-2.500000',
        'type' => 'adjustment',
    ]);

    expect(asSixDecimals($item->onHandQuantity()))->toBe('7.500000');
});

it('on-hand quantity equals sum of stock moves', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '5.000000',
        'type' => 'receipt',
    ]);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '-1.250000',
        'type' => 'issue',
    ]);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '0.500000',
        'type' => 'adjustment',
    ]);

    expect(asSixDecimals($item->onHandQuantity()))->toBe('4.250000');
});

it('rejects stock moves when uom_id does not match item base_uom_id', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    $kg = Uom::create([
        'uom_category_id' => $grams->uom_category_id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
    ]);

    $this->expectException(\InvalidArgumentException::class);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $kg->id,
        'quantity' => '1.000000',
        'type' => 'receipt',
    ]);
});
