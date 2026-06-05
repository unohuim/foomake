<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\ItemUomConversion;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\UomConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->supplierCounter = 1;
    $this->optionCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::factory()->create(array_merge([
            'tenant_name' => $attributes['tenant_name'] ?? 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email' => 'materials-show-' . $this->userCounter . '@example.test',
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'materials-show-role-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $suffix = $attributes['symbol'] ?? ('msp-' . $this->uomCounter . '-' . Str::lower(Str::random(5)));
        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $suffix)
            ->first();

        if ($existing) {
            return $existing;
        }

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Category ' . $suffix,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Uom ' . $suffix,
            'symbol' => $suffix,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Material ' . $this->itemCounter,
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

    $this->makeOption = function (
        Tenant $tenant,
        Supplier $supplier,
        Item $item,
        Uom $uom,
        array $attributes = []
    ): ItemPurchaseOption {
        $option = ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => $attributes['supplier_sku'] ?? 'MSP-SKU-' . $this->optionCounter,
            'pack_quantity' => $attributes['pack_quantity'] ?? '10.000000',
            'pack_uom_id' => $uom->id,
            'is_active' => $attributes['is_active'] ?? true,
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
            'converted_price_cents' => $attributes['converted_price_cents'] ?? 1234,
            'fx_rate' => '1.000000',
            'fx_rate_as_of' => now()->toDateString(),
            'effective_at' => now(),
            'ended_at' => $attributes['ended_at'] ?? null,
        ], $attributes));
    };

    $this->makePurchaseOrder = function (
        Tenant $tenant,
        User $user,
        Supplier $supplier,
        array $attributes = []
    ): PurchaseOrder {
        return PurchaseOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => Carbon::parse('2026-05-15')->toDateString(),
            'po_subtotal_cents' => 1000,
            'po_grand_total_cents' => 1000,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ], $attributes));
    };

    $this->makePurchaseOrderLine = function (
        Tenant $tenant,
        PurchaseOrder $purchaseOrder,
        Item $item,
        ItemPurchaseOption $option,
        array $attributes = []
    ): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => 2,
            'unit_price_cents' => 500,
            'line_subtotal_cents' => 1000,
            'unit_price_amount' => 500,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 500,
            'fx_rate' => '1.000000',
            'fx_rate_as_of' => Carbon::parse('2026-05-15')->toDateString(),
        ], $attributes));
    };

    $this->makeReceipt = function (
        Tenant $tenant,
        PurchaseOrder $purchaseOrder,
        User $user,
        array $attributes = []
    ): PurchaseOrderReceipt {
        return PurchaseOrderReceipt::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $purchaseOrder->id,
            'received_at' => Carbon::parse('2026-05-15 12:00:00'),
            'received_by_user_id' => $user->id,
        ], $attributes));
    };

    $this->makeReceiptLine = function (
        Tenant $tenant,
        PurchaseOrderReceipt $receipt,
        PurchaseOrderLine $line,
        array $attributes = []
    ): PurchaseOrderReceiptLine {
        return PurchaseOrderReceiptLine::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'purchase_order_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $line->id,
            'received_quantity' => '1.000000',
        ], $attributes));
    };

    $this->getShow = function (?User $user, Item $item) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('materials.show', $item));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        if (! array_key_exists(1, $matches)) {
            return [];
        }

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractSectionConfig = function ($response, string $sectionKey = 'supplierPackages'): array {
        $payload = ($this->extractPayload)($response, 'materials-show-payload');

        $section = $payload['sections'][$sectionKey] ?? null;

        return is_array($section) ? $section : [];
    };

    $this->getPackages = function (?User $user, Item $item, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('materials.supplier-packages.index', array_merge([
            'item' => $item,
        ], $query)));
    };

    $this->getMaterialPurchaseOrders = function (?User $user, Item $item, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('materials.purchase-orders.index', array_merge([
            'item' => $item,
        ], $query)));
    };

    $this->postMaterialPurchaseOrder = function (?User $user, Item $item, array $payload) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->postJson(route('materials.purchase-orders.store', $item), $payload);
    };

    $this->postPackage = function (?User $user, Item $item, array $payload) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->postJson(route('materials.supplier-packages.store', $item), $payload);
    };

    $this->patchPackage = function (?User $user, Item $item, ItemPurchaseOption $option, array $payload) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->patchJson(route('materials.supplier-packages.update', [$item, $option]), $payload);
    };

    $this->deletePackage = function (?User $user, Item $item, ItemPurchaseOption $option) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->deleteJson(route('materials.supplier-packages.destroy', [$item, $option]));
    };

    $this->getPurchaseOrderShow = function (?User $user, PurchaseOrder $purchaseOrder) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('purchasing.orders.show', $purchaseOrder));
    };

    $this->extractPurchaseOrderPayload = function ($response): array {
        return ($this->extractPayload)($response, 'purchasing-orders-show-payload');
    };

    $this->getPurchaseOrdersIndex = function (?User $user, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('purchasing.orders.index', $query));
    };

    $this->extractPurchaseOrdersIndexPayload = function ($response): array {
        return ($this->extractPayload)($response, 'purchasing-orders-index-payload');
    };
});

it('1. redirects guests to login for the material detail page', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->getShow)(null, $item)
        ->assertRedirect(route('login'));
});

it('2. forbids material detail access without inventory view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->getShow)($user, $item)
        ->assertForbidden();
});

it('2b. supplier package missing conversion response includes quick modal context and no silent conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['name' => 'Gram', 'symbol' => 'g']);
    $packUom = ($this->makeUom)($tenant, ['name' => 'Case', 'symbol' => 'case']);
    $item = ($this->makeItem)($tenant, $baseUom, ['name' => 'Sauce']);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->postPackage)($user, $item, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '1.000000',
        'pack_uom_id' => $packUom->id,
        'supplier_sku' => 'CASE-1',
        'price_amount' => '12.34',
    ])->assertStatus(422)
        ->assertJsonPath('meta.requires_conversion', true)
        ->assertJsonPath('meta.conversion_create_url', route('manufacturing.uom-conversions.items.store'))
        ->assertJsonPath('meta.item.id', $item->id)
        ->assertJsonPath('meta.item.name', 'Sauce')
        ->assertJsonPath('meta.from_uom.id', $packUom->id)
        ->assertJsonPath('meta.from_uom.symbol', 'case')
        ->assertJsonPath('meta.to_uom.id', $baseUom->id)
        ->assertJsonPath('meta.to_uom.symbol', 'g')
        ->assertJsonPath('meta.suggested_direction', 'package_to_base');

    expect(ItemUomConversion::query()->count())->toBe(0);
});

it('2c. supplier package missing conversion modal uses existing conversion endpoint and allows retry after conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['name' => 'Gram', 'symbol' => 'g']);
    $packUom = ($this->makeUom)($tenant, ['name' => 'Case', 'symbol' => 'case']);
    $item = ($this->makeItem)($tenant, $baseUom);
    $payload = [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '1.000000',
        'pack_uom_id' => $packUom->id,
        'supplier_sku' => 'CASE-1',
        'price_amount' => '12.34',
    ];

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'inventory-materials-manage',
        'purchasing-suppliers-manage',
    ]);

    ($this->postPackage)($user, $item, $payload)->assertStatus(422);

    $this->actingAs($user)
        ->postJson(route('manufacturing.uom-conversions.items.store'), [
            'item_id' => $item->id,
            'from_uom_id' => $packUom->id,
            'to_uom_id' => $baseUom->id,
            'conversion_factor' => '24.000000',
        ])->assertCreated();

    ($this->postPackage)($user, $item, $payload)
        ->assertCreated()
        ->assertJsonPath('data.supplier_id', $supplier->id)
        ->assertJsonPath('data.pack_uom_id', $packUom->id);
});

it('2d. supplier package quick conversion endpoint enforces permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $baseUom = ($this->makeUom)($tenant);
    $packUom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $baseUom);

    $this->actingAs($user)
        ->postJson(route('manufacturing.uom-conversions.items.store'), [
            'item_id' => $item->id,
            'from_uom_id' => $packUom->id,
            'to_uom_id' => $baseUom->id,
            'conversion_factor' => '24.000000',
        ])->assertForbidden();
});

it('2e. reusable supplier package section opens quick conversion modal from missing conversion responses', function (): void {
    $sectionModule = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($sectionModule)->toContain('missingConversionModal')
        ->and($sectionModule)->toContain('openMissingConversionModal(data.meta, body)')
        ->and($sectionModule)->toContain('submitMissingConversion()')
        ->and($sectionModule)->toContain('conversion_create_url')
        ->and($sectionModule)->toContain('conversion_factor')
        ->and($sectionModule)->toContain('await this.submitForm()');
});

it('3. includes the supplier packages section config for purchasable materials with purchasing view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Purchasable Flour']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('data-page="materials-show"', false)
        ->assertSee('materials-show-payload', false)
        ->assertSee('data-js-crud-section-root', false);

    $section = ($this->extractSectionConfig)($response);

    expect($section['resource'] ?? null)->toBe('supplier-packages')
        ->and($section['endpoints']['list'] ?? null)->toBe(route('materials.supplier-packages.index', $item))
        ->and($section['endpoints']['create'] ?? null)->toBe(route('materials.supplier-packages.store', $item))
        ->and($section['endpoints']['update'] ?? null)->toBe(url("/materials/{$item->id}/supplier-packages/{id}"))
        ->and($section['endpoints']['remove'] ?? null)->toBe(url("/materials/{$item->id}/supplier-packages/{id}"));
});

it('3a. includes the purchase orders section config for purchasable materials with the current purchase order create permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Purchasable Purchase Order Material']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getShow)($user, $item)
        ->assertOk();

    $section = ($this->extractSectionConfig)($response, 'purchaseOrders');

    expect($section['resource'] ?? null)->toBe('material-purchase-orders')
        ->and($section['endpoints']['list'] ?? null)->toBe(route('materials.purchase-orders.index', $item))
        ->and($section['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and($section['defaultOpen'] ?? null)->toBeFalse()
        ->and(array_key_exists('createUrl', $section))->toBeFalse()
        ->and($section['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($section['recordClass'] ?? null)->toBe('rounded-lg border border-gray-200 bg-gray-50 px-3 py-1 sm:px-4 sm:py-1')
        ->and($section['rowClass'] ?? null)->toBe('flex flex-row items-start justify-between gap-4')
        ->and($section['rightMetaClass'] ?? null)
        ->toBe('flex min-h-[3.25rem] min-w-[5rem] flex-col items-end justify-between gap-4 self-stretch text-right')
        ->and($section['mobileRowUrlField'] ?? null)->toBe('display.showUrl')
        ->and($section['secondaryFieldsClass'] ?? null)
        ->toBe('mt-px flex flex-wrap items-center gap-x-3 gap-y-1')
        ->and($section['rowLayout']['primaryText']['urlField'] ?? null)->toBe('display.showUrl')
        ->and($section['rowLayout']['primaryText']['linkClass'] ?? null)
        ->toBe('truncate text-sm font-semibold text-gray-900 transition hover:text-gray-700')
        ->and($section['rowLayout']['secondaryFields'][0]['field'] ?? null)->toBe('display.supplierText')
        ->and($section['rowLayout']['secondaryFields'][1]['field'] ?? null)
        ->toBe('display.materialQuantityCostText')
        ->and($section['rowLayout']['secondaryFields'][1]['textClass'] ?? null)
        ->toContain('sm:whitespace-nowrap')
        ->and($section['rowLayout']['secondaryFields'][1]['fullWidth'] ?? null)->toBeTrue()
        ->and($section['rowLayout']['secondaryFields'])->toHaveCount(2)
        ->and($section['rowLayout']['rightMeta'][0]['field'] ?? null)->toBe('display.orderDateText')
        ->and($section['rowLayout']['rightMeta'][0]['textClass'] ?? null)->toContain('text-xs')
        ->and($section['rowLayout']['rightMeta'][1]['field'] ?? null)->toBe('display.materialLineTotalAmountText')
        ->and($section['rowLayout']['rightMeta'][1]['suffixField'] ?? null)
        ->toBe('display.materialLineTotalCurrencyText')
        ->and($section['actions'] ?? null)->toBe([]);
});

it('3b. keeps the purchase orders section read only with no row actions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item), 'purchaseOrders');
    $actionIds = collect($section['actions'] ?? [])->pluck('id')->all();

    expect($section['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and($section['fields'] ?? [])->toBe([])
        ->and(array_keys($section['endpoints'] ?? []))->toBe(['list'])
        ->and(array_key_exists('createUrl', $section))->toBeFalse()
        ->and($section['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($actionIds)->toBe([])
        ->and($actionIds)->not->toContain('edit')
        ->and($actionIds)->not->toContain('remove')
        ->and($actionIds)->not->toContain('archive');
});

it('3c. still includes the supplier packages section alongside purchase orders for purchasable materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->getShow)($user, $item)->assertOk();

    expect(($this->extractSectionConfig)($response, 'supplierPackages'))->not->toBe([])
        ->and(($this->extractSectionConfig)($response, 'purchaseOrders'))->not->toBe([]);
});

it('3ca. renders the purchase order create package payload for purchasable materials with supplier packages', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-3ca']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Payload Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Payload Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'PAYLOAD-3CA']);
    ($this->makePrice)($tenant, $option, [
        'price_cents' => 2750,
        'converted_price_cents' => 2750,
        'price_currency_code' => 'USD',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->getShow)($user, $item)->assertOk();
    $payload = ($this->extractPayload)($response, 'materials-show-payload');
    $package = collect($payload['purchaseOrderCreate']['packages'] ?? [])->firstWhere('id', $option->id);

    expect($payload['sections']['purchaseOrders']['defaultOpen'] ?? null)->toBeFalse()
        ->and($payload['sections']['purchaseOrders']['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and(array_key_exists('createUrl', $payload['sections']['purchaseOrders'] ?? []))->toBeFalse()
        ->and($package)->not->toBeNull()
        ->and($package['item_id'] ?? null)->toBe($item->id)
        ->and($package['item_name'] ?? null)->toBe('Payload Material')
        ->and($package['supplier_id'] ?? null)->toBe($supplier->id)
        ->and($package['supplier_name'] ?? null)->toBe('Payload Supplier')
        ->and($package['current_price_cents'] ?? null)->toBe(2750);
});

it('3d. purchase orders section create capability is hidden when the user lacks the current purchase order create permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getShow)($user, $item)->assertOk();

    expect(($this->extractSectionConfig)($response, 'purchaseOrders'))->toBe([]);
});

it('4. preserves the material detail header while using the section shell', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg-msp-4']);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Material Header',
        'is_purchasable' => true,
        'is_sellable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Material')
        ->assertSee('Material Header')
        ->assertSee('Materials')
        ->assertSee(route('materials.index'), false)
        ->assertDontSee('Back to Materials')
        ->assertSee('Kilogram')
        ->assertSee('Purchasable')
        ->assertSee('Sellable')
        ->assertSee('Manufacturable');
});

it('4a. material detail header contains the base uom badge and only the enabled flag icons', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['name' => 'Pound', 'symbol' => 'lb-msp-4a']);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Header Flags Material',
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Header Flags Material')
        ->assertSee('Pound')
        ->assertSee('Purchasable')
        ->assertDontSee('Sellable')
        ->assertDontSee('Manufacturable')
        ->assertDontSee('Core fields');
});

it('5. omits the supplier packages section for non-purchasable materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => false,
        'name' => 'Non Purchasable',
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getShow)($user, $item)
        ->assertOk();

    expect(($this->extractSectionConfig)($response))->toBe([]);

    $response->assertDontSee('data-js-crud-section-root', false);
});

it('5a. omits the purchase orders section for non-purchasable materials', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => false,
        'name' => 'Non Purchasable Purchase Orders',
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getShow)($user, $item)
        ->assertOk();

    expect(($this->extractSectionConfig)($response, 'purchaseOrders'))->toBe([]);
});

it('5b. material detail source renders supplier packages before purchase orders', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    $purchaseOrdersPosition = strpos($viewSource, 'data-section-key="purchaseOrders"');
    $supplierPackagesPosition = strpos($viewSource, 'data-section-key="supplierPackages"');

    expect($purchaseOrdersPosition)->not->toBeFalse()
        ->and($supplierPackagesPosition)->not->toBeFalse()
        ->and($supplierPackagesPosition)->toBeLessThan($purchaseOrdersPosition);
});

it('5c. material detail payload config defines supplier packages before purchase orders', function (): void {
    $controllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));

    $purchaseOrdersPosition = strpos($controllerSource, "'purchaseOrders' =>");
    $supplierPackagesPosition = strpos($controllerSource, "'supplierPackages' =>");

    expect($purchaseOrdersPosition)->not->toBeFalse()
        ->and($supplierPackagesPosition)->not->toBeFalse()
        ->and($supplierPackagesPosition)->toBeLessThan($purchaseOrdersPosition);
});

it('6. removes the legacy supplier package payload and duplicated server rendered package list markup', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($viewSource)->toContain('materials-show-payload')
        ->and($viewSource)->toContain('data-js-crud-section-root')
        ->and($viewSource)->toContain('px-1 sm:px-6')
        ->and($viewSource)->toContain('aria-label="Purchasable"')
        ->and($viewSource)->toContain('aria-label="Sellable"')
        ->and($viewSource)->toContain('aria-label="Manufacturable"')
        ->and($viewSource)->not->toContain('materials-show-supplier-packages-payload')
        ->and($viewSource)->not->toContain('data-section="supplier-packages"')
        ->and($viewSource)->not->toContain("@forelse (\$payload['packages'] as \$package)")
        ->and($viewSource)->not->toContain('Core fields')
        ->and($viewSource)->not->toContain('<h3 class="text-lg font-medium text-gray-900">Flags</h3>')
        ->and($viewSource)->not->toContain('<h3 class="text-lg font-medium text-gray-900">Base UoM</h3>');
});

it('7. js crud section source renders a responsive accordion card shell', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('-mx-1 !-mt-px overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0')
        ->and($source)->toContain('sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200')
        ->and($source)->toContain('class="flex items-start justify-between gap-3 px-3 py-2 sm:px-6 sm:py-2"')
        ->and($source)->toContain('class="border-t border-gray-100 px-3 py-2 sm:px-6 sm:py-5"')
        ->and($source)->toContain('class="mb-2 flex flex-col gap-2 sm:mb-4 sm:gap-3"')
        ->and($source)->toContain("targetEl.classList.add('!-mt-px', 'first:!mt-0', 'sm:!mt-6', 'sm:first:!mt-0')")
        ->and($source)->toContain('aria-expanded')
        ->and($source)->toContain('data-js-crud-section-card');
});

it('7a. js crud section source closes sibling accordions only on mobile', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain("globalThis.matchMedia('(max-width: 639px)').matches")
        ->and($source)->toContain('if (nextOpen && this.isMobileViewport())')
        ->and($source)->toContain('this.closeSiblingSections();')
        ->and($source)->toContain('closeSection()')
        ->and($source)->toContain("querySelectorAll(':scope > [data-js-crud-section-root]')");
});

it('8. js crud section source defaults the accordion closed', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('defaultOpen: asBoolean(safeConfig.defaultOpen)')
        ->and($source)->toContain('isOpen: asBoolean(section.defaultOpen)');
});

it('9. js crud section source lazy loads records on first open', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('hasLoaded: false')
        ->and($source)->toContain('if (nextOpen && !this.hasLoaded)')
        ->and($source)->toContain('await this.fetchPage(1)');
});

it('10. js crud section source caches the first fetch and does not refetch on reopen', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('this.hasLoaded = true')
        ->and($source)->toContain('if (nextOpen && !this.hasLoaded)');
});

it('11. js crud section source fetches pagination pages explicitly', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain("params.set('page', String(page))");
});

it('11a. js crud section source defaults accordion rows to five records per page', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('perPage: normalizePositiveInteger(pagination.perPage, 5)')
        ->and($source)->toContain('perPageOptions: normalizePaginationOptions(pagination.perPageOptions)')
        ->and($source)->toContain('allowPerPageChange: Boolean(pagination.allowPerPageChange)')
        ->and($source)->toContain("params.set('per_page', String(this.paginationPerPage()))");
});

it('11b. js crud section source renders shared pagination controls for accordion rows', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('paginatedRecords()')
        ->and($source)->toContain('paginationLastPage()')
        ->and($source)->toContain('paginationCurrentPage()')
        ->and($source)->toContain('goToPaginationPage(paginationCurrentPage() - 1)')
        ->and($source)->toContain('goToPaginationPage(paginationCurrentPage() + 1)')
        ->and($source)->toContain('data-js-crud-section-pagination');
});

it('11bc. js crud section source uses card footer page button pagination styling', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('paginationPages()')
        ->and($source)->toContain('paginationPageItem(page, current)')
        ->and($source)->toContain('paginationEllipsisItem(position)')
        ->and($source)->toContain('paginationShowingFrom()')
        ->and($source)->toContain('paginationShowingTo()')
        ->and($source)->toContain('paginationTotal()')
        ->and($source)->toContain('flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6')
        ->and($source)->toContain('isolate inline-flex -space-x-px rounded-md shadow-sm')
        ->and($source)->toContain("page.current ? 'z-10 bg-blue-600 text-white")
        ->and($source)->toContain("x-bind:aria-current=\"page.current ? 'page' : null\"");
});

it('11ba. js crud section source keeps paginated accordion row height stable', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('data-js-crud-section-records')
        ->and($source)->toContain("section.pagination.enabled && paginationLastPage() > 1 ? 'min-h-[22rem] sm:min-h-[20rem]' : ''");
});

it('11bb. js crud section source renders mobile rows square flush and without vertical row gaps', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('class="-mx-3 space-y-0 border-t border-gray-300 sm:mx-0 sm:space-y-3 sm:border-t-0"')
        ->and($source)->toContain('const mobileRecordClass = (className) => asString(className)')
        ->and($source)->toContain("return `sm:${token}`;")
        ->and($source)->toContain("return `px-3 py-2 sm:${token}`;")
        ->and($source)->toContain("return 'py-2 sm:py-1';")
        ->and($source)->toContain("return `border-gray-300 sm:${token}`;")
        ->and($source)->toContain("return ['relative', 'max-sm:border-t-0 sm:mt-0', mobileClass, mobileRecordClass(adapterClass), mobileRecordClass(recordLevelClass)]");
});

it('11c. js crud section source resets and clamps pagination as rows change', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('resetPagination()')
        ->and($source)->toContain('clampPaginationPage()')
        ->and($source)->toContain('this.resetPagination();')
        ->and($source)->toContain('this.clampPaginationPage();');
});

it('11d. reusable section list endpoints honor the shared per page parameter', function (): void {
    $controllerSources = [
        file_get_contents(app_path('Http/Controllers/MaterialSupplierPackageController.php')),
        file_get_contents(app_path('Http/Controllers/MaterialPurchaseOrderController.php')),
        file_get_contents(app_path('Http/Controllers/InventoryCountController.php')),
        file_get_contents(app_path('Http/Controllers/ItemController.php')),
        file_get_contents(app_path('Http/Controllers/RecipeController.php')),
        file_get_contents(app_path('Http/Controllers/MakeOrderController.php')),
    ];

    foreach ($controllerSources as $source) {
        expect($source)->toContain("'per_page' => ['nullable', 'integer', 'min:1', 'max:50']")
            ->and($source)->toContain('->paginate($perPage)');
    }
});

it('12. section config exposes create capability when the user can manage supplier packages', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));

    expect($section['permissions']['canCreate'] ?? null)->toBeTrue();
});

it('13. section config hides create capability when the user lacks manage permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));

    expect($section['permissions']['canCreate'] ?? null)->toBeFalse();
});

it('14. section config uses supplier package create and edit fields from configuration', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $fieldNames = collect($section['fields'] ?? [])->pluck('name')->all();

    expect($fieldNames)->toBe([
        'supplier_id',
        'pack_quantity',
        'pack_uom_id',
        'supplier_sku',
        'price_amount',
    ]);
});

it('14a. supplier package numeric fields use the smart number field contract', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->makeSupplier)($tenant, ['currency_code' => 'CAD']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $fields = collect($section['fields'] ?? [])->keyBy('name');
    $supplierOptions = collect($fields->get('supplier_id')['options'] ?? []);

    expect($fields->get('pack_quantity'))->toMatchArray([
        'type' => 'smart-number',
        'numberType' => 'decimal',
        'precision' => 6,
        'rowGroup' => 'package-quantity-uom',
        'width' => 'short',
    ])->and($fields->get('price_amount'))->toMatchArray([
        'type' => 'smart-number',
        'numberType' => 'money',
        'precision' => 2,
        'currencyFromField' => 'supplier_id',
        'currencyOptionField' => 'currency_code',
        'rowGroup' => 'supplier-sku-price',
        'width' => 'right',
    ])->and($supplierOptions->first()['currency_code'] ?? null)->toBe('CAD');
});

it('14b. supplier package create fields group quantity with uom and sku with price', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $fields = collect($section['fields'] ?? [])->keyBy('name');

    expect($fields->get('pack_quantity')['rowGroup'] ?? null)->toBe('package-quantity-uom')
        ->and($fields->get('pack_uom_id')['rowGroup'] ?? null)->toBe('package-quantity-uom')
        ->and($fields->get('pack_quantity')['width'] ?? null)->toBe('short')
        ->and($fields->get('supplier_sku')['rowGroup'] ?? null)->toBe('supplier-sku-price')
        ->and($fields->get('supplier_sku')['width'] ?? null)->toBe('short')
        ->and($fields->get('price_amount')['rowGroup'] ?? null)->toBe('supplier-sku-price')
        ->and($fields->get('price_amount')['width'] ?? null)->toBe('right');
});

it('14c. supplier package create drawer uses supplier pack header copy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));

    expect($section['createAction']['title'] ?? null)->toBe('Supplier Package')
        ->and($section['createAction']['description'] ?? null)->toBe('Create a supplier pack to purchase.')
        ->and($section['createAction']['submitLabel'] ?? null)->toBe('Create');
});

it('15. material supplier packages expose purchase and archive row actions without view edit remove or delete actions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $actionIds = collect($section['actions'] ?? [])->pluck('id')->all();

    expect($actionIds)->toContain('purchase')
        ->and($actionIds)->toContain('archive')
        ->and($actionIds)->not->toContain('view')
        ->and($actionIds)->not->toContain('edit')
        ->and($actionIds)->not->toContain('remove')
        ->and($actionIds)->not->toContain('delete');
});

it('15a. material supplier packages use inline icon actions instead of the dots menu', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $actions = collect($section['actions'] ?? [])->keyBy('id');
    $crudSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($section['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($section['inlineActionsOnMobile'] ?? null)->toBeTrue()
        ->and($section['recordClass'] ?? null)->toBe('rounded-xl border border-gray-200 bg-gray-50 px-3 py-1 sm:px-4 sm:py-1')
        ->and($actions->get('purchase')['icon'] ?? null)->toBe('credit-card')
        ->and($actions->get('purchase')['tooltip'] ?? null)->toBe('Purchase Order')
        ->and($actions->get('archive')['icon'] ?? null)->toBe('x-mark')
        ->and($actions->get('archive')['tooltip'] ?? null)->toBe('Archive')
        ->and($actions->get('archive')['confirmMessage'] ?? null)->toBe('Are you sure you want to archive this supplier package?')
        ->and($crudSource)->toContain("action.icon === 'credit-card'")
        ->and($crudSource)->toContain("action.confirmMessage !== '' && !globalThis.confirm(action.confirmMessage)")
        ->and($crudSource)->toContain('x-bind:title="actionTooltip(record, action)"')
        ->and($crudSource)->toContain('inlineActionButtonClass(action)')
        ->and($crudSource)->toContain("section.inlineActionsOnMobile ? 'flex-row items-center justify-between'")
        ->and($crudSource)->toContain("line.hideLabelOnMobile ? 'hidden sm:inline' : ''")
        ->and($crudSource)->toContain('mobilePrimaryFieldItems(record)')
        ->and($crudSource)->toContain("line.mobilePlacement === 'primary-end'")
        ->and($crudSource)->toContain('gap-4 sm:gap-3')
        ->and($crudSource)->toContain('hidden shrink-0 text-xs text-gray-700 max-sm:block')
        ->and($crudSource)->not->toContain('ml-auto hidden shrink-0 text-xs text-gray-700 max-sm:block')
        ->and($crudSource)->toContain('text-xs sm:text-sm')
        ->and($crudSource)->toContain('hover:border-blue-600')
        ->and($crudSource)->toContain('text-xs font-medium text-gray-500')
        ->and($crudSource)->toContain('M2.25 8.25h19.5');
});

it('15b. material supplier package row config renders price amount and small currency beside sku', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $secondaryFields = collect(data_get($section, 'rowLayout.secondaryFields', []));
    $secondaryFieldNames = $secondaryFields->pluck('field')->all();
    $priceField = $secondaryFields->firstWhere('label', 'Price');

    expect($secondaryFieldNames)->toBe([
        'display.packageText',
        'display.skuText',
        'price_amount',
    ])->and($priceField['suffixField'] ?? null)->toBe('current_price_currency_code')
        ->and($secondaryFields->firstWhere('label', 'SKU')['fallback'] ?? null)->toBe('')
        ->and($secondaryFields->pluck('hideLabelOnMobile')->all())->toBe([true, true, true])
        ->and($secondaryFields->pluck('compactOnMobile')->all())->toBe([true, true, true])
        ->and($priceField['mobilePlacement'] ?? null)->toBe('primary-end')
        ->and(data_get($section, 'rowLayout.rightMeta'))->toBe([]);
});

it('15ba. supplier package row adapters leave blank sku values blank instead of rendering a placeholder', function (): void {
    $materialPageSource = file_get_contents(resource_path('js/pages/materials-show.js'));
    $supplierPageSource = file_get_contents(resource_path('js/pages/purchasing-suppliers-show.js'));
    $materialControllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));
    $supplierControllerSource = file_get_contents(app_path('Http/Controllers/SupplierController.php'));

    expect($materialPageSource)->toContain('skuText: asString(record.supplier_sku),')
        ->and($supplierPageSource)->toContain('skuText: asString(record.supplier_sku),')
        ->and($materialPageSource)->not->toContain("skuText: asString(record.supplier_sku, '—')")
        ->and($supplierPageSource)->not->toContain("skuText: asString(record.supplier_sku, '—')")
        ->and($materialControllerSource)->toContain("'fallback' => ''")
        ->and($supplierControllerSource)->toContain("'fallback' => ''");
});

it('15c. supplier package rows fall back to latest purchase order line price when current price is missing', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-15c']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option, [
        'unit_price_currency_code' => 'CAD',
        'converted_unit_price_amount' => 4321,
        'unit_price_cents' => 4321,
        'line_subtotal_cents' => 8642,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getPackages)($user, $item)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $option->id);

    expect($row['current_price_display'] ?? null)->toBe('CAD 43.21')
        ->and($row['current_price_cents'] ?? null)->toBe(4321)
        ->and($row['current_price_currency_code'] ?? null)->toBe('CAD')
        ->and($row['price_amount'] ?? null)->toBe('43.21');
});

it('16. js crud section source uses config driven fields for create and edit slide overs', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('x-for="field in section.fields"')
        ->and($source)->toContain('openCreateForm()')
        ->and($source)->toContain('openEditForm(record)');
});

it('17. js crud section source uses config driven row actions without global state', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('visibleActions(record)')
        ->and($source)->toContain('showRowActionsMenu')
        ->and($source)->toContain('section.actions')
        ->and($source)->toContain('action.type')
        ->and($source)->toContain("case 'custom'")
        ->and($source)->toContain('this.adapters.handleAction')
        ->and($source)->not->toContain('window.');
});

it('17a. js crud section core source does not reference supplier package specific row fields', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->not->toContain('supplier_name')
        ->and($source)->not->toContain('pack_quantity_display')
        ->and($source)->not->toContain('pack_uom_symbol')
        ->and($source)->not->toContain('supplier_sku')
        ->and($source)->not->toContain('current_price_display')
        ->and($source)->not->toContain('po_number')
        ->and($source)->not->toContain('order_date')
        ->and($source)->not->toContain('po_grand_total_cents')
        ->and($source)->not->toContain('show_url');
});

it('17b. js crud section source exposes adapter hooks for row normalization payload mapping and custom actions', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('normalizeRow')
        ->and($source)->toContain('buildCreatePayload')
        ->and($source)->toContain('buildUpdatePayload')
        ->and($source)->toContain('handleAction');
});

it('18. js crud section source does not render import export or search ui', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->not->toContain('Import')
        ->and($source)->not->toContain('Export')
        ->and($source)->not->toContain('type="search"')
        ->and($source)->not->toContain('data-js-crud-section-search');
});

it('18a. js crud section source uses a square rounded lg create button and mobile friendly layout classes', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('rounded-lg border border-gray-300')
        ->and($source)->toContain('h-7 w-7')
        ->and($source)->toContain('sm:h-8 sm:w-8')
        ->and($source)->toContain('h-3.5 w-3.5 sm:h-4 sm:w-4')
        ->and($source)->not->toContain('rounded-full border border-gray-200')
        ->and($source)->toContain('px-3 sm:px-6')
        ->and($source)->toContain('p-3 sm:p-4')
        ->and($source)->toContain('flex-col sm:flex-row');
});

it('18aa. js crud section source keeps the accordion trigger right aligned on mobile with a generic header layout', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('flex items-start justify-between gap-3')
        ->and($source)->toContain('min-w-0 flex-1')
        ->and($source)->toContain('px-3 py-3 sm:px-6 sm:py-2')
        ->and($source)->toContain('text-sm font-semibold leading-tight text-gray-900 sm:text-base')
        ->and($source)->toContain('mt-0 text-[0.7rem] leading-tight text-gray-500 sm:mt-px sm:text-xs')
        ->and($source)->toContain('h-7 w-7 shrink-0')
        ->and($source)->toContain('sm:h-8 sm:w-8')
        ->and($source)->toContain('h-3.5 w-3.5 text-gray-400')
        ->and($source)->toContain('sm:h-4 sm:w-4')
        ->and($source)->toContain('shrink-0')
        ->and($source)->not->toContain('rounded-full border border-gray-200');
});

it('18ab. js crud section source supports configurable default open state for embedded sections', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('defaultOpen')
        ->and($source)->toContain('isOpen: asBoolean(section.defaultOpen)');
});

it('18b. js crud section source keeps the create button right aligned in the accordion header area', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('data-js-crud-section-toggle')
        ->and($source)->toContain('aria-label="Toggle section"')
        ->and($source)->not->toContain('data-js-crud-section-header-actions');
});

it('18c. js crud section source renders the create button only inside expanded content and keeps it permission gated', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('data-js-crud-section-create-button')
        ->and($source)->toContain('data-js-crud-section-create-wrapper')
        ->and($source)->toContain('flex justify-end')
        ->and($source)->toContain('data-js-crud-section-toggle')
        ->and($source)->toContain('x-on:click.stop.prevent="openCreateForm()"')
        ->and($source)->toContain('x-on:click="toggleOpen()"')
        ->and($source)->toContain('aria-label="Create"')
        ->and($source)->toContain('aria-label="Toggle section"')
        ->and($source)->toContain('x-show="isOpen"')
        ->and($source)->toContain('x-show="section.permissions.canCreate"');
});

it('18d. js crud section supports a read only view action without core changes', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain("case 'view'")
        ->and($source)->toContain("case 'custom'")
        ->and($source)->toContain('urlField');
});

it('18e. js crud section card shell does not clip row action dropdowns', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('data-js-crud-section-card')
        ->and($source)->toContain('overflow-visible border border-gray-500')
        ->and($source)->toContain('sm:rounded-2xl')
        ->and($source)->not->toContain('overflow-hidden rounded-2xl');
});

it('18f. js crud section row action menu wrapper keeps menus above surrounding section content', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('class="relative inline-flex overflow-visible"')
        ->and($source)->toContain('absolute right-0 z-30 mt-2 w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg');
});

it('18g. js crud section keeps row action menus enabled by default unless a section disables them explicitly', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('showRowActionsMenu: safeConfig.showRowActionsMenu !== false')
        ->and($source)->toContain('rowClass: asString(safeConfig.rowClass)')
        ->and($source)->toContain('rightMetaClass: asString(safeConfig.rightMetaClass)')
        ->and($source)->toContain('showRowActionsMenuOnMobile: safeConfig.showRowActionsMenuOnMobile !== false')
        ->and($source)->toContain('mobileRowUrlField: asString(safeConfig.mobileRowUrlField)')
        ->and($source)->toContain('secondaryFieldsClass: asString(safeConfig.secondaryFieldsClass)')
        ->and($source)->toContain('rowActionsMenuVisible(record)')
        ->and($source)->toContain('mobileRowUrl(record)')
        ->and($source)->toContain('primaryTextLinkClass()')
        ->and($source)->toContain('recordRowClass(record)')
        ->and($source)->toContain('rightMetaColumnClass(record)')
        ->and($source)->toContain('x-show="section.showRowActionsMenu && visibleActions(record).length > 0"')
        ->and($source)->toContain('x-show="!section.showRowActionsMenu && visibleActions(record).length > 0"');
});

it('19. material detail page module mounts the shared js crud section component through config and adapters', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($pageSource)->toContain('mountCrudSection(')
        ->and($pageSource)->toContain('normalizeRow:')
        ->and($pageSource)->toContain('buildCreatePayload:')
        ->and($pageSource)->toContain('buildUpdatePayload:')
        ->and($pageSource)->toContain('handleAction:')
        ->and($pageSource)->not->toContain('materialsShowSupplierPricing')
        ->and($pageSource)->not->toContain('packages: safePayload.packages || []');
});

it('19a. supplier packages row layout is expressed through config instead of core row markup', function (): void {
    $controllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));

    expect($controllerSource)->toContain("'rowLayout' =>")
        ->and($controllerSource)->toContain("'primaryText'")
        ->and($controllerSource)->toContain("'secondaryFields'")
        ->and($controllerSource)->toContain("'badges'")
        ->and($controllerSource)->toContain("'rightMeta'");
});

it('19b. purchase orders row layout is expressed through config and page adapters instead of core row markup', function (): void {
    $controllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($controllerSource)->toContain("'purchaseOrders'")
        ->and($controllerSource)->toContain("'resource' => 'material-purchase-orders'")
        ->and($controllerSource)->toContain("'rowLayout' =>")
        ->and($controllerSource)->toContain("'field' => 'display.supplierText'")
        ->and($controllerSource)->toContain("'field' => 'display.materialQuantityCostText'")
        ->and($controllerSource)->toContain("'field' => 'display.materialLineTotalAmountText'")
        ->and($controllerSource)->toContain("'suffixField' => 'display.materialLineTotalCurrencyText'")
        ->and($controllerSource)->toContain("'showRowActionsMenu' => false")
        ->and($controllerSource)->toContain("'rowClass' => 'flex flex-row items-start justify-between gap-4'")
        ->and($controllerSource)->toContain("'rightMetaClass' => 'flex min-h-[3.25rem] min-w-[5rem] flex-col items-end justify-between gap-4 self-stretch text-right'")
        ->and($controllerSource)->toContain("'mobileRowUrlField' => 'display.showUrl'")
        ->and($controllerSource)->toContain("'secondaryFieldsClass' => 'mt-px flex flex-wrap items-center gap-x-3 gap-y-1'")
        ->and($controllerSource)->toContain("'urlField' => 'display.showUrl'")
        ->and($controllerSource)->toContain("'linkClass' => 'truncate text-sm font-semibold text-gray-900 transition hover:text-gray-700'")
        ->and($controllerSource)->toContain("'urlField' => 'display.showUrl'")
        ->and($pageSource)->toContain('purchaseOrders:')
        ->and($pageSource)->toContain('showUrl')
        ->and($pageSource)->toContain('poNumberText')
        ->and($pageSource)->toContain('supplierText')
        ->and($pageSource)->toContain('materialQuantityCostText')
        ->and($pageSource)->toContain('materialLineTotalCurrencyText')
        ->and($pageSource)->toContain('statusText');
});

it('20. requires authentication for supplier package list requests', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->getPackages)(null, $item)
        ->assertUnauthorized();
});

it('21. forbids supplier package list requests without purchasing supplier view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getPackages)($user, $item)
        ->assertForbidden();
});

it('22. returns 404 for cross tenant material package list access', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->getPackages)($user, $item)
        ->assertNotFound();
});

it('23. returns paginated supplier package rows with supplier name quantity uom price state and metadata', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-23']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'List Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'List Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['pack_quantity' => '8.500000']);
    ($this->makePrice)($tenant, $option, [
        'price_currency_code' => 'USD',
        'converted_price_cents' => 2599,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getPackages)($user, $item, ['page' => 1])
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1);

    expect($response->json('data.0.id'))->toBe($option->id)
        ->and($response->json('data.0.item_purchase_option_id'))->toBe($option->id)
        ->and($response->json('data.0.supplier_name'))->toBe('List Supplier')
        ->and($response->json('data.0.pack_quantity'))->toBe('8.500000')
        ->and($response->json('data.0.pack_uom_symbol'))->toBe('kg-msp-23')
        ->and($response->json('data.0.show_url'))->toBe(route('purchasing.suppliers.show', $supplier))
        ->and($response->json('data.0.current_price_display'))->toBe('USD 25.99')
        ->and($response->json('data.0.state'))->toBe('active')
        ->and($response->json('data.0.is_active'))->toBeTrue()
        ->and($response->json('meta.total'))->toBe(1);
});

it('24. excludes cross tenant supplier package rows even if rogue records reference the current material id', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-24']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $visible = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'VISIBLE-24']);

    ItemPurchaseOption::query()->create([
        'tenant_id' => $otherTenant->id,
        'supplier_id' => $otherSupplier->id,
        'item_id' => $item->id,
        'supplier_sku' => 'ROGUE-24',
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
        'is_active' => true,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getPackages)($user, $item)->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    $skus = collect($response->json('data'))->pluck('supplier_sku')->all();

    expect($ids)->toContain($visible->id)
        ->and($skus)->not->toContain('ROGUE-24');
});

it('24a. supplier package rows expose tenant scoped supplier detail links only for visible records', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-24a']);
    $otherUom = ($this->makeUom)($otherTenant, ['symbol' => 'kg-msp-24a-other']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Visible Supplier']);
    $visibleOption = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'VISIBLE-LINK']);
    $otherSupplier = ($this->makeSupplier)($otherTenant, ['company_name' => 'Hidden Supplier']);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);
    ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom, ['supplier_sku' => 'HIDDEN-LINK']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getPackages)($user, $item)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $visibleOption->id);

    expect($row['show_url'] ?? null)->toBe(route('purchasing.suppliers.show', $supplier))
        ->and(collect($response->json('data'))->pluck('show_url')->filter()->all())
        ->not->toContain(route('purchasing.suppliers.show', $otherSupplier));
});

it('24b. supplier packages section exposes a view row action that uses the supplier detail link', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getShow)($user, $item)->assertOk();
    $section = ($this->extractSectionConfig)($response, 'supplierPackages');

    expect($section['actions'][0]['id'] ?? null)->toBe('view')
        ->and($section['actions'][0]['type'] ?? null)->toBe('view')
        ->and($section['actions'][0]['urlField'] ?? null)->toBe('display.showUrl');
});

it('25. returns an empty data set and pagination metadata when no supplier packages exist', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $response = ($this->getPackages)($user, $item)
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.current_page', 1);

    expect($response->json('data'))->toBe([]);
});

it('26. excludes archived packages from the material supplier package list payload', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-26']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Archived Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'is_active' => false,
        'supplier_sku' => 'ARCH-26',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    $response = ($this->getPackages)($user, $item)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $option->id);

    expect($row)->toBeNull()
        ->and($response->json('meta.total'))->toBe(0);
});

it('26aa. material supplier package list source filters archived rows after archive actions', function (): void {
    $controllerSource = file_get_contents(app_path('Http/Controllers/MaterialSupplierPackageController.php'));

    expect($controllerSource)->toContain("->where('is_active', true)")
        ->and($controllerSource)->toContain("'result' => 'archived'");
});

it('26a. active supplier package rows include the purchase action when the user can create purchase orders', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-26a']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->getPackages)($user, $item)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $option->id);

    expect($row['available_actions'] ?? [])->toContain('purchase')
        ->and($row['available_actions'] ?? [])->not->toContain('view')
        ->and($row['available_actions'] ?? [])->not->toContain('edit');
});

it('26b. active supplier package rows include the archive action when the user can manage supplier packages', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-26b']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    $response = ($this->getPackages)($user, $item)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $option->id);

    expect($row['available_actions'] ?? [])->toContain('archive')
        ->and($row['available_actions'] ?? [])->not->toContain('view')
        ->and($row['available_actions'] ?? [])->not->toContain('edit');
});

it('27. requires authentication for supplier package create requests', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->postPackage)(null, $item, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertUnauthorized();
});

it('28. forbids supplier package create without manage permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->postPackage)($user, $item, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertForbidden();
});

it('29. returns 404 when creating a supplier package for a cross tenant material', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->postPackage)($user, $otherItem, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertNotFound();
});

it('30. validates required supplier package create fields and requires an existing tenant supplier', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->postPackage)($user, $item, [
        'pack_quantity' => '0',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_id', 'pack_quantity', 'pack_uom_id']);
});

it('31. creates an item purchase option for the current material and returns the created resource contract', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-31']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Create Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Create Supplier']);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    $response = ($this->postPackage)($user, $item, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '6.250000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'CREATE-31',
        'price_amount' => '40.00',
    ])->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.supplier_id', $supplier->id)
        ->assertJsonPath('data.pack_quantity', '6.250000')
        ->assertJsonPath('data.pack_uom_id', $uom->id)
        ->assertJsonPath('data.supplier_sku', 'CREATE-31')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.price_amount', '40.00')
        ->assertJsonPath('data.current_price_cents', 4000);

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $response->json('data.id'),
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'supplier_id' => $supplier->id,
        'supplier_sku' => 'CREATE-31',
        'is_active' => 1,
    ]);

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $response->json('data.id'),
        'price_cents' => 4000,
        'converted_price_cents' => 4000,
        'ended_at' => null,
    ]);
});

it('31aa. creates a supplier package when an indirect generic conversion path exists', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $gram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'g')->firstOrFail();
    $kilogram = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'kg')->firstOrFail();
    $pound = Uom::query()->where('tenant_id', $tenant->id)->where('symbol', 'lb')->firstOrFail();
    $item = ($this->makeItem)($tenant, $pound, ['name' => 'Indirect Path Material']);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $kilogram->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '1000.00000000',
    ]);
    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $pound->id,
        'to_uom_id' => $gram->id,
        'multiplier' => '453.59200000',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->postPackage)($user, $item, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '20.000000',
        'pack_uom_id' => $kilogram->id,
        'supplier_sku' => 'INDIRECT-31AA',
        'price_amount' => '40.00',
    ])->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.supplier_id', $supplier->id)
        ->assertJsonPath('data.pack_uom_id', $kilogram->id);

    $this->assertDatabaseHas('item_purchase_options', [
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'supplier_id' => $supplier->id,
        'pack_uom_id' => $kilogram->id,
        'supplier_sku' => 'INDIRECT-31AA',
    ]);
});

it('31a. rejects invalid decimal package prices on create', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->postPackage)($user, $item, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '1.000000',
        'pack_uom_id' => $uom->id,
        'price_amount' => '12.345',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_amount']);
});

it('32. allows duplicate supplier packages when the existing domain rules permit them', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    $payload = [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '4.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'DUP-32',
        'price_amount' => '10.00',
    ];

    ($this->postPackage)($user, $item, $payload)->assertCreated();
    ($this->postPackage)($user, $item, $payload)->assertCreated();

    $this->assertDatabaseCount('item_purchase_options', 2);
});

it('33. forbids supplier package edit without manage permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->patchPackage)($user, $item, $option, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '9.000000',
        'pack_uom_id' => $uom->id,
    ])->assertForbidden();
});

it('34. returns 404 when editing a cross tenant supplier package', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);
    $supplier = ($this->makeSupplier)($otherTenant);
    $option = ($this->makeOption)($otherTenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->patchPackage)($user, $item, $option, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '9.000000',
        'pack_uom_id' => $uom->id,
    ])->assertNotFound();
});

it('35. updates supplier package fields for the current material', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-35']);
    $otherUom = ($this->makeUom)($tenant, ['symbol' => 'bag-msp-35']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant, ['company_name' => 'Updated Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'EDIT-35']);

    ItemUomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'from_uom_id' => $otherUom->id,
        'to_uom_id' => $uom->id,
        'conversion_factor' => '1.000000',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->patchPackage)($user, $item, $option, [
        'supplier_id' => $otherSupplier->id,
        'pack_quantity' => '12.500000',
        'pack_uom_id' => $otherUom->id,
        'supplier_sku' => 'EDITED-35',
        'price_amount' => '52.25',
    ])->assertOk()
        ->assertJsonPath('data.id', $option->id)
        ->assertJsonPath('data.supplier_id', $otherSupplier->id)
        ->assertJsonPath('data.pack_quantity', '12.500000')
        ->assertJsonPath('data.pack_uom_id', $otherUom->id)
        ->assertJsonPath('data.supplier_sku', 'EDITED-35')
        ->assertJsonPath('data.price_amount', '52.25')
        ->assertJsonPath('data.current_price_cents', 5225);

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
        'supplier_id' => $otherSupplier->id,
        'pack_quantity' => '12.500000',
        'pack_uom_id' => $otherUom->id,
        'supplier_sku' => 'EDITED-35',
    ]);

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $option->id,
        'price_cents' => 5225,
        'converted_price_cents' => 5225,
        'ended_at' => null,
    ]);
});

it('35a. rejects invalid decimal package prices on edit', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->patchPackage)($user, $item, $option, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '2.000000',
        'pack_uom_id' => $uom->id,
        'price_amount' => '-1.00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['price_amount']);
});

it('36. requires authentication for supplier package remove requests', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->deletePackage)(null, $item, $option)
        ->assertUnauthorized();
});

it('37. forbids supplier package remove without manage permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->deletePackage)($user, $item, $option)
        ->assertForbidden();
});

it('38. returns 404 when removing a cross tenant supplier package', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);
    $supplier = ($this->makeSupplier)($otherTenant);
    $option = ($this->makeOption)($otherTenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->deletePackage)($user, $item, $option)
        ->assertNotFound();
});

it('39. removes a supplier package record when no purchase order line history exists and leaves the supplier intact', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Keep Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->deletePackage)($user, $item, $option)
        ->assertOk()
        ->assertJsonPath('result', 'removed')
        ->assertJsonPath('message', 'Removed.');

    $this->assertDatabaseMissing('item_purchase_options', [
        'id' => $option->id,
    ]);

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'company_name' => 'Keep Supplier',
    ]);
});

it('40. archives a supplier package instead of removing it when purchase order line history exists and keeps orders intact', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'ARCHIVE-40']);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier);
    $line = ($this->makePurchaseOrderLine)($tenant, $order, $item, $option);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->deletePackage)($user, $item, $option)
        ->assertOk()
        ->assertJsonPath('result', 'archived')
        ->assertJsonPath('message', 'Archived.');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
        'is_active' => 0,
    ]);

    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $line->id,
        'item_purchase_option_id' => $option->id,
    ]);
});

it('41. archives a supplier package instead of removing it when receipt history exists and keeps receipts intact', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'ARCHIVE-41']);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier);
    $line = ($this->makePurchaseOrderLine)($tenant, $order, $item, $option);
    $receipt = ($this->makeReceipt)($tenant, $order, $user);
    $receiptLine = ($this->makeReceiptLine)($tenant, $receipt, $line);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-suppliers-manage',
    ]);

    ($this->deletePackage)($user, $item, $option)
        ->assertOk()
        ->assertJsonPath('result', 'archived')
        ->assertJsonPath('message', 'Archived.');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
        'is_active' => 0,
    ]);

    $this->assertDatabaseHas('purchase_order_receipt_lines', [
        'id' => $receiptLine->id,
        'purchase_order_line_id' => $line->id,
    ]);
});

it('42. archived supplier packages no longer appear as active purchase options on the purchase order detail page', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $activeOption = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'ACTIVE-42']);
    $archivedOption = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'supplier_sku' => 'ARCHIVED-42',
        'is_active' => false,
    ]);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier);

    ($this->grantPermissions)($user, [
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->getPurchaseOrderShow)($user, $order)
        ->assertOk();

    $payload = ($this->extractPurchaseOrderPayload)($response);
    $optionIds = collect($payload['purchaseOptions'] ?? [])->pluck('id')->all();

    expect($optionIds)->toContain($activeOption->id)
        ->and($optionIds)->not->toContain($archivedOption->id);
});

it('43. requires authentication for supplier package edit requests', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->patchPackage)(null, $item, $option, [
        'supplier_id' => $supplier->id,
        'pack_quantity' => '7.000000',
        'pack_uom_id' => $uom->id,
    ])->assertUnauthorized();
});

it('44. returns 404 for cross tenant material detail access in the embedded section flow', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->getShow)($user, $item)
        ->assertNotFound();
});

it('45. requires authentication for material purchase order list requests', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->getMaterialPurchaseOrders)(null, $item)
        ->assertUnauthorized();
});

it('46. forbids material purchase order list requests without purchase order read permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getMaterialPurchaseOrders)($user, $item)
        ->assertForbidden();
});

it('47. returns 404 for cross tenant material purchase order list access', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    ($this->getMaterialPurchaseOrders)($user, $item)
        ->assertNotFound();
});

it('48. returns paginated purchase orders for the material and excludes unrelated orders', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-48']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Material With Orders']);
    $otherItem = ($this->makeItem)($tenant, $uom, ['name' => 'Other Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'PO Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'PO-48-OPTION']);
    $otherOption = ($this->makeOption)($tenant, $supplier, $otherItem, $uom, ['supplier_sku' => 'PO-48-OTHER']);
    $visibleOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, [
        'po_number' => 'PO-48',
        'po_subtotal_cents' => 1800,
        'po_grand_total_cents' => 2250,
        'shipping_cents' => 300,
        'tax_cents' => 150,
        'status' => PurchaseOrder::STATUS_CREATED,
        'back_ordered_at' => now(),
    ]);
    $hiddenOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, [
        'po_number' => 'PO-HIDDEN-48',
        'po_subtotal_cents' => 9900,
        'po_grand_total_cents' => 9900,
        'status' => PurchaseOrder::STATUS_CREATED,
    ]);

    ($this->makePurchaseOrderLine)($tenant, $visibleOrder, $item, $option, [
        'pack_count' => 5,
        'line_subtotal_cents' => 2500,
        'unit_price_currency_code' => 'CAD',
    ]);
    ($this->makePurchaseOrderLine)($tenant, $visibleOrder, $otherItem, $otherOption, [
        'line_subtotal_cents' => 600,
    ]);
    ($this->makePurchaseOrderLine)($tenant, $hiddenOrder, $otherItem, $otherOption, [
        'line_subtotal_cents' => 9900,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getMaterialPurchaseOrders)($user, $item, ['page' => 1])
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.id'))->toBe($visibleOrder->id)
        ->and($response->json('data.0.po_number'))->toBe('PO-48')
        ->and($response->json('data.0.supplier_name'))->toBe('PO Supplier')
        ->and($response->json('data.0.order_date'))->toBe('May 15, 2026')
        ->and($response->json('data.0.po_grand_total_cents'))->toBe(2250)
        ->and($response->json('data.0.material_quantity_display'))->toBe('50.0')
        ->and($response->json('data.0.material_uom_symbol'))->toBe('kg-msp-48')
        ->and($response->json('data.0.material_line_total_amount_display'))->toBe('25.00')
        ->and($response->json('data.0.material_line_total_currency_code'))->toBe('CAD')
        ->and($response->json('data.0.material_unit_cost_amount_display'))->toBe('0.50')
        ->and($response->json('data.0.material_unit_cost_currency_code'))->toBe('CAD')
        ->and($response->json('data.0.status'))->toBe(PurchaseOrder::STATUS_CREATED)
        ->and($response->json('data.0.show_url'))->toBe(route('purchasing.orders.show', $visibleOrder));

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($visibleOrder->id)
        ->and($ids)->not->toContain($hiddenOrder->id);
});

it('48a. uses purchase order package uom for material purchase order row totals', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'CAD']);
    $user = ($this->makeUser)($tenant);
    $baseUom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-48a']);
    $packUom = Uom::query()->create([
        'tenant_id' => $tenant->id,
        'uom_category_id' => $baseUom->uom_category_id,
        'name' => 'Pound MSP 48a',
        'symbol' => 'lb-msp-48a',
    ]);
    $item = ($this->makeItem)($tenant, $baseUom, ['name' => 'Converted PO Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Converted PO Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $packUom, [
        'pack_quantity' => '10.000000',
    ]);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier, [
        'po_number' => 'PO-48A',
        'po_subtotal_cents' => 3000,
        'po_grand_total_cents' => 3000,
        'status' => PurchaseOrder::STATUS_CREATED,
    ]);

    UomConversion::query()->create([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $packUom->id,
        'to_uom_id' => $baseUom->id,
        'multiplier' => '0.500000',
    ]);
    ($this->makePurchaseOrderLine)($tenant, $order, $item, $option, [
        'pack_count' => 3,
        'line_subtotal_cents' => 3000,
        'unit_price_currency_code' => 'CAD',
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getMaterialPurchaseOrders)($user, $item)->assertOk();

    expect($response->json('data.0.material_quantity_display'))->toBe('30.0')
        ->and($response->json('data.0.material_uom_symbol'))->toBe('lb-msp-48a')
        ->and($response->json('data.0.material_line_total_amount_display'))->toBe('30.00')
        ->and($response->json('data.0.material_line_total_currency_code'))->toBe('CAD')
        ->and($response->json('data.0.material_unit_cost_amount_display'))->toBe('1.00')
        ->and($response->json('data.0.material_unit_cost_currency_code'))->toBe('CAD');
});

it('49. excludes cross tenant purchase orders even if rogue lines reference the current material id', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-49']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $visibleOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier, ['po_number' => 'PO-49']);
    ($this->makePurchaseOrderLine)($tenant, $visibleOrder, $item, $option);

    $otherSupplier = ($this->makeSupplier)($otherTenant, ['company_name' => 'Other Tenant Supplier']);
    $otherUser = ($this->makeUser)($otherTenant);
    $otherUom = ($this->makeUom)($otherTenant, ['symbol' => 'kg-other-49']);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);
    $otherOption = ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom);
    $rogueOrder = ($this->makePurchaseOrder)($otherTenant, $otherUser, $otherSupplier, ['po_number' => 'PO-ROGUE-49']);

    PurchaseOrderLine::withoutGlobalScopes()->create([
        'tenant_id' => $otherTenant->id,
        'purchase_order_id' => $rogueOrder->id,
        'item_id' => $item->id,
        'item_purchase_option_id' => $otherOption->id,
        'pack_count' => 1,
        'unit_price_cents' => 100,
        'line_subtotal_cents' => 100,
        'unit_price_amount' => 100,
        'unit_price_currency_code' => 'USD',
        'converted_unit_price_amount' => 100,
        'fx_rate' => '1.000000',
        'fx_rate_as_of' => Carbon::parse('2026-05-15')->toDateString(),
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getMaterialPurchaseOrders)($user, $item)->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($visibleOrder->id)
        ->and($ids)->not->toContain($rogueOrder->id);
});

it('50. returns an empty paginated purchase order list when the material is not on any orders', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getMaterialPurchaseOrders)($user, $item)
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.current_page', 1);

    expect($response->json('data'))->toBe([]);
});

it('51. purchase order rows include raw status fields and the page adapter owns the safe po number fallback', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-51']);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Fallback Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier, [
        'po_number' => null,
        'po_subtotal_cents' => 500,
        'po_grand_total_cents' => 700,
        'shipping_cents' => 100,
        'tax_cents' => 100,
        'status' => PurchaseOrder::STATUS_RECEIVED,
    ]);

    ($this->makePurchaseOrderLine)($tenant, $order, $item, $option, [
        'line_subtotal_cents' => 500,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $response = ($this->getMaterialPurchaseOrders)($user, $item)->assertOk();
    $row = $response->json('data.0');
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect(array_key_exists('po_number', $row))->toBeTrue()
        ->and($row['po_number'])->toBeNull()
        ->and($row['status'] ?? null)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and($row['show_url'] ?? null)->toBe(route('purchasing.orders.show', $order))
        ->and($pageSource)->toContain('Draft PO')
        ->and($pageSource)->toContain('statusTone')
        ->and($pageSource)->toContain('poNumberText');
});

it('52. material purchase order section exposes only a view action and no mutation or create capability', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-purchase-orders-create']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item), 'purchaseOrders');

    expect(collect($section['actions'] ?? [])->pluck('label')->all())->toBe(['View'])
        ->and($section['endpoints'] ?? [])->toBe([
            'list' => route('materials.purchase-orders.index', $item),
        ])
        ->and($section['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and(array_key_exists('createUrl', $section))->toBeFalse();
});

it('53. supplier package purchase action uses direct draft purchase order creation and redirect', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));
    $controllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));

    expect($pageSource)->toContain('createPurchaseOrderFromSupplierPackage')
        ->and($pageSource)->toContain("item_purchase_option_id: record.item_purchase_option_id ?? record.id")
        ->and($pageSource)->toContain('window.location.assign(data.data.show_url)')
        ->and($controllerSource)->toContain("'handlerKey' => 'purchase'")
        ->and($pageSource)->not->toContain('window.purchaseOrderCreate');
});

it('54. supplier package purchase action does not open the purchase order create drawer', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-show.js'));

    expect($pageSource)->toContain('createPurchaseOrderFromSupplierPackage')
        ->and($pageSource)->toContain("action.handlerKey === 'purchase'")
        ->and($pageSource)->not->toContain('openFromSupplierPackage');
});

it('55. supplier packages config exposes only purchase and destructive row actions while keeping the header plus for package create only', function (): void {
    $controllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));
    $crudSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($controllerSource)->toContain("'id' => 'purchase'")
        ->and($controllerSource)->toContain("'type' => 'custom'")
        ->and($controllerSource)->toContain("'handlerKey' => 'purchase'")
        ->and($crudSource)->toContain('x-show="section.permissions.canCreate"')
        ->and($crudSource)->toContain('openCreateForm()');
});

it('56. requires authentication for creating a draft purchase order from a material supplier package', function (): void {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->postMaterialPurchaseOrder)(null, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '2',
    ])->assertUnauthorized();
});

it('57. forbids creating a draft purchase order from a material supplier package without purchase order create permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '2',
    ])->assertForbidden();
});

it('58. creates a draft purchase order with exactly one line from a supplier package and returns the redirect url', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-58']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'PO Create Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'PO Create Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    ($this->makePrice)($tenant, $option, [
        'price_cents' => 2500,
        'converted_price_cents' => 2500,
        'price_currency_code' => 'USD',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
    ])->assertCreated();

    $purchaseOrderId = $response->json('data.id');

    expect($response->json('data.show_url'))->toBe(route('purchasing.orders.show', $purchaseOrderId));

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $purchaseOrderId,
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_DRAFT,
        'po_subtotal_cents' => 2500,
        'po_grand_total_cents' => 2500,
    ]);

    $this->assertDatabaseHas('purchase_order_lines', [
        'purchase_order_id' => $purchaseOrderId,
        'item_id' => $item->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => 1,
        'unit_price_cents' => 2500,
        'line_subtotal_cents' => 2500,
    ]);
});

it('58b. package created purchase orders open details by default when date or po number are missing', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-58b']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'PO Details Open Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'PO Details Open Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    ($this->makePrice)($tenant, $option, [
        'price_cents' => 1250,
        'converted_price_cents' => 1250,
        'price_currency_code' => 'USD',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->postMaterialPurchaseOrder)($user, $item, [
        'item_purchase_option_id' => $option->id,
    ])->assertCreated();

    $showResponse = ($this->getPurchaseOrderShow)(
        $user,
        PurchaseOrder::query()->findOrFail((int) $response->json('data.id'))
    )->assertOk();
    $detailsSection = Str::between(
        $showResponse->getContent(),
        '<h3 class="text-lg font-semibold text-gray-900">Details</h3>',
        '<h3 class="text-lg font-semibold text-gray-900">Items</h3>'
    );

    expect($detailsSection)->toContain('x-data="{ open: true }"');
});

it('58a. keeps the copied purchase order line price historical after the supplier package price changes later', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg-msp-58a']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Historical Price Material']);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Historical Price Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $initialPrice = ($this->makePrice)($tenant, $option, [
        'price_cents' => 1800,
        'converted_price_cents' => 1800,
        'price_currency_code' => 'USD',
    ]);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    $response = ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '4',
    ])->assertCreated();

    $purchaseOrderId = $response->json('data.id');

    ItemPurchaseOptionPrice::query()
        ->whereKey($initialPrice->id)
        ->update(['ended_at' => now()]);

    ($this->makePrice)($tenant, $option, [
        'price_cents' => 2200,
        'converted_price_cents' => 2200,
        'price_currency_code' => 'USD',
    ]);

    $this->assertDatabaseHas('purchase_order_lines', [
        'purchase_order_id' => $purchaseOrderId,
        'item_purchase_option_id' => $option->id,
        'unit_price_cents' => 1800,
        'line_subtotal_cents' => 7200,
    ]);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $purchaseOrderId,
        'po_subtotal_cents' => 7200,
        'po_grand_total_cents' => 7200,
    ]);
});

it('59. rejects cross tenant supplier package purchase order creation', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $otherOption = ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $otherItem, [
        'supplier_id' => $otherSupplier->id,
        'item_purchase_option_id' => $otherOption->id,
        'pack_count' => '2',
    ])->assertNotFound();
});

it('60. rejects non purchasable material purchase order creation from supplier package context', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_purchasable' => false]);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '2',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);
});

it('61. rejects supplier packages that do not belong to the material', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $otherItem = ($this->makeItem)($tenant, $uom, ['name' => 'Other Item']);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $otherItem, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '2',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_purchase_option_id']);
});

it('62. rejects a supplier that does not match the supplier package', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant, ['company_name' => 'Wrong Supplier']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $otherSupplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '2',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_id']);
});

it('63. validates missing and invalid supplier package purchase order create inputs', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $item, [])->assertStatus(422)
        ->assertJsonValidationErrors(['item_purchase_option_id'])
        ->assertJsonMissingValidationErrors(['pack_count']);
});

it('64. rejects zero negative and decimal pack counts for supplier package purchase order creation', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => '0',
    ])->assertStatus(422)->assertJsonValidationErrors(['pack_count']);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => '-1',
    ])->assertStatus(422)->assertJsonValidationErrors(['pack_count']);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'item_purchase_option_id' => $option->id,
        'pack_count' => '1.5',
    ])->assertStatus(422)->assertJsonValidationErrors(['pack_count']);
});

it('64a. rejects supplier package purchase order creation when the selected package has no current price', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->postMaterialPurchaseOrder)($user, $item, [
        'supplier_id' => $supplier->id,
        'item_purchase_option_id' => $option->id,
        'pack_count' => '2',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_purchase_option_id']);

    $this->assertDatabaseCount('purchase_orders', 0);
    $this->assertDatabaseCount('purchase_order_lines', 0);
});

it('65. opening the supplier package purchase flow does not create a purchase order automatically', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
    ]);

    ($this->getShow)($user, $item)->assertOk();

    $this->assertDatabaseCount('purchase_orders', 0);
});
