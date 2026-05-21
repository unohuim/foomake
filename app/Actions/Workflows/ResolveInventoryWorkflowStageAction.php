<?php

namespace App\Actions\Workflows;

use App\Models\InventoryCount;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Resolve inventory workflow stages for Inventory Counts.
 */
class ResolveInventoryWorkflowStageAction
{
    /**
     * Resolve the fixed inventory workflow-domain id.
     *
     * @throws DomainException
     */
    public function inventoryDomainId(): int
    {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();

        $inventoryDomainId = WorkflowDomain::query()
            ->where('key', 'inventory')
            ->value('id');

        if (! $inventoryDomainId) {
            throw new DomainException('Inventory workflow domain is not configured.');
        }

        return (int) $inventoryDomainId;
    }

    /**
     * Resolve the current active workflow stage for the count.
     */
    public function currentStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        if ($inventoryCount->workflow_stage_id === null) {
            return null;
        }

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $inventoryCount->tenant_id)
            ->where('workflow_domain_id', $this->inventoryDomainId())
            ->whereKey($inventoryCount->workflow_stage_id)
            ->first();
    }

    /**
     * Return the active inventory workflow stages in runtime order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(InventoryCount $inventoryCount): Collection
    {
        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $inventoryCount->tenant_id)
            ->where('workflow_domain_id', $this->inventoryDomainId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve the first active workflow stage for a count.
     */
    public function firstActiveStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        return $this->activeStages($inventoryCount)->first();
    }

    /**
     * Resolve the next active workflow stage after the current one.
     */
    public function nextActiveStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        $currentStage = $this->currentStage($inventoryCount);

        if (! $currentStage) {
            return null;
        }

        return $this->activeStages($inventoryCount)
            ->filter(fn (WorkflowStage $stage): bool => $this->comesAfter($stage, $currentStage))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->first();
    }

    /**
     * Resolve the previous active workflow stage before the current one.
     */
    public function previousActiveStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        $currentStage = $this->currentStage($inventoryCount);

        if (! $currentStage) {
            return null;
        }

        return $this->activeStages($inventoryCount)
            ->filter(fn (WorkflowStage $stage): bool => $this->comesBefore($stage, $currentStage))
            ->sortBy([
                ['sort_order', 'desc'],
                ['id', 'desc'],
            ])
            ->first();
    }

    /**
     * Resolve the active inventory-effect stage for the tenant workflow.
     */
    public function inventoryEffectStage(InventoryCount $inventoryCount): ?WorkflowStage
    {
        return $this->activeStages($inventoryCount)
            ->firstWhere('is_inventory_effect_stage', true);
    }

    /**
     * Determine whether the candidate stage comes after the current stage.
     */
    private function comesAfter(WorkflowStage $candidate, WorkflowStage $current): bool
    {
        if ((int) $candidate->sort_order === (int) $current->sort_order) {
            return (int) $candidate->id > (int) $current->id;
        }

        return (int) $candidate->sort_order > (int) $current->sort_order;
    }

    /**
     * Determine whether the candidate stage comes before the current stage.
     */
    private function comesBefore(WorkflowStage $candidate, WorkflowStage $current): bool
    {
        if ((int) $candidate->sort_order === (int) $current->sort_order) {
            return (int) $candidate->id < (int) $current->id;
        }

        return (int) $candidate->sort_order < (int) $current->sort_order;
    }
}
