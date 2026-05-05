<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    };
});

it('allows users with inventory-materials-view permission to view materials index', function () {
    $permission = Permission::create([
        'slug' => 'inventory-materials-view',
    ]);

    $role = Role::create([
        'name' => 'Inventory',
    ]);

    $role->permissions()->attach($permission->id);

    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $user->roles()->attach($role->id);

    // Guard against missing gate registration
    expect(
        Gate::forUser($user)->allows('inventory-materials-view')
    )->toBeTrue();

    $response = $this->actingAs($user)->get('/materials');

    $response->assertOk();
    $response->assertSee('Materials');
});

it('forbids users without inventory-materials-view permission from viewing materials index', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    expect(
        Gate::forUser($user)->allows('inventory-materials-view')
    )->toBeFalse();

    $response = $this->actingAs($user)->get('/materials');

    $response->assertForbidden();
});

it('includes the shared navigation state refresh url in the materials index payload', function () {
    $customerPermission = Permission::firstOrCreate([
        'slug' => 'inventory-materials-view',
    ]);
    $materialsManagePermission = Permission::firstOrCreate([
        'slug' => 'inventory-materials-manage',
    ]);
    $salesOrdersPermission = Permission::firstOrCreate([
        'slug' => 'sales-sales-orders-manage',
    ]);
    $customersRole = Role::firstOrCreate([
        'name' => 'Materials Nav',
    ]);

    $customersRole->permissions()->syncWithoutDetaching([
        $customerPermission->id,
        $materialsManagePermission->id,
        $salesOrdersPermission->id,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $user->roles()->attach($customersRole->id);

    \App\Models\Customer::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Existing Customer',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get('/materials');

    $response->assertOk()
        ->assertSee('materials-index-payload', false);

    $payload = ($this->extractPayload)($response, 'materials-index-payload');

    expect($payload['navigationStateUrl'] ?? null)->toBe(url('/navigation/state'));
});
