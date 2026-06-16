<?php

namespace App\Actions\Workflows;

use App\Models\SalesOrder;
use App\Models\Tenant;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolve the sales workflow stage that matches a sales-order operational status.
 */
class ResolveSalesWorkflowStageAction
{
    /**
     * Resolve the fixed sales workflow-domain id.
     *
     * @throws DomainException
     */
    public function salesDomainId(): int
    {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();

        $salesDomainId = WorkflowDomain::query()
            ->where('key', 'sales')
            ->value('id');

        if (! $salesDomainId) {
            throw new DomainException('Sales workflow domain is not configured.');
        }

        return (int) $salesDomainId;
    }

    /**
     * Resolve the workflow stage for a sales-order status.
     *
     * @throws DomainException
     */
    public function execute(SalesOrder $salesOrder, string $stageKey): WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        $stage = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $this->salesDomainId())
            ->where('key', $stageKey)
            ->first();

        if (! $stage) {
            throw new DomainException('Workflow stage is not configured for this tenant.');
        }

        return $stage;
    }

    /**
     * Resolve the current workflow stage for the sales order when it is operational.
     */
    public function currentStageForStatus(SalesOrder $salesOrder): ?WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        if ($salesOrder->status === SalesOrder::STATUS_DRAFT) {
            return null;
        }

        if (in_array($salesOrder->status, [
            SalesOrder::STATUS_OPEN,
            SalesOrder::STATUS_PACKING,
        ], true)) {
            return $this->workflowStageForStatusValue($salesOrder, SalesOrder::STATUS_OPEN);
        }

        if ($this->isSystemStatus($salesOrder->status)) {
            return null;
        }

        $matchedStage = $this->workflowStageForStatusValue($salesOrder, $salesOrder->status);

        return $matchedStage ? $this->nextStageAfter($salesOrder, $matchedStage) ?? $matchedStage : null;
    }

    /**
     * Resolve an active workflow stage for the provided target status.
     */
    public function activeStageForStatus(SalesOrder $salesOrder, string $status): ?WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        if (in_array($status, [
            SalesOrder::STATUS_OPEN,
            SalesOrder::STATUS_PACKING,
        ], true)) {
            return $this->currentStageForStatus($salesOrder);
        }

        if ($this->isSystemStatus($status)) {
            return null;
        }

        $matchedStage = $this->workflowStageForStatusValue($salesOrder, $status);

        return $matchedStage ? $this->nextStageAfter($salesOrder, $matchedStage) ?? $matchedStage : null;
    }

    /**
     * Resolve a configured stage for a status even when the stage is inactive compatibility metadata.
     */
    public function stageForStatus(SalesOrder $salesOrder, string $status): ?WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        if ($status === SalesOrder::STATUS_OPEN) {
            return $this->workflowStageForStatusValue($salesOrder, SalesOrder::STATUS_OPEN);
        }

        if ($this->isSystemStatus($status)) {
            return null;
        }

        return $this->workflowStageForStatusValue($salesOrder, $status);
    }

    /**
     * Return the active sales workflow stages in runtime order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(SalesOrder $salesOrder): Collection
    {
        $this->seedWorkflowStages($salesOrder);

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $this->salesDomainId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve the first active operational stage for a sales order.
     */
    public function firstActiveStage(SalesOrder $salesOrder): ?WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        return $this->activeStages($salesOrder)->first();
    }

    /**
     * Resolve the next active stage after the sales order's current status.
     */
    public function nextActiveStage(SalesOrder $salesOrder): ?WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        $activeStages = $this->activeStages($salesOrder);

        $currentStage = $this->currentStageForStatus($salesOrder);

        if (! $currentStage) {
            return null;
        }

        return $activeStages
            ->filter(fn (WorkflowStage $stage): bool => $this->comesAfter($stage, $currentStage))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->first();
    }

    /**
     * Resolve the active stage immediately before the current sales order stage.
     */
    public function previousActiveStage(SalesOrder $salesOrder): ?WorkflowStage
    {
        $this->seedWorkflowStages($salesOrder);

        $activeStages = $this->activeStages($salesOrder);
        $currentStage = $this->currentStageForStatus($salesOrder);

        if (! $currentStage) {
            return null;
        }

        return $activeStages
            ->filter(fn (WorkflowStage $stage): bool => $this->comesBefore($stage, $currentStage))
            ->last();
    }

    /**
     * Convert a workflow stage key into the persisted sales-order status value.
     */
    public function statusForStage(WorkflowStage $stage): string
    {
        $completeLabel = trim((string) ($stage->status_complete_label ?? ''));

        return $completeLabel !== ''
            ? Str::upper($completeLabel)
            : Str::upper($stage->key);
    }

    /**
     * Determine whether the provided status is one of the system statuses.
     */
    public function isSystemStatus(string $status): bool
    {
        return in_array($status, [
            SalesOrder::STATUS_DRAFT,
            SalesOrder::STATUS_OPEN,
            SalesOrder::STATUS_COMPLETED,
            SalesOrder::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Normalize a sales-order operational status back to the workflow stage key.
     */
    public function stageKeyForStatus(string $status): string
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
     * Resolve the configured stage whose key or completion label matches the provided status value.
     */
    private function workflowStageForStatusValue(SalesOrder $salesOrder, string $status): ?WorkflowStage
    {
        $normalizedStatus = Str::upper(trim($status));

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $this->salesDomainId())
            ->where(function ($query) use ($normalizedStatus): void {
                $query->whereRaw('UPPER(`status_complete_label`) = ?', [$normalizedStatus])
                    ->orWhereRaw('UPPER(`key`) = ?', [Str::upper($this->stageKeyForStatus($normalizedStatus))]);
            })
            ->first();
    }

    /**
     * Resolve the next active stage after the provided stage.
     */
    private function nextStageAfter(SalesOrder $salesOrder, WorkflowStage $stage): ?WorkflowStage
    {
        return $this->activeStages($salesOrder)
            ->filter(fn (WorkflowStage $candidate): bool => $this->comesAfter($candidate, $stage))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->first();
    }

    /**
     * Determine whether the candidate stage comes after the current stage in runtime order.
     */
    private function comesAfter(WorkflowStage $candidate, WorkflowStage $current): bool
    {
        if ((int) $candidate->sort_order === (int) $current->sort_order) {
            return (int) $candidate->id > (int) $current->id;
        }

        return (int) $candidate->sort_order > (int) $current->sort_order;
    }

    /**
     * Determine whether the candidate stage comes before the current stage in runtime order.
     */
    private function comesBefore(WorkflowStage $candidate, WorkflowStage $current): bool
    {
        if ((int) $candidate->sort_order === (int) $current->sort_order) {
            return (int) $candidate->id < (int) $current->id;
        }

        return (int) $candidate->sort_order < (int) $current->sort_order;
    }

    /**
     * Ensure the tenant has the shared default workflow stages for sales.
     */
    private function seedWorkflowStages(SalesOrder $salesOrder): void
    {
        $tenant = Tenant::query()->find($salesOrder->tenant_id);

        if ($tenant) {
            app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
        }
    }
}
