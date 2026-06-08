<?php

namespace App\Actions\Workflows;

use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Determine assignment-scoped visibility for workflow-enabled resources.
 */
class CanViewAssignedWorkflowResourceAction
{
    /**
     * Determine whether a user may view a tenant workflow resource through assignment.
     */
    public function execute(
        User $user,
        Model $resource,
        string $workflowDomainKey,
        ?int $responsibleUserId = null
    ): bool {
        $tenantId = $resource->getAttribute('tenant_id');

        if ($tenantId === null || (int) $tenantId !== (int) $user->tenant_id) {
            return false;
        }

        if (
            $responsibleUserId !== null
            && (int) $responsibleUserId === (int) $user->id
            && app(WorkflowAssignmentPermissions::class)->userCanOwnWorkflowDomain($user, $workflowDomainKey)
        ) {
            return true;
        }

        $workflowDomainId = WorkflowDomain::query()
            ->where('key', $workflowDomainKey)
            ->value('id');

        if ($workflowDomainId === null) {
            return false;
        }

        return Task::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('workflow_domain_id', $workflowDomainId)
            ->where('domain_record_id', $resource->getKey())
            ->where('assigned_to_user_id', $user->id)
            ->exists();
    }
}
