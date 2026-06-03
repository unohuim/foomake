<?php

declare(strict_types=1);

use App\Actions\Workflows\BuildWorkflowProgressStepsAction;
use App\Models\Tenant;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenantCounter = 1;

    $this->renderWorkflowProgress = function (array $steps): string {
        return Blade::render(
            <<<'BLADE'
<x-ui.workflow-progress :steps="$steps" />
BLADE,
            ['steps' => $steps]
        );
    };

    $this->makeTenant = function (): Tenant {
        return Tenant::query()->create([
            'tenant_name' => 'Workflow Progress Tenant ' . $this->tenantCounter++,
        ]);
    };

    $this->makeDomain = function (string $key): WorkflowDomain {
        return WorkflowDomain::query()->firstOrCreate(
            ['key' => $key],
            ['name' => ucfirst($key), 'sort_order' => 10]
        );
    };

    $this->makeStage = function (
        Tenant $tenant,
        string $domainKey,
        string $key,
        string $name,
        int $sortOrder,
        array $attributes = []
    ): WorkflowStage {
        $domain = ($this->makeDomain)($domainKey);

        return WorkflowStage::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => $key,
            'name' => $name,
            'action_verb' => strtoupper($name),
            'status_complete_label' => strtoupper($name),
            'completion_mode' => 'manual',
            'sort_order' => $sortOrder,
            'is_active' => true,
            'is_core' => false,
            'is_inventory_effect_stage' => $sortOrder === 20,
        ], $attributes));
    };

    $this->makeStages = function (Tenant $tenant, string $domainKey): array {
        return [
            'prep' => ($this->makeStage)($tenant, $domainKey, 'prep', 'Prep', 10),
            'counting' => ($this->makeStage)($tenant, $domainKey, 'counting', 'Counting', 20),
            'reviewing' => ($this->makeStage)($tenant, $domainKey, 'reviewing', 'Reviewing', 30),
        ];
    };

    $this->buildSteps = function (
        Tenant $tenant,
        string $domainKey,
        ?int $currentStageId = null,
        ?string $currentStageKey = null
    ): array {
        return app(BuildWorkflowProgressStepsAction::class)->execute(
            (int) $tenant->id,
            $domainKey,
            $currentStageId,
            $currentStageKey
        );
    };

    $this->workflowProgressSource = fn (): string => File::get(resource_path('views/components/ui/workflow-progress.blade.php'));
    $this->stepBuilderSource = fn (): string => File::get(app_path('Actions/Workflows/BuildWorkflowProgressStepsAction.php'));
    $this->inventoryCountShowSource = fn (): string => File::get(resource_path('views/inventory/counts/show.blade.php'));
    $this->makeOrderShowSource = fn (): string => File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $this->purchaseOrderShowSource = fn (): string => File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $this->salesOrderShowSource = fn (): string => File::get(resource_path('views/sales/orders/show.blade.php'));
    $this->inventoryCountControllerSource = fn (): string => File::get(app_path('Http/Controllers/InventoryCountController.php'));
    $this->makeOrderControllerSource = fn (): string => File::get(app_path('Http/Controllers/MakeOrderController.php'));
    $this->purchaseOrderControllerSource = fn (): string => File::get(app_path('Http/Controllers/PurchaseOrderController.php'));
    $this->salesOrderControllerSource = fn (): string => File::get(app_path('Http/Controllers/SalesOrderController.php'));
});

it('1. workflow progress component renders a progress nav', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'current'],
    ]);

    expect($html)->toContain('<nav')
        ->and($html)->toContain('aria-label="Progress"');
});

it('2. workflow progress component renders DRAFT as the first step', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->makeStages)($tenant, 'inventory');

    $steps = ($this->buildSteps)($tenant, 'inventory');

    expect($steps[0]['label'])->toBe('DRAFT')
        ->and($steps[0]['status'])->toBe('current');
});

it('3. workflow progress steps render configured stage names after DRAFT', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->makeStages)($tenant, 'inventory');

    $steps = ($this->buildSteps)($tenant, 'inventory');
    $labels = array_column($steps, 'label');

    expect($labels[0])->toBe('DRAFT')
        ->and($labels)->toContain('Prep')
        ->and($labels)->toContain('Counting')
        ->and($labels)->toContain('Reviewing')
        ->and(array_search('Prep', $labels, true))->toBeLessThan(array_search('Counting', $labels, true))
        ->and(array_search('Counting', $labels, true))->toBeLessThan(array_search('Reviewing', $labels, true));
});

it('4. DRAFT is current when there is no current workflow stage', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->makeStages)($tenant, 'manufacturing');

    $steps = ($this->buildSteps)($tenant, 'manufacturing', null);

    expect($steps[0]['status'])->toBe('current')
        ->and($steps[1]['status'])->toBe('upcoming');
});

it('5. DRAFT is completed when a current workflow stage exists', function (): void {
    $tenant = ($this->makeTenant)();
    $stages = ($this->makeStages)($tenant, 'manufacturing');

    $steps = ($this->buildSteps)($tenant, 'manufacturing', $stages['counting']->id);

    expect($steps[0]['status'])->toBe('completed');
});

it('6. current workflow stage renders aria-current step', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Counting', 'status' => 'current'],
    ]);

    expect($html)->toContain('aria-current="step"')
        ->and($html)->toContain('Counting');
});

it('7. stages before current render as completed', function (): void {
    $tenant = ($this->makeTenant)();
    $stages = ($this->makeStages)($tenant, 'inventory');

    $steps = ($this->buildSteps)($tenant, 'inventory', $stages['reviewing']->id);
    $stepsByLabel = collect($steps)->keyBy('label');

    expect($stepsByLabel->get('Prep')['status'])->toBe('completed')
        ->and($stepsByLabel->get('Counting')['status'])->toBe('completed')
        ->and($stepsByLabel->get('Reviewing')['status'])->toBe('current');
});

it('8. stages after current render as upcoming', function (): void {
    $tenant = ($this->makeTenant)();
    $stages = ($this->makeStages)($tenant, 'inventory');

    $steps = ($this->buildSteps)($tenant, 'inventory', $stages['prep']->id);
    $stepsByLabel = collect($steps)->keyBy('label');

    expect($stepsByLabel->get('Prep')['status'])->toBe('current')
        ->and($stepsByLabel->get('Counting')['status'])->toBe('upcoming')
        ->and($stepsByLabel->get('Reviewing')['status'])->toBe('upcoming');
});

it('9. completed steps render check icon styling', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Counting', 'status' => 'current'],
    ]);

    expect($html)->toContain('bg-indigo-600')
        ->and($html)->toContain('M19.916 4.626');
});

it('10. current step renders indigo bordered number styling', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Counting', 'status' => 'current'],
    ]);

    expect($html)->toContain('border-2 border-indigo-600')
        ->and($html)->toContain('text-indigo-600');
});

it('11. upcoming step renders gray bordered number styling', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'current'],
        ['label' => 'Reviewing', 'status' => 'upcoming'],
    ]);

    expect($html)->toContain('border-2 border-gray-300')
        ->and($html)->toContain('text-gray-500');
});

it('12. panel container renders rounded border styling', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'current'],
    ]);

    expect($html)->toContain('divide-y divide-gray-300 rounded-md border border-gray-300');
});

it('13. panel chevron separators render on the desktop structure', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Counting', 'status' => 'current'],
        ['label' => 'Reviewing', 'status' => 'upcoming'],
    ]);

    expect($html)->toContain('absolute right-0 top-0 hidden h-full w-5 md:block')
        ->and($html)->toContain('viewBox="0 0 22 80"')
        ->and($html)->toContain('M0 -2L20 40L0 82');
});

it('14. component does not introduce Tailwind Plus scripts', function (): void {
    $source = ($this->workflowProgressSource)();

    expect($source)->not->toContain('@tailwindplus/elements')
        ->and($source)->not->toContain('cdn.jsdelivr.net');
});

it('15. component does not introduce Vue', function (): void {
    $source = ($this->workflowProgressSource)();

    expect($source)->not->toContain('v-for')
        ->and($source)->not->toContain('@vue')
        ->and($source)->not->toContain('Heroicons Vue');
});

it('16. component does not introduce global JavaScript state', function (): void {
    $source = ($this->workflowProgressSource)();

    expect($source)->not->toContain('<script')
        ->and($source)->not->toContain('window.');
});

it('17. Inventory Count detail renders workflow progress at the top of content', function (): void {
    $source = ($this->inventoryCountShowSource)();

    expect($source)->toContain('<x-ui.workflow-progress')
        ->and($source)->toContain('$payload[\'workflowProgressSteps\']')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('18. Make Order detail renders workflow progress at the top of content', function (): void {
    $source = ($this->makeOrderShowSource)();

    expect($source)->toContain('<x-ui.workflow-progress')
        ->and($source)->toContain('$payload[\'workflowProgressSteps\']')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('19. Purchase Order detail renders workflow progress at the top of content', function (): void {
    $source = ($this->purchaseOrderShowSource)();

    expect($source)->toContain('<x-ui.workflow-progress')
        ->and($source)->toContain('$payload[\'workflowProgressSteps\']')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('20. Sales Order detail renders workflow progress at the top of content', function (): void {
    $source = ($this->salesOrderShowSource)();

    expect($source)->toContain('<x-ui.workflow-progress')
        ->and($source)->toContain('$payload[\'workflowProgressSteps\']')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('21. workflow progress does not expose transition actions', function (): void {
    $source = ($this->workflowProgressSource)();

    expect($source)->not->toContain('method="POST"')
        ->and($source)->not->toContain('x-on:click')
        ->and($source)->not->toContain('wire:click')
        ->and($source)->not->toContain('submit');
});

it('22. detail controllers build workflow progress after normal page authorization', function (): void {
    expect(($this->inventoryCountControllerSource)())->toContain('authorizeInventoryCountView')
        ->and(($this->inventoryCountControllerSource)())->toContain('BuildWorkflowProgressStepsAction')
        ->and(($this->makeOrderControllerSource)())->toContain('abort_unless')
        ->and(($this->makeOrderControllerSource)())->toContain('BuildWorkflowProgressStepsAction')
        ->and(($this->purchaseOrderControllerSource)())->toContain('abort_unless')
        ->and(($this->purchaseOrderControllerSource)())->toContain('BuildWorkflowProgressStepsAction')
        ->and(($this->salesOrderControllerSource)())->toContain('abort_unless')
        ->and(($this->salesOrderControllerSource)())->toContain('BuildWorkflowProgressStepsAction');
});

it('23. workflow progress stage query is tenant scoped and active only', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    $stages = ($this->makeStages)($tenant, 'inventory');
    ($this->makeStage)($otherTenant, 'inventory', 'foreign', 'Foreign Stage', 5);
    ($this->makeStage)($tenant, 'inventory', 'inactive', 'Inactive Stage', 40, ['is_active' => false]);

    $steps = ($this->buildSteps)($tenant, 'inventory', $stages['counting']->id);
    $labels = array_column($steps, 'label');

    expect($labels)->toContain('DRAFT')
        ->and($labels)->toContain('Prep')
        ->and($labels)->toContain('Counting')
        ->and($labels)->not->toContain('Foreign Stage')
        ->and($labels)->not->toContain('Inactive Stage');
});

it('24. stage key fallback supports sales order workflow statuses without exposing another tenant', function (): void {
    $tenant = ($this->makeTenant)();
    $otherTenant = ($this->makeTenant)();
    ($this->makeStages)($tenant, 'sales');
    ($this->makeStage)($otherTenant, 'sales', 'counting', 'Other Counting', 20);

    $steps = ($this->buildSteps)($tenant, 'sales', null, 'COUNTING');
    $labels = array_column($steps, 'label');
    $stepsByLabel = collect($steps)->keyBy('label');

    expect($labels[0])->toBe('DRAFT')
        ->and($labels)->toContain('Prep')
        ->and($labels)->toContain('Counting')
        ->and($labels)->toContain('Reviewing')
        ->and($labels)->not->toContain('Other Counting')
        ->and($steps[0]['status'])->toBe('completed')
        ->and($stepsByLabel->get('Counting')['status'])->toBe('current');
});
