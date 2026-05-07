<?php

namespace App\Http\Controllers;

use App\Models\WorkflowStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Handle workflow-stage CRUD and ordering.
 */
class WorkflowStageController extends Controller
{
    /**
     * Store a new workflow stage.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('workflow-manage');

        $validated = $this->validateStage($request);

        $stage = WorkflowStage::withoutGlobalScopes()->create([
            'tenant_id' => (int) $request->user()->tenant_id,
            'workflow_domain_id' => (int) $validated['workflow_domain_id'],
            'key' => (string) $validated['key'],
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => true,
        ]);

        $stage->load('workflowDomain');

        return response()->json([
            'data' => $this->stageData($stage),
        ], 201);
    }

    /**
     * Update an existing workflow stage.
     */
    public function update(Request $request, WorkflowStage $workflowStage): JsonResponse
    {
        Gate::authorize('workflow-manage');

        $validated = $this->validateStage($request, $workflowStage);

        $workflowStage->update([
            'workflow_domain_id' => (int) $validated['workflow_domain_id'],
            'key' => (string) $validated['key'],
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        $workflowStage->load('workflowDomain');

        return response()->json([
            'data' => $this->stageData($workflowStage),
        ]);
    }

    /**
     * Reorder workflow stages within a domain.
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('workflow-manage');

        $validated = $request->validate([
            'workflow_domain_id' => ['required', 'integer', Rule::exists('workflow_domains', 'id')],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $stages = WorkflowStage::query()
            ->where('workflow_domain_id', (int) $validated['workflow_domain_id'])
            ->whereIn('id', $validated['ordered_ids'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($stages->count() !== count($validated['ordered_ids'])) {
            return response()->json([
                'message' => 'Workflow stage reorder payload is invalid.',
                'errors' => [
                    'ordered_ids' => ['Workflow stage reorder payload is invalid.'],
                ],
            ], 422);
        }

        foreach (array_values($validated['ordered_ids']) as $index => $stageId) {
            $stage = $stages->get((int) $stageId);

            $stage?->update([
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        return response()->json([
            'message' => 'Reordered.',
        ]);
    }

    /**
     * Validate a workflow stage payload.
     *
     * @return array<string, mixed>
     */
    private function validateStage(Request $request, ?WorkflowStage $workflowStage = null): array
    {
        $tenantId = (int) $request->user()->tenant_id;

        return $request->validate([
            'workflow_domain_id' => ['required', 'integer', Rule::exists('workflow_domains', 'id')],
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workflow_stages', 'key')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('workflow_domain_id', (int) $request->input('workflow_domain_id')))
                    ->ignore($workflowStage?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => [$workflowStage ? 'required' : 'sometimes', 'boolean'],
        ]);
    }

    /**
     * Build stage response data.
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
        ];
    }
}
