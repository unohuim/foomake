<?php

namespace App\Actions\Workflows;

use App\Models\SalesOrder;
use App\Models\Task;
use DomainException;

/**
 * Ensure the current operational sales-order stage has no open tasks.
 */
class AssertSalesOrderStageTasksCompletedAction
{
    /**
     * Assert that the sales order has no open tasks for its current stage.
     *
     * @throws DomainException
     */
    public function execute(SalesOrder $salesOrder): void
    {
        $stage = app(ResolveSalesWorkflowStageAction::class)->currentStageForStatus($salesOrder);

        if (! $stage) {
            return;
        }

        $hasOpenTasks = Task::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('domain_record_id', $salesOrder->id)
            ->where('workflow_stage_id', $stage->id)
            ->where('status', Task::STATUS_OPEN)
            ->exists();

        if ($hasOpenTasks) {
            throw new DomainException('Complete all tasks for this stage before moving the sales order forward.');
        }
    }
}

