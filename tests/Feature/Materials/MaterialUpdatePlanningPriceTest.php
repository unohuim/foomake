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

    $this->patchUpdate = function (User $user, Item $item, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('materials.update', $item), $payload);
    };
});

test('defaults currency to tenant when amount is provided without currency', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '9.99',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(999)
        ->and($updated->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('defaults currency to tenant when amount is provided and currency is blank', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.00',
        'default_price_currency_code' => '',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(100)
        ->and($updated->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('accepts explicit currency and persists uppercase', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '5.10',
        'default_price_currency_code' => 'GBP',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(510)
        ->and($updated->default_price_currency_code)->toBe('GBP');
});

test('accepts lower-case currency and normalizes to uppercase on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '2.25',
        'default_price_currency_code' => 'usd',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(225)
        ->and($updated->default_price_currency_code)->toBe('USD');
});

test('accepts integer amounts and normalizes to cents on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '5',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(500)
        ->and($updated->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('allows zero amount and defaults currency on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '0',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(0)
        ->and($updated->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('clears price fields when amount is null', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 350,
        'default_price_currency_code' => 'EUR',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => null,
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBeNull()
        ->and($updated->default_price_currency_code)->toBeNull();
});

test('clears price fields when amount is an empty string', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 350,
        'default_price_currency_code' => 'EUR',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '',
        'default_price_currency_code' => 'USD',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBeNull()
        ->and($updated->default_price_currency_code)->toBeNull();
});

test('keeps existing price fields when both price inputs are omitted', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 350,
        'default_price_currency_code' => 'EUR',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(350)
        ->and($updated->default_price_currency_code)->toBe('EUR');
});

test('clears price fields when currency is provided without amount', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 350,
        'default_price_currency_code' => 'EUR',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_currency_code' => 'USD',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBeNull()
        ->and($updated->default_price_currency_code)->toBeNull();
});

test('overrides existing currency to tenant when amount is provided without currency', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 200,
        'default_price_currency_code' => 'EUR',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '3.00',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(300)
        ->and($updated->default_price_currency_code)->toBe($this->tenantCurrency);
});

test('rejects negative amounts on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '-0.01',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);
});

test('rejects non-numeric amounts on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => 'free',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);
});

test('rejects amounts with more than two decimals on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.999',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);
});

test('rejects currency codes shorter than three letters on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.00',
        'default_price_currency_code' => 'EU',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_currency_code']);
});

test('rejects currency codes longer than three letters on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.00',
        'default_price_currency_code' => 'USDA',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_currency_code']);
});

test('rejects currency codes with non-letter characters on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.00',
        'default_price_currency_code' => 'U$D',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_currency_code']);
});

test('returns validation errors for both fields when both are invalid on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '-1',
        'default_price_currency_code' => 'us',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount', 'default_price_currency_code']);
});

test('does not partially update when validation fails', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'name' => 'Original',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Should Not Save',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '1.999',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_price_amount']);

    $reloaded = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($reloaded->name)->toBe('Original');
});

test('persists updated values as cents with uppercase currency', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 100,
        'default_price_currency_code' => 'USD',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '7.70',
        'default_price_currency_code' => 'cad',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(770)
        ->and($updated->default_price_currency_code)->toBe('CAD');
});
