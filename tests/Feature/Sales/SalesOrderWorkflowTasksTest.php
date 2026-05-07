<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use App\Actions\Workflows\EnsureWorkflowDomainsSeededAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use Database\Seeders\WorkflowDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WorkflowDomainSeeder::class);

    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->roleCounter = 1;
    $this->customerCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = fn (array $attributes = []): Tenant => Tenant::factory()->create(array_merge([
        'tenant_name' => 'Tenant ' . $this->tenantCounter++,
    ], $attributes));

    $this->makeUser = fn (Tenant $tenant, array $attributes = []): User => User::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
        'name' => 'User ' . $this->userCounter,
        'email' => 'sales-workflow-' . $this->userCounter++ . '@example.test',
    ], $attributes));

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'sales-workflow-role-' . $this->roleCounter++]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->salesDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'sales')->firstOrFail();

    $this->seedSalesStages = function (Tenant $tenant): array {
        $domain = ($this->salesDomain)();

        $stages = [];

        foreach ([
            ['key' => 'packing', 'name' => 'Packing', 'sort_order' => 10],
            ['key' => 'packed', 'name' => 'Packed', 'sort_order' => 20],
            ['key' => 'shipping', 'name' => 'Shipping', 'sort_order' => 30],
        ] as $stage) {
            $stages[$stage['key']] = WorkflowStage::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'workflow_domain_id' => $domain->id,
                'key' => $stage['key'],
            ], [
                'name' => $stage['name'],
                'description' => null,
                'sort_order' => $stage['sort_order'],
                'is_active' => true,
            ]);
        }

        return $stages;
    };

    $this->createCustomer = function (Tenant $tenant, array $attributes = []): object {
        $customerId = DB::table('customers')->insertGetId(array_merge([
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
        ], $attributes));

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createSalesOrder = fn (Tenant $tenant, int $customerId, array $attributes = []): SalesOrder => SalesOrder::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'customer_id' => $customerId,
        'contact_id' => null,
        'status' => SalesOrder::STATUS_OPEN,
    ], $attributes));

    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Workflow Category ' . $this->uomCounter,
        ]);

        return Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Workflow UOM ' . $this->uomCounter,
            'symbol' => 'workflow-uom-' . $this->uomCounter++,
        ]);
    };

    $this->createItem = fn (Tenant $tenant, Uom $uom, array $attributes = []): Item => Item::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Item ' . $this->itemCounter++,
        'base_uom_id' => $uom->id,
        'is_active' => true,
        'is_purchasable' => false,
        'is_sellable' => true,
        'is_manufacturable' => false,
        'default_price_cents' => 1000,
        'default_price_currency_code' => 'USD',
    ], $attributes));

    $this->createLine = fn (Tenant $tenant, SalesOrder $order, Item $item, array $attributes = []): SalesOrderLine => SalesOrderLine::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'quantity' => '1.000000',
        'unit_price_cents' => 1000,
        'unit_price_currency_code' => 'USD',
        'line_total_cents' => '1000.000000',
    ], $attributes));

    $this->createReceipt = fn (Tenant $tenant, Item $item, string $quantity): StockMove => StockMove::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => bcadd($quantity, '0', 6),
        'type' => 'receipt',
        'status' => 'POSTED',
    ]);

    $this->createTemplate = fn (
        Tenant $tenant,
        WorkflowStage $stage,
        ?User $assignee = null,
        array $attributes = []
    ): WorkflowTaskTemplate => WorkflowTaskTemplate::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'workflow_stage_id' => $stage->id,
        'title' => 'Template ' . fake()->unique()->word(),
        'description' => null,
        'sort_order' => 10,
        'is_active' => true,
        'default_assignee_user_id' => $assignee?->id,
    ], $attributes));

    $this->transitionOrder = fn (User $user, SalesOrder $order, string $status) => $this->actingAs($user)->patchJson(
        route('sales.orders.status.update', $order),
        ['status' => $status]
    );

    $this->completeTask = fn (User $user, Task $task) => $this->actingAs($user)->patchJson(route('tasks.complete', $task));

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '[]', true);

        return is_array($payload) ? $payload : [];
    };
});

it('1. entering packing after availability checks pass generates tasks from active templates', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Print packing slip']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKING);

    expect(Task::query()->where('domain_record_id', $order->id)->count())->toBe(1);
});

it('2. no tasks are generated when packing has no active templates', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    expect(Task::query()->where('domain_record_id', $order->id)->exists())->toBeFalse();
});

it('3. inactive templates do not generate tasks', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], null, ['is_active' => false]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    expect(Task::query()->where('domain_record_id', $order->id)->exists())->toBeFalse();
});

it('4. repeated transition calls do not duplicate tasks for the same stage', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], null, ['title' => 'Only once']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertStatus(422);

    expect(Task::query()->where('domain_record_id', $order->id)->count())->toBe(1);
});

it('5. generated tasks snapshot title description sort order and assignee', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    $template = ($this->createTemplate)($tenant, $stages['packing'], $assignee, [
        'title' => 'Print packing slip',
        'description' => 'Prepare paperwork',
        'sort_order' => 77,
    ]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $task = Task::query()->where('workflow_task_template_id', $template->id)->firstOrFail();

    expect($task->title)->toBe('Print packing slip')
        ->and($task->description)->toBe('Prepare paperwork')
        ->and($task->sort_order)->toBe(77)
        ->and($task->assigned_to_user_id)->toBe($assignee->id)
        ->and($task->status)->toBe('open');
});

it('6. generated tasks default assignee to the first tenant user when no explicit assignee is configured', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $firstUser = ($this->makeUser)($tenant);
    $manager = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], null, ['title' => 'Fallback assignee']);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->transitionOrder)($manager, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $task = Task::query()->where('domain_record_id', $order->id)->firstOrFail();

    expect($task->assigned_to_user_id)->toBe($firstUser->id);
});

it('7. editing a template later does not mutate already generated tasks', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    $template = ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Original title']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $task = Task::query()->where('workflow_task_template_id', $template->id)->firstOrFail();

    $template->update([
        'title' => 'Updated title',
        'description' => 'Updated description',
        'sort_order' => 999,
        'default_assignee_user_id' => $user->id,
    ]);

    expect($task->fresh()->title)->toBe('Original title')
        ->and($task->fresh()->assigned_to_user_id)->toBe($assignee->id);
});

it('8. future stage entry uses the latest active task template configuration', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Packing task']);
    $packedTemplate = ($this->createTemplate)($tenant, $stages['packed'], $assignee, ['title' => 'Old packed task']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $packedTemplate->update(['title' => 'Updated packed task']);

    $packingTask = Task::query()->where('domain_record_id', $order->id)->where('workflow_stage_id', $stages['packing']->id)->firstOrFail();
    ($this->completeTask)($assignee, $packingTask)->assertOk();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    $generatedPackedTask = Task::query()
        ->where('domain_record_id', $order->id)
        ->where('workflow_stage_id', $stages['packed']->id)
        ->firstOrFail();

    expect($generatedPackedTask->title)->toBe('Updated packed task');
});

it('9. open tasks block packing to packed with a clear error', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Complete me']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Complete all tasks for this stage before moving the sales order forward.');
});

it('10. completed current stage tasks allow packing to packed and preserve inventory behavior', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $line = ($this->createLine)($tenant, $order, $item);

    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $task = Task::query()->where('domain_record_id', $order->id)->where('workflow_stage_id', $stages['packing']->id)->firstOrFail();
    ($this->completeTask)($assignee, $task)->assertOk();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKED);

    expect(StockMove::query()
        ->where('source_type', SalesOrderLine::class)
        ->where('source_id', $line->id)
        ->where('type', 'issue')
        ->exists())->toBeTrue();
});

it('11. no task gate blocks packing to packed when current stage has no generated tasks', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();
});

it('12. inventory availability still blocks open to packing and no tasks are generated on failure', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertStatus(422);

    expect(Task::query()->where('domain_record_id', $order->id)->exists())->toBeFalse();
});

it('13. task completion does not bypass existing inventory rules', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item, ['quantity' => '2.000000']);
    ($this->createReceipt)($tenant, $item, '2.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $task = Task::query()->where('domain_record_id', $order->id)->firstOrFail();
    ($this->completeTask)($assignee, $task)->assertOk();

    StockMove::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'uom_id' => $item->base_uom_id,
        'quantity' => '-2.000000',
        'type' => 'adjustment',
        'status' => 'POSTED',
    ]);

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertStatus(422);
});

it('14. packed tasks are generated only after packing to packed succeeds', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee);
    ($this->createTemplate)($tenant, $stages['packed'], $assignee, ['title' => 'Packed task']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    expect(Task::query()->where('workflow_stage_id', $stages['packed']->id)->exists())->toBeFalse();

    $packingTask = Task::query()->where('workflow_stage_id', $stages['packing']->id)->firstOrFail();
    ($this->completeTask)($assignee, $packingTask)->assertOk();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    expect(Task::query()->where('workflow_stage_id', $stages['packed']->id)->exists())->toBeTrue();
});

it('15. shipping tasks are generated only after packed to shipping succeeds', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee);
    ($this->createTemplate)($tenant, $stages['packed'], $assignee);
    ($this->createTemplate)($tenant, $stages['shipping'], $assignee, ['title' => 'Shipping task']);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->completeTask)($assignee, Task::query()->where('workflow_stage_id', $stages['packing']->id)->firstOrFail())->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();
    ($this->completeTask)($assignee, Task::query()->where('workflow_stage_id', $stages['packed']->id)->firstOrFail())->assertOk();

    expect(Task::query()->where('workflow_stage_id', $stages['shipping']->id)->exists())->toBeFalse();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)->assertOk();

    expect(Task::query()->where('workflow_stage_id', $stages['shipping']->id)->exists())->toBeTrue();
});

it('16. open shipping tasks block shipping to completed until they are complete', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id, ['status' => SalesOrder::STATUS_SHIPPING]);

    Task::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'domain_record_id' => $order->id,
        'workflow_stage_id' => $stages['shipping']->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $assignee->id,
        'title' => 'Deliver',
        'description' => null,
        'sort_order' => 10,
        'status' => 'open',
        'completed_at' => null,
        'completed_by_user_id' => null,
    ]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_COMPLETED)->assertStatus(422);

    $task = Task::query()->where('domain_record_id', $order->id)->firstOrFail();
    ($this->completeTask)($assignee, $task)->assertOk();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_COMPLETED)->assertOk();
});

it('17. cancelling a sales order removes open tasks and keeps completed task history', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Done task', 'sort_order' => 10]);
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Open task', 'sort_order' => 20]);
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $doneTask = Task::query()->where('domain_record_id', $order->id)->orderBy('sort_order')->firstOrFail();
    ($this->completeTask)($assignee, $doneTask)->assertOk();

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_CANCELLED)->assertOk();

    expect(Task::query()->where('domain_record_id', $order->id)->where('status', 'open')->exists())->toBeFalse()
        ->and(Task::query()->where('domain_record_id', $order->id)->where('status', 'completed')->exists())->toBeTrue();
});

it('18. sales orders page payload shows current stage tasks without duplicates', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $manager = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Checklist item']);
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->transitionOrder)($manager, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $response = $this->actingAs($manager)->get(route('sales.orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-orders-index-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['current_stage_tasks'] ?? [])->toHaveCount(1)
        ->and($orderPayload['current_stage_tasks'][0]['title'] ?? null)->toBe('Checklist item');
});

it('19. customer detail orders payload also shows current stage tasks', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $manager = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);

    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->createTemplate)($tenant, $stages['packing'], $assignee, ['title' => 'Checklist item']);
    ($this->grantPermission)($manager, 'sales-customers-view');
    ($this->grantPermission)($manager, 'sales-sales-orders-manage');

    ($this->transitionOrder)($manager, $order, SalesOrder::STATUS_PACKING)->assertOk();

    $response = $this->actingAs($manager)->get(route('sales.customers.show', $order->customer_id))->assertOk();
    $payload = ($this->extractPayload)($response, 'sales-customers-show-payload');
    $orderPayload = collect($payload['orders'] ?? [])->firstWhere('id', $order->id);

    expect($orderPayload['current_stage_tasks'] ?? [])->toHaveCount(1);
});

it('20. no purchase order tasks are generated in this PR', function () {
    expect(Task::query()->where('workflow_domain_id', WorkflowDomain::query()->where('key', 'purchasing')->value('id'))->exists())->toBeFalse();
});

it('21. no manufacturing make order tasks are generated in this PR', function () {
    expect(Task::query()->where('workflow_domain_id', WorkflowDomain::query()->where('key', 'manufacturing')->value('id'))->exists())->toBeFalse();
});

it('22. adding a new active sales stage changes future sales order transition order', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);
    $customStage = WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'quality-check',
        'name' => 'Quality Check',
        'description' => null,
        'sort_order' => 5,
        'is_active' => true,
    ]);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    expect($order->fresh()->availableTransitions())->toBe([strtoupper($customStage->key), SalesOrder::STATUS_CANCELLED]);

    ($this->transitionOrder)($user, $order, strtoupper($customStage->key))
        ->assertOk()
        ->assertJsonPath('data.status', strtoupper($customStage->key));
});

it('23. deactivating a sales stage removes it from future sales order transition order', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $stages['packing']->update(['is_active' => false]);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    expect($order->fresh()->availableTransitions())->toBe([SalesOrder::STATUS_PACKED, SalesOrder::STATUS_CANCELLED]);
});

it('24. reordering active sales stages changes future sales order transition order', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $stages['shipping']->update(['sort_order' => 5]);
    $stages['packing']->update(['sort_order' => 10]);
    $stages['packed']->update(['sort_order' => 20]);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    expect($order->fresh()->availableTransitions())->toBe([SalesOrder::STATUS_SHIPPING, SalesOrder::STATUS_CANCELLED]);

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_SHIPPING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_SHIPPING);
});

it('25. seeded packing packed and shipping are defaults only and do not override db order', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);
    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'prep',
        'name' => 'Prep',
        'description' => null,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    expect($order->fresh()->availableTransitions())->toBe([strtoupper('prep'), SalesOrder::STATUS_CANCELLED]);
});

it('26. workflow domain and default sales stage seeding is idempotent', function () {
    $tenant = ($this->makeTenant)();

    app(EnsureWorkflowDomainsSeededAction::class)->execute();
    app(EnsureWorkflowDomainsSeededAction::class)->execute();
    app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
    app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);

    expect(WorkflowDomain::query()->count())->toBe(3)
        ->and(WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', ($this->salesDomain)()->id)
            ->whereIn('key', ['packing', 'packed', 'shipping'])
            ->count())->toBe(3);
});

it('27. future sales order transitions use the db stage sequence instead of a hardcoded fallback', function () {
    $tenant = ($this->makeTenant)();
    $stages = ($this->seedSalesStages)($tenant);
    $stages['packing']->update(['is_active' => false]);
    WorkflowStage::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'qa',
        'name' => 'QA',
        'description' => null,
        'sort_order' => 5,
        'is_active' => true,
    ]);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->createItem)($tenant, $uom);
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createLine)($tenant, $order, $item);
    ($this->createReceipt)($tenant, $item, '1.000000');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    expect($order->fresh()->availableTransitions())->toBe([strtoupper('qa'), SalesOrder::STATUS_CANCELLED])
        ->and($order->fresh()->availableTransitions())->not->toContain(SalesOrder::STATUS_PACKING);
});
