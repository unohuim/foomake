<?php

namespace App\Actions\Tasks;

use App\Models\InventoryCount;
use App\Models\Task;
use App\Models\User;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use Illuminate\Support\Facades\DB;

/**
 * Complete an assigned task.
 */
class CompleteTaskAction
{
    /**
     * Complete a task for the assigned user.
     */
    public function execute(Task $task, User $user): Task
    {
        return DB::transaction(function () use ($task, $user): Task {
            $lockedTask = Task::query()
                ->with('workflowDomain')
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $this->userCanCompleteTask($lockedTask, $user)
                && ! $user->hasRole('super-admin')
            ) {
                abort(403);
            }

            if ($lockedTask->isCompleted()) {
                return $lockedTask->fresh(['assignedTo', 'completedBy']);
            }

            $lockedTask->forceFill([
                'status' => Task::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by_user_id' => $user->id,
            ])->save();

            return $lockedTask->fresh(['assignedTo', 'completedBy']);
        });
    }

    /**
     * Determine whether the user can complete the assigned operational task.
     */
    private function userCanCompleteTask(Task $task, User $user): bool
    {
        if ((int) $task->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        $domainKey = $task->workflowDomain?->key;
        $isEligibleForDomainTask = $domainKey === null
            || app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain($user, (string) $domainKey);

        if ((int) $task->assigned_to_user_id === (int) $user->id && $isEligibleForDomainTask) {
            return true;
        }

        if ($domainKey !== 'inventory' || ! $isEligibleForDomainTask) {
            return false;
        }

        return InventoryCount::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $task->tenant_id)
            ->whereKey($task->domain_record_id)
            ->where('assigned_to_user_id', $user->id)
            ->exists();
    }
}
