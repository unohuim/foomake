<?php

use App\Models\Item;
use App\Models\InventoryBalance;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\UomConversion;
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

    $category = UomCategory::firstOrCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Mass',
    ]);

    $grams = Uom::firstOrCreate(
        [
            'tenant_id' => $tenant->id,
            'symbol' => 'g',
        ],
        [
            'uom_category_id' => $category->id,
            'name' => 'Gram',
        ]
    );

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

it('creates an inventory balance row for a posted stock move', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '10.000000',
        'type' => 'receipt',
    ]);

    $balance = InventoryBalance::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('uom_id', $grams->id)
        ->first();

    expect($balance)->not->toBeNull()
        ->and(asSixDecimals((string) $balance->quantity))->toBe('10.000000');
});

it('updates the existing inventory balance row for repeated posted stock moves', function () {
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
        'type' => 'issue',
    ]);

    expect(InventoryBalance::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('uom_id', $grams->id)
        ->count())->toBe(1);

    $balance = InventoryBalance::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('uom_id', $grams->id)
        ->firstOrFail();

    expect(asSixDecimals((string) $balance->quantity))->toBe('7.500000');
});

it('converts inventory balance rows into the current item base uom for on hand quantity', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    $kg = Uom::firstOrCreate(
        [
            'tenant_id' => $tenant->id,
            'symbol' => 'kg',
        ],
        [
            'uom_category_id' => $grams->uom_category_id,
            'name' => 'Kilogram',
        ]
    );

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $grams->id,
        'to_uom_id' => $kg->id,
        'multiplier' => '0.00100000',
    ]);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '1000.000000',
        'type' => 'receipt',
    ]);

    $item->forceFill(['base_uom_id' => $kg->id])->save();

    expect(asSixDecimals($item->fresh('baseUom')->onHandQuantity()))->toBe('1.000000');
});

it('fails on hand quantity when an inventory balance uom cannot convert to current base uom', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    $kg = Uom::firstOrCreate(
        [
            'tenant_id' => $tenant->id,
            'symbol' => 'kg',
        ],
        [
            'uom_category_id' => $grams->uom_category_id,
            'name' => 'Kilogram',
        ]
    );

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $grams->id,
        'quantity' => '1000.000000',
        'type' => 'receipt',
    ]);

    $item->forceFill(['base_uom_id' => $kg->id])->save();

    expect(fn () => $item->fresh('baseUom')->onHandQuantity())
        ->toThrow(\DomainException::class);
});

it('rejects stock moves when uom_id does not match item base_uom_id', function () {
    [$tenant, $user, $grams, $item] = makeTenantItemWithGrams();

    $this->actingAs($user);

    $kg = Uom::firstOrCreate(
        [
            'tenant_id' => $tenant->id,
            'symbol' => 'kg',
        ],
        [
            'uom_category_id' => $grams->uom_category_id,
            'name' => 'Kilogram',
        ]
    );

    $this->expectException(\InvalidArgumentException::class);

    StockMove::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $kg->id,
        'quantity' => '1.000000',
        'type' => 'receipt',
    ]);
});
