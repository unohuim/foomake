<?php

namespace App\Actions\Workflows;

use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Validation\ValidationException;

/**
 * Enforce stock workflow inventory-effect stage invariants.
 */
class EnforceWorkflowStageInventoryEffectInvariantAction
{
    /**
     * Normalize inventory-effect ownership and validate affected domains.
     */
    public function normalizeAndAssert(WorkflowStage $workflowStage, ?int $originalWorkflowDomainId = null): void
    {
        if (
            $workflowStage->is_inventory_effect_stage
            && $this->requiresInventoryEffectStage((int) $workflowStage->workflow_domain_id)
        ) {
            WorkflowStage::withoutGlobalScopes()
                ->where('tenant_id', $workflowStage->tenant_id)
                ->where('workflow_domain_id', $workflowStage->workflow_domain_id)
                ->where('id', '!=', $workflowStage->id)
                ->update([
                    'is_inventory_effect_stage' => false,
                ]);
        }

        $affectedDomainIds = array_values(array_unique(array_filter([
            $originalWorkflowDomainId,
            (int) $workflowStage->workflow_domain_id,
        ])));

        foreach ($affectedDomainIds as $workflowDomainId) {
            $this->assertDomain((int) $workflowStage->tenant_id, (int) $workflowDomainId);
        }
    }

    /**
     * Determine whether the given domain currently requires the invariant.
     */
    public function requiresInventoryEffectStage(int $workflowDomainId): bool
    {
        return WorkflowDomain::query()
            ->whereKey($workflowDomainId)
            ->whereIn('key', ['sales', 'purchasing', 'manufacturing', 'inventory'])
            ->exists();
    }

    /**
     * Assert the configured workflow stages satisfy the domain invariant.
     */
    public function assertDomain(int $tenantId, int $workflowDomainId): void
    {
        if (! $this->requiresInventoryEffectStage($workflowDomainId)) {
            return;
        }

        $activeStages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workflow_domain_id', $workflowDomainId)
            ->where('is_active', true)
            ->get();

        if ($activeStages->count() < 2) {
            throw ValidationException::withMessages([
                'workflow_domain_id' => ['This workflow must have at least two active stages.'],
            ]);
        }

        $inventoryEffectStageCount = $activeStages
            ->where('is_inventory_effect_stage', true)
            ->count();

        if ($inventoryEffectStageCount !== 1) {
            throw ValidationException::withMessages([
                'is_inventory_effect_stage' => ['This workflow must have exactly one active inventory-effect stage.'],
            ]);
        }
    }
}
