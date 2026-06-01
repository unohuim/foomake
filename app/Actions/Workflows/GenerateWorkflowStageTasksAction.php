<?php

namespace App\Actions\Workflows;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use DomainException;

/**
 * Generate workflow tasks when a domain record enters a workflow stage.
 */
class GenerateWorkflowStageTasksAction
{
    /**
     * Generate idempotent tasks for the provided workflow stage.
     *
     * @throws DomainException
     */
    public function execute(
        int $tenantId,
        int $domainRecordId,
        WorkflowStage $stage,
        ?int $preferredAssigneeUserId = null
    ): void {
        $templates = WorkflowTaskTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('workflow_stage_id', $stage->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $existing = Task::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('workflow_domain_id', $stage->workflow_domain_id)
                ->where('domain_record_id', $domainRecordId)
                ->where('workflow_stage_id', $stage->id)
                ->where('workflow_task_template_id', $template->id)
                ->exists();

            if ($existing) {
                continue;
            }

            $assigneeId = $this->resolveAssigneeId(
                $tenantId,
                $stage,
                $preferredAssigneeUserId ?? $template->default_assignee_user_id
            );

            Task::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'workflow_domain_id' => $stage->workflow_domain_id,
                'domain_record_id' => $domainRecordId,
                'workflow_stage_id' => $stage->id,
                'workflow_task_template_id' => $template->id,
                'assigned_to_user_id' => $assigneeId,
                'title' => $template->title,
                'description' => $template->description,
                'sort_order' => $template->sort_order,
                'status' => Task::STATUS_OPEN,
                'completed_at' => null,
                'completed_by_user_id' => null,
            ]);
        }
    }

    /**
     * Resolve a preferred eligible assignee or fall back to the first eligible tenant user.
     *
     * @throws DomainException
     */
    private function resolveAssigneeId(int $tenantId, WorkflowStage $stage, ?int $preferredAssigneeUserId): int
    {
        $stage->loadMissing('workflowDomain');
        $domainKey = $stage->workflowDomain?->key;
        $assignmentPermissions = app(WorkflowAssignmentPermissions::class);

        if ($preferredAssigneeUserId !== null && $domainKey !== null) {
            $preferredUser = User::query()
                ->where('tenant_id', $tenantId)
                ->find($preferredAssigneeUserId);

            if (
                $preferredUser !== null
                && $assignmentPermissions->userCanBeAssignedToDomain($preferredUser, $domainKey)
            ) {
                return (int) $preferredUser->id;
            }
        }

        return $this->firstEligibleTenantUserId($tenantId, (string) $domainKey);
    }

    /**
     * Resolve the fallback assignee for generated workflow tasks.
     *
     * @throws DomainException
     */
    private function firstEligibleTenantUserId(int $tenantId, string $domainKey): int
    {
        $userId = app(WorkflowAssignmentPermissions::class)
            ->eligibleUsersQuery($tenantId, $domainKey)
            ->orderBy('id')
            ->value('id');

        if (! $userId) {
            throw new DomainException('Workflow tasks require an eligible assigned user.');
        }

        return (int) $userId;
    }
}
