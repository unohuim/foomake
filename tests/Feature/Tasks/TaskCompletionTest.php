<?php

declare(strict_types=1);

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Database\Seeders\WorkflowDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WorkflowDomainSeeder::class);

    $this->roleCounter = 1;
    $this->userCounter = 1;
    $this->tenantCounter = 1;
    $this->customerCounter = 1;

    $this->makeTenant = fn (string $name = null): Tenant => Tenant::factory()->create([
        'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter++,
    ]);

    $this->makeUser = fn (Tenant $tenant, array $attributes = []): User => User::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
        'name' => 'Task User ' . $this->userCounter,
        'email' => 'task-user-' . $this->userCounter++ . '@example.test',
    ], $attributes));

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'task-completion-role-' . $this->roleCounter++]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->salesDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'sales')->firstOrFail();

    $this->createCustomer = function (Tenant $tenant): object {
        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $this->customerCounter++,
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
        ]);

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createStage = function (Tenant $tenant, array $attributes = []): WorkflowStage {
        $payload = array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => ($this->salesDomain)()->id,
            'key' => 'packing',
            'name' => 'Packing',
            'description' => null,
            'sort_order' => 10,
            'is_active' => true,
        ], $attributes);

        return WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $payload['workflow_domain_id'],
            'key' => $payload['key'],
        ], $payload);
    };

    $this->createSalesOrder = fn (Tenant $tenant, int $customerId, array $attributes = []): SalesOrder => SalesOrder::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_PACKING,
    ], $attributes));

    $this->createTask = function (
        Tenant $tenant,
        SalesOrder $order,
        WorkflowStage $stage,
        User $assignedUser,
        array $attributes = []
    ): Task {
        ($this->grantPermission)($assignedUser, 'sales-sales-orders-update');

        return Task::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => ($this->salesDomain)()->id,
            'domain_record_id' => $order->id,
            'workflow_stage_id' => $stage->id,
            'workflow_task_template_id' => null,
            'assigned_to_user_id' => $assignedUser->id,
            'title' => 'Complete checklist item',
            'description' => 'Do the thing',
            'sort_order' => 10,
            'status' => 'open',
            'completed_at' => null,
            'completed_by_user_id' => null,
        ], $attributes));
    };

    $this->completeTask = fn (User $user, Task $task) => $this->actingAs($user)->patchJson(route('tasks.complete', $task));

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '[]', true);

        return is_array($payload) ? $payload : [];
    };
});

it('1. assigned user can complete an assigned task without workflow manage', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();

    expect($task->fresh()->status)->toBe('completed');
});

it('2. assigned user does not need sales sales orders manage just to complete a task', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();
});

it('3. guest cannot complete a task', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    $this->patchJson(route('tasks.complete', $task))
        ->assertUnauthorized();
});

it('4. non assigned user cannot complete a task without an elevated override', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($otherUser, $task)->assertForbidden();
});

it('5. even workflow manage does not let a non assigned user complete the task in this PR', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($manager, 'workflow-manage');

    ($this->completeTask)($manager, $task)->assertForbidden();
});

it('6. completing a task sets completed at and completed by user id', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->completed_by_user_id)->toBe($assignee->id);
});

it('7. completing an already completed task is idempotent', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();
    $completedAt = $task->fresh()->completed_at;

    ($this->completeTask)($assignee, $task)->assertOk();

    expect($task->fresh()->completed_at?->toDateTimeString())->toBe($completedAt?->toDateTimeString());
});

it('8. completed tasks remain visible on sales order detail payload', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->completeTask)($assignee, $task)->assertOk();

    $response = $this->actingAs($manager)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');
    $orderPayload = $payload['order'] ?? [];

    expect($orderPayload['current_stage_tasks'][0]['status'] ?? null)->toBe('completed');
});

it('8a. authenticated tenant user can create a manual task without workflow context', function () {
    $tenant = ($this->makeTenant)();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);

    $this->actingAs($creator)->postJson(route('tasks.store'), [
        'title' => 'Call supplier',
        'description' => 'Confirm delivery time',
        'due_date' => '2026-06-15',
        'assigned_to_user_id' => $assignee->id,
    ])->assertCreated()
        ->assertJsonPath('data.source', Task::SOURCE_MANUAL)
        ->assertJsonPath('data.title', 'Call supplier')
        ->assertJsonPath('data.due_date', '2026-06-15')
        ->assertJsonPath('data.assigned_to_user_id', $assignee->id);

    $task = Task::query()->where('title', 'Call supplier')->firstOrFail();

    expect($task->source)->toBe(Task::SOURCE_MANUAL)
        ->and($task->workflow_domain_id)->toBeNull()
        ->and($task->domain_record_id)->toBeNull()
        ->and($task->workflow_stage_id)->toBeNull()
        ->and($task->due_date?->format('Y-m-d'))->toBe('2026-06-15')
        ->and($task->assigned_to_user_id)->toBe($assignee->id);
});

it('8b. manual task creation rejects cross-tenant assignees', function () {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $creator = ($this->makeUser)($tenant);
    $otherAssignee = ($this->makeUser)($otherTenant);

    $this->actingAs($creator)->postJson(route('tasks.store'), [
        'title' => 'Wrong tenant',
        'assigned_to_user_id' => $otherAssignee->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_to_user_id');

    expect(Task::query()->where('title', 'Wrong tenant')->exists())->toBeFalse();
});

it('8c. manual workflow-context tasks are stored against the current workflow stage', function () {
    $tenant = ($this->makeTenant)();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($assignee, 'sales-sales-orders-update');

    $this->actingAs($creator)->postJson(route('tasks.store'), [
        'title' => 'Check labels',
        'assigned_to_user_id' => $assignee->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'domain_record_id' => $order->id,
        'workflow_stage_id' => $stage->id,
    ])->assertCreated()
        ->assertJsonPath('data.source', Task::SOURCE_MANUAL);

    $task = Task::query()->where('title', 'Check labels')->firstOrFail();

    expect($task->source)->toBe(Task::SOURCE_MANUAL)
        ->and($task->workflow_domain_id)->toBe(($this->salesDomain)()->id)
        ->and($task->domain_record_id)->toBe($order->id)
        ->and($task->workflow_stage_id)->toBe($stage->id);
});

it('8ca. manual workflow-context tasks may be stored before a workflow stage exists', function () {
    $tenant = ($this->makeTenant)();
    $creator = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($assignee, 'sales-sales-orders-update');

    $this->actingAs($creator)->postJson(route('tasks.store'), [
        'title' => 'Pre-stage follow-up',
        'assigned_to_user_id' => $assignee->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'domain_record_id' => $order->id,
        'workflow_stage_id' => null,
    ])->assertCreated()
        ->assertJsonPath('data.source', Task::SOURCE_MANUAL);

    $task = Task::query()->where('title', 'Pre-stage follow-up')->firstOrFail();

    expect($task->source)->toBe(Task::SOURCE_MANUAL)
        ->and($task->workflow_domain_id)->toBe(($this->salesDomain)()->id)
        ->and($task->domain_record_id)->toBe($order->id)
        ->and($task->workflow_stage_id)->toBeNull();
});

it('8d. manual workflow-context tasks appear on the sales order current stage task payload', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->createTask)($tenant, $order, $stage, $assignee, [
        'source' => Task::SOURCE_MANUAL,
        'title' => 'Manual check',
        'due_date' => '2026-06-16',
    ]);

    $response = $this->actingAs($manager)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');
    $tasks = $payload['order']['current_stage_tasks'] ?? [];

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]['title'] ?? null)->toBe('Manual check')
        ->and($tasks[0]['due_date'] ?? null)->toBe('2026-06-16')
        ->and($tasks[0]['source'] ?? null)->toBe(Task::SOURCE_MANUAL);
});

it('8e. manual workflow-context tasks remain visible after the resource changes stages', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $packingStage = ($this->createStage)($tenant, [
        'key' => 'packing',
        'name' => 'Packing',
        'sort_order' => 20,
    ]);
    $shippingStage = ($this->createStage)($tenant, [
        'key' => 'shipping',
        'name' => 'Shipping',
        'sort_order' => 30,
    ]);
    $order = ($this->createSalesOrder)($tenant, $customer->id, [
        'status' => SalesOrder::STATUS_SHIPPING,
    ]);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    $manualTask = ($this->createTask)($tenant, $order, $packingStage, $assignee, [
        'source' => Task::SOURCE_MANUAL,
        'title' => 'Manual packing follow-up',
        'status' => Task::STATUS_COMPLETED,
        'completed_at' => now(),
        'completed_by_user_id' => $assignee->id,
    ]);

    ($this->createTask)($tenant, $order, $packingStage, $assignee, [
        'source' => Task::SOURCE_GENERATED,
        'title' => 'Old generated packing task',
    ]);

    $shippingTask = ($this->createTask)($tenant, $order, $shippingStage, $assignee, [
        'source' => Task::SOURCE_GENERATED,
        'title' => 'Current generated shipping task',
    ]);

    $response = $this->actingAs($manager)->get(route('sales.orders.show', $order))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-show-payload');
    $tasks = collect($payload['order']['current_stage_tasks'] ?? []);

    expect($tasks->pluck('id')->all())->toContain($manualTask->id)
        ->and($tasks->pluck('title')->all())->not->toContain('Old generated packing task')
        ->and($tasks->firstWhere('id', $manualTask->id)['is_completed'] ?? null)->toBeTrue();
});

it('8f. manual workflow-context tasks do not block workflow stage gates', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createTask)($tenant, $order, $stage, $assignee, [
        'source' => Task::SOURCE_MANUAL,
        'title' => 'Manual check',
    ]);

    app(AssertWorkflowStageTasksCompletedAction::class)->execute(
        $tenant->id,
        $order->id,
        $stage,
        'Blocked by generated tasks.'
    );

    expect(true)->toBeTrue();
});

it('8g. generated workflow tasks still block workflow stage gates', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createTask)($tenant, $order, $stage, $assignee, [
        'source' => Task::SOURCE_GENERATED,
        'title' => 'Generated check',
    ]);

    expect(fn () => app(AssertWorkflowStageTasksCompletedAction::class)->execute(
        $tenant->id,
        $order->id,
        $stage,
        'Blocked by generated tasks.'
    ))->toThrow(\DomainException::class, 'Blocked by generated tasks.');
});

it('8h. manual task create drawer is available on sales order detail', function () {
    $tenant = ($this->makeTenant)();
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    $this->actingAs($manager)->get(route('sales.orders.show', $order))
        ->assertOk()
        ->assertSee('Create Task')
        ->assertSee(route('tasks.store'), false)
        ->assertSee('name="due_date"', false)
        ->assertSee('showPicker', false)
        ->assertSee('workflow_domain_id', false)
        ->assertSee('workflow_stage_id', false);
});

it('8i. task drawer only submits workflow context when all workflow fields are available', function () {
    $source = file_get_contents(resource_path('views/tasks/partials/create-task-slide-over.blade.php'));

    expect($source)->toContain('$hasWorkflowContext')
        ->and($source)->toContain('@if ($hasWorkflowContext)')
        ->and($source)->toContain('name="workflow_domain_id"')
        ->and($source)->toContain('name="domain_record_id"')
        ->and($source)->toContain('name="workflow_stage_id"')
        ->and($source)->toContain('x-on:click="$el.showPicker?.()"')
        ->and($source)->not->toContain('x-on:focus="$el.showPicker?.()"')
        ->and($source)->toContain('x-data="taskCreateDrawer"');
});

it('8j. task drawer behavior is registered outside inline Alpine expressions', function () {
    $appSource = file_get_contents(resource_path('js/app.js'));
    $componentSource = file_get_contents(resource_path('js/components/task-create-drawer.js'));

    expect($appSource)->toContain('registerTaskCreateDrawer')
        ->and($componentSource)->toContain("Alpine.data('taskCreateDrawer'")
        ->and($componentSource)->toContain("Alpine.data('taskCreateSection'")
        ->and($componentSource)->toContain("window.dispatchEvent(new CustomEvent('task-created'");
});

it('8k. manual task sections listen for created tasks and render the returned task row', function () {
    $materialSource = file_get_contents(resource_path('views/materials/show.blade.php'));
    $customerSource = file_get_contents(resource_path('views/sales/customers/show.blade.php'));
    $salesOrderSource = file_get_contents(resource_path('views/sales/orders/show.blade.php'));
    $makeOrderSource = file_get_contents(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $purchaseOrderSource = file_get_contents(resource_path('views/purchasing/orders/show.blade.php'));
    $inventoryCountSource = file_get_contents(resource_path('views/inventory/counts/show.blade.php'));

    expect($materialSource)->toContain('x-on:task-created.window')
        ->and($materialSource)->toContain('createdTasks')
        ->and($materialSource)->toContain('completeCreatedTask(task)')
        ->and($customerSource)->toContain('x-on:task-created.window')
        ->and($customerSource)->toContain('createdTasks')
        ->and($customerSource)->toContain('completeCreatedTask(task)')
        ->and($salesOrderSource)->toContain('x-on:task-created.window')
        ->and($makeOrderSource)->toContain('x-on:task-created.window')
        ->and($purchaseOrderSource)->toContain('x-on:task-created.window')
        ->and($inventoryCountSource)->toContain('x-on:task-created.window')
        ->and($inventoryCountSource)->toContain('currentStageTasks')
        ->and($inventoryCountSource)->toContain('completeInventoryCountTask($event)');
});

it('8l. card crud config gives cards a safe href expression fallback', function () {
    $source = file_get_contents(resource_path('js/lib/crud-config.js'));

    expect($source)->toContain('rawMobileCard.urlExpression, "record.show_url ||')
        ->and($source)->toContain('rawDesktopCard.urlExpression, "record.show_url ||');
});

it('9. completed tasks are clearly marked completed in detail payload', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->completeTask)($assignee, $task)->assertOk();

    $payload = ($this->extractPayload)(
        $this->actingAs($manager)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    $taskPayload = collect($payload['order']['current_stage_tasks'] ?? [])->first();

    expect($taskPayload['is_completed'] ?? null)->toBeTrue();
});

it('10. task completion endpoint returns the completed task payload', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.status', 'completed');
});

it('11. cross tenant access to a task returns not found', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $assignee = ($this->makeUser)($tenantA);
    $otherTenantUser = ($this->makeUser)($tenantB);
    $customer = ($this->createCustomer)($tenantA);
    $stage = ($this->createStage)($tenantA);
    $order = ($this->createSalesOrder)($tenantA, $customer->id);
    $task = ($this->createTask)($tenantA, $order, $stage, $assignee);

    ($this->completeTask)($otherTenantUser, $task)->assertNotFound();
});

it('12. assigned user can complete a task for an order they cannot manage', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();
});

it('13. task completion does not change sales order status directly', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_PACKING);
});

it('14. task completion preserves title description and assignment snapshots', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee, [
        'title' => 'Snapshot title',
        'description' => 'Snapshot description',
    ]);

    ($this->completeTask)($assignee, $task)->assertOk();

    expect($task->fresh()->title)->toBe('Snapshot title')
        ->and($task->fresh()->description)->toBe('Snapshot description')
        ->and($task->fresh()->assigned_to_user_id)->toBe($assignee->id);
});

it('15. open task detail payload is clearly actionable before completion', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($assignee, 'sales-sales-orders-manage');

    $payload = ($this->extractPayload)(
        $this->actingAs($assignee)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    $taskPayload = collect($payload['order']['current_stage_tasks'] ?? [])->first();

    expect($taskPayload['status'] ?? null)->toBe('open')
        ->and($taskPayload['can_complete'] ?? null)->toBeTrue();
});

it('16. completed task detail payload is no longer actionable for the assigned user', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($assignee, 'sales-sales-orders-manage');

    ($this->completeTask)($assignee, $task)->assertOk();

    $payload = ($this->extractPayload)(
        $this->actingAs($assignee)->get(route('sales.orders.show', $order))->assertOk(),
        'sales-orders-show-payload'
    );

    $taskPayload = collect($payload['order']['current_stage_tasks'] ?? [])->first();

    expect($taskPayload['can_complete'] ?? null)->toBeFalse();
});

it('17. task completion route requires authentication even for valid task ids', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    $this->patchJson(route('tasks.complete', $task))->assertUnauthorized();
});

it('18. task completion cannot reopen a completed task through the same endpoint', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();
    ($this->completeTask)($assignee, $task)->assertOk();

    expect($task->fresh()->status)->toBe('completed');
});

it('19. task completion response includes completed by user id and completed at', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)
        ->assertOk()
        ->assertJsonPath('data.completed_by_user_id', $assignee->id);
});

it('20. task completion stays within the sales workflow domain in this PR', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);

    ($this->completeTask)($assignee, $task)->assertOk();

    expect($task->fresh()->workflow_domain_id)->toBe(($this->salesDomain)()->id);
});
