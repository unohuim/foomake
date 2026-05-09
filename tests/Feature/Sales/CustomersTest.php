<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        $tenant = Tenant::query()->create(array_merge([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ], $attributes));

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        $user = User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'user' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ], $attributes));

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'role-' . $this->roleCounter]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantSuperAdmin = function (User $user): void {
        $role = Role::query()->firstOrCreate(['name' => 'super-admin']);

        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        static $customerCounter = 1;

        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $customerCounter,
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->getIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.customers.index'));
    };

    $this->getShow = function (User $user, int $customerId) {
        return $this->actingAs($user)->get(route('sales.customers.show', $customerId));
    };

    $this->postStore = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.customers.store'), $payload);
    };

    $this->patchUpdate = function (User $user, int $customerId, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('sales.customers.update', $customerId), $payload);
    };

    $this->deleteDestroy = function (User $user, int $customerId) {
        return $this->actingAs($user)->deleteJson(route('sales.customers.destroy', $customerId));
    };
});

it('1. authenticated user can view customers index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->getIndex)($user)
        ->assertOk();
});

it('2. guest cannot view customers index', function () {
    $this->get(route('sales.customers.index'))
        ->assertRedirect(route('login'));
});

it('3. user without permission is denied index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->getIndex)($user)
        ->assertForbidden();
});

it('4. user with sales-customers-manage can view index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('Customers');
});

it('5. customer can be created via AJAX', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, [
        'name' => 'Northwind Foods',
        'status' => 'inactive',
        'notes' => 'Primary CRM account',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Northwind Foods')
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.notes', 'Primary CRM account');

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'Northwind Foods',
        'status' => 'inactive',
        'notes' => 'Primary CRM account',
    ]);
});

it('6. name is required on create', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, [
        'name' => '',
        'status' => 'active',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('7. status defaults correctly on create', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, [
        'name' => 'Default Status Customer',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'Default Status Customer',
        'status' => 'active',
    ]);
});

it('8. status must be valid on create', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, [
        'name' => 'Invalid Status Customer',
        'status' => 'deleted',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('9. notes are nullable', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, [
        'name' => 'Nullable Notes Customer',
        'status' => 'active',
        'notes' => null,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.notes', null);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'Nullable Notes Customer',
        'notes' => null,
    ]);
});

it('10. customer is tenant-scoped on create', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, [
        'tenant_id' => $otherTenant->id,
        'name' => 'Tenant Scoped Customer',
        'status' => 'active',
    ])->assertCreated();

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'name' => 'Tenant Scoped Customer',
    ]);

    $this->assertDatabaseMissing('customers', [
        'tenant_id' => $otherTenant->id,
        'name' => 'Tenant Scoped Customer',
    ]);
});

it('11. other-tenant customers are not visible in index', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->createCustomer)($tenant, ['name' => 'Visible Customer']);
    ($this->createCustomer)($otherTenant, ['name' => 'Hidden Customer']);

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('Visible Customer')
        ->assertDontSee('Hidden Customer');
});

it('11a. inactive customers are not visible in index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->createCustomer)($tenant, [
        'name' => 'Active Customer',
        'status' => 'active',
    ]);
    ($this->createCustomer)($tenant, [
        'name' => 'Inactive Customer',
        'status' => 'inactive',
    ]);

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('Active Customer')
        ->assertDontSee('Inactive Customer');
});

it('12. customer detail page loads for same tenant', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-view');
    $customer = ($this->createCustomer)($tenant, ['name' => 'Detail Customer']);

    ($this->getShow)($user, $customer->id)
        ->assertOk()
        ->assertSee('Detail Customer');
});

it('13. other-tenant customer detail returns 404', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-view');
    $customer = ($this->createCustomer)($otherTenant, ['name' => 'Other Tenant Customer']);

    ($this->getShow)($user, $customer->id)
        ->assertNotFound();
});

it('14. customer can be updated via AJAX', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Original Customer',
        'status' => 'active',
        'notes' => null,
    ]);

    ($this->patchUpdate)($user, $customer->id, [
        'name' => 'Updated Customer',
        'status' => 'inactive',
        'notes' => 'Updated notes',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Customer')
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.notes', 'Updated notes');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'tenant_id' => $tenant->id,
        'name' => 'Updated Customer',
        'status' => 'inactive',
        'notes' => 'Updated notes',
    ]);
});

it('15. name is required on update', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant);

    ($this->patchUpdate)($user, $customer->id, [
        'name' => '',
        'status' => 'active',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('16. status must be valid on update', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant);

    ($this->patchUpdate)($user, $customer->id, [
        'name' => 'Still Valid Name',
        'status' => 'pending',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('17. customer can be archived via destroy action', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant, ['status' => 'active']);

    ($this->deleteDestroy)($user, $customer->id)
        ->assertOk();

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'status' => 'archived',
    ]);
});

it('18. archived customer is not hard deleted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant);

    ($this->deleteDestroy)($user, $customer->id)
        ->assertOk();

    expect(DB::table('customers')->where('id', $customer->id)->exists())->toBeTrue();
});

it('19. archive updates status to archived', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant, ['status' => 'inactive']);

    ($this->deleteDestroy)($user, $customer->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');
});

it('20. navigation shows Sales to Customers only when authorized', function () {
    $tenant = ($this->makeTenant)();
    $unauthorizedUser = ($this->makeUser)($tenant, ['email' => 'unauthorized@example.test']);
    $authorizedUser = ($this->makeUser)($tenant, ['email' => 'authorized@example.test']);

    ($this->grantPermission)($authorizedUser, 'sales-customers-manage');

    $this->actingAs($unauthorizedUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Sales')
        ->assertDontSee(route('sales.customers.index'), false);

    $this->actingAs($authorizedUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Customers')
        ->assertSee(route('sales.customers.index'), false);
});

it('20a. index shows name email address and actions columns only', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->createCustomer)($tenant, [
        'name' => 'Column Check Customer',
        'notes' => 'Index should not show this note',
    ]);

    $response = ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('data-crud-config=', false)
        ->assertDontSee('Index should not show this note');

    preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

    expect($matches)->toHaveKey(1);

    $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

    expect($config['headers'] ?? [])->toBe([
        'name' => 'Name',
        'email' => 'Email',
        'address_summary' => 'Address',
    ])
        ->and($config['columns'] ?? [])->toBe(['name', 'email', 'address_summary'])
        ->and(array_key_exists('status', $config['headers'] ?? []))->toBeFalse()
        ->and(array_key_exists('notes', $config['headers'] ?? []))->toBeFalse();
});

it('21. validation errors return 422 JSON', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, [
        'name' => '',
        'status' => 'nope',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                'name',
                'status',
            ],
        ]);

    $contentType = (string) $response->headers->get('content-type');

    expect(str_starts_with($contentType, 'application/json'))->toBeTrue();
});

it('22. successful AJAX responses return expected structure', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, [
        'name' => 'Structured Response Customer',
        'status' => 'active',
        'notes' => 'Structured notes',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'status',
                'notes',
                'show_url',
            ],
        ]);
});

it('23. tenant_id is automatically assigned correctly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, [
        'name' => 'Automatic Tenant Customer',
        'status' => 'active',
    ]);

    $customerId = (int) ($response->json('data.id') ?? 0);

    $this->assertDatabaseHas('customers', [
        'id' => $customerId,
        'tenant_id' => $tenant->id,
    ]);
});

it('24. super-admin bypass works if Gate before exists', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantSuperAdmin)($user);

    ($this->getIndex)($user)
        ->assertOk();

    ($this->postStore)($user, [
        'name' => 'Super Admin Customer',
        'status' => 'active',
    ])->assertCreated();
});
