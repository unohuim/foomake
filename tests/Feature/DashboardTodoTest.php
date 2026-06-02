<?php

declare(strict_types=1);

use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Database\Seeders\TenancyRolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->userCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (array $attributes = []): Tenant {
        return Tenant::factory()->create(array_merge([
            'tenant_name' => 'Dashboard Tenant ' . $this->tenantCounter++,
        ], $attributes));
    };

    $this->makeUser = function (Tenant $tenant, array $attributes = []): User {
        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
            'name' => 'Dashboard User ' . $this->userCounter,
            'email' => 'dashboard-user-' . $this->userCounter++ . '@example.test',
        ], $attributes));
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug]);
        $role = Role::query()->create(['name' => 'dashboard-role-' . $this->roleCounter++]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $suffix = Str::lower(Str::random(8));
        $category = UomCategory::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Dashboard Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Each ' . $suffix,
            'symbol' => 'ea-' . $suffix,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, string $name = 'Dashboard Item'): Item {
        return Item::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => $name . ' ' . $this->itemCounter++,
            'base_uom_id' => ($this->makeUom)($tenant)->id,
            'is_stockable' => true,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => true,
        ]);
    };

    $this->makeRecipe = function (Tenant $tenant, Item $item): Recipe {
        $recipe = Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => 'Dashboard Recipe ' . $item->id,
            'is_active' => true,
            'output_quantity' => '1.000000',
        ]);

        $version = RecipeVersion::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'version_number' => 100,
            'output_quantity' => '1.000000',
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'status' => RecipeVersion::STATUS_PUBLISHED,
            'effective_from' => now(),
            'approved_at' => now(),
        ]);

        $recipe->forceFill(['current_version_id' => $version->id])->save();

        return $recipe->fresh();
    };

    $this->makeMakeOrder = function (Tenant $tenant, User $owner, array $overrides = []): MakeOrder {
        $item = ($this->makeItem)($tenant, $overrides['item_name'] ?? 'Dashboard Make Item');
        $recipe = ($this->makeRecipe)($tenant, $item);

        return MakeOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $recipe->current_version_id,
            'output_item_id' => $item->id,
            'runs' => '1.000000',
            'expected_output_qty' => '1.000000',
            'status' => MakeOrder::STATUS_DRAFT,
            'due_date' => '2026-01-15',
            'created_by_user_id' => $owner->id,
            'made_by_user_id' => $owner->id,
        ], $overrides));
    };

    $this->makeInventoryCount = function (Tenant $tenant, ?User $assignee, array $overrides = []): InventoryCount {
        return InventoryCount::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $assignee?->id,
            'tasked_by_user_id' => $assignee?->id,
            'assigned_to_user_id' => $assignee?->id,
            'counted_at' => '2026-01-14 09:00:00',
            'notes' => 'Dashboard Inventory Count',
        ], $overrides));
    };

    $this->makePurchaseOrder = function (Tenant $tenant, array $overrides = []): PurchaseOrder {
        return PurchaseOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => null,
            'supplier_id' => null,
            'order_date' => '2026-01-16',
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'po_subtotal_cents' => 0,
            'po_grand_total_cents' => 0,
            'po_number' => 'PO-DASH-' . Str::upper(Str::random(6)),
            'notes' => null,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ], $overrides));
    };

    $this->makeSalesOrder = function (Tenant $tenant, array $overrides = []): SalesOrder {
        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Dashboard Customer ' . Str::upper(Str::random(6)),
            'status' => 'active',
            'customer_type' => 'business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return SalesOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => null,
            'order_date' => '2026-01-17',
            'status' => SalesOrder::STATUS_OPEN,
        ], $overrides));
    };

    $this->makeWorkflowStage = function (Tenant $tenant, string $domainKey, string $stageName): WorkflowStage {
        $sortOrders = [
            'sales' => 10,
            'purchasing' => 20,
            'manufacturing' => 30,
            'inventory' => 40,
        ];
        $domain = WorkflowDomain::query()->firstOrCreate([
            'key' => $domainKey,
        ], [
            'name' => ucfirst($domainKey),
            'sort_order' => $sortOrders[$domainKey] ?? 100,
        ]);

        return WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => Str::slug($stageName),
        ], [
            'name' => $stageName,
            'action_verb' => $stageName,
            'status_complete_label' => strtoupper(Str::slug($stageName, '_')),
            'completion_mode' => 'manual',
            'sort_order' => 10,
            'is_active' => true,
            'is_core' => false,
        ]);
    };

    $this->makeTask = function (
        Tenant $tenant,
        User $assignee,
        WorkflowStage $stage,
        int $domainRecordId,
        array $overrides = []
    ): Task {
        return Task::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $stage->workflow_domain_id,
            'domain_record_id' => $domainRecordId,
            'workflow_stage_id' => $stage->id,
            'workflow_task_template_id' => null,
            'assigned_to_user_id' => $assignee->id,
            'title' => 'Dashboard Stage Task',
            'description' => null,
            'sort_order' => 10,
            'status' => Task::STATUS_OPEN,
        ], $overrides));
    };
});

it('1. authenticated user can see the Todo section on the dashboard', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Todo');
});

it('2. unauthenticated user cannot access the dashboard', function (): void {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('3. Todo section renders as a detail-section accordion', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-detail-section-card', false)
        ->assertSee('data-dashboard-todo-section', false);
});

it('4. empty assigned work shows a calm empty state', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No assigned work.');
});

it('5. assigned make order workflow responsibility appears', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Make Order #' . $makeOrder->id);
});

it('6. unassigned make order workflow responsibility does not appear', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['made_by_user_id' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Make Order #' . $makeOrder->id);
});

it('7. another users make order workflow responsibility does not appear', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $makeOrder = ($this->makeMakeOrder)($tenant, $otherUser);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Make Order #' . $makeOrder->id);
});

it('8. cross-tenant make order workflow responsibility does not appear', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($otherTenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $makeOrder = ($this->makeMakeOrder)($otherTenant, $otherUser, ['made_by_user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Make Order #' . $makeOrder->id);
});

it('9. assigned make order workflow responsibility links to its resource', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('manufacturing.make-orders.show', $makeOrder), false);
});

it('10. assigned inventory count workflow responsibility appears', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant, $user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Inventory Count #' . $count->id);
});

it('11. assigned inventory count workflow responsibility links to its resource', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $count = ($this->makeInventoryCount)($tenant, $user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('inventory.counts.show', $count), false);
});

it('12. assigned stage task appears', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id, ['title' => 'Mix ingredients']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Mix ingredients');
});

it('13. unassigned stage task does not appear', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $otherUser, $stage, $makeOrder->id, ['title' => 'Other task']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Other task');
});

it('14. other users stage task does not appear', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $otherUser, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $otherUser, $stage, $makeOrder->id, ['title' => 'Someone else task']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Someone else task');
});

it('15. cross-tenant stage task does not appear', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($otherTenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($otherTenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($otherTenant, $otherUser, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($otherTenant, $user, $stage, $makeOrder->id, ['title' => 'Cross tenant task']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Cross tenant task');
});

it('16. assigned stage task links to its related resource', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('manufacturing.make-orders.show', $makeOrder), false);
});

it('17. task row shows task name', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id, ['title' => 'Pack finished goods']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Pack finished goods');
});

it('18. task row shows related resource identity', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Make Order #' . $makeOrder->id);
});

it('19. task row shows workflow stage when available', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Quality Review');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Quality Review');
});

it('20. task row shows due date when available', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, [
        'workflow_stage_id' => $stage->id,
        'due_date' => '2026-02-03',
    ]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('2026-02-03');
});

it('21. task row shows status when available', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $makeOrder->id, ['status' => Task::STATUS_OPEN]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Open');
});

it('22. dashboard remains view-only and does not expose complete-task actions for assigned open tasks', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    $task = ($this->makeTask)($tenant, $user, $stage, $makeOrder->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($task->title)
        ->assertDontSee('Complete')
        ->assertDontSee(route('tasks.complete', $task), false);
});

it('23. dashboard does not introduce a tasks route link', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="/tasks', false);
});

it('24. dashboard does not include draggable or customizable widget controls', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('draggable', false)
        ->assertDontSee('Customize widgets');
});

it('25. users without relevant permissions do not see unauthorized resource data', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    $makeOrder = ($this->makeMakeOrder)($tenant, $otherUser);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Make Order #' . $makeOrder->id);
});

it('26. super-admin bypass remains aligned with Gate before behavior', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $role = Role::query()->create(['name' => 'super-admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
    $makeOrder = ($this->makeMakeOrder)($tenant, $user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Make Order #' . $makeOrder->id);
});

it('27. seeded tasker role includes workflow execution credentials without broad view permissions', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $tasker = Role::query()->where('name', 'tasker')->firstOrFail();

    foreach ([
        'purchasing-purchase-orders-receive',
        'sales-sales-orders-update',
        'inventory-stock-view',
        'inventory-adjustments-execute',
        'inventory-make-orders-execute',
    ] as $slug) {
        expect($tasker->permissions()->where('slug', $slug)->exists())->toBeTrue();
    }

    foreach ([
        'purchasing-purchase-orders-create',
        'sales-sales-orders-manage',
        'inventory-adjustments-view',
        'inventory-make-orders-view',
    ] as $slug) {
        expect($tasker->permissions()->where('slug', $slug)->exists())->toBeFalse();
    }
});

it('28. seeded tasker user sees assigned make order Todo work', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $tenant = Tenant::query()->where('tenant_name', 'FooMake')->firstOrFail();
    $user = ($this->makeUser)($tenant, ['email' => 'assigned-tasker@example.test']);
    $tasker = Role::query()->where('name', 'tasker')->firstOrFail();
    $user->roles()->syncWithoutDetaching([$tasker->id]);
    $makeOrder = ($this->makeMakeOrder)($tenant, $user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Make Order #' . $makeOrder->id)
        ->assertSee(route('manufacturing.make-orders.show', $makeOrder), false);
});

it('29. seeded tasker user sees assigned inventory count stage Todo task', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $tenant = Tenant::query()->where('tenant_name', 'FooMake')->firstOrFail();
    $user = ($this->makeUser)($tenant, ['email' => 'inventory-tasker@example.test']);
    $tasker = Role::query()->where('name', 'tasker')->firstOrFail();
    $user->roles()->syncWithoutDetaching([$tasker->id]);
    $stage = ($this->makeWorkflowStage)($tenant, 'inventory', 'Counting');
    $count = ($this->makeInventoryCount)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $count->id, ['title' => 'Count freezer stock']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Count freezer stock')
        ->assertSee(route('inventory.counts.show', $count), false);
});

it('29b. assigned inventory count Todo task appears without broad inventory view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $stage = ($this->makeWorkflowStage)($tenant, 'inventory', 'Counting');
    $count = ($this->makeInventoryCount)($tenant, null, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $count->id, ['title' => 'Count freezer shelf']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Count freezer shelf')
        ->assertSee(route('inventory.counts.show', $count), false);
});

it('29c. dashboard does not show unauthorized inventory count links', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    $stage = ($this->makeWorkflowStage)($tenant, 'inventory', 'Counting');
    $count = ($this->makeInventoryCount)($tenant, $otherUser, ['workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $otherUser, $stage, $count->id, ['title' => 'Other counter task']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Other counter task')
        ->assertDontSee(route('inventory.counts.show', $count), false);
});

it('30. seeded tasker user sees assigned purchase order stage Todo task', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $tenant = Tenant::query()->where('tenant_name', 'FooMake')->firstOrFail();
    $user = ($this->makeUser)($tenant, ['email' => 'purchasing-tasker@example.test']);
    $tasker = Role::query()->where('name', 'tasker')->firstOrFail();
    $user->roles()->syncWithoutDetaching([$tasker->id]);
    $stage = ($this->makeWorkflowStage)($tenant, 'purchasing', 'Receiving');
    $purchaseOrder = ($this->makePurchaseOrder)($tenant, ['current_workflow_stage_id' => $stage->id]);
    ($this->makeTask)($tenant, $user, $stage, $purchaseOrder->id, ['title' => 'Receive vendor order']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Receive vendor order')
        ->assertSee(route('purchasing.orders.show', $purchaseOrder), false);
});

it('31. seeded tasker user sees assigned sales order stage Todo task', function (): void {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $tenant = Tenant::query()->where('tenant_name', 'FooMake')->firstOrFail();
    $user = ($this->makeUser)($tenant, ['email' => 'sales-tasker@example.test']);
    $tasker = Role::query()->where('name', 'tasker')->firstOrFail();
    $user->roles()->syncWithoutDetaching([$tasker->id]);
    $stage = ($this->makeWorkflowStage)($tenant, 'sales', 'Packing');
    $salesOrder = ($this->makeSalesOrder)($tenant, ['status' => 'PACKING']);
    ($this->makeTask)($tenant, $user, $stage, $salesOrder->id, ['title' => 'Pack customer order']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Pack customer order')
        ->assertSee(route('sales.orders.show', $salesOrder), false);
});

it('32. Make Order assignment options exclude users without manufacturing workflow credentials', function (): void {
    $tenant = ($this->makeTenant)();
    $viewer = ($this->makeUser)($tenant, ['name' => 'Viewer']);
    $eligible = ($this->makeUser)($tenant, ['name' => 'Eligible Maker']);
    $ineligible = ($this->makeUser)($tenant, ['name' => 'No Maker Access']);
    ($this->grantPermissions)($viewer, ['inventory-make-orders-view', 'inventory-make-orders-execute']);
    ($this->grantPermissions)($eligible, ['inventory-make-orders-view', 'inventory-make-orders-execute']);
    $makeOrder = ($this->makeMakeOrder)($tenant, $viewer);

    $response = $this->actingAs($viewer)->get(route('manufacturing.make-orders.show', $makeOrder));
    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-show-payload');
    $labels = collect(data_get($payload, 'workflow.assignee_options', []))->pluck('label')->all();

    expect($labels)->toContain('Eligible Maker')
        ->and($labels)->not->toContain('No Maker Access');
});

it('33. Make Order assignment update rejects users without manufacturing workflow credentials', function (): void {
    $tenant = ($this->makeTenant)();
    $viewer = ($this->makeUser)($tenant);
    $ineligible = ($this->makeUser)($tenant);
    ($this->grantPermissions)($viewer, ['inventory-make-orders-view', 'inventory-make-orders-execute']);
    $makeOrder = ($this->makeMakeOrder)($tenant, $viewer);

    $this->actingAs($viewer)
        ->patchJson(route('manufacturing.make-orders.assignment.update', $makeOrder), [
            'made_by_user_id' => $ineligible->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['made_by_user_id']);
});

it('34. Inventory Count assignment options exclude users without inventory workflow credentials', function (): void {
    $tenant = ($this->makeTenant)();
    $viewer = ($this->makeUser)($tenant, ['name' => 'Counter Viewer']);
    $eligible = ($this->makeUser)($tenant, ['name' => 'Eligible Counter']);
    $ineligible = ($this->makeUser)($tenant, ['name' => 'No Counter Access']);
    ($this->grantPermissions)($viewer, ['inventory-adjustments-view', 'inventory-adjustments-execute']);
    ($this->grantPermissions)($eligible, ['inventory-adjustments-view', 'inventory-adjustments-execute']);
    $count = ($this->makeInventoryCount)($tenant, $viewer);

    $response = $this->actingAs($viewer)->get(route('inventory.counts.show', $count));
    $payload = ($this->extractPayload)($response, 'inventory-count-show-payload');
    $labels = collect(data_get($payload, 'count.assignee_options', []))->pluck('label')->all();

    expect($labels)->toContain('Eligible Counter')
        ->and($labels)->not->toContain('No Counter Access');
});

it('35. Inventory Count assignment update rejects users without inventory workflow credentials', function (): void {
    $tenant = ($this->makeTenant)();
    $viewer = ($this->makeUser)($tenant);
    $ineligible = ($this->makeUser)($tenant);
    ($this->grantPermissions)($viewer, ['inventory-adjustments-view', 'inventory-adjustments-execute']);
    $count = ($this->makeInventoryCount)($tenant, $viewer);

    $this->actingAs($viewer)
        ->patchJson(route('inventory.counts.update', $count), [
            'counted_at' => '2026-01-14 09:00:00',
            'notes' => 'Still assigned carefully',
            'assigned_to_user_id' => $ineligible->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_to_user_id']);
});

it('36. Workflow admin assignee payload identifies domain-eligible users only', function (): void {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    $salesEligible = ($this->makeUser)($tenant, ['name' => 'Sales Eligible']);
    $ineligible = ($this->makeUser)($tenant, ['name' => 'No Sales Access']);
    ($this->grantPermission)($admin, 'workflow-manage');
    ($this->grantPermission)($salesEligible, 'sales-sales-orders-update');
    $salesDomain = WorkflowDomain::query()->firstOrCreate(
        ['key' => 'sales'],
        ['name' => 'Sales', 'sort_order' => 10]
    );

    $response = $this->actingAs($admin)->get(route('admin.workflows.index'));
    $payload = ($this->extractPayload)($response, 'admin-workflows-index-payload');
    $salesUser = collect($payload['users'])->firstWhere('name', 'Sales Eligible');
    $blockedUser = collect($payload['users'])->firstWhere('name', 'No Sales Access');

    expect($salesUser['eligible_workflow_domain_ids'])->toContain($salesDomain->id)
        ->and($blockedUser['eligible_workflow_domain_ids'])->not->toContain($salesDomain->id);
});

it('37. Workflow task-template assignment rejects users without domain credentials', function (): void {
    $tenant = ($this->makeTenant)();
    $admin = ($this->makeUser)($tenant);
    $ineligible = ($this->makeUser)($tenant);
    ($this->grantPermission)($admin, 'workflow-manage');
    $stage = ($this->makeWorkflowStage)($tenant, 'purchasing', 'Receiving');

    $this->actingAs($admin)
        ->postJson(route('admin.workflows.task-templates.store'), [
            'workflow_domain_id' => $stage->workflow_domain_id,
            'workflow_stage_id' => $stage->id,
            'title' => 'Receive order',
            'description' => null,
            'sort_order' => 10,
            'default_assignee_user_id' => $ineligible->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_assignee_user_id']);
});

it('38. generated workflow tasks fall back to an eligible tenant user', function (): void {
    $tenant = ($this->makeTenant)();
    $ineligible = ($this->makeUser)($tenant);
    $eligible = ($this->makeUser)($tenant);
    ($this->grantPermissions)($eligible, ['inventory-make-orders-view', 'inventory-make-orders-execute']);
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    WorkflowTaskTemplate::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $stage->workflow_domain_id,
        'workflow_stage_id' => $stage->id,
        'title' => 'Build batch',
        'sort_order' => 10,
        'is_active' => true,
        'default_assignee_user_id' => $ineligible->id,
    ]);

    app(GenerateWorkflowStageTasksAction::class)->execute($tenant->id, 123, $stage);

    expect(Task::query()->where('title', 'Build batch')->value('assigned_to_user_id'))->toBe($eligible->id);
});

it('39. generated workflow tasks require an eligible tenant user', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->makeUser)($tenant);
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    WorkflowTaskTemplate::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $stage->workflow_domain_id,
        'workflow_stage_id' => $stage->id,
        'title' => 'Build batch',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    expect(fn () => app(GenerateWorkflowStageTasksAction::class)->execute($tenant->id, 123, $stage))
        ->toThrow(DomainException::class, 'Workflow tasks require an eligible assigned user.');
});

it('40. assigned dashboard task does not render a server completion form', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    $task = ($this->makeTask)($tenant, $user, $stage, $makeOrder->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($task->title)
        ->assertDontSee('<form method="POST" action="' . route('tasks.complete', $task) . '">', false);

    expect($task->fresh()->status)->toBe(Task::STATUS_OPEN)
        ->and($task->fresh()->completed_by_user_id)->toBeNull();
});

it('41. dashboard hides completed assigned tasks', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $user, ['workflow_stage_id' => $stage->id]);
    $task = ($this->makeTask)($tenant, $user, $stage, $makeOrder->id, [
        'status' => Task::STATUS_COMPLETED,
        'completed_at' => now(),
        'completed_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($task->title)
        ->assertDontSee('Completed')
        ->assertDontSee(route('tasks.complete', $task), false);
});

it('42. dashboard task links do not expose another users completion route', function (): void {
    $tenant = ($this->makeTenant)();
    $assignee = ($this->makeUser)($tenant);
    $otherUser = ($this->makeUser)($tenant);
    ($this->grantPermission)($assignee, 'inventory-make-orders-view');
    ($this->grantPermission)($otherUser, 'inventory-make-orders-view');
    $stage = ($this->makeWorkflowStage)($tenant, 'manufacturing', 'Production');
    $makeOrder = ($this->makeMakeOrder)($tenant, $assignee, ['workflow_stage_id' => $stage->id]);
    $task = ($this->makeTask)($tenant, $assignee, $stage, $makeOrder->id);

    $this->actingAs($otherUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($task->title)
        ->assertDontSee(route('tasks.complete', $task), false);

    expect($task->fresh()->status)->toBe(Task::STATUS_OPEN);
});
