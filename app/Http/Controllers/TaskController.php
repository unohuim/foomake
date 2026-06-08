<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\InventoryCount;
use App\Models\MakeOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Create manually assigned tenant tasks.
 */
class TaskController extends Controller
{
    /**
     * Store a manually created task.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $tenantId = (int) $request->user()->tenant_id;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assigned_to_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'workflow_domain_id' => [
                'nullable',
                'integer',
                Rule::exists('workflow_domains', 'id'),
            ],
            'domain_record_id' => ['nullable', 'integer', 'min:1'],
            'workflow_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('workflow_stages', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $workflowDomainId = $validated['workflow_domain_id'] ?? null;
        $domainRecordId = $validated['domain_record_id'] ?? null;
        $workflowStageId = $validated['workflow_stage_id'] ?? null;

        if ($workflowDomainId !== null || $domainRecordId !== null || $workflowStageId !== null) {
            $request->validate([
                'workflow_domain_id' => ['required', 'integer'],
                'domain_record_id' => ['required', 'integer', 'min:1'],
            ]);

            $this->assertWorkflowContextBelongsTogether(
                $tenantId,
                (int) $workflowDomainId,
                $workflowStageId === null ? null : (int) $workflowStageId,
                (int) $domainRecordId
            );
        }

        $sortOrder = Task::query()
            ->where('tenant_id', $tenantId)
            ->when($workflowDomainId !== null, fn ($query) => $query->where('workflow_domain_id', $workflowDomainId))
            ->when($workflowDomainId === null, fn ($query) => $query->whereNull('workflow_domain_id'))
            ->when($domainRecordId !== null, fn ($query) => $query->where('domain_record_id', $domainRecordId))
            ->when($domainRecordId === null, fn ($query) => $query->whereNull('domain_record_id'))
            ->when($workflowStageId !== null, fn ($query) => $query->where('workflow_stage_id', $workflowStageId))
            ->when($workflowStageId === null, fn ($query) => $query->whereNull('workflow_stage_id'))
            ->max('sort_order');

        $task = Task::query()->create([
            'tenant_id' => $tenantId,
            'source' => Task::SOURCE_MANUAL,
            'workflow_domain_id' => $workflowDomainId === null ? null : (int) $workflowDomainId,
            'domain_record_id' => $domainRecordId === null ? null : (int) $domainRecordId,
            'workflow_stage_id' => $workflowStageId === null ? null : (int) $workflowStageId,
            'workflow_task_template_id' => null,
            'assigned_to_user_id' => (int) $validated['assigned_to_user_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => isset($validated['due_date']) && $validated['due_date'] !== null
                ? Carbon::parse($validated['due_date'])->toDateString()
                : null,
            'sort_order' => ((int) ($sortOrder ?? 0)) + 1,
            'status' => Task::STATUS_OPEN,
            'completed_at' => null,
            'completed_by_user_id' => null,
        ])->fresh(['assignedTo', 'completedBy']);

        if (! $request->expectsJson()) {
            return redirect()->back();
        }

        return response()->json([
            'data' => $this->taskData($task, $request->user()?->id),
        ], 201);
    }

    /**
     * Assert that the selected workflow stage belongs to the selected workflow domain.
     */
    private function assertWorkflowContextBelongsTogether(
        int $tenantId,
        int $workflowDomainId,
        ?int $workflowStageId,
        int $domainRecordId
    ): void {
        $workflowDomain = WorkflowDomain::query()->findOrFail($workflowDomainId);

        if ($workflowStageId !== null) {
            WorkflowStage::query()
                ->where('tenant_id', $tenantId)
                ->where('workflow_domain_id', $workflowDomainId)
                ->whereKey($workflowStageId)
                ->firstOrFail();
        }

        $this->assertWorkflowDomainRecordExists($tenantId, (string) $workflowDomain->key, $domainRecordId);
    }

    /**
     * Assert that the workflow-context domain record belongs to the tenant.
     */
    private function assertWorkflowDomainRecordExists(int $tenantId, string $domainKey, int $domainRecordId): void
    {
        match ($domainKey) {
            'inventory' => InventoryCount::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($domainRecordId)
                ->firstOrFail(),
            'manufacturing' => MakeOrder::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($domainRecordId)
                ->firstOrFail(),
            'purchasing' => PurchaseOrder::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($domainRecordId)
                ->firstOrFail(),
            'sales' => SalesOrder::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($domainRecordId)
                ->firstOrFail(),
            default => abort(422, 'Unsupported workflow domain.'),
        };
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
            'source' => $task->source,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->format('Y-m-d'),
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
