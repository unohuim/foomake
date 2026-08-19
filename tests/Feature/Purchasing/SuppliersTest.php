<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Purchasing\SupplierDeleteGuard;
use Illuminate\Support\Facades\Route;

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
            'email_verified_at' => now(),
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

    $this->getSuppliersList = function (User $user, array $query = []) {
        return $this->actingAs($user)->getJson(route('purchasing.suppliers.list', $query));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config=(['\"])(.*?)\\1/s", $response->getContent(), $matches);

        expect($matches)->toHaveKey(2);

        return json_decode(html_entity_decode($matches[2], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
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

it('requires authentication for supplier index', function () {
    $this->get('/purchasing/suppliers')->assertRedirect('/?auth=login');
});

it('denies supplier index without view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->getSuppliersIndex)($user)->assertForbidden();
});

it('allows supplier index with view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getSuppliersIndex)($user)->assertOk();
});

it('renders the suppliers index as a configured crud page module shell', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getSuppliersIndex)($user)
        ->assertOk()
        ->assertSee('data-page="purchasing-suppliers-index"', false)
        ->assertSee('data-payload="purchasing-suppliers-index-payload"', false)
        ->assertSee('data-crud-config=', false)
        ->assertSee('data-crud-root', false);
});

it('does not hardcode suppliers toolbar or empty-state markup in blade', function () {
    $view = file_get_contents(resource_path('views/purchasing/suppliers/index.blade.php'));

    expect($view)->not->toContain('data-crud-toolbar')
        ->and($view)->not->toContain('data-crud-toolbar-create-button')
        ->and($view)->not->toContain('Create Supplier')
        ->and($view)->not->toContain('No suppliers yet')
        ->and($view)->not->toContain('<table');
});

it('configures suppliers crud endpoints without supplier import or export endpoints', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect($config['resource'])->toBe('suppliers')
        ->and($config['endpoints']['list'])->toBe(route('purchasing.suppliers.list'))
        ->and($config['endpoints']['create'])->toBe(route('purchasing.suppliers.store'))
        ->and($config['endpoints']['update'])->toBe(url('/purchasing/suppliers/{id}'))
        ->and($config['endpoints']['delete'])->toBe(url('/purchasing/suppliers/{id}'))
        ->and($config['endpoints'])->not->toHaveKey('importPreview')
        ->and($config['endpoints'])->not->toHaveKey('importStore')
        ->and($config['endpoints'])->not->toHaveKey('export');
});

it('configures suppliers crud headers in the requested order', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect($config['columns'])->toBe(['company_name', 'phone', 'email', 'currency_code'])
        ->and($config['headers'])->toBe([
            'company_name' => 'Supplier name',
            'phone' => 'Phone',
            'email' => 'Email',
            'currency_code' => 'Currency',
        ]);
});

it('configures supplier name as a detail link using the detail url contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect($config['detailUrlTemplate'])->toBe(url('/purchasing/suppliers/{id}'))
        ->and($config['rowDisplay']['columns']['company_name']['kind'])->toBe('linked-text')
        ->and($config['rowDisplay']['columns']['company_name']['urlExpression'])->toBe('record.show_url');
});

it('configures add edit and archive supplier actions', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect($config['permissions']['showCreate'])->toBeTrue()
        ->and($config['labels']['createTitle'])->toBe('Add New Supplier')
        ->and($config['actions'])->toMatchArray([
            ['id' => 'edit', 'label' => 'Edit', 'tone' => 'default'],
            ['id' => 'archive', 'label' => 'Archive', 'tone' => 'warning'],
        ]);
});

it('hides supplier add and row actions from view-only users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect($config['permissions']['showCreate'])->toBeFalse()
        ->and($config['actions'])->toBe([]);
});

it('hides supplier import and export buttons when supplier endpoints do not exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect(Route::has('purchasing.suppliers.import.preview'))->toBeFalse()
        ->and(Route::has('purchasing.suppliers.import.store'))->toBeFalse()
        ->and(Route::has('purchasing.suppliers.export'))->toBeFalse()
        ->and($config['permissions']['showImport'])->toBeFalse()
        ->and($config['permissions']['showExport'])->toBeFalse();
});

it('does not introduce supplier import or export routes', function () {
    $supplierRouteNames = collect(Route::getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => is_string($name) && str_starts_with($name, 'purchasing.suppliers.'))
        ->values()
        ->all();

    expect($supplierRouteNames)->not->toContain('purchasing.suppliers.import.preview')
        ->and($supplierRouteNames)->not->toContain('purchasing.suppliers.import.store')
        ->and($supplierRouteNames)->not->toContain('purchasing.suppliers.export');
});

it('requires authentication for supplier list endpoint', function () {
    $this->getJson(route('purchasing.suppliers.list'))->assertUnauthorized();
});

it('denies supplier list endpoint without view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->getSuppliersList)($user)->assertForbidden();
});

it('allows supplier list endpoint with view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getSuppliersList)($user)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('returns supplier list rows with detail urls and visible fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'North Supplier',
        'phone' => '555-0100',
        'email' => 'north@example.test',
        'currency_code' => 'CAD',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getSuppliersList)($user)
        ->assertOk()
        ->assertJsonPath('data.0.id', $supplier->id)
        ->assertJsonPath('data.0.company_name', 'North Supplier')
        ->assertJsonPath('data.0.phone', '555-0100')
        ->assertJsonPath('data.0.email', 'north@example.test')
        ->assertJsonPath('data.0.currency_code', 'CAD')
        ->assertJsonPath('data.0.show_url', route('purchasing.suppliers.show', $supplier));
});

it('supplier list endpoint is tenant scoped', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->createSupplier)($tenant, ['company_name' => 'Visible Supplier']);
    ($this->createSupplier)($otherTenant, ['company_name' => 'Hidden Supplier']);
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getSuppliersList)($user)->assertOk();

    expect(collect($response->json('data'))->pluck('company_name')->all())
        ->toContain('Visible Supplier')
        ->not->toContain('Hidden Supplier');
});

it('supplier list endpoint searches visible supplier fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->createSupplier)($tenant, ['company_name' => 'Alpha Foods', 'email' => 'alpha@example.test']);
    ($this->createSupplier)($tenant, ['company_name' => 'Beta Foods', 'email' => 'beta@example.test']);
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getSuppliersList)($user, ['search' => 'beta'])->assertOk();

    expect(collect($response->json('data'))->pluck('company_name')->all())->toBe(['Beta Foods']);
});

it('supplier list endpoint sorts by supplier name', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->createSupplier)($tenant, ['company_name' => 'Beta Foods']);
    ($this->createSupplier)($tenant, ['company_name' => 'Alpha Foods']);
    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    $response = ($this->getSuppliersList)($user, [
        'sort' => 'company_name',
        'direction' => 'asc',
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('company_name')->all())->toBe(['Alpha Foods', 'Beta Foods']);
});

it('supplier list endpoint rejects unsupported sort columns', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->getSuppliersList)($user, ['sort' => 'url'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

it('supplier create response supports detail url template redirects', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = $this->actingAs($user)->postJson(route('purchasing.suppliers.store'), [
        'company_name' => 'Redirect Supplier',
        'currency_code' => 'usd',
    ])->assertCreated();

    $supplier = Supplier::query()->where('company_name', 'Redirect Supplier')->firstOrFail();

    $response->assertJsonPath('data.id', $supplier->id)
        ->assertJsonPath('data.show_url', route('purchasing.suppliers.show', $supplier))
        ->assertJsonPath('data.currency_code', 'USD');
});

it('supplier archive action maps to the existing delete endpoint behavior', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->createSupplier)($tenant, [
        'company_name' => 'Archive Supplier',
    ]);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $config = ($this->extractCrudConfig)(($this->getSuppliersIndex)($user)->assertOk());

    expect($config['actions'][1]['id'])->toBe('archive')
        ->and($config['endpoints']['delete'])->toBe(url('/purchasing/suppliers/{id}'));

    ($this->deleteSupplier)($user, $supplier)->assertOk();

    expect(Supplier::withoutGlobalScopes()->whereKey($supplier->id)->exists())->toBeFalse();
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

    ($this->getSuppliersList)($user)
        ->assertOk()
        ->assertJsonPath('data.0.company_name', 'Updated Index Supplier')
        ->assertJsonPath('data.0.currency_code', 'EUR');
});

it('includes the shared navigation state refresh url in the suppliers index payload', function () {
    $tenant = ($this->makeTenant)(['currency_code' => 'USD']);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');
    ($this->grantPermission)($user, 'purchasing-suppliers-manage');

    $response = ($this->getSuppliersIndex)($user)
        ->assertOk()
        ->assertSee('purchasing-suppliers-index-payload', false);

    $payload = ($this->extractPayload)($response, 'purchasing-suppliers-index-payload');

    expect($payload['navigationStateUrl'] ?? null)->toBe(url('/navigation/state'));
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

    $response = ($this->getSuppliersList)($user)->assertOk();

    expect(collect($response->json('data'))->pluck('company_name')->all())
        ->not->toContain('Delete Index Supplier');
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
