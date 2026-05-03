<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->contactCounter = 1;

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

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $this->customerCounter,
            'status' => 'active',
            'notes' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
            'formatted_address' => null,
            'latitude' => null,
            'longitude' => null,
            'address_provider' => null,
            'address_provider_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createContact = function (Tenant $tenant, int $customerId, array $attributes = []): object {
        $contactId = DB::table('customer_contacts')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'first_name' => 'Contact',
            'last_name' => (string) $this->contactCounter,
            'email' => 'contact' . $this->contactCounter . '@example.test',
            'phone' => '555-010' . $this->contactCounter,
            'role' => 'Buyer',
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        $this->contactCounter++;

        return DB::table('customer_contacts')->where('id', $contactId)->first();
    };

    $this->fetchContact = function (int $contactId): object {
        return DB::table('customer_contacts')->where('id', $contactId)->first();
    };

    $this->primaryContactsForCustomer = function (int $customerId) {
        return DB::table('customer_contacts')
            ->where('customer_id', $customerId)
            ->where('is_primary', true)
            ->orderBy('id')
            ->get();
    };

    $this->getShow = function (User $user, int $customerId) {
        return $this->actingAs($user)->get(route('sales.customers.show', $customerId));
    };

    $this->postStore = function (User $user, int $customerId, array $payload = []) {
        return $this->actingAs($user)->postJson(
            route('sales.customers.contacts.store', $customerId),
            $payload
        );
    };

    $this->patchUpdate = function (User $user, int $customerId, int $contactId, array $payload = []) {
        return $this->actingAs($user)->patchJson(
            route('sales.customers.contacts.update', [$customerId, $contactId]),
            $payload
        );
    };

    $this->patchSetPrimary = function (User $user, int $customerId, int $contactId) {
        return $this->actingAs($user)->patchJson(
            route('sales.customers.contacts.primary.update', [$customerId, $contactId])
        );
    };

    $this->deleteContact = function (User $user, int $customerId, int $contactId) {
        return $this->actingAs($user)->deleteJson(
            route('sales.customers.contacts.destroy', [$customerId, $contactId])
        );
    };

    $this->assertStableErrors = function ($response): void {
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'first_name',
                'last_name',
                'email',
                'phone',
                'role',
                'is_primary',
            ],
        ]);

        expect($response->json('errors.first_name'))->toBeArray()
            ->and($response->json('errors.last_name'))->toBeArray()
            ->and($response->json('errors.email'))->toBeArray()
            ->and($response->json('errors.phone'))->toBeArray()
            ->and($response->json('errors.role'))->toBeArray()
            ->and($response->json('errors.is_primary'))->toBeArray();
    };
});

it('1. customer detail page renders a Contacts section', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Northwind Foods']);

    ($this->grantPermission)($user, 'sales-customers-view');

    ($this->getShow)($user, $customer->id)
        ->assertOk()
        ->assertSee('Contacts')
        ->assertSee('data-section="customer-contacts"', false);
});

it('2. Contacts section lists existing contacts for that customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, ['name' => 'Northwind Foods']);
    $otherCustomer = ($this->createCustomer)($tenant, ['name' => 'Other Customer']);

    ($this->grantPermission)($user, 'sales-customers-view');

    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'email' => 'jane@example.test',
        'role' => 'Purchasing Lead',
        'is_primary' => true,
    ]);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Moe',
        'last_name' => 'Operator',
        'email' => 'moe@example.test',
        'role' => 'Operations',
    ]);
    ($this->createContact)($tenant, $otherCustomer->id, [
        'first_name' => 'Hidden',
        'last_name' => 'Contact',
        'email' => 'hidden@example.test',
    ]);

    ($this->getShow)($user, $customer->id)
        ->assertOk()
        ->assertSee('Jane Buyer')
        ->assertSee('jane@example.test')
        ->assertSee('Purchasing Lead')
        ->assertSee('Moe Operator')
        ->assertDontSee('Hidden Contact');
});

it('3. Contacts section shows create edit delete and set-primary controls only for users with customer manage permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->getShow)($user, $customer->id)
        ->assertOk()
        ->assertSee('data-contact-action="create"', false)
        ->assertSee('data-contact-action="edit"', false)
        ->assertSee('data-contact-action="delete"', false)
        ->assertSee('data-contact-action="set-primary"', false)
        ->assertSee(trim($contact->first_name . ' ' . $contact->last_name));
});

it('4. Contacts section hides management controls from unauthorized users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-view');
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Visible',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);

    ($this->getShow)($user, $customer->id)
        ->assertOk()
        ->assertSee('Visible Contact')
        ->assertDontSee('data-contact-action="create"', false)
        ->assertDontSee('data-contact-action="edit"', false)
        ->assertDontSee('data-contact-action="delete"', false)
        ->assertDontSee('data-contact-action="set-primary"', false);
});

it('4a. guest cannot view customer detail page', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);

    $this->get(route('sales.customers.show', $customer->id))
        ->assertRedirect(route('login'));
});

it('4b. user without customer view permission cannot view customer detail page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->getShow)($user, $customer->id)
        ->assertForbidden();
});

it('5. Authorized user can create a contact for a customer', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, $customer->id, [
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'email' => 'jane@example.test',
        'phone' => '555-0101',
        'role' => 'Buyer',
    ])->assertCreated()
        ->assertJsonPath('data.first_name', 'Jane')
        ->assertJsonPath('data.last_name', 'Buyer')
        ->assertJsonPath('data.full_name', 'Jane Buyer')
        ->assertJsonPath('data.email', 'jane@example.test')
        ->assertJsonPath('data.phone', '555-0101')
        ->assertJsonPath('data.role', 'Buyer');

    $this->assertDatabaseHas('customer_contacts', [
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'email' => 'jane@example.test',
        'phone' => '555-0101',
        'role' => 'Buyer',
    ]);
});

it('6. First contact for a customer is automatically marked primary', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, $customer->id, [
        'first_name' => 'First',
        'last_name' => 'Contact',
        'email' => 'first@example.test',
    ])->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $contactId = (int) ($response->json('data.id') ?? 0);

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $contactId,
        'customer_id' => $customer->id,
        'is_primary' => true,
    ]);
});

it('7. Second and subsequent contacts are not primary by default', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, $customer->id, [
        'first_name' => 'First',
        'last_name' => 'Contact',
        'email' => 'first@example.test',
    ])->assertCreated();

    $response = ($this->postStore)($user, $customer->id, [
        'first_name' => 'Second',
        'last_name' => 'Contact',
        'email' => 'second@example.test',
    ])->assertCreated()
        ->assertJsonPath('data.is_primary', false);

    $contactId = (int) ($response->json('data.id') ?? 0);

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $contactId,
        'customer_id' => $customer->id,
        'is_primary' => false,
    ]);
});

it('8. Contact email is optional', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, $customer->id, [
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
        'phone' => '555-0102',
        'role' => 'Accounts Payable',
    ])->assertCreated()
        ->assertJsonPath('data.email', null);

    $this->assertDatabaseHas('customer_contacts', [
        'customer_id' => $customer->id,
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
    ]);
});

it('9. Contact first and last names are required', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, $customer->id, [
        'first_name' => '',
        'last_name' => '',
        'email' => 'invalid@example.test',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name']);

    ($this->assertStableErrors)($response);
});

it('10. Invalid email returns 422 JSON', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, $customer->id, [
        'first_name' => 'Invalid',
        'last_name' => 'Email',
        'email' => 'not-an-email',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    expect($response->headers->get('location'))->toBeNull();
    ($this->assertStableErrors)($response);
});

it('11. Create contact returns JSON and does not redirect', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, $customer->id, [
        'first_name' => 'Json',
        'last_name' => 'Contact',
        'email' => 'json@example.test',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'customer_id',
                'tenant_id',
                'first_name',
                'last_name',
                'full_name',
                'email',
                'phone',
                'role',
                'is_primary',
            ],
        ]);

    expect((string) $response->headers->get('content-type'))->toContain('application/json');
    expect($response->headers->get('location'))->toBeNull();
});

it('12. User cannot create a contact for another tenant customer', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($otherTenant);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, $customer->id, [
        'first_name' => 'Cross',
        'last_name' => 'Tenant',
        'email' => 'cross@example.test',
    ])->assertNotFound();
});

it('13. Authorized user can edit contact first name last name email phone and role', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Original',
        'last_name' => 'Name',
        'email' => 'original@example.test',
        'phone' => '555-0000',
        'role' => 'Buyer',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchUpdate)($user, $customer->id, $contact->id, [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.test',
        'phone' => '555-0199',
        'role' => 'Finance',
    ])->assertOk()
        ->assertJsonPath('data.first_name', 'Updated')
        ->assertJsonPath('data.last_name', 'Name')
        ->assertJsonPath('data.full_name', 'Updated Name')
        ->assertJsonPath('data.email', 'updated@example.test')
        ->assertJsonPath('data.phone', '555-0199')
        ->assertJsonPath('data.role', 'Finance');

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $contact->id,
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.test',
        'phone' => '555-0199',
        'role' => 'Finance',
    ]);
});

it('14. Editing a contact preserves primary status unless explicitly changed', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchUpdate)($user, $customer->id, $contact->id, [
        'first_name' => 'Renamed',
        'last_name' => 'Primary',
        'email' => 'primary@example.test',
        'phone' => '555-0103',
        'role' => 'Owner',
    ])->assertOk()
        ->assertJsonPath('data.is_primary', true);

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $contact->id,
        'first_name' => 'Renamed',
        'last_name' => 'Primary',
        'is_primary' => true,
    ]);
});

it('15. Invalid edit payload returns 422 JSON', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->patchUpdate)($user, $customer->id, $contact->id, [
        'first_name' => '',
        'last_name' => '',
        'email' => 'still-not-an-email',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);

    expect($response->headers->get('location'))->toBeNull();
    ($this->assertStableErrors)($response);
});

it('16. User cannot edit another tenant contact', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($otherTenant);
    $contact = ($this->createContact)($otherTenant, $customer->id);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchUpdate)($user, $customer->id, $contact->id, [
        'first_name' => 'Cross',
        'last_name' => 'Tenant',
    ])->assertNotFound();
});

it('17. User can set a non-primary contact as primary', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    $secondary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Secondary',
        'last_name' => 'Contact',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchSetPrimary)($user, $customer->id, $secondary->id)
        ->assertOk()
        ->assertJsonPath('data.id', $secondary->id)
        ->assertJsonPath('data.is_primary', true);

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $secondary->id,
        'is_primary' => true,
    ]);
});

it('18. Setting a new primary unsets the previous primary', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $originalPrimary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    $secondary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Secondary',
        'last_name' => 'Contact',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchSetPrimary)($user, $customer->id, $secondary->id)
        ->assertOk();

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $originalPrimary->id,
        'is_primary' => false,
    ]);

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $secondary->id,
        'is_primary' => true,
    ]);
});

it('19. Customer with contacts can never have more than one primary contact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    $secondary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Secondary',
        'last_name' => 'Contact',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchSetPrimary)($user, $customer->id, $secondary->id)
        ->assertOk();

    expect(($this->primaryContactsForCustomer)($customer->id))->toHaveCount(1);
});

it('20. Customer with contacts can never have zero primary contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $primary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    $secondary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Secondary',
        'last_name' => 'Contact',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->deleteContact)($user, $customer->id, $secondary->id)
        ->assertOk();

    $primaries = ($this->primaryContactsForCustomer)($customer->id);

    expect($primaries)->toHaveCount(1)
        ->and((int) $primaries->first()->id)->toBe((int) $primary->id);
});

it('21. Primary designation is scoped per customer and not tenant-wide', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customerA = ($this->createCustomer)($tenant, ['name' => 'Customer A']);
    $customerB = ($this->createCustomer)($tenant, ['name' => 'Customer B']);
    ($this->createContact)($tenant, $customerA->id, [
        'first_name' => 'A',
        'last_name' => 'Primary',
        'is_primary' => true,
    ]);
    $customerASecondary = ($this->createContact)($tenant, $customerA->id, [
        'first_name' => 'A',
        'last_name' => 'Secondary',
        'is_primary' => false,
    ]);
    $customerBPrimary = ($this->createContact)($tenant, $customerB->id, [
        'first_name' => 'B',
        'last_name' => 'Primary',
        'is_primary' => true,
    ]);
    ($this->createContact)($tenant, $customerB->id, [
        'first_name' => 'B',
        'last_name' => 'Secondary',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchSetPrimary)($user, $customerA->id, $customerASecondary->id)
        ->assertOk();

    $customerAPrimaryIds = DB::table('customer_contacts')
        ->where('customer_id', $customerA->id)
        ->where('is_primary', true)
        ->pluck('id')
        ->all();

    $customerBPrimaryIds = DB::table('customer_contacts')
        ->where('customer_id', $customerB->id)
        ->where('is_primary', true)
        ->pluck('id')
        ->all();

    expect($customerAPrimaryIds)->toBe([$customerASecondary->id])
        ->and($customerBPrimaryIds)->toBe([$customerBPrimary->id]);
});

it('22. Setting primary on another tenant contact is blocked', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($otherTenant);
    $contact = ($this->createContact)($otherTenant, $customer->id, [
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->patchSetPrimary)($user, $customer->id, $contact->id)
        ->assertNotFound();
});

it('23. Authorized user can delete a non-primary contact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    $secondary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Delete',
        'last_name' => 'Me',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->deleteContact)($user, $customer->id, $secondary->id)
        ->assertOk();

    $this->assertDatabaseMissing('customer_contacts', [
        'id' => $secondary->id,
    ]);
});

it('24. User cannot delete the primary contact when other contacts exist', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $primary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Secondary',
        'last_name' => 'Contact',
        'is_primary' => false,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->deleteContact)($user, $customer->id, $primary->id)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Primary contact cannot be deleted while other contacts exist.');

    $this->assertDatabaseHas('customer_contacts', [
        'id' => $primary->id,
    ]);
});

it('25. User can delete the only contact leaving the customer with zero contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Only',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->deleteContact)($user, $customer->id, $contact->id)
        ->assertOk();

    expect(DB::table('customer_contacts')->where('customer_id', $customer->id)->count())->toBe(0);
});

it('26. User cannot delete another tenant contact', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($otherTenant);
    $contact = ($this->createContact)($otherTenant, $customer->id);

    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->deleteContact)($user, $customer->id, $contact->id)
        ->assertNotFound();
});

it('27. Delete contact returns JSON and does not redirect', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, [
        'is_primary' => true,
    ]);

    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->deleteContact)($user, $customer->id, $contact->id);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
        ]);

    expect((string) $response->headers->get('content-type'))->toContain('application/json');
    expect($response->headers->get('location'))->toBeNull();
});

it('28. User without customer manage permission cannot create contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);

    ($this->postStore)($user, $customer->id, [
        'first_name' => 'Forbidden',
        'last_name' => 'Contact',
        'email' => 'forbidden@example.test',
    ])->assertForbidden();
});

it('29. User without customer manage permission cannot edit contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id);

    ($this->patchUpdate)($user, $customer->id, $contact->id, [
        'first_name' => 'Forbidden',
        'last_name' => 'Edit',
    ])->assertForbidden();
});

it('30. User without customer manage permission cannot delete contacts', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id, [
        'is_primary' => true,
    ]);

    ($this->deleteContact)($user, $customer->id, $contact->id)
        ->assertForbidden();
});

it('31. User without customer manage permission cannot set primary contact', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Primary',
        'last_name' => 'Contact',
        'is_primary' => true,
    ]);
    $secondary = ($this->createContact)($tenant, $customer->id, [
        'first_name' => 'Secondary',
        'last_name' => 'Contact',
        'is_primary' => false,
    ]);

    ($this->patchSetPrimary)($user, $customer->id, $secondary->id)
        ->assertForbidden();
});

it('32. Existing customer manage permission is reused and no new contact permission is required', function () {
    $permissionsMatrix = file_get_contents(base_path('docs/PERMISSIONS_MATRIX.md'));
    $roadmap = file_get_contents(base_path('docs/PR3_ROADMAP.md'));
    $provider = file_get_contents(app_path('Providers/AuthServiceProvider.php'));

    expect($permissionsMatrix)->not->toBeFalse()
        ->and($roadmap)->not->toBeFalse()
        ->and($provider)->not->toBeFalse();

    expect(str_contains($permissionsMatrix, 'sales-contacts-manage'))->toBeFalse()
        ->and(str_contains($roadmap, 'sales-contacts-manage'))->toBeFalse()
        ->and(str_contains($provider, 'sales-contacts-manage'))->toBeFalse()
        ->and(str_contains($permissionsMatrix, 'sales-customers-manage'))->toBeTrue();
});

it('33. Migration and schema include customer contact table fields', function () {
    expect(Schema::hasTable('customer_contacts'))->toBeTrue();

    foreach ([
        'tenant_id',
        'customer_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'is_primary',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('customer_contacts', $column))->toBeTrue();
    }
});

it('34. Docs record the customer-contact relationship', function () {
    $inventory = file_get_contents(base_path('docs/ARCHITECTURE_INVENTORY.md'));
    $architecture = file_get_contents(base_path('docs/architecture/sales/CustomerContactPrimaryInvariant.yaml'));

    expect($inventory)->not->toBeFalse()
        ->and($architecture)->not->toBeFalse();

    expect(str_contains($inventory, 'Customer Contact Primary Invariant'))->toBeTrue()
        ->and(str_contains($architecture, 'customer-contact relationship'))->toBeTrue()
        ->and(str_contains($architecture, 'A customer may have multiple contacts'))->toBeTrue();
});

it('35. Docs record the exactly-one-primary-when-contacts-exist invariant', function () {
    $architecture = file_get_contents(base_path('docs/architecture/sales/CustomerContactPrimaryInvariant.yaml'));

    expect($architecture)->not->toBeFalse();

    expect(str_contains($architecture, 'Exactly one contact must be primary when contacts exist.'))->toBeTrue()
        ->and(str_contains($architecture, 'The first contact created for a customer becomes primary automatically.'))->toBeTrue()
        ->and(str_contains($architecture, 'Promoting a new primary contact must unset the previous primary for the same customer.'))->toBeTrue();
});

it('36. Docs record customer detail Contacts section behavior', function () {
    $roadmap = file_get_contents(base_path('docs/PR3_ROADMAP.md'));
    $dbSchema = file_get_contents(base_path('docs/DB_SCHEMA.md'));
    $permissionsMatrix = file_get_contents(base_path('docs/PERMISSIONS_MATRIX.md'));

    expect($roadmap)->not->toBeFalse()
        ->and($dbSchema)->not->toBeFalse()
        ->and($permissionsMatrix)->not->toBeFalse();

    expect(str_contains($roadmap, 'Contacts section'))->toBeTrue()
        ->and(str_contains($roadmap, 'Nested under customer detail'))->toBeTrue()
        ->and(str_contains($dbSchema, 'customer_contacts'))->toBeTrue()
        ->and(str_contains($dbSchema, 'first_name'))->toBeTrue()
        ->and(str_contains($dbSchema, 'last_name'))->toBeTrue()
        ->and(str_contains($permissionsMatrix, 'Customer contacts reuse sales-customers-manage.'))->toBeTrue();
});

it('37. guest cannot create contacts', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);

    $this->postJson(route('sales.customers.contacts.store', $customer->id), [
        'first_name' => 'Guest',
        'last_name' => 'Contact',
    ])->assertUnauthorized();
});

it('38. guest cannot edit contacts', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id);

    $this->patchJson(route('sales.customers.contacts.update', [$customer->id, $contact->id]), [
        'first_name' => 'Guest',
        'last_name' => 'Edit',
    ])->assertUnauthorized();
});

it('39. guest cannot delete contacts', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id);

    $this->deleteJson(route('sales.customers.contacts.destroy', [$customer->id, $contact->id]))
        ->assertUnauthorized();
});

it('40. guest cannot set a primary contact', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant);
    $contact = ($this->createContact)($tenant, $customer->id);

    $this->patchJson(route('sales.customers.contacts.primary.update', [$customer->id, $contact->id]))
        ->assertUnauthorized();
});
