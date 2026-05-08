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
            'name' => 'sales-products-list-role-' . $this->roleCounter,
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
        $symbol = (string) ($attributes['symbol'] ?? 'plist-' . $this->uomCounter);
        $categoryName = (string) ($attributes['category_name'] ?? 'Products List Category ' . $this->uomCounter);

        $existing = Uom::query()
            ->where('tenant_id', $tenant->id)
            ->where('symbol', $symbol)
            ->first();

        if ($existing) {
            return $existing;
        }

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
                'name' => (string) ($attributes['name'] ?? 'Products List UoM ' . $this->uomCounter),
            ]
        );

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Products List Item ' . $this->itemCounter,
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

    $this->listProducts = function (?User $user = null, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('sales.products.list', $query));
    };
});

it('1. unauthenticated request returns 401 json', function () {
    ($this->listProducts)()
        ->assertUnauthorized();
});

it('2. unauthorized request returns 403 json', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->listProducts)($user)
        ->assertForbidden();
});

it('3. authorized user with view permission can fetch products', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->listProducts)($user)
        ->assertOk();
});

it('4. authorized user with manage permission can fetch products', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->listProducts)($user)
        ->assertOk();
});

it('5. only sellable items are returned', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    $sellable = ($this->makeItem)($tenant, $uom, ['name' => 'Sellable Product', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Not Sellable', 'is_sellable' => false]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.id', $sellable->id);
});

it('6. non sellable materials are excluded', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Excluded Material', 'is_sellable' => false]);

    $response = ($this->listProducts)($user)->assertOk()->json();

    expect(collect($response['data'] ?? [])->pluck('name'))->not->toContain('Excluded Material');
});

it('7. tenant isolation is enforced', function () {
    $tenant = ($this->makeTenant)('Current Tenant');
    $otherTenant = ($this->makeTenant)('Other Tenant');
    $tenantUom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $tenantUom, ['name' => 'Current Tenant Product', 'is_sellable' => true]);
    ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Other Tenant Product', 'is_sellable' => true]);

    $response = ($this->listProducts)($user)->assertOk()->json();

    expect(collect($response['data'] ?? [])->pluck('name'))
        ->toContain('Current Tenant Product')
        ->not->toContain('Other Tenant Product');
});

it('8. response includes id', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    $item = ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.id', $item->id);
});

it('9. response includes name', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Named Product', 'is_sellable' => true]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Named Product');
});

it('10. response includes base uom', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant, ['name' => 'Each', 'symbol' => 'ea']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.base_uom.name', 'Each')
        ->assertJsonPath('data.0.base_uom.symbol', 'ea');
});

it('11. response includes price', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'is_sellable' => true,
        'default_price_cents' => 1234,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.price', '12.34');
});

it('12. response includes currency', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'is_sellable' => true,
        'default_price_cents' => 2500,
        'default_price_currency_code' => 'CAD',
    ]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.currency', 'CAD');
});

it('13. response includes image url', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonPath('data.0.image_url', null);
});

it('14. search filters products', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Apple Jam', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Berry Syrup', 'is_sellable' => true]);

    $response = ($this->listProducts)($user, ['search' => 'apple'])->assertOk()->json();

    expect(collect($response['data'] ?? [])->pluck('name'))
        ->toContain('Apple Jam')
        ->not->toContain('Berry Syrup');
});

it('15. empty search returns the normal result set', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Apple Jam', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Berry Syrup', 'is_sellable' => true]);

    $response = ($this->listProducts)($user, ['search' => ''])->assertOk()->json();

    expect($response['meta']['total'] ?? null)->toBe(2);
});

it('16. sorting works by name', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Alpha', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Zulu', 'is_sellable' => true]);

    ($this->listProducts)($user, ['sort' => 'name', 'direction' => 'asc'])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha')
        ->assertJsonPath('data.1.name', 'Zulu');
});

it('17. sorting works by price', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Budget Product',
        'is_sellable' => true,
        'default_price_cents' => 100,
        'default_price_currency_code' => 'USD',
    ]);
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Premium Product',
        'is_sellable' => true,
        'default_price_cents' => 500,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->listProducts)($user, ['sort' => 'price', 'direction' => 'asc'])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Budget Product')
        ->assertJsonPath('data.1.name', 'Premium Product');
});

it('17a. search works after sorting by base uom', function () {
    $tenant = ($this->makeTenant)();
    $each = ($this->makeUom)($tenant, ['name' => 'Each', 'symbol' => 'ea']);
    $kilogram = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $each, ['name' => 'Apple Jam', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $kilogram, ['name' => 'Berry Syrup', 'is_sellable' => true]);

    $response = ($this->listProducts)($user, [
        'search' => 'apple',
        'sort' => 'base_uom',
        'direction' => 'desc',
    ])->assertOk()->json();

    expect(collect($response['data'] ?? [])->pluck('name'))
        ->toContain('Apple Jam')
        ->not->toContain('Berry Syrup')
        ->and($response['meta']['sort']['column'] ?? null)->toBe('base_uom')
        ->and($response['meta']['sort']['direction'] ?? null)->toBe('desc')
        ->and($response['meta']['search'] ?? null)->toBe('apple');
});

it('18. sorting starts desc by default', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Alpha', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Zulu', 'is_sellable' => true]);

    ($this->listProducts)($user, ['sort' => 'name'])
        ->assertOk()
        ->assertJsonPath('meta.sort.column', 'name')
        ->assertJsonPath('meta.sort.direction', 'desc')
        ->assertJsonPath('data.0.name', 'Zulu');
});

it('19. sorting toggles asc when requested', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['name' => 'Alpha', 'is_sellable' => true]);
    ($this->makeItem)($tenant, $uom, ['name' => 'Zulu', 'is_sellable' => true]);

    ($this->listProducts)($user, ['sort' => 'name', 'direction' => 'asc'])
        ->assertOk()
        ->assertJsonPath('meta.sort.direction', 'asc')
        ->assertJsonPath('data.0.name', 'Alpha');
});

it('20. only allowed sort columns are accepted', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    $response = ($this->listProducts)($user, ['sort' => 'name'])->assertOk()->json();

    expect($response['meta']['allowed_sort_columns'] ?? [])->toContain('name', 'price');
});

it('21. invalid sort column returns 422 json', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->listProducts)($user, ['sort' => 'flags'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonValidationErrors(['sort']);
});

it('22. json response shape is stable and documented by assertions', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Stable Product',
        'is_sellable' => true,
        'default_price_cents' => 700,
        'default_price_currency_code' => 'USD',
    ]);

    $response = ($this->listProducts)($user)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'base_uom' => ['id', 'name', 'symbol'],
                    'price',
                    'currency',
                    'image_url',
                ],
            ],
            'meta' => [
                'search',
                'sort' => ['column', 'direction'],
                'allowed_sort_columns',
                'total',
            ],
        ])
        ->json();

    expect($response['meta']['total'] ?? null)->toBe(1)
        ->and($response['meta']['search'] ?? null)->toBe('')
        ->and($response['meta']['sort']['direction'] ?? null)->toBe('desc');
});
