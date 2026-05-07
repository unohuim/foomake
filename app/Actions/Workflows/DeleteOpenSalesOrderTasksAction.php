<?php

namespace App\Actions\Workflows;

use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\WorkflowDomain;

/**
 * Delete open generated tasks when a sales order is cancelled.
 */
class DeleteOpenSalesOrderTasksAction
{
    /**
     * Delete only open tasks for the sales order.
     */
    public function execute(SalesOrder $salesOrder): void
    {
        $salesDomainId = WorkflowDomain::query()
            ->where('key', 'sales')
            ->value('id');

        if (! $salesDomainId) {
            return;
        }

        Task::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $salesDomainId)
            ->where('domain_record_id', $salesOrder->id)
            ->where('status', Task::STATUS_OPEN)
            ->delete();
    }
}

