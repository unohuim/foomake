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
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->stageCounter = 1;
    $this->templateCounter = 1;

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
    $this->inventoryDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'inventory')->firstOrFail();

    $this->seedSalesStages = function (Tenant $tenant): void {
        $domain = ($this->salesDomain)();

        foreach ([
            ['key' => 'creating', 'name' => 'Creating', 'action_verb' => 'CREATE', 'sort_order' => 10],
            ['key' => 'packing', 'name' => 'Packing', 'action_verb' => 'PACK', 'sort_order' => 20],
            ['key' => 'shipping', 'name' => 'Shipping', 'action_verb' => 'SHIP', 'sort_order' => 30],
            ['key' => 'invoicing', 'name' => 'Invoicing', 'action_verb' => 'INVOICE', 'sort_order' => 40],
            ['key' => 'completing', 'name' => 'Completing', 'action_verb' => 'COMPLETE', 'sort_order' => 50],
        ] as $stage) {
            WorkflowStage::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'workflow_domain_id' => $domain->id,
                'key' => $stage['key'],
            ], [
                'name' => $stage['name'],
                'action_verb' => $stage['action_verb'],
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
        $sequence = $this->stageCounter;
        $this->stageCounter++;

        $payload = array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'stage-' . $sequence,
            'name' => 'Stage ' . $sequence,
            'action_verb' => 'Stage ' . $sequence,
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
        $sequence = $this->templateCounter;
        $this->templateCounter++;

        return WorkflowTaskTemplate::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'workflow_stage_id' => $stage->id,
            'title' => 'Template ' . $sequence,
            'description' => null,
            'sort_order' => 10,
            'is_active' => true,
            'default_assignee_user_id' => $assignee?->id,
        ], $attributes));
    };
});

it('1. seeds workflow domains for sales purchasing manufacturing and inventory', function () {
    expect(WorkflowDomain::query()->orderBy('sort_order')->orderBy('id')->pluck('key')->all())
        ->toBe(['sales', 'purchasing', 'manufacturing', 'inventory']);
});

it('2. workflow domain keys are unique and stable', function () {
    expect(WorkflowDomain::query()->count())->toBe(4)
        ->and(WorkflowDomain::query()->distinct('key')->count('key'))->toBe(4)
        ->and(($this->salesDomain)()->name)->toBe('Sales')
        ->and(($this->purchasingDomain)()->name)->toBe('Purchasing')
        ->and(($this->manufacturingDomain)()->name)->toBe('Manufacturing')
        ->and(($this->inventoryDomain)()->name)->toBe('Inventory');
});

it('2a. workflow stages table uses action_verb and drops stale action fields', function () {
    expect(Schema::hasColumn('workflow_stages', 'action_verb'))->toBeTrue();

    $migrationSource = file_get_contents(database_path('migrations/2026_05_28_000003_add_workflow_foundation_fields.php'));

    expect($migrationSource)->toContain("\$table->string('action_verb')->nullable()")
        ->and($migrationSource)->toContain("'action_verb' => DB::raw('button_text')")
        ->and($migrationSource)->toContain("\$table->dropColumn('button_text');")
        ->and($migrationSource)->toContain("\$table->dropColumn('action_verb');");
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
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->pluck('key')
        ->all();

    expect($keys)->toBe(['creating', 'packing', 'packed', 'shipping', 'invoicing', 'completing']);
});

it('6. sales does not seed legacy system lifecycle statuses while inventory seeds creating and completing', function () {
    $tenant = ($this->makeTenant)();
    $this->actingAs(($this->makeUser)($tenant));
    app(\App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);

    $salesStageKeys = WorkflowStage::query()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->salesDomain)()->id)
        ->pluck('key')
        ->all();

    $inventoryStageKeys = WorkflowStage::query()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->pluck('key')
        ->all();

    expect($salesStageKeys)->not->toContain('draft')
        ->and($salesStageKeys)->not->toContain('open')
        ->and($salesStageKeys)->not->toContain('cancelled')
        ->and($inventoryStageKeys)->toContain('creating')
        ->and($inventoryStageKeys)->toContain('completing');
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
        ->and($payload['domains'][0]['key'] ?? null)->toBe('sales')
        ->and($payload['stages'][0]['action_verb'] ?? null)->toBeString()
        ->and($response->getContent())->not->toContain('>Key<')
        ->and($response->getContent())->not->toContain('Reorder active stages');
});

it('12a. workflow stage admin UI hides the key field and shows action verb plus sort order', function () {
    $source = file_get_contents(resource_path('views/admin/workflows/index.blade.php'));

    expect($source)->not->toContain('>Key<')
        ->and($source)->toContain('Action verb')
        ->and($source)->toContain('Sort order')
        ->and($source)->not->toContain('Reorder active stages');
});

it('12b. workflow stage page module re-sorts the stage list after saves', function () {
    $source = file_get_contents(resource_path('js/pages/admin-workflows-index.js'));

    expect($source)->toContain('sortStages()')
        ->and($source)->toContain('this.sortStages();')
        ->and($source)->not->toContain('reorderStages()');
});

it('13. authorized navigation shows workflows in the profile dropdown', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-profile-workflows-link="desktop"', false)
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
        'name' => 'PLANNED',
        'action_verb' => 'PLAN',
        'description' => 'Before shipping',
        'sort_order' => 1,
    ])->assertCreated();

    expect(WorkflowStage::query()->where('key', 'planned')->exists())->toBeTrue()
        ->and($response->json('data.name'))->toBe('PLANNED')
        ->and($response->json('data.action_verb'))->toBe('PLAN')
        ->and($response->json('data.sort_order'))->toBe(1);
});

it('16. admin can edit a workflow stage while preserving the generated key', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), [
        'key' => 'quality-check',
        'name' => 'Quality Check',
        'action_verb' => 'CHECK',
    ]);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'PLANNED',
        'action_verb' => 'PLAN',
        'description' => 'Updated',
        'sort_order' => 2,
        'is_active' => true,
    ])->assertOk();

    expect($stage->fresh()->key)->toBe('quality-check')
        ->and($stage->fresh()->name)->toBe('PLANNED')
        ->and($stage->fresh()->action_verb)->toBe('PLAN')
        ->and($stage->fresh()->sort_order)->toBe(2);
});

it('16a. workflow stage create and update reject missing action_verb', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), [
        'key' => 'planned',
        'name' => 'PLANNED',
        'action_verb' => 'PLAN',
    ]);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'SCHEDULED',
        'sort_order' => 10,
    ])->assertStatus(422)->assertJsonValidationErrors(['action_verb']);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'PLANNED',
        'description' => null,
        'sort_order' => 10,
        'is_active' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['action_verb']);
});

it('17. admin can deactivate and reactivate a workflow stage', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    $stage = ($this->createStage)($tenant, ($this->salesDomain)(), ['is_active' => true]);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => $stage->workflow_domain_id,
        'name' => $stage->name,
        'action_verb' => $stage->action_verb,
        'description' => $stage->description,
        'sort_order' => $stage->sort_order,
        'is_active' => false,
    ])->assertOk();

    expect($stage->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stage), [
        'workflow_domain_id' => $stage->workflow_domain_id,
        'name' => $stage->name,
        'action_verb' => $stage->action_verb,
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
        'name' => 'Packing',
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

it('22. workflow stage sort orders persist exact numeric values without string concatenation', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');

    $firstResponse = $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'First Stage',
        'action_verb' => 'FIRST',
        'description' => null,
        'sort_order' => 1,
    ])->assertCreated();

    $secondResponse = $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'Second Stage',
        'action_verb' => 'SECOND',
        'description' => null,
        'sort_order' => 2,
    ])->assertCreated();

    expect($firstResponse->json('data.sort_order'))->toBe(1)
        ->and($secondResponse->json('data.sort_order'))->toBe(2)
        ->and(WorkflowStage::query()->where('name', 'First Stage')->value('sort_order'))->toBe(1)
        ->and(WorkflowStage::query()->where('name', 'Second Stage')->value('sort_order'))->toBe(2);
});

it('23. inventory workflow system stages are reorderable while sales still excludes those lifecycle keys', function () {
    $tenant = ($this->makeTenant)();
    app(\App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);

    $inventoryStages = WorkflowStage::query()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->inventoryDomain)()->id)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->pluck('key')
        ->all();

    $salesStageKeys = WorkflowStage::query()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', ($this->salesDomain)()->id)
        ->pluck('key')
        ->all();

    expect($inventoryStages)->toBe(['creating', 'completing'])
        ->and($salesStageKeys)->not->toContain('scheduled')
        ->and($salesStageKeys)->toContain('completing');
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
