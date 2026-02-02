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
            'supplier_sku' => $attributes['supplier_sku'] ?? null,
            'pack_quantity' => $attributes['pack_quantity'] ?? '5.000000',
            'pack_uom_id' => $uom->id,
        ], $attributes));
    };

    $this->postStore = function (User $user, Supplier $supplier, array $payload = []) {
        return $this->actingAs($user)
            ->postJson(route('purchasing.suppliers.purchase-options.store', $supplier), $payload);
    };

    $this->deleteOption = function (User $user, Supplier $supplier, ItemPurchaseOption $option) {
        return $this->actingAs($user)
            ->deleteJson(route('purchasing.suppliers.purchase-options.destroy', [$supplier, $option]));
    };

    $this->getSupplierShow = function (User $user, Supplier $supplier) {
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

    $this->assertStableErrors = function ($response): void {
        $response->assertJsonStructure([
            'errors' => [
                'item_id',
                'pack_quantity',
                'pack_uom_id',
                'supplier_sku',
            ],
        ]);

        expect($response->json('errors.item_id'))->toBeArray()
            ->and($response->json('errors.pack_quantity'))->toBeArray()
            ->and($response->json('errors.pack_uom_id'))->toBeArray()
            ->and($response->json('errors.supplier_sku'))->toBeArray();
    };
});

it('requires authentication to create a supplier package', function () {
    $tenant = ($this->makeTenant)();
    $supplier = ($this->makeSupplier)($tenant);

    $this->postJson(route('purchasing.suppliers.purchase-options.store', $supplier), [])
        ->assertUnauthorized();
});

it('requires authentication to delete a supplier package', function () {
    $tenant = ($this->makeTenant)();
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    $this->deleteJson(route('purchasing.suppliers.purchase-options.destroy', [$supplier, $option]))
        ->assertUnauthorized();
});

it('forbids package creation without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'SKU-1',
    ])->assertForbidden();
});

it('forbids package deletion without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->deleteOption)($user, $supplier, $option)
        ->assertForbidden();
});

it('returns not found when creating a package for another tenant supplier', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($otherTenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertNotFound();
});

it('validates item_id required when creating a package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);

    $this->assertDatabaseCount('item_purchase_options', 0);
    ($this->assertStableErrors)($response);
});

it('validates item_id exists when creating a package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => 99999,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);

    ($this->assertStableErrors)($response);
});

it('validates item_id belongs to tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->makeItem)($otherTenant, $otherUom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $otherItem->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);

    ($this->assertStableErrors)($response);
});

it('validates pack_quantity required when creating a package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_uom_id' => $uom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_quantity']);

    ($this->assertStableErrors)($response);
});

it('validates pack_quantity must be greater than zero', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '0',
        'pack_uom_id' => $uom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_quantity']);

    ($this->assertStableErrors)($response);
});

it('validates pack_quantity numeric format', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => 'not-a-number',
        'pack_uom_id' => $uom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_quantity']);

    ($this->assertStableErrors)($response);
});

it('accepts pack_quantity at minimum precision boundary', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '0.000001',
        'pack_uom_id' => $uom->id,
    ])->assertCreated();
});

it('accepts pack_quantity at upper precision boundary', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '999999999999.999999',
        'pack_uom_id' => $uom->id,
    ])->assertCreated();
});

it('validates pack_uom_id required when creating a package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_uom_id']);

    ($this->assertStableErrors)($response);
});

it('validates pack_uom_id exists when creating a package', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => 99999,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_uom_id']);

    ($this->assertStableErrors)($response);
});

it('validates pack_uom_id belongs to tenant', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, ($this->makeUom)($tenant));
    $otherUom = ($this->makeUom)($otherTenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $otherUom->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['pack_uom_id']);

    ($this->assertStableErrors)($response);
});

it('creates a supplier package and returns JSON values', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '5.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'SKU-1',
    ])->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.supplier_id', $supplier->id)
        ->assertJsonPath('data.pack_quantity', '5.000000')
        ->assertJsonPath('data.pack_uom_id', $uom->id)
        ->assertJsonPath('data.supplier_sku', 'SKU-1');
});

it('persists created packages with tenant and supplier ids', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '6.000000',
        'pack_uom_id' => $uom->id,
    ])->assertCreated();

    $optionId = $response->json('data.id');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $optionId,
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'item_id' => $item->id,
    ]);
});

it('ignores supplier_id from payload and uses route supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '6.000000',
        'pack_uom_id' => $uom->id,
        'supplier_id' => $otherSupplier->id,
    ])->assertCreated();

    $optionId = $response->json('data.id');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $optionId,
        'supplier_id' => $supplier->id,
    ]);
});

it('allows supplier_sku to be null', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '6.000000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => null,
    ])->assertCreated();

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $response->json('data.id'),
        'supplier_sku' => null,
    ]);
});

it('deletes a supplier package and returns JSON', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->deleteOption)($user, $supplier, $option)
        ->assertOk()
        ->assertJsonPath('message', 'Deleted.');

    $this->assertDatabaseMissing('item_purchase_options', [
        'id' => $option->id,
    ]);

    $response = ($this->getSupplierShow)($user, $supplier)
        ->assertOk();

    $payload = ($this->extractSupplierPayload)($response);
    expect(collect($payload['packages'] ?? [])->pluck('id')->all())
        ->not()
        ->toContain($option->id);
});

it('returns not found when deleting another tenant package', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($otherTenant);
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom);
    $option = ($this->makeOption)($otherTenant, $otherSupplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteOption)($user, $supplier, $option)
        ->assertNotFound();

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
    ]);
});

it('returns not found when package does not belong to supplier', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $otherSupplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $otherSupplier, $item, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteOption)($user, $supplier, $option)
        ->assertNotFound();

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $option->id,
    ]);
});

it('end-to-end: creates a package then shows it on supplier detail', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->postStore)($user, $supplier, [
        'item_id' => $item->id,
        'pack_quantity' => '2.500000',
        'pack_uom_id' => $uom->id,
        'supplier_sku' => 'SKU-2',
    ])->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.pack_quantity', '2.500000')
        ->assertJsonPath('data.pack_uom_id', $uom->id);

    $optionId = $response->json('data.id');

    $this->assertDatabaseHas('item_purchase_options', [
        'id' => $optionId,
        'supplier_id' => $supplier->id,
        'item_id' => $item->id,
    ]);

    ($this->getSupplierShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"id":' . $optionId, false);
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

    $first = ($this->postStore)($user, $supplier, $payload)->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.pack_quantity', '5.000000')
        ->assertJsonPath('data.pack_uom_id', $uom->id);
    $second = ($this->postStore)($user, $supplier, $payload)->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.pack_quantity', '5.000000')
        ->assertJsonPath('data.pack_uom_id', $uom->id);

    $this->assertDatabaseCount('item_purchase_options', 2);

    ($this->getSupplierShow)($user, $supplier)
        ->assertOk()
        ->assertSee('"id":' . $first->json('data.id'), false)
        ->assertSee('"id":' . $second->json('data.id'), false);

    expect($first->json('data.id'))->not()->toBe($second->json('data.id'));
});
