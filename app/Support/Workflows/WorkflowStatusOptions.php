<?php

namespace App\Support\Workflows;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\WorkflowDomain;

/**
 * Provide approved status-complete labels for workflow stages.
 */
class WorkflowStatusOptions
{
    /**
     * Return approved status-complete labels for a workflow domain id.
     *
     * @return array<int, string>
     */
    public function forDomainId(int $workflowDomainId): array
    {
        $domainKey = WorkflowDomain::query()
            ->whereKey($workflowDomainId)
            ->value('key');

        return $this->forDomainKey((string) $domainKey);
    }

    /**
     * Return approved status-complete labels for a workflow domain key.
     *
     * @return array<int, string>
     */
    public function forDomainKey(string $domainKey): array
    {
        return match ($domainKey) {
            'sales' => array_values(array_unique([
                SalesOrder::STATUS_DRAFT,
                SalesOrder::STATUS_OPEN,
                'CREATED',
                ...SalesOrder::statuses(),
                'SHIPPED',
                'INVOICED',
            ])),
            'purchasing' => array_values(array_unique([
                PurchaseOrder::STATUS_DRAFT,
                'OPEN',
                ...PurchaseOrder::statuses(),
            ])),
            'manufacturing' => ['DRAFT', 'SCHEDULED', 'IN PROGRESS', 'COMPLETED', 'CANCELLED'],
            'inventory' => ['DRAFT', 'SCHEDULED', 'COMPLETED', 'CANCELLED'],
            default => [],
        };
    }

    /**
     * Build approved status-complete labels keyed by workflow domain id.
     *
     * @param iterable<int, WorkflowDomain> $domains
     * @return array<int, array<int, string>>
     */
    public function byDomainId(iterable $domains): array
    {
        $options = [];

        foreach ($domains as $domain) {
            $options[(int) $domain->id] = $this->forDomainKey((string) $domain->key);
        }

        return $options;
    }
}
