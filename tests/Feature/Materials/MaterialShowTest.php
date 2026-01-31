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
            'name' => Str::uuid()->toString(),
        ]);

        return Uom::query()->create([
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
