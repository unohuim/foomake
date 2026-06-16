<?php

namespace App\Services\Workflows;

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Contracts\Workflows\Workflowable;
use App\Models\MakeOrder;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use App\Support\Workflows\WorkflowDefinition;
use App\Support\Workflows\WorkflowDefinitionRepository;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Make Order workflow runtime behavior.
 */
class MakeOrderWorkflow extends BaseWorkflow
{
    private ?User $actingUser = null;

    public function __construct(
        WorkflowDefinitionRepository $definitions,
        private readonly AssertWorkflowStageTasksCompletedAction $assertTasksCompleted,
        private readonly GenerateWorkflowStageTasksAction $generateTasks
    ) {
        parent::__construct($definitions);
    }

    /**
     * Enter the first active manufacturing workflow stage.
     *
     * @throws DomainException
     */
    public function enter(MakeOrder $makeOrder, User $user): MakeOrder
    {
        $firstStage = $this->firstActiveStage($makeOrder);

        if (! $firstStage) {
            throw new DomainException('No active manufacturing workflow stage is configured.');
        }

        return $this->moveToStage($makeOrder, (int) $firstStage->id, $user);
    }

    /**
     * Move a Make Order to a configured manufacturing workflow stage.
     *
     * @throws DomainException
     */
    public function moveToStage(MakeOrder $makeOrder, int $targetWorkflowStageId, User $user): MakeOrder
    {
        $this->actingUser = $user;

        try {
            /** @var MakeOrder $moved */
            $moved = $this->transition($makeOrder, (string) $targetWorkflowStageId);

            return $moved;
        } finally {
            $this->actingUser = null;
        }
    }

    /**
     * Resolve the current active workflow stage for the Make Order.
     */
    public function currentStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        if ($makeOrder->workflow_stage_id === null) {
            return null;
        }

        return $this->definition($makeOrder)
            ->activeStages()
            ->firstWhere('id', (int) $makeOrder->workflow_stage_id);
    }

    /**
     * Resolve the first active workflow stage for the Make Order.
     */
    public function firstActiveStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        return $this->definition($makeOrder)->firstStage();
    }

    /**
     * Resolve the next active workflow stage after the current one.
     */
    public function nextActiveStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        return $this->definition($makeOrder)->nextStageAfter($this->currentStage($makeOrder));
    }

    /**
     * Return the valid next workflow-stage targets for the Make Order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function availableTransitions(MakeOrder $makeOrder): Collection
    {
        if ($makeOrder->status === MakeOrder::STATUS_MADE || $makeOrder->status === MakeOrder::STATUS_CANCELLED) {
            return collect();
        }

        $currentStage = $this->currentStage($makeOrder);

        if (! $currentStage) {
            $firstStage = $this->firstActiveStage($makeOrder);

            return $firstStage ? collect([$firstStage]) : collect();
        }

        $nextStage = $this->nextActiveStage($makeOrder);

        return $nextStage ? collect([$nextStage]) : collect();
    }

    /**
     * Build the Make Order workflow section payload.
     *
     * @return array<string, mixed>
     */
    public function responsePayload(MakeOrder $makeOrder, User $viewer): array
    {
        $makeOrder->loadMissing(['workflowStage', 'madeByUser', 'taskedByUser']);

        $currentStage = $this->currentStage($makeOrder);
        $availableStages = collect();
        $workflowLabel = $this->displayLabel($makeOrder);

        if ($makeOrder->status !== MakeOrder::STATUS_MADE && $makeOrder->status !== MakeOrder::STATUS_CANCELLED) {
            $availableStages = $this->availableTransitions($makeOrder);
        }

        $forwardAction = null;
        $actions = [];
        $canOperateWorkflow = $this->canOperate($viewer);
        $nextStage = $currentStage
            ? $this->nextActiveStage($makeOrder)
            : $this->firstActiveStage($makeOrder);

        if (
            $canOperateWorkflow
            && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true)
            && $nextStage !== null
        ) {
            if ($currentStage?->is_inventory_effect_stage) {
                $forwardAction = [
                    'id' => $nextStage->id,
                    'label' => $nextStage->action_verb ?: $nextStage->name,
                    'type' => 'make',
                    'description' => $this->stageActionDescription($nextStage, 'Make this make order.'),
                    'endpoint' => route('manufacturing.make-orders.make', $makeOrder),
                ];
            } else {
                $forwardAction = [
                    'id' => $nextStage->id,
                    'label' => $nextStage->action_verb ?: $nextStage->name,
                    'type' => 'stage',
                    'description' => $this->stageActionDescription(
                        $nextStage,
                        'Move this make order to the next workflow stage.'
                    ),
                    'endpoint' => route('manufacturing.make-orders.workflow-stage.update', $makeOrder),
                ];
            }
        }

        if ($forwardAction) {
            $actions[] = $forwardAction;
        }

        if (
            $canOperateWorkflow
            && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true)
        ) {
            $actions[] = [
                'id' => 'cancel',
                'type' => 'cancel',
                'label' => 'Cancel',
                'description' => 'Cancel this make order.',
                'endpoint' => route('manufacturing.make-orders.destroy', $makeOrder),
                'method' => 'DELETE',
                'requiresConfirmation' => true,
            ];
        }

        return [
            'default_open' => false,
            'transition_url' => route('manufacturing.make-orders.workflow-stage.update', $makeOrder),
            'can_move_stage' => $canOperateWorkflow
                && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true),
            'current_stage' => $currentStage ? [
                'id' => $currentStage->id,
                'workflow_domain_id' => $currentStage->workflow_domain_id,
                'key' => $currentStage->key,
                'name' => $currentStage->name,
                'action_verb' => $currentStage->action_verb,
                'status_complete_label' => $currentStage->status_complete_label,
                'description' => $currentStage->description,
            ] : null,
            'status' => $workflowLabel,
            'status_label' => $workflowLabel,
            'display_label' => $workflowLabel,
            'currentLabel' => $workflowLabel,
            'current_stage_label' => $workflowLabel,
            'header_menu' => [
                'currentLabel' => $workflowLabel,
                'options' => $actions,
            ],
            'actions' => $actions,
            'available_stages' => $availableStages
                ->map(fn (WorkflowStage $stage): array => [
                    'id' => $stage->id,
                    'key' => $stage->key,
                    'name' => $stage->name,
                    'description' => $stage->description,
                ])
                ->values()
                ->all(),
            'next_stage_action' => $forwardAction,
            'due_date' => $makeOrder->due_date?->format('Y-m-d'),
            'due_date_update_url' => route('manufacturing.make-orders.due-date.update', $makeOrder),
            'can_edit_due_date' => $canOperateWorkflow
                && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true),
            'made_by_user_id' => $makeOrder->made_by_user_id,
            'owner_user_name' => $makeOrder->madeByUser?->name,
            'assignee_options' => $this->assigneeOptions((int) $makeOrder->tenant_id),
            'assignment_update_url' => route('manufacturing.make-orders.assignment.update', $makeOrder),
            'can_edit_assignment' => $canOperateWorkflow,
            'tasked_by_user_id' => $makeOrder->tasked_by_user_id,
            'tasked_by_user_name' => $makeOrder->taskedByUser?->name,
            'current_stage_tasks' => $this->tasksPayload($makeOrder, $currentStage, $viewer),
        ];
    }

    /**
     * Build workflow progress steps without the legacy progress action.
     *
     * @return array<int, array{label: string, status: string, url: null, current: bool}>
     */
    public function progressSteps(MakeOrder $makeOrder): array
    {
        return $this->definition($makeOrder)->progressSteps(
            $makeOrder->status !== MakeOrder::STATUS_MADE ? $this->currentStage($makeOrder) : null,
            $makeOrder->status === MakeOrder::STATUS_MADE
        );
    }

    /**
     * Resolve the visible workflow state label for one Make Order.
     */
    public function stateLabel(MakeOrder $makeOrder): string
    {
        if ($makeOrder->status === MakeOrder::STATUS_CANCELLED) {
            return MakeOrder::STATUS_CANCELLED;
        }

        if ($makeOrder->status === MakeOrder::STATUS_MADE) {
            return $makeOrder->workflowStage?->status_complete_label ?? 'COMPLETED';
        }

        if ($makeOrder->workflow_stage_id === null) {
            return MakeOrder::STATUS_DRAFT;
        }

        return $makeOrder->workflowStage?->name ?? '-';
    }

    /**
     * Authorize a transition.
     */
    protected function authorize(Workflowable $record, string $target): void
    {
        abort_unless($this->actingUser && $this->canOperate($this->actingUser), 403);
    }

    /**
     * Lock and return the Make Order.
     */
    protected function lockRecord(Workflowable $record, WorkflowDefinition $definition): Workflowable
    {
        $query = MakeOrder::query()
            ->whereKey($record->workflowRecordId())
            ->lockForUpdate();

        if ($this->actingUser) {
            $query->where('tenant_id', $this->actingUser->tenant_id);
        }

        return $query->firstOrFail()->load(['workflowStage', 'madeByUser', 'taskedByUser']);
    }

    /**
     * Validate target movement.
     */
    protected function assertTransitionAllowed(
        Workflowable $record,
        WorkflowDefinition $definition,
        string $target
    ): void {
        if (! $record instanceof MakeOrder || ! ctype_digit($target)) {
            throw new DomainException('Workflow stage transition is not allowed.');
        }

        if ($record->status === MakeOrder::STATUS_MADE) {
            throw new DomainException('Made make orders cannot move workflow stages.');
        }

        if ($record->status === MakeOrder::STATUS_CANCELLED) {
            throw new DomainException('Cancelled make orders cannot move workflow stages.');
        }

        if ($this->definition($record)->activeStages()->isEmpty()) {
            throw new DomainException('No active manufacturing workflow stage is configured.');
        }

        $targetStage = $this->availableTransitions($record)->firstWhere('id', (int) $target);

        if (! $targetStage) {
            throw new DomainException('Workflow stage transition is not allowed.');
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
        if (! $record instanceof MakeOrder) {
            return;
        }

        $currentStage = $this->currentStage($record);

        if (! $currentStage) {
            return;
        }

        $this->assertTasksCompleted->execute(
            (int) $record->tenant_id,
            (int) $record->id,
            $currentStage,
            'Complete all tasks for this stage before moving the make order forward.'
        );
    }

    /**
     * Persist Make Order workflow movement.
     */
    protected function persistMovement(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        if (! $record instanceof MakeOrder) {
            return;
        }

        $currentStage = $this->currentStage($record);

        $record->workflow_stage_id = (int) $target;

        if ($currentStage === null) {
            $record->status = MakeOrder::STATUS_SCHEDULED;
            $record->scheduled_at = $record->scheduled_at ?? now();
        }

        $record->tasked_by_user_id = $this->actingUser?->id;
        $record->save();
    }

    /**
     * Generate tasks for the newly entered stage.
     */
    protected function afterTransition(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        if (! $record instanceof MakeOrder) {
            return;
        }

        $targetStage = $this->definition($record)->activeStages()->firstWhere('id', (int) $target);

        if ($targetStage) {
            $this->generateTasks->execute(
                (int) $record->tenant_id,
                (int) $record->id,
                $targetStage
            );
        }
    }

    /**
     * Reload the Make Order with workflow relations.
     */
    protected function freshRecord(Workflowable $record): Workflowable
    {
        if ($record instanceof MakeOrder) {
            return $record->fresh(['workflowStage', 'madeByUser', 'taskedByUser']) ?? $record;
        }

        return parent::freshRecord($record);
    }

    /**
     * Return the filtered workflow definition for Make Orders.
     */
    private function definition(MakeOrder $makeOrder): WorkflowDefinition
    {
        $definition = $this->definitions->for((int) $makeOrder->tenant_id, 'manufacturing');
        $stages = $this->runtimeStages($definition->activeStages());

        return new WorkflowDefinition(
            $definition->tenantId(),
            $definition->domainId(),
            $definition->domainKey(),
            $stages
        );
    }

    /**
     * Prefer custom manufacturing stages when configured, matching existing Make Order behavior.
     *
     * @param Collection<int, WorkflowStage> $stages
     *
     * @return Collection<int, WorkflowStage>
     */
    private function runtimeStages(Collection $stages): Collection
    {
        $customStages = $stages->reject(fn (WorkflowStage $stage): bool => in_array($stage->key, [
            'creating',
            'making',
            'completing',
            'cancelling',
        ], true));

        if ($customStages->isNotEmpty()) {
            return $customStages->values();
        }

        return $stages
            ->reject(fn (WorkflowStage $stage): bool => in_array($stage->key, [
                'creating',
                'cancelling',
            ], true))
            ->values();
    }

    /**
     * Resolve the visible workflow status label for one Make Order.
     */
    private function displayLabel(MakeOrder $makeOrder): string
    {
        if ($makeOrder->status === MakeOrder::STATUS_CANCELLED) {
            return 'CANCELLED';
        }

        if ($makeOrder->workflow_stage_id === null) {
            return MakeOrder::STATUS_DRAFT;
        }

        if ($makeOrder->status === MakeOrder::STATUS_MADE) {
            return 'COMPLETED';
        }

        $currentStage = $this->currentStage($makeOrder);

        return $currentStage?->status_complete_label
            ?? $currentStage?->name
            ?? MakeOrder::STATUS_DRAFT;
    }

    /**
     * Resolve helper copy for a workflow stage action from the stage itself.
     */
    private function stageActionDescription(?WorkflowStage $stage, string $fallback): string
    {
        return filled($stage?->description)
            ? (string) $stage->description
            : $fallback;
    }

    /**
     * Build tenant user options for Make Order owner assignment.
     *
     * @return array<int, array<string, string>>
     */
    private function assigneeOptions(int $tenantId): array
    {
        $assignedUsers = app(WorkflowAssignmentPermissions::class)
            ->ownerEligibleUsersQuery($tenantId, 'manufacturing')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->all();

        return [
            [
                'value' => '',
                'label' => 'Unassigned',
            ],
            ...$assignedUsers,
        ];
    }

    /**
     * Build task payload data for the current Make Order workflow stage.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function tasksPayload(MakeOrder $makeOrder, ?WorkflowStage $currentStage, User $viewer): array
    {
        if (! $currentStage) {
            return [];
        }

        return Task::query()
            ->with(['assignedTo', 'completedBy'])
            ->where('tenant_id', $makeOrder->tenant_id)
            ->where('workflow_domain_id', $currentStage->workflow_domain_id)
            ->where('domain_record_id', $makeOrder->id)
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
            ->map(function (Task $task) use ($viewer): array {
                $canComplete = ! $task->isCompleted()
                    && (int) $task->assigned_to_user_id === (int) $viewer->id
                    && app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain($viewer, 'manufacturing');

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
            })
            ->values()
            ->all();
    }

    /**
     * Determine whether the user can operate Make Order workflow.
     */
    private function canOperate(?User $user): bool
    {
        return $user !== null
            && app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, 'manufacturing');
    }
}
