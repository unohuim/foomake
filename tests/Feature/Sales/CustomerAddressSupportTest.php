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

    $this->addressPayload = function (array $overrides = []): array {
        return array_merge([
            'address_line_1' => '123 King Street West',
            'address_line_2' => 'Suite 400',
            'city' => 'Toronto',
            'region' => 'ON',
            'postal_code' => 'M5V 1J2',
            'country_code' => 'CA',
            'formatted_address' => '123 King Street West, Suite 400, Toronto, ON M5V 1J2, CA',
            'latitude' => '43.643448',
            'longitude' => '-79.386225',
            'address_provider' => 'manual',
            'address_provider_id' => 'manual-addr-123',
        ], $overrides);
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        static $customerCounter = 1;

        $customerId = DB::table('customers')->insertGetId(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $customerCounter,
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

        $customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->fetchCustomer = function (int $customerId): object {
        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->postStore = function (User $user, array $payload = []) {
        return $this->actingAs($user)->postJson(route('sales.customers.store'), $payload);
    };

    $this->patchUpdate = function (User $user, int $customerId, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('sales.customers.update', $customerId), $payload);
    };

    $this->getShow = function (User $user, int $customerId) {
        return $this->actingAs($user)->get(route('sales.customers.show', $customerId));
    };

    $this->getIndex = function (User $user) {
        return $this->actingAs($user)->get(route('sales.customers.index'));
    };
});

it('1. create accepts all nullable address fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $payload = array_merge([
        'name' => 'Address Ready Customer',
        'notes' => 'Has a full address',
    ], ($this->addressPayload)());

    $response = ($this->postStore)($user, $payload);
    $customerId = (int) ($response->json('data.id') ?? 0);
    $customer = ($this->fetchCustomer)($customerId);

    $response->assertCreated();

    expect($customer->address_line_1)->toBe($payload['address_line_1'])
        ->and($customer->address_line_2)->toBe($payload['address_line_2'])
        ->and($customer->city)->toBe($payload['city'])
        ->and($customer->region)->toBe($payload['region'])
        ->and($customer->postal_code)->toBe($payload['postal_code'])
        ->and($customer->country_code)->toBe($payload['country_code'])
        ->and($customer->formatted_address)->toBe($payload['formatted_address'])
        ->and((string) $customer->latitude)->toContain('43.643448')
        ->and((string) $customer->longitude)->toContain('-79.386225')
        ->and($customer->address_provider)->toBe($payload['address_provider'])
        ->and($customer->address_provider_id)->toBe($payload['address_provider_id']);
});

it('2. create stores address_line_1', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Address Line 1 Customer',
    ], ($this->addressPayload)()));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->address_line_1)->toBe('123 King Street West');
});

it('3. create stores address_line_2', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Address Line 2 Customer',
    ], ($this->addressPayload)()));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->address_line_2)->toBe('Suite 400');
});

it('4. create stores city', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'City Customer',
    ], ($this->addressPayload)()));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->city)->toBe('Toronto');
});

it('5. create stores region', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Region Customer',
    ], ($this->addressPayload)()));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->region)->toBe('ON');
});

it('6. create stores postal_code', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Postal Code Customer',
    ], ($this->addressPayload)()));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->postal_code)->toBe('M5V 1J2');
});

it('7. create stores valid country_code', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Country Code Customer',
    ], ($this->addressPayload)([
        'country_code' => 'CA',
    ])));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->country_code)->toBe('CA');
});

it('8. create rejects invalid country_code', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, array_merge([
        'name' => 'Invalid Country Code Customer',
    ], ($this->addressPayload)([
        'country_code' => 'CAN',
    ])))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['country_code']);
});

it('9. create stores formatted_address', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $formattedAddress = '123 King Street West, Suite 400, Toronto, ON M5V 1J2, CA';

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Formatted Address Customer',
    ], ($this->addressPayload)([
        'formatted_address' => $formattedAddress,
    ])));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->formatted_address)->toBe($formattedAddress);
});

it('10. create stores latitude', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Latitude Customer',
    ], ($this->addressPayload)([
        'latitude' => '43.700110',
    ])));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect((float) $customer->latitude)->toBe(43.70011);
});

it('11. create stores longitude', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Longitude Customer',
    ], ($this->addressPayload)([
        'longitude' => '-79.416300',
    ])));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect((float) $customer->longitude)->toBe(-79.4163);
});

it('12. create stores address_provider', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Address Provider Customer',
    ], ($this->addressPayload)([
        'address_provider' => 'ops-entry',
    ])));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->address_provider)->toBe('ops-entry');
});

it('13. create stores address_provider_id', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Address Provider Id Customer',
    ], ($this->addressPayload)([
        'address_provider_id' => 'provider-customer-42',
    ])));

    $response->assertCreated();

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    expect($customer->address_provider_id)->toBe('provider-customer-42');
});

it('14. update accepts all address fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Updatable Customer',
    ]);

    $payload = array_merge([
        'name' => 'Updated Address Customer',
        'status' => 'inactive',
        'notes' => 'Updated with full address',
    ], ($this->addressPayload)());

    $response = ($this->patchUpdate)($user, $customer->id, $payload);
    $updatedCustomer = ($this->fetchCustomer)($customer->id);

    $response->assertOk();

    expect($updatedCustomer->address_line_1)->toBe($payload['address_line_1'])
        ->and($updatedCustomer->address_line_2)->toBe($payload['address_line_2'])
        ->and($updatedCustomer->city)->toBe($payload['city'])
        ->and($updatedCustomer->region)->toBe($payload['region'])
        ->and($updatedCustomer->postal_code)->toBe($payload['postal_code'])
        ->and($updatedCustomer->country_code)->toBe($payload['country_code'])
        ->and($updatedCustomer->formatted_address)->toBe($payload['formatted_address'])
        ->and((float) $updatedCustomer->latitude)->toBe(43.643448)
        ->and((float) $updatedCustomer->longitude)->toBe(-79.386225)
        ->and($updatedCustomer->address_provider)->toBe($payload['address_provider'])
        ->and($updatedCustomer->address_provider_id)->toBe($payload['address_provider_id']);
});

it('15. update can clear address fields to null', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant, array_merge([
        'name' => 'Clearable Customer',
    ], ($this->addressPayload)()));

    $response = ($this->patchUpdate)($user, $customer->id, [
        'name' => 'Clearable Customer',
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
    ]);

    $updatedCustomer = ($this->fetchCustomer)($customer->id);

    $response->assertOk();

    expect($updatedCustomer->address_line_1)->toBeNull()
        ->and($updatedCustomer->address_line_2)->toBeNull()
        ->and($updatedCustomer->city)->toBeNull()
        ->and($updatedCustomer->region)->toBeNull()
        ->and($updatedCustomer->postal_code)->toBeNull()
        ->and($updatedCustomer->country_code)->toBeNull()
        ->and($updatedCustomer->formatted_address)->toBeNull()
        ->and($updatedCustomer->latitude)->toBeNull()
        ->and($updatedCustomer->longitude)->toBeNull()
        ->and($updatedCustomer->address_provider)->toBeNull()
        ->and($updatedCustomer->address_provider_id)->toBeNull();
});

it('16. update rejects invalid latitude', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant);

    ($this->patchUpdate)($user, $customer->id, [
        'name' => 'Latitude Validation Customer',
        'status' => 'active',
        'latitude' => 'north',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});

it('17. update rejects invalid longitude', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant);

    ($this->patchUpdate)($user, $customer->id, [
        'name' => 'Longitude Validation Customer',
        'status' => 'active',
        'longitude' => 'west',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['longitude']);
});

it('18. detail view displays address fields', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-view');
    $customer = ($this->createCustomer)($tenant, array_merge([
        'name' => 'Readable Address Customer',
    ], ($this->addressPayload)()));

    ($this->getShow)($user, $customer->id)
        ->assertOk()
        ->assertSee('123 King Street West')
        ->assertSee('Suite 400')
        ->assertSee('Toronto')
        ->assertSee('ON')
        ->assertSee('M5V 1J2')
        ->assertSee('CA');
});

it('19. index payload includes address summary when customer has an address', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->createCustomer)($tenant, array_merge([
        'name' => 'Indexed Address Customer',
    ], ($this->addressPayload)([
        'address_line_2' => null,
        'formatted_address' => '123 King Street West, Toronto, ON M5V 1J2, CA',
    ])));

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('123 King Street West, Toronto, ON M5V 1J2, CA');
});

it('19a. inactive customer address summary is not shown on index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->createCustomer)($tenant, array_merge([
        'name' => 'Inactive Indexed Address Customer',
        'status' => 'inactive',
    ], ($this->addressPayload)([
        'address_line_2' => null,
        'formatted_address' => '987 Queen Street West, Toronto, ON M6J 1H1, CA',
    ])));

    ($this->getIndex)($user)
        ->assertOk()
        ->assertDontSee('Inactive Indexed Address Customer')
        ->assertDontSee('987 Queen Street West, Toronto, ON M6J 1H1, CA');
});

it('20. other-tenant customer address is inaccessible', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-view');
    $customer = ($this->createCustomer)($otherTenant, array_merge([
        'name' => 'Hidden Address Customer',
    ], ($this->addressPayload)()));

    ($this->getShow)($user, $customer->id)
        ->assertNotFound();
});

it('21. ajax validation returns 422 json for invalid address payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Invalid Address Payload Customer',
    ], ($this->addressPayload)([
        'country_code' => 'CAN',
    ])));

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                'country_code',
            ],
        ]);

    expect(str_starts_with((string) $response->headers->get('content-type'), 'application/json'))->toBeTrue();
});

it('22. successful create response includes address data', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    ($this->postStore)($user, array_merge([
        'name' => 'Structured Create Address Customer',
    ], ($this->addressPayload)()))
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'status',
                'notes',
                'address_line_1',
                'address_line_2',
                'city',
                'region',
                'postal_code',
                'country_code',
                'formatted_address',
                'latitude',
                'longitude',
                'address_provider',
                'address_provider_id',
                'show_url',
            ],
        ]);
});

it('23. successful update response includes address data', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Structured Update Address Customer',
    ]);

    ($this->patchUpdate)($user, $customer->id, array_merge([
        'name' => 'Structured Update Address Customer',
        'status' => 'inactive',
        'notes' => 'Now has address data',
    ], ($this->addressPayload)()))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'status',
                'notes',
                'address_line_1',
                'address_line_2',
                'city',
                'region',
                'postal_code',
                'country_code',
                'formatted_address',
                'latitude',
                'longitude',
                'address_provider',
                'address_provider_id',
                'show_url',
            ],
        ]);
});

it('24. create still defaults status to active', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');

    $response = ($this->postStore)($user, array_merge([
        'name' => 'Default Status Address Customer',
    ], ($this->addressPayload)()));

    $customer = ($this->fetchCustomer)((int) ($response->json('data.id') ?? 0));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'active');

    expect($customer->status)->toBe('active');
});

it('25. guest cannot view customer detail with address data', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant, array_merge([
        'name' => 'Guest Blocked Detail Customer',
    ], ($this->addressPayload)()));

    $this->get(route('sales.customers.show', $customer->id))
        ->assertRedirect(route('login'));
});

it('26. user without permission is denied customer detail with address data', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, array_merge([
        'name' => 'Forbidden Detail Customer',
    ], ($this->addressPayload)()));

    ($this->getShow)($user, $customer->id)
        ->assertForbidden();
});

it('27. guest cannot create customer with address data', function () {
    $response = $this->postJson(route('sales.customers.store'), array_merge([
        'name' => 'Guest Create Address Customer',
    ], ($this->addressPayload)()));

    $response->assertUnauthorized();
});

it('28. user without permission is denied create customer with address data', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->postStore)($user, array_merge([
        'name' => 'Forbidden Create Address Customer',
    ], ($this->addressPayload)()))
        ->assertForbidden();
});

it('29. guest cannot update customer address', function () {
    $tenant = ($this->makeTenant)();
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Guest Update Address Customer',
    ]);

    $response = $this->patchJson(route('sales.customers.update', $customer->id), array_merge([
        'name' => 'Guest Update Address Customer',
        'status' => 'active',
    ], ($this->addressPayload)()));

    $response->assertUnauthorized();
});

it('30. user without permission is denied customer address update', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant, [
        'name' => 'Forbidden Update Address Customer',
    ]);

    ($this->patchUpdate)($user, $customer->id, array_merge([
        'name' => 'Forbidden Update Address Customer',
        'status' => 'active',
    ], ($this->addressPayload)()))
        ->assertForbidden();
});

it('31. other-tenant customer address update is inaccessible', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'sales-customers-manage');
    $customer = ($this->createCustomer)($otherTenant, [
        'name' => 'Cross Tenant Update Address Customer',
    ]);

    ($this->patchUpdate)($user, $customer->id, array_merge([
        'name' => 'Cross Tenant Update Address Customer',
        'status' => 'active',
    ], ($this->addressPayload)()))
        ->assertNotFound();
});
