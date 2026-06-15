<?php

namespace App\Actions\Inventory;

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Actions\Workflows\ResolveInventoryWorkflowStageAction;
use App\Models\InventoryCount;
use App\Models\WorkflowStage;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Submit, advance, and compatibility-post Inventory Counts through the Inventory workflow.
 */
class AdvanceInventoryCountWorkflowStageAction
{
    private const AUTOMATIC_COMPLETION_MODE = 'automatic';

    /**
     * Submit a draft Inventory Count into the first active inventory workflow stage.
     *
     * @throws DomainException
     */
    public function submit(InventoryCount $inventoryCount, int $taskedByUserId): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $taskedByUserId): InventoryCount {
            $lockedCount = InventoryCount::query()
                ->whereKey($inventoryCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            if ($lockedCount->workflow_stage_id !== null) {
                throw new DomainException('Inventory count has already been submitted.');
            }

            $resolver = app(ResolveInventoryWorkflowStageAction::class);
            $firstStage = $resolver->firstActiveStage($lockedCount);

            if (! $firstStage) {
                throw new DomainException('Inventory workflow stages are not configured for this tenant.');
            }

            $lockedCount->tasked_by_user_id = $taskedByUserId;
            $this->enterStageAndCompleteAutomaticStages($lockedCount, $firstStage, $taskedByUserId);

            return $lockedCount->fresh(['workflowStage']);
        });
    }

    /**
     * Advance an Inventory Count, completing the current stage and stopping at the next manual stage.
     *
     * @throws DomainException
     */
    public function advance(InventoryCount $inventoryCount, int $userId): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $userId): InventoryCount {
            $lockedCount = InventoryCount::query()
                ->whereKey($inventoryCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            if ($lockedCount->workflow_stage_id === null) {
                throw new DomainException('Inventory count must be submitted before it can advance.');
            }

            $resolver = app(ResolveInventoryWorkflowStageAction::class);
            $currentStage = $resolver->currentStage($lockedCount);

            if (! $currentStage) {
                throw new DomainException('Current inventory workflow stage is invalid.');
            }

            app(AssertWorkflowStageTasksCompletedAction::class)->execute(
                (int) $lockedCount->tenant_id,
                (int) $lockedCount->id,
                $currentStage,
                'Complete all tasks for this stage before moving the inventory count forward.'
            );

            if ((string) $currentStage->key === 'counting') {
                $this->assertCountedQuantitiesPresent($lockedCount);
            }

            if ($currentStage->is_inventory_effect_stage) {
                app(PostInventoryCountAction::class)->execute($lockedCount, $userId);

                return $lockedCount->fresh(['workflowStage']);
            }

            $nextStage = $resolver->nextActiveStage($lockedCount);

            if (! $nextStage) {
                throw new DomainException('Inventory count has no next workflow stage.');
            }

            $this->enterStageAndCompleteAutomaticStages($lockedCount, $nextStage, $userId);

            return $lockedCount->fresh(['workflowStage']);
        });
    }

    /**
     * Move an Inventory Count back to the previous configured workflow stage without undoing stock moves.
     *
     * @throws DomainException
     */
    public function previous(InventoryCount $inventoryCount, int $userId): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $userId): InventoryCount {
            $lockedCount = InventoryCount::query()
                ->whereKey($inventoryCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            if ($lockedCount->workflow_stage_id === null) {
                throw new DomainException('Inventory count is still in draft setup.');
            }

            $resolver = app(ResolveInventoryWorkflowStageAction::class);
            $previousStage = $resolver->previousActiveStage($lockedCount);

            if (! $previousStage) {
                throw new DomainException('Inventory count has no previous workflow stage.');
            }

            $lockedCount->workflow_stage_id = $previousStage->id;
            $lockedCount->tasked_by_user_id = $userId;
            $lockedCount->save();

            app(GenerateWorkflowStageTasksAction::class)->execute(
                (int) $lockedCount->tenant_id,
                (int) $lockedCount->id,
                $previousStage,
                $lockedCount->assigned_to_user_id
            );

            return $lockedCount->fresh(['workflowStage']);
        });
    }

    /**
     * Preserve direct /post compatibility by moving to the inventory-effect stage and posting.
     *
     * @throws DomainException
     */
    public function postCompatible(InventoryCount $inventoryCount, int $userId): InventoryCount
    {
        return DB::transaction(function () use ($inventoryCount, $userId): InventoryCount {
            $lockedCount = InventoryCount::query()
                ->whereKey($inventoryCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCount->posted_at !== null) {
                throw new DomainException('Inventory count is posted and cannot be modified.');
            }

            $resolver = app(ResolveInventoryWorkflowStageAction::class);
            $effectStage = $resolver->inventoryEffectStage($lockedCount);

            if (! $effectStage) {
                throw new DomainException('Inventory workflow stages are not configured for this tenant.');
            }

            $currentStage = $resolver->currentStage($lockedCount);

            if ($currentStage && (int) $currentStage->id !== (int) $effectStage->id) {
                app(AssertWorkflowStageTasksCompletedAction::class)->execute(
                    (int) $lockedCount->tenant_id,
                    (int) $lockedCount->id,
                    $currentStage,
                    'Complete all tasks for this stage before moving the inventory count forward.'
                );
            }

            $lockedCount->workflow_stage_id = $effectStage->id;
            $lockedCount->tasked_by_user_id ??= $userId;
            $lockedCount->save();

            app(PostInventoryCountAction::class)->execute($lockedCount, $userId);

            return $lockedCount->fresh(['workflowStage']);
        });
    }

    /**
     * Enter a target stage, completing automatic stages until a manual stage or final posting is reached.
     */
    private function enterStageAndCompleteAutomaticStages(
        InventoryCount $inventoryCount,
        WorkflowStage $targetStage,
        int $userId
    ): void {
        $resolver = app(ResolveInventoryWorkflowStageAction::class);
        $stage = $targetStage;

        while ($stage !== null) {
            $inventoryCount->workflow_stage_id = $stage->id;
            $inventoryCount->tasked_by_user_id = $userId;
            $inventoryCount->save();

            if ($stage->completion_mode !== self::AUTOMATIC_COMPLETION_MODE) {
                app(GenerateWorkflowStageTasksAction::class)->execute(
                    (int) $inventoryCount->tenant_id,
                    (int) $inventoryCount->id,
                    $stage,
                    $inventoryCount->assigned_to_user_id
                );

                return;
            }

            if ($stage->is_inventory_effect_stage) {
                app(PostInventoryCountAction::class)->execute($inventoryCount, $userId);

                return;
            }

            $this->assertCanMovePastScheduling($inventoryCount, $stage);

            $inventoryCount->setRelation('workflowStage', $stage);
            $stage = $resolver->nextActiveStage($inventoryCount);
        }
    }

    /**
     * Require at least one material before the automatic Scheduling stage can complete.
     *
     * @throws DomainException
     */
    private function assertCanMovePastScheduling(InventoryCount $inventoryCount, WorkflowStage $stage): void
    {
        if ((string) $stage->key !== 'scheduling') {
            return;
        }

        if ($inventoryCount->lines()->exists()) {
            return;
        }

        throw new DomainException('Add at least one material item before moving past Scheduling.');
    }

    /**
     * Require every counted material to have a filled quantity before leaving the Counting stage.
     *
     * @throws DomainException
     */
    private function assertCountedQuantitiesPresent(InventoryCount $inventoryCount): void
    {
        $hasBlankQuantity = $inventoryCount->lines()
            ->where(function ($query): void {
                $query->whereNull('counted_quantity')
                    ->orWhereRaw("TRIM(counted_quantity) = ''");
            })
            ->exists();

        if ($hasBlankQuantity) {
            throw new DomainException('Enter a counted quantity for every item before moving past Counting.');
        }
    }
}
