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
use App\Models\UomConversion;
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
            'is_stockable' => true,
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
                'name',
                'counted_at',
                'counted_at_iso',
                'notes',
                'can_edit_details',
                'can_edit_notes',
                'can_edit_counted_at',
                'can_edit_assignment',
                'show_details_section',
                'assignee_options',
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
            'name' => 'Draft Count ' . Str::random(10),
            'counted_at' => now()->toISOString(),
            'notes' => 'Draft ' . Str::random(10),
            'assigned_to_user_id' => $user->id,
        ], $payload);

        if (isset($payload['assigned_to_user_id'])) {
            $assignee = User::query()->find((int) $payload['assigned_to_user_id']);

            if ($assignee instanceof User && (int) $assignee->tenant_id === (int) $user->tenant_id) {
                ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
            }
        }

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

    $this->ensureCountHasMaterial = function (User $user, Tenant $tenant, InventoryCount $count): void {
        if ($count->lines()->exists()) {
            return;
        }

        $uom = ($this->makeUom)($tenant);
        $item = ($this->makeItem)($tenant, $uom);

        ($this->createLineViaApi)($user, $count, [
            'item_id' => $item->id,
            'counted_quantity' => null,
        ]);
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
        ($this->grantPermission)($defaultAssignee, 'inventory-adjustments-execute');

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
    $this->actingAs($user)->get('/inventory/counts')->assertOk();
    $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertForbidden();

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    $this->actingAs($user)->get('/inventory/counts')->assertOk();
    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Details')
        ->assertSee('Notes')
        ->assertSee('Count Date')
        ->assertSee('Assigned To');
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

it('allows execute-only users to view assigned counts and update notes only', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $this->actingAs($user)->get('/inventory/counts')->assertOk();

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Execute-only create',
    ]);

    $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    $resp = $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'notes' => 'Execute-only update',
    ]);

    $resp->assertOk()
        ->assertJsonPath('count.notes', 'Execute-only update')
        ->assertJsonPath('count.can_edit_notes', true)
        ->assertJsonPath('count.can_edit_details', false)
        ->assertJsonPath('count.can_edit_counted_at', false)
        ->assertJsonPath('count.can_edit_assignment', false);
    ($this->assertCountPayloadShape)($resp);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => now()->addMinutes(10)->seconds(0)->toISOString(),
        'notes' => 'Blocked metadata update',
        'assigned_to_user_id' => $user->id,
    ])->assertForbidden();

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'assigned_to_user_id' => $assignee->id,
    ])->assertForbidden();
});

it('allows a tasker assigned to an inventory count to view that count without broad view permission', function () {
    $tenant = Tenant::factory()->create();
    $tasker = ($this->makeUser)($tenant);
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'assigned_to_user_id' => $tasker->id,
        'counted_at' => Carbon::parse('2026-06-01 16:45:00'),
    ]);

    $response = $this->actingAs($tasker)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Inventory Count')
        ->assertSee('June 1, 2026')
        ->assertDontSee('Details')
        ->assertSee('Notes')
        ->assertDontSee('Count Date')
        ->assertSee('>Assigned To<', false);

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $countPayload = $payload['count'] ?? [];

    expect($countPayload['can_edit_notes'] ?? null)->toBeFalse()
        ->and($countPayload['can_edit_details'] ?? null)->toBeFalse()
        ->and($countPayload['can_edit_counted_at'] ?? null)->toBeFalse()
        ->and($countPayload['can_edit_assignment'] ?? null)->toBeFalse()
        ->and($countPayload['show_details_section'] ?? null)->toBeFalse()
        ->and($countPayload['assignee_options'] ?? null)->toBe([]);
});

it('blocks a tasker who is not assigned to an inventory count or any of its tasks', function () {
    $tenant = Tenant::factory()->create();
    $tasker = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'assigned_to_user_id' => $otherUser->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($tasker)
        ->get('/inventory/counts/' . $count->id)
        ->assertForbidden();
});

it('allows a tasker assigned to an inventory workflow-stage task to view the related count', function () {
    $tenant = Tenant::factory()->create();
    $tasker = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    ($this->seedInventoryWorkflow)($tenant);
    $stage = ($this->inventoryStages)($tenant)->first();
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'assigned_to_user_id' => $otherUser->id,
        'workflow_stage_id' => $stage->id,
        'counted_at' => now(),
    ]);

    Task::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $stage->workflow_domain_id,
        'domain_record_id' => $count->id,
        'workflow_stage_id' => $stage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $tasker->id,
        'title' => 'Assigned count task',
        'description' => null,
        'sort_order' => 10,
        'status' => Task::STATUS_OPEN,
    ]);

    $this->actingAs($tasker)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Assigned count task');
});

it('keeps cross-tenant assigned inventory counts inaccessible', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $tasker = ($this->makeUser)($tenant);
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'assigned_to_user_id' => $tasker->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($tasker)
        ->get('/inventory/counts/' . $count->id)
        ->assertNotFound();
});

it('keeps cross-tenant assigned inventory tasks from granting count access', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $tasker = ($this->makeUser)($tenant);
    ($this->seedInventoryWorkflow)($otherTenant);
    $stage = ($this->inventoryStages)($otherTenant)->first();
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'workflow_stage_id' => $stage->id,
        'counted_at' => now(),
    ]);

    Task::query()->forceCreate([
        'tenant_id' => $otherTenant->id,
        'workflow_domain_id' => $stage->workflow_domain_id,
        'domain_record_id' => $count->id,
        'workflow_stage_id' => $stage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $tasker->id,
        'title' => 'Cross tenant task',
        'description' => null,
        'sort_order' => 10,
        'status' => Task::STATUS_OPEN,
    ]);

    $this->actingAs($tasker)
        ->get('/inventory/counts/' . $count->id)
        ->assertNotFound();
});

it('allows users with normal inventory count view permission to keep viewing counts', function () {
    $tenant = Tenant::factory()->create();
    $viewer = ($this->makeUser)($tenant);
    ($this->grantPermission)($viewer, 'inventory-adjustments-view');
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk();
});

it('allows super-admin users to view inventory counts through Gate before behavior', function () {
    $tenant = Tenant::factory()->create();
    $admin = ($this->makeUser)($tenant);
    $role = Role::query()->forceCreate(['name' => 'super-admin']);
    $admin->roles()->syncWithoutDetaching([$role->id]);
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk();
});

it('lets assigned inventory count viewers load line and task sections without broad view permission', function () {
    $tenant = Tenant::factory()->create();
    $tasker = ($this->makeUser)($tenant);
    ($this->seedInventoryWorkflow)($tenant);
    $stage = ($this->inventoryStages)($tenant)->first();
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'assigned_to_user_id' => $tasker->id,
        'workflow_stage_id' => $stage->id,
        'counted_at' => now(),
    ]);

    $this->actingAs($tasker)
        ->getJson('/inventory/counts/' . $count->id . '/lines')
        ->assertOk();

    $this->actingAs($tasker)
        ->getJson('/inventory/counts/' . $count->id . '/tasks')
        ->assertOk();
});

it('renders the inventory count date under the header title without mutating storage', function () {
    $tenant = Tenant::factory()->create();
    $viewer = ($this->makeUser)($tenant);
    ($this->grantPermission)($viewer, 'inventory-adjustments-view');
    $countedAt = Carbon::parse('2026-06-01 16:45:00');
    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => $countedAt,
    ]);

    $this->actingAs($viewer)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('June 1, 2026')
        ->assertDontSee('June 1, 2026 at 4:45 PM');

    expect($count->fresh()->counted_at->format('Y-m-d H:i:s'))->toBe('2026-06-01 16:45:00');
});

it('validates count create/update payloads (update requires counted_at) and updates counted_at on success', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $this->actingAs($user)->postJson('/inventory/counts', [
        'name' => 'Missing counted at count',
        'notes' => 'Missing counted_at',
    ])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts', [
        'name' => 'Invalid counted at count',
        'counted_at' => 'not-a-date',
    ])->assertStatus(422);

    $this->actingAs($user)->postJson('/inventory/counts', [
        'name' => 'Bad assignee count',
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
        'name' => 'Updated inventory count name',
        'counted_at' => $newCountedAt->toISOString(),
        'notes' => 'Updated',
        'assigned_to_user_id' => $user->id,
    ]);

    $resp->assertOk();
    ($this->assertCountPayloadShape)($resp);

    $count->refresh();
    expect($count->counted_at->format('Y-m-d H:i'))->toBe($newCountedAt->format('Y-m-d H:i'));
    expect($count->name)->toBe('Updated inventory count name');
    expect($resp->json('count.counted_at'))->toBe($newCountedAt->format('F j, Y'));
    expect($resp->json('count.name'))->toBe('Updated inventory count name');
    expect($resp->json('count.counted_at_iso'))->toBe($newCountedAt->format('Y-m-d'));
});

it('create succeeds without assigned_to_user_id and keeps assignment null until explicitly set', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $response = $this->actingAs($user)->postJson('/inventory/counts', [
        'name' => 'Unassigned cycle count',
        'counted_at' => now()->toISOString(),
        'notes' => 'No assignment yet',
    ]);

    $response->assertCreated();
    ($this->assertCountPayloadShape)($response);

    $count = InventoryCount::query()->findOrFail((int) $response->json('count.id'));

    expect($count->name)->toBe('Unassigned cycle count')
        ->and($count->assigned_to_user_id)->toBeNull()
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
    expect($update->json('count.counted_at'))->toBe($updatedAt->format('F j, Y'));
    expect($update->json('count.counted_at_iso'))->toBe($updatedAt->format('Y-m-d'));
    expect($update->json('count.assigned_to_user_id'))->toBe($user->id);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '5.000000',
        'notes' => 'Line',
    ]);

    expect($line->uom_id)->toBe($uom->id);

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

it('posts count lines by converting their snapshot uom into the current item base uom', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $gram = ($this->makeUom)($tenant);
    $kilogram = Uom::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'uom_category_id' => $gram->uom_category_id,
        'name' => 'Test Kilogram',
        'symbol' => 'test-kg',
    ]);

    UomConversion::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'from_uom_id' => $gram->id,
        'to_uom_id' => $kilogram->id,
        'multiplier' => '0.00100000',
    ]);

    $item = ($this->makeItem)($tenant, $kilogram);

    $item->stockMoves()->create([
        'tenant_id' => $tenant->id,
        'uom_id' => $kilogram->id,
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
        'uom_id' => $gram->id,
        'counted_quantity' => '5000.000000',
    ]);

    ($this->postCount)($user, $count)->assertOk();

    $move = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('type', 'inventory_count_adjustment')
        ->where('source_type', InventoryCount::class)
        ->where('source_id', $count->id)
        ->where('item_id', $item->id)
        ->firstOrFail();

    expect($move->uom_id)->toBe($kilogram->id)
        ->and($move->quantity)->toBe('3.000000');
});

it('rejects posting when a count line snapshot uom cannot convert into the current item base uom', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $each = ($this->makeUom)($tenant);
    $kilogram = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $kilogram);

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'counted_at' => now(),
    ]);

    InventoryCountLine::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'inventory_count_id' => $count->id,
        'item_id' => $item->id,
        'uom_id' => $each->id,
        'counted_quantity' => '5.000000',
    ]);

    ($this->postCount)($user, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'Inventory count line quantity cannot be converted to the item base UoM.']);

    expect(($this->countAdjustmentsFor)($tenant, $count))->toBe(0);
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
    $expectedRemoveMessage = 'Inventory count is posted and materials can no longer be removed.';

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
        ->assertJson(['message' => $expectedRemoveMessage]);

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
    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    $response = ($this->submitCount)($user, $count);

    $response->assertOk();
    ($this->assertCountPayloadShape)($response);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('counting')
        ->and($count->posted_at)->toBeNull()
        ->and($response->json('count.workflow_stage_key'))->toBe('counting')
        ->and($response->json('count.workflow_status_label'))->toBe('SCHEDULED')
        ->and($response->json('count.is_draft_setup'))->toBeFalse();
});

it('blocks scheduling from moving past scheduling when no material item is attached', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'No materials yet',
    ]);

    ($this->submitCount)($user, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'Add at least one material item before moving past Scheduling.']);

    $count->refresh();

    expect($count->workflow_stage_id)->toBeNull()
        ->and($count->lines()->count())->toBe(0);
});

it('draft schedule action works without an assigned user and still moves the count into the first active stage', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => null,
        'notes' => 'Open me',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('SCHEDULE')
        ->assertDontSee('Post Count');

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    $response = ($this->submitCount)($user, $count);

    $response->assertOk()
        ->assertJsonPath('count.workflow_stage_key', 'counting')
        ->assertJsonPath('count.workflow_status_label', 'SCHEDULED');

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('counting')
        ->and($count->assigned_to_user_id)->toBeNull();
});

it('draft detail page shows the next workflow stage action verb as the submit action', function () {
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
        ->assertSee($count->name)
        ->assertSee('Draft')
        ->assertSee('SCHEDULE')
        ->assertDontSee('Submit Count')
        ->assertDontSee('Post Count')
        ->assertDontSee('>COMPLETE<', false);
});

it('draft inventory count detail auto-resolves the next workflow stage button even before stages were manually seeded elsewhere', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Draft auto-stage detail',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-submit'))")
        ->and($response->getContent())->toContain('SCHEDULE')
        ->and($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))");
});

it('completed inventory count detail does not show a draft stage advancement button', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'Completed detail header',
    ]);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
    ]);

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-submit'))")
        ->and($response->getContent())->not->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->toContain('COMPLETED');
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
        ->and($response->getContent())->toContain('data-inventory-count-tasks-section')
        ->and($response->getContent())->toContain('!-mt-px sm:!mt-6')
        ->and($response->getContent())->toContain('data-detail-section-card')
        ->and($response->getContent())->toContain('data-detail-section-toggle')
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
        ->and($countLinesSection['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and($countLinesSection['endpoints']['list'] ?? null)->toBe(route('inventory.counts.show', $count) . '/lines')
        ->and($countLinesSection['endpoints']['create'] ?? null)->toBe(route('inventory.counts.lines.store', $count))
        ->and($countLinesSection['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($countLinesSection['actions'][0]['handlerKey'] ?? null)->toBe('removeCountLine')
        ->and($countLinesSection['actions'][0]['icon'] ?? null)->toBe('x-mark')
        ->and($countLinesSection['addRow']['enabled'] ?? null)->toBeTrue()
        ->and($countLinesSection['addRow']['type'] ?? null)->toBe('combobox-add')
        ->and($countLinesSection['addRow']['fieldName'] ?? null)->toBe('item_id')
        ->and($countLinesSection['addRow']['action']['handlerKey'] ?? null)->toBe('addSelectedCountLine')
        ->and($tasksSection['title'] ?? null)->toBe('Tasks')
        ->and($tasksSection['resource'] ?? null)->toBe('inventory-count-tasks')
        ->and($tasksSection['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($tasksSection['permissions']['canCreate'] ?? null)->toBeFalse()
        ->and($countPayload['submit_url'] ?? null)->toBe(route('inventory.counts.submit', $count))
        ->and($countPayload['advance_url'] ?? null)->toBe(route('inventory.counts.advance', $count));
});

it('detail page cleans legacy header pills and renders a Details section with editable fields', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user, [
        'counted_at' => Carbon::parse('2026-05-21 10:15:00')->toISOString(),
        'assigned_to_user_id' => $assignee->id,
        'notes' => '',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->not->toContain('Counted:')
        ->and($response->getContent())->not->toContain('Line Items:')
        ->and($response->getContent())->not->toContain('Workflow Stage:')
        ->and($response->getContent())->toContain('Details')
        ->and($response->getContent())->toContain('Count Date')
        ->and($response->getContent())->toContain('Assigned To')
        ->and($response->getContent())->toContain('x-model="details.counted_at_iso"')
        ->and($response->getContent())->not->toContain('x-model="details.notes"')
        ->and($response->getContent())->toContain('workflow_status_badge')
        ->and($response->getContent())->toContain('data-section-key="countLines"');

    $source = file_get_contents(resource_path('views/inventory/counts/show.blade.php'));

    expect($source)->toContain('title="Details"')
        ->and($source)->toContain(':default-open="false"');
});

it('detail header renders workflow-derived status badge under the count date instead of lifecycle posted status', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'counted_at' => Carbon::parse('2026-05-21 10:15:00')->toISOString(),
    ]);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain('May 21, 2026')
        ->and($response->getContent())->toContain('data-workflow-status-badge="workflow_status_badge"')
        ->and($response->getContent())->toContain('COMPLETED')
        ->and($response->getContent())->not()->toContain('data-lifecycle-status-badge="lifecycle_status_badge"')
        ->and($response->getContent())->not()->toContain('>Posted<');

    $source = file_get_contents(resource_path('views/inventory/counts/show.blade.php'));

    expect($source)->toContain('$payload[\'count\'][\'workflow_status_label\'] ?? __(\'Draft\')')
        ->and($source)->not()->toContain('$payload[\'count\'][\'lifecycle_status_label\'] ?? __(\'Draft\')');
});

it('posted count progress marks the final inventory workflow stage completed with no current workflow step', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $steps = collect($payload['workflowProgressSteps'] ?? [])->keyBy('label');

    expect($steps->get('DRAFT')['status'] ?? null)->toBe('completed')
        ->and($steps->get('Scheduling')['status'] ?? null)->toBe('completed')
        ->and($steps->get('Counting')['status'] ?? null)->toBe('completed')
        ->and($steps->get('Completing')['status'] ?? null)->toBe('completed')
        ->and(collect($payload['workflowProgressSteps'] ?? [])->where('current', true)->count())->toBe(0)
        ->and($response->getContent())->not()->toContain('aria-current="step"');
});

it('detail payload exposes editable details data and tenant assignee options', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => $assignee->id,
        'notes' => '',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $countPayload = $payload['count'] ?? [];

    expect($countPayload['can_edit_details'] ?? null)->toBeTrue()
        ->and($countPayload['can_edit_notes'] ?? null)->toBeTrue()
        ->and($countPayload['can_edit_counted_at'] ?? null)->toBeTrue()
        ->and($countPayload['can_edit_assignment'] ?? null)->toBeTrue()
        ->and($countPayload['show_details_section'] ?? null)->toBeTrue()
        ->and($countPayload['counted_at_iso'] ?? null)->toBeString()
        ->and($countPayload['notes'] ?? null)->toBe('')
        ->and($countPayload['assigned_to_user_id'] ?? null)->toBe($assignee->id)
        ->and(collect($countPayload['assignee_options'] ?? [])->pluck('value')->contains((string) $assignee->id))->toBeTrue();
});

it('detail page renders count date and assigned user fields for authorized metadata editors', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain('Count Date')
        ->and($response->getContent())->toContain('Assigned To')
        ->and($response->getContent())->toContain('x-model="details.counted_at_iso"')
        ->and($response->getContent())->toContain('x-model="details.assigned_to_user_id"');
});

it('detail payload separates metadata visibility from metadata editability', function () {
    $tenant = Tenant::factory()->create();
    $viewer = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($viewer, 'inventory-adjustments-view');

    $count = InventoryCount::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'created_by_user_id' => $viewer->id,
        'tasked_by_user_id' => $viewer->id,
        'assigned_to_user_id' => $assignee->id,
        'counted_at' => Carbon::parse('2026-06-01 16:45:00'),
        'notes' => 'View-only metadata',
    ]);

    $response = $this->actingAs($viewer)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $countPayload = $payload['count'] ?? [];

    expect($response->getContent())->toContain('Count Date')
        ->and($response->getContent())->toContain('Assigned To')
        ->and($countPayload['can_view_counted_at'] ?? null)->toBeTrue()
        ->and($countPayload['can_view_assignment'] ?? null)->toBeTrue()
        ->and($countPayload['can_edit_counted_at'] ?? null)->toBeFalse()
        ->and($countPayload['can_edit_assignment'] ?? null)->toBeFalse()
        ->and(collect($countPayload['assignee_options'] ?? [])->pluck('value')->contains((string) $assignee->id))->toBeTrue();
});

it('draft detail page renders the Schedule button wired to the submit workflow action only', function () {
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
        ->and($response->getContent())->toContain('SCHEDULE')
        ->and($response->getContent())->not->toContain('>COMPLETE<');
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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $creator, [
        'title' => 'Count review',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Review count lines',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

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

it('detail payload shows manual inventory count tasks while the workflow is still draft', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-view');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    $this->actingAs($creator)->postJson(route('tasks.store'), [
        'title' => 'Check freezer shelf',
        'assigned_to_user_id' => $assignee->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'domain_record_id' => $count->id,
        'workflow_stage_id' => null,
    ])->assertCreated();

    $response = $this->actingAs($assignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['count']['current_stage_tasks'] ?? [])->firstWhere('title', 'Check freezer shelf');
    $sectionTaskPayload = collect($payload['sections']['tasks']['initialRecords'] ?? [])->firstWhere('title', 'Check freezer shelf');

    expect($taskPayload)->not->toBeNull()
        ->and(array_key_exists('workflow_stage_id', $taskPayload))->toBeTrue()
        ->and($taskPayload['workflow_stage_id'])->toBeNull()
        ->and($sectionTaskPayload)->not->toBeNull();

    expect($response->getContent())->toContain('name="workflow_domain_id" value="' . ($this->inventoryDomain)()->id . '"')
        ->and($response->getContent())->toContain('name="domain_record_id" value="' . $count->id . '"')
        ->and($response->getContent())->not->toContain('name="workflow_stage_id" value=""');

    $this->actingAs($assignee)
        ->getJson(route('inventory.counts.tasks.index', $count))
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Check freezer shelf')
        ->assertJsonPath('data.0.workflow_stage_id', null);
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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Task section item',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

    ($this->submitCount)($creator, $count)->assertOk();

    $this->actingAs($assignee)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Tasks')
        ->assertSee('Task section item')
        ->assertSee('open')
        ->assertDontSee('Assigned By:')
        ->assertDontSee('Assigned To:')
        ->assertDontSee('Completed By:')
        ->assertSee($assignee->name);
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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Inline task completion',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

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
        ->and($response->getContent())->toContain('-mx-3 rounded-none border-y border-gray-200 bg-gray-50 px-3 py-2')
        ->and($response->getContent())->toContain('inline-flex h-8 w-8')
        ->and($response->getContent())->not->toContain('Assigned By:')
        ->and($response->getContent())->not->toContain('Assigned To:')
        ->and($response->getContent())->not->toContain('Completed By:')
        ->and($response->getContent())->toContain('space-y-0 sm:space-y-3')
        ->and($response->getContent())->toContain('flex min-w-0 items-center gap-3')
        ->and($response->getContent())->toContain('shrink-0 self-center')
        ->and($response->getContent())->toContain('text-[0.65rem] font-semibold uppercase tracking-wide')
        ->and($response->getContent())->toContain('Complete');
});

it('shared detail section source supports inline task actions when the row actions menu is disabled', function () {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('showRowActionsMenu: safeConfig.showRowActionsMenu !== false')
        ->and($source)->toContain('icon: asString(safeAction.icon)')
        ->and($source)->toContain('ariaLabel: asString(safeAction.ariaLabel)')
        ->and($source)->toContain('Object.prototype.hasOwnProperty.call(safeEntry, "fallback")')
        ->and($source)->toContain('x-show="!section.showRowActionsMenu && visibleActions(record).length > 0"')
        ->and($source)->toContain('x-show="rowActionsMenuVisible(record)"')
        ->and($source)->toContain('flex flex-wrap items-center gap-4')
        ->and($source)->toContain('flex items-center justify-end gap-3 self-center')
        ->and($source)->toContain('items-center justify-end gap-2 self-center')
        ->and($source)->toContain("action.icon === 'x-mark'")
        ->and($source)->toContain('rounded-full border border-slate-300')
        ->and($source)->toContain('flex flex-col gap-4 sm:flex-row');
});

it('draft inventory count materials render the inline x-mark remove contract instead of the vertical dots menu', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '3.000000',
        'notes' => 'Draft removable line',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();
    $payload = json_decode((string) preg_replace('/^.*<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>.*$/s', '$1', $response->getContent()), true);
    $countLinesSection = is_array($payload) ? ($payload['sections']['countLines'] ?? []) : [];

    expect($countLinesSection['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($countLinesSection['actions'][0]['icon'] ?? null)->toBe('x-mark')
        ->and($countLinesSection['actions'][0]['ariaLabel'] ?? null)->toBe('Remove material line')
        ->and($countLinesSection['addRow']['enabled'] ?? null)->toBeTrue()
        ->and($countLinesSection['addRow']['placeholder'] ?? null)->toBe('Search materials')
        ->and($countLinesSection['addRow']['action']['handlerKey'] ?? null)->toBe('addSelectedCountLine')
        ->and($countLinesSection['recordClass'] ?? null)->toBe('rounded-xl sm:rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 sm:px-4 sm:py-3.5')
        ->and($countLinesSection['rowClass'] ?? null)->toBe('flex flex-row items-center justify-between gap-3')
        ->and($countLinesSection['rightMetaClass'] ?? null)->toBe('flex shrink-0 items-end justify-center text-right')
        ->and($countLinesSection['rowLayout']['secondaryFields'] ?? [])->toBe([])
        ->and($countLinesSection['rowLayout']['rightMeta'] ?? [])->toBe([])
        ->and($response->getContent())->not->toContain('d="M12 6.75a.75.75 0 1 0 0-1.5');

    $lines = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data');

    expect($lines[0]['can_edit_counted_quantity'] ?? null)->toBeFalse()
        ->and($response->getContent())->not->toContain('Draft removable line')
        ->and(array_key_exists('counted_quantity_input', $lines[0]))->toBeTrue();
});

it('inventory counts in a workflow stage do not expose removable material row actions', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();
    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );
    $payload = json_decode($matches[1] ?? '[]', true);
    $countLinesSection = $payload['sections']['countLines'] ?? [];

    expect($countLinesSection['showRowActionsMenu'] ?? null)->toBeFalse()
        ->and($countLinesSection['actions'] ?? [])->toBe([])
        ->and($countLinesSection['addRow']['enabled'] ?? null)->toBeFalse()
        ->and($response->getContent())->not->toContain('Remove material line')
        ->and($countLinesSection['rowLayout']['rightMeta'][0]['label'] ?? null)->toBe('QTY')
        ->and($countLinesSection['rowLayout']['rightMeta'][0]['field'] ?? null)->toBe('counted_quantity_input')
        ->and($countLinesSection['rowLayout']['rightMeta'][0]['compactOnMobile'] ?? null)->toBeTrue();

    $lines = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data');

    expect($lines[0]['can_edit_counted_quantity'] ?? null)->toBeTrue();
});

it('inventory count line payload omits the notes placeholder dash when notes are blank', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
        'notes' => null,
    ]);

    $lines = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data');

    expect($lines[0]['notes_display'] ?? null)->toBe('')
        ->and($lines[0]['item_display'] ?? null)->toBe($item->name . ' (' . $uom->symbol . ')');
});

it('inventory count materials rows never render notes text even when notes exist', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
        'notes' => 'Should stay hidden',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();
    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );
    $payload = json_decode($matches[1] ?? '[]', true);
    $countLinesSection = $payload['sections']['countLines'] ?? [];

    expect($countLinesSection['rowLayout']['secondaryFields'] ?? [])->toBe([])
        ->and($response->getContent())->not->toContain('Should stay hidden')
        ->and($response->getContent())->not->toContain('Notes:');
});

it('inventory count show page source handles inline draft line removal without a full page refresh', function () {
    $pageSource = file_get_contents(resource_path('js/pages/inventory-count-show.js'));

    expect($pageSource)->toContain("sectionKey === 'countLines'")
        ->and($pageSource)->toContain("safeAction.handlerKey !== 'removeCountLine'")
        ->and($pageSource)->toContain("method: 'DELETE'")
        ->and($pageSource)->toContain("const deletedLineId = responseData.deleted_line_id ?? safeRecord.id;")
        ->and($pageSource)->toContain('component.records = remainingLines;')
        ->and($pageSource)->toContain("message: responseData.message || 'Unable to remove material line.'")
        ->and($pageSource)->toContain("safeMeta.handlerKey !== 'updateCountedQuantity'")
        ->and($pageSource)->toContain("message: responseData.message || 'Unable to update counted quantity.'");
});

it('shared detail section source supports workflow-stage qty inputs without showing remove and qty together', function () {
    $source = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($source)->toContain('if (typeof this.adapters.rightMetaItems === "function")')
        ->and($source)->toContain("meta.type === 'input'")
        ->and($source)->toContain('x-on:change="performInlineMetaAction(record, meta)"')
        ->and($source)->toContain('x-on:blur="performInlineMetaAction(record, meta)"')
        ->and($source)->toContain('meta.showSuccessIcon')
        ->and($source)->toContain('x-if="meta.showSuccessIcon"')
        ->and($source)->toContain("text-emerald-500")
        ->and($source)->toContain('rightMetaControlLabelClass(meta)')
        ->and($source)->toContain('rightMetaLabelClass(meta)')
        ->and($source)->toContain('smartNumberMetaRootClass(meta)')
        ->and($source)->toContain('smartNumberMetaFrameClass(meta)')
        ->and($source)->toContain('smartNumberMetaInputClass(meta)')
        ->and($source)->toContain('text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500')
        ->and($source)->toContain('flex items-center gap-1.5 text-xs sm:gap-2.5 sm:text-sm')
        ->and($source)->toContain('w-20 sm:w-24')
        ->and($source)->toContain('px-2 py-1 text-xs sm:px-3 sm:py-1.5 sm:text-sm')
        ->and($source)->toContain('section.addRow.enabled')
        ->and($source)->toContain('data-detail-section-add-row')
        ->and($source)->toContain("recordClass: asString(safeConfig.recordClass)")
        ->and($source)->toContain('const baseClass = asString(')
        ->and($source)->toContain('this.section.recordClass,')
        ->and($source)->toContain('"rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4",')
        ->and($source)->toContain('visibleActions(record).length > 0')
        ->and($source)->toContain('handleInlineMetaAction');
});

it('inventory count materials source wires the draft add row through the reusable combobox add contract instead of the create slide-over', function () {
    $pageSource = file_get_contents(resource_path('js/pages/inventory-count-show.js'));
    $sectionSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($pageSource)->toContain("safeAction.handlerKey !== 'addSelectedCountLine'")
        ->and($pageSource)->toContain("component.sectionError = responseData.message || 'Unable to add material line.';")
        ->and($sectionSource)->toContain("x-data=\"combobox({")
        ->and($sectionSource)->toContain('x-model="addRowValue"')
        ->and($sectionSource)->toContain('x-effect="configuredOptions = section.addRow.options"')
        ->and($sectionSource)->toContain('x-on:click.stop.prevent="submitAddRow()"');
});

it('draft inventory count material delete endpoint removes the line and returns remaining lines for instant ui updates', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $itemA = ($this->makeItem)($tenant, $uom, ['name' => 'Alpha material']);
    $itemB = ($this->makeItem)($tenant, $uom, ['name' => 'Beta material']);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);
    $lineA = ($this->createLineViaApi)($user, $count, [
        'item_id' => $itemA->id,
        'counted_quantity' => '1.000000',
    ]);
    $lineB = ($this->createLineViaApi)($user, $count, [
        'item_id' => $itemB->id,
        'counted_quantity' => '2.000000',
    ]);

    $response = $this->actingAs($user)
        ->deleteJson(route('inventory.counts.lines.destroy', ['inventoryCount' => $count, 'line' => $lineA]))
        ->assertOk();

    expect(InventoryCountLine::query()->whereKey($lineA->id)->exists())->toBeFalse()
        ->and($response->json('deleted_line_id'))->toBe($lineA->id)
        ->and(collect($response->json('lines'))->pluck('id')->all())->not->toContain($lineA->id)
        ->and(collect($response->json('lines'))->pluck('id')->all())->toContain($lineB->id);
});

it('non-draft inventory counts reject material line removal server side', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('inventory.counts.lines.destroy', ['inventoryCount' => $count, 'line' => $line]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Inventory count has been submitted and materials can no longer be removed.');

    expect(InventoryCountLine::query()->whereKey($line->id)->exists())->toBeTrue();
});

it('workflow-stage inventory count line qty updates persist canonical scale six and return refreshed row data', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $uom->forceFill(['display_precision' => 2])->save();
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
        'notes' => 'Workflow editable line',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->patchJson(route('inventory.counts.lines.update', [
        'inventoryCount' => $count,
        'line' => $line,
    ]), [
        'counted_quantity' => '2.5',
    ])->assertOk();

    $line->refresh();

    expect($line->counted_quantity)->toBe('2.500000')
        ->and($response->json('line.id'))->toBe($line->id)
        ->and($response->json('line.counted_quantity'))->toBe('2.500000')
        ->and($response->json('line.counted_quantity_display'))->toBe('2.50')
        ->and($response->json('line.counted_quantity_input'))->toBe('2.50')
        ->and($response->json('line.can_edit_counted_quantity'))->toBeTrue();
});

it('workflow-stage qty input display uses uom precision zero without decimals', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $uom->forceFill(['display_precision' => 0])->save();
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2300.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $line = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data.0');

    expect($line['counted_quantity'])->toBe('2300.000000')
        ->and($line['counted_quantity_input'])->toBe('2300')
        ->and($line['counted_quantity_display'])->toBe('2300');
});

it('workflow-stage qty input display uses uom precision one with one decimal', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $uom->forceFill(['display_precision' => 1])->save();
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2300.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $line = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data.0');

    expect($line['counted_quantity_input'])->toBe('2300.0')
        ->and($line['counted_quantity_display'])->toBe('2300.0');
});

it('workflow-stage qty input display uses uom precision two with two decimals', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $uom->forceFill(['display_precision' => 2])->save();
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2300.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $line = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data.0');

    expect($line['counted_quantity_input'])->toBe('2300.00')
        ->and($line['counted_quantity_display'])->toBe('2300.00');
});

it('inventory count line list display uses the line uom snapshot after the item base uom changes', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $gram = ($this->makeUom)($tenant);
    $gram->forceFill([
        'tenant_id' => null,
        'name' => 'Gram',
        'symbol' => 'g',
        'display_precision' => 0,
    ])->save();
    $ounce = ($this->makeUom)($tenant);
    $ounce->forceFill([
        'name' => 'Test Ounce',
        'symbol' => 'test-oz',
        'display_precision' => 2,
    ])->save();
    $item = ($this->makeItem)($tenant, $gram);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2300.000000',
    ]);

    $item->forceFill(['base_uom_id' => $ounce->id])->save();

    $line = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data.0');

    expect($line['item_display'])->toBe($item->name . ' (g)')
        ->and($line['counted_quantity_display'])->toBe('2300')
        ->and($line['uom_display_precision'])->toBe(0);
});

it('workflow-stage qty input display uses uom precision six with six decimals', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $uom->forceFill(['display_precision' => 6])->save();
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '2300.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $line = $this->actingAs($user)
        ->getJson(route('inventory.counts.lines.index', $count))
        ->assertOk()
        ->json('data.0');

    expect($line['counted_quantity_input'])->toBe('2300.000000')
        ->and($line['counted_quantity_display'])->toBe('2300.000000');
});

it('inventory count materials source uses QTY label and row scoped check-circle save feedback', function () {
    $pageSource = file_get_contents(resource_path('js/pages/inventory-count-show.js'));
    $sectionSource = file_get_contents(resource_path('js/lib/js-crud-section.js'));

    expect($pageSource)->toContain("label: 'QTY'")
        ->and($pageSource)->toContain("_countedQuantitySaved = true;")
        ->and($pageSource)->toContain("window.setTimeout(() => {")
        ->and($pageSource)->toContain("_countedQuantitySaved: false")
        ->and($pageSource)->toContain("safeRecord._countedQuantitySaving = true;")
        ->and($sectionSource)->toContain("meta.showSuccessIcon")
        ->and($sectionSource)->toContain("d=\"M9 12.75 11.25 15 15 9.75\"")
        ->and($sectionSource)->toContain("text-emerald-500")
        ->and($sectionSource)->toContain("x-text=\"meta.labelBare ? meta.label :");
});

it('inventory count show page source does not use optional chaining on assignment left hand sides', function () {
    $pageSource = file_get_contents(resource_path('js/pages/inventory-count-show.js'));

    expect($pageSource)->not->toContain('?.')
        ->and($pageSource)->not->toMatch('/\\?\\.[A-Za-z0-9_$\\.\\[\\]]+\\s*(?:\\+=|-=|\\*=|\\/=|\\?\\?=|\\|\\|=|&&=|=(?!=))/');
});

it('draft inventory count line qty updates are blocked server side', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);
    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);

    $this->actingAs($user)->patchJson(route('inventory.counts.lines.update', [
        'inventoryCount' => $count,
        'line' => $line,
    ]), [
        'counted_quantity' => '2.000000',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Inventory count is still in draft and counted quantity can be updated after submission.');
});

it('workflow-stage inventory count line qty updates reject invalid quantity formats', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
    ]);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $this->actingAs($user)->patchJson(route('inventory.counts.lines.update', [
        'inventoryCount' => $count,
        'line' => $line,
    ]), [
        'counted_quantity' => 'not-a-number',
    ])->assertStatus(422);
});

it('draft inventory count add selected material creates a line and returns row payload for immediate ui updates', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Combobox add item']);
    $remainingItem = ($this->makeItem)($tenant, $uom, ['name' => 'Still selectable item']);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);

    $response = $this->actingAs($user)->postJson(route('inventory.counts.lines.store', $count), [
        'item_id' => $item->id,
    ])->assertCreated();

    expect($response->json('line.item_id'))->toBe($item->id)
        ->and($response->json('line.item_display'))->toBe($item->name . ' (' . $uom->symbol . ')')
        ->and(InventoryCountLine::query()->where('inventory_count_id', $count->id)->where('item_id', $item->id)->exists())->toBeTrue();

    $refreshedLabels = collect($response->json('section.addRow.options'))->pluck('label')->all();

    expect($refreshedLabels)->not->toContain('Combobox add item (' . $uom->symbol . ')')
        ->and($refreshedLabels)->toContain('Still selectable item (' . $uom->symbol . ')');
});

it('inventory count materials add options stay tenant scoped and exclude already-added items from the draft combobox payload', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $includedItem = ($this->makeItem)($tenant, $uom, ['name' => 'Eligible item']);
    $alreadyAddedItem = ($this->makeItem)($tenant, $uom, ['name' => 'Already added item']);
    ($this->makeItem)($otherTenant, $otherUom, ['name' => 'Cross tenant item']);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user);
    ($this->createLineViaApi)($user, $count, [
        'item_id' => $alreadyAddedItem->id,
        'counted_quantity' => '1.000000',
    ]);

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();
    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );
    $payload = json_decode($matches[1] ?? '[]', true);
    $options = $payload['sections']['countLines']['addRow']['options'] ?? [];
    $labels = collect($options)->pluck('label')->all();

    expect($labels)->toContain('Eligible item (' . $uom->symbol . ')')
        ->and($labels)->not->toContain('Already added item (' . $uom->symbol . ')')
        ->and(collect($labels)->contains(fn ($label) => str_contains($label, 'Cross tenant item')))->toBeFalse();
});

it('workflow-stage inventory counts reject adding additional material lines server side', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();

    $this->actingAs($user)->postJson(route('inventory.counts.lines.store', $count), [
        'item_id' => $item->id,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Inventory count has been submitted and cannot be modified.');
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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Already done task',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

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

    expect($response->getContent())->not->toContain('Assigned By:')
        ->and($response->getContent())->not->toContain('Completed By:')
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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $assignee, [
        'title' => 'Still open task',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $assignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

    ($this->submitCount)($creator, $count)->assertOk();

    $response = $this->actingAs($assignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['count']['current_stage_tasks'] ?? [])->first();
    $sectionTaskPayload = collect($payload['sections']['tasks']['initialRecords'] ?? [])->first();

    expect($taskPayload)->not->toBeNull()
        ->and($taskPayload['is_completed'] ?? null)->toBeFalse()
        ->and($taskPayload['available_actions'] ?? null)->toBe(['complete'])
        ->and($taskPayload['availableActions'] ?? null)->toBe(['complete'])
        ->and($taskPayload['status'] ?? null)->toBe('open')
        ->and($taskPayload['assigned_by_user_name'] ?? null)->toBe($creator->name)
        ->and($taskPayload['assigned_to_display'] ?? null)->toBe($assignee->name)
        ->and($taskPayload['completed_by_display'] ?? null)->toBe('');

    expect($sectionTaskPayload)->not->toBeNull()
        ->and($sectionTaskPayload['id'] ?? null)->toBe($taskPayload['id'])
        ->and($sectionTaskPayload['can_complete'] ?? null)->toBeTrue()
        ->and($sectionTaskPayload['available_actions'] ?? null)->toBe(['complete'])
        ->and($sectionTaskPayload['availableActions'] ?? null)->toBe(['complete']);

    $task = Task::query()->findOrFail((int) $taskPayload['id']);

    expect($response->getContent())->toContain('action="' . route('tasks.complete', $task) . '"')
        ->and($response->getContent())->toContain('Complete')
        ->and($response->getContent())->toContain('x-on:submit.prevent="completeInventoryCountTask($event)"')
        ->and($response->getContent())->toContain('data-inventory-count-task-row')
        ->and($response->getContent())->toContain('data-inventory-count-task-status')
        ->and($response->getContent())->toContain('data-inventory-count-task-complete-form')
        ->and($response->getContent())->toContain('data-inventory-count-tasks-section')
        ->and($response->getContent())->not->toContain('data-section-key="tasks"');
});

it('current inventory count assignee can complete open current-stage tasks after reassignment', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $originalAssignee = ($this->makeUser)($tenant);
    $currentAssignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-view');
    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($originalAssignee, 'inventory-adjustments-view');
    ($this->grantPermission)($originalAssignee, 'inventory-adjustments-execute');
    ($this->grantPermission)($currentAssignee, 'inventory-adjustments-view');
    ($this->grantPermission)($currentAssignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    ($this->createInventoryTaskTemplate)($tenant, $openStage, $originalAssignee, [
        'title' => 'Count review',
    ]);

    $count = ($this->createDraftCountViaApi)($creator, [
        'assigned_to_user_id' => $originalAssignee->id,
    ]);

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

    ($this->submitCount)($creator, $count)->assertOk();

    $task = Task::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('domain_record_id', $count->id)
        ->where('workflow_stage_id', $openStage->id)
        ->firstOrFail();

    $this->actingAs($creator)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => $count->fresh()->counted_at->toISOString(),
        'notes' => 'Reassigned count',
        'assigned_to_user_id' => $currentAssignee->id,
    ])->assertOk();

    $response = $this->actingAs($currentAssignee)->get('/inventory/counts/' . $count->id)->assertOk();

    preg_match(
        '/<script type="application\\/json" id="inventory-count-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['sections']['tasks']['initialRecords'] ?? [])->firstWhere('id', $task->id);

    expect($taskPayload)->not->toBeNull()
        ->and($taskPayload['can_complete'] ?? null)->toBeTrue()
        ->and($taskPayload['available_actions'] ?? null)->toBe(['complete']);

    $this->actingAs($currentAssignee)
        ->patchJson(route('tasks.complete', $task))
        ->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.completed_by_user_id', $currentAssignee->id);
});

it('advance requires current inventory workflow tasks to be completed before moving forward', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
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

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

    ($this->submitCount)($creator, $count)->assertOk();

    ($this->advanceCount)($creator, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'Complete all tasks for this stage before moving the inventory count forward.']);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('counting')
        ->and($count->posted_at)->toBeNull();
});

it('completing the assigned current stage task removes the gating block and allows advancement', function () {
    $tenant = Tenant::factory()->create();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($creator, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
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

    ($this->ensureCountHasMaterial)($creator, $tenant, $count);

    ($this->submitCount)($creator, $count)->assertOk();

    $task = Task::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('domain_record_id', $count->id)
        ->where('workflow_stage_id', $openStage->id)
        ->firstOrFail();

    $this->actingAs($assignee)->patchJson(route('tasks.complete', $task))->assertOk();

    ($this->advanceCount)($creator, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing')
        ->and($count->posted_at)->toBeNull();
});

it('submitting uses the configured first active inventory stage rather than a hardcoded default', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('key', 'completing')
        ->update([
            'sort_order' => 5,
            'is_inventory_effect_stage' => false,
        ]);

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('key', 'scheduling')
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing');
});

it('open stage blocks new material adds while still allowing counted qty updates detail edits and blocking count deletion', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $count = ($this->createDraftCountViaApi)($user);
    $line = ($this->createLineViaApi)($user, $count, [
        'item_id' => $item->id,
        'counted_quantity' => '1.000000',
        'notes' => 'Before submit',
    ]);

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();

    $expectedRemoveMessage = 'Inventory count has been submitted and materials can no longer be removed.';

    $updatedAt = now()->addDay();

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => $updatedAt->toISOString(),
        'notes' => 'Updated while open',
        'assigned_to_user_id' => $assignee->id,
    ])->assertOk()
        ->assertJsonPath('count.counted_at', $updatedAt->format('F j, Y'))
        ->assertJsonPath('count.notes', 'Updated while open')
        ->assertJsonPath('count.assigned_to_user_id', $assignee->id);

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id)
        ->assertStatus(422)
        ->assertJson(['message' => 'Inventory count has been submitted and cannot be modified.']);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id . '/lines/' . $line->id, [
        'counted_quantity' => '2.000000',
    ])->assertOk()->assertJsonPath('line.counted_quantity', '2.000000');

    $this->actingAs($user)->postJson('/inventory/counts/' . $count->id . '/lines', [
        'item_id' => $item->id,
        'counted_quantity' => '2.000000',
        'notes' => 'Open stage add',
    ])->assertStatus(422)->assertJson(['message' => 'Inventory count has been submitted and cannot be modified.']);

    $this->actingAs($user)->deleteJson('/inventory/counts/' . $count->id . '/lines/' . $line->id)
        ->assertStatus(422)
        ->assertJson(['message' => $expectedRemoveMessage]);
});

it('inventory count details ajax updates persist in draft and reject invalid date or cross-tenant assignment', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $otherTenantUser = ($this->makeUser)($otherTenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->grantPermission)($assignee, 'inventory-adjustments-execute');

    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => null,
        'notes' => '',
    ]);

    $updatedAt = Carbon::parse('2026-05-23 14:45:00');

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => $updatedAt->toISOString(),
        'notes' => 'Cycle count notes',
        'assigned_to_user_id' => $assignee->id,
    ])->assertOk()
        ->assertJsonPath('count.counted_at', $updatedAt->format('F j, Y'))
        ->assertJsonPath('count.counted_at_iso', $updatedAt->format('Y-m-d'))
        ->assertJsonPath('count.notes', 'Cycle count notes')
        ->assertJsonPath('count.assigned_to_user_id', $assignee->id)
        ->assertJsonPath('count.assigned_to_user_name', $assignee->name);

    $count->refresh();

    expect($count->counted_at->format('Y-m-d H:i'))->toBe($updatedAt->format('Y-m-d H:i'))
        ->and($count->notes)->toBe('Cycle count notes')
        ->and($count->assigned_to_user_id)->toBe($assignee->id);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => 'not-a-date',
        'notes' => 'Still invalid',
    ])->assertStatus(422)->assertJsonValidationErrors(['counted_at']);

    $this->actingAs($user)->patchJson('/inventory/counts/' . $count->id, [
        'counted_at' => now()->toISOString(),
        'assigned_to_user_id' => $otherTenantUser->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to_user_id']);
});

it('advancing a non inventory-effect stage does not post the count', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('key', 'completing')
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    $response = ($this->advanceCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('review')
        ->and($count->posted_at)->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(0)
        ->and($response->json('count.workflow_stage_key'))->toBe('review');
});

it('advancing into a manual inventory-effect stage does not post until that stage is completed', function () {
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Submit')
        ->assertDontSee('>Complete<', false);

    $response = ($this->advanceCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing')
        ->and($count->posted_at)->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(0)
        ->and($response->json('count.status'))->toBe('draft')
        ->and($response->json('count.workflow_status_label'))->toBe('COUNTED');

    $this->actingAs($user)
        ->get('/inventory/counts/' . $count->id)
        ->assertOk()
        ->assertSee('Complete');

    $finalResponse = ($this->advanceCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing')
        ->and($count->posted_at)->not->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(1)
        ->and($finalResponse->json('count.status'))->toBe('posted')
        ->and($finalResponse->json('count.workflow_status_label'))->toBe('COMPLETED');
});

it('posting failure while completing the manual inventory-effect stage leaves no partial adjustments', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completingStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completing');
    $count = ($this->createDraftCountViaApi)($user);
    $count->forceFill([
        'workflow_stage_id' => $completingStage?->id,
    ])->save();

    $response = ($this->advanceCount)($user, $count);

    $response->assertStatus(422)->assertJson(['message' => 'Inventory count must have at least one line.']);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing')
        ->and($count->posted_at)->toBeNull()
        ->and(($this->countAdjustmentsFor)($tenant, $count))->toBe(0);
});

it('blank counted quantity is allowed before completion but blocks completing the inventory effect stage', function () {
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    ($this->advanceCount)($user, $count)
        ->assertStatus(422)
        ->assertJson(['message' => 'All inventory count lines must have a counted quantity before posting.']);

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing')
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();
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

    expect($count->workflowStage?->key)->toBe('completing')
        ->and($count->status)->toBe('posted')
        ->and($count->notes)->toBe('Direct post draft')
        ->and($count->counted_at->format('Y-m-d H:i'))->toBe('2026-05-19 09:00')
        ->and($response->json('count.workflow_stage_key'))->toBe('completing');
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->postCount)($user, $count)->assertOk();

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('completing')
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id);

    $response->assertOk()
        ->assertSee('Home')
        ->assertSee('Inventory Count')
        ->assertSee('Inventory Counts')
        ->assertSee($count->name)
        ->assertSee('Counting')
        ->assertSee('Details')
        ->assertSee('Materials')
        ->assertDontSee('Count Lines')
        ->assertSee('Tasks')
        ->assertDontSee('Back to Counts')
        ->assertDontSee('Lifecycle Status')
        ->assertDontSee('Post Count')
        ->assertDontSee('Submit Count')
        ->assertSee('Submit')
        ->assertSee('data-section-key="countLines"', false)
        ->assertSee('data-inventory-count-tasks-section', false)
        ->assertDontSee('data-crud-root', false);
});

it('inventory count detail uses the shared resource detail header breadcrumb component with metadata and workflow action slots', function () {
    $source = file_get_contents(resource_path('views/inventory/counts/show.blade.php'));
    $componentSource = file_get_contents(resource_path('views/components/resource-detail-header-breadcrumb.blade.php'));

    expect($source)->toContain('x-resource-detail-header-breadcrumb')
        ->and($source)->toContain('<x-slot name="metadata">')
        ->and($source)->toContain('<x-slot name="actions">')
        ->and($componentSource)->toContain('@isset($actions)')
        ->and($componentSource)->toContain('@isset($metadata)')
        ->and($componentSource)->toContain('data-resource-detail-header-actions')
        ->and($componentSource)->toContain('data-resource-detail-header-metadata');
});

it('first manual workflow stage exposes previous and next stage buttons after automatic scheduling', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $count = ($this->createDraftCountViaApi)($user, [
        'notes' => 'First stage navigation',
    ]);

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->toContain('Submit')
        ->and($response->getContent())->toContain('SCHEDULE')
        ->and($response->getContent())->not->toContain('Back to');
});

it('non-first non-posted workflow stages can expose previous and next buttons by target stage action verb', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completing');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'review',
        'name' => 'Review',
        'action_verb' => 'REVIEW',
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))")
        ->and($response->getContent())->toContain('Count')
        ->and($response->getContent())->toContain('Submit')
        ->and($response->getContent())->not->toContain('Back to')
        ->and($response->getContent())->not->toContain('Move to');
});

it('previous-stage button renders before the next-stage button in the inventory count header', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');
    ($this->grantPermission)($user, 'inventory-adjustments-execute');
    ($this->seedInventoryWorkflow)($tenant);

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completing');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'action_verb' => 'APPROVE',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill(['sort_order' => 30])->save();
    }

    $count = ($this->createDraftCountViaApi)($user);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
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

    $openStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'counting');
    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completing');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'action_verb' => 'APPROVE',
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $response = $this->actingAs($user)->get('/inventory/counts/' . $count->id)->assertOk();

    expect($response->getContent())->toContain('Approval')
        ->and($response->getContent())->toContain('Count')
        ->and($response->getContent())->toContain('Submit')
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-previous'))")
        ->and($response->getContent())->toContain("window.dispatchEvent(new CustomEvent('inventory-count-advance'))");
});

it('draft detail page does not expose Complete as an available workflow action', function () {
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
        ->and($response->getContent())->not->toContain('>COMPLETE<');
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

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completing');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'action_verb' => 'APPROVE',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill(['sort_order' => 30])->save();
    }

    $count = ($this->createDraftCountViaApi)($executor);
    ($this->ensureCountHasMaterial)($executor, $tenant, $count);
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

    $completedStage = ($this->inventoryStages)($tenant)->firstWhere('key', 'completing');

    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->inventoryDomain)()->id,
        'key' => 'approval',
        'name' => 'Approval',
        'action_verb' => 'APPROVE',
        'description' => null,
        'sort_order' => 20,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ]);

    if ($completedStage) {
        $completedStage->forceFill(['sort_order' => 30])->save();
    }

    $count = ($this->createDraftCountViaApi)($user);
    ($this->ensureCountHasMaterial)($user, $tenant, $count);
    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();

    $this->actingAs($user)->postJson(route('inventory.counts.previous', $count))
        ->assertOk()
        ->assertJsonPath('count.workflow_stage_name', 'Counting');

    $count->refresh();

    expect($count->workflowStage?->key)->toBe('counting');
});

it('previous-stage action is blocked after inventory has posted', function () {
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

    ($this->ensureCountHasMaterial)($user, $tenant, $count);

    ($this->submitCount)($user, $count)->assertOk();
    ($this->advanceCount)($user, $count)->assertOk();
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

it('posts inventory count adjustments only for stockable items', function () {
    $tenant = Tenant::factory()->create();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-execute');

    $uom = ($this->makeUom)($tenant);
    $stockableItem = ($this->makeItem)($tenant, $uom, ['name' => 'Tracked']);
    $nonStockableItem = ($this->makeItem)($tenant, $uom, ['name' => 'Untracked', 'is_stockable' => false]);

    StockMove::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $stockableItem->id,
        'uom_id' => $uom->id,
        'quantity' => '1.000000',
        'type' => 'receipt',
        'status' => 'POSTED',
    ]);

    StockMove::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $nonStockableItem->id,
        'uom_id' => $uom->id,
        'quantity' => '1.000000',
        'type' => 'receipt',
        'status' => 'POSTED',
    ]);

    $count = ($this->createDraftCountViaApi)($user, [
        'assigned_to_user_id' => $user->id,
    ]);

    ($this->createLineViaApi)($user, $count, [
        'item_id' => $stockableItem->id,
        'counted_quantity' => '3.000000',
    ]);

    ($this->createLineViaApi)($user, $count, [
        'item_id' => $nonStockableItem->id,
        'counted_quantity' => '5.000000',
    ]);

    ($this->postCount)($user, $count)->assertOk();

    $moves = StockMove::query()
        ->where('source_type', InventoryCount::class)
        ->where('source_id', $count->id)
        ->orderBy('id')
        ->get();

    expect($moves)->toHaveCount(1)
        ->and($moves->first()->item_id)->toBe($stockableItem->id)
        ->and((string) $moves->first()->quantity)->toBe('2.000000');
});
