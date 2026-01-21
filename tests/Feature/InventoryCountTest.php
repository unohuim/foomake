<?php

use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeUom = function (): Uom {
        $suffix = Str::random(12);

        $category = UomCategory::query()->forceCreate([
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'uom_category_id' => $category->id,
            'name' => 'Unit ' . $suffix,
            'symbol' => 'u' . $suffix,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $overrides = []): Item {
        return Item::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . Str::random(12),
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ], $overrides));
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->forceCreate([
            'name' => 'role-' . $slug . '-' . Str::random(10),
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->countAdjustmentsFor = function (Tenant $tenant, InventoryCount $count): int {
        return StockMove::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'inventory_count_adjustment')
            ->where('source_type', InventoryCount::class)
            ->where('source_id', $count->id)
            ->count();
    };

    $this->assertCountPayloadShape = function ($response): void {
        $response->assertJsonStructure([
            'count' => [
                'id',
                'counted_at',
                'counted_at_iso',
                'notes',
                'status',
                'posted_at_display',
                'posted_at_iso',
                'lines_count',
                'show_url',
                'update_url',
                'delete_url',
                'post_url',
            ],
        ]);
    };

    $this->assertLinePayloadShape = function ($response): void {
        $response->assertJsonStructure([
            'line' => [
                'id',
                'item_id',
                'item_display',
                'counted_quantity',
                'notes',
                'update_url',
                'delete_url',
            ],
        ]);
    };

    $this->createDraftCountViaApi = function (User $user, array $payload = []): InventoryCount {
        $payload = array_merge([
            'counted_at' => now()->toISOString(),
            'notes' => 'Draft ' . Str::random(10),
        ], $payload);

        $response = $this->actingAs($user)->postJson('/inventory/counts', $payload);

        $response->assertCreated();
        ($this->assertCountPayloadShape)($response);

        $id = (int) $response->json('count.id');
        expect($id)->toBeGreaterThan(0);

        return InventoryCount::query()->findOrFail($id);
    };

    $this->createLineViaApi = function (User $user, InventoryCount $count, array $payload): InventoryCountLine {
        $response = $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', $payload);

        $response->assertCreated();
        ($this->assertLinePayloadShape)($response);

        $id = (int) $response->json('line.id');
        expect($id)->toBeGreaterThan(0);

        return InventoryCountLine::query()->findOrFail($id);
    };

    $this->postCount = function (User $user, InventoryCount $count) {
        return $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/post');
    };
});

it('redirects/blocks guests for inventory count routes', function () {
    $this->get('/inventory/counts')->assertRedirect(route('login'));
    $this->get('/inventory/counts/1')->assertRedirect(route('login'));

    $this->postJson('/inventory/counts', ['counted_at' => now()->toISOString()])->assertUnauthorized();
    $this->patchJson('/inventory/counts/1', ['counted_at' => now()->toISOString(), 'notes' => 'x'])->assertUnauthorized();
    $this->deleteJson('/inventory/counts/1')->assertUnauthorized();

    $this->postJson('/inventory/counts/1/lines', ['item_id' => 1, 'counted_quantity' => '1.000000'])->assertUnauthorized();
    $this->patchJson('/inventory/counts/1/lines/1', ['item_id' => 1, 'counted_quantity' => '2.000000'])->assertUnauthorized();
    $this->deleteJson('/inventory/counts/1/lines/1')->assertUnauthorized();

    $this->postJson('/inventory/counts/1/post')->assertUnauthorized();
});

it('enforces view permission for index/show and execute does not imply view', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($user)->get('/inventory/counts')->assertForbidden();
    $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertForbidden();

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    $this->actingAs($user)->get('/inventory/counts')->assertForbidden();
    $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertForbidden();

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    $this->actingAs($user)->get('/inventory/counts')->assertOk();
    $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();
});

it('requires execute permission for all mutations (count CRUD, line CRUD, post)', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($tenant, $uom);

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($user)->postJson('/inventory/counts', [
        'counted_at' => now()->toISOString(),
        'notes' => 'Nope',
    ])->assertForbidden();

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => now()->toISOString(),
        'notes' => 'Nope',
    ])->assertForbidden();

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id)->assertForbidden();

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ])->assertForbidden();

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/1', [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
    ])->assertForbidden();

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id . '/lines/1')->assertForbidden();

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/post')->assertForbidden();
});

it('allows execute-only users to mutate, while still blocking index/show', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $this->actingAs($user)->get('/inventory/counts')->assertForbidden();

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Execute-only create',
    ]);

    $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertForbidden();

    $updatedAt = now()->addMinutes(10)->seconds(0);
    $resp = $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => $updatedAt->toISOString(),
        'notes' => 'Execute-only update',
    ]);

    $resp->assertOk();
    ($this->assertCountPayloadShape)($resp);
});

it('validates count create/update payloads (update requires counted_at) and updates counted_at on success', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $this->actingAs($user)->postJson('/inventory/counts', [
        'notes' => 'Missing counted_at',
    ])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts', [
        'counted_at' => 'not-a-date',
    ])->assertStatus(422);

    $count = ($this->createDraftCountViaApi)($user);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'notes' => 'Missing counted_at on update',
    ])->assertStatus(422);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => 'not-a-date',
        'notes' => 'x',
    ])->assertStatus(422);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => now()->toISOString(),
        'notes' => ['not', 'a', 'string'],
    ])->assertStatus(422);

    $newCountedAt = now()->addDays(2)->seconds(0);

    $resp = $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => $newCountedAt->toISOString(),
        'notes' => 'Updated',
    ]);

    $resp->assertOk();
    ($this->assertCountPayloadShape)($resp);

    $count->refresh();
    expect($count->counted_at->format('Y-m-d H:i'))->toBe($newCountedAt->format('Y-m-d H:i'));
    expect($resp->json('count.counted_at'))->toBe($newCountedAt->format('Y-m-d H:i'));
    expect($resp->json('count.counted_at_iso'))->toBe($newCountedAt->format('Y-m-d\TH:i'));
});

it('validates line create/update payloads (regex + exists scoped to tenant, required fields on update)', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($tenant, $uom);

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => 999999,
        'counted_quantity' => '1.000000',
    ])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => 'not-a-number',
    ])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '-1.000000',
    ])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '1.1234567',
    ])->assertStatus(422);

    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
        'notes' => 'Initial',
    ]);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'counted_quantity' => '2.000000',
    ])->assertStatus(422);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $item->id,
    ])->assertStatus(422);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $item->id,
        'counted_quantity' => 'nope',
    ])->assertStatus(422);
});

it('creates/updates/deletes draft counts and lines with correct JSON shape + status codes (including item_display)', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'First',
    ]);

    $updatedAt = now()->addMinutes(5)->seconds(0);

    $update = $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => $updatedAt->toISOString(),
        'notes' => 'Updated',
    ]);

    $update->assertOk();
    ($this->assertCountPayloadShape)($update);
    expect((int) $update->json('count.id'))->toBe($count->id);
    expect($update->json('count.counted_at'))->toBe($updatedAt->format('Y-m-d H:i'));
    expect($update->json('count.counted_at_iso'))->toBe($updatedAt->format('Y-m-d\TH:i'));

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($tenant, $uom);

    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '5.000000',
        'notes' => 'Line',
    ]);

    $lineUpdate = $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $item->id,
        'counted_quantity' => '6.000000',
        'notes' => 'Updated line',
    ]);

    $lineUpdate->assertOk();
    ($this->assertLinePayloadShape)($lineUpdate);
    expect((int) $lineUpdate->json('line.id'))->toBe($line->id);
    expect($lineUpdate->json('line.item_display'))->toBe($item->name . ' (' . $uom->symbol . ')');

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id . '/lines/' . $line->id)
        ->assertOk()
        ->assertJson(['deleted' => true]);

    expect(InventoryCountLine::query()->whereKey($line->id)->exists())->toBeFalse();

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertJson(['deleted' => true]);

    expect(InventoryCount::query()->whereKey($count->id)->exists())->toBeFalse();
});

it('enforces tenant isolation: index/show scoped, other-tenant count is 404 for all actions', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermission)($userA, 'inventory-adjustments-view');
    ($this->grantPermission)($userA, 'inventory-adjustments-execute');

    $countA = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenantA->id,
        'counted_at' => now(),
    ]);

    $countB = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenantB->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($userA)->get('/inventory/counts')
        ->assertOk()
        ->assertSee('/inventory/counts/' . $countA->id)
        ->assertDontSee('/inventory/counts/' . $countB->id);

    $this->actingAs($userA)->get('/inventory/counts/' . $countB->id)->assertNotFound();
    $this->actingAs($userA)->patchJson('/inventory/counts/' . $countB->id, [
        'counted_at' => now()->toISOString(),
        'notes' => 'Nope',
    ])->assertNotFound();
    $this->actingAs($userA)->deleteJson('/inventory/counts/' . $countB->id)->assertNotFound();
    $this->actingAs($userA)->postJson('/inventory/counts/' . $countB->id . '/post')->assertNotFound();
});

it('enforces line ownership: other-tenant count is 404; count/line mismatch is 404', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermission)($userA, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)();
    $itemA = ($this->makeItem)($tenantA, $uom);
    $itemB = ($this->makeItem)($tenantB, $uom);

    $countB = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenantB->id,
        'counted_at' => now(),
    ]);

    $lineB = InventoryCountLine::query()->forceCreate([
        'tenant_id' => $tenantB->id,
        'inventory_count_id' => $countB->id,
        'item_id' => $itemB->id,
        'counted_quantity' => '1.000000',
    ]);

    $this->actingAs($userA)->postJson('/inventory/counts/' . $countB->id . '/lines', [
        'item_id' => $itemA->id,
        'counted_quantity' => '2.000000',
    ])->assertNotFound();

    $this->actingAs($userA)->patchJson('/inventory/counts/' . $countB->id . '/lines/' . $lineB->id, [
        'item_id' => $itemA->id,
        'counted_quantity' => '2.000000',
    ])->assertNotFound();

    $this->actingAs($userA)->deleteJson('/inventory/counts/' . $countB->id . '/lines/' . $lineB->id)->assertNotFound();

    $count1 = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenantA->id,
        'counted_at' => now(),
    ]);

    $count2 = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenantA->id,
        'counted_at' => now(),
    ]);

    $lineOn1 = InventoryCountLine::query()->forceCreate([
        'tenant_id' => $tenantA->id,
        'inventory_count_id' => $count1->id,
        'item_id' => $itemA->id,
        'counted_quantity' => '1.000000',
    ]);

    $this->actingAs($userA)->patchJson('/inventory/counts/' . $count2->id . '/lines/' . $lineOn1->id, [
        'item_id' => $itemA->id,
        'counted_quantity' => '2.000000',
    ])->assertNotFound();

    $this->actingAs($userA)->deleteJson('/inventory/counts/' . $count2->id . '/lines/' . $lineOn1->id)->assertNotFound();
});

it('post requires at least one line: no lines returns 422 and creates no adjustment moves', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    $before = ($this->countAdjustmentsFor)($tenant, $count);

    ($this->postCount)($user, $count)->assertStatus(422);

    $after = ($this->countAdjustmentsFor)($tenant, $count);
    expect($after)->toBe($before);
});

it('posts: creates adjustment moves, locks the count, blocks all future mutations, blocks double-post, returns full payload, and returns 422 message on draft-guard', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)();
    $item = ($this->makeItem)($tenant, $uom);

    $item->stockMoves()->create([
        'tenant_id' => $tenant->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '2.000000',
        'type' => 'receipt',
    ]);

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    InventoryCountLine::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'inventory_count_id' => $count->id,
        'item_id' => $item->id,
        'counted_quantity' => '5.000000',
    ]);

    $post = ($this->postCount)($user, $count);

    $post->assertOk();
    ($this->assertCountPayloadShape)($post);

    $count->refresh();
    expect($count->posted_at)->not->toBeNull();
    expect($count->posted_by_user_id)->toBe($user->id);

    $move = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('type', 'inventory_count_adjustment')
        ->where('source_type', InventoryCount::class)
        ->where('source_id', $count->id)
        ->where('item_id', $item->id)
        ->first();

    expect($move)->not->toBeNull();
    expect($move->quantity)->toBe('3.000000');

    $expectedMessage = 'Inventory count is posted and cannot be modified.';

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => now()->toISOString(),
        'notes' => 'Should fail',
    ])->assertStatus(422)->assertJson(['message' => $expectedMessage]);

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id)
        ->assertStatus(422)
        ->assertJson(['message' => $expectedMessage]);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '9.000000',
    ])->assertStatus(422)->assertJson(['message' => $expectedMessage]);

    $line = InventoryCountLine::query()->where('inventory_count_id', $count->id)->firstOrFail();

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $item->id,
        'counted_quantity' => '9.000000',
    ])->assertStatus(422)->assertJson(['message' => $expectedMessage]);

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id . '/lines/' . $line->id)
        ->assertStatus(422)
        ->assertJson(['message' => $expectedMessage]);

    $before = ($this->countAdjustmentsFor)($tenant, $count);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/post')
        ->assertStatus(422)
        ->assertJson(['message' => $expectedMessage]);

    $after = ($this->countAdjustmentsFor)($tenant, $count);
    expect($after)->toBe($before);
});

it('prevents cross-tenant item usage via validation (line create/update uses tenant-scoped exists)', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermission)($userA, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)();
    $itemA = ($this->makeItem)($tenantA, $uom);
    $itemB = ($this->makeItem)($tenantB, $uom);

    $countA = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenantA->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($userA)->postJson('/inventory/counts/' . $countA->id . '/lines', [
        'item_id' => $itemB->id,
        'counted_quantity' => '1.000000',
    ])->assertStatus(422);

    $lineA = InventoryCountLine::query()->forceCreate([
        'tenant_id' => $tenantA->id,
        'inventory_count_id' => $countA->id,
        'item_id' => $itemA->id,
        'counted_quantity' => '1.000000',
    ]);

    $this->actingAs($userA)->patchJson('/inventory/counts/' . $countA->id . '/lines/' . $lineA->id, [
        'item_id' => $itemB->id,
        'counted_quantity' => '2.000000',
    ])->assertStatus(422);
});
