<?php

use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeUom = function (Tenant $tenant): Uom {
        $suffix = Str::random(12);

        $category = UomCategory::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'tenant_id' => $tenant->id,
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

    $this->seedInventoryWorkflow = function (Tenant $tenant): void {
        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
    };

    $this->inventoryDomain = fn (): WorkflowDomain => WorkflowDomain::query()
        ->where('key', 'inventory')
        ->firstOrFail();

    $this->inventoryStages = function (Tenant $tenant) {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    };

    $this->assertCountPayloadShape = function ($response): void {
        $response->assertJsonStructure([
            'count' => [
                'id',
                'counted_at',
                'counted_at_iso',
                'notes',
                'status',
                'lifecycle_status_label',
                'created_by_user_id',
                'tasked_by_user_id',
                'assigned_to_user_id',
                'assigned_to_user_name',
                'workflow_stage_id',
                'workflow_stage_key',
                'workflow_stage_name',
                'workflow_status_label',
                'is_draft_setup',
                'is_submitted',
                'posted_at_display',
                'posted_at_iso',
                'lines_count',
                'show_url',
                'update_url',
                'delete_url',
                'submit_url',
                'advance_url',
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
            'assigned_to_user_id' => $user->id,
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

    $this->submitCount = function (User $user, InventoryCount $count) {
        return $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/submit');
    };

    $this->advanceCount = function (User $user, InventoryCount $count) {
        return $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/advance');
    };

    $this->createInventoryTaskTemplate = function (
        Tenant $tenant,
        WorkflowStage $stage,
        User $defaultAssignee,
        array $overrides = []
    ): WorkflowTaskTemplate {
        return WorkflowTaskTemplate::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $stage->workflow_domain_id,
            'workflow_stage_id' => $stage->id,
            'title' => 'Inventory Task ' . Str::random(8),
            'description' => 'Inventory workflow task',
            'sort_order' => 10,
            'is_active' => true,
            'default_assignee_user_id' => $defaultAssignee->id,
        ], $overrides));
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

    $this->postJson('/inventory/counts/1/submit')->assertUnauthorized();
    $this->postJson('/inventory/counts/1/advance')->assertUnauthorized();
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

it('requires execute permission for all mutations (count CRUD, line CRUD, submit, advance, post)', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');

    $uom = ($this->makeUom)($tenant);
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

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/submit')->assertForbidden();
    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/advance')->assertForbidden();
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
        'assigned_to_user_id' => $user->id,
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

    $this->actingAs($user)->postJson('/inventory/counts', [
        'counted_at' => now()->toISOString(),
        'assigned_to_user_id' => 999999,
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
        'assigned_to_user_id' => $user->id,
    ]);

    $resp->assertOk();
    ($this->assertCountPayloadShape)($resp);

    $count->refresh();
    expect($count->counted_at->format('Y-m-d H:i'))->toBe($newCountedAt->format('Y-m-d H:i'));
    expect($resp->json('count.counted_at'))->toBe($newCountedAt->format('Y-m-d H:i'));
    expect($resp->json('count.counted_at_iso'))->toBe($newCountedAt->format('Y-m-d\TH:i'));
});

it('create succeeds without assigned_to_user_id and keeps assignment null until explicitly set', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $response = $this->actingAs($user)->postJson('/inventory/counts', [
        'counted_at' => now()->toISOString(),
        'notes' => 'No assignment yet',
    ]);

    $response->assertCreated();
    ($this->assertCountPayloadShape)($response);

    $count = InventoryCount::query()->findOrFail((int) $response->json('count.id'));

    expect($count->assigned_to_user_id)->toBeNull()
        ->and($count->created_by_user_id)->toBe($user->id)
        ->and($count->tasked_by_user_id)->toBe($user->id);
});

it('validates line create/update payloads (regex + exists scoped to tenant, item remains required, counted quantity may be blank)', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)($tenant);
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

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '',
        'notes' => 'Blank allowed',
    ])->assertCreated()->assertJsonPath('line.counted_quantity', null);

    $secondItem = ($this->makeItem)($tenant, $uom);

    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $secondItem->id,
        'counted_quantity' => '1.000000',
        'notes' => 'Initial',
    ]);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'counted_quantity' => '2.000000',
    ])->assertStatus(422);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $secondItem->id,
        'notes' => 'Item only update',
    ])->assertOk()->assertJsonPath('line.notes', 'Item only update');

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $secondItem->id,
        'counted_quantity' => '',
        'notes' => 'Cleared',
    ])->assertOk()->assertJsonPath('line.counted_quantity', null);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $secondItem->id,
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
        'assigned_to_user_id' => $user->id,
    ]);

    $update->assertOk();
    ($this->assertCountPayloadShape)($update);
    expect((int) $update->json('count.id'))->toBe($count->id);
    expect($update->json('count.counted_at'))->toBe($updatedAt->format('Y-m-d H:i'));
    expect($update->json('count.counted_at_iso'))->toBe($updatedAt->format('Y-m-d\TH:i'));
    expect($update->json('count.assigned_to_user_id'))->toBe($user->id);

    $uom = ($this->makeUom)($tenant);
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

    $response = $this->actingAs($userA)->getJson(route('inventory.counts.list'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))
        ->toContain($countA->id)
        ->not->toContain($countB->id);

    $this->actingAs($userA)->get('/inventory/counts/' . $countB->id)->assertNotFound();
    $this->actingAs($userA)->patchJson('/inventory/counts/' . $countB->id, [
        'counted_at' => now()->toISOString(),
        'notes' => 'Nope',
    ])->assertNotFound();
    $this->actingAs($userA)->deleteJson('/inventory/counts/' . $countB->id)->assertNotFound();
    $this->actingAs($userA)->postJson('/inventory/counts/' . $countB->id . '/submit')->assertNotFound();
    $this->actingAs($userA)->postJson('/inventory/counts/' . $countB->id . '/advance')->assertNotFound();
    $this->actingAs($userA)->postJson('/inventory/counts/' . $countB->id . '/post')->assertNotFound();
});

it('enforces line ownership: other-tenant count is 404; count/line mismatch is 404', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermission)($userA, 'inventory-adjustments-execute');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $itemA = ($this->makeItem)($tenantA, $uomA);
    $itemB = ($this->makeItem)($tenantB, $uomB);

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

    $uom = ($this->makeUom)($tenant);
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

it('submits a draft count into the first active inventory workflow stage', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Submit me',
    ]);

    $response = ($this->submitCount)($user, $count);

    $response->assertOk();
    ($this->assertCountPayloadShape)($response);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('open')
        ->and($count->posted_at)->toBeNull()
        ->and($response->json('count.workflow_stage_key'))->toBe('open')
        ->and($response->json('count.workflow_status_label'))->toBe('Open')
        ->and($response->json('count.is_draft_setup'))->toBeFalse();
});

it('draft open action works without an assigned user and still moves the count into the first active stage', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => null,
        'notes' => 'Open me',
    ]);

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Open')
        ->assertDontSee('Post Count');

    $response = ($this->submitCount)($user, $count);

    $response->assertOk()
        ->assertJsonPath('count.workflow_stage_key', 'open')
        ->assertJsonPath('count.workflow_status_label', 'Open');

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('open')
        ->and($count->assigned_to_user_id)->toBeNull();
});

it('draft detail page shows the next workflow stage name as the submit action', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Draft workflow detail',
    ]);

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Home')
        ->assertSee('Inventory Counts')
        ->assertSee('ID# ' . $count->id)
        ->assertSee('Workflow Stage')
        ->assertSee('Draft')
        ->assertSee('Open')
        ->assertDontSee('Submit Count')
        ->assertDontSee('Post Count')
        ->assertDontSee('>Completed<', false);
});

it('detail page mounts reusable sections for count lines and tasks', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Count lines section count',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain('data-js-crud-section-root')
        ->and($response->getContent())->toContain('data-section-key="countLines"')
        ->and($response->getContent())->toContain('data-section-key="tasks"')
        ->and($response->getContent())->not->toContain('<table class="min-w-full text-sm">');
});

it('detail payload exposes Materials and Tasks section configs for inventory count detail', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Detail payload count',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $countLinesSection = $payload['sections']['countLines'] ?? [];
    $tasksSection = $payload['sections']['tasks'] ?? [];
    $countPayload = $payload['count'] ?? [];

    expect($countLinesSection['title'] ?? null)->toBe('Materials')
        ->and($countLinesSection['resource'] ?? null)->toBe('inventory-count-lines')
        ->and($countLinesSection['permissions']['canCreate'] ?? null)->toBeTrue()
        ->and($countLinesSection['endpoints']['list'] ?? null)->toBe(route('inventory.counts.show', $count) . '/lines')
        ->and($countLinesSection['endpoints']['create'] ?? null)->toBe(route('inventory.counts.lines.store', $count))
        ->and($tasksSection['title'] ?? null)->toBe('Tasks')
        ->and($tasksSection['resource'] ?? null)->toBe('inventory-count-tasks')
        ->and($tasksSection['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($tasksSection['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and($countPayload['submit_url'] ?? null)->toBe(route('inventory.counts.submit', $count))
        ->and($countPayload['advance_url'] ?? null)->toBe(route('inventory.counts.advance', $count));
});

it('draft detail page renders the Open button wired to the submit workflow action only', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Open button wiring',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-submit'))")
        ->and($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-post'))")
        ->and($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->toContain('Open')
        ->and($response->getContent())->not->toContain('>Completed<');
});

it('create persists created by tasked by and assigned user fields', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
        'notes' => 'Assigned draft',
    ]);

    $count->refresh();

    expect($count->created_by_user_id)->toBe($creator->id)
        ->and($count->tasked_by_user_id)->toBe($creator->id)
        ->and($count->assigned_to_user_id)->toBe($assignee->id);
});

it('submitting a draft count generates workflow tasks for the selected assigned user', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $creator, [
        'title' => 'Count review',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $task = Task::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('domain_record_id', $count->id)
        ->where('workflow_stage_id', $openStage->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task?->assigned_to_user_id)->toBe($assignee->id)
        ->and($task?->status)->toBe(Task::STATUS_OPEN);
});

it('detail payload shows current stage tasks with name status assigned user and completion url', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-view');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Review count lines',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $response = $this->actingAs($assignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $task = collect($payload['count']['current_stage_tasks'] ?? [])->first();

    expect($task)->not->toBeNull()
        ->and($task['title'] ?? null)->toBe('Review count lines')
        ->and($task['status'] ?? null)->toBe('open')
        ->and($task['assigned_to_user_name'] ?? null)->toBe($assignee->name)
        ->and($task['complete_url'] ?? null)->toBeString();
});

it('detail page renders a Tasks section with generated task name status and open-task assignment metadata', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-view');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Task section item',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $this->actingAs($assignee)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Tasks')
        ->assertSee('Task section item')
        ->assertSee('open')
        ->assertSee('Assigned By:')
        ->assertSee('Assigned To:')
        ->assertDontSee('Completed By:')
        ->assertSee($assignee->name)
        ->assertSee($creator->name);
});

it('tasks section rows expose a visible Complete button and disable the row actions menu through config', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-view');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Inline task completion',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $response = $this->actingAs($assignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $tasksSection = $payload['sections']['tasks'] ?? [];

    expect($tasksSection['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($tasksSection['actions'][0]['label'] ?? null)->toBe('Complete')
        ->and($tasksSection['rowLayout']['secondaryFields'][0]['label'] ?? null)->toBe('Assigned By')
        ->and($tasksSection['rowLayout']['secondaryFields'][1]['label'] ?? null)->toBe('Assigned To')
        ->and($tasksSection['rowLayout']['secondaryFields'][2]['label'] ?? null)->toBe('Completed By')
        ->and($tasksSection['rowLayout']['rightMeta'] ?? [])->toBe([])
        ->and($response->getContent())->toContain('Inline task completion')
        ->and($response->getContent())->toContain('Assigned By:')
        ->and($response->getContent())->toContain('Assigned To:')
        ->and($response->getContent())->not->toContain('Completed By:')
        ->and($response->getContent())->toContain('Complete');
});

it('shared detail section source supports inline task actions when the row actions menu is disabled', function () {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('showRowActionsMenu: safeConfig.showRowActionsMenu !== false')
        ->and($source)->toContain("Object.prototype.hasOwnProperty.call(safeEntry, 'fallback')")
        ->and($source)->toContain('x-show="!section.showRowActionsMenu && visibleActions(record).length > 0"')
        ->and($source)->toContain('x-show="section.showRowActionsMenu && visibleActions(record).length > 0"')
        ->and($source)->toContain('flex flex-wrap items-center gap-4')
        ->and($source)->toContain('flex items-center justify-end gap-3 self-center')
        ->and($source)->toContain('items-center justify-end gap-2 self-center')
        ->and($source)->toContain('inline-flex items-center rounded-lg')
        ->and($source)->toContain('flex flex-col sm:flex-row');
});

it('completed task rows do not render a Complete button in the tasks section payload', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-view');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Already done task',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $task = Task::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('domain_record_id', $count->id)
        ->where('workflow_stage_id', $openStage->id)
        ->firstOrFail();

    $this->actingAs($assignee)
        ->patchJson(route('tasks.complete', $task), [])
        ->assertOk();

    $response = $this->actingAs($assignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['count']['current_stage_tasks'] ?? [])->firstWhere('id', $task->id);

    expect($taskPayload)->not->toBeNull()
        ->and($taskPayload['is_completed'] ?? null)->toBeTrue()
        ->and($taskPayload['available_actions'] ?? null)->toBe([])
        ->and($taskPayload['assigned_by_user_name'] ?? null)->toBe($creator->name)
        ->and($taskPayload['assigned_to_display'] ?? null)->toBe('')
        ->and($taskPayload['completed_by_user_name'] ?? null)->toBe($assignee->name)
        ->and($taskPayload['completed_by_display'] ?? null)->toBe($assignee->name);

    expect($response->getContent())->toContain('Assigned By:')
        ->and($response->getContent())->toContain('Completed By:')
        ->and($response->getContent())->not->toContain('Assigned To:')
        ->and($response->getContent())->not->toContain('action="' . route('tasks.complete', $task) . '"');
});

it('incomplete task rows expose a Complete button in the tasks section payload', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-view');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Still open task',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $response = $this->actingAs($assignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['count']['current_stage_tasks'] ?? [])->first();

    expect($taskPayload)->not->toBeNull()
        ->and($taskPayload['is_completed'] ?? null)->toBeFalse()
        ->and($taskPayload['available_actions'] ?? null)->toBe(['complete'])
        ->and($taskPayload['status'] ?? null)->toBe('open')
        ->and($taskPayload['assigned_by_user_name'] ?? null)->toBe($creator->name)
        ->and($taskPayload['assigned_to_display'] ?? null)->toBe($assignee->name)
        ->and($taskPayload['completed_by_display'] ?? null)->toBe('');
});

it('advance requires current inventory workflow tasks to be completed before moving forward', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Count check',
    ]);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);
    ($this->createLineViaApi)($creator, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    ($this->advanceCount)($creator, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'Complete all tasks for this stage before moving the inventory count forward.']);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('open')
        ->and($count->posted_at)->toBeNull();
});

it('completing the assigned current stage task removes the gating block and allows advancement', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Complete me',
    ]);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);
    ($this->createLineViaApi)($creator, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '4.000000',
    ]);

    ($this->submitCount)($creator, $count)->assertOk();

    $task = Task::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('domain_record_id', $count->id)
        ->where('workflow_stage_id', $openStage->id)
        ->firstOrFail();

    $this->actingAs($assignee)->patchJson(route('tasks.complete', $task))->assertOk();

    ($this->advanceCount)($creator, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completed')
        ->and($count->posted_at)->not->toBeNull();
});

it('submitting uses the configured first active inventory stage rather than a hardcoded default', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('key', 'completed')
        ->update([
            'sort_order' => 5,
            'is_inventory_effect_stage' => false,
        ]);

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('key', 'open')
        ->update([
            'sort_order' => 20,
        ]);

    WorkflowStage::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'review',
        'name' => 'Review',
        'description' => null,
        'sort_order' => 30,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ]);

    $count = ($this->createDraftCountViaApi)($user);

    ($this->submitCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completed');
});

it('open stage keeps line mutations available while still blocking header edits and count deletion', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $secondItem = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user);
    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
        'notes' => 'Before submit',
    ]);

    ($this->submitCount)($user, $count)->assertOk();

    $expectedMessage = 'Inventory count has been submitted and cannot be modified.';

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => now()->addDay()->toISOString(),
        'notes' => 'Blocked',
    ])->assertStatus(422)->assertJson(['message' => $expectedMessage]);

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id)
        ->assertStatus(422)
        ->assertJson(['message' => $expectedMessage]);

    $lineCreate = $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $secondItem->id,
        'counted_quantity' => '2.000000',
        'notes' => 'Open stage add',
    ]);

    $lineCreate->assertCreated()->assertJsonPath('line.item_id', $secondItem->id);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
        'notes' => 'Open stage edit',
    ])->assertOk()->assertJsonPath('line.notes', 'Open stage edit');

    $createdLineId = (int) $lineCreate->json('line.id');

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id . '/lines/' . $createdLineId)
        ->assertOk()
        ->assertJson(['deleted' => true]);
});

it('advancing a non inventory-effect stage does not post the count', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('key', 'completed')
        ->update([
            'sort_order' => 30,
        ]);

    WorkflowStage::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'review',
        'name' => 'Review',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    ($this->submitCount)($user, $count)->assertOk();
    $response = ($this->advanceCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('review')
        ->and($count->posted_at)->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(0)
        ->and($response->json('count.workflow_stage_key'))->toBe('review');
});

it('advancing into the inventory-effect stage posts the count', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $item->stockMoves()->create([
        'tenant_id' => $tenant->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '2.000000',
        'type' => 'receipt',
    ]);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '5.000000',
    ]);

    ($this->submitCount)($user, $count)->assertOk();
    $response = ($this->advanceCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completed')
        ->and($count->posted_at)->not->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(1)
        ->and($response->json('count.status'))->toBe('posted');
});

it('posting failure while advancing blocks stage completion and leaves no partial adjustments', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);

    ($this->submitCount)($user, $count)->assertOk();

    $response = ($this->advanceCount)($user, $count);

    $response->assertStatus(422)->assertJson(['message' => 'Inventory count must have at least one line.']);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('open')
        ->and($count->posted_at)->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(0);
});

it('blank counted quantity is allowed before completion but blocks advancing into the inventory effect stage', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => null,
    ]);

    $line = $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '',
        'notes' => 'Still counting',
    ]);

    $line->assertCreated()->assertJsonPath('line.counted_quantity', null);

    ($this->submitCount)($user, $count)->assertOk();

    ($this->advanceCount)($user, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'All inventory count lines must have a counted quantity before posting.']);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('open')
        ->and($count->posted_at)->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(0);
});

it('duplicate materials cannot be added to the same inventory count', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user);

    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
        'notes' => 'Duplicate item',
    ])->assertStatus(422)->assertJsonValidationErrors(['item_id']);
});

it('the same material can be used on different inventory counts', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $countA = ($this->createDraftCountViaApi)($user, ['notes' => 'Count A']);
    $countB = ($this->createDraftCountViaApi)($user, ['notes' => 'Count B']);

    ($this->createLineViaApi)($user, $countA, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    $this->actingAs($user)->postJson('/inventory/counts/' . $countB->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '3.000000',
        'notes' => 'Same item other count',
    ])->assertCreated()->assertJsonPath('line.item_id', $item->id);
});

it('repeating advance or post after completion does not double post inventory moves', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $before = ($this->countAdjustmentsFor)($tenant, $count);

    ($this->advanceCount)($user, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'Inventory count is posted and cannot be modified.']);

    ($this->postCount)($user, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'Inventory count is posted and cannot be modified.']);

    expect(($this->countAdjustmentsFor)($tenant, $count))->toBe($before);
});

it('direct post remains compatible from draft and moves the count to the inventory effect stage', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user, [
        'counted_at' => Carbon::parse('2026-05-19 09:00:00')->toISOString(),
        'notes' => 'Direct post draft',
    ]);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '4.000000',
    ]);

    $response = ($this->postCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completed')
        ->and($count->status)->toBe('posted')
        ->and($count->notes)->toBe('Direct post draft')
        ->and($count->counted_at->format('Y-m-d H:i'))->toBe('2026-05-19 09:00')
        ->and($response->json('count.workflow_stage_key'))->toBe('completed');
});

it('direct post remains compatible after submit from an in workflow count', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '3.000000',
    ]);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->postCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completed')
        ->and($count->status)->toBe('posted');
});

it('detail page shows breadcrumb workflow metadata materials tasks and no post ui or lifecycle status copy', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Workflow detail',
    ]);

    ($this->submitCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id);

    $response->assertOk()
        ->assertSee('Home')
        ->assertSee('Inventory Count')
        ->assertSee('Inventory Counts')
        ->assertSee('ID# ' . $count->id)
        ->assertSee('Workflow Stage')
        ->assertSee('Open')
        ->assertSee('Counted')
        ->assertSee('Line Items')
        ->assertSee('Workflow detail')
        ->assertSee('Materials')
        ->assertDontSee('Count Lines')
        ->assertSee('Tasks')
        ->assertDontSee('Back to Counts')
        ->assertDontSee('Lifecycle Status')
        ->assertDontSee('Post Count')
        ->assertDontSee('Submit Count')
        ->assertSee('Completed')
        ->assertSee('data-section-key="countLines"', false)
        ->assertSee('data-section-key="tasks"', false)
        ->assertDontSee('data-crud-root', false);
});

it('first active workflow stage hides the previous-stage button and shows the next stage button only', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'First stage navigation',
    ]);

    ($this->submitCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->toContain('Completed')
        ->and($response->getContent())->not->toContain('Back to');
});

it('non-first non-posted workflow stages can expose a previous-stage button by stage name only', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completed');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'review',
        'name' => 'Review',
        'description' => null,
        'sort_order' => 15,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill([
            'sort_order' => 20,
        ])->save();
    }

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Previous stage available',
    ]);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->toContain('Open')
        ->and($response->getContent())->toContain('Completed')
        ->and($response->getContent())->not->toContain('Back to')
        ->and($response->getContent())->not->toContain('Move to');
});

it('previous-stage button renders before the next-stage button in the inventory count header', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completed');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill(['sort_order' => 30])->save();
    }

    $count = ($this->createDraftCountViaApi)($user);
    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $content = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk()->getContent();

    expect(strpos($content, "window.dispatchEvent(new CustomEvent('inventory-count-previous'))"))->toBeLessThan(
        strpos($content, "window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
    );
});

it('previous stage availability follows configured stage order rather than hardcoded stage names', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'open');
    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completed');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill([
            'sort_order' => 30,
        ])->save();
    }

    if ($openStage) {
        $openStage->forceFill([
            'sort_order' => 10,
        ])->save();
    }

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Configured order navigation',
    ]);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain('Approval')
        ->and($response->getContent())->toContain('Open')
        ->and($response->getContent())->toContain('Completed')
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))");
});

it('draft detail page does not expose Completed as an available workflow action', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Only open is available',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-submit'))")
        ->and($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->not->toContain('>Completed<');
});

it('draft detail page hides the previous-stage button', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))");
});

it('previous-stage button is hidden without execute permission', function () {
    $tenant = Tenant::factory()->create();
    $viewer = ($this->makeUser)($tenant);
    $executor = ($this->makeUser)($tenant);

    ($this->grantPermission)($viewer, 'inventory-adjustments-view');
    ($this->grantPermission)($executor, 'inventory-adjustments-view');
    ($this->grantPermission)($executor, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completed');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill(['sort_order' => 30])->save();
    }

    $count = ($this->createDraftCountViaApi)($executor);
    ($this->submitCount)($executor, $count)->assertOk();
    ($this->advanceCount)($executor, $count)->assertOk();

    $response = $this->actingAs($viewer)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))");
});

it('previous-stage action works when the count is on a non-first non-posted workflow stage', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completed');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill(['sort_order' => 30])->save();
    }

    $count = ($this->createDraftCountViaApi)($user);
    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $this->actingAs($user)->postJson(route('inventory.counts.previous', $count))
        ->assertOk()
        ->assertJsonPath('count.workflow_stage_name', 'Open');

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('open');
});

it('previous-stage action is blocked after inventory has posted', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->submitCount)($user, $count)->assertOk();

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    ($this->advanceCount)($user, $count)->assertOk();

    $this->actingAs($user)->postJson(route('inventory.counts.previous', $count))
        ->assertStatus(422)
        ->assertJson(['message' => 'Inventory count is posted and cannot be modified.']);
});

it('prevents cross-tenant item usage via validation (line create/update uses tenant-scoped exists)', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = ($this->makeUser)($tenantA);

    ($this->grantPermission)($userA, 'inventory-adjustments-execute');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $itemA = ($this->makeItem)($tenantA, $uomA);
    $itemB = ($this->makeItem)($tenantB, $uomB);

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
