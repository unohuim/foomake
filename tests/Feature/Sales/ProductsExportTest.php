<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ExternalProductSourceConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Products Export Tenant ' . $this->tenantCounter,
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
            'name' => 'products-export-role-' . $this->roleCounter,
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

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $symbol = (string) ($attributes['symbol'] ?? 'pex-' . $this->uomCounter);
        $categoryName = (string) ($attributes['category_name'] ?? 'Products Export Category ' . $this->uomCounter);

        $category = UomCategory::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'name' => $categoryName,
        ]);

        $uom = Uom::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'symbol' => $symbol,
            ],
            [
                'uom_category_id' => $category->id,
                'name' => (string) ($attributes['name'] ?? 'Products Export UoM ' . $this->uomCounter),
            ]
        );

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Products Export Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->exportProducts = function (?User $user = null, array $query = []): TestResponse {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('sales.products.export', $query));
    };

    $this->importProducts = function (User $user, array $payload): TestResponse {
        return $this->actingAs($user)->postJson(route('sales.products.import.store'), $payload);
    };

    $this->previewFileRows = function (User $user, array $rows): TestResponse {
        return $this->actingAs($user)->postJson(route('sales.products.import.preview'), [
            'source' => 'file-upload',
            'rows' => $rows,
        ]);
    };

    $this->csvRows = function (TestResponse $response): array {
        $content = $response->streamedContent();
        $lines = preg_split("/\\r\\n|\\n|\\r/", trim($content)) ?: [];

        return array_values(array_filter(array_map(static function (string $line): ?array {
            if ($line === '') {
                return null;
            }

            return str_getcsv($line);
        }, $lines)));
    };

    $this->csvHeader = function (TestResponse $response): array {
        $rows = ($this->csvRows)($response);

        return $rows[0] ?? [];
    };

    $this->csvDataRows = function (TestResponse $response): array {
        $rows = ($this->csvRows)($response);

        return array_slice($rows, 1);
    };

    $this->csvRecords = function (TestResponse $response): array {
        $rows = ($this->csvRows)($response);
        $header = $rows[0] ?? [];
        $dataRows = array_slice($rows, 1);

        return array_map(static fn (array $row): array => array_combine($header, $row), $dataRows);
    };

    $this->localPreviewRowsFromExport = function (TestResponse $response): array {
        $records = ($this->csvRecords)($response);

        return array_values(array_map(static function (array $record, int $index): array {
            $name = trim((string) ($record['name'] ?? ''));
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
            $slug = trim((string) $slug, '-');
            $slug = $slug === '' ? 'product' : $slug;
            $csvBooleanOrNull = static function (?string $value): ?bool {
                $normalized = strtolower(trim((string) $value));

                return match ($normalized) {
                    '1', 'true', 'yes' => true,
                    '0', 'false', 'no' => false,
                    '' => null,
                    default => null,
                };
            };

            return [
                'external_id' => $record['external_id'] !== '' ? $record['external_id'] : 'file-' . ($index + 1) . '-' . $slug,
                'external_source' => (string) ($record['external_source'] ?? ''),
                'name' => $name,
                'sku' => '',
                'price' => (string) ($record['default_price_amount'] ?? ''),
                'is_active' => in_array(strtolower((string) ($record['is_active'] ?? '')), ['1', 'true', 'yes'], true),
                'is_sellable' => true,
                'selected' => true,
                'base_uom_id' => (string) ($record['base_uom_id'] ?? ''),
                'is_manufacturable' => $csvBooleanOrNull($record['is_manufacturable'] ?? null),
                'is_purchasable' => $csvBooleanOrNull($record['is_purchasable'] ?? null),
                'has_manufacturable_override' => false,
                'has_purchasable_override' => false,
                'is_duplicate' => false,
                'duplicate_reason' => '',
            ];
        }, $records, array_keys($records)));
    };

    $this->payloadRowsFromPreviewSelection = function (
        array $previewRows,
        ?string $importSource = null,
        bool $bulkManufacturable = false,
        bool $bulkPurchasable = false
    ): array {
        return array_values(array_map(static function (array $row) use (
            $importSource,
            $bulkManufacturable,
            $bulkPurchasable
        ): array {
            return [
                'external_id' => $row['external_id'],
                'external_source' => $row['external_source'] ?: $importSource,
                'name' => $row['name'],
                'sku' => $row['sku'],
                'base_uom_id' => $row['base_uom_id'] === '' ? null : (int) $row['base_uom_id'],
                'is_active' => $row['is_active'],
                'is_sellable' => true,
                'is_manufacturable' => !empty($row['has_manufacturable_override'])
                    ? (bool) $row['is_manufacturable']
                    : ($bulkManufacturable ? true : (bool) $row['is_manufacturable']),
                'is_purchasable' => !empty($row['has_purchasable_override'])
                    ? (bool) $row['is_purchasable']
                    : ($bulkPurchasable ? true : (bool) $row['is_purchasable']),
            ];
        }, array_values(array_filter($previewRows, static fn (array $row): bool => $row['selected'] === true))));
    };

    $this->connectWooCommerce = function (Tenant $tenant): ExternalProductSourceConnection {
        return ExternalProductSourceConnection::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            ],
            [
                'store_url' => 'https://store.example.test',
                'consumer_key' => 'ck_valid_readonly_key',
                'consumer_secret' => 'cs_valid_readonly_secret',
                'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
                'is_connected' => true,
                'connected_at' => now(),
                'last_verified_at' => now(),
                'last_error' => null,
            ]
        );
    };
});

it('1. products export route requires authentication', function () {
    ($this->exportProducts)()
        ->assertRedirect(route('login'));
});

it('2. products export route denies authenticated users without product permissions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->exportProducts)($user)
        ->assertForbidden();
});

it('3. same products view permission allows export', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'View Export Product']);

    ($this->exportProducts)($user)
        ->assertOk();
});

it('4. products manage permission also allows export', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->makeItem)($tenant, $uom, ['name' => 'Manage Export Product']);

    ($this->exportProducts)($user)
        ->assertOk();
});

it('5. export all records includes all sellable products for the tenant', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Export All Alpha']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Export All Beta']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, ['scope' => 'all'])
            ->assertOk()
    );

    expect(collect($records)->pluck('name')->all())
        ->toContain('Export All Alpha', 'Export All Beta');
});

it('6. export all records excludes non sellable items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Included Sellable', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Excluded Material', 'is_sellable' => false]);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, ['scope' => 'all'])
            ->assertOk()
    );

    expect(collect($records)->pluck('name')->all())
        ->toContain('Included Sellable')
        ->not->toContain('Excluded Material');
});

it('7. export current filters includes only matching products', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Filter Match Product']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Another Product']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'scope' => 'current',
            'search' => 'Filter Match',
        ])->assertOk()
    );

    expect($records)->toHaveCount(1)
        ->and($records[0]['name'])->toBe('Filter Match Product');
});

it('8. export current filters with no matches returns header only', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Present Product']);

    $response = ($this->exportProducts)($user, [
        'scope' => 'current',
        'search' => 'Missing Product',
    ])->assertOk();

    expect(($this->csvHeader)($response))->not->toBeEmpty()
        ->and(($this->csvDataRows)($response))->toHaveCount(0);
});

it('9. export current sort preserves name ascending order', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Zulu Product']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Alpha Product']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'scope' => 'current',
            'sort' => 'name',
            'direction' => 'asc',
        ])->assertOk()
    );

    expect(array_column($records, 'name'))->toBe([
        'Alpha Product',
        'Zulu Product',
    ]);
});

it('10. export current sort preserves name descending order', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Zulu Product']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Alpha Product']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'scope' => 'current',
            'sort' => 'name',
            'direction' => 'desc',
        ])->assertOk()
    );

    expect(array_column($records, 'name'))->toBe([
        'Zulu Product',
        'Alpha Product',
    ]);
});

it('11. export current sort preserves price ascending order', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Higher Price',
        'default_price_cents' => 2500,
        'default_price_currency_code' => 'USD',
    ]);
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Lower Price',
        'default_price_cents' => 500,
        'default_price_currency_code' => 'USD',
    ]);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'scope' => 'current',
            'sort' => 'price',
            'direction' => 'asc',
        ])->assertOk()
    );

    expect(array_column($records, 'name'))->toBe([
        'Lower Price',
        'Higher Price',
    ]);
});

it('12. export current sort preserves base uom ascending order', function () {
    $tenant = ($this->makeTenant)();
    $each = ($this->makeUom)($tenant, ['name' => 'Each', 'symbol' => 'ea']);
    $kilogram = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $kilogram, ['name' => 'Kilogram Product']);
    ($this->makeItem)($tenant, $each, ['name' => 'Each Product']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'scope' => 'current',
            'sort' => 'base_uom',
            'direction' => 'asc',
        ])->assertOk()
    );

    expect(array_column($records, 'name'))->toBe([
        'Each Product',
        'Kilogram Product',
    ]);
});

it('13. csv downloads with the correct content type', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom);

    ($this->exportProducts)($user)
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('14. csv downloads with the correct filename', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom);

    ($this->exportProducts)($user)
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=products-export.csv');
});

it('15. csv includes all import compatible headers', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom);

    $header = ($this->csvHeader)(
        ($this->exportProducts)($user)->assertOk()
    );

    expect($header)->toBe([
        'name',
        'base_uom_id',
        'is_active',
        'is_purchasable',
        'is_manufacturable',
        'default_price_amount',
        'default_price_currency_code',
        'external_source',
        'external_id',
    ]);
});

it('16. csv rows include all product export fields', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Row Export Product',
        'is_active' => false,
        'is_purchasable' => true,
        'is_manufacturable' => true,
        'default_price_cents' => 1299,
        'default_price_currency_code' => 'USD',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-row-1299',
    ]);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user)->assertOk()
    );

    expect($records[0])->toBe([
        'name' => 'Row Export Product',
        'base_uom_id' => (string) $item->base_uom_id,
        'is_active' => '0',
        'is_purchasable' => '1',
        'is_manufacturable' => '1',
        'default_price_amount' => '12.99',
        'default_price_currency_code' => 'USD',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-row-1299',
    ]);
});

it('17. csv leaves nullable export fields blank when product values are missing', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Blank Fields Product',
        'default_price_cents' => null,
        'default_price_currency_code' => null,
        'external_source' => null,
        'external_id' => null,
    ]);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user)->assertOk()
    );

    expect($records[0]['default_price_amount'])->toBe('')
        ->and($records[0]['default_price_currency_code'])->toBe('')
        ->and($records[0]['external_source'])->toBe('')
        ->and($records[0]['external_id'])->toBe('');
});

it('18. export does not leak other tenant records', function () {
    $tenant = ($this->makeTenant)('Current Tenant');
    $otherTenant = ($this->makeTenant)('Other Tenant');
    $tenantUom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $tenantUom, ['name' => 'Current Tenant Export']);
    ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Other Tenant Export']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, ['scope' => 'all'])->assertOk()
    );

    expect(array_column($records, 'name'))
        ->toContain('Current Tenant Export')
        ->not->toContain('Other Tenant Export');
});

it('19. export all records ignores current search filters', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Scope All Alpha']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Scope All Beta']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'scope' => 'all',
            'search' => 'Alpha',
        ])->assertOk()
    );

    expect(array_column($records, 'name'))
        ->toContain('Scope All Alpha', 'Scope All Beta');
});

it('20. export defaults to current filtered scope when no scope is provided', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Default Scope Match']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Default Scope Other']);

    $records = ($this->csvRecords)(
        ($this->exportProducts)($user, [
            'search' => 'Match',
        ])->assertOk()
    );

    expect($records)->toHaveCount(1)
        ->and($records[0]['name'])->toBe('Default Scope Match');
});

it('21. exported csv can be previewed as local file rows', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Previewable Export Product',
        'is_purchasable' => true,
    ]);

    $previewRows = ($this->localPreviewRowsFromExport)(
        ($this->exportProducts)($user)->assertOk()
    );

    expect($previewRows)->toHaveCount(1)
        ->and($previewRows[0]['name'])->toBe('Previewable Export Product')
        ->and($previewRows[0]['base_uom_id'])->toBe((string) $uom->id);
});

it('22. non duplicate previewed csv row is selected by default for local file import', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Selected Preview Product',
    ]);

    $previewRows = ($this->localPreviewRowsFromExport)(
        ($this->exportProducts)($user)->assertOk()
    );

    expect($previewRows[0]['selected'])->toBeTrue()
        ->and($previewRows[0]['external_id'])->toStartWith('file-1-selected-preview-product');
});

it('22a. file preview preserves external source from the file', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    ($this->previewFileRows)($user, [[
        'external_id' => 'csv-1001',
        'external_source' => 'woocommerce',
        'name' => 'CSV Source Product',
        'sku' => 'CSV-1001',
        'base_uom_id' => null,
        'is_active' => true,
        'is_sellable' => true,
        'is_manufacturable' => false,
        'is_purchasable' => false,
    ]])->assertOk()
        ->assertJsonPath('data.source', 'file-upload')
        ->assertJsonPath('data.rows.0.external_source', 'woocommerce')
        ->assertJsonPath('data.rows.0.is_duplicate', false);
});

it('22b. file preview flags external source and external id duplicates', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Existing CSV Duplicate',
        'external_source' => 'woocommerce',
        'external_id' => 'dup-file-1001',
    ]);

    ($this->previewFileRows)($user, [[
        'external_id' => 'dup-file-1001',
        'external_source' => 'woocommerce',
        'name' => 'Incoming CSV Duplicate',
        'sku' => 'CSV-DUP-1001',
        'base_uom_id' => null,
        'is_active' => true,
        'is_sellable' => true,
        'is_manufacturable' => false,
        'is_purchasable' => false,
    ]])->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.0.duplicate_reason', 'A product with the same external source and external ID already exists.');
});

it('23. file preview then selected import succeeds after choosing a base uom', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $exportOwner = ($this->makeUser)($tenant);
    ($this->grantPermission)($exportOwner, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Importable CSV Product',
        'default_price_cents' => 1234,
        'default_price_currency_code' => 'USD',
    ]);

    $parsedRows = ($this->localPreviewRowsFromExport)(
        ($this->exportProducts)($exportOwner)->assertOk()
    );
    $previewRows = ($this->previewFileRows)($user, $parsedRows)
        ->assertOk()
        ->json('data.rows');

    $previewRows[0]['base_uom_id'] = (string) $uom->id;
    $payloadRows = ($this->payloadRowsFromPreviewSelection)($previewRows);

    ($this->importProducts)($user, [
        'is_local_file_import' => true,
        'create_fulfillment_recipes' => false,
        'rows' => $payloadRows,
    ])->assertCreated()
        ->assertJsonPath('data.imported_count', 1)
        ->assertJsonPath('data.imported.0.name', 'Importable CSV Product');

    expect(Item::query()
        ->where('tenant_id', $tenant->id)
        ->whereNull('external_source')
        ->where('external_id', $payloadRows[0]['external_id'])
        ->exists())->toBeTrue();
});

it('24. local file import validation errors expose field specific messages', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Invalid CSV Product',
    ]);

    $previewRows = ($this->previewFileRows)(
        $user,
        ($this->localPreviewRowsFromExport)(
            ($this->exportProducts)($user, ['scope' => 'all'])->assertOk()
        )
    )->assertOk()->json('data.rows');

    ($this->importProducts)($user, [
        'is_local_file_import' => true,
        'rows' => [
            [
                'external_id' => $previewRows[0]['external_id'],
                'name' => $previewRows[0]['name'],
                'sku' => '',
                'base_uom_id' => null,
                'is_active' => $previewRows[0]['is_active'],
                'is_sellable' => true,
                'is_manufacturable' => false,
                'is_purchasable' => false,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonValidationErrors(['rows.0.base_uom_id']);
});

it('25. local file import defaults persist is manufacturable when rows do not override it', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $exportOwner = ($this->makeUser)($tenant);
    ($this->grantPermission)($exportOwner, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Bulk Manufacturable Import Product',
        'is_manufacturable' => false,
        'external_source' => null,
        'external_id' => null,
    ]);

    $previewRows = ($this->previewFileRows)(
        $user,
        ($this->localPreviewRowsFromExport)(
            ($this->exportProducts)($exportOwner)->assertOk()
        )
    )->assertOk()->json('data.rows');
    $previewRows[0]['is_manufacturable'] = null;

    ($this->importProducts)($user, [
        'is_local_file_import' => true,
        'import_all_as_manufacturable' => true,
        'create_fulfillment_recipes' => false,
        'rows' => ($this->payloadRowsFromPreviewSelection)($previewRows, null, true),
    ])->assertCreated();

    $item = Item::query()
        ->where('tenant_id', $tenant->id)
        ->whereNull('external_source')
        ->where('external_id', $previewRows[0]['external_id'])
        ->first();

    expect($item)->not->toBeNull()
        ->and($item?->is_manufacturable)->toBeTrue();
});

it('26. local file import does not require a woo source selection', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $exportOwner = ($this->makeUser)($tenant);
    ($this->grantPermission)($exportOwner, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Source Preserved Import Product',
        'external_source' => null,
        'external_id' => null,
    ]);

    $previewRows = ($this->previewFileRows)(
        $user,
        ($this->localPreviewRowsFromExport)(
            ($this->exportProducts)($exportOwner)->assertOk()
        )
    )->assertOk()->json('data.rows');

    ($this->importProducts)($user, [
        'is_local_file_import' => true,
        'create_fulfillment_recipes' => false,
        'rows' => ($this->payloadRowsFromPreviewSelection)($previewRows),
    ])->assertCreated();

    expect(Item::query()
        ->where('tenant_id', $tenant->id)
        ->whereNull('external_source')
        ->where('external_id', $previewRows[0]['external_id'])
        ->exists())->toBeTrue();
});

it('26a. import endpoint rejects file duplicates when submitted anyway', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Existing Duplicate Reject Product',
        'external_source' => 'woocommerce',
        'external_id' => 'dup-reject-1001',
    ]);

    ($this->importProducts)($user, [
        'is_local_file_import' => true,
        'rows' => [[
            'external_id' => 'dup-reject-1001',
            'external_source' => 'woocommerce',
            'name' => 'Duplicate Submitted Anyway',
            'sku' => 'DUP-REJECT-1001',
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'is_purchasable' => false,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['rows.0.external_id']);
});

it('27. woo import rows still import successfully through the existing contract', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWooCommerce)($tenant);

    ($this->importProducts)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-regression-1001',
            'external_source' => 'woocommerce',
            'name' => 'Woo Regression Product',
            'sku' => 'WOO-REG-1001',
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'is_purchasable' => false,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.imported.0.external_source', 'woocommerce');
});
