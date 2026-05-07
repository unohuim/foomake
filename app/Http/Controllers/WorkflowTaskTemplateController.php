<?php

namespace App\Http\Controllers;

use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Handle workflow task-template CRUD and ordering.
 */
class WorkflowTaskTemplateController extends Controller
{
    /**
     * Store a new workflow task template.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('workflow-manage');

        $validated = $this->validateTemplate($request);

        $template = WorkflowTaskTemplate::withoutGlobalScopes()->create([
            'tenant_id' => (int) $request->user()->tenant_id,
            'workflow_domain_id' => (int) $validated['workflow_domain_id'],
            'workflow_stage_id' => (int) $validated['workflow_stage_id'],
            'title' => (string) $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => true,
            'default_assignee_user_id' => $validated['default_assignee_user_id'] ?? null,
        ]);

        $template->load(['workflowDomain', 'workflowStage', 'defaultAssignee']);

        return response()->json([
            'data' => $this->templateData($template),
        ], 201);
    }

    /**
     * Update an existing workflow task template.
     */
    public function update(Request $request, WorkflowTaskTemplate $workflowTaskTemplate): JsonResponse
    {
        Gate::authorize('workflow-manage');

        $validated = $this->validateTemplate($request, $workflowTaskTemplate);

        $workflowTaskTemplate->update([
            'workflow_domain_id' => (int) $validated['workflow_domain_id'],
            'workflow_stage_id' => (int) $validated['workflow_stage_id'],
            'title' => (string) $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) $validated['is_active'],
            'default_assignee_user_id' => $validated['default_assignee_user_id'] ?? null,
        ]);

        $workflowTaskTemplate->load(['workflowDomain', 'workflowStage', 'defaultAssignee']);

        return response()->json([
            'data' => $this->templateData($workflowTaskTemplate),
        ]);
    }

    /**
     * Reorder task templates within a stage.
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('workflow-manage');

        $validated = $request->validate([
            'workflow_stage_id' => ['required', 'integer', Rule::exists('workflow_stages', 'id')],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $templates = WorkflowTaskTemplate::query()
            ->where('workflow_stage_id', (int) $validated['workflow_stage_id'])
            ->whereIn('id', $validated['ordered_ids'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($templates->count() !== count($validated['ordered_ids'])) {
            return response()->json([
                'message' => 'Workflow task template reorder payload is invalid.',
                'errors' => [
                    'ordered_ids' => ['Workflow task template reorder payload is invalid.'],
                ],
            ], 422);
        }

        foreach (array_values($validated['ordered_ids']) as $index => $templateId) {
            $template = $templates->get((int) $templateId);

            $template?->update([
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        return response()->json([
            'message' => 'Reordered.',
        ]);
    }

    /**
     * Validate a workflow task-template payload.
     *
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request, ?WorkflowTaskTemplate $template = null): array
    {
        $tenantId = (int) $request->user()->tenant_id;

        $validated = $request->validate([
            'workflow_domain_id' => ['required', 'integer', Rule::exists('workflow_domains', 'id')],
            'workflow_stage_id' => [
                'required',
                'integer',
                Rule::exists('workflow_stages', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('workflow_domain_id', (int) $request->input('workflow_domain_id'))),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'default_assignee_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'is_active' => [$template ? 'required' : 'sometimes', 'boolean'],
        ]);

        $stage = WorkflowStage::query()->findOrFail((int) $validated['workflow_stage_id']);

        if ((int) $stage->workflow_domain_id !== (int) $validated['workflow_domain_id']) {
            return tap([], function (): void {
                abort(response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'workflow_stage_id' => ['The selected workflow stage is invalid for the domain.'],
                    ],
                ], 422));
            });
        }

        return $validated;
    }

    /**
     * Build task-template response data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function templateData(WorkflowTaskTemplate $template): array
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
}
