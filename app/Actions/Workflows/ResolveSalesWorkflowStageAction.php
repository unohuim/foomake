<?php

namespace App\Actions\Workflows;

use App\Models\SalesOrder;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use DomainException;

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
        if ($this->isSystemStatus($salesOrder->status)) {
            return null;
        }

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $this->salesDomainId())
            ->where('key', $this->stageKeyForStatus($salesOrder->status))
            ->first();
    }

    /**
     * Resolve an active workflow stage for the provided target status.
     */
    public function activeStageForStatus(SalesOrder $salesOrder, string $status): ?WorkflowStage
    {
        if ($this->isSystemStatus($status)) {
            return null;
        }

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $this->salesDomainId())
            ->where('key', $this->stageKeyForStatus($status))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Return the active sales workflow stages in runtime order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(SalesOrder $salesOrder): Collection
    {
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
        return $this->activeStages($salesOrder)->first();
    }

    /**
     * Resolve the next active stage after the sales order's current status.
     */
    public function nextActiveStage(SalesOrder $salesOrder): ?WorkflowStage
    {
        $activeStages = $this->activeStages($salesOrder);

        if ($salesOrder->status === SalesOrder::STATUS_OPEN) {
            return $activeStages->first();
        }

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
     * Convert a workflow stage key into the persisted sales-order status value.
     */
    public function statusForStage(WorkflowStage $stage): string
    {
        return Str::upper($stage->key);
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
        return Str::lower($status);
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
}
