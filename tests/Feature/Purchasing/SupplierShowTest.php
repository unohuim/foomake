<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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
            'supplier_sku' => $attributes['supplier_sku'] ?? 'SKU-' . $this->optionCounter,
            'pack_quantity' => $attributes['pack_quantity'] ?? '10.000000',
            'pack_uom_id' => $uom->id,
            'is_active' => $attributes['is_active'] ?? true,
        ], $attributes));

        $this->optionCounter++;

        return $option;
    };

    $this->makePrice = function (
        Tenant $tenant,
        ItemPurchaseOption $option,
        array $attributes = []
    ): ItemPurchaseOptionPrice {
        return ItemPurchaseOptionPrice::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_purchase_option_id' => $option->id,
            'price_cents' => $attributes['price_cents'] ?? 1234,
            'price_currency_code' => $attributes['price_currency_code'] ?? 'USD',
            'converted_price_cents' => $attributes['converted_price_cents'] ?? ($attributes['price_cents'] ?? 1234),
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

    $this->getShow = function (User $user, Supplier $supplier) {
        return $this->actingAs($user)->get(route('purchasing.suppliers.show', $supplier));
    };

    $this->getList = function (User $user, Supplier $supplier) {
        return $this->actingAs($user)->getJson(route('purchasing.suppliers.purchase-options.index', $supplier));
    };

    $this->getPurchaseOrders = function (User $user, Supplier $supplier) {
        return $this->actingAs($user)->getJson(route('purchasing.suppliers.purchase-orders.index', $supplier));
    };

    $this->postPurchaseOrder = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('purchasing.orders.store'), $payload);
    };

    $this->postOption = function (User $user, Supplier $supplier, array $payload) {
        return $this->actingAs($user)
            ->postJson(route('purchasing.suppliers.purchase-options.store', $supplier), $payload);
    };

    $this->patchOption = function (User $user, Supplier $supplier, ItemPurchaseOption $option, array $payload) {
        return $this->actingAs($user)
            ->patchJson(route('purchasing.suppliers.purchase-options.update', [$supplier, $option]), $payload);
    };

    $this->deleteOption = function (User $user, Supplier $supplier, ItemPurchaseOption $option) {
        return $this->actingAs($user)
            ->deleteJson(route('purchasing.suppliers.purchase-options.destroy', [$supplier, $option]));
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

it('returns 404 for cross-tenant supplier access', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($otherTenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertNotFound();
});

it('renders the supplier detail page module payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Breadcrumb Supplier']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('Suppliers')
        ->assertSee('Breadcrumb Supplier')
        ->assertSee(route('purchasing.suppliers.index'), false)
        ->assertSee('data-page="purchasing-suppliers-show"', false)
        ->assertSee('purchasing-suppliers-show-payload', false);
});

it('renders the reusable supplier packages detail section mount shell', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-js-crud-section-root', false)
        ->assertSee('data-section-key="supplierPackages"', false);
});

it('removes the old bespoke supplier packages markup from the Blade response', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertDontSee('data-section="supplier-packages"', false)
        ->assertDontSee('submitPackageAndPrice', false)
        ->assertDontSee('Save package & price', false)
        ->assertDontSee('supplier-package-actions', false);
});

it('exposes supplier packages reusable section config in the payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $section = $payload['sections']['supplierPackages'] ?? [];

    expect($section['resource'] ?? null)->toBe('supplier-packages')
        ->and($section['title'] ?? null)->toBe('Supplier Packages')
        ->and($section['endpoints']['list'] ?? null)->toBe(route('purchasing.suppliers.purchase-options.index', $supplier))
        ->and($section['endpoints']['create'] ?? null)->toBe(route('purchasing.suppliers.purchase-options.store', $supplier))
        ->and($section['endpoints']['update'] ?? null)->toContain('/purchasing/suppliers/' . $supplier->id . '/purchase-options/{id}')
        ->and($section['endpoints']['remove'] ?? null)->toContain('/purchasing/suppliers/' . $supplier->id . '/purchase-options/{id}');
});

it('configures the plus button create slide-over through the shared section contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $section = $payload['sections']['supplierPackages'];

    expect($section['permissions']['canCreate'])->toBeTrue()
        ->and($section['createAction']['type'] ?? null)->toBe('slide-over')
        ->and($section['createAction']['label'] ?? null)->toBe('Add Supplier Package');
});

it('configures a material field for create because supplier context is fixed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Granulated Sugar']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $fields = collect($payload['sections']['supplierPackages']['fields']);
    $materialField = $fields->firstWhere('name', 'item_id');

    expect($materialField)->not()->toBeNull()
        ->and($materialField['label'])->toBe('Material')
        ->and($materialField['type'])->toBe('combobox')
        ->and(collect($materialField['options'])->pluck('value')->all())->toContain((string) $item->id)
        ->and($fields->pluck('name')->all())->not()->toContain('supplier_id');
});

it('fixes supplier from detail context instead of making supplier selectable', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $section = $payload['sections']['supplierPackages'];

    expect($payload['supplier']['id'])->toBe($supplier->id)
        ->and($section['context']['supplier_id'] ?? null)->toBe($supplier->id)
        ->and($section['createAction']['prefill']['supplier_id'] ?? null)->toBe($supplier->id)
        ->and(collect($section['fields'])->pluck('name')->all())->not()->toContain('supplier_id');
});

it('configures row menu actions as exactly Edit Purchase Delete', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $actions = collect($payload['sections']['supplierPackages']['actions']);

    expect($actions->pluck('label')->all())->toBe(['Edit', 'Purchase', 'Delete'])
        ->and($actions->pluck('id')->all())->toBe(['edit', 'purchase', 'delete'])
        ->and($actions->pluck('type')->all())->toBe(['edit', 'custom', 'remove']);
});

it('uses the shared vertical dots menu pattern for row actions', function () {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js')) ?: '';

    expect($source)->toContain('section.showRowActionsMenu')
        ->and($source)->toContain('aria-haspopup="menu"')
        ->and($source)->toContain('M12 6.75');
});

it('lists only packages scoped to the current supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $visible = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'VISIBLE-1']);
    $hidden = ($this->makeOption)($tenant, $otherSupplier, $item, $uom, ['supplier_sku' => 'HIDDEN-1']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getList)($user, $supplier)->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toContain($visible->id)
        ->and(collect($response->json('data'))->pluck('id')->all())->not()->toContain($hidden->id);
});

it('prevents cross-tenant package visibility in the supplier scoped list', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($tenant, $uom);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);
    $visible = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $hidden = ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getList)($user, $supplier)->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toContain($visible->id)
        ->and(collect($response->json('data'))->pluck('id')->all())->not()->toContain($hidden->id);
});

it('returns package rows with material package supplier sku and price fields', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Cake Flour']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, [
        'pack_quantity' => '5.000000',
        'supplier_sku' => 'FLOUR-5KG',
    ]);

    ($this->makePrice)($tenant, $option, ['price_cents' => 1299, 'converted_price_cents' => 1299]);
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $row = ($this->getList)($user, $supplier)->assertOk()->json('data.0');

    expect($row['item_name'])->toBe('Cake Flour')
        ->and($row['pack_quantity'])->toBe('5.000000')
        ->and($row['pack_uom_symbol'])->toBe('kg')
        ->and($row['supplier_sku'])->toBe('FLOUR-5KG')
        ->and($row['current_price_display'])->toBe('USD 12.99');
});

it('gives view-only users rows without create edit or delete visibility', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $row = ($this->getList)($user, $supplier)->assertOk()->json('data.0');

    expect($payload['sections']['supplierPackages']['permissions']['canCreate'])->toBeFalse()
        ->and($row['available_actions'])->not()->toContain('edit')
        ->and($row['available_actions'])->not()->toContain('delete');
});

it('gives manage users create edit and delete visibility', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $row = ($this->getList)($user, $supplier)->assertOk()->json('data.0');

    expect($payload['sections']['supplierPackages']['permissions']['canCreate'])->toBeTrue()
        ->and($row['available_actions'])->toContain('edit')
        ->and($row['available_actions'])->toContain('delete');
});

it('adds purchase action only when the user can create purchase orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $row = ($this->getList)($user, $supplier)->assertOk()->json('data.0');

    expect($row['available_actions'])->toContain('purchase')
        ->and($row['purchase_url'])->toBe(route('materials.purchase-orders.store', $item));
});

it('uses existing delete behavior to remove packages with no dependent records', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteOption)($user, $supplier, $option)
        ->assertOk()
        ->assertJsonPath('message', 'Deleted.');

    $this->assertDatabaseMissing('item_purchase_options', ['id' => $option->id]);
});

it('archives instead of deleting when dependent purchase order lines exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, $user, $supplier);
    ($this->makePurchaseOrderLine)($tenant, $purchaseOrder, $item, $option);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteOption)($user, $supplier, $option)
        ->assertOk()
        ->assertJsonPath('message', 'Archived.');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
        'is_active' => false,
    ]);
});

it('keeps JSON validation intact for create update and delete requests', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postOption)($user, $supplier, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['item_id', 'pack_quantity', 'pack_uom_id']);

    ($this->patchOption)($user, $supplier, $option, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['item_id', 'pack_quantity', 'pack_uom_id']);

    ($this->deleteOption)($user, $supplier, $option)
        ->assertOk()
        ->assertJsonStructure(['message']);
});

it('prevents cross-tenant package mutation', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($tenant, $uom);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);
    $otherOption = ($this->makeOption)($otherTenant, $otherSupplier, $otherItem, $otherUom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->patchOption)($user, $supplier, $otherOption, [
        'item_id' => $item->id,
        'pack_quantity' => '2.000000',
        'pack_uom_id' => $uom->id,
    ])->assertNotFound();

    ($this->deleteOption)($user, $supplier, $otherOption)
        ->assertNotFound();
});

it('creates a supplier package with an initial price from the reusable section payload', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postOption)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '6.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'PRICE-1',
        'price_amount' => '25.00',
    ])->assertCreated();

    $this->assertDatabaseHas('item_purchase_option_prices', [
        'item_purchase_option_id' => $response->json('data.id'),
        'price_cents' => 2500,
        'price_currency_code' => 'USD',
        'ended_at' => null,
    ]);
});

it('updates a supplier package through the reusable section endpoint', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant, ['symbol' => 'kg']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Original']);
    $newItem = ($this->makeItem)($tenant, $uom, ['name' => 'Updated']);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'OLD']);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->patchOption)($user, $supplier, $option, [
        'item_id' => $newItem->id,
        'pack_quantity' => '4.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'NEW',
        'price_amount' => '18.50',
    ])->assertOk()
        ->assertJsonPath('data.item_id', $newItem->id)
        ->assertJsonPath('data.supplier_sku', 'NEW')
        ->assertJsonPath('data.current_price_display', 'USD 18.50');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
        'item_id' => $newItem->id,
        'pack_quantity' => '4.000000',
        'supplier_sku' => 'NEW',
    ]);
});

it('renders the reusable purchase orders detail section mount shell', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-js-crud-section-root', false)
        ->assertSee('data-section-key="purchaseOrders"', false);
});

it('exposes supplier purchase orders reusable section config in the payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $section = $payload['sections']['purchaseOrders'] ?? [];

    expect($section['resource'] ?? null)->toBe('supplier-purchase-orders')
        ->and($section['title'] ?? null)->toBe('Purchase Orders')
        ->and($section['defaultOpen'] ?? null)->toBeFalse()
        ->and($section['endpoints']['list'] ?? null)->toBe(route('purchasing.suppliers.purchase-orders.index', $supplier));
});

it('matches the Material detail purchase orders row layout and row action contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $section = $payload['sections']['purchaseOrders'];

    expect($section['rowLayout']['primaryText']['field'])->toBe('display.poNumberText')
        ->and(collect($section['rowLayout']['secondaryFields'])->pluck('label')->all())
        ->toBe(['Order date', 'Supplier'])
        ->and($section['rowLayout']['badges'][0]['field'])->toBe('display.statusText')
        ->and($section['rowLayout']['rightMeta'][0]['label'])->toBe('Total')
        ->and(collect($section['actions'])->pluck('label')->all())->toBe(['View'])
        ->and(collect($section['actions'])->pluck('type')->all())->toBe(['view']);
});

it('lists purchase orders for the current supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier, ['po_number' => 'SUP-PO-1']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getPurchaseOrders)($user, $supplier)->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toContain($order->id)
        ->and($response->json('data.0.po_number'))->toBe('SUP-PO-1');
});

it('does not list purchase orders for other suppliers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant);
    $visible = ($this->makePurchaseOrder)($tenant, $user, $supplier);
    $hidden = ($this->makePurchaseOrder)($tenant, $user, $otherSupplier);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $ids = collect(($this->getPurchaseOrders)($user, $supplier)->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toContain($visible->id)
        ->and($ids)->not()->toContain($hidden->id);
});

it('lists supplier purchase orders even when they have no package lines', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier, ['po_number' => 'NO-LINES']);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getPurchaseOrders)($user, $supplier)->assertOk();

    expect(PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->exists())->toBeFalse()
        ->and(collect($response->json('data'))->pluck('id')->all())->toContain($order->id);
});

it('returns purchase order row display fields supported by the Material detail section contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Display Supplier']);
    $order = ($this->makePurchaseOrder)($tenant, $user, $supplier, [
        'po_number' => 'DISPLAY-1',
        'order_date' => Carbon::parse('2026-05-20')->toDateString(),
        'status' => PurchaseOrder::STATUS_OPEN,
        'po_grand_total_cents' => 4567,
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $row = ($this->getPurchaseOrders)($user, $supplier)->assertOk()->json('data.0');

    expect($row['id'])->toBe($order->id)
        ->and($row['po_number'])->toBe('DISPLAY-1')
        ->and($row['status'])->toBe(PurchaseOrder::STATUS_OPEN)
        ->and($row['order_date'])->toBe('2026-05-20')
        ->and($row['supplier_name'])->toBe('Display Supplier')
        ->and($row['po_grand_total_cents'])->toBe(4567)
        ->and($row['show_url'])->toBe(route('purchasing.orders.show', $order));
});

it('uses the shared vertical dots menu pattern for purchase order rows', function () {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js')) ?: '';

    expect($source)->toContain('section.showRowActionsMenu')
        ->and($source)->toContain('aria-haspopup="menu"')
        ->and($source)->toContain('M12 6.75');
});

it('configures the purchase order plus action for users with create permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());
    $section = $payload['sections']['purchaseOrders'];

    expect($section['permissions']['canCreate'])->toBeTrue()
        ->and($section['createAction']['type'])->toBe('custom')
        ->and($section['createAction']['handlerKey'])->toBe('createSupplierPurchaseOrder')
        ->and($section['createAction']['url'])->toBe(route('purchasing.orders.store'))
        ->and($section['createAction']['prefill']['supplier_id'])->toBe($supplier->id);
});

it('hides the purchase order plus action from supplier view-only users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());

    expect($payload['sections']['purchaseOrders']['permissions']['canCreate'])->toBeFalse();
});

it('creates a draft purchase order for the current supplier through the existing PO store route', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = ($this->postPurchaseOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    $purchaseOrderId = $response->json('data.id');

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $purchaseOrderId,
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_DRAFT,
    ]);
});

it('preserves nullable draft header fields when supplier detail creates a draft purchase order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $purchaseOrderId = ($this->postPurchaseOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated()->json('data.id');

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $purchaseOrderId,
        'order_date' => null,
        'shipping_cents' => null,
        'tax_cents' => null,
        'po_number' => null,
        'notes' => null,
    ]);
});

it('returns the purchase order detail route after successful supplier draft creation', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = ($this->postPurchaseOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertCreated();

    expect($response->json('data.show_url'))->toBe(route('purchasing.orders.show', $response->json('data.id')));
});

it('forbids supplier draft purchase order creation without purchase order create permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->postPurchaseOrder)($user, [
        'supplier_id' => $supplier->id,
    ])->assertForbidden();

    $this->assertDatabaseCount('purchase_orders', 0);
});

it('prevents cross-tenant purchase order visibility in the supplier scoped list', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $visible = ($this->makePurchaseOrder)($tenant, $user, $supplier);
    $otherUser = ($this->makeUser)($otherTenant);
    $hidden = ($this->makePurchaseOrder)($otherTenant, $otherUser, $otherSupplier);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $ids = collect(($this->getPurchaseOrders)($user, $supplier)->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toContain($visible->id)
        ->and($ids)->not()->toContain($hidden->id);
});

it('prevents cross-tenant draft creation for another tenant supplier', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->postPurchaseOrder)($user, [
        'supplier_id' => $otherSupplier->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_id']);

    $this->assertDatabaseCount('purchase_orders', 0);
});

it('returns supplier purchase orders using the shared section JSON envelope', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->makePurchaseOrder)($tenant, $user, $supplier);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getPurchaseOrders)($user, $supplier)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_date',
                    'supplier_name',
                    'po_number',
                    'po_grand_total_cents',
                    'status',
                    'show_url',
                    'available_actions',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});

it('does not add page-local bespoke purchase order section markup', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertDontSee('data-section="supplier-purchase-orders"', false)
        ->assertDontSee('submitSupplierPurchaseOrder', false);
});

it('keeps the supplier packages section mounted alongside purchase orders', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-section-key="supplierPackages"', false)
        ->assertSee('data-section-key="purchaseOrders"', false);
});

it('keeps Material detail purchase orders behavior as the existing read-only section contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $response = $this->actingAs($user)->get(route('materials.show', $item))->assertOk();
    $content = $response->getContent();
    preg_match('/<script[^>]+id="materials-show-payload"[^>]*>(.*?)<\\/script>/s', $content, $matches);
    $payload = json_decode($matches[1] ?? '{}', true) ?: [];
    $section = $payload['sections']['purchaseOrders'] ?? [];

    expect($section['resource'] ?? null)->toBe('material-purchase-orders')
        ->and($section['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and(collect($section['actions'] ?? [])->pluck('label')->all())->toBe(['View']);
});

it('wires supplier purchase order create through the page module without global state', function () {
    $pageSource = file_get_contents(resource_path('js/pages/purchasing-suppliers-show.js')) ?: '';

    expect($pageSource)->toContain('handleCreateAction')
        ->and($pageSource)->toContain('createSupplierPurchaseOrder')
        ->and($pageSource)->toContain('supplier_id')
        ->and($pageSource)->toContain('window.location.assign')
        ->and($pageSource)->not()->toContain('window.supplierPurchaseOrders');
});

it('renders a shared Details accordion for editable supplier fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-detail-section-card', false)
        ->assertSee('open: false', false)
        ->assertSee('Details')
        ->assertSee('Update supplier contact and purchasing defaults.');
});

it('renders supplier detail editable fields for name website phone email and currency', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('Supplier name')
        ->assertSee('Website')
        ->assertSee('Phone')
        ->assertSee('Email')
        ->assertSee('Currency');
});

it('exposes supplier detail update contract in the page payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant, [
        'company_name' => 'Details Supplier',
        'url' => 'https://supplier.example',
        'phone' => '555-0100',
        'email' => 'buyer@supplier.example',
        'currency_code' => 'CAD',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());

    expect($payload['supplier']['company_name'])->toBe('Details Supplier')
        ->and($payload['supplier']['url'])->toBe('https://supplier.example')
        ->and($payload['supplier']['phone'])->toBe('555-0100')
        ->and($payload['supplier']['email'])->toBe('buyer@supplier.example')
        ->and($payload['supplier']['currency_code'])->toBe('CAD')
        ->and($payload['supplier']['update_url'])->toBe(route('purchasing.suppliers.update', $supplier));
});

it('disables supplier detail fields for users without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());

    expect($payload['supplier']['can_manage'])->toBeFalse();
});

it('enables supplier detail fields for users with manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = ($this->extractSupplierPayload)(($this->getShow)($user, $supplier)->assertOk());

    expect($payload['supplier']['can_manage'])->toBeTrue();
});

it('wires supplier detail saves through the page module without global state', function () {
    $pageSource = file_get_contents(resource_path('js/pages/purchasing-suppliers-show.js')) ?: '';

    expect($pageSource)->toContain('saveDetails')
        ->and($pageSource)->toContain('detailsPayload')
        ->and($pageSource)->toContain('supplier.update_url')
        ->and($pageSource)->toContain('company_name')
        ->and($pageSource)->toContain('currency_code')
        ->and($pageSource)->not()->toContain('window.supplierDetails');
});

it('highlights changed supplier detail fields with a lime border after save', function () {
    $viewSource = file_get_contents(resource_path('views/purchasing/suppliers/show.blade.php')) ?: '';
    $pageSource = file_get_contents(resource_path('js/pages/purchasing-suppliers-show.js')) ?: '';

    expect($viewSource)->toContain("detailsFieldClass('company_name')")
        ->and($viewSource)->toContain("saveDetails('company_name')")
        ->and($viewSource)->toContain("detailsFieldClass('currency_code')")
        ->and($viewSource)->toContain("saveDetails('currency_code')")
        ->and($pageSource)->toContain('detailsSavedFields')
        ->and($pageSource)->toContain('border-2')
        ->and($pageSource)->toContain('border-lime-400')
        ->and($pageSource)->toContain('window.setTimeout')
        ->and($pageSource)->toContain('1000');
});

it('keeps reusable package and purchase order sections after adding supplier details', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getShow)($user, $supplier)
        ->assertOk()
        ->assertSee('data-detail-section-card', false)
        ->assertSee('data-section-key="supplierPackages"', false)
        ->assertSee('data-section-key="purchaseOrders"', false);
});
