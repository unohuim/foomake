<?php

namespace App\Actions\Workflows;

use App\Models\Task;
use App\Models\WorkflowStage;
use DomainException;

/**
 * Assert that the current workflow stage has no incomplete required tasks.
 */
class AssertWorkflowStageTasksCompletedAction
{
    /**
     * Assert that the provided workflow stage has no open tasks for the domain record.
     *
     * @throws DomainException
     */
    public function execute(
        int $tenantId,
        int $domainRecordId,
        ?WorkflowStage $stage,
        string $failureMessage
    ): void {
        if (! $stage) {
            return;
        }

        $hasOpenTasks = Task::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('source', Task::SOURCE_GENERATED)
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('domain_record_id', $domainRecordId)
            ->where('workflow_stage_id', $stage->id)
            ->where('status', Task::STATUS_OPEN)
            ->exists();

        if ($hasOpenTasks) {
            throw new DomainException($failureMessage);
        }
    }
}
