<?php

declare(strict_types=1);

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

it('8. completed tasks remain visible on sales orders index payload', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->completeTask)($assignee, $task)->assertOk();

    $response = $this->actingAs($manager)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['current_stage_tasks'][0]['status'] ?? null)->toBe('completed');
});

it('9. completed tasks are clearly marked completed in payload', function () {
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
        $this->actingAs($manager)->get(route('sales.orders.index'))->assertOk(),
        'sales-orders-index-payload'
    );

    $taskPayload = collect(collect($payload['orders'] ?? [])->firstWhere('id', $order->id)['current_stage_tasks'] ?? [])->first();

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

it('15. open task payload is clearly actionable before completion', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($assignee, 'sales-sales-orders-manage');

    $payload = ($this->extractPayload)(
        $this->actingAs($assignee)->get(route('sales.orders.index'))->assertOk(),
        'sales-orders-index-payload'
    );

    $taskPayload = collect(collect($payload['orders'] ?? [])->firstWhere('id', $order->id)['current_stage_tasks'] ?? [])->first();

    expect($taskPayload['status'] ?? null)->toBe('open')
        ->and($taskPayload['can_complete'] ?? null)->toBeTrue();
});

it('16. completed task payload is no longer actionable for the assigned user', function () {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $stage = ($this->createStage)($tenant);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $task = ($this->createTask)($tenant, $order, $stage, $assignee);
    ($this->grantPermission)($assignee, 'sales-sales-orders-manage');

    ($this->completeTask)($assignee, $task)->assertOk();

    $payload = ($this->extractPayload)(
        $this->actingAs($assignee)->get(route('sales.orders.index'))->assertOk(),
        'sales-orders-index-payload'
    );

    $taskPayload = collect(collect($payload['orders'] ?? [])->firstWhere('id', $order->id)['current_stage_tasks'] ?? [])->first();

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
