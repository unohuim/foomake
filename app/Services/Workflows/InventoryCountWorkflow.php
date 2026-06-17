<?php

namespace App\Services\Workflows;

use App\Actions\Inventory\PostInventoryCountAction;
use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Models\InventoryCount;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use App\Support\Workflows\WorkflowDefinition;
use App\Support\Workflows\WorkflowDefinitionRepository;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Inventory Count workflow runtime behavior.
 */
class InventoryCountWorkflow
{
    private const AUTOMATIC_COMPLETION_MODE = 'automatic';

    public function __construct(
        private readonly WorkflowDefinitionRepository $definitions,
        private readonly AssertWorkflowStageTasksCompletedAction $assertTasksCompleted,
        private readonly GenerateWorkflowStageTasksAction $generateTasks,
        private readonly PostInventoryCountAction $postInventoryCount
    ) {
    }

    /**
     * Submit a draft Inventory Count into the first active inventory workflow stage.
     *
     * @throws DomainException
     */
    public function submit(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $user): InventoryCount {
            $lockedCount = $this->lockCount($inventoryCount, $user);

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            if ($lockedCount->workflow_stage_id !== null) {
                throw new DomainException('Inventory count has already been submitted.');
            }

            $firstStage = $this->firstActiveStage($lockedCount);

            if (! $firstStage) {
                throw new DomainException('Inventory workflow stages are not configured for this tenant.');
            }

            $lockedCount->tasked_by_user_id = (int) $user->id;
            $this->enterStageAndCompleteAutomaticStages($lockedCount, $firstStage, $user);

            return $this->freshCount($lockedCount);
        });
    }

    /**
     * Advance an Inventory Count, completing the current stage and stopping at the next manual stage.
     *
     * @throws DomainException
     */
    public function advance(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $user): InventoryCount {
            $lockedCount = $this->lockCount($inventoryCount, $user);

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            if ($lockedCount->workflow_stage_id === null) {
                throw new DomainException('Inventory count must be submitted before it can advance.');
            }

            $currentStage = $this->currentStage($lockedCount);

            if (! $currentStage) {
                throw new DomainException('Current inventory workflow stage is invalid.');
            }

            $this->assertTasksCompleted->execute(
                (int) $lockedCount->tenant_id,
                (int) $lockedCount->id,
                $currentStage,
                'Complete all tasks for this stage before moving the inventory count forward.'
            );

            if ((string) $currentStage->key === 'counting') {
                $this->assertCountedQuantitiesPresent($lockedCount);
            }

            if ($currentStage->is_inventory_effect_stage) {
                $this->postInventoryCount->execute($lockedCount, (int) $user->id);

                return $this->freshCount($lockedCount);
            }

            $nextStage = $this->nextActiveStage($lockedCount);

            if (! $nextStage) {
                throw new DomainException('Inventory count has no next workflow stage.');
            }

            $this->enterStageAndCompleteAutomaticStages($lockedCount, $nextStage, $user);

            return $this->freshCount($lockedCount);
        });
    }

    /**
     * Move an Inventory Count back to the previous configured workflow stage without undoing stock moves.
     *
     * @throws DomainException
     */
    public function previous(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $user): InventoryCount {
            $lockedCount = $this->lockCount($inventoryCount, $user);

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            if ($lockedCount->workflow_stage_id === null) {
                throw new DomainException('Inventory count is still in draft setup.');
            }

            $previousStage = $this->previousActiveStage($lockedCount);

            if (! $previousStage) {
                throw new DomainException('Inventory count has no previous workflow stage.');
            }

            $lockedCount->workflow_stage_id = $previousStage->id;
            $lockedCount->tasked_by_user_id = (int) $user->id;
            $lockedCount->save();

            $this->generateTasks->execute(
                (int) $lockedCount->tenant_id,
                (int) $lockedCount->id,
                $previousStage,
                $lockedCount->assigned_to_user_id
            );

            return $this->freshCount($lockedCount);
        });
    }

    /**
     * Preserve direct /post compatibility by moving to the inventory-effect stage and posting.
     *
     * @throws DomainException
     */
    public function postCompatible(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $user): InventoryCount {
            $lockedCount = $this->lockCount($inventoryCount, $user);

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            $effectStage = $this->inventoryEffectStage($lockedCount);

            if (! $effectStage) {
                throw new DomainException('Inventory workflow stages are not configured for this tenant.');
            }

            $currentStage = $this->currentStage($lockedCount);

            if ($currentStage && (int) $currentStage->id !== (int) $effectStage->id) {
                $this->assertTasksCompleted->execute(
                    (int) $lockedCount->tenant_id,
                    (int) $lockedCount->id,
                    $currentStage,
                    'Complete all tasks for this stage before moving the inventory count forward.'
                );
            }

            $lockedCount->workflow_stage_id = $effectStage->id;
            $lockedCount->tasked_by_user_id ??= (int) $user->id;
            $lockedCount->save();

            $this->postInventoryCount->execute($lockedCount, (int) $user->id);

            return $this->freshCount($lockedCount);
        });
    }

    /**
     * Resolve the current active workflow stage for the count.
     */
    public function currentStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        if ($inventoryCount->workflow_stage_id === null) {
            return null;
        }

        return $this->definition($inventoryCount)
            ->activeStages()
            ->firstWhere('id', (int) $inventoryCount->workflow_stage_id);
    }

    /**
     * Return active stages in runtime order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(InventoryCount $inventoryCount): Collection
    {
        return $this->definition($inventoryCount)->activeStages();
    }

    /**
     * Resolve the first active workflow stage for a count.
     */
    public function firstActiveStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        return $this->definition($inventoryCount)->firstStage();
    }

    /**
     * Resolve the next active workflow stage after the current one.
     */
    public function nextActiveStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        return $this->definition($inventoryCount)->nextStageAfter($this->currentStage($inventoryCount));
    }

    /**
     * Resolve the previous active workflow stage before the current one.
     */
    public function previousActiveStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        return $this->definition($inventoryCount)->previousStageBefore($this->currentStage($inventoryCount));
    }

    /**
     * Resolve the active inventory-effect stage for the tenant workflow.
     */
    public function inventoryEffectStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        return $this->definition($inventoryCount)
            ->activeStages()
            ->firstWhere('is_inventory_effect_stage', true);
    }

    /**
     * Build workflow progress steps without the legacy progress action.
     *
     * @return array<int, array{label: string, status: string, url: null, current: bool}>
     */
    public function progressSteps(InventoryCount $inventoryCount): array
    {
        return $this->definition($inventoryCount)->progressSteps(
            $inventoryCount->posted_at === null ? $this->currentStage($inventoryCount) : null,
            $inventoryCount->posted_at !== null
        );
    }

    /**
     * Return the workflow-facing status label for index/detail presentation.
     */
    public function statusLabel(InventoryCount $inventoryCount): string
    {
        if ($inventoryCount->workflow_cancelled_at !== null) {
            return 'CANCELLED';
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return $inventoryCount->posted_at !== null ? 'COMPLETED' : 'Draft';
        }

        $currentStage = $this->currentStage($inventoryCount);

        if (! $currentStage) {
            return 'Unknown';
        }

        if ($inventoryCount->posted_at !== null) {
            return $currentStage->status_complete_label ?: $currentStage->name;
        }

        $previousStage = $this->previousActiveStage($inventoryCount);

        if ($previousStage) {
            return $previousStage->status_complete_label ?: $previousStage->name;
        }

        return $currentStage->status_complete_label ?: $currentStage->name ?: 'Draft';
    }

    /**
     * Build the shared workflow action-button payload for an inventory count.
     *
     * @return array<string, mixed>
     */
    public function responsePayload(
        InventoryCount $inventoryCount,
        bool $canSubmitWorkflow,
        bool $canOperateWorkflow
    ): array {
        $currentStage = $this->currentStage($inventoryCount);
        $nextStage = $this->nextWorkflowActionStage($inventoryCount);
        $actions = [];

        if ($inventoryCount->workflow_cancelled_at === null) {
            if ($inventoryCount->posted_at === null) {
                $actions[] = [
                    'id' => 'cancel',
                    'type' => 'cancel',
                    'label' => 'Cancel',
                    'description' => 'Cancel this inventory count before it is posted.',
                    'endpoint' => route('inventory.counts.destroy', $inventoryCount),
                    'method' => 'DELETE',
                    'requiresConfirmation' => true,
                ];
            }

            if ($this->canShowNextWorkflowAction($inventoryCount, $canSubmitWorkflow, $canOperateWorkflow) && $nextStage !== null) {
                $isInitialWorkflowAction = $inventoryCount->workflow_stage_id === null;

                $actions[] = [
                    'id' => 'next',
                    'type' => $inventoryCount->workflow_stage_id === null ? 'submit' : 'advance',
                    'label' => $this->actionButtonText($nextStage, $inventoryCount),
                    'description' => $this->actionDescription(
                        $nextStage,
                        $isInitialWorkflowAction
                            ? 'Schedule this inventory count.'
                            : 'Advance this inventory count to the next workflow stage.'
                    ),
                    'endpoint' => $inventoryCount->workflow_stage_id === null
                        ? route('inventory.counts.submit', $inventoryCount)
                        : route('inventory.counts.advance', $inventoryCount),
                    'method' => 'POST',
                ];
            }
        }

        $currentLabel = $this->statusLabel($inventoryCount);

        return [
            'status' => $inventoryCount->status,
            'status_label' => $currentLabel,
            'display_label' => $currentLabel,
            'currentLabel' => $currentLabel,
            'current_stage_label' => $currentLabel,
            'current_stage' => $currentStage ? [
                'id' => (int) $currentStage->id,
                'workflow_domain_id' => (int) $currentStage->workflow_domain_id,
                'key' => $currentStage->key,
                'name' => $currentStage->name,
                'action_verb' => $currentStage->action_verb,
                'status_complete_label' => $currentStage->status_complete_label,
                'description' => $currentStage->description,
            ] : null,
            'actions' => $actions,
            'header_menu' => [
                'currentLabel' => $currentLabel,
                'options' => $actions,
            ],
            'previous_url' => route('inventory.counts.previous', $inventoryCount),
            'submit_url' => route('inventory.counts.submit', $inventoryCount),
            'advance_url' => route('inventory.counts.advance', $inventoryCount),
            'post_url' => route('inventory.counts.post', $inventoryCount),
        ];
    }

    /**
     * Build current-stage task payloads for the detail page.
     *
     * @return array<int, array<string, int|string|bool|null|array<int, string>>>
     */
    public function currentStageTasks(InventoryCount $inventoryCount, ?User $viewer): array
    {
        $inventoryCount->loadMissing('workflowStage');
        $workflowDomainId = $inventoryCount->workflowStage?->workflow_domain_id
            ?? $this->definition($inventoryCount)->domainId();

        return Task::withoutGlobalScopes()
            ->where('tenant_id', $inventoryCount->tenant_id)
            ->where('workflow_domain_id', $workflowDomainId)
            ->where('domain_record_id', $inventoryCount->id)
            ->where(function ($query) use ($inventoryCount): void {
                $query->where('source', Task::SOURCE_MANUAL);

                if ($inventoryCount->workflow_stage_id !== null) {
                    $query->orWhere(function ($query) use ($inventoryCount): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $inventoryCount->workflow_stage_id);
                    });
                }
            })
            ->with(['assignedTo', 'completedBy'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskPayload($task, $viewer))
            ->values()
            ->all();
    }

    /**
     * Enter a target stage, completing automatic stages until a manual stage or final posting is reached.
     */
    private function enterStageAndCompleteAutomaticStages(
        InventoryCount $inventoryCount,
        WorkflowStage $targetStage,
        User $user
    ): void {
        $stage = $targetStage;

        while ($stage !== null) {
            $inventoryCount->workflow_stage_id = $stage->id;
            $inventoryCount->tasked_by_user_id = (int) $user->id;
            $inventoryCount->save();

            if ($stage->completion_mode !== self::AUTOMATIC_COMPLETION_MODE) {
                $this->generateTasks->execute(
                    (int) $inventoryCount->tenant_id,
                    (int) $inventoryCount->id,
                    $stage,
                    $inventoryCount->assigned_to_user_id
                );

                return;
            }

            if ($stage->is_inventory_effect_stage) {
                $this->postInventoryCount->execute($inventoryCount, (int) $user->id);

                return;
            }

            $this->assertCanMovePastScheduling($inventoryCount, $stage);

            $inventoryCount->setRelation('workflowStage', $stage);
            $stage = $this->nextActiveStage($inventoryCount);
        }
    }

    /**
     * Resolve the next workflow stage the visible action should enter.
     */
    private function nextWorkflowActionStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        if ($inventoryCount->posted_at !== null) {
            return null;
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return $this->firstActiveStage($inventoryCount);
        }

        $currentStage = $this->currentStage($inventoryCount);

        if ($currentStage?->is_inventory_effect_stage) {
            return $currentStage;
        }

        return $this->nextActiveStage($inventoryCount);
    }

    /**
     * Resolve the visible action-button text for a workflow stage target.
     */
    private function actionButtonText(?WorkflowStage $workflowStage, ?InventoryCount $inventoryCount = null): ?string
    {
        if (! $workflowStage) {
            return null;
        }

        if (
            $inventoryCount !== null
            && $inventoryCount->posted_at === null
            && $inventoryCount->workflow_stage_id !== null
            && (int) $inventoryCount->workflow_stage_id === (int) $workflowStage->id
            && $workflowStage->is_inventory_effect_stage
        ) {
            return 'Complete';
        }

        $actionVerb = trim((string) ($workflowStage->action_verb ?? ''));

        if ($actionVerb !== '') {
            return Str::title(Str::lower($actionVerb));
        }

        return $workflowStage->name;
    }

    /**
     * Resolve helper copy for an inventory workflow action.
     */
    private function actionDescription(WorkflowStage $workflowStage, string $fallback): string
    {
        return filled($workflowStage->description)
            ? (string) $workflowStage->description
            : $fallback;
    }

    /**
     * Determine whether the next workflow action should be shown.
     */
    private function canShowNextWorkflowAction(
        InventoryCount $inventoryCount,
        bool $canSubmitWorkflow,
        bool $canOperateWorkflow
    ): bool {
        if ($inventoryCount->posted_at !== null) {
            return false;
        }

        return $inventoryCount->workflow_stage_id === null
            ? $canSubmitWorkflow
            : $canOperateWorkflow;
    }

    /**
     * Require at least one material before the automatic Scheduling stage can complete.
     *
     * @throws DomainException
     */
    private function assertCanMovePastScheduling(InventoryCount $inventoryCount, WorkflowStage $stage): void
    {
        if ((string) $stage->key !== 'scheduling') {
            return;
        }

        if ($inventoryCount->lines()->exists()) {
            return;
        }

        throw new DomainException('Add at least one material item before moving past Scheduling.');
    }

    /**
     * Require every counted material to have a filled quantity before leaving the Counting stage.
     *
     * @throws DomainException
     */
    private function assertCountedQuantitiesPresent(InventoryCount $inventoryCount): void
    {
        $hasBlankQuantity = $inventoryCount->lines()
            ->where(function ($query): void {
                $query->whereNull('counted_quantity')
                    ->orWhereRaw("TRIM(counted_quantity) = ''");
            })
            ->exists();

        if ($hasBlankQuantity) {
            throw new DomainException('Enter a counted quantity for every item before moving past Counting.');
        }
    }

    /**
     * Build the shared task payload contract used by the detail page and task list endpoint.
     *
     * @return array<string, int|string|bool|null|array<int, string>>
     */
    private function taskPayload(Task $task, ?User $viewer): array
    {
        $isCompleted = $task->isCompleted() || $task->completed_at !== null;
        $inventoryCount = InventoryCount::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $task->tenant_id)
            ->find($task->domain_record_id);
        $viewerUserId = $viewer?->id;
        $canCompleteWorkflowTask = $viewer !== null
            && app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain($viewer, 'inventory');
        $canComplete = ! $isCompleted
            && $viewerUserId !== null
            && $canCompleteWorkflowTask
            && $inventoryCount?->workflow_cancelled_at === null
            && (
                (int) $task->assigned_to_user_id === (int) $viewerUserId
                || (int) $inventoryCount?->assigned_to_user_id === (int) $viewerUserId
            );

        return [
            'id' => $task->id,
            'source' => $task->source,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'assigned_by_user_name' => $inventoryCount?->createdByUser?->name
                ?? $inventoryCount?->taskedByUser?->name,
            'assigned_to_display' => $isCompleted ? '' : ($task->assignedTo?->name ?? '-'),
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'status_tone' => $isCompleted ? 'success' : 'muted',
            'is_completed' => $isCompleted,
            'can_complete' => $canComplete,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'completed_by_display' => $isCompleted ? ($task->completedBy?->name ?? '-') : '',
            'complete_url' => route('tasks.complete', $task),
            'available_actions' => $canComplete ? ['complete'] : [],
            'availableActions' => $canComplete ? ['complete'] : [],
        ];
    }

    /**
     * Lock one count for workflow mutation.
     */
    private function lockCount(InventoryCount $inventoryCount, User $user): InventoryCount
    {
        return InventoryCount::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($inventoryCount->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Reload workflow relations.
     */
    private function freshCount(InventoryCount $inventoryCount): InventoryCount
    {
        return $inventoryCount->fresh(['workflowStage']) ?? $inventoryCount;
    }

    /**
     * Return the cached workflow definition.
     */
    private function definition(InventoryCount $inventoryCount): WorkflowDefinition
    {
        return $this->definitions->for((int) $inventoryCount->tenant_id, 'inventory');
    }
}
