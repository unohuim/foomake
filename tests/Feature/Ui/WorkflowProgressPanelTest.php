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

    $this->renderWorkflowProgress = function (array $steps, bool $doDraft = true): string {
        return Blade::render(
            <<<'BLADE'
<x-ui.workflow-progress :steps="$steps" :do_draft="$doDraft" />
BLADE,
            ['steps' => $steps, 'doDraft' => $doDraft]
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

        return WorkflowStage::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workflow_domain_id' => $domain->id,
            'key' => $key,
        ], array_merge([
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
        ?string $currentStageKey = null,
        ?int $completedStageId = null,
        bool $workflowCompleted = false
    ): array {
        return app(BuildWorkflowProgressStepsAction::class)->execute(
            (int) $tenant->id,
            $domainKey,
            $currentStageId,
            $currentStageKey,
            $completedStageId,
            $workflowCompleted
        );
    };

    $this->workflowProgressSource = fn (): string => File::get(resource_path('views/components/ui/workflow-progress.blade.php'));
    $this->stepBuilderSource = fn (): string => File::get(app_path('Actions/Workflows/BuildWorkflowProgressStepsAction.php'));
    $this->inventoryCountShowSource = fn (): string => File::get(resource_path('views/inventory/counts/show.blade.php'));
    $this->makeOrderShowSource = fn (): string => File::get(resource_path('views/manufacturing/make-orders/show.blade.php'));
    $this->purchaseOrderShowSource = fn (): string => File::get(resource_path('views/purchasing/orders/show.blade.php'));
    $this->purchaseOrderShowPageSource = fn (): string => File::get(resource_path('js/pages/purchasing-orders-show.js'));
    $this->purchaseOrderStatusControllerSource = fn (): string => File::get(app_path('Http/Controllers/PurchaseOrderStatusController.php'));
    $this->purchaseOrderWorkflowControllerSource = fn (): string => File::get(app_path('Http/Controllers/PurchaseOrderWorkflowController.php'));
    $this->workflowActionButtonSource = fn (): string => File::get(resource_path('js/components/workflow-action-button.js'));
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

it('1a. simple dropdown component renders a menu with alpine transitions', function (): void {
    $html = Blade::render(
        <<<'BLADE'
<x-ui.dropdown align="left" width="w-56">
    <x-slot name="trigger">Current UoM</x-slot>
    <button type="button" role="menuitem">Grams</button>
</x-ui.dropdown>
BLADE
    );

    expect($html)->toContain('Current UoM')
        ->and($html)->toContain('role="menu"')
        ->and($html)->toContain('role="menuitem"')
        ->and($html)->toContain('x-transition:enter')
        ->and($html)->toContain('x-on:click.outside')
        ->and($html)->not->toContain('@tailwindplus/elements');
});

it('2. workflow progress component renders DRAFT as the first step', function (): void {
    $tenant = ($this->makeTenant)();
    ($this->makeStages)($tenant, 'inventory');

    $steps = ($this->buildSteps)($tenant, 'inventory');

    expect($steps[0]['label'])->toBe('DRAFT')
        ->and($steps[0]['status'])->toBe('current');
});

it('2a. workflow progress component hides DRAFT by default unless do_draft is true', function (): void {
    $steps = [
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Prep', 'status' => 'current'],
    ];

    $defaultHtml = Blade::render(
        <<<'BLADE'
<x-ui.workflow-progress :steps="$steps" />
BLADE,
        ['steps' => $steps]
    );
    $draftHtml = ($this->renderWorkflowProgress)($steps, true);

    expect($defaultHtml)->not->toContain('DRAFT')
        ->and($defaultHtml)->toContain('Prep')
        ->and($defaultHtml)->toContain('>01<')
        ->and($draftHtml)->toContain('DRAFT')
        ->and($draftHtml)->toContain('Prep');
});

it('2b. workflow progress component marks the first visible stage current when DRAFT is hidden and current', function (): void {
    $html = Blade::render(
        <<<'BLADE'
<x-ui.workflow-progress :steps="$steps" />
BLADE,
        [
            'steps' => [
                ['label' => 'DRAFT', 'status' => 'current', 'current' => true],
                ['label' => 'Prep', 'status' => 'upcoming', 'current' => false],
                ['label' => 'Counting', 'status' => 'upcoming', 'current' => false],
            ],
        ]
    );

    expect($html)->not->toContain('DRAFT')
        ->and($html)->toContain('Prep')
        ->and($html)->toContain('aria-current="step"')
        ->and($html)->toContain('border-2 border-indigo-600');
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

it('8a. completed workflows render every step through the completed stage as completed with no current step', function (): void {
    $tenant = ($this->makeTenant)();
    $stages = ($this->makeStages)($tenant, 'inventory');

    $steps = ($this->buildSteps)($tenant, 'inventory', null, null, $stages['reviewing']->id, true);
    $stepsByLabel = collect($steps)->keyBy('label');

    expect($stepsByLabel->get('DRAFT')['status'])->toBe('completed')
        ->and($stepsByLabel->get('Prep')['status'])->toBe('completed')
        ->and($stepsByLabel->get('Counting')['status'])->toBe('completed')
        ->and($stepsByLabel->get('Reviewing')['status'])->toBe('completed')
        ->and(collect($steps)->where('current', true)->count())->toBe(0);

    $html = ($this->renderWorkflowProgress)($steps);

    expect($html)->not->toContain('aria-current="step"');
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

it('13a. mobile workflow progress renders horizontal selectable stage numbers', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Counting', 'status' => 'current'],
        ['label' => 'Reviewing', 'status' => 'upcoming'],
    ]);

    expect($html)->toContain('data-workflow-progress-mobile-tabs')
        ->and($html)->toContain('role="tablist"')
        ->and($html)->toContain('role="tab"')
        ->and($html)->toContain('x-data="{ activeWorkflowStep:')
        ->and($html)->toContain('x-cloak')
        ->and($html)->toContain('x-on:click="activeWorkflowStep =')
        ->and($html)->toContain('md:hidden')
        ->and($html)->toContain('transition-[flex-basis,flex-grow] duration-300 ease-out will-change-[flex-basis]')
        ->and($html)->toContain("activeWorkflowStep === 1 ? 'basis-0 grow' : 'basis-16 grow-0'")
        ->and($html)->toContain('transition-[max-width,opacity,transform] duration-300 ease-out')
        ->and($html)->toContain("activeWorkflowStep === 1 ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0'")
        ->and($html)->not->toContain('x-show="activeWorkflowStep ===')
        ->and($html)->toContain('pointer-events-none absolute right-0 top-0 h-full w-5 md:hidden')
        ->and($html)->toContain('M0 -2L20 40L0 82')
        ->and($html)->not->toContain('border-r border-gray-200')
        ->and($html)->toContain('hidden divide-y divide-gray-300 rounded-md border border-gray-300 bg-white md:flex')
        ->and($html)->toContain('border-indigo-600 bg-indigo-600 text-white')
        ->and($html)->toContain('class="size-5 text-white"')
        ->and($html)->toContain('M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5')
        ->and($html)->toContain('border-indigo-600 bg-white text-indigo-600')
        ->and($html)->toContain('border-gray-300 bg-white text-gray-500');
});

it('13b. workflow detail pages remove mobile top gap while keeping progress bottom spacing', function (): void {
    $html = ($this->renderWorkflowProgress)([
        ['label' => 'DRAFT', 'status' => 'completed'],
        ['label' => 'Counting', 'status' => 'current'],
    ]);

    expect($html)->toContain('<nav')
        ->and($html)->toContain('class="w-full"')
        ->and($html)->not->toContain('-mt-6')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('<nav class="w-full"')
        ->and(($this->purchaseOrderShowPageSource)())->not->toContain('-mt-6')
        ->and(($this->inventoryCountShowSource)())->toContain('class="pt-0 pb-12 sm:pt-6"')
        ->and(($this->inventoryCountShowSource)())->toContain('class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8"')
        ->and(($this->makeOrderShowSource)())->toContain('space-y-6 px-1 pt-0 pb-8 sm:px-6 sm:pt-6')
        ->and(($this->salesOrderShowSource)())->toContain('class="pt-0 pb-12 sm:pt-6"')
        ->and(($this->purchaseOrderShowSource)())->toContain('class="pt-0 pb-8 sm:pt-6 sm:pb-12"')
        ->and(($this->purchaseOrderShowSource)())->toContain('max-w-7xl space-y-6 px-1 sm:px-6')
        ->and(File::get(resource_path('views/layouts/app.blade.php')))->toContain('max-w-7xl mx-auto pb-0 px-4 sm:pb-6 sm:px-6 lg:px-8')
        ->and(File::get(resource_path('views/components/resource-detail-header-breadcrumb.blade.php')))->toContain('flex items-center justify-between gap-3 sm:items-start')
        ->and(File::get(resource_path('views/components/resource-detail-header-breadcrumb.blade.php')))->toContain('flex shrink-0 items-center justify-end sm:items-start');
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

    expect($source)->toContain('data-workflow-progress-panel')
        ->and($source)->toContain('x-html="workflowProgressHtml()"')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('18. Make Order detail renders workflow progress at the top of content', function (): void {
    $source = ($this->makeOrderShowSource)();

    expect($source)->toContain('data-workflow-progress-panel')
        ->and($source)->toContain('x-html="workflowProgressHtml()"')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('19. Purchase Order detail renders workflow progress at the top of content', function (): void {
    $source = ($this->purchaseOrderShowSource)();

    expect($source)->toContain('x-html="workflowProgressHtml()"')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('19a. Purchase Order detail refreshes workflow progress from ajax status responses', function (): void {
    expect(($this->purchaseOrderShowPageSource)())->toContain('workflowProgressSteps: Array.isArray(safePayload.workflowProgressSteps)')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('workflowProgressHtml()')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('workflowProgressMobileStepHtml(step, index, index === steps.length - 1)')
        ->and(($this->purchaseOrderShowPageSource)())->toContain("activeWorkflowStep === \${index} ? 'basis-0 grow' : 'basis-16 grow-0'")
        ->and(($this->purchaseOrderShowPageSource)())->toContain('transition-[max-width,opacity,transform] duration-300 ease-out')
        ->and(($this->purchaseOrderShowPageSource)())->toContain("activeWorkflowStep === \${index} ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0'")
        ->and(($this->purchaseOrderShowPageSource)())->not->toContain('x-show="activeWorkflowStep ===')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('pointer-events-none absolute right-0 top-0 h-full w-5 md:hidden')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('const circleContent = isCompleted')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('class="size-5 text-white"')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('workflowProgressStepHtml(step, isLast)')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('x-cloak')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('workflowUpdatedDetail(workflow, purchaseOrder, workflowProgressSteps = null)')
        ->and(($this->purchaseOrderShowPageSource)())->toContain('this.workflowProgressSteps = workflowProgressSteps')
        ->and(($this->purchaseOrderShowPageSource)())->not->toContain('workflowProgressSteps: workflowProgressSteps || []')
        ->and(($this->purchaseOrderShowPageSource)())->not->toContain('workflowProgressSteps: responseData.workflowProgressSteps || []')
        ->and(($this->purchaseOrderStatusControllerSource)())->toContain('workflowProgressSteps')
        ->and(($this->purchaseOrderStatusControllerSource)())->toContain('PurchaseOrderWorkflow')
        ->and(($this->purchaseOrderWorkflowControllerSource)())->toContain('workflowProgressSteps')
        ->and(($this->purchaseOrderWorkflowControllerSource)())->toContain('PurchaseOrderWorkflow')
        ->and(($this->workflowActionButtonSource)())->toContain('workflowUpdatedDetail.workflowProgressSteps = workflowProgressSteps');
});

it('20. Sales Order detail renders workflow progress at the top of content', function (): void {
    $source = ($this->salesOrderShowSource)();

    expect($source)->toContain('data-workflow-progress-panel')
        ->and($source)->toContain('x-html="workflowProgressHtml()"')
        ->and($source)->toContain('data-workflow-progress-panel');
});

it('21. workflow progress does not expose transition actions', function (): void {
    $source = ($this->workflowProgressSource)();

    expect($source)->not->toContain('method="POST"')
        ->and($source)->not->toContain('wire:click')
        ->and($source)->not->toContain('submit')
        ->and($source)->toContain('x-on:click="activeWorkflowStep =');
});

it('22. detail controllers build workflow progress after normal page authorization', function (): void {
    expect(($this->inventoryCountControllerSource)())->toContain('authorizeInventoryCountView')
        ->and(($this->inventoryCountControllerSource)())->toContain('BuildWorkflowProgressStepsAction')
        ->and(($this->makeOrderControllerSource)())->toContain('abort_unless')
        ->and(($this->makeOrderControllerSource)())->toContain('MakeOrderWorkflow')
        ->and(($this->purchaseOrderControllerSource)())->toContain('abort_unless')
        ->and(($this->purchaseOrderControllerSource)())->toContain('PurchaseOrderWorkflow')
        ->and(($this->salesOrderControllerSource)())->toContain('abort_unless')
        ->and(($this->salesOrderControllerSource)())->toContain('BuildWorkflowProgressStepsAction');
});

it('22a. detail controllers pass completed workflow state into the shared progress builder', function (): void {
    expect(($this->inventoryCountControllerSource)())->toContain('$count->posted_at !== null')
        ->and(($this->makeOrderControllerSource)())->toContain('$makeOrder->status === MakeOrder::STATUS_MADE')
        ->and(($this->purchaseOrderControllerSource)())->toContain('$purchaseOrderWorkflow->progressSteps($purchaseOrder)')
        ->and(($this->salesOrderControllerSource)())->toContain('in_array($salesOrder->status, [')
        ->and(($this->salesOrderControllerSource)())->toContain('SalesOrder::STATUS_COMPLETED')
        ->and(($this->salesOrderControllerSource)())->toContain('SalesOrder::STATUS_CANCELLED');
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
