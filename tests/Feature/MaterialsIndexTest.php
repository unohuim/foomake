<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Materials Tenant ' . $this->tenantCounter,
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
            'name' => 'materials-role-' . $this->roleCounter,
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
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Materials Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Materials UoM ' . $this->uomCounter,
            'symbol' => $attributes['symbol'] ?? 'mat-' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Material ' . $this->itemCounter,
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

    $this->createStockMove = function (Tenant $tenant, Item $item): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => '1.000000',
            'type' => 'receipt',
        ]);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($config) ? $config : [];
    };

    $this->getIndex = function (?User $user = null) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('materials.index'));
    };

    $this->getList = function (?User $user = null, array $query = []) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('materials.list', $query));
    };
});

it('1. redirects guests away from the materials index', function (): void {
    ($this->getIndex)()
        ->assertRedirect(route('login'));
});

it('2. forbids authenticated users without the materials view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->getIndex)($user)
        ->assertForbidden();
});

it('3. allows users with inventory-materials-view to access the materials index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('Materials');
});

it('4. renders the materials page mount contracts for the shared crud module', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('data-page="materials-index"', false)
        ->assertSee('data-payload="materials-index-payload"', false)
        ->assertSee('data-crud-config=', false)
        ->assertSee('data-crud-root', false);
});

it('5. payload exposes urls and page data needed by the materials page module without embedding records', function (): void {
    $tenant = ($this->makeTenant)();
    $tenant->currency_code = 'CAD';
    $tenant->save();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $response = ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('materials-index-payload', false);

    $payload = ($this->extractPayload)($response, 'materials-index-payload');

    expect($payload['storeUrl'] ?? null)->toBe(route('materials.store'))
        ->and($payload['navigationStateUrl'] ?? null)->toBe(route('navigation.state'))
        ->and($payload['tenantCurrency'] ?? null)->toBe('CAD')
        ->and($payload)->not->toHaveKey('items');

    expect(collect($payload['uoms'] ?? [])->pluck('id')->contains($uom->id))->toBeTrue();
});

it('6. crud config includes the materials list endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['list'] ?? null)->toBe(route('materials.list'));
});

it('7. crud config includes the materials create endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['create'] ?? null)->toBe(route('materials.store'));
});

it('8. crud config includes the materials update endpoint template', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['update'] ?? null)->toBe(url('/materials/{id}'));
});

it('9. crud config includes the materials delete endpoint template', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['delete'] ?? null)->toBe(url('/materials/{id}'));
});

it('10. crud config includes the materials detail redirect template', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['detailUrlTemplate'] ?? null)->toBe(url('/materials/{id}'));
});

it('11. crud config exposes the shared vertical dot action menu labels for managers', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['labels']['actionsAriaLabel'] ?? null)->toBe('Material actions')
        ->and($config['actions'] ?? null)->toBe([
            [
                'id' => 'edit',
                'label' => 'Edit',
                'tone' => 'default',
            ],
            [
                'id' => 'delete',
                'label' => 'Delete',
                'tone' => 'warning',
            ],
        ]);
});

it('12. crud config hides edit and delete actions for view-only users', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['actions'] ?? null)->toBe([]);
});

it('13. crud config shows create only for users with manage permission', function (): void {
    $tenant = ($this->makeTenant)();
    $manager = ($this->makeUser)($tenant);
    $viewer = ($this->makeUser)($tenant);

    ($this->grantPermissions)($manager, ['inventory-materials-view', 'inventory-materials-manage']);
    ($this->grantPermission)($viewer, 'inventory-materials-view');

    $managerConfig = ($this->extractCrudConfig)(($this->getIndex)($manager));
    $viewerConfig = ($this->extractCrudConfig)(($this->getIndex)($viewer));

    expect($managerConfig['permissions']['showCreate'] ?? null)->toBeTrue()
        ->and($viewerConfig['permissions']['showCreate'] ?? null)->toBeFalse();
});

it('14. list endpoint returns unauthenticated json status for guests', function (): void {
    ($this->getList)()
        ->assertUnauthorized();
});

it('15. list endpoint forbids authenticated users without materials view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->getList)($user)
        ->assertForbidden();
});

it('16. list endpoint allows users with materials view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->getList)($user)
        ->assertOk();
});

it('17. list endpoint returns the materials row data required by the shared crud renderer', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $symbol = 'kg-' . Str::lower(Str::random(6));
    $uom = ($this->makeUom)($tenant, [
        'name' => 'Kilogram',
        'symbol' => $symbol,
    ]);
    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Flour',
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => true,
        'default_price_cents' => 425,
        'default_price_currency_code' => 'USD',
    ]);

    ($this->createStockMove)($tenant, $item);
    ($this->grantPermission)($user, 'inventory-materials-view');

    $response = ($this->getList)($user)
        ->assertOk();

    $row = $response->json('data.0');

    expect($row)->toMatchArray([
        'id' => $item->id,
        'name' => 'Flour',
        'base_uom_name' => 'Kilogram',
        'base_uom_symbol' => $symbol,
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => true,
        'has_stock_moves' => true,
        'default_price_amount' => '4.25',
        'default_price_currency_code' => 'USD',
        'show_url' => route('materials.show', $item),
    ]);
});

it('18. list endpoint excludes cross tenant records from materials index data', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)('Other Materials Tenant');
    $user = ($this->makeUser)($tenant);
    $tenantUom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);

    ($this->makeItem)($tenant, $tenantUom, ['name' => 'Visible Material']);
    ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Hidden Material']);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $names = collect(($this->getList)($user)->json('data'))
        ->pluck('name')
        ->all();

    expect($names)->toContain('Visible Material')
        ->not->toContain('Hidden Material');
});

it('18a. materials create response returns the created record id needed for redirect behavior', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $response = $this->actingAs($user)->postJson(route('materials.store'), [
        'name' => 'Redirect Material',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertCreated();

    $createdId = $response->json('data.id');

    expect($createdId)->toBeInt()
        ->and($response->json('data.name'))->toBe('Redirect Material');
});

it('19. list endpoint search filters materials by name', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->makeItem)($tenant, $uom, ['name' => 'Alpha Flour']);
    ($this->makeItem)($tenant, $uom, ['name' => 'Beta Sugar']);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $response = ($this->getList)($user, ['search' => 'flour'])
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Alpha Flour']);
});

it('20. list endpoint returns allowed sortable columns metadata', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $response = ($this->getList)($user)
        ->assertOk();

    expect($response->json('meta.allowed_sort_columns'))->toBe(['name', 'base_uom']);
});

it('21. materials blade shell no longer contains page local list table or row action markup', function (): void {
    $viewSource = file_get_contents(resource_path('views/materials/index.blade.php'));

    expect($viewSource)->toContain('data-crud-root')
        ->and($viewSource)->not->toContain('<table class="min-w-full divide-y divide-gray-100">')
        ->and($viewSource)->not->toContain('x-for="item in items"')
        ->and($viewSource)->not->toContain('toggleActionMenu($event, item.id)');
});

it('22. materials page module uses the shared crud renderer and configured crud helper', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));

    expect($pageSource)->toContain('createGenericCrud(parseCrudConfig(rootEl))')
        ->and($pageSource)->toContain('mountCrudRenderer(crudRootEl, rendererConfig);')
        ->and($pageSource)->toContain('this.crud.fetchList({');
});

it('23. materials page module removes duplicate page local action menu state and methods', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));

    expect($pageSource)->not->toContain('actionMenuOpen')
        ->and($pageSource)->not->toContain('toggleActionMenu(')
        ->and($pageSource)->not->toContain('openEditFromActionMenu')
        ->and($pageSource)->not->toContain('openDeleteFromActionMenu');
});

it('24. shared crud helper source supports optional detail redirects using an id placeholder', function (): void {
    $configSource = file_get_contents(resource_path('js/lib/crud-config.js'));
    $crudSource = file_get_contents(resource_path('js/lib/generic-crud.js'));

    expect($configSource)->toContain('detailUrlTemplate')
        ->and($crudSource)->toContain('buildDetailUrl(record)')
        ->and($crudSource)->toContain("this.detailUrlTemplate.replace('{id}'");
});

it('25. materials page module redirects after create when the shared crud detail url template is present', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));

    expect($pageSource)->toContain('const redirectUrl = this.crud.buildDetailUrl(data?.data);')
        ->and($pageSource)->toContain('window.location.assign(redirectUrl);');
});

it('26. materials create validation handling does not redirect before success', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));
    $validationBlockStart = strpos($pageSource, 'onValidationError:');
    $successBlockStart = strpos($pageSource, 'onSuccess: async (data) => {');

    expect($validationBlockStart)->not->toBeFalse()
        ->and($successBlockStart)->not->toBeFalse();

    $validationBlock = substr($pageSource, $validationBlockStart, $successBlockStart - $validationBlockStart);

    expect($validationBlock)->not->toContain('window.location.assign');
});

it('27. materials create generic error handling does not redirect before success', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));
    $errorBlockStart = strpos($pageSource, 'onError: () => {');
    $successBlockStart = strpos($pageSource, 'onSuccess: async (data) => {');

    expect($errorBlockStart)->not->toBeFalse()
        ->and($successBlockStart)->not->toBeFalse();

    $errorBlock = substr($pageSource, $errorBlockStart, $successBlockStart - $errorBlockStart);

    expect($errorBlock)->not->toContain('window.location.assign');
});

it('28. existing permission slugs used by the materials routes remain unchanged', function (): void {
    $materialsIndexRoute = Route::getRoutes()->getByName('materials.index');
    $materialsStoreRoute = Route::getRoutes()->getByName('materials.store');
    $materialsUpdateRoute = Route::getRoutes()->getByName('materials.update');
    $materialsDeleteRoute = Route::getRoutes()->getByName('materials.destroy');

    expect($materialsIndexRoute)->not->toBeNull()
        ->and($materialsStoreRoute)->not->toBeNull()
        ->and($materialsUpdateRoute)->not->toBeNull()
        ->and($materialsDeleteRoute)->not->toBeNull();

    $itemControllerSource = file_get_contents(app_path('Http/Controllers/ItemController.php'));
    $materialControllerSource = file_get_contents(app_path('Http/Controllers/MaterialController.php'));

    expect(substr_count($itemControllerSource, "inventory-materials-manage"))->toBeGreaterThanOrEqual(3)
        ->and($materialControllerSource)->toContain("inventory-materials-view");
});
