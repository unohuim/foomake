<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Purchasing\SupplierDeleteGuard;

beforeEach(function () {
    $this->makeTenant = function (array $attributes = []) {
        static $tenantCounter = 1;

        $currencyCode = $attributes['currency_code'] ?? null;
        unset($attributes['currency_code']);

        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Tenant ' . $tenantCounter,
        ], $attributes));

        if ($currencyCode !== null) {
            $tenant->forceFill(['currency_code' => $currencyCode])->save();
        }

        $tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []) {
        static $userCounter = 1;

        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $userCounter,
            'email' => 'user' . $userCounter . '@example.test',
            'email_verified_at' => null,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $userCounter++;

        return $user;
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
        static $supplierCounter = 1;

        $supplier = Supplier::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $supplierCounter,
            'url' => null,
            'phone' => null,
            'email' => null,
            'currency_code' => null,
        ], $attributes));

        $supplierCounter++;

        return $supplier;
    };

    $this->updateSupplier = function (User $user, Supplier $supplier, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('purchasing.suppliers.update', $supplier), $payload);
    };

    $this->deleteSupplier = function (User $user, Supplier $supplier) {
        return $this->actingAs($user)->deleteJson(route('purchasing.suppliers.destroy', $supplier));
    };

    $this->getSuppliersIndex = function (User $user) {
        return $this->actingAs($user)->get('/purchasing/suppliers');
    };

    $this->assertStableUpdateErrors = function ($response) {
        $response->assertJsonStructure([
            'errors' => [
                'company_name',
                'url',
                'phone',
                'email',
                'currency_code',
            ],
        ]);

        expect($response->json('errors.company_name'))->toBeArray()
            ->and($response->json('errors.url'))->toBeArray()
            ->and($response->json('errors.phone'))->toBeArray()
            ->and($response->json('errors.email'))->toBeArray()
            ->and($response->json('errors.currency_code'))->toBeArray();
    };
});

it('requires authentication for supplier update', function () {
    $tenant = ($this->makeTenant)();
    $supplier = ($this->createSupplier)($tenant);

    $this->patchJson(route('purchasing.suppliers.update', $supplier), [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'USD',
    ])->assertUnauthorized();
});

it('requires authentication for supplier delete', function () {
    $tenant = ($this->makeTenant)();
    $supplier = ($this->createSupplier)($tenant);

    $this->deleteJson(route('purchasing.suppliers.destroy', $supplier))
        ->assertUnauthorized();
});

it('denies supplier update without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant);

    ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'USD',
    ])->assertForbidden();
});

it('denies supplier delete without manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant);

    ($this->deleteSupplier)($user, $supplier)->assertForbidden();
});

it('does not mutate supplier when update is forbidden', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Original Supplier',
    ]);

    ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'USD',
    ])->assertForbidden();

    $unchanged = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);
    expect($unchanged->company_name)->toBe('Original Supplier');
});

it('does not delete supplier when delete is forbidden', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Protected Supplier',
    ]);

    ($this->deleteSupplier)($user, $supplier)->assertForbidden();

    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeTrue();
});

it('updates a supplier and returns JSON', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Original Supplier',
        'url' => null,
        'phone' => null,
        'email' => null,
        'currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'EUR',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'company_name',
                'url',
                'phone',
                'email',
                'currency_code',
            ],
        ])
        ->assertJsonPath('data.id', $supplier->id)
        ->assertJsonPath('data.company_name', 'Updated Supplier')
        ->assertJsonPath('data.currency_code', 'EUR');
});

it('persists update changes to the database', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Original Supplier',
        'url' => null,
        'phone' => null,
        'email' => null,
        'currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'EUR',
    ])->assertOk();

    $updated = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);

    expect($updated->company_name)->toBe('Updated Supplier')
        ->and($updated->url)->toBe('https://example.test')
        ->and($updated->phone)->toBe('555-4567')
        ->and($updated->email)->toBe('buyer@example.test')
        ->and($updated->currency_code)->toBe('EUR');
});

it('reflects update changes in the suppliers index', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Index Supplier',
        'currency_code' => 'USD',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Index Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'EUR',
    ])->assertOk();

    $updated = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);

    expect($updated->company_name)->toBe('Updated Index Supplier')
        ->and($updated->currency_code)->toBe('EUR');

    ($this->getSuppliersIndex)($user)
        ->assertOk()
        ->assertSee('Updated Index Supplier')
        ->assertSee('EUR');
});

it('validates company_name required on update', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->updateSupplier)($user, $supplier, [
        'company_name' => '',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'USD',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['company_name']);

    ($this->assertStableUpdateErrors)($response);
});

it('validates email format on update when provided', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'not-an-email',
        'currency_code' => 'USD',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    ($this->assertStableUpdateErrors)($response);
});

it('does not mutate supplier when email validation fails', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Email Supplier',
        'email' => 'buyer@example.test',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Email Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'not-an-email',
        'currency_code' => 'USD',
    ])->assertStatus(422);

    $unchanged = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);
    expect($unchanged->email)->toBe('buyer@example.test');
});

it('validates currency_code length on update when provided', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'US',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['currency_code']);

    ($this->assertStableUpdateErrors)($response);
});

it('allows nullable fields to be cleared on update', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Nullable Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'EUR',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Nullable Supplier',
        'url' => null,
        'phone' => null,
        'email' => null,
        'currency_code' => null,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.url', null)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.currency_code', null);

    $updated = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);

    expect($updated->url)->toBeNull()
        ->and($updated->phone)->toBeNull()
        ->and($updated->email)->toBeNull()
        ->and($updated->currency_code)->toBeNull();
});

it('returns not found when updating another tenant supplier', function () {
    $tenantA = ($this->makeTenant)(['currency_code' => 'USD']);
    $tenantB = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenantA);
    $supplier = ($this->createSupplier)($tenantB, [
        'company_name' => 'Other Tenant Supplier',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->updateSupplier)($user, $supplier, [
        'company_name' => 'Updated Supplier',
        'url' => 'https://example.test',
        'phone' => '555-4567',
        'email' => 'buyer@example.test',
        'currency_code' => 'USD',
    ])->assertNotFound();

    $unchanged = Supplier::withoutGlobalScopes()->findOrFail($supplier->id);
    expect($unchanged->company_name)->toBe('Other Tenant Supplier');
});

it('deletes a supplier and returns JSON', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteSupplier)($user, $supplier)
        ->assertOk()
        ->assertJsonPath('message', 'Deleted.');

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeFalse();
    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeFalse();
});

it('reflects deletion in the suppliers index', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Delete Index Supplier',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->deleteSupplier)($user, $supplier)
        ->assertOk()
        ->assertJsonPath('message', 'Deleted.');

    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeFalse();

    ($this->getSuppliersIndex)($user)
        ->assertOk()
        ->assertDontSee('Delete Index Supplier');
});

it('returns not found when deleting another tenant supplier', function () {
    $tenantA = ($this->makeTenant)(['currency_code' => 'USD']);
    $tenantB = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenantA);
    $supplier = ($this->createSupplier)($tenantB);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteSupplier)($user, $supplier)->assertNotFound();

    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeTrue();
});

it('blocks deletion when supplier is linked to materials', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Linked Supplier',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    app()->bind(SupplierDeleteGuard::class, function () {
        return new class implements SupplierDeleteGuard {
            public function isLinkedToMaterials(Supplier $supplier): bool
            {
                return true;
            }
        };
    });

    $response = ($this->deleteSupplier)($user, $supplier)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Supplier cannot be deleted because it is linked to materials.');

    expect($response->json('errors'))->toBeNull();
    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeTrue();
});

it('allows deletion when delete guard is not blocking', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Unlinked Supplier',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    ($this->deleteSupplier)($user, $supplier)
        ->assertOk()
        ->assertJsonPath('message', 'Deleted.');

    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeFalse();
});
