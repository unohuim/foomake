<?php

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->tenantCurrency = 'USD';
    $this->tenant->currency_code = $this->tenantCurrency;
    $this->tenant->save();

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->grantPermission = function (User $user, string $permissionSlug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $permissionSlug,
        ]);

        $role = Role::query()->create([
            'name' => Str::uuid()->toString(),
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => Str::uuid()->toString(),
        ]);

        return Uom::query()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $category->id,
            'name' => Str::uuid()->toString(),
            'symbol' => Str::upper(Str::random(6)),
        ]);
    };

    $this->makeItem = function (Uom $uom, array $overrides = []): Item {
        return Item::query()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Flour',
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->getShow = function (User $user, Item $item) {
        return $this->actingAs($user)->getJson(route('materials.show', $item));
    };

    $this->getShowPage = function (User $user, Item $item) {
        return $this->actingAs($user)->get(route('materials.show', $item));
    };

    $this->postCreate = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('materials.store'), $payload);
    };
});

test('forbids material show without inventory-materials-view permission', function (): void {
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->getShow)($this->user, $item);

    $response->assertForbidden();
});

test('allows material show with inventory-materials-view permission', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-view');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->getShow)($this->user, $item);

    $response->assertOk();
});

test('material show includes planning price fields when set', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-view');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 420,
        'default_price_currency_code' => 'USD',
    ]);

    $response = ($this->getShow)($this->user, $item);

    $response->assertOk()
        ->assertJsonPath('data.default_price_amount', '4.20')
        ->assertJsonPath('data.default_price_currency_code', 'USD');
});

test('material show page renders the base uom dropdown with tenant uom options', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-view');
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $otherUom = Uom::query()->create([
        'tenant_id' => $this->tenant->id,
        'uom_category_id' => $uom->uom_category_id,
        'name' => 'Test Kilogram',
        'symbol' => 'test-kg',
    ]);
    $item = ($this->makeItem)($uom);

    $response = ($this->getShowPage)($this->user, $item);

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Materials/Show')
            ->where('payload.item.can_manage', true)
            ->where('payload.item.base_uom_name', $uom->name)
            ->has('payload.item.uom_options'));

    $page = $response->viewData('page');
    $options = collect($page['props']['payload']['item']['uom_options'] ?? []);

    expect($options->contains(fn (array $option): bool => $option['id'] === $otherUom->id
        && $option['name'] === 'Test Kilogram'))->toBeTrue();
});

test('material show page renders base uom as a static badge without manage permission', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-view');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->getShowPage)($this->user, $item);

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Materials/Show')
            ->where('payload.item.can_manage', false)
            ->where('payload.item.base_uom_name', $uom->name));
});

test('material show page renders inventory stats through inertia payload state', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-view');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'is_stockable' => true,
    ]);

    $response = ($this->getShowPage)($this->user, $item);

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Materials/Show')
            ->has('payload.inventoryStats.cards'));
});

test('material show quantity bar preserves the blade mobile collapse contract', function (): void {
    $componentSource = file_get_contents(resource_path('js/components/MaterialQuantityBar.vue')) ?: '';
    $pageSource = file_get_contents(resource_path('js/pages/Materials/Show.vue')) ?: '';

    expect($pageSource)->toContain('MaterialQuantityBar')
        ->and($componentSource)->toContain('role="tablist"')
        ->and($componentSource)->toContain('activeStat === card.key')
        ->and($componentSource)->toContain("'flex-1 bg-white px-3 py-2'")
        ->and($componentSource)->toContain("'w-8 bg-slate-50'")
        ->and($componentSource)->toContain('duration-[400ms]')
        ->and($componentSource)->toContain('-rotate-90')
        ->and($componentSource)->toContain('quantity_display_grouped')
        ->and($componentSource)->toContain('compact_label')
        ->and($componentSource)->toContain('sm:grid-cols-5');
});

test('material show header uses the legacy icon-only material type buttons', function (): void {
    $pageSource = file_get_contents(resource_path('js/pages/Materials/Show.vue')) ?: '';

    expect($pageSource)->toContain('shopping-cart')
        ->and($pageSource)->toContain('credit-card')
        ->and($pageSource)->toContain('icon: "cog"')
        ->and($pageSource)->toContain('rectangle-group')
        ->and($pageSource)->toContain('aria-label="toggle.label"')
        ->and($pageSource)->toContain('sr-only')
        ->and($pageSource)->not()->toContain('short: "Sell"')
        ->and($pageSource)->not()->toContain('short: "Buy"')
        ->and($pageSource)->not()->toContain('short: "Make"')
        ->and($pageSource)->not()->toContain('short: "Stock"');
});

test('material show includes planning price fields when null', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-view');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => null,
        'default_price_currency_code' => null,
    ]);

    $response = ($this->getShow)($this->user, $item);

    $response->assertOk()
        ->assertJsonPath('data.default_price_amount', null)
        ->assertJsonPath('data.default_price_currency_code', null);
});

test('material show reflects normalization from store when currency is omitted', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    ($this->grantPermission)($this->user, 'inventory-materials-view');

    $uom = ($this->makeUom)();

    $createResponse = ($this->postCreate)($this->user, [
        'name' => 'Sugar',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '3.5',
    ]);

    $createResponse->assertCreated();

    $itemId = $createResponse->json('data.id');
    $item = Item::withoutGlobalScopes()->findOrFail($itemId);

    $showResponse = ($this->getShow)($this->user, $item);

    $showResponse->assertOk()
        ->assertJsonPath('data.default_price_amount', '3.50')
        ->assertJsonPath('data.default_price_currency_code', $this->tenantCurrency);
});
