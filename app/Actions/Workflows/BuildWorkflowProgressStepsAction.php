<?php

namespace App\Actions\Workflows;

use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Build read-only workflow progress steps from tenant-configured stages.
 */
class BuildWorkflowProgressStepsAction
{
    /**
     * Build DRAFT-first workflow progress steps for a tenant workflow domain.
     *
     * @return array<int, array{label: string, status: string, url: null, current: bool}>
     */
    public function execute(
        int $tenantId,
        string $domainKey,
        ?int $currentWorkflowStageId = null,
        ?string $currentWorkflowStageKey = null,
        ?int $completedWorkflowStageId = null,
        bool $workflowCompleted = false
    ): array {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();

        $domainId = WorkflowDomain::query()
            ->where('key', $domainKey)
            ->value('id');

        if (! $domainId) {
            return [];
        }

        $stages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('workflow_domain_id', $domainId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $currentStage = $workflowCompleted
            ? null
            : $this->currentStage($stages, $currentWorkflowStageId, $currentWorkflowStageKey);
        $completedStage = $workflowCompleted
            ? $this->currentStage($stages, $completedWorkflowStageId, $currentWorkflowStageKey)
            : null;

        return collect([
            [
                'label' => 'DRAFT',
                'status' => $currentStage || $workflowCompleted ? 'completed' : 'current',
                'url' => null,
                'current' => ! $workflowCompleted && $currentStage === null,
            ],
        ])
            ->merge($stages->map(fn (WorkflowStage $stage): array => [
                'label' => (string) $stage->name,
                'status' => $workflowCompleted
                    ? $this->completedStageStatus($stage, $completedStage)
                    : $this->stageStatus($stage, $currentStage),
                'url' => null,
                'current' => ! $workflowCompleted
                    && $currentStage !== null
                    && (int) $stage->id === (int) $currentStage->id,
            ]))
            ->values()
            ->all();
    }

    /**
     * Resolve the current stage by id or key from the active stage collection.
     *
     * @param Collection<int, WorkflowStage> $stages
     */
    private function currentStage(Collection $stages, ?int $currentWorkflowStageId, ?string $currentWorkflowStageKey): ?WorkflowStage
    {
        if ($currentWorkflowStageId !== null) {
            return $stages->first(
                fn (WorkflowStage $stage): bool => (int) $stage->id === $currentWorkflowStageId
            );
        }

        $stageKey = $this->normalizeStageKey($currentWorkflowStageKey);

        if ($stageKey === null) {
            return null;
        }

        return $stages->first(
            fn (WorkflowStage $stage): bool => (string) $stage->key === $stageKey
        );
    }

    /**
     * Resolve the display status for a configured workflow stage.
     */
    private function stageStatus(WorkflowStage $stage, ?WorkflowStage $currentStage): string
    {
        if ($currentStage === null) {
            return 'upcoming';
        }

        if ((int) $stage->id === (int) $currentStage->id) {
            return 'current';
        }

        if ((int) $stage->sort_order === (int) $currentStage->sort_order) {
            return (int) $stage->id < (int) $currentStage->id ? 'completed' : 'upcoming';
        }

        return (int) $stage->sort_order < (int) $currentStage->sort_order ? 'completed' : 'upcoming';
    }

    /**
     * Resolve the display status for a completed workflow stage.
     */
    private function completedStageStatus(WorkflowStage $stage, ?WorkflowStage $completedStage): string
    {
        if ($completedStage === null) {
            return 'completed';
        }

        if ((int) $stage->id === (int) $completedStage->id) {
            return 'completed';
        }

        if ((int) $stage->sort_order === (int) $completedStage->sort_order) {
            return (int) $stage->id < (int) $completedStage->id ? 'completed' : 'upcoming';
        }

        return (int) $stage->sort_order < (int) $completedStage->sort_order ? 'completed' : 'upcoming';
    }

    /**
     * Normalize a workflow-stage status/key into the stored stage key shape.
     */
    private function normalizeStageKey(?string $stageKey): ?string
    {
        $stageKey = Str::lower(trim((string) $stageKey));

        if ($stageKey === '' || in_array($stageKey, ['draft', 'open', 'completed', 'cancelled'], true)) {
            return null;
        }

        return $stageKey === 'packed' ? 'packing' : $stageKey;
    }
}
