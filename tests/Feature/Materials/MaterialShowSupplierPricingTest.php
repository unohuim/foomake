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
        $tenant = Tenant::factory()->create([
            'tenant_name' => $attributes['tenant_name'] ?? 'Tenant ' . $this->tenantCounter,
        ]);

        if (array_key_exists('currency_code', $attributes)) {
            $tenant->forceFill(['currency_code' => $attributes['currency_code']])->save();
        }

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user' . $this->userCounter . '@example.test',
        ]);

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
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $this->uomCounter,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Item ' . $this->itemCounter,
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
            'company_name' => $attributes['company_name'] ?? 'Supplier ' . $this->supplierCounter,
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
            'pack_quantity' => $attributes['pack_quantity'] ?? '10.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));
    };

    $this->makePrice = function (Tenant $tenant, ItemPurchaseOption $option, array $attributes = []): ItemPurchaseOptionPrice {
        return ItemPurchaseOptionPrice::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_purchase_option_id' => $option->id,
            'price_cents' => 1500,
            'price_currency_code' => $attributes['price_currency_code'] ?? 'USD',
            'converted_price_cents' => 1500,
            'fx_rate' => '1.000000',
            'fx_rate_as_of' => now()->toDateString(),
            'effective_at' => now(),
            'ended_at' => $attributes['ended_at'] ?? null,
        ], $attributes));
    };

    $this->postOption = function (User $user, Supplier $supplier, array $payload) {
        return $this->actingAs($user)
            ->postJson(route('purchasing.suppliers.purchase-options.store', $supplier), $payload);
    };

    $this->postPrice = function (User $user, ItemPurchaseOption $option, array $payload) {
        return $this->actingAs($user)
            ->postJson(route('purchasing.purchase-options.prices.store', $option), $payload);
    };

    $this->getShow = function (User $user, Item $item) {
        return $this->actingAs($user)->get(route('materials.show', $item));
    };

    $this->extractMaterialPayload = function ($response): array {
        $content = $response->getContent();
        preg_match('/<script[^>]+id="materials-show-supplier-packages-payload"[^>]*>(.*?)<\\/script>/s', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        return json_decode($matches[1], true) ?? [];
    };
});

it('redirects guests to login for the material show page', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    $this->get(route('materials.show', $item))
        ->assertRedirect(route('login'));
});

it('forbids material show without inventory permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->getShow)($user, $item)
        ->assertForbidden();
});

it('allows material show with inventory permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Flour');
});

it('returns 404 for cross-tenant material access', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getShow)($user, $item)
        ->assertNotFound();
});

it('hides supplier pricing section without purchasing permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertDontSee('data-section="supplier-packages"', false);
});

it('shows supplier pricing section with purchasing permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'tenant_id' => $tenant->id,
    ]);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('data-section="supplier-packages"', false);
});

it('shows supplier name for each package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Sugar']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Acme Supply']);

    ($this->makeOption)($tenant, $supplier, $item, $uom, ['pack_quantity' => '8.000000']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Acme Supply');
});

it('shows pack quantity and uom for supplier packages', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->makeOption)($tenant, $supplier, $item, $uom, ['pack_quantity' => '3.500000']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('3.50')
        ->assertSee('kg');
});

it('shows supplier sku for each package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'SUP-999']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('SUP-999');
});

it('shows placeholder for missing supplier sku', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => null]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('—');
});

it('shows current price for supplier packages', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->makePrice)($tenant, $option, [
        'price_cents' => 2450,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 2450,
    ]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('USD 24.50');
});

it('does not show ended prices as current', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->makePrice)($tenant, $option, [
        'price_cents' => 999,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 999,
        'ended_at' => now(),
    ]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertDontSee('USD 9.99');
});

it('does not show packages for other items', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $otherItem = ($this->makeItem)($tenant, $uom, ['name' => 'Yeast']);
    $supplier = ($this->makeSupplier)($tenant);

    $otherOption = ($this->makeOption)($tenant, $supplier, $otherItem, $uom, ['supplier_sku' => 'OTHER-1']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $otherOption->id,
        'item_id' => $otherItem->id,
    ]);

    $response = ($this->getShow)($user, $item)
        ->assertOk();

    $payload = ($this->extractMaterialPayload)($response);
    expect(collect($payload['packages'] ?? [])->pluck('id')->all())
        ->not()
        ->toContain($otherOption->id);
});

it('does not show packages from another tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Salt']);
    $otherSupplier = ($this->makeSupplier)($otherTenant, ['company_name' => 'Other Supplier']);

    $otherOption = ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom, ['supplier_sku' => 'OTHER-2']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $otherOption->id,
        'supplier_id' => $otherSupplier->id,
    ]);

    $response = ($this->getShow)($user, $item)
        ->assertOk();

    $payload = ($this->extractMaterialPayload)($response);
    expect(collect($payload['packages'] ?? [])->pluck('id')->all())
        ->not()
        ->toContain($otherOption->id);
});

it('shows multiple supplier packages for one item', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplierA = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier A']);
    $supplierB = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier B']);

    $optionA = ($this->makeOption)($tenant, $supplierA, $item, $uom, ['supplier_sku' => 'A-1']);
    $optionB = ($this->makeOption)($tenant, $supplierB, $item, $uom, ['supplier_sku' => 'B-1']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('"id":' . $optionA->id, false)
        ->assertSee('"id":' . $optionB->id, false);
});

it('shows placeholder when no current price exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('—');
});

it('hides supplier pricing section when packages exist but purchasing permission is missing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Hidden Supplier']);

    ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertDontSee('Hidden Supplier')
        ->assertDontSee('data-section="supplier-packages"', false);
});

it('denies material show without inventory permission even when purchasing permission exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertForbidden();
});

it('does not show packages without a supplier assigned', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ItemPurchaseOption::query()->create([
        'tenant_id' => $tenant->id,
        'supplier_id' => null,
        'item_id' => $item->id,
        'supplier_sku' => 'NO-SUP',
        'pack_quantity' => '2.000000',
        'pack_uom_id' => $uom->id,
    ]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertDontSee('NO-SUP');
});

it('end-to-end: creates package and price then shows on material detail', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Rice']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Supplier End2End']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $optionResponse = ($this->postOption)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '4.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'RICE-1',
    ])->assertCreated();

    $option = ItemPurchaseOption::query()->findOrFail($optionResponse->json('data.id'));

    Carbon::setTestNow('2025-02-01 08:00:00');

    ($this->postPrice)($user, $option, [
        'price_cents' => 4200,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $option->id,
        'price_cents' => 4200,
        'ended_at' => null,
    ]);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('USD 42.00')
        ->assertSee('Supplier End2End');

    Carbon::setTestNow();
});
