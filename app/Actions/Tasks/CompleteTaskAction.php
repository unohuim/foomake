<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
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
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $lockedTask->assigned_to_user_id !== (int) $user->id
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
}

