<?php

use App\Actions\Inventory\ReceivePurchaseOptionAction;
use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemUomConversion;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\UomConversion;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function asSixDecimals(string $value): string
{
    return bcadd($value, '0', 6);
}

function makeTenant(string $name): Tenant
{
    return Tenant::create(['tenant_name' => $name]);
}

function makeCategory(string $name): UomCategory
{
    return UomCategory::create(['name' => $name]);
}

function makeUom(UomCategory $category, string $name, string $symbol): Uom
{
    return Uom::create([
        'uom_category_id' => $category->id,
        'name' => $name,
        'symbol' => $symbol,
    ]);
}

function makeItem(Tenant $tenant, Uom $baseUom, string $name): Item
{
    return Item::create([
        'tenant_id' => $tenant->id,
        'name' => $name,
        'base_uom_id' => $baseUom->id,
    ]);
}

function makeOption(Tenant $tenant, Item $item, Uom $packUom, string $packQuantity): ItemPurchaseOption
{
    return ItemPurchaseOption::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'supplier_id' => null,
        'supplier_sku' => null,
        'pack_quantity' => $packQuantity,
        'pack_uom_id' => $packUom->id,
    ]);
}

it('receives one 10kg pack into base grams', function () {
    $tenant = makeTenant('Tenant A');
    $mass = makeCategory('Mass');
    $kg = makeUom($mass, 'Kilogram', 'kg');
    $grams = makeUom($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = makeItem($tenant, $grams, 'Flour');
    $option = makeOption($tenant, $item, $kg, '10.000000');

    $action = new ReceivePurchaseOptionAction();
    $move = $action->execute($option, '1');

    expect($move->type)->toBe('receipt');
    expect(asSixDecimals($move->quantity))->toBe('10000.000000');
    expect($move->uom_id)->toBe($grams->id);
    expect($item->fresh()->onHandQuantity())->toBe('10000.000000');
});

it('receives two 20kg packs and aggregates correctly', function () {
    $tenant = makeTenant('Tenant A');
    $mass = makeCategory('Mass');
    $kg = makeUom($mass, 'Kilogram', 'kg');
    $grams = makeUom($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = makeItem($tenant, $grams, 'Sugar');
    $option = makeOption($tenant, $item, $kg, '20.000000');

    $action = new ReceivePurchaseOptionAction();
    $move = $action->execute($option, '2');

    expect(asSixDecimals($move->quantity))->toBe('40000.000000');
    expect($item->fresh()->onHandQuantity())->toBe('40000.000000');
});

it('requires item-specific conversion for cross-category receiving', function () {
    $tenant = makeTenant('Tenant A');
    $mass = makeCategory('Mass');
    $count = makeCategory('Count');

    $grams = makeUom($mass, 'Gram', 'g');
    $patty = makeUom($count, 'Patty', 'patty');

    $item = makeItem($tenant, $grams, 'Burger');
    $option = makeOption($tenant, $item, $patty, '40.000000');

    ItemUomConversion::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $patty->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '113.000000',
    ]);

    $action = new ReceivePurchaseOptionAction();
    $move = $action->execute($option, '1');

    expect(asSixDecimals($move->quantity))->toBe('4520.000000');
    expect($item->fresh()->onHandQuantity())->toBe('4520.000000');
});

it('throws when cross-category conversion is missing', function () {
    $tenant = makeTenant('Tenant A');
    $mass = makeCategory('Mass');
    $count = makeCategory('Count');

    $grams = makeUom($mass, 'Gram', 'g');
    $patty = makeUom($count, 'Patty', 'patty');

    $item = makeItem($tenant, $grams, 'Burger');
    $option = makeOption($tenant, $item, $patty, '40.000000');

    $action = new ReceivePurchaseOptionAction();

    expect(fn () => $action->execute($option, '1'))
        ->toThrow(DomainException::class);

    expect(StockMove::count())->toBe(0);
});

it('enforces authenticated tenant isolation', function () {
    $tenantA = makeTenant('Tenant A');
    $tenantB = makeTenant('Tenant B');
    $mass = makeCategory('Mass');
    $kg = makeUom($mass, 'Kilogram', 'kg');
    $grams = makeUom($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = makeItem($tenantA, $grams, 'Flour');
    $option = makeOption($tenantA, $item, $kg, '10.000000');

    $user = User::factory()->create(['tenant_id' => $tenantB->id]);

    $action = new ReceivePurchaseOptionAction();

    $this->actingAs($user);

    expect(fn () => $action->execute($option, '1'))
        ->toThrow(DomainException::class);

    expect(StockMove::count())->toBe(0);
});

it('throws when option tenant does not match item tenant', function () {
    $tenantA = makeTenant('Tenant A');
    $tenantB = makeTenant('Tenant B');
    $mass = makeCategory('Mass');
    $kg = makeUom($mass, 'Kilogram', 'kg');
    $grams = makeUom($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = makeItem($tenantA, $grams, 'Flour');

    $option = ItemPurchaseOption::create([
        'tenant_id' => $tenantB->id,
        'item_id' => $item->id,
        'supplier_id' => null,
        'supplier_sku' => null,
        'pack_quantity' => '10.000000',
        'pack_uom_id' => $kg->id,
    ]);

    $action = new ReceivePurchaseOptionAction();

    expect(fn () => $action->execute($option, '1'))
        ->toThrow(DomainException::class);

    expect(StockMove::count())->toBe(0);
});
