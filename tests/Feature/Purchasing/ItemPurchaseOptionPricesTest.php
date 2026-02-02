<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->supplierCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::factory()->create(array_merge([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email' => 'user' . $this->userCounter . '@example.test',
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'role-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = $attributes['symbol'] ?? 'U' . $this->uomCounter;

        if (array_key_exists('symbol', $attributes)) {
            $existing = Uom::query()
                ->where('tenant_id', $tenant->id)
                ->where('symbol', $symbol)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $this->uomCounter,
        ]);

        try {
            $uom = Uom::query()->create([
                'tenant_id' => $tenant->id,
                'uom_category_id' => $category->id,
                'name' => $attributes['name'] ?? 'Uom ' . $this->uomCounter,
                'symbol' => $symbol,
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (str_contains($exception->getMessage(), 'UNIQUE') || str_contains($exception->getMessage(), 'unique')) {
                return Uom::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('symbol', $symbol)
                    ->firstOrFail();
            }

            throw $exception;
        }

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        $supplier = Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $this->supplierCounter,
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeOption = function (Tenant $tenant, Supplier $supplier, Item $item, Uom $uom, array $attributes = []): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => $attributes['supplier_sku'] ?? 'SKU-1',
            'pack_quantity' => $attributes['pack_quantity'] ?? '5.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));
    };

    $this->postPrice = function (User $user, ItemPurchaseOption $option, array $payload = []) {
        return $this->actingAs($user)
            ->postJson(route('purchasing.purchase-options.prices.store', $option), $payload);
    };

    $this->getSupplierShow = function (User $user, Supplier $supplier) {
        return $this->actingAs($user)->get(route('purchasing.suppliers.show', $supplier));
    };

    $this->assertStableErrors = function ($response): void {
        $response->assertJsonStructure([
            'errors' => [
                'price_cents',
                'price_currency_code',
                'fx_rate',
                'fx_rate_as_of',
            ],
        ]);

        expect($response->json('errors.price_cents'))->toBeArray()
            ->and($response->json('errors.price_currency_code'))->toBeArray()
            ->and($response->json('errors.fx_rate'))->toBeArray()
            ->and($response->json('errors.fx_rate_as_of'))->toBeArray();
    };
});

it('requires authentication to set a price', function () {
    $tenant = ($this->makeTenant)();
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $this->postJson(route('purchasing.purchase-options.prices.store', $option), [])
        ->assertUnauthorized();
});

it('forbids price creation without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'USD',
    ])->assertForbidden();
});

it('returns not found when pricing another tenant option', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $otherTenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($otherTenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);
    $option = ($this->makeOption)($otherTenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'USD',
    ])->assertNotFound();
});

it('validates price_cents required', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_currency_code' => 'USD',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_cents']);

    ($this->assertStableErrors)($response);
});

it('validates price_cents integer', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => '10.5',
        'price_currency_code' => 'USD',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_cents']);

    ($this->assertStableErrors)($response);
});

it('validates price_cents non-negative', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => -1,
        'price_currency_code' => 'USD',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_cents']);

    ($this->assertStableErrors)($response);
});

it('validates price_currency_code required', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_currency_code']);

    ($this->assertStableErrors)($response);
});

it('validates price_currency_code length', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'US',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_currency_code']);

    ($this->assertStableErrors)($response);
});

it('requires fx_rate when currency differs from tenant', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'EUR',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['fx_rate']);

    ($this->assertStableErrors)($response);
});

it('requires fx_rate_as_of when currency differs from tenant', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'EUR',
        'fx_rate' => '1.25',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['fx_rate_as_of']);

    ($this->assertStableErrors)($response);
});

it('validates fx_rate numeric when provided', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'EUR',
        'fx_rate' => 'not-a-number',
        'fx_rate_as_of' => '2025-01-01',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['fx_rate']);

    ($this->assertStableErrors)($response);
});

it('allows zero price_cents', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 0,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $option->id,
        'price_cents' => 0,
    ]);
});

it('does not create a price row when validation fails', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => -10,
        'price_currency_code' => 'USD',
    ])->assertStatus(422);

    expect(ItemPurchaseOptionPrice::query()->count())->toBe(0);
});

it('creates a current price row', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1500,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $priceId = $response->json('data.id');

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'id' => $priceId,
        'tenant_id' => $tenant->id,
        'item_purchase_option_id' => $option->id,
        'price_cents' => 1500,
        'price_currency_code' => 'USD',
        'ended_at' => null,
    ]);
});

it('defaults fx fields when currency matches tenant', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    Carbon::setTestNow('2025-01-01 12:00:00');

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 2000,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $price = ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->firstOrFail();

    expect($price->converted_price_cents)->toBe(2000)
        ->and($price->fx_rate)->toBe('1')
        ->and($price->fx_rate_as_of->toDateString())->toBe('2025-01-01');

    Carbon::setTestNow();
});

it('calculates converted price when currency differs', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'EUR',
        'fx_rate' => '1.25',
        'fx_rate_as_of' => '2025-01-01',
    ])->assertCreated();

    $price = ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->firstOrFail();

    expect($price->converted_price_cents)->toBe(1250)
        ->and($price->fx_rate)->toBe('1.25')
        ->and($price->fx_rate_as_of->toDateString())->toBe('2025-01-01');
});

it('rounds converted price half-up to the nearest cent', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'EUR',
        'fx_rate' => '1.0005',
        'fx_rate_as_of' => '2025-01-01',
    ])->assertCreated();

    $price = ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->firstOrFail();

    expect($price->converted_price_cents)->toBe(1001);
});

it('defaults currency to supplier currency when omitted', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['currency_code' => 'CAD']);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'fx_rate' => '0.80',
        'fx_rate_as_of' => '2025-01-01',
    ])->assertCreated();

    $price = ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->firstOrFail();

    expect($price->price_currency_code)->toBe('CAD');
    $response->assertJsonPath('data.price_currency_code', 'CAD');
});

it('defaults currency to tenant when supplier currency is null', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['currency_code' => null]);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
    ])->assertCreated();

    $price = ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->firstOrFail();

    expect($price->price_currency_code)->toBe('USD');
    $response->assertJsonPath('data.price_currency_code', 'USD');
});

it('ignores client effective_at and ended_at on create', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    Carbon::setTestNow('2025-01-02 12:00:00');

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1111,
        'price_currency_code' => 'USD',
        'effective_at' => '2020-01-01 00:00:00',
        'ended_at' => '2020-01-02 00:00:00',
    ])->assertCreated();

    $price = ItemPurchaseOptionPrice::query()->where('item_purchase_option_id', $option->id)->firstOrFail();

    expect($price->effective_at->toDateTimeString())->toBe('2025-01-02 12:00:00')
        ->and($price->ended_at)->toBeNull();

    $response->assertJsonPath('data.effective_at', '2025-01-02 12:00:00')
        ->assertJsonPath('data.ended_at', null);

    Carbon::setTestNow();
});

it('returns JSON payload for created price with expected values', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    Carbon::setTestNow('2025-01-03 08:30:00');

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postPrice)($user, $option, [
        'price_cents' => 1500,
        'price_currency_code' => 'USD',
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'item_purchase_option_id',
                'price_cents',
                'price_currency_code',
                'converted_price_cents',
                'fx_rate',
                'fx_rate_as_of',
                'effective_at',
                'ended_at',
            ],
        ])
        ->assertJsonPath('data.price_cents', 1500)
        ->assertJsonPath('data.price_currency_code', 'USD')
        ->assertJsonPath('data.converted_price_cents', 1500)
        ->assertJsonPath('data.fx_rate', '1')
        ->assertJsonPath('data.fx_rate_as_of', '2025-01-03')
        ->assertJsonPath('data.ended_at', null);

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $option->id,
        'price_cents' => 1500,
        'price_currency_code' => 'USD',
    ]);

    Carbon::setTestNow();
});

it('retires the previous current price when a new price is set', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    Carbon::setTestNow('2025-01-01 09:00:00');

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    Carbon::setTestNow('2025-01-02 10:00:00');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1200,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $current = ItemPurchaseOptionPrice::query()
        ->where('item_purchase_option_id', $option->id)
        ->whereNull('ended_at')
        ->firstOrFail();

    $ended = ItemPurchaseOptionPrice::query()
        ->where('item_purchase_option_id', $option->id)
        ->whereNotNull('ended_at')
        ->firstOrFail();

    expect($current->price_cents)->toBe(1200)
        ->and($ended->price_cents)->toBe(1000);

    Carbon::setTestNow();
});

it('uses the same timestamp for ending the previous price and creating the new one', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    Carbon::setTestNow('2025-01-01 09:00:00');

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    Carbon::setTestNow('2025-01-02 10:00:00');

    ($this->postPrice)($user, $option, [
        'price_cents' => 1200,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $current = ItemPurchaseOptionPrice::query()
        ->where('item_purchase_option_id', $option->id)
        ->whereNull('ended_at')
        ->firstOrFail();

    $ended = ItemPurchaseOptionPrice::query()
        ->where('item_purchase_option_id', $option->id)
        ->whereNotNull('ended_at')
        ->firstOrFail();

    expect($ended->ended_at->toDateTimeString())->toBe($current->effective_at->toDateTimeString());

    Carbon::setTestNow();
});

it('keeps history after multiple updates and only one current row', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 900,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    ($this->postPrice)($user, $option, [
        'price_cents' => 1000,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    ($this->postPrice)($user, $option, [
        'price_cents' => 1100,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $currentCount = ItemPurchaseOptionPrice::query()
        ->where('item_purchase_option_id', $option->id)
        ->whereNull('ended_at')
        ->count();

    $historyCount = ItemPurchaseOptionPrice::query()
        ->where('item_purchase_option_id', $option->id)
        ->count();

    expect($currentCount)->toBe(1)
        ->and($historyCount)->toBe(3);
});

it('end-to-end: setting price shows on supplier detail', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Rice']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postPrice)($user, $option, [
        'price_cents' => 3300,
        'price_currency_code' => 'USD',
    ])->assertCreated()
        ->assertJsonPath('data.price_cents', 3300)
        ->assertJsonPath('data.price_currency_code', 'USD')
        ->assertJsonPath('data.converted_price_cents', 3300);

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $option->id,
        'price_cents' => 3300,
        'ended_at' => null,
    ]);

    ($this->getSupplierShow)($user, $supplier)
        ->assertOk()
        ->assertSee('USD 33.00');
});
