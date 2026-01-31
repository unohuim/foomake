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

    $this->postCreate = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('materials.store'), $payload);
    };
});

test('denies creation for users without inventory-materials-manage permission', function (): void {
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertForbidden();
});

test('allows creation for users with inventory-materials-manage permission', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Flour')
        ->assertJsonPath('data.base_uom_id', $uom->id);
});

test('stores null price fields when amount is omitted', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Olive Oil',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Olive Oil')->firstOrFail();

    expect($item->default_price_cents)->toBeNull()
        ->and($item->default_price_currency_code)->toBeNull();
});

test('stores null price fields when amount is null', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Tomato',
        'base_uom_id' => $uom->id,
        'default_price_amount' => null,
        'default_price_currency_code' => 'USD',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Tomato')->firstOrFail();

    expect($item->default_price_cents)->toBeNull()
        ->and($item->default_price_currency_code)->toBeNull();
});

test('stores null price fields when amount is an empty string', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Carrot',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '',
        'default_price_currency_code' => 'USD',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Carrot')->firstOrFail();

    expect($item->default_price_cents)->toBeNull()
        ->and($item->default_price_currency_code)->toBeNull();
});

test('stores null price fields when currency is provided without amount', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Lemon',
        'base_uom_id' => $uom->id,
        'default_price_currency_code' => 'USD',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Lemon')->firstOrFail();

    expect($item->default_price_cents)->toBeNull()
        ->and($item->default_price_currency_code)->toBeNull();
});

test('defaults currency to tenant when amount is provided without currency', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Butter',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '12.50',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Butter')->firstOrFail();

    expect($item->default_price_cents)->toBe(1250)
        ->and($item->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('defaults currency to tenant when amount is provided and currency is blank', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Water',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.00',
        'default_price_currency_code' => '',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Water')->firstOrFail();

    expect($item->default_price_cents)->toBe(100)
        ->and($item->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('accepts explicit currency and persists uppercase', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Cocoa',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '4.25',
        'default_price_currency_code' => 'EUR',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Cocoa')->firstOrFail();

    expect($item->default_price_cents)->toBe(425)
        ->and($item->default_price_currency_code)->toBe('EUR');
});

test('accepts lower-case currency and normalizes to uppercase', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Cinnamon',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '3.25',
        'default_price_currency_code' => 'usd',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Cinnamon')->firstOrFail();

    expect($item->default_price_cents)->toBe(325)
        ->and($item->default_price_currency_code)->toBe('USD');
});

test('accepts integer amounts and normalizes to cents', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Flax',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '5',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Flax')->firstOrFail();

    expect($item->default_price_cents)->toBe(500)
        ->and($item->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('allows zero amount and defaults currency', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Salt',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '0',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Salt')->firstOrFail();

    expect($item->default_price_cents)->toBe(0)
        ->and($item->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('rejects negative amounts', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Pepper',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '-1.00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);
});

test('rejects non-numeric amounts', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Saffron',
        'base_uom_id' => $uom->id,
        'default_price_amount' => 'free',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);
});

test('rejects amounts with more than two decimals', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Paprika',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.999',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);
});

test('rejects currency codes shorter than three letters', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Rice',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '2.00',
        'default_price_currency_code' => 'US',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_currency_code']);
});

test('rejects currency codes longer than three letters', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Oats',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '2.00',
        'default_price_currency_code' => 'USDA',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_currency_code']);
});

test('rejects currency codes with non-letter characters', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Nutmeg',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '3.25',
        'default_price_currency_code' => 'U$D',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_currency_code']);
});

test('returns validation errors for both fields when both are invalid', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Ginger',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '-1',
        'default_price_currency_code' => 'us',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount', 'default_price_currency_code']);
});

test('persists values as cents with uppercase currency', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Turmeric',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '10.50',
        'default_price_currency_code' => 'cad',
    ]);

    $response->assertCreated();

    $item = Item::withoutGlobalScopes()->where('name', 'Turmeric')->firstOrFail();

    expect($item->default_price_cents)->toBe(1050)
        ->and($item->default_price_currency_code)->toBe('CAD');
});
