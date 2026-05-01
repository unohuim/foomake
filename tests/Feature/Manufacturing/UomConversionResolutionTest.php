<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->categoryCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (string $name = null): Tenant {
        $tenant = Tenant::query()->create([
            'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => null,
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->makeCategory = function (Tenant $tenant, string $name): UomCategory {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name . ' ' . $this->categoryCounter,
        ]);

        $this->categoryCounter++;

        return $category;
    };

    $this->makeUom = function (Tenant $tenant, UomCategory $category, string $symbol, string $name = null): Uom {
        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $name ?? strtoupper($symbol) . ' ' . $this->uomCounter,
            'symbol' => $symbol . $this->uomCounter,
            'display_precision' => 1,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $baseUom): Item {
        $item = Item::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $baseUom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ]);

        $this->itemCounter++;

        return $item;
    };

    $this->itemStoreUrl = '/manufacturing/uom-conversions/items';
    $this->itemUpdateUrl = fn (int $conversionId): string => '/manufacturing/uom-conversions/items/' . $conversionId;
    $this->itemDestroyUrl = fn (int $conversionId): string => '/manufacturing/uom-conversions/items/' . $conversionId;
    $this->resolveUrl = '/manufacturing/uom-conversions/resolve';
});

it('25. item-specific conversion can be created', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $grams = ($this->makeUom)($tenant, $mass, 'g');
    $each = ($this->makeUom)($tenant, $count, 'ea');
    $item = ($this->makeItem)($tenant, $grams);

    $this->postJson($this->itemStoreUrl, [
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ])->assertCreated();
});

it('26. item-specific conversion can be edited', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $grams = ($this->makeUom)($tenant, $mass, 'g');
    $each = ($this->makeUom)($tenant, $count, 'ea');
    $item = ($this->makeItem)($tenant, $grams);

    $conversion = ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ]);

    $this->patchJson(($this->itemUpdateUrl)($conversion->id), [
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '200.000000',
    ])->assertOk();
});

it('27. item-specific conversion can be deleted', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $grams = ($this->makeUom)($tenant, $mass, 'g');
    $each = ($this->makeUom)($tenant, $count, 'ea');
    $item = ($this->makeItem)($tenant, $grams);

    $conversion = ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ]);

    $this->deleteJson(($this->itemDestroyUrl)($conversion->id))
        ->assertNoContent();
});

it('28. item-specific conversion allows cross-category', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $grams = ($this->makeUom)($tenant, $mass, 'g');
    $each = ($this->makeUom)($tenant, $count, 'ea');
    $item = ($this->makeItem)($tenant, $grams);

    $conversion = ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ]);

    expect($conversion->fromUom->uom_category_id)->not->toBe($conversion->toUom->uom_category_id);
});

it('29. item-specific conversion requires item ownership', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $massA = ($this->makeCategory)($tenantA, 'Mass');
    $countA = ($this->makeCategory)($tenantA, 'Count');
    $gramsA = ($this->makeUom)($tenantA, $massA, 'g');
    $eachA = ($this->makeUom)($tenantA, $countA, 'ea');
    $itemB = ($this->makeItem)($tenantB, $gramsA);

    $this->postJson($this->itemStoreUrl, [
        'tenant_id' => $tenantA->id,
        'item_id' => $itemB->id,
        'from_uom_id' => $eachA->id,
        'to_uom_id' => $gramsA->id,
        'conversion_factor' => '180.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);
});

it('30. item-specific duplicate conversion is rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $grams = ($this->makeUom)($tenant, $mass, 'g');
    $each = ($this->makeUom)($tenant, $count, 'ea');
    $item = ($this->makeItem)($tenant, $grams);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ]);

    $this->postJson($this->itemStoreUrl, [
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['from_uom_id']);
});

it('31. item-specific conversions are tenant-isolated', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $massA = ($this->makeCategory)($tenantA, 'Mass');
    $massB = ($this->makeCategory)($tenantB, 'Mass');
    $countA = ($this->makeCategory)($tenantA, 'Count');
    $countB = ($this->makeCategory)($tenantB, 'Count');
    $gramsA = ($this->makeUom)($tenantA, $massA, 'g');
    $gramsB = ($this->makeUom)($tenantB, $massB, 'g');
    $eachA = ($this->makeUom)($tenantA, $countA, 'ea');
    $eachB = ($this->makeUom)($tenantB, $countB, 'ea');
    $itemA = ($this->makeItem)($tenantA, $gramsA);
    $itemB = ($this->makeItem)($tenantB, $gramsB);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenantA->id,
        'item_id' => $itemA->id,
        'from_uom_id' => $eachA->id,
        'to_uom_id' => $gramsA->id,
        'conversion_factor' => '180.000000',
    ]);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenantB->id,
        'item_id' => $itemB->id,
        'from_uom_id' => $eachB->id,
        'to_uom_id' => $gramsB->id,
        'conversion_factor' => '200.000000',
    ]);

    expect(ItemUomConversion::query()->where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(ItemUomConversion::query()->where('tenant_id', $tenantB->id)->count())->toBe(1);
});

it('32. resolution prefers item-specific over tenant', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $grams = ($this->makeUom)($tenant, $mass, 'g');
    $each = ($this->makeUom)($tenant, $count, 'ea');
    $item = ($this->makeItem)($tenant, $grams);

    \DB::table('uom_conversions')->insert([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'multiplier' => '150.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'conversion_factor' => '180.000000',
    ]);

    $this->postJson($this->resolveUrl, [
        'item_id' => $item->id,
        'from_uom_id' => $each->id,
        'to_uom_id' => $grams->id,
        'quantity' => '1.000000',
    ])->assertOk()
        ->assertJsonPath('data.source', 'item-specific')
        ->assertJsonPath('data.multiplier', '180.000000');
});

it('33. resolution prefers tenant over global', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    \DB::table('uom_conversions')->insert([
        [
            'tenant_id' => null,
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '1000.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'tenant_id' => $tenant->id,
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '999.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->postJson($this->resolveUrl, [
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'quantity' => '1.000000',
    ])->assertOk()
        ->assertJsonPath('data.source', 'tenant')
        ->assertJsonPath('data.multiplier', '999.00000000');
});

it('34. resolution falls back to global', function (): void {
    $tenant = ($this->makeTenant)();
    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => null,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '1000.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson($this->resolveUrl, [
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'quantity' => '1.000000',
    ])->assertOk()
        ->assertJsonPath('data.source', 'global')
        ->assertJsonPath('data.multiplier', '1000.00000000');
});
