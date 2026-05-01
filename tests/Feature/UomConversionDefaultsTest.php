<?php

declare(strict_types=1);

use App\Models\Uom;
use App\Models\UomCategory;
use App\Services\Uom\SystemUomCloner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->defaultConfig = [
        'categories' => [
            ['key' => 'mass', 'name' => 'Mass'],
            ['key' => 'volume', 'name' => 'Volume'],
            ['key' => 'count', 'name' => 'Count'],
            ['key' => 'length', 'name' => 'Length'],
        ],
        'uoms' => [
            ['category_key' => 'mass', 'name' => 'Gram', 'symbol' => 'g'],
            ['category_key' => 'mass', 'name' => 'Kilogram', 'symbol' => 'kg'],
            ['category_key' => 'mass', 'name' => 'Pound', 'symbol' => 'lb'],
            ['category_key' => 'mass', 'name' => 'Ounce', 'symbol' => 'oz'],
            ['category_key' => 'volume', 'name' => 'Milliliter', 'symbol' => 'ml'],
            ['category_key' => 'volume', 'name' => 'Liter', 'symbol' => 'l'],
            ['category_key' => 'count', 'name' => 'Each', 'symbol' => 'ea'],
            ['category_key' => 'count', 'name' => 'Piece', 'symbol' => 'pc'],
            ['category_key' => 'length', 'name' => 'Centimeter', 'symbol' => 'cm'],
            ['category_key' => 'length', 'name' => 'Meter', 'symbol' => 'm'],
        ],
        'conversions' => [
            'mass' => [
                ['from' => 'kg', 'to' => 'g', 'multiplier' => '1000.00000000'],
                ['from' => 'lb', 'to' => 'oz', 'multiplier' => '16.00000000'],
            ],
            'volume' => [
                ['from' => 'l', 'to' => 'ml', 'multiplier' => '1000.00000000'],
            ],
            'length' => [
                ['from' => 'm', 'to' => 'cm', 'multiplier' => '100.00000000'],
            ],
        ],
    ];

    config(['system_uoms' => $this->defaultConfig]);

    $this->seedDefaults = function (): void {
        app(SystemUomCloner::class)->seedSystemDefaults();
    };

    $this->systemUom = function (string $symbol): ?Uom {
        return Uom::query()
            ->withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('symbol', $symbol)
            ->first();
    };
});

it('1. seeds global mass conversions', function (): void {
    ($this->seedDefaults)();

    $kg = ($this->systemUom)('kg');
    $g = ($this->systemUom)('g');

    expect($kg)->not->toBeNull()
        ->and($g)->not->toBeNull();

    $this->assertDatabaseHas('uom_conversions', [
        'tenant_id' => null,
        'from_uom_id' => $kg?->id,
        'to_uom_id' => $g?->id,
        'multiplier' => '1000.00000000',
    ]);
});

it('2. seeds global volume conversions', function (): void {
    ($this->seedDefaults)();

    $liter = ($this->systemUom)('l');
    $milliliter = ($this->systemUom)('ml');

    expect($liter)->not->toBeNull()
        ->and($milliliter)->not->toBeNull();

    $this->assertDatabaseHas('uom_conversions', [
        'tenant_id' => null,
        'from_uom_id' => $liter?->id,
        'to_uom_id' => $milliliter?->id,
        'multiplier' => '1000.00000000',
    ]);
});

it('3. seeds global length conversions', function (): void {
    ($this->seedDefaults)();

    $meter = ($this->systemUom)('m');
    $centimeter = ($this->systemUom)('cm');

    expect($meter)->not->toBeNull()
        ->and($centimeter)->not->toBeNull();

    $this->assertDatabaseHas('uom_conversions', [
        'tenant_id' => null,
        'from_uom_id' => $meter?->id,
        'to_uom_id' => $centimeter?->id,
        'multiplier' => '100.00000000',
    ]);
});

it('4. does not seed count conversions', function (): void {
    ($this->seedDefaults)();

    $each = ($this->systemUom)('ea');
    $piece = ($this->systemUom)('pc');

    expect($each)->not->toBeNull()
        ->and($piece)->not->toBeNull();

    $this->assertDatabaseMissing('uom_conversions', [
        'from_uom_id' => $each?->id,
        'to_uom_id' => $piece?->id,
    ]);
});

it('5. does not seed cross-category global conversions', function (): void {
    ($this->seedDefaults)();

    $gram = ($this->systemUom)('g');
    $milliliter = ($this->systemUom)('ml');

    expect($gram)->not->toBeNull()
        ->and($milliliter)->not->toBeNull();

    $this->assertDatabaseMissing('uom_conversions', [
        'from_uom_id' => $gram?->id,
        'to_uom_id' => $milliliter?->id,
    ]);
});

it('6. generates reverse conversions automatically', function (): void {
    ($this->seedDefaults)();

    $kg = ($this->systemUom)('kg');
    $g = ($this->systemUom)('g');

    expect($kg)->not->toBeNull()
        ->and($g)->not->toBeNull();

    $this->assertDatabaseHas('uom_conversions', [
        'tenant_id' => null,
        'from_uom_id' => $g?->id,
        'to_uom_id' => $kg?->id,
        'multiplier' => '0.00100000',
    ]);
});

it('7. seeder is idempotent', function (): void {
    ($this->seedDefaults)();

    $firstCategoryCount = UomCategory::query()->withoutGlobalScopes()->whereNull('tenant_id')->count();
    $firstUomCount = Uom::query()->withoutGlobalScopes()->whereNull('tenant_id')->count();
    $firstConversionCount = \DB::table('uom_conversions')->whereNull('tenant_id')->count();

    ($this->seedDefaults)();

    expect(UomCategory::query()->withoutGlobalScopes()->whereNull('tenant_id')->count())->toBe($firstCategoryCount)
        ->and(Uom::query()->withoutGlobalScopes()->whereNull('tenant_id')->count())->toBe($firstUomCount)
        ->and(\DB::table('uom_conversions')->whereNull('tenant_id')->count())->toBe($firstConversionCount);
});

it('8. seeder fails loudly when required uoms are missing', function (): void {
    config([
        'system_uoms' => array_merge($this->defaultConfig, [
            'conversions' => [
                'mass' => [
                    ['from' => 'kg', 'to' => 'stone', 'multiplier' => '0.15747304'],
                ],
            ],
        ]),
    ]);

    expect(function (): void {
        ($this->seedDefaults)();
    })->toThrow(\RuntimeException::class);
});

it('9. seeder fails loudly on invalid category config', function (): void {
    config([
        'system_uoms' => [
            'categories' => [
                ['key' => 'mass'],
            ],
            'uoms' => $this->defaultConfig['uoms'],
            'conversions' => $this->defaultConfig['conversions'],
        ],
    ]);

    expect(function (): void {
        ($this->seedDefaults)();
    })->toThrow(\RuntimeException::class);
});

it('10. global conversions have tenant_id null', function (): void {
    ($this->seedDefaults)();

    expect(\DB::table('uom_conversions')->whereNull('tenant_id')->count())->toBeGreaterThan(0)
        ->and(\DB::table('uom_conversions')->whereNotNull('tenant_id')->count())->toBe(0);
});
