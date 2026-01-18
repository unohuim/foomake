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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->asSixDecimals = fn (string $value): string => bcadd($value, '0', 6);

    $this->makeTenant = fn (string $name): Tenant => Tenant::create(['tenant_name' => $name]);

    $this->makeCategory = fn (string $name): UomCategory => UomCategory::create(['name' => $name]);

    $this->makeUom = function (UomCategory $category, string $name, string $symbol): Uom {
        return Uom::create([
            'uom_category_id' => $category->id,
            'name' => $name,
            'symbol' => $symbol,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $baseUom, string $name): Item {
        return Item::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $baseUom->id,
        ]);
    };

    $this->makeOption = function (Tenant $tenant, Item $item, Uom $packUom, string $packQuantity): ItemPurchaseOption {
        return ItemPurchaseOption::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'supplier_id' => null,
            'supplier_sku' => null,
            'pack_quantity' => $packQuantity,
            'pack_uom_id' => $packUom->id,
        ]);
    };
});

it('receives one 10kg pack into base grams', function () {
    $tenant = ($this->makeTenant)('Tenant A');

    $mass = ($this->makeCategory)('Mass');
    $kg = ($this->makeUom)($mass, 'Kilogram', 'kg');
    $grams = ($this->makeUom)($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = ($this->makeItem)($tenant, $grams, 'Flour');
    $option = ($this->makeOption)($tenant, $item, $kg, '10.000000');

    $action = new ReceivePurchaseOptionAction();
    $move = $action->execute($option, '1');

    expect($move->type)->toBe('receipt');
    expect(($this->asSixDecimals)($move->quantity))->toBe('10000.000000');
    expect($move->uom_id)->toBe($grams->id);
    expect((float) $item->fresh()->onHandQuantity())->toBe(10000.0);
});

it('receives two 20kg packs and aggregates correctly', function () {
    $tenant = ($this->makeTenant)('Tenant A');

    $mass = ($this->makeCategory)('Mass');
    $kg = ($this->makeUom)($mass, 'Kilogram', 'kg');
    $grams = ($this->makeUom)($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = ($this->makeItem)($tenant, $grams, 'Sugar');
    $option = ($this->makeOption)($tenant, $item, $kg, '20.000000');

    $action = new ReceivePurchaseOptionAction();
    $move = $action->execute($option, '2');

    expect(($this->asSixDecimals)($move->quantity))->toBe('40000.000000');
    expect((float) $item->fresh()->onHandQuantity())->toBe(40000.0);
});

it('requires item-specific conversion for cross-category receiving', function () {
    $tenant = ($this->makeTenant)('Tenant A');

    $mass = ($this->makeCategory)('Mass');
    $count = ($this->makeCategory)('Count');

    $grams = ($this->makeUom)($mass, 'Gram', 'g');
    $patty = ($this->makeUom)($count, 'Patty', 'patty');

    $item = ($this->makeItem)($tenant, $grams, 'Burger');
    $option = ($this->makeOption)($tenant, $item, $patty, '40.000000');

    ItemUomConversion::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $patty->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '113.000000',
    ]);

    $action = new ReceivePurchaseOptionAction();
    $move = $action->execute($option, '1');

    expect(($this->asSixDecimals)($move->quantity))->toBe('4520.000000');
    expect((float) $item->fresh()->onHandQuantity())->toBe(4520.0);
});

it('throws when cross-category conversion is missing', function () {
    $tenant = ($this->makeTenant)('Tenant A');

    $mass = ($this->makeCategory)('Mass');
    $count = ($this->makeCategory)('Count');

    $grams = ($this->makeUom)($mass, 'Gram', 'g');
    $patty = ($this->makeUom)($count, 'Patty', 'patty');

    $item = ($this->makeItem)($tenant, $grams, 'Burger');
    $option = ($this->makeOption)($tenant, $item, $patty, '40.000000');

    $action = new ReceivePurchaseOptionAction();

    expect(fn () => $action->execute($option, '1'))
        ->toThrow(\DomainException::class);

    expect(StockMove::count())->toBe(0);
});

it('throws when pack_count is zero or negative', function () {
    $tenant = ($this->makeTenant)('Tenant A');

    $mass = ($this->makeCategory)('Mass');
    $kg = ($this->makeUom)($mass, 'Kilogram', 'kg');
    $grams = ($this->makeUom)($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = ($this->makeItem)($tenant, $grams, 'Flour');
    $option = ($this->makeOption)($tenant, $item, $kg, '10.000000');

    $action = new ReceivePurchaseOptionAction();

    expect(fn () => $action->execute($option, '0'))
        ->toThrow(\DomainException::class);

    expect(fn () => $action->execute($option, '-1'))
        ->toThrow(\DomainException::class);

    expect(StockMove::count())->toBe(0);
});

it('requires a direct same-category conversion (no chained conversions)', function () {
    $tenant = ($this->makeTenant)('Tenant A');

    $mass = ($this->makeCategory)('Mass');
    $kg = ($this->makeUom)($mass, 'Kilogram', 'kg');
    $grams = ($this->makeUom)($mass, 'Gram', 'g');
    $mg = ($this->makeUom)($mass, 'Milligram', 'mg');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    UomConversion::create([
        'from_uom_id' => $grams->id,
        'to_uom_id' => $mg->id,
        'multiplier' => '1000',
    ]);

    $item = ($this->makeItem)($tenant, $mg, 'Salt');
    $option = ($this->makeOption)($tenant, $item, $kg, '1.000000');

    $action = new ReceivePurchaseOptionAction();

    expect(fn () => $action->execute($option, '1'))
        ->toThrow(\DomainException::class);

    expect(StockMove::count())->toBe(0);
});

it('enforces authenticated tenant isolation', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $mass = ($this->makeCategory)('Mass');
    $kg = ($this->makeUom)($mass, 'Kilogram', 'kg');
    $grams = ($this->makeUom)($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = ($this->makeItem)($tenantA, $grams, 'Flour');
    $option = ($this->makeOption)($tenantA, $item, $kg, '10.000000');

    $user = User::factory()->create(['tenant_id' => $tenantB->id]);
    $this->actingAs($user);

    $action = new ReceivePurchaseOptionAction();

    expect(fn () => $action->execute($option, '1'))
        ->toThrow(\DomainException::class);

    expect(StockMove::count())->toBe(0);
});

it('throws when option tenant does not match item tenant', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $mass = ($this->makeCategory)('Mass');
    $kg = ($this->makeUom)($mass, 'Kilogram', 'kg');
    $grams = ($this->makeUom)($mass, 'Gram', 'g');

    UomConversion::create([
        'from_uom_id' => $kg->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '1000',
    ]);

    $item = ($this->makeItem)($tenantA, $grams, 'Flour');

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
        ->toThrow(\DomainException::class);

    expect(StockMove::count())->toBe(0);
});
