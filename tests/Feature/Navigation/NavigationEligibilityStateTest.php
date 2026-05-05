<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Navigation\NavigationEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (string $name = null): Tenant {
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

        $role = Role::query()->firstOrCreate([
            'name' => 'navigation-eligibility-' . $slug,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Nav Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Nav UoM ' . $this->uomCounter,
            'symbol' => 'nav-uom-' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeCustomer = function (Tenant $tenant, array $attributes = []): Customer {
        return Customer::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $tenant->id . '-' . $this->tenantCounter,
            'status' => Customer::STATUS_ACTIVE,
        ], $attributes));
    };

    $this->makeSupplier = function (Tenant $tenant, array $attributes = []): Supplier {
        return Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $tenant->id . '-' . $this->tenantCounter,
        ], $attributes));
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeRecipe = function (Tenant $tenant, Item $item, array $attributes = []): Recipe {
        return Recipe::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'name' => 'Recipe ' . $item->id,
            'output_quantity' => '1.000000',
            'is_active' => true,
            'is_default' => false,
        ], $attributes));
    };

    $this->navigationEligibility = function (?int $tenantId): array {
        return app(NavigationEligibility::class)->forTenantId($tenantId);
    };
});

it('1. enables sales orders when a customer and a sellable item exist', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeCustomer)($tenant);
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeTrue();
});

it('2. disables sales orders when no customer exists', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeFalse();
});

it('3. disables sales orders when no sellable item exists', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeCustomer)($tenant);
    ($this->makeItem)($tenant, $uom);

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeFalse();
});

it('4. disables sales orders when neither prerequisite exists', function () {
    $tenant = ($this->makeTenant)();

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeFalse();
});

it('5. turns sales orders false again after the only customer is deleted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $customer = ($this->makeCustomer)($tenant);

    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeTrue();

    $customer->delete();

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeFalse();
});

it('6. turns sales orders false again after the only sellable item is changed off or deleted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeCustomer)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeTrue();

    $item->update(['is_sellable' => false]);

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeFalse();

    $item->delete();

    expect(($this->navigationEligibility)($tenant->id)['salesOrdersEnabled'])->toBeFalse();
});

it('7. enables purchase orders when a supplier and a purchasable item exist', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeSupplier)($tenant);
    ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeTrue();
});

it('8. disables purchase orders when no supplier exists', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeFalse();
});

it('9. disables purchase orders when no purchasable item exists', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeSupplier)($tenant);
    ($this->makeItem)($tenant, $uom);

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeFalse();
});

it('10. disables purchase orders when neither prerequisite exists', function () {
    $tenant = ($this->makeTenant)();

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeFalse();
});

it('11. turns purchase orders false again after the only supplier is deleted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $supplier = ($this->makeSupplier)($tenant);

    ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeTrue();

    $supplier->delete();

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeFalse();
});

it('12. turns purchase orders false again after the only purchasable item is changed off or deleted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeSupplier)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeTrue();

    $item->update(['is_purchasable' => false]);

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeFalse();

    $item->delete();

    expect(($this->navigationEligibility)($tenant->id)['purchaseOrdersEnabled'])->toBeFalse();
});

it('13. enables make orders when a manufacturable item and an active recipe exist', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->makeRecipe)($tenant, $item, ['is_active' => true]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeTrue();
});

it('14. disables make orders when no manufacturable item exists', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->makeRecipe)($tenant, $item, ['is_active' => true]);
    $item->update(['is_manufacturable' => false]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();
});

it('15. disables make orders when no active recipe exists', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();
});

it('16. disables make orders when only inactive recipes exist', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->makeRecipe)($tenant, $item, ['is_active' => false]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();
});

it('17. turns make orders false again after the only manufacturable item is changed off or deleted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->makeRecipe)($tenant, $item, ['is_active' => true]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeTrue();

    $item->update(['is_manufacturable' => false]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();

    $item->delete();

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();
});

it('18. turns make orders false again after the only active recipe is deactivated or deleted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);
    $recipe = ($this->makeRecipe)($tenant, $item, ['is_active' => true]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeTrue();

    $recipe->update(['is_active' => false]);

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();

    $recipe->delete();

    expect(($this->navigationEligibility)($tenant->id)['makeOrdersEnabled'])->toBeFalse();
});

it('19. is tenant scoped and ignores another tenants prerequisite records', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $otherTenant = ($this->makeTenant)('Tenant B');
    $uom = ($this->makeUom)($otherTenant);
    $otherItem = ($this->makeItem)($otherTenant, $uom, [
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->makeCustomer)($otherTenant);
    ($this->makeSupplier)($otherTenant);
    ($this->makeRecipe)($otherTenant, $otherItem, ['is_active' => true]);

    expect(($this->navigationEligibility)($tenant->id))->toBe([
        'salesOrdersEnabled' => false,
        'purchaseOrdersEnabled' => false,
        'makeOrdersEnabled' => false,
    ]);
});

it('20. returns all three booleans together in one contract', function () {
    $tenant = ($this->makeTenant)();

    expect(($this->navigationEligibility)($tenant->id))->toBe([
        'salesOrdersEnabled' => false,
        'purchaseOrdersEnabled' => false,
        'makeOrdersEnabled' => false,
    ]);
});

it('21. GET navigation state requires authentication', function () {
    $this->getJson('/navigation/state')->assertUnauthorized();
});

it('22. GET navigation state returns JSON', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $response = $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk();

    expect((string) $response->headers->get('content-type'))->toContain('application/json');
});

it('23. GET navigation state includes sales orders enabled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertJsonStructure([
            'salesOrdersEnabled',
        ]);
});

it('24. GET navigation state includes purchase orders enabled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertJsonStructure([
            'purchaseOrdersEnabled',
        ]);
});

it('25. GET navigation state includes make orders enabled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertJsonStructure([
            'makeOrdersEnabled',
        ]);
});

it('26. endpoint returns true values when the current tenant has prerequisites', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->makeCustomer)($tenant);
    ($this->makeSupplier)($tenant);
    ($this->makeRecipe)($tenant, $item, ['is_active' => true]);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertExactJson([
            'salesOrdersEnabled' => true,
            'purchaseOrdersEnabled' => true,
            'makeOrdersEnabled' => true,
        ]);
});

it('27. endpoint returns false values when the current tenant lacks prerequisites', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertExactJson([
            'salesOrdersEnabled' => false,
            'purchaseOrdersEnabled' => false,
            'makeOrdersEnabled' => false,
        ]);
});

it('28. endpoint does not leak eligibility from another tenant', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $otherTenant = ($this->makeTenant)('Tenant B');
    $uom = ($this->makeUom)($otherTenant);
    $item = ($this->makeItem)($otherTenant, $uom, [
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->makeCustomer)($otherTenant);
    ($this->makeSupplier)($otherTenant);
    ($this->makeRecipe)($otherTenant, $item, ['is_active' => true]);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertExactJson([
            'salesOrdersEnabled' => false,
            'purchaseOrdersEnabled' => false,
            'makeOrdersEnabled' => false,
        ]);
});

it('29. endpoint uses the same service contract as blade nav rendering', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->makeCustomer)($tenant);
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    $expected = app(NavigationEligibility::class)->forTenantId($tenant->id);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('data-sales-orders-nav-link="desktop"', false);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertExactJson($expected);
});

it('30. endpoint response remains stable even when all prerequisites are missing', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->getJson('/navigation/state')
        ->assertOk()
        ->assertJsonStructure([
            'salesOrdersEnabled',
            'purchaseOrdersEnabled',
            'makeOrdersEnabled',
        ])
        ->assertExactJson([
            'salesOrdersEnabled' => false,
            'purchaseOrdersEnabled' => false,
            'makeOrdersEnabled' => false,
        ]);
});
