<?php

namespace App\Services\Workflows;

use App\Actions\Sales\BuildSalesOrderIssuePlanAction;
use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\DeleteOpenSalesOrderTasksAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Contracts\Workflows\Workflowable;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\WorkflowStage;
use App\Support\Workflows\WorkflowDefinition;
use App\Support\Workflows\WorkflowDefinitionRepository;
use DomainException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Sales Order workflow runtime behavior.
 */
class SalesOrderWorkflow extends BaseWorkflow
{
    private const SCALE = 6;

    private ?string $startingStatus = null;

    public function __construct(
        WorkflowDefinitionRepository $definitions,
        private readonly BuildSalesOrderIssuePlanAction $buildPlanAction,
        private readonly AssertWorkflowStageTasksCompletedAction $assertTasksCompleted,
        private readonly GenerateWorkflowStageTasksAction $generateTasks,
        private readonly DeleteOpenSalesOrderTasksAction $deleteOpenTasks
    ) {
        parent::__construct($definitions);
    }

    /**
     * Return the response payload expected by the Sales Order detail page.
     *
     * @return array<string, mixed>
     */
    public function responsePayload(SalesOrder $salesOrder): array
    {
        $definition = $this->definition($salesOrder);
        $currentStage = $this->currentStage($salesOrder, $definition);
        $workflowCompleted = in_array($salesOrder->status, SalesOrder::terminalStatuses(), true);
        $progressStage = $workflowCompleted ? null : $currentStage;

        return [
            'data' => [
                'id' => $salesOrder->id,
                'status' => $salesOrder->status,
                'available_status_transitions' => $this->availableTransitions($salesOrder, $definition),
                'can_edit' => $salesOrder->isEditable(),
                'can_manage_lines' => $salesOrder->allowsLineMutations(),
                'current_stage_tasks' => $this->currentStageTasksData($salesOrder, $definition),
                'workflowProgressSteps' => $definition->progressSteps($progressStage, $workflowCompleted),
            ],
            'workflow' => $this->workflowPayload($salesOrder, $definition),
        ];
    }

    /**
     * Return available Sales Order workflow transitions.
     *
     * @return list<string>
     */
    public function availableTransitions(SalesOrder $salesOrder, ?WorkflowDefinition $definition = null): array
    {
        if ($salesOrder->status === SalesOrder::STATUS_DRAFT) {
            return [SalesOrder::STATUS_OPEN];
        }

        if (in_array($salesOrder->status, SalesOrder::terminalStatuses(), true)) {
            return [];
        }

        $definition ??= $this->definition($salesOrder);
        $currentStage = $this->currentStage($salesOrder, $definition);

        if (! $currentStage) {
            return [SalesOrder::STATUS_COMPLETED, SalesOrder::STATUS_CANCELLED];
        }

        $nextStatus = $this->statusForStage($currentStage);

        return $nextStatus !== ''
            ? [$nextStatus, SalesOrder::STATUS_CANCELLED]
            : [SalesOrder::STATUS_COMPLETED, SalesOrder::STATUS_CANCELLED];
    }

    /**
     * Resolve the current Sales Order workflow stage from status.
     */
    public function currentStage(SalesOrder $salesOrder, ?WorkflowDefinition $definition = null): ?WorkflowStage
    {
        $definition ??= $this->definition($salesOrder);

        if ($salesOrder->status === SalesOrder::STATUS_DRAFT) {
            return null;
        }

        if (in_array($salesOrder->status, [
            SalesOrder::STATUS_OPEN,
            SalesOrder::STATUS_PACKING,
        ], true)) {
            return $definition->stageByStatus(
                SalesOrder::STATUS_OPEN,
                fn (string $status): string => $this->stageKeyForStatus($status)
            );
        }

        if ($this->isSystemStatus($salesOrder->status)) {
            return null;
        }

        $matchedStage = $definition->stageByStatus(
            $salesOrder->status,
            fn (string $status): string => $this->stageKeyForStatus($status)
        );

        return $matchedStage ? $definition->nextStageAfter($matchedStage) ?? $matchedStage : null;
    }

    /**
     * Resolve the display stage immediately before the current stage.
     */
    public function previousStage(SalesOrder $salesOrder, ?WorkflowDefinition $definition = null): ?WorkflowStage
    {
        $definition ??= $this->definition($salesOrder);

        return $definition->previousStageBefore($this->currentStage($salesOrder, $definition));
    }

    /**
     * Authorize a transition.
     */
    protected function authorize(Workflowable $record, string $target): void
    {
        Gate::authorize('sales-sales-orders-manage');
    }

    /**
     * Lock and return the Sales Order.
     */
    protected function lockRecord(Workflowable $record): Workflowable
    {
        return SalesOrder::query()
            ->whereKey($record->workflowRecordId())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Validate target movement.
     */
    protected function assertTransitionAllowed(
        Workflowable $record,
        WorkflowDefinition $definition,
        string $target
    ): void {
        if (
            ! $record instanceof SalesOrder
            || ! in_array($target, $this->availableTransitions($record, $definition), true)
        ) {
            throw new DomainException('Status transition is not allowed.');
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
        if (! $record instanceof SalesOrder || $target === SalesOrder::STATUS_CANCELLED) {
            return;
        }

        $stage = $this->currentStage($record, $definition);

        if ($stage === null) {
            return;
        }

        $this->assertTasksCompleted->execute(
            (int) $record->tenant_id,
            (int) $record->id,
            $stage,
            'Complete all tasks for this stage before moving the sales order forward.'
        );
    }

    /**
     * Run sales-specific effects before status persistence.
     */
    protected function beforePersist(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        if (! $record instanceof SalesOrder) {
            return;
        }

        $this->startingStatus = (string) $record->status;

        if ($target === SalesOrder::STATUS_CANCELLED && $this->hasPostedInventoryImpact($record)) {
            $this->reversePostedInventory($record);

            return;
        }

        if ($target === SalesOrder::STATUS_CANCELLED) {
            return;
        }

        $currentStage = $this->currentStage($record, $definition);

        if ($record->status === SalesOrder::STATUS_DRAFT || $currentStage?->is_inventory_effect_stage) {
            $plan = $this->buildPlanAction->execute($record);
        }

        if ($currentStage?->is_inventory_effect_stage) {
            foreach ($plan ?? [] as $moveData) {
                StockMove::query()->create($moveData);
            }
        }
    }

    /**
     * Generate entered-stage tasks and handle cancellation cleanup.
     */
    protected function afterTransition(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        if (! $record instanceof SalesOrder) {
            return;
        }

        if ($target === SalesOrder::STATUS_CANCELLED) {
            $this->deleteOpenTasks->execute($record);
            $this->startingStatus = null;

            return;
        }

        $targetStage = $this->startingStatus === SalesOrder::STATUS_DRAFT && $target === SalesOrder::STATUS_OPEN
            ? $definition->firstStage()
            : $this->activeStageForStatus($record, $definition, $target);

        $this->startingStatus = null;

        if ($targetStage === null) {
            return;
        }

        $this->generateTasks->execute(
            (int) $record->tenant_id,
            (int) $record->id,
            $targetStage
        );
    }

    /**
     * Return the cached workflow definition for a Sales Order.
     */
    private function definition(SalesOrder $salesOrder): WorkflowDefinition
    {
        return $this->definitions->for((int) $salesOrder->tenant_id, 'sales');
    }

    /**
     * Resolve an active stage for a target status.
     */
    private function activeStageForStatus(
        SalesOrder $salesOrder,
        WorkflowDefinition $definition,
        string $status
    ): ?WorkflowStage {
        if (in_array($status, [
            SalesOrder::STATUS_OPEN,
            SalesOrder::STATUS_PACKING,
        ], true)) {
            return $this->currentStage($salesOrder, $definition);
        }

        if ($this->isSystemStatus($status)) {
            return null;
        }

        $matchedStage = $definition->stageByStatus(
            $status,
            fn (string $status): string => $this->stageKeyForStatus($status)
        );

        return $matchedStage ? $definition->nextStageAfter($matchedStage) ?? $matchedStage : null;
    }

    /**
     * Convert a workflow stage to a persisted status value.
     */
    private function statusForStage(WorkflowStage $stage): string
    {
        $completeLabel = trim((string) ($stage->status_complete_label ?? ''));

        return $completeLabel !== ''
            ? Str::upper($completeLabel)
            : Str::upper($stage->key);
    }

    /**
     * Determine whether status is a system status.
     */
    private function isSystemStatus(string $status): bool
    {
        return in_array($status, [
            SalesOrder::STATUS_DRAFT,
            SalesOrder::STATUS_OPEN,
            SalesOrder::STATUS_COMPLETED,
            SalesOrder::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Normalize a sales-order status to a workflow stage key.
     */
    private function stageKeyForStatus(string $status): string
    {
        return match ($status) {
            SalesOrder::STATUS_OPEN => 'packing',
            SalesOrder::STATUS_PACKED => 'packing',
            SalesOrder::STATUS_PACKING => 'packing',
            SalesOrder::STATUS_SHIPPING => 'shipping',
            SalesOrder::STATUS_INVOICED => 'invoicing',
            default => Str::lower($status),
        };
    }

    /**
     * Build the shared workflow action-button payload.
     *
     * @return array<string, mixed>
     */
    private function workflowPayload(SalesOrder $salesOrder, WorkflowDefinition $definition): array
    {
        $currentStage = $this->currentStage($salesOrder, $definition);
        $displayStage = $this->previousStage($salesOrder, $definition);
        $actions = [];

        foreach ($this->availableTransitions($salesOrder, $definition) as $status) {
            $stage = $this->workflowActionStage($salesOrder, $definition, $status);

            $actions[] = [
                'id' => $status,
                'type' => $status,
                'label' => $this->workflowActionLabel($status, $stage),
                'description' => $this->workflowActionDescription($status, $stage),
                'endpoint' => route('sales.orders.status.update', $salesOrder),
                'method' => 'PATCH',
            ];
        }

        $currentLabel = $displayStage?->status_complete_label
            ?? $currentStage?->status_complete_label
            ?? $currentStage?->name
            ?? $salesOrder->status;

        return [
            'status' => $salesOrder->status,
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
        ];
    }

    /**
     * Resolve the workflow stage backing an action.
     */
    private function workflowActionStage(
        SalesOrder $salesOrder,
        WorkflowDefinition $definition,
        string $status
    ): ?WorkflowStage {
        if ($salesOrder->status === SalesOrder::STATUS_DRAFT && $status === SalesOrder::STATUS_OPEN) {
            return $definition->firstStage();
        }

        if ($status === SalesOrder::STATUS_CANCELLED || $status === SalesOrder::STATUS_COMPLETED) {
            return null;
        }

        return $definition->nextStageAfter($this->currentStage($salesOrder, $definition));
    }

    /**
     * Resolve the label for a workflow action.
     */
    private function workflowActionLabel(string $status, ?WorkflowStage $stage): string
    {
        return match ($status) {
            SalesOrder::STATUS_CANCELLED => 'Cancel',
            SalesOrder::STATUS_COMPLETED => 'Complete',
            default => Str::headline(Str::lower(trim((string) ($stage?->action_verb ?: $stage?->name ?: $status)))),
        };
    }

    /**
     * Resolve the helper copy for a workflow action.
     */
    private function workflowActionDescription(string $status, ?WorkflowStage $stage): string
    {
        return match ($status) {
            SalesOrder::STATUS_CANCELLED => 'Cancel this sales order.',
            SalesOrder::STATUS_COMPLETED => 'Mark this sales order complete.',
            default => filled($stage?->description)
                ? (string) $stage->description
                : 'Move this sales order to the next workflow stage.',
        };
    }

    /**
     * Build the current-stage workflow tasks payload.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function currentStageTasksData(SalesOrder $order, WorkflowDefinition $definition): array
    {
        $stage = $this->currentStage($order, $definition);

        if (! $stage) {
            return [];
        }

        $viewerUserId = auth()->id();

        return Task::query()
            ->with(['assignedTo', 'completedBy'])
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('domain_record_id', $order->id)
            ->where(function ($query) use ($stage): void {
                $query->where('source', Task::SOURCE_MANUAL)
                    ->orWhere(function ($query) use ($stage): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $stage->id);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskData($task, $viewerUserId))
            ->values()
            ->all();
    }

    /**
     * Build workflow task payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function taskData(Task $task, ?int $viewerUserId): array
    {
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
            'can_complete' => ! $task->isCompleted()
                && $viewerUserId !== null
                && (int) $task->assigned_to_user_id === (int) $viewerUserId,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'complete_url' => route('tasks.complete', $task),
        ];
    }

    /**
     * Determine whether inventory has already been posted for the sales order.
     */
    private function hasPostedInventoryImpact(SalesOrder $salesOrder): bool
    {
        $lineIds = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->id)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return false;
        }

        return StockMove::query()
            ->where('source_type', SalesOrderLine::class)
            ->whereIn('source_id', $lineIds->all())
            ->exists();
    }

    /**
     * Append reversing stock moves for a packed sales order cancellation.
     */
    private function reversePostedInventory(SalesOrder $salesOrder): void
    {
        $lineIds = SalesOrderLine::query()
            ->where('sales_order_id', $salesOrder->id)
            ->pluck('id');

        $moves = StockMove::query()
            ->where('source_type', SalesOrderLine::class)
            ->whereIn('source_id', $lineIds->all() === [] ? [0] : $lineIds->all())
            ->orderBy('id')
            ->get();

        if ($moves->isEmpty()) {
            throw new DomainException('Status transition is not allowed.');
        }

        foreach ($moves as $move) {
            StockMove::query()->create([
                'tenant_id' => $move->tenant_id,
                'item_id' => $move->item_id,
                'uom_id' => $move->uom_id,
                'quantity' => bcsub('0.000000', (string) $move->quantity, self::SCALE),
                'type' => $move->type,
                'status' => $move->status,
                'source_type' => $move->source_type,
                'source_id' => $move->source_id,
            ]);
        }
    }
}
