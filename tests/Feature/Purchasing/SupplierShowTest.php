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
    $this->optionCounter = 1;

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
            'url' => $attributes['url'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'currency_code' => $attributes['currency_code'] ?? null,
        ], $attributes));

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeOption = function (Tenant $tenant, Supplier $supplier, Item $item, Uom $uom, array $attributes = []): ItemPurchaseOption {
        $option = ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => $attributes['supplier_sku'] ?? 'SKU-' . $this->optionCounter,
            'pack_quantity' => $attributes['pack_quantity'] ?? '10.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));

        $this->optionCounter++;

        return $option;
    };

    $this->makePrice = function (Tenant $tenant, ItemPurchaseOption $option, array $attributes = []): ItemPurchaseOptionPrice {
        return ItemPurchaseOptionPrice::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_purchase_option_id' => $option->id,
            'price_cents' => 1234,
            'price_currency_code' => $attributes['price_currency_code'] ?? 'USD',
            'converted_price_cents' => 1234,
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

    $this->getShow = function (User $user, Supplier $supplier) {
        return $this->actingAs($user)->get(route('purchasing.suppliers.show', $supplier));
    };

    $this->extractSupplierPayload = function ($response): array {
        $content = $response->getContent();
        preg_match('/<script[^>]+id="purchasing-suppliers-show-payload"[^>]*>(.*?)<\\/script>/s', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        return json_decode($matches[1], true) ?? [];
    };
});

it('redirects guests to login for the supplier detail page', function () {
    $tenant = ($this->makeTenant)();
    $supplier = ($this->makeSupplier)($tenant);

    $this->get(route('purchasing.suppliers.show', $supplier))
        ->assertRedirect(route('login'));
});

it('forbids supplier show without view permission and does not mutate supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Original Supplier']);

    ($this->getShow)($user, $supplier)
        ->assertForbidden();

    $fresh = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);
    expect($fresh->company_name)->toBe('Original Supplier');
});

it('allows supplier show with view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk();
});

it('returns 404 for cross-tenant supplier access', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($otherTenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertNotFound();
});

it('renders the page module payload for the supplier detail page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-page="purchasing-suppliers-show"', false)
        ->assertSee('purchasing-suppliers-show-payload', false);
});

it('includes the supplier id in the payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"supplier":', false)
        ->assertSee('"id":' . $supplier->id, false);
});

it('includes supplier name and currency in the payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, [
        'company_name' => 'Supplier Payload',
        'currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'company_name' => 'Supplier Payload',
        'currency_code' => 'USD',
    ]);

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"company_name":"Supplier Payload"', false)
        ->assertSee('"currency_code":"USD"', false);
});

it('renders a stable packages section identifier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-section="supplier-packages"', false);
});

it('exposes manage flag in payload when user can manage', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"canManage":true', false);
});

it('exposes manage flag in payload when user cannot manage', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"canManage":false', false);
});

it('lists supplier packages with option ids in the payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['pack_quantity' => '5.000000']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
        'supplier_id' => $supplier->id,
    ]);

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"id":' . $option->id, false);
});

it('does not include packages from another supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Salt']);

    $otherOption = ($this->makeOption)($tenant, $otherSupplier, $item, $uom, ['supplier_sku' => 'OTHER-1']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $otherOption->id,
        'supplier_id' => $otherSupplier->id,
    ]);

    $response = ($this->getShow)($user, $supplier)
        ->assertOk();

    $payload = ($this->extractSupplierPayload)($response);
    expect(collect($payload['packages'] ?? [])->pluck('id')->all())
        ->not()
        ->toContain($otherOption->id);
});

it('does not include packages from another tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Pepper']);

    $otherOption = ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom, ['supplier_sku' => 'OTHER-2']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $otherOption->id,
        'supplier_id' => $otherSupplier->id,
    ]);

    $response = ($this->getShow)($user, $supplier)
        ->assertOk();

    $payload = ($this->extractSupplierPayload)($response);
    expect(collect($payload['packages'] ?? [])->pluck('id')->all())
        ->not()
        ->toContain($otherOption->id);
});

it('shows multiple packages for the supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Corn']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Oil']);

    $optionA = ($this->makeOption)($tenant, $supplier, $itemA, $uom, ['pack_quantity' => '2.000000']);
    $optionB = ($this->makeOption)($tenant, $supplier, $itemB, $uom, ['pack_quantity' => '3.000000']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"id":' . $optionA->id, false)
        ->assertSee('"id":' . $optionB->id, false);
});

it('shows current price for a package', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->makePrice)($tenant, $option, [
        'price_cents' => 1299,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 1299,
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('USD 12.99');
});

it('does not show ended prices as current', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->makePrice)($tenant, $option, [
        'price_cents' => 500,
        'price_currency_code' => 'USD',
        'converted_price_cents' => 500,
        'ended_at' => now(),
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertDontSee('USD 5.00');
});

it('shows placeholder for price when no current price exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('—');
});

it('allows duplicate packages by default', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'DUP-1',
    ];

    $first = ($this->postOption)($user, $supplier, $payload)->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.pack_quantity', '5.000000')
        ->assertJsonPath('data.pack_uom_id', $uom->id);
    $second = ($this->postOption)($user, $supplier, $payload)->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.pack_quantity', '5.000000')
        ->assertJsonPath('data.pack_uom_id', $uom->id);

    $this->assertDatabaseCount('item_purchase_options', 2);

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"id":' . $first->json('data.id'), false)
        ->assertSee('"id":' . $second->json('data.id'), false);

    expect($first->json('data.id'))->not()->toBe($second->json('data.id'));
});

it('end-to-end: creates a package and shows it on supplier detail', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Sugar']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postOption)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '7.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'SUGAR-1',
    ])->assertCreated();

    $optionId = $response->json('data.id');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $optionId,
        'supplier_id' => $supplier->id,
        'item_id' => $item->id,
    ]);

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"id":' . $optionId, false);
});

it('end-to-end: sets a price and shows it on supplier detail', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Rice']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $optionResponse = ($this->postOption)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '9.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'RICE-1',
    ])->assertCreated();

    $optionId = $optionResponse->json('data.id');
    $option = ItemPurchaseOption::query()->findOrFail($optionId);

    Carbon::setTestNow('2025-01-01 10:00:00');

    ($this->postPrice)($user, $option, [
        'price_cents' => 2500,
        'price_currency_code' => 'USD',
    ])->assertCreated();

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $option->id,
        'price_cents' => 2500,
        'ended_at' => null,
    ]);

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('USD 25.00');

    Carbon::setTestNow();
});
