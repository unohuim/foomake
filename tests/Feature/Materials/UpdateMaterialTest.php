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
            'is_stockable' => false,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->patchUpdate = function (User $user, Item $item, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('materials.update', $item), $payload);
    };
});

test('denies updates for users without inventory-materials-manage permission', function (): void {
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertForbidden();
});

test('updates a material for users with inventory-materials-manage permission', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $newUom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $newUom->id,
        'is_stockable' => true,
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Flour')
        ->assertJsonPath('data.base_uom_id', $newUom->id)
        ->assertJsonPath('data.is_stockable', true)
        ->assertJsonPath('data.is_purchasable', true);

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->name)->toBe('Updated Flour')
        ->and($updated->base_uom_id)->toBe($newUom->id)
        ->and($updated->is_stockable)->toBeTrue()
        ->and($updated->is_purchasable)->toBeTrue()
        ->and($updated->is_sellable)->toBeFalse()
        ->and($updated->is_manufacturable)->toBeFalse();
});

test('updates can toggle is_stockable on and off', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, ['is_stockable' => false]);

    ($this->patchUpdate)($this->user, $item, [
        'name' => 'Tracked Flour',
        'base_uom_id' => $uom->id,
        'is_stockable' => true,
    ])->assertOk()->assertJsonPath('data.is_stockable', true);

    ($this->patchUpdate)($this->user, $item->fresh(), [
        'name' => 'Untracked Flour',
        'base_uom_id' => $uom->id,
        'is_stockable' => false,
    ])->assertOk()->assertJsonPath('data.is_stockable', false);

    expect(Item::withoutGlobalScopes()->findOrFail($item->id)->is_stockable)->toBeFalse();
});

test('edit material slide over keeps the stockable checkbox bound to the shared edit form', function (): void {
    $source = file_get_contents(resource_path('views/materials/partials/edit-material-slide-over.blade.php'));
    $pageSource = file_get_contents(resource_path('js/pages/materials-index.js'));

    expect($source)->toContain('x-model="editForm.is_stockable"')
        ->and($pageSource)->toContain('is_stockable: Boolean(record.is_stockable)');
});

test('returns not found when attempting to update another tenant item', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();

    $otherTenant = Tenant::factory()->create();

    $otherItem = Item::query()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Flour',
        'base_uom_id' => $uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    $response = ($this->patchUpdate)($this->user, $otherItem, [
        'name' => 'Blocked',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertNotFound();
});

test('returns validation errors for missing required fields', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'base_uom_id']);
});

test('locks base_uom_id when stock moves exist and does not partially update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $newUom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'name' => 'Original Flour',
    ]);

    StockMove::query()->create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '1.000000',
        'type' => 'receipt',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Should Not Apply',
        'base_uom_id' => $newUom->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);

    $reloaded = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($reloaded->base_uom_id)->toBe($uom->id)
        ->and($reloaded->name)->toBe('Original Flour');
});

test('allows updates when stock moves exist but base_uom_id is unchanged', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'name' => 'Original Flour',
    ]);

    StockMove::query()->create([
        'tenant_id' => $this->tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '1.000000',
        'type' => 'receipt',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'is_purchasable' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Flour')
        ->assertJsonPath('data.base_uom_id', $uom->id);

    $reloaded = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($reloaded->name)->toBe('Updated Flour')
        ->and($reloaded->base_uom_id)->toBe($uom->id)
        ->and($reloaded->is_purchasable)->toBeTrue();
});

test('allows base_uom_id updates when no stock moves exist', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $newUom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Unlocked Flour',
        'base_uom_id' => $newUom->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.base_uom_id', $newUom->id);

    $reloaded = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($reloaded->base_uom_id)->toBe($newUom->id);
});

test('defaults currency to tenant when updating amount without currency', function (): void {
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

test('persists explicit currency when updating with amount and currency', function (): void {
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

test('includes default price fields in the update response when set', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '8.75',
        'default_price_currency_code' => 'USD',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.default_price_amount', '8.75')
        ->assertJsonPath('data.default_price_currency_code', 'USD');
});

test('does not override explicit currency on update', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
        'default_price_amount' => '6.00',
        'default_price_currency_code' => 'CAD',
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_currency_code)->toBe('CAD');
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

test('clears price fields when amount is missing but currency is provided', function (): void {
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

test('rejects negative default price amounts on update', function (): void {
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

test('rejects invalid currency length on update', function (): void {
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

test('rejects non-letter currency code on update', function (): void {
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

test('allows zero amount and defaults currency on update when missing', function (): void {
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

test('accepts lower-case currency codes and normalizes to uppercase on update', function (): void {
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

test('overrides existing currency to tenant currency when amount is provided without currency', function (): void {
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

test('keeps existing price fields when price inputs are omitted from update payload', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, [
        'default_price_cents' => 200,
        'default_price_currency_code' => 'EUR',
    ]);

    $response = ($this->patchUpdate)($this->user, $item, [
        'name' => 'Updated Flour',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertOk();

    $updated = Item::withoutGlobalScopes()->findOrFail($item->id);

    expect($updated->default_price_cents)->toBe(200)
        ->and($updated->default_price_currency_code)->toBe('EUR');
});

test('rejects non-numeric default price amounts on update', function (): void {
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

test('forbidden users cannot mutate is_stockable', function (): void {
    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($uom, ['is_stockable' => false]);

    ($this->patchUpdate)($this->user, $item, [
        'name' => 'Blocked Flour',
        'base_uom_id' => $uom->id,
        'is_stockable' => true,
    ])->assertForbidden();

    expect(Item::withoutGlobalScopes()->findOrFail($item->id)->is_stockable)->toBeFalse();
});

test('cross tenant update attempts cannot mutate is_stockable', function (): void {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $uom = ($this->makeUom)();
    $otherTenant = Tenant::factory()->create();
    $otherItem = Item::query()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Flour',
        'base_uom_id' => $uom->id,
        'is_stockable' => false,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ]);

    ($this->patchUpdate)($this->user, $otherItem, [
        'name' => 'Blocked',
        'base_uom_id' => $uom->id,
        'is_stockable' => true,
    ])->assertNotFound();

    expect(Item::withoutGlobalScopes()->findOrFail($otherItem->id)->is_stockable)->toBeFalse();
});
