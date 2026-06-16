<?php

namespace App\Services\Workflows;

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Contracts\Workflows\Workflowable;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use App\Support\Workflows\WorkflowDefinition;
use App\Support\Workflows\WorkflowDefinitionRepository;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Purchase Order workflow runtime behavior.
 */
class PurchaseOrderWorkflow extends BaseWorkflow
{
    private const ACTION_COMPLETE_STAGE = 'complete_stage';
    private const ACTION_CANCEL = 'cancel';
    private const AUTOMATIC_COMPLETION_MODE = 'automatic';

    private ?User $actingUser = null;

    public function __construct(
        WorkflowDefinitionRepository $definitions,
        private readonly AssertWorkflowStageTasksCompletedAction $assertTasksCompleted,
        private readonly GenerateWorkflowStageTasksAction $generateTasks
    ) {
        parent::__construct($definitions);
    }

    /**
     * Ensure a purchase order has entered the first purchasing workflow stage.
     */
    public function initialize(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->current_workflow_stage_id !== null) {
            return $purchaseOrder;
        }

        $definition = $this->definition($purchaseOrder);
        $firstStage = $definition->firstStage();

        if (! $firstStage) {
            return $purchaseOrder;
        }

        $purchaseOrder->forceFill([
            'current_workflow_stage_id' => $firstStage->id,
        ])->save();

        $this->generateTasks->execute(
            (int) $purchaseOrder->tenant_id,
            (int) $purchaseOrder->id,
            $firstStage
        );

        return $this->freshPurchaseOrder($purchaseOrder);
    }

    /**
     * Complete the current purchase order workflow stage.
     *
     * @return array<string, mixed>
     */
    public function completeStage(PurchaseOrder $purchaseOrder, User $user): array
    {
        $this->actingUser = $user;

        try {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = $this->transition($purchaseOrder, self::ACTION_COMPLETE_STAGE);

            while (
                $purchaseOrder->currentWorkflowStage
                && $purchaseOrder->currentWorkflowStage->completion_mode === self::AUTOMATIC_COMPLETION_MODE
                && ! $purchaseOrder->currentWorkflowStage->is_inventory_effect_stage
            ) {
                /** @var PurchaseOrder $purchaseOrder */
                $purchaseOrder = $this->transition($purchaseOrder, self::ACTION_COMPLETE_STAGE);
            }

            return $this->responsePayload($purchaseOrder, $user);
        } finally {
            $this->actingUser = null;
        }
    }

    /**
     * Cancel the purchase order workflow.
     *
     * @return array<string, mixed>
     */
    public function cancel(PurchaseOrder $purchaseOrder, User $user): array
    {
        $this->actingUser = $user;

        try {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = $this->transition($purchaseOrder, self::ACTION_CANCEL);

            return $this->responsePayload($purchaseOrder, $user);
        } finally {
            $this->actingUser = null;
        }
    }

    /**
     * Build the workflow action-button JSON for a purchase order.
     *
     * @return array<string, mixed>
     */
    public function responsePayload(PurchaseOrder $purchaseOrder, ?User $user): array
    {
        if (
            $purchaseOrder->current_workflow_stage_id === null
            && $purchaseOrder->last_completed_workflow_stage_id === null
            && ! $purchaseOrder->isTerminal()
        ) {
            $purchaseOrder = $this->initialize($purchaseOrder);
        }

        $purchaseOrder->loadMissing([
            'currentWorkflowStage',
            'lastCompletedWorkflowStage',
        ]);

        $currentStage = $purchaseOrder->currentWorkflowStage;
        $actions = [];

        if (
            $user
            && app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, 'purchasing')
            && ! $purchaseOrder->isTerminal()
            && $purchaseOrder->workflow_cancelled_at === null
        ) {
            if ($currentStage) {
                if ($currentStage->is_inventory_effect_stage) {
                    $actions[] = [
                        'type' => 'receive',
                        'label' => 'Receive',
                        'description' => 'Record one receipt with one or more received lines.',
                        'endpoint' => route('purchasing.orders.receipts.store', $purchaseOrder),
                        'method' => 'POST',
                    ];
                } else {
                    $actions[] = [
                        'type' => self::ACTION_COMPLETE_STAGE,
                        'label' => $this->naturalCase((string) $currentStage->action_verb),
                        'description' => $this->stageDescription($currentStage),
                        'endpoint' => route('purchasing.orders.workflow.complete', $purchaseOrder),
                        'method' => 'POST',
                    ];
                }
            }

            if ($currentStage?->is_inventory_effect_stage) {
                $actions[] = [
                    'type' => PurchaseOrder::ACTION_BACK_ORDER,
                    'label' => 'Back Order',
                    'description' => 'Mark remaining items as back ordered.',
                    'endpoint' => route('purchasing.orders.status.update', $purchaseOrder),
                    'method' => 'PATCH',
                ];

                $actions[] = [
                    'type' => 'short_close',
                    'label' => 'Short Close',
                    'description' => 'Close remaining unreceived quantities.',
                    'endpoint' => route('purchasing.orders.short-closures.store', $purchaseOrder),
                    'method' => 'POST',
                ];
            }

            if (! $purchaseOrder->receipts()->exists()) {
                $actions[] = [
                    'type' => self::ACTION_CANCEL,
                    'label' => 'Cancel',
                    'description' => 'Cancel this purchase order.',
                    'endpoint' => route('purchasing.orders.workflow.cancel', $purchaseOrder),
                    'method' => 'POST',
                    'requiresConfirmation' => true,
                ];
            }
        }

        return [
            'status' => $purchaseOrder->workflowStatus(),
            'currentStage' => $currentStage ? [
                'id' => $currentStage->id,
                'workflow_domain_id' => $currentStage->workflow_domain_id,
                'name' => $currentStage->name,
                'actionVerb' => $this->naturalCase((string) $currentStage->action_verb),
            ] : null,
            'currentStageTasks' => $this->currentStageTasks($purchaseOrder, $currentStage, $user),
            'actions' => $actions,
        ];
    }

    /**
     * Build workflow progress steps without running the legacy progress action.
     *
     * @return array<int, array{label: string, status: string, url: null, current: bool}>
     */
    public function progressSteps(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['currentWorkflowStage']);

        return $this->definition($purchaseOrder)->progressSteps(
            $purchaseOrder->currentWorkflowStage,
            $purchaseOrder->current_workflow_stage_id === null
                && $purchaseOrder->last_completed_workflow_stage_id !== null
                && $purchaseOrder->workflowStatus() === PurchaseOrder::STATUS_COMPLETED
        );
    }

    /**
     * Build workflow mirror fields for legacy status endpoint transitions.
     *
     * @return array<string, int|null>
     */
    public function fieldsForStatus(PurchaseOrder $purchaseOrder, string $targetStatus): array
    {
        $definition = $this->definition($purchaseOrder);
        $creatingStage = $definition->stageByKey('creating') ?? $definition->firstStage();
        $inventoryEffectStage = $definition->activeStages()->firstWhere('is_inventory_effect_stage', true)
            ?? $definition->stageByKey('receiving')
            ?? $definition->activeStages()->get(1);
        $completingStage = $definition->stageByKey('completing') ?? $definition->activeStages()->last();

        return match ($targetStatus) {
            PurchaseOrder::STATUS_CREATED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [
                'last_completed_workflow_stage_id' => $creatingStage?->id,
                'current_workflow_stage_id' => $inventoryEffectStage?->id,
            ],
            PurchaseOrder::STATUS_RECEIVED => [
                'last_completed_workflow_stage_id' => $inventoryEffectStage?->id,
                'current_workflow_stage_id' => $completingStage?->id,
            ],
            PurchaseOrder::STATUS_COMPLETED => [
                'last_completed_workflow_stage_id' => $completingStage?->id,
                'current_workflow_stage_id' => null,
            ],
            default => [],
        };
    }

    /**
     * Authorize a transition.
     */
    protected function authorize(Workflowable $record, string $target): void
    {
        abort_unless(
            $this->actingUser
                && app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($this->actingUser, 'purchasing'),
            403
        );
    }

    /**
     * Lock and return the Purchase Order.
     */
    protected function lockRecord(Workflowable $record, WorkflowDefinition $definition): Workflowable
    {
        $query = PurchaseOrder::query()
            ->lockForUpdate()
            ->whereKey($record->workflowRecordId());

        if ($this->actingUser) {
            $query->where('tenant_id', $this->actingUser->tenant_id);
        }

        $purchaseOrder = $query->firstOrFail();

        if (
            $purchaseOrder->current_workflow_stage_id === null
            && $purchaseOrder->last_completed_workflow_stage_id === null
            && ! $purchaseOrder->isTerminal()
            && $definition->firstStage()
        ) {
            $firstStage = $definition->firstStage();

            $purchaseOrder->forceFill([
                'current_workflow_stage_id' => $firstStage?->id,
            ])->save();

            if ($firstStage) {
                $this->generateTasks->execute(
                    (int) $purchaseOrder->tenant_id,
                    (int) $purchaseOrder->id,
                    $firstStage
                );
            }
        }

        return $purchaseOrder->load([
            'currentWorkflowStage',
            'lastCompletedWorkflowStage',
        ]);
    }

    /**
     * Validate target movement.
     */
    protected function assertTransitionAllowed(
        Workflowable $record,
        WorkflowDefinition $definition,
        string $target
    ): void {
        if (! $record instanceof PurchaseOrder) {
            throw new DomainException('Purchase order workflow record is invalid.');
        }

        if ($target === self::ACTION_CANCEL) {
            if ($record->isTerminal() || $record->receipts()->exists()) {
                throw new DomainException('Purchase order workflow cannot be cancelled.');
            }

            return;
        }

        if ($target !== self::ACTION_COMPLETE_STAGE) {
            throw new DomainException('Purchase order workflow action is invalid.');
        }

        if ($record->workflow_cancelled_at !== null || $record->status === PurchaseOrder::STATUS_CANCELLED) {
            throw new DomainException('Purchase order workflow is cancelled.');
        }

        if (
            $record->current_workflow_stage_id === null
            && $record->last_completed_workflow_stage_id === null
            && ! $record->isTerminal()
        ) {
            return;
        }

        $record->loadMissing('currentWorkflowStage');

        if (! $record->currentWorkflowStage) {
            throw new DomainException('Purchase order has no current workflow stage.');
        }

        if ($record->currentWorkflowStage->is_inventory_effect_stage) {
            throw new DomainException('Receive inventory before advancing this workflow stage.');
        }
    }

    /**
     * Check generated task gating for the current stage.
     */
    protected function assertCurrentStageTasksComplete(
        Workflowable $record,
        WorkflowDefinition $definition,
        string $target
    ): void {
        if (! $record instanceof PurchaseOrder || $target !== self::ACTION_COMPLETE_STAGE) {
            return;
        }

        $record->loadMissing('currentWorkflowStage');
        $currentStage = $record->currentWorkflowStage;

        if (! $currentStage) {
            return;
        }

        $this->assertTasksCompleted->execute(
            (int) $record->tenant_id,
            (int) $record->id,
            $currentStage,
            'Current workflow stage has incomplete required tasks.'
        );
    }

    /**
     * Persist purchase order workflow movement.
     */
    protected function persistMovement(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        if (! $record instanceof PurchaseOrder) {
            return;
        }

        if ($target === self::ACTION_CANCEL) {
            $record->forceFill([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'workflow_cancelled_at' => Carbon::now(),
                'cancelled_at' => Carbon::now(),
                'cancelled_by_user_id' => $this->actingUser?->id,
            ])->save();

            return;
        }

        $record->loadMissing('currentWorkflowStage');
        $currentStage = $record->currentWorkflowStage;

        if (! $currentStage) {
            return;
        }

        $nextStage = $definition->nextStageAfter($currentStage);

        $record->forceFill([
            'status' => $this->mirroredStatus($record, $currentStage),
            'last_completed_workflow_stage_id' => $currentStage->id,
            'current_workflow_stage_id' => $nextStage?->id,
        ])->save();

        $record->setRelation('lastCompletedWorkflowStage', $currentStage);
        $record->setRelation('currentWorkflowStage', $nextStage);
    }

    /**
     * Generate tasks for the newly entered stage.
     */
    protected function afterTransition(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        if (! $record instanceof PurchaseOrder || $target !== self::ACTION_COMPLETE_STAGE) {
            return;
        }

        $nextStage = $record->currentWorkflowStage;

        if ($nextStage) {
            $this->generateTasks->execute(
                (int) $record->tenant_id,
                (int) $record->id,
                $nextStage
            );
        }
    }

    /**
     * Reload the purchase order with workflow relations.
     */
    protected function freshRecord(Workflowable $record): Workflowable
    {
        if ($record instanceof PurchaseOrder) {
            return $this->freshPurchaseOrder($record);
        }

        return parent::freshRecord($record);
    }

    /**
     * Return the cached workflow definition for a Purchase Order.
     */
    private function definition(PurchaseOrder $purchaseOrder): WorkflowDefinition
    {
        return $this->definitions->for((int) $purchaseOrder->tenant_id, 'purchasing');
    }

    /**
     * Reload a purchase order with workflow relations.
     */
    private function freshPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->fresh([
            'currentWorkflowStage',
            'lastCompletedWorkflowStage',
        ]) ?? $purchaseOrder;
    }

    /**
     * Mirror workflow progress into the legacy status column during migration.
     */
    private function mirroredStatus(PurchaseOrder $purchaseOrder, WorkflowStage $completedStage): string
    {
        return match (true) {
            $completedStage->key === 'creating' => PurchaseOrder::STATUS_CREATED,
            $completedStage->is_inventory_effect_stage => PurchaseOrder::STATUS_RECEIVED,
            $completedStage->key === 'completing' => PurchaseOrder::STATUS_COMPLETED,
            default => $purchaseOrder->status,
        };
    }

    /**
     * Return purchase-order specific dropdown helper copy for the current stage action.
     */
    private function stageDescription(WorkflowStage $stage): string
    {
        if (filled($stage->description)) {
            return (string) $stage->description;
        }

        return match (true) {
            $stage->key === 'creating' => 'Create this purchase order.',
            $stage->is_inventory_effect_stage => 'Record received inventory for this purchase order.',
            $stage->key === 'completing' => 'Mark this purchase order as complete.',
            default => 'Complete the current workflow stage.',
        };
    }

    /**
     * Build current-stage task payloads for the purchase order detail workflow panel.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function currentStageTasks(PurchaseOrder $purchaseOrder, ?WorkflowStage $currentStage, ?User $viewer): array
    {
        if ($currentStage === null) {
            return [];
        }

        return Task::query()
            ->with(['assignedTo', 'completedBy'])
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('workflow_domain_id', $currentStage->workflow_domain_id)
            ->where('domain_record_id', $purchaseOrder->id)
            ->where(function ($query) use ($currentStage): void {
                $query->where('source', Task::SOURCE_MANUAL)
                    ->orWhere(function ($query) use ($currentStage): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $currentStage->id);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskData($task, $viewer))
            ->values()
            ->all();
    }

    /**
     * Build workflow task payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function taskData(Task $task, ?User $viewer): array
    {
        $canComplete = ! $task->isCompleted()
            && $viewer !== null
            && (int) $task->assigned_to_user_id === (int) $viewer->id
            && app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain(
                $viewer,
                'purchasing'
            );

        return [
            'id' => $task->id,
            'source' => $task->source,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'is_completed' => $task->isCompleted(),
            'can_complete' => $canComplete,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'complete_url' => route('tasks.complete', $task),
        ];
    }

    /**
     * Format canonical workflow verbs for UI presentation.
     */
    private function naturalCase(string $value): string
    {
        return ucwords(strtolower($value));
    }
}
