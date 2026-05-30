<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\EnforceWorkflowStageInventoryEffectInvariantAction;
use App\Models\WorkflowStage;
use App\Support\Workflows\WorkflowStatusOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

        $request->merge([
            'key' => $this->generatedStageKey((string) $request->input('name')),
        ]);

        $validated = $this->validateStage($request);

        $stage = DB::transaction(function () use ($request, $validated): WorkflowStage {
            $stage = WorkflowStage::withoutGlobalScopes()->create([
                'tenant_id' => (int) $request->user()->tenant_id,
                'workflow_domain_id' => (int) $validated['workflow_domain_id'],
                'key' => (string) $validated['key'],
                'name' => (string) $validated['name'],
                'action_verb' => (string) $validated['action_verb'],
                'status_complete_label' => (string) ($validated['status_complete_label'] ?? $validated['action_verb']),
                'completion_mode' => (string) ($validated['completion_mode'] ?? 'manual'),
                'description' => $validated['description'] ?? null,
                'sort_order' => (int) $validated['sort_order'],
                'is_active' => true,
                'is_core' => false,
                'is_inventory_effect_stage' => (bool) ($validated['is_inventory_effect_stage'] ?? false),
            ]);

            app(EnforceWorkflowStageInventoryEffectInvariantAction::class)
                ->normalizeAndAssert($stage);

            return $stage->fresh();
        });

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

        $request->merge([
            'key' => $workflowStage->key,
        ]);

        $validated = $this->validateStage($request, $workflowStage);
        $coreError = $this->coreStageUpdateError($workflowStage, $validated);

        if ($coreError !== null) {
            return $coreError;
        }

        $originalWorkflowDomainId = (int) $workflowStage->workflow_domain_id;

        $workflowStage = DB::transaction(function () use (
            $workflowStage,
            $validated,
            $originalWorkflowDomainId
        ): WorkflowStage {
            $workflowStage->update([
                'workflow_domain_id' => (int) $validated['workflow_domain_id'],
                'key' => (string) $validated['key'],
                'name' => (string) $validated['name'],
                'action_verb' => (string) $validated['action_verb'],
                'status_complete_label' => (string) ($validated['status_complete_label'] ?? $validated['action_verb']),
                'completion_mode' => (string) ($validated['completion_mode'] ?? 'manual'),
                'description' => $validated['description'] ?? null,
                'sort_order' => (int) $validated['sort_order'],
                'is_active' => (bool) $validated['is_active'],
                'is_inventory_effect_stage' => (bool) ($validated['is_inventory_effect_stage'] ?? false),
            ]);

            $freshStage = $workflowStage->fresh();

            app(EnforceWorkflowStageInventoryEffectInvariantAction::class)
                ->normalizeAndAssert($freshStage, $originalWorkflowDomainId);

            return $freshStage->fresh();
        });

        $workflowStage->load('workflowDomain');

        return response()->json([
            'data' => $this->stageData($workflowStage),
        ]);
    }

    /**
     * Delete a workflow stage.
     */
    public function destroy(WorkflowStage $workflowStage): JsonResponse
    {
        Gate::authorize('workflow-manage');

        if ($workflowStage->is_core) {
            return $this->coreStageError('stage', 'Core workflow stages cannot be deleted.');
        }

        $workflowStage->delete();

        return response()->json([
            'message' => 'Workflow stage deleted.',
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

        if ($stages->contains(fn (WorkflowStage $stage): bool => $stage->is_core)) {
            return $this->coreStageError('ordered_ids', 'Core workflow stages cannot be reordered.');
        }

        DB::transaction(function () use ($validated, $stages, $request): void {
            foreach (array_values($validated['ordered_ids']) as $index => $stageId) {
                $stage = $stages->get((int) $stageId);

                $stage?->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }

            app(EnforceWorkflowStageInventoryEffectInvariantAction::class)
                ->assertDomain((int) $request->user()->tenant_id, (int) $validated['workflow_domain_id']);
        });

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
        $workflowDomainId = (int) ($request->input('workflow_domain_id') ?? $workflowStage?->workflow_domain_id);
        $statusLabels = app(WorkflowStatusOptions::class)->forDomainId($workflowDomainId);

        if ($workflowStage !== null && ! $request->has('status_complete_label')) {
            $request->merge([
                'status_complete_label' => $workflowStage->status_complete_label,
            ]);
        }

        if ($workflowStage !== null && ! $request->has('completion_mode')) {
            $request->merge([
                'completion_mode' => $workflowStage->completion_mode,
            ]);
        }

        return $request->validate([
            'workflow_domain_id' => ['required', 'integer', Rule::exists('workflow_domains', 'id')],
            'key' => [
                'bail',
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
            'action_verb' => ['required', 'string', 'max:255'],
            'status_complete_label' => ['required', 'string', Rule::in($statusLabels)],
            'completion_mode' => ['nullable', 'string', Rule::in(['manual', 'automatic'])],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => [$workflowStage ? 'required' : 'sometimes', 'boolean'],
            'is_inventory_effect_stage' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Generate the stable workflow stage key from a stage name.
     */
    private function generatedStageKey(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * Build a core-stage lock error when immutable fields are changed.
     *
     * @param array<string, mixed> $validated
     */
    private function coreStageUpdateError(WorkflowStage $stage, array $validated): ?JsonResponse
    {
        if (! $stage->is_core) {
            return null;
        }

        $lockedFields = [
            'workflow_domain_id',
            'key',
            'name',
            'action_verb',
            'status_complete_label',
            'completion_mode',
            'sort_order',
        ];

        foreach ($lockedFields as $field) {
            $incoming = $validated[$field] ?? null;
            $current = $stage->getAttribute($field);

            if ((string) $incoming !== (string) $current) {
                return $this->coreStageError($field, 'Core workflow stage fields cannot be changed.');
            }
        }

        if (! (bool) $validated['is_active']) {
            return $this->coreStageError('is_active', 'Core workflow stages cannot be deactivated.');
        }

        return null;
    }

    /**
     * Build a validation-style response for core-stage lock failures.
     */
    private function coreStageError(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => [
                $field => [$message],
            ],
        ], 422);
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
            'action_verb' => $stage->action_verb,
            'status_complete_label' => $stage->status_complete_label,
            'completion_mode' => $stage->completion_mode,
            'description' => $stage->description,
            'sort_order' => $stage->sort_order,
            'is_active' => $stage->is_active,
            'is_core' => $stage->is_core,
            'is_inventory_effect_stage' => $stage->is_inventory_effect_stage,
        ];
    }
}
