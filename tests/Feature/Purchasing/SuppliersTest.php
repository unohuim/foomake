<?php

/*
 * Covers PR2-PUR-001: Suppliers index + AJAX create with gates, tenancy, validation,
 * tenant-currency defaulting, and Blade payload contract.
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->makeTenant = function (array $attributes = []) {
        $currencyCode = $attributes['currency_code'] ?? null;
        unset($attributes['currency_code']);

        $tenant = Tenant::factory()->create($attributes);

        if ($currencyCode !== null) {
            $tenant->forceFill(['currency_code' => $currencyCode])->save();
        }

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []) {
        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $attributes));
    };

    $this->grantPermission = function (User $user, string $slug) {
        $permission = Permission::firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::firstOrCreate([
            'name' => 'role-' . $slug,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $permission;
    };

    $this->createSupplier = function (Tenant $tenant, array $attributes = []) {
        return Supplier::create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . uniqid(),
            'url' => null,
            'phone' => null,
            'email' => null,
            'currency_code' => null,
        ], $attributes));
    };
});

it('denies suppliers index without view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    expect(Gate::forUser($user)->allows('purchasing-suppliers-view'))->toBeFalse();

    $this->actingAs($user)
        ->get('/purchasing/suppliers')
        ->assertForbidden();
});

it('denies Gate check for suppliers view without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    expect(Gate::forUser($user)->allows('purchasing-suppliers-view'))->toBeFalse();
});

it('allows Gate check for suppliers view with permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    expect(Gate::forUser($user)->allows('purchasing-suppliers-view'))->toBeTrue();
});

it('allows suppliers index with view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->actingAs($user)
        ->get('/purchasing/suppliers')
        ->assertOk();
});

it('renders suppliers index payload markers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->actingAs($user)
        ->get('/purchasing/suppliers')
        ->assertOk()
        ->assertSee('data-page="purchasing-suppliers-index"', false)
        ->assertSee('id="purchasing-suppliers-index-payload"', false);
});

it('shows suppliers for the current tenant on index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Tenant Supplier',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->actingAs($user)
        ->get('/purchasing/suppliers')
        ->assertOk()
        ->assertSee($supplier->company_name);
});

it('does not show suppliers from other tenants on index', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    ($this->createSupplier)($tenantA, [
        'company_name' => 'Other Tenant Supplier',
    ]);

    $user = ($this->makeUser)($tenantB);
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $this->actingAs($user)
        ->get('/purchasing/suppliers')
        ->assertOk()
        ->assertDontSee('Other Tenant Supplier');
});

it('denies supplier create without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    expect(Gate::forUser($user)->allows('purchasing-suppliers-manage'))->toBeFalse();

    $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'No Permission Supplier',
        ])
        ->assertForbidden();
});

it('denies Gate check for suppliers manage without permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    expect(Gate::forUser($user)->allows('purchasing-suppliers-manage'))->toBeFalse();
});

it('allows Gate check for suppliers manage with permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    expect(Gate::forUser($user)->allows('purchasing-suppliers-manage'))->toBeTrue();
});

it('allows supplier create with manage permission', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Permitted Supplier',
        ])
        ->assertCreated();
});

it('returns supplier JSON fields on create', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Json Supplier',
            'url' => 'https://example.test',
            'phone' => '555-1234',
            'email' => 'buyer@example.test',
            'currency_code' => 'USD',
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'company_name',
                'url',
                'phone',
                'email',
                'currency_code',
            ],
        ]);
});

it('persists supplier with tenant_id on create', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Stored Supplier',
        ]);

    $supplierId = $response->json('data.id');

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplierId,
        'tenant_id' => $tenant->id,
        'company_name' => 'Stored Supplier',
    ]);
});

it('validates missing company_name', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company_name']);
});

it('validates invalid email', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Invalid Email Supplier',
            'email' => 'not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('validates invalid currency_code length', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Bad Currency Supplier',
            'currency_code' => 'US',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['currency_code']);
});

it('defaults currency_code to tenant currency when null', function () {
    if (!Schema::hasColumn('tenants', 'currency_code')) {
        $this->fail('Expected tenants.currency_code to exist for supplier currency defaulting.');
    }

    $tenant = ($this->makeTenant)(['currency_code' => 'CAD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Default Currency Supplier',
            'currency_code' => null,
        ])
        ->assertCreated();

    expect($response->json('data.currency_code'))->toBe('CAD');
    $this->assertDatabaseHas('suppliers', [
        'company_name' => 'Default Currency Supplier',
        'currency_code' => 'CAD',
    ]);
});

it('defaults currency_code to tenant currency when omitted', function () {
    if (!Schema::hasColumn('tenants', 'currency_code')) {
        $this->fail('Expected tenants.currency_code to exist for supplier currency defaulting.');
    }

    $tenant = ($this->makeTenant)(['currency_code' => 'CAD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Omitted Currency Supplier',
        ])
        ->assertCreated();

    expect($response->json('data.currency_code'))->toBe('CAD');
});

it('uses provided currency_code when supplied', function () {
    if (!Schema::hasColumn('tenants', 'currency_code')) {
        $this->fail('Expected tenants.currency_code to exist for supplier currency defaulting.');
    }

    $tenant = ($this->makeTenant)(['currency_code' => 'CAD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Provided Currency Supplier',
            'currency_code' => 'EUR',
        ])
        ->assertCreated();

    expect($response->json('data.currency_code'))->toBe('EUR');
});

it('allows tenant to create supplier without affecting other tenant data', function () {
    $tenantA = ($this->makeTenant)(['currency_code' => 'USD']);
    $tenantB = ($this->makeTenant)(['currency_code' => 'USD']);

    ($this->createSupplier)($tenantA, [
        'company_name' => 'Tenant A Supplier',
    ]);

    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermission)($userB, 'purchasing-suppliers-manage');

    $this->actingAs($userB)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Tenant B Supplier',
        ])
        ->assertCreated();

    expect(Supplier::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count())->toBe(1);
    expect(Supplier::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count())->toBe(1);
});

it('returns validation error shape for company_name', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => null,
        ])
        ->assertStatus(422);

    expect($response->json('errors.company_name'))->toBeArray();
});

it('returns validation error shape for email', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Invalid Email Supplier',
            'email' => 'bad',
        ])
        ->assertStatus(422);

    expect($response->json('errors.email'))->toBeArray();
});

it('returns validation error shape for currency_code', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)
        ->postJson('/purchasing/suppliers', [
            'company_name' => 'Bad Currency Supplier',
            'currency_code' => 'US',
        ])
        ->assertStatus(422);

    expect($response->json('errors.currency_code'))->toBeArray();
});
