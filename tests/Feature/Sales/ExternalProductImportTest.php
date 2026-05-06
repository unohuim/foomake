<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'external-products-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Import Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Import UoM ' . $this->uomCounter,
            'symbol' => 'imp-' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Import Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->connectSource = function (User $user, string $source = 'woocommerce') {
        return $this->actingAs($user)->postJson(route('sales.products.import.connect', $source), [
            'connection_label' => 'Prep Stub Connection',
        ]);
    };

    $this->previewSource = function (User $user, string $source = 'woocommerce', array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.products.import.preview'), array_merge([
            'source' => $source,
        ], $payload));
    };

    $this->importRows = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('sales.products.import.store'), $payload);
    };
});

it('1. rejects unauthenticated users from the connect endpoint', function () {
    $this->postJson(route('sales.products.import.connect', 'woocommerce'), [])
        ->assertUnauthorized();
});

it('2. rejects unauthenticated users from the preview endpoint', function () {
    $this->postJson(route('sales.products.import.preview'), [
        'source' => 'woocommerce',
    ])->assertUnauthorized();
});

it('3. rejects unauthenticated users from the import endpoint', function () {
    $this->postJson(route('sales.products.import.store'), [
        'source' => 'woocommerce',
        'rows' => [],
    ])->assertUnauthorized();
});

it('4. forbids authenticated users without product manage permission from the connect endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->connectSource)($user)->assertForbidden();
});

it('5. forbids authenticated users without product manage permission from the preview endpoint', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->previewSource)($user)->assertForbidden();
});

it('6. forbids authenticated users without product manage permission from the import endpoint before validation runs', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        // Intentionally invalid payload; auth must fail before validation.
    ])->assertForbidden();
});

it('7. rejects invalid connect sources', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $this->actingAs($user)
        ->postJson(route('sales.products.import.connect', 'magento'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});

it('8. stores minimal simulated connection state for a supported source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->connectSource)($user)
        ->assertCreated()
        ->assertJsonPath('data.source', 'woocommerce')
        ->assertJsonPath('data.is_connected', true);

    expect(DB::table('external_product_source_connections')
        ->where('tenant_id', $tenant->id)
        ->where('source', 'woocommerce')
        ->exists())->toBeTrue();
});

it('9. preview rejects invalid sources', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $this->actingAs($user)
        ->postJson(route('sales.products.import.preview'), [
            'source' => 'bigcommerce',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});

it('10. preview rejects unconnected sources with a connect-required state', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->previewSource)($user)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The selected source is not connected.')
        ->assertJsonPath('meta.connect_required', true);
});

it('11. preview returns deterministic importable rows for a connected source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    $response = ($this->previewSource)($user)
        ->assertOk()
        ->assertJsonPath('data.source', 'woocommerce')
        ->assertJsonPath('data.is_connected', true)
        ->assertJsonCount(3, 'data.rows');

    expect($response->json('data.rows.0.external_id'))->toBe('woo-stub-1001')
        ->and($response->json('data.rows.1.external_id'))->toBe('woo-stub-1002')
        ->and($response->json('data.rows.2.external_id'))->toBe('woo-stub-1003');
});

it('12. preview rows include the importable product shape needed by the slide-over', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    $response = ($this->previewSource)($user)->assertOk();
    $row = $response->json('data.rows.0');

    expect($row)->toMatchArray([
        'external_id' => 'woo-stub-1001',
        'sku' => 'WC-STUB-1001',
        'name' => 'Woo Stub Product 1001',
        'is_active' => true,
        'is_sellable' => true,
        'is_manufacturable' => false,
        'is_purchasable' => false,
    ]);
});

it('13. import rejects invalid sources', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->importRows)($user, [
        'source' => 'magento',
        'rows' => [[
            'external_id' => 'bad-1',
            'name' => 'Bad Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});

it('14. import rejects unconnected sources', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-stub-9001',
            'name' => 'Unconnected Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertUnprocessable()
        ->assertJsonPath('meta.connect_required', true);
});

it('15. import rejects malformed external product payloads', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '',
            'name' => '',
            'base_uom_id' => 999999,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'rows.0.external_id',
            'rows.0.name',
            'rows.0.base_uom_id',
        ]);
});

it('16. import returns stable JSON validation errors for ajax consumers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    $response = ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => '',
            'name' => '',
        ]],
    ])->assertUnprocessable();

    $response->assertJsonStructure([
        'message',
        'errors' => [
            'rows.0.external_id',
            'rows.0.name',
            'rows.0.base_uom_id',
        ],
    ]);
});

it('16a. authorized import requests with missing rows return 422', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['rows']);
});

it('16b. authorized import requests with empty rows return 422', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['rows']);
});

it('17. import creates a normal items row for each imported product', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1001',
            'name' => 'Imported Product 1001',
            'sku' => 'IMP-1001',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('name', 'Imported Product 1001')->exists())->toBeTrue();
});

it('18. import stores external_source on the created item', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1002',
            'name' => 'Imported Product 1002',
            'sku' => 'IMP-1002',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', 'woo-import-1002')->value('external_source'))
        ->toBe('woocommerce');
});

it('19. import stores external_id on the created item', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1003',
            'name' => 'Imported Product 1003',
            'sku' => 'IMP-1003',
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('name', 'Imported Product 1003')->value('external_id'))
        ->toBe('woo-import-1003');
});

it('20. ecommerce imports always set is_sellable to true', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1004',
            'name' => 'Imported Product 1004',
            'sku' => 'IMP-1004',
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_sellable' => false,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', 'woo-import-1004')->value('is_sellable'))
        ->toBeTrue();
});

it('21. import stores active status separately from sellable status', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1005',
            'name' => 'Inactive But Sellable Product',
            'sku' => 'IMP-1005',
            'base_uom_id' => $uom->id,
            'is_active' => false,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-import-1005')->firstOrFail();

    expect($item->is_active)->toBeFalse()
        ->and($item->is_sellable)->toBeTrue();
});

it('22. bulk manufacturable option sets manufacturable on all selected rows', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'import_all_as_manufacturable' => true,
        'rows' => [
            [
                'external_id' => 'woo-import-1006',
                'name' => 'Bulk Make 1',
                'sku' => 'IMP-1006',
                'base_uom_id' => $uom->id,
            ],
            [
                'external_id' => 'woo-import-1007',
                'name' => 'Bulk Make 2',
                'sku' => 'IMP-1007',
                'base_uom_id' => $uom->id,
            ],
        ],
    ])->assertCreated();

    expect(Item::query()->whereIn('external_id', ['woo-import-1006', 'woo-import-1007'])->where('is_manufacturable', true)->count())
        ->toBe(2);
});

it('23. bulk purchasable option sets purchasable on all selected rows', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'import_all_as_purchasable' => true,
        'rows' => [
            [
                'external_id' => 'woo-import-1008',
                'name' => 'Bulk Buy 1',
                'sku' => 'IMP-1008',
                'base_uom_id' => $uom->id,
            ],
            [
                'external_id' => 'woo-import-1009',
                'name' => 'Bulk Buy 2',
                'sku' => 'IMP-1009',
                'base_uom_id' => $uom->id,
            ],
        ],
    ])->assertCreated();

    expect(Item::query()->whereIn('external_id', ['woo-import-1008', 'woo-import-1009'])->where('is_purchasable', true)->count())
        ->toBe(2);
});

it('24. users may leave imported rows sellable-only', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1010',
            'name' => 'Sellable Only Product',
            'sku' => 'IMP-1010',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-import-1010')->firstOrFail();

    expect($item->is_sellable)->toBeTrue()
        ->and($item->is_manufacturable)->toBeFalse()
        ->and($item->is_purchasable)->toBeFalse();
});

it('25. per-row overrides win over the bulk defaults when provided', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'import_all_as_manufacturable' => true,
        'import_all_as_purchasable' => true,
        'rows' => [[
            'external_id' => 'woo-import-1011',
            'name' => 'Row Override Product',
            'sku' => 'IMP-1011',
            'base_uom_id' => $uom->id,
            'is_manufacturable' => false,
            'is_purchasable' => false,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-import-1011')->firstOrFail();

    expect($item->is_manufacturable)->toBeFalse()
        ->and($item->is_purchasable)->toBeFalse()
        ->and($item->is_sellable)->toBeTrue();
});

it('26. same external id from different sources is allowed', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->connectSource)($user, 'woocommerce')->assertCreated();
    $this->actingAs($user)
        ->postJson(route('sales.products.import.connect', 'shopify'), [
            'connection_label' => 'Prep Shopify Stub',
        ])
        ->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'shared-2001',
            'name' => 'Woo Shared External Id',
            'sku' => 'WOO-2001',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    ($this->importRows)($user, [
        'source' => 'shopify',
        'rows' => [[
            'external_id' => 'shared-2001',
            'name' => 'Shopify Shared External Id',
            'sku' => 'SHOP-2001',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', 'shared-2001')->count())->toBe(2);
});

it('27. same source and external id across different tenants is allowed', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $otherTenant = ($this->makeTenant)('Tenant B');
    $tenantUom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($otherTenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->grantPermission)($otherUser, 'inventory-products-manage');

    ($this->connectSource)($user)->assertCreated();
    ($this->connectSource)($otherUser)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'shared-tenant-3001',
            'name' => 'Tenant A Shared Product',
            'sku' => 'TENANT-A-3001',
            'base_uom_id' => $tenantUom->id,
        ]],
    ])->assertCreated();

    ($this->importRows)($otherUser, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'shared-tenant-3001',
            'name' => 'Tenant B Shared Product',
            'sku' => 'TENANT-B-3001',
            'base_uom_id' => $otherUom->id,
        ]],
    ])->assertCreated();

    expect(Item::withoutGlobalScopes()
        ->where('external_source', 'woocommerce')
        ->where('external_id', 'shared-tenant-3001')
        ->count())->toBe(2);
});

it('28. duplicate source and external id within the same tenant is blocked deterministically', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'dup-4001',
            'name' => 'Original Product',
            'sku' => 'DUP-4001',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'dup-4001',
            'name' => 'Duplicate Product',
            'sku' => 'DUP-4001-B',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'One or more imported products already exist for this tenant and source.');
});

it('29. transaction failure rolls back the whole import with no partial items persisted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [
            [
                'external_id' => 'txn-5001',
                'name' => 'Txn Product A',
                'sku' => 'TXN-5001',
                'base_uom_id' => $uom->id,
            ],
            [
                'external_id' => 'txn-5001',
                'name' => 'Txn Product B',
                'sku' => 'TXN-5001-B',
                'base_uom_id' => $uom->id,
            ],
        ],
    ])->assertUnprocessable();

    expect(Item::query()->whereIn('name', ['Txn Product A', 'Txn Product B'])->count())->toBe(0);
});

it('30. imported products appear on the sales products index after import', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-manage', 'inventory-products-view']);
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1012',
            'name' => 'Visible Imported Sales Product',
            'sku' => 'IMP-1012',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Visible Imported Sales Product');
});

it('31. imported products appear on the manufacturing materials index after import', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-manage', 'inventory-materials-view']);
    ($this->connectSource)($user)->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-import-1013',
            'name' => 'Visible Imported Material Item',
            'sku' => 'IMP-1013',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $this->actingAs($user)
        ->get(route('materials.index'))
        ->assertOk()
        ->assertSee('Visible Imported Material Item');
});

it('32. protects the invariant that no separate products table is introduced for imports', function () {
    expect(Schema::hasTable('products'))->toBeFalse();
});
