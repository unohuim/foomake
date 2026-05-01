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
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->categoryCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (string $name = null): Tenant {
        $tenant = Tenant::query()->create([
            'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => null,
            'password' => Hash::make('password'),
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantManagePermission = function (User $user): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => 'inventory-materials-manage',
        ]);

        $role = Role::query()->create([
            'name' => 'uom-conversions-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeCategory = function (Tenant $tenant, string $name): UomCategory {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name . ' ' . $this->categoryCounter,
        ]);

        $this->categoryCounter++;

        return $category;
    };

    $this->makeUom = function (Tenant $tenant, UomCategory $category, string $symbol, string $name = null): Uom {
        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $name ?? strtoupper($symbol) . ' ' . $this->uomCounter,
            'symbol' => $symbol . $this->uomCounter,
            'display_precision' => 1,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $baseUom): Item {
        $item = Item::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $baseUom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ]);

        $this->itemCounter++;

        return $item;
    };

    $this->indexUrl = '/manufacturing/uom-conversions';
    $this->generalStoreUrl = '/manufacturing/uom-conversions';
    $this->generalUpdateUrl = fn (int $conversionId): string => '/manufacturing/uom-conversions/' . $conversionId;
    $this->generalDestroyUrl = fn (int $conversionId): string => '/manufacturing/uom-conversions/' . $conversionId;
});

it('11. tenant conversions require tenant_id', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    $this->actingAs($user)
        ->postJson($this->generalStoreUrl, [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '1000.00000000',
            'tenant_id' => null,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id']);
});

it('12. general conversions require same category', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    $this->actingAs($user)
        ->postJson($this->generalStoreUrl, [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '1000.00000000',
        ])
        ->assertCreated();
});

it('13. general cross-category conversion is rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $count = ($this->makeCategory)($tenant, 'Count');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $count, 'ea');

    $this->actingAs($user)
        ->postJson($this->generalStoreUrl, [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '12.00000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to_uom_id']);
});

it('14. tenant can create conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'lb');
    $to = ($this->makeUom)($tenant, $mass, 'oz');

    $this->actingAs($user)
        ->postJson($this->generalStoreUrl, [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '16.00000000',
        ])
        ->assertCreated();
});

it('15. tenant can edit own conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'lb');
    $to = ($this->makeUom)($tenant, $mass, 'oz');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '16.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversionId = (int) \DB::table('uom_conversions')->where('tenant_id', $tenant->id)->value('id');

    $this->actingAs($user)
        ->patchJson(($this->generalUpdateUrl)($conversionId), [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '15.50000000',
        ])
        ->assertOk();
});

it('16. tenant can delete own conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'lb');
    $to = ($this->makeUom)($tenant, $mass, 'oz');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '16.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversionId = (int) \DB::table('uom_conversions')->where('tenant_id', $tenant->id)->value('id');

    $this->actingAs($user)
        ->deleteJson(($this->generalDestroyUrl)($conversionId))
        ->assertNoContent();
});

it('17. tenant cannot edit global conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => null,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '1000.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversionId = (int) \DB::table('uom_conversions')->whereNull('tenant_id')->value('id');

    $this->actingAs($user)
        ->patchJson(($this->generalUpdateUrl)($conversionId), [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '999.00000000',
        ])
        ->assertForbidden();
});

it('18. tenant cannot delete global conversion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => null,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '1000.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $conversionId = (int) \DB::table('uom_conversions')->whereNull('tenant_id')->value('id');

    $this->actingAs($user)
        ->deleteJson(($this->generalDestroyUrl)($conversionId))
        ->assertForbidden();
});

it('19. duplicate tenant conversion is rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'lb');
    $to = ($this->makeUom)($tenant, $mass, 'oz');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '16.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson($this->generalStoreUrl, [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '16.00000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_uom_id']);
});

it('20. duplicate global conversion is rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $mass = ($this->makeCategory)($tenant, 'Mass');
    $from = ($this->makeUom)($tenant, $mass, 'kg');
    $to = ($this->makeUom)($tenant, $mass, 'g');

    \DB::table('uom_conversions')->insert([
        'tenant_id' => null,
        'from_uom_id' => $from->id,
        'to_uom_id' => $to->id,
        'multiplier' => '1000.00000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson($this->generalStoreUrl, [
            'from_uom_id' => $from->id,
            'to_uom_id' => $to->id,
            'multiplier' => '1000.00000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_uom_id']);
});

it('21. same conversion can exist separately for different tenants', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $massA = ($this->makeCategory)($tenantA, 'Mass');
    $massB = ($this->makeCategory)($tenantB, 'Mass');
    $fromA = ($this->makeUom)($tenantA, $massA, 'lb');
    $toA = ($this->makeUom)($tenantA, $massA, 'oz');
    $fromB = ($this->makeUom)($tenantB, $massB, 'lb');
    $toB = ($this->makeUom)($tenantB, $massB, 'oz');

    \DB::table('uom_conversions')->insert([
        [
            'tenant_id' => $tenantA->id,
            'from_uom_id' => $fromA->id,
            'to_uom_id' => $toA->id,
            'multiplier' => '16.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'tenant_id' => $tenantB->id,
            'from_uom_id' => $fromB->id,
            'to_uom_id' => $toB->id,
            'multiplier' => '16.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(\DB::table('uom_conversions')->whereIn('tenant_id', [$tenantA->id, $tenantB->id])->count())->toBe(2);
});

it('22. conversion index shows global conversions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $this->actingAs($user)
        ->get($this->indexUrl)
        ->assertOk()
        ->assertSee('globalConversions');
});

it('23. conversion index shows tenant conversions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $this->actingAs($user)
        ->get($this->indexUrl)
        ->assertOk()
        ->assertSee('tenantConversions');
});

it('24. conversion index separates global read-only from tenant editable', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $this->actingAs($user)
        ->get($this->indexUrl)
        ->assertOk()
        ->assertSee('read_only')
        ->assertSee('editable');
});

it('35. unauthorized users cannot access conversion page', function (): void {
    $this->get($this->indexUrl)
        ->assertRedirect(route('login'));
});

it('36. unauthorized users cannot mutate conversions', function (): void {
    $this->postJson($this->generalStoreUrl, [])
        ->assertUnauthorized();
});

it('37. authorized users can access conversion page', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $this->actingAs($user)
        ->get($this->indexUrl)
        ->assertOk();
});

it('38. validation returns json 422 for ajax failures', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $response = $this->actingAs($user)->postJson($this->generalStoreUrl, []);

    $response->assertStatus(422);

    expect((string) $response->headers->get('content-type'))->toContain('application/json');
});

it('39. crud endpoints never redirect on ajax', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $response = $this->actingAs($user)->postJson($this->generalStoreUrl, []);

    expect($response->isRedirection())->toBeFalse();
});

it('40. global conversions are visibly read-only in payload or ui data', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantManagePermission)($user);

    $this->actingAs($user)
        ->get($this->indexUrl)
        ->assertOk()
        ->assertSee('read_only')
        ->assertSee('tenant_id');
});
