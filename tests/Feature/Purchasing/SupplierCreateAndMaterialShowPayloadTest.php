<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->supplierCounter = 1;
    $this->itemCounter = 1;
    $this->uomCounter = 1;

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
            'email' => 'supplier-create-' . $this->userCounter . '@example.test',
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'supplier-create-role-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $categoryName = $attributes['category_name'] ?? 'Category ' . $this->uomCounter;
        unset($attributes['category_name']);

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $categoryName,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Unit ' . $this->uomCounter,
            'symbol' => $attributes['symbol'] ?? 'u' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Material ' . $this->itemCounter,
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
            'url' => null,
            'phone' => null,
            'email' => null,
            'currency_code' => null,
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
        return ItemPurchaseOption::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => 'SKU-' . $supplier->id . '-' . $item->id,
            'pack_quantity' => '1.000000',
            'pack_uom_id' => $uom->id,
            'is_active' => true,
        ], $attributes));
    };

    $this->postSupplier = function (?User $user, array $payload): TestResponse {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->postJson(route('purchasing.suppliers.store'), $payload);
    };

    $this->getMaterialShow = function (User $user, Item $item): TestResponse {
        return $this->actingAs($user)->get(route('materials.show', $item));
    };

    $this->extractPayload = function (TestResponse $response, string $payloadId): array {
        $page = $response->viewData('page') ?? null;

        if (is_array($page) && isset($page['props']['payload']) && is_array($page['props']['payload'])) {
            return $page['props']['payload'];
        }

        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };
});

it('1. user with purchasing-suppliers-manage can create a supplier via post purchasing suppliers', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postSupplier)($user, [
        'company_name' => 'Inline Supplier',
    ])->assertCreated();
});

it('2. user without purchasing-suppliers-manage is rejected with 403', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->postSupplier)($user, [
        'company_name' => 'Forbidden Supplier',
    ])->assertForbidden();
});

it('3. company_name is required', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postSupplier)($user, [
        'company_name' => '',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['company_name']);
});

it('4. url phone email and currency_code are nullable and a supplier can be created without them', function (): void {
    $tenant = ($this->makeTenant)(['currency_code' => 'CAD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postSupplier)($user, [
        'company_name' => 'Nullable Supplier',
    ])->assertCreated();

    $supplierId = $response->json('data.id');
    $supplier = Supplier::withoutGlobalScopes()->findOrFail($supplierId);

    expect($supplier->url)->toBeNull()
        ->and($supplier->phone)->toBeNull()
        ->and($supplier->email)->toBeNull()
        ->and($supplier->currency_code)->toBeNull();
});

it('5. the created supplier is scoped to the authenticated users tenant', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postSupplier)($user, [
        'company_name' => 'Tenant Scoped Supplier',
    ])->assertCreated();

    $supplierId = $response->json('data.id');
    $supplier = Supplier::withoutGlobalScopes()->findOrFail($supplierId);

    expect($supplier->tenant_id)->toBe($tenant->id)
        ->and($supplier->tenant_id)->not->toBe($otherTenant->id);
});

it('6. the response includes id and company_name', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postSupplier)($user, [
        'company_name' => 'Response Supplier',
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'company_name',
            ],
        ])
        ->assertJsonPath('data.company_name', 'Response Supplier');
});

it('7. the supplier list in the materials show page payload is scoped to the items active purchase options', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $includedSupplier = ($this->makeSupplier)($tenant, ['company_name' => 'Included Supplier']);
    $inactiveSupplier = ($this->makeSupplier)($tenant, ['company_name' => 'Inactive Supplier']);
    $otherItemSupplier = ($this->makeSupplier)($tenant, ['company_name' => 'Other Item Supplier']);

    ($this->makeOption)($tenant, $includedSupplier, $item, $uom, ['is_active' => true]);
    ($this->makeOption)($tenant, $inactiveSupplier, $item, $uom, ['is_active' => false]);

    $otherItem = ($this->makeItem)($tenant, $uom, ['name' => 'Other Material']);
    ($this->makeOption)($tenant, $otherItemSupplier, $otherItem, $uom, ['is_active' => true]);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    $payload = ($this->extractPayload)(
        ($this->getMaterialShow)($user, $item)->assertOk(),
        'materials-show-payload'
    );

    expect($payload['purchaseOrderCreate']['suppliers'] ?? [])->toBe([
            [
                'id' => $includedSupplier->id,
                'name' => 'Included Supplier',
            ],
        ]);
});

it('8. the supplier package supplier field exposes inline supplier create config while the draft po supplier list is deduplicated by supplier id', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $supplier = ($this->makeSupplier)($tenant, ['company_name' => 'Deduplicated Supplier']);

    ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'DUP-1']);
    ($this->makeOption)($tenant, $supplier, $item, $uom, ['supplier_sku' => 'DUP-2']);

    ($this->grantPermission)($user, 'inventory-materials-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $payload = ($this->extractPayload)(
        ($this->getMaterialShow)($user, $item)->assertOk(),
        'materials-show-payload'
    );

    $supplierField = collect($payload['sections']['supplierPackages']['fields'] ?? [])->firstWhere('name', 'supplier_id');

    expect($supplierField['type'] ?? null)->toBe('combobox')
        ->and($supplierField['inlineCreate']['storeUrl'] ?? null)->toBe(route('purchasing.suppliers.store'))
        ->and(collect($supplierField['inlineCreate']['fields'] ?? [])->pluck('name')->all())->toBe([
            'company_name',
            'email',
            'phone',
            'url',
        ])
        ->and($payload['purchaseOrderCreate']['suppliers'] ?? [])->toHaveCount(1)
        ->and($payload['purchaseOrderCreate']['suppliers'][0] ?? null)->toBe([
            'id' => $supplier->id,
            'name' => 'Deduplicated Supplier',
        ]);
});
