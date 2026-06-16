<?php

namespace App\Support\Workflows;

use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use DomainException;

/**
 * Load workflow definitions once per request without mutating workflow defaults.
 */
class WorkflowDefinitionRepository
{
    /**
     * @var array<string, WorkflowDefinition>
     */
    private array $definitions = [];

    /**
     * Return a workflow definition for one tenant and domain.
     *
     * @throws DomainException
     */
    public function for(int $tenantId, string $domainKey): WorkflowDefinition
    {
        $domainKey = trim($domainKey);
        $cacheKey = $tenantId . ':' . $domainKey;

        if (isset($this->definitions[$cacheKey])) {
            return $this->definitions[$cacheKey];
        }

        $domainId = WorkflowDomain::query()
            ->where('key', $domainKey)
            ->value('id');

        if (! $domainId) {
            throw new DomainException('Workflow domain is not configured.');
        }

        $stages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workflow_domain_id', $domainId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->definitions[$cacheKey] = new WorkflowDefinition(
            $tenantId,
            (int) $domainId,
            $domainKey,
            $stages
        );

        return $this->definitions[$cacheKey];
    }
}
