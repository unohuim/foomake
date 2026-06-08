<?php

namespace App\Services\Workflows;

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Complete and cancel configurable workflow stages for domain records.
 */
class WorkflowTransitionService
{
    private const PURCHASING_DOMAIN = 'purchasing';
    private const AUTOMATIC_COMPLETION_MODE = 'automatic';

    public function __construct(
        private readonly AssertWorkflowStageTasksCompletedAction $assertTasksCompleted,
        private readonly GenerateWorkflowStageTasksAction $generateTasks,
        private readonly PurchaseOrderLifecycleService $purchaseOrderLifecycleService
    ) {
    }

    /**
     * Ensure a purchase order has entered the first purchasing workflow stage.
     */
    public function initializePurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->current_workflow_stage_id !== null) {
            return $purchaseOrder;
        }

        $stages = $this->activeStagesForDomain($purchaseOrder->tenant_id, self::PURCHASING_DOMAIN);
        $firstStage = $stages->first();

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

        return $purchaseOrder->fresh([
            'currentWorkflowStage',
            'lastCompletedWorkflowStage',
        ]);
    }

    /**
     * Complete the current purchase order workflow stage.
     *
     * @return array<string, mixed>
     *
     * @throws DomainException
     */
    public function completePurchaseOrderStage(PurchaseOrder $purchaseOrder, User $user): array
    {
        abort_unless(
            app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, self::PURCHASING_DOMAIN),
            403
        );

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $user): PurchaseOrder {
            $lockedOrder = PurchaseOrder::query()
                ->where('tenant_id', $user->tenant_id)
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            $lockedOrder = $this->initializePurchaseOrder($lockedOrder);

            $this->completeCurrentPurchaseOrderStage($lockedOrder, $user);

            while (
                $lockedOrder->currentWorkflowStage
                && $lockedOrder->currentWorkflowStage->completion_mode === self::AUTOMATIC_COMPLETION_MODE
            ) {
                $this->completeCurrentPurchaseOrderStage($lockedOrder, $user);
            }

            return $lockedOrder->fresh([
                'currentWorkflowStage',
                'lastCompletedWorkflowStage',
            ]);
        });

        return $this->purchaseOrderWorkflowPayload($purchaseOrder, $user);
    }

    /**
     * Cancel a purchase order workflow.
     *
     * @return array<string, mixed>
     *
     * @throws DomainException
     */
    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder, User $user): array
    {
        abort_unless(
            app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, self::PURCHASING_DOMAIN),
            403
        );

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $user): PurchaseOrder {
            $lockedOrder = PurchaseOrder::query()
                ->where('tenant_id', $user->tenant_id)
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedOrder->isTerminal()) {
                throw new DomainException('Purchase order workflow cannot be cancelled.');
            }

            if ($lockedOrder->receipts()->exists()) {
                throw new DomainException('Purchase order workflow cannot be cancelled after receiving inventory.');
            }

            $lockedOrder->forceFill([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'workflow_cancelled_at' => Carbon::now(),
                'cancelled_at' => Carbon::now(),
                'cancelled_by_user_id' => $user->id,
            ])->save();

            return $lockedOrder->fresh([
                'currentWorkflowStage',
                'lastCompletedWorkflowStage',
            ]);
        });

        return $this->purchaseOrderWorkflowPayload($purchaseOrder, $user);
    }

    /**
     * Build the workflow action-button JSON for a purchase order.
     *
     * @return array<string, mixed>
     */
    public function purchaseOrderWorkflowPayload(PurchaseOrder $purchaseOrder, ?User $user): array
    {
        if (
            $purchaseOrder->current_workflow_stage_id === null
            && $purchaseOrder->last_completed_workflow_stage_id === null
            && ! $purchaseOrder->isTerminal()
        ) {
            $purchaseOrder = $this->initializePurchaseOrder($purchaseOrder);
        }

        $purchaseOrder->loadMissing([
            'currentWorkflowStage',
            'lastCompletedWorkflowStage',
        ]);

        $currentStage = $purchaseOrder->currentWorkflowStage;
        $actions = [];

        if (
            $user
            && app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, self::PURCHASING_DOMAIN)
            && ! $purchaseOrder->isTerminal()
            && $purchaseOrder->workflow_cancelled_at === null
        ) {
            if ($currentStage) {
                if ($currentStage->key === 'receiving') {
                    $actions[] = [
                        'type' => 'receive',
                        'label' => 'Receive',
                        'description' => 'Record one receipt with one or more received lines.',
                        'endpoint' => route('purchasing.orders.receipts.store', $purchaseOrder),
                        'method' => 'POST',
                    ];
                } else {
                    $actions[] = [
                        'type' => 'complete_stage',
                        'label' => $this->naturalCase((string) $currentStage->action_verb),
                        'description' => $this->purchaseOrderStageDescription($currentStage),
                        'endpoint' => route('purchasing.orders.workflow.complete', $purchaseOrder),
                        'method' => 'POST',
                    ];
                }
            }

            if ($currentStage?->key === 'receiving') {
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
                    'type' => 'cancel',
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
                self::PURCHASING_DOMAIN
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
     * Complete the loaded current stage for a purchase order.
     *
     * @throws DomainException
     */
    private function completeCurrentPurchaseOrderStage(PurchaseOrder $purchaseOrder, User $user): void
    {
        if (
            $purchaseOrder->workflow_cancelled_at !== null
            || $purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED
        ) {
            throw new DomainException('Purchase order workflow is cancelled.');
        }

        $currentStage = $purchaseOrder->currentWorkflowStage;

        if (! $currentStage) {
            throw new DomainException('Purchase order has no current workflow stage.');
        }

        $this->assertTasksCompleted->execute(
            $purchaseOrder->tenant_id,
            $purchaseOrder->id,
            $currentStage,
            'Current workflow stage has incomplete required tasks.'
        );

        if ($currentStage->is_inventory_effect_stage) {
            $this->applyPurchaseOrderInventoryEffect($purchaseOrder, $user);
        }

        $nextStage = $this->nextActiveStage($purchaseOrder, $currentStage);
        $status = $this->mirroredPurchaseOrderStatus($purchaseOrder, $currentStage);

        $purchaseOrder->forceFill([
            'status' => $status,
            'last_completed_workflow_stage_id' => $currentStage->id,
            'current_workflow_stage_id' => $nextStage?->id,
        ])->save();

        $purchaseOrder->setRelation('lastCompletedWorkflowStage', $currentStage);
        $purchaseOrder->setRelation('currentWorkflowStage', $nextStage);

        if ($nextStage) {
            $this->generateTasks->execute(
                $purchaseOrder->tenant_id,
                $purchaseOrder->id,
                $nextStage
            );
        }
    }

    /**
     * Apply purchase order inventory effects when the configured stage completes.
     */
    private function applyPurchaseOrderInventoryEffect(PurchaseOrder $purchaseOrder, User $user): void
    {
        $purchaseOrder->forceFill([
            'status' => PurchaseOrder::STATUS_CREATED,
        ])->save();

        $lineItems = $this->remainingReceiptLineItems($purchaseOrder);

        if ($lineItems === []) {
            return;
        }

        $this->purchaseOrderLifecycleService->createReceipt(
            $purchaseOrder,
            $user,
            $lineItems,
            null,
            'Workflow completion',
            null
        );
    }

    /**
     * Build receipt line items for all remaining purchase order balances.
     *
     * @return array<int, array{line: PurchaseOrderLine, quantity: string}>
     */
    private function remainingReceiptLineItems(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing([
            'lines',
            'lines.item',
            'lines.purchaseOption',
            'lines.purchaseOption.packUom',
        ]);

        $totals = $this->purchaseOrderLifecycleService->computeLineTotals($purchaseOrder);
        $lineItems = [];

        foreach ($purchaseOrder->lines as $line) {
            $balance = (string) ($totals[$line->id]['balance'] ?? '0.000000');

            if (bccomp($balance, '0', 6) <= 0) {
                continue;
            }

            $lineItems[] = [
                'line' => $line,
                'quantity' => $balance,
            ];
        }

        return $lineItems;
    }

    /**
     * Mirror workflow progress into the legacy status column during migration.
     */
    private function mirroredPurchaseOrderStatus(PurchaseOrder $purchaseOrder, WorkflowStage $completedStage): string
    {
        return match ($completedStage->key) {
            'creating' => PurchaseOrder::STATUS_CREATED,
            'receiving' => PurchaseOrder::STATUS_RECEIVED,
            'completing' => PurchaseOrder::STATUS_COMPLETED,
            default => $purchaseOrder->status,
        };
    }

    /**
     * Return purchase-order specific dropdown helper copy for the current stage action.
     */
    private function purchaseOrderStageDescription(WorkflowStage $stage): string
    {
        return match ($stage->key) {
            'creating' => 'Create this purchase order, and begin workflow.',
            'receiving' => 'Record received inventory for this purchase order.',
            'completing' => 'Mark this purchase order as complete.',
            default => 'Complete the current workflow stage.',
        };
    }

    /**
     * Resolve the first active stage for a tenant/domain.
     */
    private function firstActiveStage(int $tenantId, string $domainKey): ?WorkflowStage
    {
        return $this->activeStagesForDomain($tenantId, $domainKey)->first();
    }

    /**
     * Return active stages for a tenant/domain key.
     *
     * @return Collection<int, WorkflowStage>
     */
    private function activeStagesForDomain(int $tenantId, string $domainKey): Collection
    {
        $domainId = $this->domainId($domainKey);

        if (! $domainId) {
            return new Collection();
        }

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workflow_domain_id', $domainId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve the next active stage after the completed stage.
     */
    private function nextActiveStage(PurchaseOrder $purchaseOrder, WorkflowStage $completedStage): ?WorkflowStage
    {
        return $this->activeStages($purchaseOrder->tenant_id, $completedStage->workflow_domain_id)
            ->first(function (WorkflowStage $stage) use ($completedStage): bool {
                if ($stage->sort_order > $completedStage->sort_order) {
                    return true;
                }

                return $stage->sort_order === $completedStage->sort_order
                    && $stage->id > $completedStage->id;
            });
    }

    /**
     * Return active stages for a tenant/domain id.
     *
     * @return Collection<int, WorkflowStage>
     */
    private function activeStages(int $tenantId, int $domainId): Collection
    {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workflow_domain_id', $domainId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve a workflow domain id by key.
     */
    private function domainId(string $domainKey): ?int
    {
        $domainId = WorkflowDomain::query()
            ->where('key', $domainKey)
            ->value('id');

        return $domainId ? (int) $domainId : null;
    }

    /**
     * Format canonical workflow verbs for UI presentation.
     */
    private function naturalCase(string $value): string
    {
        return ucwords(strtolower($value));
    }
}
