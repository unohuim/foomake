<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->categoryCounter = 1;
    $this->uomCounter = 1;

    $this->makeTenant = function (string $name): Tenant {
        return Tenant::query()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUser = function (Tenant $tenant, string $email): User {
        return User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $email,
            'email' => $email,
            'email_verified_at' => null,
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);
    };

    $this->grantManage = function (User $user): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'inventory-materials-manage',
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'uom-display-precision-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeCategory = function (Tenant $tenant, string $name): UomCategory {
        $suffix = (string) $this->categoryCounter;
        $this->categoryCounter++;

        return UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name . '-' . $suffix,
        ]);
    };

    $this->makeUom = function (Tenant $tenant, UomCategory $category, array $attributes = []): Uom {
        $suffix = (string) $this->uomCounter;
        $this->uomCounter++;

        return Uom::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $suffix,
            'symbol' => 'u' . $suffix,
            'display_precision' => 1,
        ], $attributes));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };

    $this->flattenUomRows = function (array $payload): array {
        $rows = [];

        foreach (($payload['categories'] ?? []) as $category) {
            foreach (($category['uoms'] ?? []) as $uom) {
                $rows[] = $uom;
            }
        }

        return $rows;
    };
});

it('defaults display_precision to 1 when omitted on create and exposes it in index payload', function () {
    $tenant = ($this->makeTenant)('Default Precision Tenant');
    $user = ($this->makeUser)($tenant, 'default@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $createResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Kilogram',
            'symbol' => 'kg-default',
        ])
        ->assertCreated()
        ->assertJsonPath('display_precision', 1);

    $uomId = (int) $createResponse->json('id');

    $this->assertDatabaseHas('uoms', [
        'id' => $uomId,
        'display_precision' => 1,
    ]);

    $indexResponse = $this->actingAs($user)
        ->get(route('manufacturing.uoms.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($indexResponse, 'manufacturing-uoms-index-payload');
    $rows = ($this->flattenUomRows)($payload);

    expect($rows)->not()->toBeEmpty();
    expect($rows[0]['display_precision'] ?? null)->toBe(1);
});

it('accepts create with display_precision 0', function () {
    $tenant = ($this->makeTenant)('Create Precision Zero Tenant');
    $user = ($this->makeUser)($tenant, 'create-zero@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Count');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Each',
            'symbol' => 'ea-zero',
            'display_precision' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('display_precision', 0);

    $this->assertDatabaseHas('uoms', [
        'tenant_id' => $tenant->id,
        'symbol' => 'ea-zero',
        'display_precision' => 0,
    ]);
});

it('accepts create with display_precision 1', function () {
    $tenant = ($this->makeTenant)('Create Precision One Tenant');
    $user = ($this->makeUser)($tenant, 'create-one@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'symbol' => 'g-one',
            'display_precision' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('display_precision', 1);
});

it('accepts create with display_precision 2', function () {
    $tenant = ($this->makeTenant)('Create Precision Two Tenant');
    $user = ($this->makeUser)($tenant, 'create-two@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Pound',
            'symbol' => 'lb-two',
            'display_precision' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('display_precision', 2);
});

it('accepts create with display_precision 3', function () {
    $tenant = ($this->makeTenant)('Create Precision Three Tenant');
    $user = ($this->makeUser)($tenant, 'create-three@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Volume');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Liter',
            'symbol' => 'l-three',
            'display_precision' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('display_precision', 3);
});

it('accepts create with display_precision 6', function () {
    $tenant = ($this->makeTenant)('Create Precision Six Tenant');
    $user = ($this->makeUser)($tenant, 'create-six@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Microgram',
            'symbol' => 'mcg-six',
            'display_precision' => 6,
        ])
        ->assertCreated()
        ->assertJsonPath('display_precision', 6);
});

it('rejects create when display_precision is below 0', function () {
    $tenant = ($this->makeTenant)('Create Below Zero Tenant');
    $user = ($this->makeUser)($tenant, 'create-below-zero@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Invalid Neg',
            'symbol' => 'neg-precision',
            'display_precision' => -1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('rejects create when display_precision is above 6', function () {
    $tenant = ($this->makeTenant)('Create Above Six Tenant');
    $user = ($this->makeUser)($tenant, 'create-above-six@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Invalid Above',
            'symbol' => 'above-precision',
            'display_precision' => 7,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('rejects create when display_precision is a decimal value', function () {
    $tenant = ($this->makeTenant)('Create Decimal Tenant');
    $user = ($this->makeUser)($tenant, 'create-decimal@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Invalid Decimal',
            'symbol' => 'decimal-precision',
            'display_precision' => 1.5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('rejects create when display_precision is a non-numeric string', function () {
    $tenant = ($this->makeTenant)('Create Numeric String Tenant');
    $user = ($this->makeUser)($tenant, 'create-string@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Invalid String',
            'symbol' => 'string-precision',
            'display_precision' => 'abc',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('returns display_precision key in create response payload', function () {
    $tenant = ($this->makeTenant)('Create Response Payload Tenant');
    $user = ($this->makeUser)($tenant, 'create-response@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Mass');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Response Unit',
            'symbol' => 'response-unit',
            'display_precision' => 4,
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'id',
            'uom_category_id',
            'name',
            'symbol',
            'display_precision',
        ])
        ->assertJsonPath('display_precision', 4);
});

it('accepts update with display_precision 0', function () {
    $tenant = ($this->makeTenant)('Update Zero Tenant');
    $user = ($this->makeUser)($tenant, 'update-zero@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Zero Unit',
        'symbol' => 'update-zero',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Zero Unit',
            'symbol' => 'update-zero',
            'display_precision' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('display_precision', 0);

    $this->assertDatabaseHas('uoms', [
        'id' => $uom->id,
        'display_precision' => 0,
    ]);
});

it('accepts update with display_precision 1', function () {
    $tenant = ($this->makeTenant)('Update One Tenant');
    $user = ($this->makeUser)($tenant, 'update-one@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update One Unit',
        'symbol' => 'update-one',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update One Unit',
            'symbol' => 'update-one',
            'display_precision' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('display_precision', 1);
});

it('accepts update with display_precision 2', function () {
    $tenant = ($this->makeTenant)('Update Two Tenant');
    $user = ($this->makeUser)($tenant, 'update-two@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Two Unit',
        'symbol' => 'update-two',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Two Unit',
            'symbol' => 'update-two',
            'display_precision' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('display_precision', 2);
});

it('accepts update with display_precision 3', function () {
    $tenant = ($this->makeTenant)('Update Three Tenant');
    $user = ($this->makeUser)($tenant, 'update-three@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Three Unit',
        'symbol' => 'update-three',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Three Unit',
            'symbol' => 'update-three',
            'display_precision' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('display_precision', 3);
});

it('accepts update with display_precision 6', function () {
    $tenant = ($this->makeTenant)('Update Six Tenant');
    $user = ($this->makeUser)($tenant, 'update-six@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Six Unit',
        'symbol' => 'update-six',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Six Unit',
            'symbol' => 'update-six',
            'display_precision' => 6,
        ])
        ->assertOk()
        ->assertJsonPath('display_precision', 6);
});

it('rejects update when display_precision is below 0', function () {
    $tenant = ($this->makeTenant)('Update Below Zero Tenant');
    $user = ($this->makeUser)($tenant, 'update-below-zero@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Neg Unit',
        'symbol' => 'update-neg',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Neg Unit',
            'symbol' => 'update-neg',
            'display_precision' => -1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('rejects update when display_precision is above 6', function () {
    $tenant = ($this->makeTenant)('Update Above Six Tenant');
    $user = ($this->makeUser)($tenant, 'update-above-six@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Above Unit',
        'symbol' => 'update-above',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Above Unit',
            'symbol' => 'update-above',
            'display_precision' => 7,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('rejects update when display_precision is decimal', function () {
    $tenant = ($this->makeTenant)('Update Decimal Tenant');
    $user = ($this->makeUser)($tenant, 'update-decimal@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Decimal Unit',
        'symbol' => 'update-decimal',
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Decimal Unit',
            'symbol' => 'update-decimal',
            'display_precision' => 2.25,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_precision']);
});

it('keeps prior display_precision on update when display_precision is omitted', function () {
    $tenant = ($this->makeTenant)('Update Omitted Precision Tenant');
    $user = ($this->makeUser)($tenant, 'update-omitted@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Omitted Unit',
        'symbol' => 'update-omitted',
        'display_precision' => 4,
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Omitted Unit',
            'symbol' => 'update-omitted',
        ])
        ->assertOk()
        ->assertJsonPath('display_precision', 4);

    $this->assertDatabaseHas('uoms', [
        'id' => $uom->id,
        'display_precision' => 4,
    ]);
});

it('returns display_precision key in update response payload', function () {
    $tenant = ($this->makeTenant)('Update Response Payload Tenant');
    $user = ($this->makeUser)($tenant, 'update-response@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Update Category');
    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Update Response Unit',
        'symbol' => 'update-response-unit',
        'display_precision' => 2,
    ]);

    $this->actingAs($user)
        ->patchJson(route('manufacturing.uoms.update', $uom), [
            'uom_category_id' => $category->id,
            'name' => 'Update Response Unit',
            'symbol' => 'update-response-unit',
            'display_precision' => 5,
        ])
        ->assertOk()
        ->assertJsonStructure([
            'id',
            'uom_category_id',
            'name',
            'symbol',
            'display_precision',
        ])
        ->assertJsonPath('display_precision', 5);
});

it('includes stored display_precision values in index payload rows', function () {
    $tenant = ($this->makeTenant)('Index Precision Tenant');
    $user = ($this->makeUser)($tenant, 'index-precision@example.test');
    ($this->grantManage)($user);

    $category = ($this->makeCategory)($tenant, 'Index Category');

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Index Unit One',
            'symbol' => 'index-unit-one',
            'display_precision' => 3,
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('manufacturing.uoms.store'), [
            'uom_category_id' => $category->id,
            'name' => 'Index Unit Two',
            'symbol' => 'index-unit-two',
            'display_precision' => 6,
        ])
        ->assertCreated();

    $indexResponse = $this->actingAs($user)
        ->get(route('manufacturing.uoms.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($indexResponse, 'manufacturing-uoms-index-payload');
    $rows = ($this->flattenUomRows)($payload);

    $precisions = array_values(array_map(fn (array $row): int => (int) ($row['display_precision'] ?? -1), $rows));

    expect($precisions)->toContain(3);
    expect($precisions)->toContain(6);
});

it('stores display_precision as integer on model retrieval', function () {
    $tenant = ($this->makeTenant)('Cast Tenant');
    $category = ($this->makeCategory)($tenant, 'Cast Category');

    $uom = ($this->makeUom)($tenant, $category, [
        'name' => 'Cast Unit',
        'symbol' => 'cast-unit',
        'display_precision' => 2,
    ])->fresh();

    expect($uom)->not()->toBeNull();
    expect($uom->display_precision)->toBeInt();
    expect($uom->display_precision)->toBe(2);
});

it('migration defines uoms display_precision as non-null with default 1', function () {
    $columns = DB::select("PRAGMA table_info('uoms')");
    $displayPrecisionColumn = collect($columns)->firstWhere('name', 'display_precision');

    expect($displayPrecisionColumn)->not()->toBeNull();
    expect((int) $displayPrecisionColumn->notnull)->toBe(1);
    expect(in_array((string) $displayPrecisionColumn->dflt_value, ['1', "'1'"], true))->toBeTrue();
});
