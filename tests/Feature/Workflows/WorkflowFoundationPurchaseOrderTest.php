<?php

declare(strict_types=1);

use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->supplierCounter = 1;

    $this->makeTenant = function (): Tenant {
        $tenant = Tenant::query()->create([
            'tenant_name' => 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'User ' . $this->userCounter,
            'email' => 'workflow-user-' . $this->userCounter . '@example.test',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => null,
        ]);

        $this->userCounter++;

        return $user;
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $slugs = $slug === 'purchasing-purchase-orders-receive'
            ? ['purchasing-purchase-orders-create', $slug]
            : [$slug];
        $role = Role::query()->create(['name' => 'workflow-role-' . $this->roleCounter]);

        $this->roleCounter++;

        foreach ($slugs as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $permissionSlug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->purchasingDomain = function (): WorkflowDomain {
        return WorkflowDomain::query()->where('key', 'purchasing')->firstOrFail();
    };

    $this->domain = function (string $key): WorkflowDomain {
        return WorkflowDomain::query()->where('key', $key)->firstOrFail();
    };

    $this->seedWorkflow = function (Tenant $tenant): void {
        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
    };

    $this->purchasingStages = function (Tenant $tenant) {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', ($this->purchasingDomain)()->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    };

    $this->stage = function (Tenant $tenant, string $key): WorkflowStage {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', ($this->purchasingDomain)()->id)
            ->where('key', $key)
            ->firstOrFail();
    };

    $this->domainStage = function (Tenant $tenant, string $domainKey, string $stageKey): WorkflowStage {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', ($this->domain)($domainKey)->id)
            ->where('key', $stageKey)
            ->firstOrFail();
    };

    $this->makeSupplier = function (Tenant $tenant): Supplier {
        $supplier = Supplier::query()->create([
            'tenant_id' => $tenant->id,
            'company_name' => 'Supplier ' . $this->supplierCounter,
        ]);

        $this->supplierCounter++;

        return $supplier;
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $this->uomCounter,
            'symbol' => 'u' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => true,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'is_stockable' => true,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->makeOption = function (Tenant $tenant, Supplier $supplier, Item $item, Uom $uom): ItemPurchaseOption {
        return ItemPurchaseOption::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'supplier_sku' => 'SKU-' . $item->id,
            'pack_quantity' => '1.000000',
            'pack_uom_id' => $uom->id,
        ]);
    };

    $this->makeOrder = function (Tenant $tenant, User $user, Supplier $supplier, array $attributes = []): PurchaseOrder {
        return PurchaseOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'order_date' => '2026-05-28',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_number' => 'PO-' . $tenant->id,
            'notes' => null,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
        ], $attributes));
    };

    $this->makeLine = function (
        Tenant $tenant,
        PurchaseOrder $order,
        Item $item,
        ItemPurchaseOption $option,
        int $packCount = 1
    ): PurchaseOrderLine {
        return PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $order->id,
            'item_id' => $item->id,
            'item_purchase_option_id' => $option->id,
            'pack_count' => $packCount,
            'unit_price_cents' => 100,
            'line_subtotal_cents' => 100 * $packCount,
            'unit_price_amount' => 100,
            'unit_price_currency_code' => 'USD',
            'converted_unit_price_amount' => 100,
            'fx_rate' => '1.00000000',
            'fx_rate_as_of' => '2026-05-28',
        ]);
    };

    $this->workflowPayload = function ($response): array {
        return (array) $response->json('data.workflow');
    };
});

it('1. workflow stages use action verb as the single workflow action field', function (): void {
    expect(Schema::hasColumn('workflow_stages', 'action_verb'))->toBeTrue()
        ->and(Schema::hasColumn('workflow_stages', 'button_text'))->toBeFalse()
        ->and(Schema::hasColumn('workflow_stages', 'action_label'))->toBeFalse()
        ->and(Schema::hasColumn('workflow_stages', 'status_complete_label'))->toBeTrue()
        ->and(Schema::hasColumn('workflow_stages', 'completion_mode'))->toBeTrue()
        ->and(Schema::hasColumn('workflow_stages', 'is_core'))->toBeTrue();
});

it('2. default purchasing workflow stages use present-tense names and action verbs', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    $stages = ($this->purchasingStages)($tenant);

    expect($stages->pluck('name')->all())->toBe(['Creating', 'Receiving', 'Completing'])
        ->and($stages->pluck('action_verb')->all())->toBe(['CREATE', 'RECEIVE', 'COMPLETE'])
        ->and($stages->pluck('status_complete_label')->all())->toBe(['CREATED', 'RECEIVED', 'COMPLETED'])
        ->and($stages->pluck('is_core')->all())->toBe([true, true, true])
        ->and(WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', ($this->purchasingDomain)()->id)
            ->where('key', 'partially_received')
            ->exists())->toBeFalse();
});

it('2a. user-created workflow stages default to non-core', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);

    $response = $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'name' => 'Vendor Review',
        'action_verb' => 'REVIEW',
        'status_complete_label' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        'completion_mode' => 'manual',
        'description' => null,
        'sort_order' => 15,
        'is_inventory_effect_stage' => false,
    ])->assertCreated();

    expect($response->json('data.is_core'))->toBeFalse()
        ->and((bool) WorkflowStage::withoutGlobalScopes()->where('key', 'vendor-review')->value('is_core'))->toBeFalse();
});

it('2b. core workflow stages cannot be deleted', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);

    $stage = ($this->stage)($tenant, 'creating');

    $this->actingAs($user)
        ->deleteJson(route('admin.workflows.stages.destroy', $stage))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['stage']);

    expect($stage->fresh())->not->toBeNull();
});

it('2c. non-core workflow stages can be deleted', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);

    $stage = WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'key' => 'vendor-review',
        'name' => 'Vendor Review',
        'action_verb' => 'REVIEW',
        'status_complete_label' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        'completion_mode' => 'manual',
        'description' => null,
        'sort_order' => 15,
        'is_active' => true,
        'is_core' => false,
        'is_inventory_effect_stage' => false,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('admin.workflows.stages.destroy', $stage))
        ->assertOk();

    expect(WorkflowStage::withoutGlobalScopes()->whereKey($stage->id)->exists())->toBeFalse();
});

it('2d. core workflow stages cannot be deactivated', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);
    $stage = ($this->stage)($tenant, 'creating');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => $stage->workflow_domain_id,
        'name' => $stage->name,
        'action_verb' => $stage->action_verb,
        'status_complete_label' => $stage->status_complete_label,
        'completion_mode' => $stage->completion_mode,
        'description' => $stage->description,
        'sort_order' => $stage->sort_order,
        'is_active' => false,
        'is_inventory_effect_stage' => $stage->is_inventory_effect_stage,
    ])->assertStatus(422)->assertJsonValidationErrors(['is_active']);

    expect($stage->fresh()->is_active)->toBeTrue();
});

it('2e. core workflow stage locked fields cannot be changed', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);
    $stage = ($this->stage)($tenant, 'receiving');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => $stage->workflow_domain_id,
        'name' => 'Inbound',
        'action_verb' => $stage->action_verb,
        'status_complete_label' => $stage->status_complete_label,
        'completion_mode' => $stage->completion_mode,
        'description' => $stage->description,
        'sort_order' => $stage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => $stage->is_inventory_effect_stage,
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);

    expect($stage->fresh()->name)->toBe('Receiving');
});

it('2f. arbitrary status complete labels are rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'name' => 'Vendor Review',
        'action_verb' => 'REVIEW',
        'status_complete_label' => 'RECEIVING',
        'completion_mode' => 'manual',
        'description' => null,
        'sort_order' => 15,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['status_complete_label']);
});

it('2g. partially received remains an allowed purchasing status complete label', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedWorkflow)($tenant);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'name' => 'Vendor Review',
        'action_verb' => 'REVIEW',
        'status_complete_label' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        'completion_mode' => 'manual',
        'description' => null,
        'sort_order' => 15,
        'is_inventory_effect_stage' => false,
    ])->assertCreated();

    expect(PurchaseOrder::statuses())->toContain(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
});

it('2h. purchase order partial receipt persists partially received status', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'current_workflow_stage_id' => ($this->stage)($tenant, 'receiving')->id,
        'last_completed_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);
    $line = ($this->makeLine)($tenant, $order, $item, $option, 2);

    $this->actingAs($user)->postJson(route('purchasing.orders.receipts.store', $order), [
        'received_at' => '2026-05-30',
        'reference' => null,
        'notes' => null,
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'received_quantity' => '1',
            ],
        ],
    ])->assertCreated();

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and($order->workflowStatus())->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and((int) $order->current_workflow_stage_id)->toBe(($this->stage)($tenant, 'receiving')->id);
});

it('3. default seeding updates stale stage action verbs and completion labels', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    ($this->stage)($tenant, 'creating')->forceFill([
        'action_verb' => 'CREATED',
        'status_complete_label' => 'OLD',
        'completion_mode' => 'automatic',
    ])->save();

    ($this->seedWorkflow)($tenant);

    expect(($this->stage)($tenant, 'creating')->action_verb)->toBe('CREATE')
        ->and(($this->stage)($tenant, 'creating')->status_complete_label)->toBe('CREATED')
        ->and(($this->stage)($tenant, 'creating')->completion_mode)->toBe('manual');
});

it('4. derived workflow status is draft before any stage completes', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    expect($order->workflowStatus())->toBe('DRAFT');
});

it('5. derived workflow status uses the last completed stage complete label', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'last_completed_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    expect($order->workflowStatus())->toBe('CREATED');
});

it('6. cancelled workflow derives cancelled status', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'workflow_cancelled_at' => now(),
    ]);

    expect($order->workflowStatus())->toBe('CANCELLED');
});

it('7. creating a purchase order initializes the first purchasing workflow stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->seedWorkflow)($tenant);

    $response = $this->actingAs($user)->postJson('/purchasing/orders', [])->assertCreated();
    $order = PurchaseOrder::query()->findOrFail($response->json('data.id'));

    expect((int) $order->current_workflow_stage_id)->toBe(($this->stage)($tenant, 'creating')->id)
        ->and($order->last_completed_workflow_stage_id)->toBeNull();
});

it('8. purchase order detail workflow JSON renders current status and stage action', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();

    expect($response->getContent())->toContain('"workflow"')
        ->and($response->getContent())->toContain('"status":"DRAFT"')
        ->and($response->getContent())->toContain('"actionVerb":"Create"');
});

it('9. purchase order detail renders the reusable workflow action button', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.orders.show', $order))
        ->assertOk()
        ->assertSee('data-workflow-action-button', false)
        ->assertSee('Create')
        ->assertSee('Cancel')
        ->assertSee('Create this purchase order.');
});

it('9a. purchase order detail workflow payload exposes assigned current-stage task completion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);

    $stage = ($this->stage)($tenant, 'creating');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => $stage->id,
    ]);

    $task = Task::query()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'domain_record_id' => $order->id,
        'workflow_stage_id' => $stage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $user->id,
        'title' => 'Confirm vendor terms',
        'description' => 'Review terms before creating the PO.',
        'sort_order' => 1,
        'status' => 'open',
    ]);

    $response = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();

    preg_match(
        '/<script type="application\\/json" id="purchasing-orders-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['workflow']['currentStageTasks'] ?? [])->first();

    expect($taskPayload)->not->toBeNull()
        ->and($taskPayload['id'] ?? null)->toBe($task->id)
        ->and($taskPayload['title'] ?? null)->toBe('Confirm vendor terms')
        ->and($taskPayload['can_complete'] ?? null)->toBeTrue()
        ->and($taskPayload['complete_url'] ?? null)->toBe(route('tasks.complete', $task));
});

it('9b. purchase order stage entry generates tasks for the configured default assignee', function (): void {
    $tenant = ($this->makeTenant)();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($creator, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($creator, 'purchasing-purchase-orders-receive');
    ($this->grantPermission)($assignee, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($assignee, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);

    $stage = ($this->stage)($tenant, 'creating');
    WorkflowTaskTemplate::query()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'workflow_stage_id' => $stage->id,
        'default_assignee_user_id' => $assignee->id,
        'title' => 'Confirm vendor terms',
        'description' => null,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $order = ($this->makeOrder)($tenant, $creator, $supplier);

    $response = $this->actingAs($assignee)->get(route('purchasing.orders.show', $order))->assertOk();

    preg_match(
        '/<script type="application\\/json" id="purchasing-orders-show-payload">\\s*(.*?)\\s*<\\/script>/s',
        $response->getContent(),
        $matches
    );

    $payload = json_decode($matches[1] ?? '[]', true);
    $taskPayload = collect($payload['workflow']['currentStageTasks'] ?? [])->first();

    expect($taskPayload)->not->toBeNull()
        ->and($taskPayload['title'] ?? null)->toBe('Confirm vendor terms')
        ->and($taskPayload['assigned_to_user_id'] ?? null)->toBe($assignee->id)
        ->and($taskPayload['can_complete'] ?? null)->toBeTrue();
});

it('10. completing a stage sets last completed stage and advances to the next stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.complete', $order))
        ->assertOk()
        ->assertJsonPath('data.workflow.status', 'CREATED')
        ->assertJsonPath('data.workflow.currentStage.actionVerb', 'Receive');

    $order->refresh();

    expect((int) $order->last_completed_workflow_stage_id)->toBe(($this->stage)($tenant, 'creating')->id)
        ->and((int) $order->current_workflow_stage_id)->toBe(($this->stage)($tenant, 'receiving')->id);
});

it('11. automatic stages complete until a manual stage is reached', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    ($this->stage)($tenant, 'receiving')->forceFill(['completion_mode' => 'automatic'])->save();
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.complete', $order))
        ->assertOk()
        ->assertJsonPath('data.workflow.status', 'COMPLETED')
        ->assertJsonPath('data.workflow.currentStage', null);
});

it('12. inventory effect fires when completing the inventory-impacting stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom);
    $option = ($this->makeOption)($tenant, $supplier, $item, $uom);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'status' => PurchaseOrder::STATUS_CREATED,
        'current_workflow_stage_id' => ($this->stage)($tenant, 'receiving')->id,
        'last_completed_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);
    ($this->makeLine)($tenant, $order, $item, $option, 2);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.complete', $order))
        ->assertOk()
        ->assertJsonPath('data.workflow.status', 'COMPLETED');

    expect(DB::table('stock_moves')->where('source_type', 'purchase_order_receipt_line')->exists())->toBeTrue();
});

it('13. inventory-impacting stage may be last', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    ($this->stage)($tenant, 'completing')->forceFill([
        'is_active' => false,
    ])->save();

    expect(($this->stage)($tenant, 'receiving')->fresh()->is_inventory_effect_stage)->toBeTrue();
});

it('14. required tasks block current stage completion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $stage = ($this->stage)($tenant, 'creating');
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => $stage->id,
    ]);
    WorkflowTaskTemplate::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'workflow_stage_id' => $stage->id,
        'title' => 'Approve PO',
        'description' => null,
        'sort_order' => 10,
        'is_active' => true,
        'default_assignee_user_id' => $user->id,
    ]);
    Task::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'domain_record_id' => $order->id,
        'workflow_stage_id' => $stage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $user->id,
        'title' => 'Approve PO',
        'description' => null,
        'sort_order' => 10,
        'status' => Task::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.complete', $order))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow']);
});

it('15. users without receive permission cannot complete purchase order workflow', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.complete', $order))
        ->assertForbidden();
});

it('16. tenant scoping blocks cross-tenant workflow completion', function (): void {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    $supplierB = ($this->makeSupplier)($tenantB);
    ($this->grantPermission)($userA, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenantB);
    $orderB = ($this->makeOrder)($tenantB, $userB, $supplierB, [
        'current_workflow_stage_id' => ($this->stage)($tenantB, 'creating')->id,
    ]);

    $this->actingAs($userA)
        ->postJson(route('purchasing.orders.workflow.complete', $orderB))
        ->assertNotFound();
});

it('17. cancelling a purchase order returns cancelled workflow JSON', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.cancel', $order))
        ->assertOk()
        ->assertJsonPath('data.workflow.status', 'CANCELLED')
        ->assertJsonPath('data.workflow.actions', []);

    expect($order->fresh()->workflow_cancelled_at)->not->toBeNull();
});

it('18. cancelled workflow blocks future workflow actions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
        'workflow_cancelled_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('purchasing.orders.workflow.complete', $order))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow']);
});

it('19. purchase order show does not leak literal blade javascript directives', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.orders.show', $order))
        ->assertOk()
        ->assertDontSee('@js(', false);
});

it('20. workflow action button script does not introduce global state', function (): void {
    $source = file_get_contents(resource_path('js/components/workflow-action-button.js'));

    expect($source)->not->toContain('window.workflow')
        ->and($source)->not->toContain('window.Workflow')
        ->and($source)->toContain("Alpine.data('workflowActionButton'");
});

it('21. workflow json for a new purchase order includes create and cancel actions without stale created labels', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);

    $response = $this->actingAs($user)->postJson('/purchasing/orders', [])->assertCreated();
    $order = PurchaseOrder::query()->findOrFail($response->json('data.id'));
    $show = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();

    expect($show->getContent())->toContain('"label":"Create"')
        ->and($show->getContent())->toContain('"label":"Cancel"')
        ->and($show->getContent())->not->toContain('"label":"CREATED"');
});

it('22. default completing stages are automatic except inventory completing remains manual', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    expect(($this->domainStage)($tenant, 'purchasing', 'completing')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'sales', 'completing')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'manufacturing', 'completing')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'inventory', 'completing')->completion_mode)->toBe('manual');
});

it('22a. default cancelling stages are seeded as automatic system stages', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    expect(($this->domainStage)($tenant, 'purchasing', 'cancelling')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'sales', 'cancelling')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'manufacturing', 'cancelling')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'inventory', 'cancelling')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'purchasing', 'cancelling')->is_active)->toBeFalse();
});

it('23. default work stages before completion remain manual', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    expect(($this->domainStage)($tenant, 'purchasing', 'creating')->completion_mode)->toBe('manual')
        ->and(($this->domainStage)($tenant, 'purchasing', 'receiving')->completion_mode)->toBe('manual')
        ->and(($this->domainStage)($tenant, 'sales', 'packing')->completion_mode)->toBe('manual')
        ->and(($this->domainStage)($tenant, 'sales', 'shipping')->completion_mode)->toBe('manual')
        ->and(($this->domainStage)($tenant, 'sales', 'invoicing')->completion_mode)->toBe('manual')
        ->and(($this->domainStage)($tenant, 'manufacturing', 'making')->completion_mode)->toBe('manual')
        ->and(($this->domainStage)($tenant, 'inventory', 'counting')->completion_mode)->toBe('manual');
});

it('23a. default inventory scheduling stage is automatic', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedWorkflow)($tenant);

    expect(($this->domainStage)($tenant, 'inventory', 'scheduling')->completion_mode)->toBe('automatic')
        ->and(($this->domainStage)($tenant, 'inventory', 'scheduling')->status_complete_label)->toBe('SCHEDULED')
        ->and(($this->domainStage)($tenant, 'inventory', 'counting')->status_complete_label)->toBe('COUNTED');
});

it('24. cancel remains available beside the primary current stage action', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier, [
        'current_workflow_stage_id' => ($this->stage)($tenant, 'creating')->id,
    ]);

    $show = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();

    expect($show->getContent())->toContain('"type":"complete_stage"')
        ->and($show->getContent())->toContain('"label":"Create"')
        ->and($show->getContent())->toContain('"type":"cancel"')
        ->and($show->getContent())->toContain('"label":"Cancel"');
});

it('25. purchase order workflow json initializes a missing current stage before rendering actions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $supplier = ($this->makeSupplier)($tenant);
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-receive');
    ($this->seedWorkflow)($tenant);
    $order = ($this->makeOrder)($tenant, $user, $supplier);

    $show = $this->actingAs($user)->get(route('purchasing.orders.show', $order))->assertOk();

    expect((int) $order->fresh()->current_workflow_stage_id)->toBe(($this->stage)($tenant, 'creating')->id)
        ->and($show->getContent())->toContain('"label":"Create"')
        ->and($show->getContent())->toContain('"label":"Cancel"');
});
