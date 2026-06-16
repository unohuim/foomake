<?php

namespace App\Support\Workflows;

use App\Models\WorkflowStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Read-only workflow configuration for one tenant and domain.
 */
class WorkflowDefinition
{
    /**
     * @param Collection<int, WorkflowStage> $activeStages
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly int $domainId,
        private readonly string $domainKey,
        private readonly Collection $activeStages
    ) {
    }

    /**
     * Return the tenant id.
     */
    public function tenantId(): int
    {
        return $this->tenantId;
    }

    /**
     * Return the workflow domain id.
     */
    public function domainId(): int
    {
        return $this->domainId;
    }

    /**
     * Return the workflow domain key.
     */
    public function domainKey(): string
    {
        return $this->domainKey;
    }

    /**
     * Return active stages in runtime order.
     *
     * @return Collection<int, WorkflowStage>
     */
    public function activeStages(): Collection
    {
        return $this->activeStages;
    }

    /**
     * Return the first active stage.
     */
    public function firstStage(): ?WorkflowStage
    {
        return $this->activeStages->first();
    }

    /**
     * Return the stage after the provided stage.
     */
    public function nextStageAfter(?WorkflowStage $stage): ?WorkflowStage
    {
        if ($stage === null) {
            return null;
        }

        return $this->activeStages
            ->filter(fn (WorkflowStage $candidate): bool => $this->comesAfter($candidate, $stage))
            ->first();
    }

    /**
     * Return the stage before the provided stage.
     */
    public function previousStageBefore(?WorkflowStage $stage): ?WorkflowStage
    {
        if ($stage === null) {
            return null;
        }

        return $this->activeStages
            ->filter(fn (WorkflowStage $candidate): bool => $this->comesBefore($candidate, $stage))
            ->last();
    }

    /**
     * Return the active stage matching a key.
     */
    public function stageByKey(string $key): ?WorkflowStage
    {
        $key = Str::lower(trim($key));

        return $this->activeStages->first(
            fn (WorkflowStage $stage): bool => Str::lower((string) $stage->key) === $key
        );
    }

    /**
     * Return the stage matching a completed status label or key.
     */
    public function stageByStatus(string $status, ?callable $stageKeyNormalizer = null): ?WorkflowStage
    {
        $status = Str::upper(trim($status));
        $key = $stageKeyNormalizer ? (string) $stageKeyNormalizer($status) : Str::lower($status);
        $normalizedKey = Str::upper($key);

        return $this->activeStages->first(function (WorkflowStage $stage) use ($status, $normalizedKey): bool {
            return Str::upper(trim((string) $stage->status_complete_label)) === $status
                || Str::upper(trim((string) $stage->key)) === $normalizedKey;
        });
    }

    /**
     * Return workflow progress steps without another database read.
     *
     * @return array<int, array{label: string, status: string, url: null, current: bool}>
     */
    public function progressSteps(?WorkflowStage $currentStage, bool $workflowCompleted): array
    {
        return collect([
            [
                'label' => 'DRAFT',
                'status' => $currentStage || $workflowCompleted ? 'completed' : 'current',
                'url' => null,
                'current' => ! $workflowCompleted && $currentStage === null,
            ],
        ])
            ->merge($this->activeStages->map(fn (WorkflowStage $stage): array => [
                'label' => (string) $stage->name,
                'status' => $workflowCompleted
                    ? 'completed'
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
     * Determine whether the candidate stage comes after the current stage.
     */
    private function comesAfter(WorkflowStage $candidate, WorkflowStage $current): bool
    {
        if ((int) $candidate->sort_order === (int) $current->sort_order) {
            return (int) $candidate->id > (int) $current->id;
        }

        return (int) $candidate->sort_order > (int) $current->sort_order;
    }

    /**
     * Determine whether the candidate stage comes before the current stage.
     */
    private function comesBefore(WorkflowStage $candidate, WorkflowStage $current): bool
    {
        if ((int) $candidate->sort_order === (int) $current->sort_order) {
            return (int) $candidate->id < (int) $current->id;
        }

        return (int) $candidate->sort_order < (int) $current->sort_order;
    }

    /**
     * Resolve progress status for a stage.
     */
    private function stageStatus(WorkflowStage $stage, ?WorkflowStage $currentStage): string
    {
        if ($currentStage === null) {
            return 'upcoming';
        }

        if ((int) $stage->id === (int) $currentStage->id) {
            return 'current';
        }

        return $this->comesBefore($stage, $currentStage) ? 'completed' : 'upcoming';
    }
}
