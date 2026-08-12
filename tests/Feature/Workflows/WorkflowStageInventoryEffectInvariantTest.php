<?php

declare(strict_types=1);

use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Database\Seeders\WorkflowDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowDomainSeeder::class);

    $this->roleCounter = 1;
    $this->stageCounter = 1;

    $this->makeTenant = fn (string $name = 'Tenant A'): Tenant => Tenant::factory()->create([
        'tenant_name' => $name,
    ]);

    $this->makeUser = fn (Tenant $tenant): User => User::factory()->create([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'workflow-invariant-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->salesDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'sales')->firstOrFail();
    $this->purchasingDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'purchasing')->firstOrFail();
    $this->manufacturingDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'manufacturing')->firstOrFail();
    $this->inventoryDomain = fn (): WorkflowDomain => WorkflowDomain::query()->where('key', 'inventory')->firstOrFail();

    $this->seedDefaultStages = function (Tenant $tenant): void {
        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
    };

    $this->domainStages = function (Tenant $tenant, WorkflowDomain $domain) {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', $domain->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    };

    $this->salesStages = function (Tenant $tenant) {
        return ($this->domainStages)($tenant, ($this->salesDomain)());
    };

    $this->createStage = function (Tenant $tenant, WorkflowDomain $domain, array $attributes = []): WorkflowStage {
        $sequence = $this->stageCounter;
        $this->stageCounter++;
        $defaultStatusCompleteLabel = match ($domain->key) {
            'sales' => 'OPEN',
            'purchasing' => 'CREATED',
            'manufacturing', 'inventory' => 'SCHEDULED',
            default => 'OPEN',
        };

        return WorkflowStage::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => 'stage-' . $sequence,
            'name' => 'Stage ' . $sequence,
            'action_verb' => 'STAGE ' . $sequence,
            'status_complete_label' => $defaultStatusCompleteLabel,
            'description' => null,
            'sort_order' => 10,
            'is_active' => true,
            'is_core' => false,
            'is_inventory_effect_stage' => false,
        ], $attributes));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s',
            $response->getContent(),
            $matches
        );

        $payload = json_decode($matches[1] ?? '[]', true);

        return is_array($payload) ? $payload : [];
    };
});

it('1. guest cannot access workflow management', function (): void {
    $this->get(route('admin.workflows.index'))
        ->assertRedirect(route('login'));
});

it('1a. sales workflow status resolution uses database grammar safe column wrapping', function (): void {
    $source = file_get_contents(base_path('app/Actions/Workflows/ResolveSalesWorkflowStageAction.php'));

    expect($source)->not->toContain('UPPER(`')
        ->and($source)->toContain("\$query->getGrammar()->wrap('status_complete_label')")
        ->and($source)->toContain("\$query->getGrammar()->wrap('key')");
});

it('2. authenticated users without workflow-manage cannot create stages', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->postJson(route('admin.workflows.stages.store'), [
            'workflow_domain_id' => ($this->salesDomain)()->id,
            'key' => 'quality-check',
            'name' => 'Quality Check',
            'action_verb' => 'QUALITY CHECK',
            'status_complete_label' => 'OPEN',
            'description' => null,
            'sort_order' => 40,
            'is_inventory_effect_stage' => false,
        ])
        ->assertForbidden();
});

it('3. seeded sales stages have exactly one active inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedDefaultStages)($tenant);

    $inventoryEffectCount = ($this->salesStages)($tenant)
        ->where('is_active', true)
        ->where('is_inventory_effect_stage', true)
        ->count();

    expect($inventoryEffectCount)->toBe(1);
});

it('4. seeded packing stage is the default inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedDefaultStages)($tenant);

    $packingStage = ($this->salesStages)($tenant)
        ->firstWhere('key', 'packing');

    expect($packingStage)->not->toBeNull()
        ->and($packingStage?->is_inventory_effect_stage)->toBeTrue()
        ->and(($this->salesStages)($tenant)->pluck('key')->all())->not->toContain('packed');
});

it('5. workflow admin payload exposes inventory-effect stage flags', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('admin.workflows.index'))->assertOk(),
        'admin-workflows-index-payload'
    );

    $packingStage = collect($payload['stages'] ?? [])
        ->firstWhere('key', 'packing');

    expect($packingStage)->not->toBeNull()
        ->and($packingStage)->toHaveKey('is_inventory_effect_stage')
        ->and($packingStage['is_inventory_effect_stage'] ?? null)->toBeTrue()
        ->and(collect($payload['stages'] ?? [])->pluck('key')->all())->not->toContain('packed');
});

it('6. admin can create an additional sales stage without changing the inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'quality-check',
        'name' => 'Quality Check',
        'action_verb' => 'QUALITY CHECK',
        'status_complete_label' => 'OPEN',
        'description' => 'Post-pack review',
        'sort_order' => 40,
        'is_inventory_effect_stage' => false,
    ])->assertCreated();

    expect(($this->salesStages)($tenant)->where('is_inventory_effect_stage', true)->count())->toBe(1)
        ->and(($this->salesStages)($tenant)->pluck('key')->all())->toContain('quality-check');
});

it('7. creating a new marked sales stage clears the previous inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $response = $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'quality-check',
        'name' => 'Quality Check',
        'action_verb' => 'QUALITY CHECK',
        'status_complete_label' => 'OPEN',
        'description' => null,
        'sort_order' => 40,
        'is_inventory_effect_stage' => true,
    ])->assertCreated();

    $qualityStage = WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('key', 'quality-check')
        ->firstOrFail();

    $packingStage = ($this->salesStages)($tenant)->firstWhere('key', 'packing');

    expect($response->json('data.is_inventory_effect_stage'))->toBeTrue()
        ->and($qualityStage->is_inventory_effect_stage)->toBeTrue()
        ->and($packingStage?->fresh()->is_inventory_effect_stage)->toBeFalse();
});

it('8. updating a sales stage to marked true clears the previous inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $shippingStage = ($this->salesStages)($tenant)->firstWhere('key', 'shipping');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $shippingStage), [
        'workflow_domain_id' => $shippingStage->workflow_domain_id,
        'key' => $shippingStage->key,
        'name' => $shippingStage->name,
        'action_verb' => $shippingStage->action_verb,
        'description' => $shippingStage->description,
        'sort_order' => $shippingStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ])->assertOk();

    $packingStage = ($this->salesStages)($tenant)->firstWhere('key', 'packing');

    expect($shippingStage->fresh()->is_inventory_effect_stage)->toBeTrue()
        ->and($packingStage?->fresh()->is_inventory_effect_stage)->toBeFalse();
});

it('9. removing the only sales inventory-effect stage is rejected', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $packingStage = ($this->salesStages)($tenant)->firstWhere('key', 'packing');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $packingStage), [
        'workflow_domain_id' => $packingStage->workflow_domain_id,
        'key' => $packingStage->key,
        'name' => $packingStage->name,
        'action_verb' => $packingStage->action_verb,
        'description' => $packingStage->description,
        'sort_order' => $packingStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['is_inventory_effect_stage']);
});

it('10. deactivating a core sales stage is rejected by the core lock', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $packingStage = ($this->salesStages)($tenant)->firstWhere('key', 'packing');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $packingStage), [
        'workflow_domain_id' => $packingStage->workflow_domain_id,
        'key' => $packingStage->key,
        'name' => $packingStage->name,
        'action_verb' => $packingStage->action_verb,
        'description' => $packingStage->description,
        'sort_order' => $packingStage->sort_order,
        'is_active' => false,
        'is_inventory_effect_stage' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['is_active']);
});

it('11. core sales stages cannot be deactivated through workflow management', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $stages = ($this->salesStages)($tenant)->keyBy('key');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $stages['shipping']), [
        'workflow_domain_id' => $stages['shipping']->workflow_domain_id,
        'key' => $stages['shipping']->key,
        'name' => $stages['shipping']->name,
        'action_verb' => $stages['shipping']->action_verb,
        'description' => $stages['shipping']->description,
        'sort_order' => $stages['shipping']->sort_order,
        'is_active' => false,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['is_active']);

    expect($stages['shipping']->fresh()->is_active)->toBeTrue();
});

it('12. sales workflow may deactivate non-core non-inventory stages while one is marked', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $reviewStage = ($this->createStage)($tenant, ($this->salesDomain)(), [
        'key' => 'reviewing',
        'name' => 'Reviewing',
        'action_verb' => 'REVIEW',
        'sort_order' => 35,
    ]);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $reviewStage), [
        'workflow_domain_id' => $reviewStage->workflow_domain_id,
        'key' => $reviewStage->key,
        'name' => $reviewStage->name,
        'action_verb' => $reviewStage->action_verb,
        'description' => $reviewStage->description,
        'sort_order' => $reviewStage->sort_order,
        'is_active' => false,
        'is_inventory_effect_stage' => false,
    ])->assertOk();

    expect(($this->salesStages)($tenant)->where('is_active', true)->count())->toBe(5)
        ->and(($this->salesStages)($tenant)->where('is_active', true)->where('is_inventory_effect_stage', true)->count())->toBe(1);
});

it('13. reordering core sales stages is rejected by the core lock', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $stages = ($this->salesStages)($tenant)->keyBy('key');

    $this->actingAs($user)->postJson(route('admin.workflows.stages.reorder'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'ordered_ids' => [$stages['shipping']->id, $stages['packing']->id, $stages['creating']->id],
    ])->assertStatus(422)->assertJsonValidationErrors(['ordered_ids']);

    $refreshedStages = ($this->salesStages)($tenant);

    expect($refreshedStages->where('is_inventory_effect_stage', true)->count())->toBe(1)
        ->and($refreshedStages->firstWhere('key', 'packing')?->fresh()->is_inventory_effect_stage)->toBeTrue();
});

it('14. inventory-effect invariant validation keeps the existing json error shape', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $packingStage = ($this->salesStages)($tenant)->firstWhere('key', 'packing');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $packingStage), [
        'workflow_domain_id' => $packingStage->workflow_domain_id,
        'key' => $packingStage->key,
        'name' => $packingStage->name,
        'action_verb' => $packingStage->action_verb,
        'description' => $packingStage->description,
        'sort_order' => $packingStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['is_inventory_effect_stage']);
});

it('15. moving a core sales stage into purchasing is rejected by the core lock', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $packingStage = ($this->salesStages)($tenant)->firstWhere('key', 'packing');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $packingStage), [
        'workflow_domain_id' => ($this->purchasingDomain)()->id,
        'key' => 'receiving-check',
        'name' => 'Receiving Check',
        'action_verb' => 'RECEIVING CHECK',
        'status_complete_label' => 'RECEIVED',
        'description' => $packingStage->description,
        'sort_order' => $packingStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['workflow_domain_id']);
});

it('16. inventory workflow domain exists as a fixed system-owned domain', function (): void {
    expect(($this->inventoryDomain)()->name)->toBe('Inventory');
});

it('17. inventory defaults seed scheduling counting and completing with completing as the marker', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedDefaultStages)($tenant);

    $inventoryStages = ($this->domainStages)($tenant, ($this->inventoryDomain)());

    expect($inventoryStages->pluck('key')->all())->toBe(['scheduling', 'counting', 'completing'])
        ->and($inventoryStages->firstWhere('key', 'completing')?->is_inventory_effect_stage)->toBeTrue()
        ->and($inventoryStages->where('is_active', true)->where('is_inventory_effect_stage', true)->count())->toBe(1);
});

it('18. purchasing defaults are seeded with a single active inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedDefaultStages)($tenant);

    $purchasingStages = ($this->domainStages)($tenant, ($this->purchasingDomain)());

    expect($purchasingStages->pluck('key')->all())->toBe(['creating', 'receiving', 'completing'])
        ->and($purchasingStages->firstWhere('key', 'receiving')?->is_inventory_effect_stage)->toBeTrue()
        ->and($purchasingStages->where('is_active', true)->where('is_inventory_effect_stage', true)->count())->toBe(1);
});

it('19. manufacturing defaults are seeded with a single active inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedDefaultStages)($tenant);

    $manufacturingStages = ($this->domainStages)($tenant, ($this->manufacturingDomain)());

    expect($manufacturingStages->pluck('key')->all())->toBe(['creating', 'making', 'completing'])
        ->and($manufacturingStages->firstWhere('key', 'making')?->is_inventory_effect_stage)->toBeTrue()
        ->and($manufacturingStages->where('is_active', true)->where('is_inventory_effect_stage', true)->count())->toBe(1);
});

it('20. all stock-impacting workflow domains have exactly one active inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->seedDefaultStages)($tenant);

    foreach ([
        ($this->salesDomain)(),
        ($this->purchasingDomain)(),
        ($this->manufacturingDomain)(),
        ($this->inventoryDomain)(),
    ] as $domain) {
        $stages = ($this->domainStages)($tenant, $domain);

        expect($stages->where('is_active', true)->where('is_inventory_effect_stage', true)->count())
            ->toBe(1);
    }
});

it('21. purchasing workflow cannot lose its only inventory-effect stage', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $receivingStage = ($this->domainStages)($tenant, ($this->purchasingDomain)())
        ->firstWhere('key', 'receiving');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $receivingStage), [
        'workflow_domain_id' => $receivingStage->workflow_domain_id,
        'key' => $receivingStage->key,
        'name' => $receivingStage->name,
        'action_verb' => $receivingStage->action_verb,
        'description' => $receivingStage->description,
        'sort_order' => $receivingStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['is_inventory_effect_stage']);
});

it('22. purchasing stage updates may reassign the marker while preserving a single owner', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $completedStage = ($this->domainStages)($tenant, ($this->purchasingDomain)())
        ->firstWhere('key', 'completing');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $completedStage), [
        'workflow_domain_id' => $completedStage->workflow_domain_id,
        'key' => $completedStage->key,
        'name' => $completedStage->name,
        'action_verb' => $completedStage->action_verb,
        'description' => $completedStage->description,
        'sort_order' => $completedStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ])->assertOk();

    $purchasingStages = ($this->domainStages)($tenant, ($this->purchasingDomain)());

    expect($purchasingStages->firstWhere('key', 'completing')?->is_inventory_effect_stage)->toBeTrue()
        ->and($purchasingStages->firstWhere('key', 'receiving')?->is_inventory_effect_stage)->toBeFalse()
        ->and($purchasingStages->where('is_active', true)->where('is_inventory_effect_stage', true)->count())->toBe(1);
});

it('23. workflow payload remains tenant scoped for inventory-effect stage state', function (): void {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'workflow-manage');
    ($this->seedDefaultStages)($tenantA);
    ($this->seedDefaultStages)($tenantB);

    $shippingStageB = ($this->salesStages)($tenantB)->firstWhere('key', 'shipping');
    $shippingStageB->forceFill(['is_inventory_effect_stage' => true])->save();
    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenantB->id)
        ->where('workflow_domain_id', ($this->salesDomain)()->id)
        ->where('key', 'packing')
        ->update(['is_inventory_effect_stage' => false]);

    $payload = ($this->extractPayload)(
        $this->actingAs($userA)->get(route('admin.workflows.index'))->assertOk(),
        'admin-workflows-index-payload'
    );

    $tenantAStages = collect($payload['stages'] ?? []);

    expect($tenantAStages->firstWhere('key', 'packing')['is_inventory_effect_stage'] ?? null)->toBeTrue()
        ->and($tenantAStages->firstWhere('key', 'shipping')['is_inventory_effect_stage'] ?? null)->toBeFalse();
});

it('24. stage store responses include the inventory-effect marker', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'key' => 'quality-check',
        'name' => 'Quality Check',
        'action_verb' => 'QUALITY CHECK',
        'status_complete_label' => 'OPEN',
        'description' => null,
        'sort_order' => 40,
        'is_inventory_effect_stage' => true,
    ])->assertCreated()
        ->assertJsonPath('data.is_inventory_effect_stage', true);
});

it('25. stage update responses include the inventory-effect marker', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $shippingStage = ($this->salesStages)($tenant)->firstWhere('key', 'shipping');

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $shippingStage), [
        'workflow_domain_id' => $shippingStage->workflow_domain_id,
        'key' => $shippingStage->key,
        'name' => $shippingStage->name,
        'action_verb' => $shippingStage->action_verb,
        'description' => $shippingStage->description,
        'sort_order' => $shippingStage->sort_order,
        'is_active' => true,
        'is_inventory_effect_stage' => true,
    ])->assertOk()
        ->assertJsonPath('data.is_inventory_effect_stage', true);
});

it('26. inactive sales stages remain visible with their marker field in show-inactive payloads', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $reviewStage = ($this->createStage)($tenant, ($this->salesDomain)(), [
        'key' => 'reviewing',
        'name' => 'Reviewing',
        'action_verb' => 'REVIEW',
        'sort_order' => 35,
    ]);

    $this->actingAs($user)->patchJson(route('admin.workflows.stages.update', $reviewStage), [
        'workflow_domain_id' => $reviewStage->workflow_domain_id,
        'key' => $reviewStage->key,
        'name' => $reviewStage->name,
        'action_verb' => $reviewStage->action_verb,
        'description' => $reviewStage->description,
        'sort_order' => $reviewStage->sort_order,
        'is_active' => false,
        'is_inventory_effect_stage' => false,
    ])->assertOk();

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('admin.workflows.index', ['show_inactive' => 1]))->assertOk(),
        'admin-workflows-index-payload'
    );

    $inactiveReviewStage = collect($payload['stages'] ?? [])
        ->firstWhere('key', 'reviewing');

    expect($inactiveReviewStage)->not->toBeNull()
        ->and($inactiveReviewStage)->toHaveKey('is_inventory_effect_stage')
        ->and($inactiveReviewStage['is_inventory_effect_stage'] ?? null)->toBeFalse();
});

it('27. creating a stage whose generated key duplicates an existing stage key is rejected with validation errors', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'Quality Check',
        'action_verb' => 'QUALITY CHECK',
        'status_complete_label' => 'OPEN',
        'description' => null,
        'sort_order' => 40,
        'is_inventory_effect_stage' => false,
    ])->assertCreated();

    $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'quality-check',
        'action_verb' => 'QUALITY-CHECK',
        'status_complete_label' => 'OPEN',
        'description' => null,
        'sort_order' => 50,
        'is_inventory_effect_stage' => false,
    ])->assertStatus(422)->assertJsonValidationErrors(['key']);
});

it('28. unique generated stage keys still create successfully', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'workflow-manage');
    ($this->seedDefaultStages)($tenant);

    $response = $this->actingAs($user)->postJson(route('admin.workflows.stages.store'), [
        'workflow_domain_id' => ($this->salesDomain)()->id,
        'name' => 'Quality Review',
        'action_verb' => 'QUALITY REVIEW',
        'status_complete_label' => 'OPEN',
        'description' => null,
        'sort_order' => 40,
        'is_inventory_effect_stage' => false,
    ])->assertCreated();

    expect($response->json('data.key'))->toBe('quality-review');
});
