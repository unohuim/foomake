<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Render the workflow-management page.
 */
class WorkflowController extends Controller
{
    /**
     * Display the workflow-management page.
     */
    public function index(Request $request): View
    {
        Gate::authorize('workflow-manage');

        $showInactive = $request->boolean('show_inactive');

        $domains = WorkflowDomain::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stagesQuery = WorkflowStage::query()
            ->with('workflowDomain')
            ->orderBy('workflow_domain_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        $templatesQuery = WorkflowTaskTemplate::query()
            ->with(['workflowDomain', 'workflowStage', 'defaultAssignee'])
            ->orderBy('workflow_stage_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $showInactive) {
            $stagesQuery->where('is_active', true);
            $templatesQuery->where('is_active', true);
        }

        $stages = $stagesQuery->get();
        $templates = $templatesQuery->get();
        $users = User::query()->orderBy('id')->get();

        $payload = [
            'domains' => $domains->map(fn (WorkflowDomain $domain): array => $this->domainData($domain))->values()->all(),
            'stages' => $stages->map(fn (WorkflowStage $stage): array => $this->stageData($stage))->values()->all(),
            'taskTemplates' => $templates->map(fn (WorkflowTaskTemplate $template): array => $this->taskTemplateData($template))->values()->all(),
            'users' => $users->map(fn (User $user): array => $this->userData($user))->values()->all(),
            'showInactive' => $showInactive,
            'stageStoreUrl' => route('admin.workflows.stages.store'),
            'stageUpdateUrlBase' => url('/admin/workflows/stages'),
            'stageReorderUrl' => route('admin.workflows.stages.reorder'),
            'taskTemplateStoreUrl' => route('admin.workflows.task-templates.store'),
            'taskTemplateUpdateUrlBase' => url('/admin/workflows/task-templates'),
            'taskTemplateReorderUrl' => route('admin.workflows.task-templates.reorder'),
            'csrfToken' => csrf_token(),
        ];

        return view('admin.workflows.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Build workflow-domain payload data.
     *
     * @return array<string, int|string>
     */
    private function domainData(WorkflowDomain $domain): array
    {
        return [
            'id' => $domain->id,
            'key' => $domain->key,
            'name' => $domain->name,
            'sort_order' => $domain->sort_order,
        ];
    }

    /**
     * Build workflow-stage payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function stageData(WorkflowStage $stage): array
    {
        return [
            'id' => $stage->id,
            'workflow_domain_id' => $stage->workflow_domain_id,
            'workflow_domain_key' => $stage->workflowDomain?->key,
            'key' => $stage->key,
            'name' => $stage->name,
            'description' => $stage->description,
            'sort_order' => $stage->sort_order,
            'is_active' => $stage->is_active,
            'is_seeded_sales_stage' => in_array($stage->key, ['packing', 'packed', 'shipping'], true)
                && $stage->workflowDomain?->key === 'sales',
        ];
    }

    /**
     * Build workflow-task-template payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function taskTemplateData(WorkflowTaskTemplate $template): array
    {
        return [
            'id' => $template->id,
            'workflow_domain_id' => $template->workflow_domain_id,
            'workflow_domain_key' => $template->workflowDomain?->key,
            'workflow_stage_id' => $template->workflow_stage_id,
            'workflow_stage_key' => $template->workflowStage?->key,
            'title' => $template->title,
            'description' => $template->description,
            'sort_order' => $template->sort_order,
            'is_active' => $template->is_active,
            'default_assignee_user_id' => $template->default_assignee_user_id,
            'default_assignee_name' => $template->defaultAssignee?->name,
        ];
    }

    /**
     * Build user payload data for assignee selection.
     *
     * @return array<string, int|string>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
