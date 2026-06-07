<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
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
        $symbol = $attributes['symbol'] ?? 'mat-' . $this->uomCounter;

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
            'name' => $attributes['category_name'] ?? 'Materials Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Materials UoM ' . $this->uomCounter,
            'symbol' => $symbol,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Material ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_stockable' => false,
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

it('3a. allows users with inventory-stock-view to access the materials availability index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-stock-view');

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

    expect($config['endpoints']['delete'] ?? null)->toBe('');
});

it('10. crud config includes the materials detail redirect template', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['detailUrlTemplate'] ?? null)->toBe(url('/materials/{id}'));
});

it('11. crud config omits row actions because material activation is handled by the shared toggle', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['labels']['actionsAriaLabel'] ?? null)->toBe('Material actions')
        ->and($config['actions'] ?? null)->toBe([]);
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

it('16a. list endpoint allows users with stock view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-stock-view');

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
        'item' => 'Flour',
        'item_uom_name' => 'Kilogram',
        'item_uom_symbol' => $symbol,
        'is_active' => true,
        'is_stockable' => false,
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => true,
        'default_price_amount' => '4.25',
        'default_price_currency_code' => 'USD',
        'show_url' => route('materials.show', $item),
        'update_url' => route('materials.update', $item),
    ]);

    expect($row['on_hand_display'] ?? null)->not->toBeNull()
        ->and($row['net_display'] ?? null)->not->toBeNull();
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
        ->pluck('item')
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

it('18b. materials create accepts is_stockable true and persists it', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $response = $this->actingAs($user)->postJson(route('materials.store'), [
        'name' => 'Tracked Material',
        'base_uom_id' => $uom->id,
        'is_stockable' => true,
    ])->assertCreated();

    $item = Item::withoutGlobalScopes()->findOrFail((int) $response->json('data.id'));

    expect($response->json('data.is_stockable'))->toBeTrue()
        ->and($item->is_stockable)->toBeTrue();
});

it('18c. materials create defaults is_stockable to false when omitted', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $response = $this->actingAs($user)->postJson(route('materials.store'), [
        'name' => 'Non Tracked Material',
        'base_uom_id' => $uom->id,
    ])->assertCreated();

    $item = Item::withoutGlobalScopes()->findOrFail((int) $response->json('data.id'));

    expect($response->json('data.is_stockable'))->toBeFalse()
        ->and($item->is_stockable)->toBeFalse();
});

it('18d. creating a stockable material with starting quantity creates a completed inventory count and stock effect', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant, ['name' => 'Kilogram', 'symbol' => 'kg']);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $response = $this->actingAs($user)->postJson(route('materials.store'), [
        'name' => 'Opening Balance Material',
        'base_uom_id' => $uom->id,
        'is_stockable' => true,
        'starting_quantity' => '3.250000',
    ])->assertCreated();

    $item = Item::withoutGlobalScopes()->findOrFail((int) $response->json('data.id'));
    $line = InventoryCountLine::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->firstOrFail();
    $count = InventoryCount::withoutGlobalScopes()
        ->with('workflowStage')
        ->findOrFail((int) $line->inventory_count_id);
    $move = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('source_type', InventoryCount::class)
        ->where('source_id', $count->id)
        ->where('item_id', $item->id)
        ->firstOrFail();

    expect($count->workflowStage?->key)->toBe('completing')
        ->and($count->posted_at)->not->toBeNull()
        ->and($count->created_by_user_id)->toBe($user->id)
        ->and($count->tasked_by_user_id)->toBe($user->id)
        ->and($count->assigned_to_user_id)->toBe($user->id)
        ->and($line->item_id)->toBe($item->id)
        ->and((string) $line->counted_quantity)->toBe('3.250000')
        ->and($count->tenant_id)->toBe($tenant->id)
        ->and($move->uom_id)->toBe($uom->id)
        ->and((string) $move->quantity)->toBe('3.250000');
});

it('18e. non stockable material creation rejects a starting quantity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $this->actingAs($user)->postJson(route('materials.store'), [
        'name' => 'Non Tracked Opening Balance',
        'base_uom_id' => $uom->id,
        'is_stockable' => false,
        'starting_quantity' => '1.000000',
    ])->assertStatus(422)->assertJsonValidationErrors('starting_quantity');
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

    $names = collect($response->json('data'))->pluck('item')->all();

    expect($names)->toBe(['Alpha Flour']);
});

it('20. list endpoint returns allowed sortable columns metadata', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    $response = ($this->getList)($user)
        ->assertOk();

    expect($response->json('meta.allowed_sort_columns'))->toBe(['item']);
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
    $createSource = file_get_contents(resource_path('views/materials/partials/create-material-slide-over.blade.php'));

    expect($pageSource)->toContain('createGenericCrud(parseCrudConfig(rootEl))')
        ->and($pageSource)->toContain('mountCrudCardRenderer(crudRootEl, rendererConfig);')
        ->and($pageSource)->toContain('this.crud.fetchList({')
        ->and($pageSource)->toContain('is_stockable')
        ->and($createSource)->toContain('x-model="form.is_stockable"');
});

it('22a. materials mobile crud config renders clickable rows with badges and an active toggle', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-materials-view', 'inventory-materials-manage']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['mobileCard']['urlExpression'] ?? null)->toBe('record.show_url')
        ->and($config['actions'] ?? null)->toBe([])
        ->and($config['mobileCard']['titleAsideExpression'] ?? null)->toBe('materialCardUomLabel(record)')
        ->and($config['mobileCard']['layout'] ?? null)->toBe('flush-stacked')
        ->and($config['mobileCard']['badgesExpression'] ?? null)->toBe('')
        ->and($config['mobileCard']['iconBadgesExpression'] ?? null)->toBe('materialFlagIcons(record)')
        ->and($config['mobileCard']['showActions'] ?? null)->toBeFalse()
        ->and($config['mobileCard']['toggle']['name'] ?? null)->toBe('is_active')
        ->and($config['mobileCard']['toggle']['checkedExpression'] ?? null)->toBe('Boolean(record.is_active)')
        ->and($config['mobileCard']['toggle']['eventName'] ?? null)->toBe('inventory-material-active-toggle')
        ->and($config['mobileCard']['toggle']['handler'] ?? null)->toBe('toggleMaterialActive(toggleDetail)')
        ->and($config['mobileCard']['toggle']['disabledExpression'] ?? null)->toBe('!canManageMaterials()');
});

it('22b. shared crud card mobile renderer emits row toggle events and uses lime active styling', function (): void {
    $rendererSource = file_get_contents(resource_path('js/lib/crud-card-page.js'));
    $configSource = file_get_contents(resource_path('js/lib/crud-config.js'));
    $toggleSource = file_get_contents(resource_path('js/components/toggle.js'));
    $componentSource = file_get_contents(resource_path('views/components/ui/toggle.blade.php'));

    expect($rendererSource)->toContain("from '../components/toggle'")
        ->and($rendererSource)->toContain('renderToggle')
        ->and($rendererSource)->toContain('rowToggle')
        ->and($rendererSource)->toContain('const toggleConfig = config.rowToggle.name')
        ->and($rendererSource)->toContain('data-crud-mobile-cards')
        ->and($rendererSource)->toContain('data-crud-card')
        ->and($rendererSource)->toContain(':href="${card.urlExpression}"')
        ->and($rendererSource)->toContain('iconBadgesExpression')
        ->and($rendererSource)->toContain("badge.icon === 'rectangle-group'")
        ->and($rendererSource)->toContain("badge.icon === 'credit-card'")
        ->and($rendererSource)->toContain("badge.icon === 'shopping-cart'")
        ->and($rendererSource)->toContain("badge.icon === 'cog'")
        ->and($rendererSource)->toContain('overflow-y-auto p-0')
        ->and($rendererSource)->toContain('border-t border-gray-300')
        ->and($rendererSource)->toContain('border-b border-gray-300')
        ->and($rendererSource)->toContain('px-4 py-2')
        ->and($rendererSource)->not->toContain('rounded-lg border border-gray-100 bg-white p-4')
        ->and($rendererSource)->not->toContain('hover:border-gray-200')
        ->and($configSource)->toContain('layout: sanitizeLabel(rawMobileCard.layout)')
        ->and($configSource)->toContain('iconBadgesExpression: sanitizeLabel(rawMobileCard.iconBadgesExpression)')
        ->and($configSource)->toContain('rowToggle: {')
        ->and($configSource)->toContain('label: sanitizeLabel(rawRowToggle.label)')
        ->and($configSource)->toContain('titleAsideExpression: sanitizeLabel(rawMobileCard.titleAsideExpression)')
        ->and($configSource)->toContain('badgesExpression: sanitizeLabel(rawMobileCard.badgesExpression)')
        ->and($configSource)->toContain('urlExpression: sanitizeLabel(rawMobileCard.urlExpression)')
        ->and($configSource)->toContain('showActions: rawMobileCard.showActions !== false')
        ->and($configSource)->toContain('toggle: {')
        ->and($configSource)->toContain('const rawDesktopCard = sanitizeRecord(config.desktopCard)')
        ->and($configSource)->toContain('desktopCard: {')
        ->and($configSource)->toContain('statsExpression: sanitizeLabel(rawDesktopCard.statsExpression)')
        ->and($configSource)->toContain('iconBadgesExpression: sanitizeLabel(rawDesktopCard.iconBadgesExpression)')
        ->and($toggleSource)->toContain("role=\"switch\"")
        ->and($toggleSource)->toContain('bg-lime-500')
        ->and($toggleSource)->toContain("\$dispatch")
        ->and($toggleSource)->toContain('toggleDetail')
        ->and($toggleSource)->toContain('record')
        ->and($toggleSource)->toContain('id')
        ->and($componentSource)->toContain('role="switch"')
        ->and($componentSource)->toContain('bg-lime-500')
        ->and($componentSource)->toContain("\$dispatch");
});

it('22c. materials page module persists mobile active toggle changes through the existing update endpoint', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));

    expect($pageSource)->toContain('materialCardUomLabel(record)')
        ->and($pageSource)->toContain('materialFlagIcons(record)')
        ->and($pageSource)->toContain('canManageMaterials()')
        ->and($pageSource)->toContain('async toggleMaterialActive(toggleDetail)')
        ->and($pageSource)->toContain('is_active: nextValue')
        ->and($pageSource)->toContain('${record.item || \'Material\'} ${record.is_active ? \'Active\' : \'Inactive\'}')
        ->and($pageSource)->toContain('record.update_url || buildItemEndpoint(this.endpoints.update, record.id)');
});

it('22d. materials desktop card icons render as standalone icons without circle rings', function (): void {
    $rendererSource = file_get_contents(resource_path('js/lib/crud-card-page.js'));

    expect($rendererSource)->toContain('data-crud-card-icon-badges')
        ->and($rendererSource)->toContain('class="h-7 w-7"')
        ->and($rendererSource)->toContain("badge.active ? 'text-blue-600' : 'text-gray-300'")
        ->and($rendererSource)->not->toContain('rounded-full border-2 bg-white')
        ->and($rendererSource)->not->toContain("badge.active ? 'border-blue-600 text-blue-600' : 'border-gray-300 text-gray-300'");
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

it('25. materials page module refreshes the list after create without redirecting to detail', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));

    expect($pageSource)->toContain('await this.fetchMaterials();')
        ->and($pageSource)->toContain('this.closeCreate();')
        ->and($pageSource)->toContain("this.showToast('success', 'Material created.');")
        ->and($pageSource)->not->toContain('this.crud.buildDetailUrl(data?.data)')
        ->and($pageSource)->not->toContain('window.location.assign(redirectUrl);');
});

it('26. materials create validation handling does not redirect before success', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));
    $validationBlockStart = strpos($pageSource, 'onValidationError:');
    $successBlockStart = strpos($pageSource, 'onSuccess: async () => {');

    expect($validationBlockStart)->not->toBeFalse()
        ->and($successBlockStart)->not->toBeFalse();

    $validationBlock = substr($pageSource, $validationBlockStart, $successBlockStart - $validationBlockStart);

    expect($validationBlock)->not->toContain('window.location.assign');
});

it('27. materials create generic error handling does not redirect before success', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));
    $errorBlockStart = strpos($pageSource, 'onError: () => {');
    $successBlockStart = strpos($pageSource, 'onSuccess: async () => {');

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
