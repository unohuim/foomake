<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
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

    $this->extractSectionConfig = function ($response): array {
        $payload = ($this->extractPayload)($response, 'materials-show-payload');

        $section = $payload['sections']['supplierPackages'] ?? null;

        return is_array($section) ? $section : [];
    };

    $this->getPackages = function (?User $user, Item $item, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('materials.supplier-packages.index', array_merge([
            'item' => $item,
        ], $query)));
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

it('4. preserves the material detail header while using the section shell', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg-msp-4']);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Material Header']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Material')
        ->assertSee('Material Header')
        ->assertSee('Back to Materials')
        ->assertSee('Kilogram (kg-msp-4)');
});

it('4a. core fields section contains the base uom and all material flags', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['name' => 'Pound', 'symbol' => 'lb-msp-4a']);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Core Fields Material',
        'is_purchasable' => true,
        'is_sellable' => true,
        'is_manufacturable' => false,
    ]);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    ($this->getShow)($user, $item)
        ->assertOk()
        ->assertSee('Core fields')
        ->assertSee('Name')
        ->assertSee('Base UoM')
        ->assertSee('Pound (lb-msp-4a)')
        ->assertSee('Purchasable')
        ->assertSee('Sellable')
        ->assertSee('Manufacturable');
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

it('6. removes the legacy supplier package payload and duplicated server rendered package list markup', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/show.blade.php'));

    expect($viewSource)->toContain('materials-show-payload')
        ->and($viewSource)->toContain('data-js-crud-section-root')
        ->and($viewSource)->toContain('px-1 sm:px-6')
        ->and($viewSource)->toContain('>Flags<')
        ->and($viewSource)->not->toContain('materials-show-supplier-packages-payload')
        ->and($viewSource)->not->toContain('data-section="supplier-packages"')
        ->and($viewSource)->not->toContain("@forelse (\$payload['packages'] as \$package)")
        ->and($viewSource)->not->toContain('<h3 class="text-lg font-medium text-gray-900">Flags</h3>')
        ->and($viewSource)->not->toContain('<h3 class="text-lg font-medium text-gray-900">Base UoM</h3>');
});

it('7. js crud section source renders a rounded accordion card shell', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('rounded-2xl')
        ->and($source)->toContain('aria-expanded')
        ->and($source)->toContain('data-js-crud-section-card');
});

it('8. js crud section source defaults the accordion closed', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('isOpen: false');
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

it('15. section config exposes config driven row actions and no delete action id', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'purchasing-suppliers-view']);

    $section = ($this->extractSectionConfig)(($this->getShow)($user, $item));
    $actionIds = collect($section['actions'] ?? [])->pluck('id')->all();

    expect($actionIds)->toContain('edit')
        ->and($actionIds)->toContain('remove')
        ->and($actionIds)->toContain('archive')
        ->and($actionIds)->not->toContain('delete');
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
        ->and($source)->toContain('section.actions')
        ->and($source)->toContain('action.type')
        ->and($source)->not->toContain('window.');
});

it('17a. js crud section core source does not reference supplier package specific row fields', function (): void {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->not->toContain('supplier_name')
        ->and($source)->not->toContain('pack_quantity_display')
        ->and($source)->not->toContain('pack_uom_symbol')
        ->and($source)->not->toContain('supplier_sku')
        ->and($source)->not->toContain('current_price_display');
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
        ->and($source)->toContain('h-10 w-10')
        ->and($source)->not->toContain('rounded-full border border-gray-200')
        ->and($source)->toContain('px-3 sm:px-6')
        ->and($source)->toContain('p-3 sm:p-4')
        ->and($source)->toContain('flex-col sm:flex-row');
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
        ->and($response->json('data.0.supplier_name'))->toBe('List Supplier')
        ->and($response->json('data.0.pack_quantity'))->toBe('8.500000')
        ->and($response->json('data.0.pack_uom_symbol'))->toBe('kg-msp-23')
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

it('26. represents archived packages consistently in the list payload', function (): void {
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

    expect($row['state'] ?? null)->toBe('archived')
        ->and($row['is_active'] ?? null)->toBeFalse()
        ->and($row['available_actions'] ?? [])->toContain('edit');
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
