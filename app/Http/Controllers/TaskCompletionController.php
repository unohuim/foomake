<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\CompleteTaskAction;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Complete assigned workflow tasks.
 */
class TaskCompletionController extends Controller
{
    /**
     * Complete the provided task.
     */
    public function update(Request $request, Task $task, CompleteTaskAction $completeTaskAction): JsonResponse
    {
        $completedTask = $completeTaskAction->execute($task, $request->user());

        return response()->json([
            'data' => $this->taskData($completedTask, $request->user()?->id),
        ]);
    }

    /**
     * Build task payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function taskData(Task $task, ?int $viewerUserId): array
    {
        return [
            'id' => $task->id,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'title' => $task->title,
            'description' => $task->description,
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'is_completed' => $task->isCompleted(),
            'can_complete' => ! $task->isCompleted()
                && $viewerUserId !== null
                && (int) $task->assigned_to_user_id === (int) $viewerUserId,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'complete_url' => route('tasks.complete', $task),
        ];
    }
}

