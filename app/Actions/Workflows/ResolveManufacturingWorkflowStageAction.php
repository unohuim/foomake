<?php

namespace App\Actions\Workflows;

use App\Models\MakeOrder;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Resolve manufacturing workflow stages for Make Orders.
 */
class ResolveManufacturingWorkflowStageAction
{
    /**
     * Resolve the fixed manufacturing workflow-domain id.
     *
     * @throws DomainException
     */
    public function manufacturingDomainId(): int
    {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();

        $manufacturingDomainId = WorkflowDomain::query()
            ->where('key', 'manufacturing')
            ->value('id');

        if (! $manufacturingDomainId) {
            throw new DomainException('Manufacturing workflow domain is not configured.');
        }

        return (int) $manufacturingDomainId;
    }

    /**
     * Resolve the current active workflow stage for the Make Order.
     */
    public function currentStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        if ($makeOrder->workflow_stage_id === null) {
            return null;
        }

        return WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $makeOrder->tenant_id)
            ->where('workflow_domain_id', $this->manufacturingDomainId())
            ->whereKey($makeOrder->workflow_stage_id)
            ->first();
    }

    /**
     * Return the active manufacturing workflow stages in runtime order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(MakeOrder $makeOrder): Collection
    {
        $stages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $makeOrder->tenant_id)
            ->where('workflow_domain_id', $this->manufacturingDomainId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

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
     * Resolve the first active workflow stage for the Make Order.
     */
    public function firstActiveStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        return $this->activeStages($makeOrder)->first();
    }

    /**
     * Resolve the next active workflow stage after the current one.
     */
    public function nextActiveStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        $currentStage = $this->currentStage($makeOrder);

        if (! $currentStage) {
            return null;
        }

        return $this->activeStages($makeOrder)
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
    public function previousActiveStage(MakeOrder $makeOrder): ?WorkflowStage
    {
        $currentStage = $this->currentStage($makeOrder);

        if (! $currentStage) {
            return null;
        }

        return $this->activeStages($makeOrder)
            ->filter(fn (WorkflowStage $stage): bool => $this->comesBefore($stage, $currentStage))
            ->sortBy([
                ['sort_order', 'desc'],
                ['id', 'desc'],
            ])
            ->first();
    }

    /**
     * Return the valid next workflow-stage targets for the Make Order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function availableTransitions(MakeOrder $makeOrder): Collection
    {
        $currentStage = $this->currentStage($makeOrder);

        if (! $currentStage) {
            $firstStage = $this->firstActiveStage($makeOrder);

            return $firstStage ? collect([$firstStage]) : collect();
        }

        $available = [];
        $previousStage = $this->previousActiveStage($makeOrder);
        $nextStage = $this->nextActiveStage($makeOrder);

        if ($nextStage) {
            $available[] = $nextStage;
        }

        return collect($available)->unique('id')->values();
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
