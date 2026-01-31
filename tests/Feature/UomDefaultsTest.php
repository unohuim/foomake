<?php

use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Services\Uom\SystemUomCloner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

beforeEach(function () {
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
            ['category_key' => 'volume', 'name' => 'Milliliter', 'symbol' => 'ml'],
            ['category_key' => 'volume', 'name' => 'Liter', 'symbol' => 'l'],
            ['category_key' => 'count', 'name' => 'Each', 'symbol' => 'ea'],
            ['category_key' => 'count', 'name' => 'Piece', 'symbol' => 'pc'],
            ['category_key' => 'length', 'name' => 'Centimeter', 'symbol' => 'cm'],
            ['category_key' => 'length', 'name' => 'Meter', 'symbol' => 'm'],
        ],
    ];

    config(['system_uoms' => $this->defaultConfig]);

    $this->seedSystemDefaults = function (): void {
        app(SystemUomCloner::class)->seedSystemDefaults();
    };

    $this->cloneForTenant = function (Tenant $tenant): void {
        app(SystemUomCloner::class)->cloneForTenant($tenant);
    };
});

it('seeds system categories with tenant_id null', function () {
    ($this->seedSystemDefaults)();

    $names = UomCategory::query()->whereNull('tenant_id')->pluck('name')->all();

    expect($names)->toContain('Mass', 'Volume', 'Count', 'Length');
});

it('seeds system uoms with tenant_id null', function () {
    ($this->seedSystemDefaults)();

    $symbols = Uom::query()->whereNull('tenant_id')->pluck('symbol')->all();

    expect($symbols)->toContain('g', 'kg', 'ml', 'l', 'ea', 'pc', 'cm', 'm');
});

it('system seeding is idempotent', function () {
    ($this->seedSystemDefaults)();

    $categoryCount = UomCategory::query()->whereNull('tenant_id')->count();
    $uomCount = Uom::query()->whereNull('tenant_id')->count();

    ($this->seedSystemDefaults)();

    expect(UomCategory::query()->whereNull('tenant_id')->count())->toBe($categoryCount)
        ->and(Uom::query()->whereNull('tenant_id')->count())->toBe($uomCount);
});

it('db seed inserts system defaults with tenant_id null', function () {
    $this->seed();

    expect(UomCategory::query()->whereNull('tenant_id')->count())->toBeGreaterThan(0)
        ->and(Uom::query()->whereNull('tenant_id')->count())->toBeGreaterThan(0);
});

it('system defaults are sourced from config overrides', function () {
    config([
        'system_uoms' => [
            'categories' => [
                ['key' => 'custom', 'name' => 'Custom'],
            ],
            'uoms' => [
                ['category_key' => 'custom', 'name' => 'Custom Unit', 'symbol' => 'cu'],
            ],
        ],
    ]);

    ($this->seedSystemDefaults)();

    expect(UomCategory::query()->whereNull('tenant_id')->pluck('name')->all())
        ->toContain('Custom')
        ->and(Uom::query()->whereNull('tenant_id')->pluck('symbol')->all())
        ->toContain('cu');
});

it('empty config results in no system inserts', function () {
    config(['system_uoms' => ['categories' => [], 'uoms' => []]]);

    ($this->seedSystemDefaults)();

    expect(UomCategory::query()->whereNull('tenant_id')->count())->toBe(0)
        ->and(Uom::query()->whereNull('tenant_id')->count())->toBe(0);
});

it('malformed config without categories throws', function () {
    config(['system_uoms' => ['uoms' => []]]);

    expect(function () {
        ($this->seedSystemDefaults)();
    })->toThrow(RuntimeException::class);
});

it('malformed config without uoms throws', function () {
    config(['system_uoms' => ['categories' => []]]);

    expect(function () {
        ($this->seedSystemDefaults)();
    })->toThrow(RuntimeException::class);
});

it('malformed category entry throws', function () {
    config([
        'system_uoms' => [
            'categories' => [
                ['name' => 'Mass'],
            ],
            'uoms' => [],
        ],
    ]);

    expect(function () {
        ($this->seedSystemDefaults)();
    })->toThrow(RuntimeException::class);
});

it('malformed uom entry throws', function () {
    config([
        'system_uoms' => [
            'categories' => [
                ['key' => 'mass', 'name' => 'Mass'],
            ],
            'uoms' => [
                ['category_key' => 'mass', 'name' => 'Gram'],
            ],
        ],
    ]);

    expect(function () {
        ($this->seedSystemDefaults)();
    })->toThrow(RuntimeException::class);
});

it('tenant creation via Tenant::create auto-clones defaults', function () {
    $tenant = Tenant::create(['tenant_name' => 'Tenant A']);

    $categoryNames = UomCategory::query()->where('tenant_id', $tenant->id)->pluck('name')->all();
    $symbols = Uom::query()->where('tenant_id', $tenant->id)->pluck('symbol')->all();

    expect($categoryNames)->toContain('Mass', 'Volume', 'Count', 'Length')
        ->and($symbols)->toContain('g', 'kg', 'ml', 'l', 'ea', 'pc', 'cm', 'm');
});

it('tenant creation via factory auto-clones defaults', function () {
    $tenant = Tenant::factory()->create();

    expect(UomCategory::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(0)
        ->and(Uom::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(0);
});

it('tenant creation via registration auto-clones defaults', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'register@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/dashboard');

    $user = User::where('email', 'register@example.com')->firstOrFail();

    expect(UomCategory::query()->where('tenant_id', $user->tenant_id)->count())->toBeGreaterThan(0)
        ->and(Uom::query()->where('tenant_id', $user->tenant_id)->count())->toBeGreaterThan(0);
});

it('tenant clone is idempotent when cloner runs after auto-clone', function () {
    $tenant = Tenant::factory()->create();

    $categoryCount = UomCategory::query()->where('tenant_id', $tenant->id)->count();
    $uomCount = Uom::query()->where('tenant_id', $tenant->id)->count();

    ($this->cloneForTenant)($tenant);

    expect(UomCategory::query()->where('tenant_id', $tenant->id)->count())->toBe($categoryCount)
        ->and(Uom::query()->where('tenant_id', $tenant->id)->count())->toBe($uomCount);
});

it('partial tenant categories are completed without duplication', function () {
    $tenant = Tenant::factory()->create();

    UomCategory::query()->where('tenant_id', $tenant->id)->where('name', 'Length')->delete();

    $before = UomCategory::query()->where('tenant_id', $tenant->id)->count();

    ($this->cloneForTenant)($tenant);

    $after = UomCategory::query()->where('tenant_id', $tenant->id)->count();

    expect($after)->toBeGreaterThan($before)
        ->and(UomCategory::query()->where('tenant_id', $tenant->id)->where('name', 'Length')->exists())
        ->toBeTrue();
});

it('partial tenant uoms are completed without duplication', function () {
    $tenant = Tenant::factory()->create();

    Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'kg')->delete();

    $before = Uom::query()->where('tenant_id', $tenant->id)->count();

    ($this->cloneForTenant)($tenant);

    $after = Uom::query()->where('tenant_id', $tenant->id)->count();

    expect($after)->toBeGreaterThan($before)
        ->and(Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'kg')->exists())
        ->toBeTrue();
});

it('tenant cloned categories have tenant_id set', function () {
    $tenant = Tenant::factory()->create();

    expect(UomCategory::query()->where('tenant_id', $tenant->id)->whereNull('tenant_id')->count())->toBe(0)
        ->and(UomCategory::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(0);
});

it('tenant cloned uoms have tenant_id set', function () {
    $tenant = Tenant::factory()->create();

    expect(Uom::query()->where('tenant_id', $tenant->id)->whereNull('tenant_id')->count())->toBe(0)
        ->and(Uom::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(0);
});

it('tenant uoms reference categories belonging to the same tenant', function () {
    $tenant = Tenant::factory()->create();

    $categoryIds = UomCategory::query()->where('tenant_id', $tenant->id)->pluck('id')->all();

    $mismatches = Uom::query()
        ->where('tenant_id', $tenant->id)
        ->whereNotIn('uom_category_id', $categoryIds)
        ->count();

    expect($mismatches)->toBe(0);
});

it('multi-tenant copies do not share category ids', function () {
    $tenantA = Tenant::factory()->create(['tenant_name' => 'Tenant A']);
    $tenantB = Tenant::factory()->create(['tenant_name' => 'Tenant B']);

    $categoryA = UomCategory::query()->where('tenant_id', $tenantA->id)->where('name', 'Mass')->first();
    $categoryB = UomCategory::query()->where('tenant_id', $tenantB->id)->where('name', 'Mass')->first();

    expect($categoryA)->not()->toBeNull()
        ->and($categoryB)->not()->toBeNull()
        ->and($categoryA->id)->not()->toBe($categoryB->id);
});

it('multi-tenant copies do not share uom ids', function () {
    $tenantA = Tenant::factory()->create(['tenant_name' => 'Tenant A']);
    $tenantB = Tenant::factory()->create(['tenant_name' => 'Tenant B']);

    $uomA = Uom::query()->where('tenant_id', $tenantA->id)->where('symbol', 'g')->first();
    $uomB = Uom::query()->where('tenant_id', $tenantB->id)->where('symbol', 'g')->first();

    expect($uomA)->not()->toBeNull()
        ->and($uomB)->not()->toBeNull()
        ->and($uomA->id)->not()->toBe($uomB->id);
});

it('creating a new tenant does not alter another tenant\'s custom categories', function () {
    $tenantA = Tenant::factory()->create(['tenant_name' => 'Tenant A']);

    UomCategory::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Custom Category',
    ]);

    $before = UomCategory::query()->where('tenant_id', $tenantA->id)->count();

    Tenant::factory()->create(['tenant_name' => 'Tenant B']);

    $after = UomCategory::query()->where('tenant_id', $tenantA->id)->count();

    expect($after)->toBe($before);
});

it('symbol uniqueness is enforced per tenant', function () {
    $tenant = Tenant::factory()->create();

    $categoryId = UomCategory::query()->where('tenant_id', $tenant->id)->value('id');

    Uom::query()->create([
        'tenant_id' => $tenant->id,
        'uom_category_id' => $categoryId,
        'name' => 'Custom Symbol',
        'symbol' => 'dup',
    ]);

    expect(function () use ($tenant, $categoryId) {
        Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $categoryId,
            'name' => 'Custom Symbol Two',
            'symbol' => 'dup',
        ]);
    })->toThrow(QueryException::class);
});

it('same symbol is allowed across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $categoryA = UomCategory::query()->where('tenant_id', $tenantA->id)->value('id');
    $categoryB = UomCategory::query()->where('tenant_id', $tenantB->id)->value('id');

    Uom::query()->create([
        'tenant_id' => $tenantA->id,
        'uom_category_id' => $categoryA,
        'name' => 'Custom',
        'symbol' => 'shared',
    ]);

    Uom::query()->create([
        'tenant_id' => $tenantB->id,
        'uom_category_id' => $categoryB,
        'name' => 'Custom',
        'symbol' => 'shared',
    ]);

    expect(Uom::query()->where('tenant_id', $tenantA->id)->where('symbol', 'shared')->exists())->toBeTrue()
        ->and(Uom::query()->where('tenant_id', $tenantB->id)->where('symbol', 'shared')->exists())->toBeTrue();
});

it('empty config results in no tenant clones', function () {
    config(['system_uoms' => ['categories' => [], 'uoms' => []]]);

    $tenant = Tenant::factory()->create();

    expect(UomCategory::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Uom::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('tenant clones reference the expected symbols per category', function () {
    $tenant = Tenant::factory()->create();

    $symbols = Uom::query()->where('tenant_id', $tenant->id)->pluck('symbol')->all();

    expect($symbols)->toContain('g', 'kg', 'ml', 'l', 'ea', 'pc', 'cm', 'm');
});
