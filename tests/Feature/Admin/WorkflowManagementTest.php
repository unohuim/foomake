<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Database\Seeders\TenancyRolesPermissionsSeeder;
use Database\Seeders\WorkflowDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;

    $this->seed(WorkflowDomainSeeder::class);

    $this->makeTenant = fn (string $name = 'Tenant A'): Tenant => Tenant::factory()->create([
        'tenant_name' => $name,
    ]);

    $this->makeUser = fn (Tenant $tenant, array $attributes = []): User => User::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ], $attributes));

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'workflow-management-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '[]', true);

        return is_array($payload) ? $payload : [];
    };

    $this->salesDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'sales')->firstOrFail();
    $this->purchasingDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'purchasing')->firstOrFail();
    $this->manufacturingDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'manufacturing')->firstOrFail();

    $this->seedSalesStages = function (Tenant $tenant): void {
        $domain = ($this->salesDomain)();

        foreach ([
            ['key' => 'packing', 'name' => 'Packing', 'sort_order' => 10],
            ['key' => 'packed', 'name' => 'Packed', 'sort_order' => 20],
            ['key' => 'shipping', 'name' => 'Shipping', 'sort_order' => 30],
        ] as $stage) {
            WorkflowStage::withoutGlobalScopes()->updateOrCreate([
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
    };

    $this->createStage = function (
        Tenant $tenant,
        WorkflowDomain $domain,
        array $attributes = []
    ): WorkflowStage {
        $payload = array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'stage-' . fake()->unique()->slug(),
            'name' => 'Stage ' . fake()->unique()->word(),
            'description' => null,
            'sort_order' => 100,
            'is_active' => true,
        ], $attributes);

        return WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => $payload['key'],
        ], $payload);
    };

    $this->createTemplate = function (
        Tenant $tenant,
        WorkflowDomain $domain,
        WorkflowStage $stage,
        ?User $assignee = null,
        array $attributes = []
    ): WorkflowTaskTemplate {
        return WorkflowTaskTemplate::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'workflow_stage_id' => $stage->id,
            'title' => 'Template ' . fake()->unique()->word(),
            'description' => null,
            'sort_order' => 10,
            'is_active' => true,
            'default_assignee_user_id' => $assignee?->id,
        ], $attributes));
    };
});

it('1. seeds workflow domains for sales purchasing and manufacturing', function () {
    expect(WorkflowDomain::query()->orderBy('sort_order')->orderBy('id')->pluck('key')->all())
        ->toBe(['sales', 'purchasing', 'manufacturing']);
});

it('2. workflow domain keys are unique and stable', function () {
    expect(WorkflowDomain::query()->count())->toBe(3)
        ->and(WorkflowDomain::query()->distinct('key')->count('key'))->toBe(3)
        ->and(($this->salesDomain)()->name)->toBe('Sales')
        ->and(($this->purchasingDomain)()->name)->toBe('Purchasing')
        ->and(($this->manufacturingDomain)()->name)->toBe('Manufacturing');
});

it('3. workflow domains scope workflow stages', function () {
    $tenant = ($this->makeTenant)();
    $salesStage = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);
    $purchasingStage = ($this->createStage)($tenant, ($this->purchasingDomain)(), ['key' => 'packing']);

    expect($salesStage->workflow_domain_id)->not->toBe($purchasingStage->workflow_domain_id)
        ->and($salesStage->key)->toBe($purchasingStage->key);
});

it('4. workflow domains scope workflow task templates', function () {
    $tenant = ($this->makeTenant)();
    $salesStage = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);
    $purchasingStage = ($this->createStage)($tenant, ($this->purchasingDomain)(), ['key' => 'packing']);

    $salesTemplate = ($this->createTemplate)($tenant, ($this->salesDomain)(), $salesStage, null, ['title' => 'Sales template']);
    $purchasingTemplate = ($this->createTemplate)($tenant, ($this->purchasingDomain)(), $purchasingStage, null, ['title' => 'Purchasing template']);

    expect($salesTemplate->workflow_domain_id)->not->toBe($purchasingTemplate->workflow_domain_id);
});

it('5. seeded sales operational stages exist with exact keys', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);

    $keys = WorkflowStage::query()
        ->where('workflow_domain_id', ($this->salesDomain)()->id)
        ->orderBy('sort_order')
        ->pluck('key')
        ->all();

    expect($keys)->toBe(['packing', 'packed', 'shipping']);
});

it('6. system statuses are not seeded as workflow stages', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);

    expect(WorkflowStage::query()->whereIn('key', ['draft', 'open', 'completed', 'cancelled'])->exists())->toBeFalse();
});

it('7. workflow stages are tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    ($this->createStage)($tenantA, ($this->salesDomain)(), ['key' => 'packing']);
    ($this->createStage)($tenantB, ($this->salesDomain)(), ['key' => 'packing']);

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'workflow-manage');

    $this->actingAs($userA)
        ->get(route('admin.workflows.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($this->actingAs($userA)->get(route('admin.workflows.index')), 'admin-workflows-index-payload');
    $stageKeys = array_map(static fn (array $stage): string => (string) ($stage['key'] ?? ''), $payload['stages'] ?? []);

    expect(count(array_keys($stageKeys, 'packing', true)))->toBe(1);
});

it('8. workflow manage permission slug exists after seeding roles permissions', function () {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    expect(Permission::query()->where('slug', 'workflow-manage')->exists())->toBeTrue();
});

it('9. admins receive workflow manage by default from seeding', function () {
    $this->seed(TenancyRolesPermissionsSeeder::class);

    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

    expect($adminRole->permissions()->where('slug', 'workflow-manage')->exists())->toBeTrue();
});

it('10. guest cannot access admin workflows page', function () {
    $this->get(route('admin.workflows.index'))
        ->assertRedirect(route('login'));
});

it('11. user without workflow manage cannot access admin workflows page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('admin.workflows.index'))
        ->assertForbidden();
});

it('12. user with workflow manage can access admin workflows page and payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedSalesStages)($tenant);

    $response = $this->actingAs($user)
        ->get(route('admin.workflows.index'))
        ->assertOk()
        ->assertSee('Workflows')
        ->assertSee('Stages')
        ->assertSee('Tasks')
        ->assertSee('data-page="admin-workflows-index"', false);

    $payload = ($this->extractPayload)($response, 'admin-workflows-index-payload');

    expect($payload['stageStoreUrl'] ?? null)->toBe(route('admin.workflows.stages.store'))
        ->and($payload['taskTemplateStoreUrl'] ?? null)->toBe(route('admin.workflows.task-templates.store'))
        ->and($payload['domains'][0]['key'] ?? null)->toBe('sales');
});

it('13. authorized navigation shows admin workflows link', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Admin')
        ->assertSee('Workflows')
        ->assertSee(route('admin.workflows.index'), false);
});

it('14. unauthorized navigation hides admin workflows link', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Workflows');
});

it('15. admin can create a workflow stage', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $response = $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'quality-check',
        'name' => 'Quality Check',
        'description' => 'Before shipping',
        'sort_order' => 40,
    ])->assertCreated();

    expect(WorkflowStage::query()->where('key', 'quality-check')->exists())->toBeTrue()
        ->and($response->json('data.name'))->toBe('Quality Check');
});

it('16. admin can edit a workflow stage', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), [
        'key' => 'quality-check',
        'name' => 'Quality Check',
    ]);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'quality-review',
        'name' => 'Quality Review',
        'description' => 'Updated',
        'sort_order' => 50,
        'is_active' => true,
    ])->assertOk();

    expect($stage->fresh()->key)->toBe('quality-review')
        ->and($stage->fresh()->name)->toBe('Quality Review');
});

it('17. admin can deactivate and reactivate a workflow stage', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), ['is_active' => true]);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => $stage->workflow_domain_id,
        'key' => $stage->key,
        'name' => $stage->name,
        'description' => $stage->description,
        'sort_order' => $stage->sort_order,
        'is_active' => false,
    ])->assertOk();

    expect($stage->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => $stage->workflow_domain_id,
        'key' => $stage->key,
        'name' => $stage->name,
        'description' => $stage->description,
        'sort_order' => $stage->sort_order,
        'is_active' => true,
    ])->assertOk();

    expect($stage->fresh()->is_active)->toBeTrue();
});

it('18. inactive workflow stages are hidden by default from payload', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'active-stage', 'is_active' => true]);
    ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'inactive-stage', 'is_active' => false]);

    $response = $this->actingAs($user)->get(route('admin.workflows.index'));
    $payload = ($this->extractPayload)($response, 'admin-workflows-index-payload');
    $keys = array_map(static fn (array $stage): string => (string) ($stage['key'] ?? ''), $payload['stages'] ?? []);

    expect($keys)->toContain('active-stage')
        ->not->toContain('inactive-stage');
});

it('19. inactive workflow stages are visible with show inactive enabled', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'inactive-stage', 'is_active' => false]);

    $response = $this->actingAs($user)
        ->get(route('admin.workflows.index', ['show_inactive' => 1]))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'admin-workflows-index-payload');
    $keys = array_map(static fn (array $stage): string => (string) ($stage['key'] ?? ''), $payload['stages'] ?? []);

    expect($keys)->toContain('inactive-stage');
});

it('20. duplicate workflow stage keys are blocked per tenant and domain', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'packing',
        'name' => 'Packing 2',
        'description' => null,
        'sort_order' => 40,
    ])->assertStatus(422)->assertJsonValidationErrors(['key']);
});

it('21. same workflow stage key can exist in different domains', function () {
    $tenant = ($this->makeTenant)();

    ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);
    ($this->createStage)($tenant, ($this->purchasingDomain)(), ['key' => 'packing']);

    expect(WorkflowStage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', 'packing')->count())->toBe(2);
});

it('22. admin can reorder active operational stages', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $first = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing', 'sort_order' => 10]);
    $second = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packed', 'sort_order' => 20]);
    $third = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'shipping', 'sort_order' => 30]);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.reorder'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'ordered_ids' => [$third->id, $first->id, $second->id],
    ])->assertOk();

    expect($third->fresh()->sort_order)->toBe(10)
        ->and($first->fresh()->sort_order)->toBe(20)
        ->and($second->fresh()->sort_order)->toBe(30);
});

it('23. system statuses cannot be reordered through workflow stage admin because they are not workflow stages', function () {
    $tenant = ($this->makeTenant)();
    ($this->seedSalesStages)($tenant);

    expect(WorkflowStage::query()->whereIn('key', ['draft', 'open', 'completed', 'cancelled'])->exists())->toBeFalse();
});

it('24. admin can create and edit a workflow task template', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);

    $response = $this->actingAs($user)->postJson(route('admin.workflows.task-templates.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'workflow_stage_id' => $stage->id,
        'title' => 'Print packing slip',
        'description' => 'Prepare paperwork',
        'sort_order' => 10,
        'default_assignee_user_id' => $assignee->id,
    ])->assertCreated();

    $template = WorkflowTaskTemplate::query()->firstOrFail();

    expect($response->json('data.title'))->toBe('Print packing slip');

    $this->actingAs($user)->patchJson(route('admin.workflows.task-templates.update', $template), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'workflow_stage_id' => $stage->id,
        'title' => 'Print updated packing slip',
        'description' => 'Updated',
        'sort_order' => 20,
        'default_assignee_user_id' => $assignee->id,
        'is_active' => true,
    ])->assertOk();

    expect($template->fresh()->title)->toBe('Print updated packing slip');
});

it('25. admin can deactivate and reactivate a workflow task template', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);
    $template = ($this->createTemplate)($tenant, ($this->salesDomain)(), $stage);

    $this->actingAs($user)->patchJson(route('admin.workflows.task-templates.update', $template), [
        'workflow_domain_id' => $template->workflow_domain_id,
        'workflow_stage_id' => $template->workflow_stage_id,
        'title' => $template->title,
        'description' => $template->description,
        'sort_order' => $template->sort_order,
        'default_assignee_user_id' => $template->default_assignee_user_id,
        'is_active' => false,
    ])->assertOk();

    expect($template->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patchJson(route('admin.workflows.task-templates.update', $template), [
        'workflow_domain_id' => $template->workflow_domain_id,
        'workflow_stage_id' => $template->workflow_stage_id,
        'title' => $template->title,
        'description' => $template->description,
        'sort_order' => $template->sort_order,
        'default_assignee_user_id' => $template->default_assignee_user_id,
        'is_active' => true,
    ])->assertOk();

    expect($template->fresh()->is_active)->toBeTrue();
});

it('26. workflow task templates are tenant scoped and hidden cross tenant', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'workflow-manage');

    $stageA = ($this->createStage)($tenantA, ($this->salesDomain)(), ['key' => 'packing']);
    $stageB = ($this->createStage)($tenantB, ($this->salesDomain)(), ['key' => 'packing']);
    ($this->createTemplate)($tenantA, ($this->salesDomain)(), $stageA, null, ['title' => 'A']);
    $templateB = ($this->createTemplate)($tenantB, ($this->salesDomain)(), $stageB, null, ['title' => 'B']);

    $payload = ($this->extractPayload)(
        $this->actingAs($userA)->get(route('admin.workflows.index')),
        'admin-workflows-index-payload'
    );

    $titles = array_map(static fn (array $template): string => (string) ($template['title'] ?? ''), $payload['taskTemplates'] ?? []);

    expect($titles)->toContain('A')
        ->not->toContain('B');

    $this->actingAs($userA)
        ->patchJson(route('admin.workflows.task-templates.update', $templateB), [
            'workflow_domain_id' => $templateB->workflow_domain_id,
            'workflow_stage_id' => $templateB->workflow_stage_id,
            'title' => 'Nope',
            'description' => $templateB->description,
            'sort_order' => $templateB->sort_order,
            'default_assignee_user_id' => $templateB->default_assignee_user_id,
            'is_active' => $templateB->is_active,
        ])
        ->assertNotFound();
});

it('27. task template stage must belong to selected domain', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    $salesStage = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);

    $this->actingAs($user)->postJson(route('admin.workflows.task-templates.store'), [
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'workflow_stage_id' => $salesStage->id,
        'title' => 'Bad',
        'description' => null,
        'sort_order' => 10,
        'default_assignee_user_id' => null,
    ])->assertStatus(422)->assertJsonValidationErrors(['workflow_stage_id']);
});

it('28. admin can reorder task templates within a stage and inactive templates stay hidden by default', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), ['key' => 'packing']);

    $first = ($this->createTemplate)($tenant, ($this->salesDomain)(), $stage, null, ['title' => 'First', 'sort_order' => 10]);
    $second = ($this->createTemplate)($tenant, ($this->salesDomain)(), $stage, null, ['title' => 'Second', 'sort_order' => 20]);
    ($this->createTemplate)($tenant, ($this->salesDomain)(), $stage, null, ['title' => 'Hidden', 'sort_order' => 30, 'is_active' => false]);

    $this->actingAs($user)->postJson(route('admin.workflows.task-templates.reorder'), [
        'workflow_stage_id' => $stage->id,
        'ordered_ids' => [$second->id, $first->id],
    ])->assertOk();

    expect($second->fresh()->sort_order)->toBe(10)
        ->and($first->fresh()->sort_order)->toBe(20);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('admin.workflows.index')),
        'admin-workflows-index-payload'
    );

    $titles = array_map(static fn (array $template): string => (string) ($template['title'] ?? ''), $payload['taskTemplates'] ?? []);

    expect($titles)->toContain('First')
        ->toContain('Second')
        ->not->toContain('Hidden');
});
