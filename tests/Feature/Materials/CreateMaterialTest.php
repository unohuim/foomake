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

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
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

    $this->assertCreatedItemCountIs = function (int $expected): void {
        expect(Item::withoutGlobalScopes()->count())->toBe($expected);
    };
});

test('denies creation for users without inventory-materials-manage permission', function () {
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertForbidden();
    ($this->assertCreatedItemCountIs)(0);
});

test('creates a material for users with inventory-materials-manage permission', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => $uom->id,
        'is_purchasable' => true,
        'is_sellable' => false,
        'is_manufacturable' => 0,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Flour')
        ->assertJsonPath('data.base_uom_id', $uom->id);

    // Only assert boolean keys if the API returns them.
    $json = $response->json();
    if (isset($json['data']['is_purchasable'])) {
        $response->assertJsonPath('data.is_purchasable', true);
    }
    if (isset($json['data']['is_sellable'])) {
        $response->assertJsonPath('data.is_sellable', false);
    }
    if (isset($json['data']['is_manufacturable'])) {
        $response->assertJsonPath('data.is_manufacturable', false);
    }
    if (isset($json['data']['tenant_id'])) {
        $response->assertJsonPath('data.tenant_id', $this->tenant->id);
    }

    ($this->assertCreatedItemCountIs)(1);

    $item = Item::withoutGlobalScopes()->where('name', 'Flour')->firstOrFail();

    expect($item->tenant_id)->toBe($this->tenant->id)
        ->and($item->base_uom_id)->toBe($uom->id)
        ->and($item->is_purchasable)->toBeTrue()
        ->and($item->is_sellable)->toBeFalse()
        ->and($item->is_manufacturable)->toBeFalse();
});

test('defaults optional boolean flags to false when omitted', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Sugar',
        'base_uom_id' => $uom->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Sugar')
        ->assertJsonPath('data.base_uom_id', $uom->id);

    // If API returns flags, ensure they are false; otherwise rely on DB assertions below.
    $json = $response->json();
    if (isset($json['data']['is_purchasable'])) {
        $response->assertJsonPath('data.is_purchasable', false);
    }
    if (isset($json['data']['is_sellable'])) {
        $response->assertJsonPath('data.is_sellable', false);
    }
    if (isset($json['data']['is_manufacturable'])) {
        $response->assertJsonPath('data.is_manufacturable', false);
    }
    if (isset($json['data']['tenant_id'])) {
        $response->assertJsonPath('data.tenant_id', $this->tenant->id);
    }

    ($this->assertCreatedItemCountIs)(1);

    $item = Item::withoutGlobalScopes()->where('name', 'Sugar')->firstOrFail();

    expect($item->tenant_id)->toBe($this->tenant->id)
        ->and($item->base_uom_id)->toBe($uom->id)
        ->and($item->is_purchasable)->toBeFalse()
        ->and($item->is_sellable)->toBeFalse()
        ->and($item->is_manufacturable)->toBeFalse();
});

test('returns validation errors for missing required fields', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    ($this->makeUom)();

    $response = ($this->postCreate)($this->user, []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'base_uom_id']);

    ($this->assertCreatedItemCountIs)(0);
});

test('returns validation error when base_uom_id is not a valid uom', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Salt',
        'base_uom_id' => 999999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);

    ($this->assertCreatedItemCountIs)(0);
});

test('returns validation errors for invalid boolean inputs', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Oil',
        'base_uom_id' => $uom->id,
        'is_purchasable' => 'nope',
        'is_sellable' => 'wat',
        'is_manufacturable' => 'maybe',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['is_purchasable', 'is_sellable', 'is_manufacturable']);

    ($this->assertCreatedItemCountIs)(0);
});

test('fails creation when no uoms exist', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');

    $response = ($this->postCreate)($this->user, [
        'name' => 'Flour',
        'base_uom_id' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);

    ($this->assertCreatedItemCountIs)(0);
});

test('ignores tenant_id from payload and creates under authenticated tenant', function () {
    ($this->grantPermission)($this->user, 'inventory-materials-manage');
    $uom = ($this->makeUom)();
    $otherTenant = Tenant::factory()->create();

    $response = ($this->postCreate)($this->user, [
        'name' => 'Yeast',
        'base_uom_id' => $uom->id,
        'tenant_id' => $otherTenant->id,
    ]);

    $response->assertCreated();

    $json = $response->json();
    if (isset($json['data']['tenant_id'])) {
        $response->assertJsonPath('data.tenant_id', $this->tenant->id);
    }

    ($this->assertCreatedItemCountIs)(1);

    $item = Item::withoutGlobalScopes()->where('name', 'Yeast')->firstOrFail();

    expect($item->tenant_id)->toBe($this->tenant->id)
        ->and(Item::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->count())->toBe(0);
});
