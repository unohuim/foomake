<?php

namespace App\Actions\Workflows;

use App\Models\SalesOrder;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTaskTemplate;
use DomainException;

/**
 * Generate workflow tasks for a sales order entering an operational stage.
 */
class GenerateSalesOrderWorkflowTasksAction
{
    /**
     * Generate idempotent tasks for the provided stage key.
     *
     * @throws DomainException
     */
    public function execute(SalesOrder $salesOrder, string $stageKey): void
    {
        $stage = app(ResolveSalesWorkflowStageAction::class)->execute($salesOrder, $stageKey);

        $templates = WorkflowTaskTemplate::withoutGlobalScopes()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->where('workflow_domain_id', $stage->workflow_domain_id)
            ->where('workflow_stage_id', $stage->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $existing = Task::withoutGlobalScopes()
                ->where('tenant_id', $salesOrder->tenant_id)
                ->where('workflow_domain_id', $stage->workflow_domain_id)
                ->where('domain_record_id', $salesOrder->id)
                ->where('workflow_stage_id', $stage->id)
                ->where('workflow_task_template_id', $template->id)
                ->exists();

            if ($existing) {
                continue;
            }

            $assigneeId = $template->default_assignee_user_id ?? $this->firstTenantUserId($salesOrder);

            Task::withoutGlobalScopes()->create([
                'tenant_id' => $salesOrder->tenant_id,
                'workflow_domain_id' => $stage->workflow_domain_id,
                'domain_record_id' => $salesOrder->id,
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
     * Resolve the fallback assignee for generated tasks.
     *
     * @throws DomainException
     */
    private function firstTenantUserId(SalesOrder $salesOrder): int
    {
        $userId = User::query()
            ->where('tenant_id', $salesOrder->tenant_id)
            ->orderBy('id')
            ->value('id');

        if (! $userId) {
            throw new DomainException('Workflow tasks require an assigned user.');
        }

        return (int) $userId;
    }
}

