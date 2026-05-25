<?php

namespace App\Actions\Manufacturing;

use App\Actions\Workflows\AssertWorkflowStageTasksCompletedAction;
use App\Actions\Workflows\GenerateWorkflowStageTasksAction;
use App\Actions\Workflows\ResolveManufacturingWorkflowStageAction;
use App\Models\MakeOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Move a Make Order between configured manufacturing workflow stages.
 */
class MoveMakeOrderWorkflowStageAction
{
    /**
     * Move the Make Order to the provided workflow stage.
     *
     * @throws DomainException
     */
    public function execute(MakeOrder $makeOrder, int $targetWorkflowStageId, int $taskedByUserId): MakeOrder
    {
        return DB::transaction(function () use ($makeOrder, $targetWorkflowStageId, $taskedByUserId): MakeOrder {
            $lockedMakeOrder = MakeOrder::query()
                ->whereKey($makeOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMakeOrder->status === MakeOrder::STATUS_MADE) {
                throw new DomainException('Made make orders cannot move workflow stages.');
            }

            if ($lockedMakeOrder->status === MakeOrder::STATUS_CANCELLED) {
                throw new DomainException('Cancelled make orders cannot move workflow stages.');
            }

            $resolver = app(ResolveManufacturingWorkflowStageAction::class);
            $currentStage = $resolver->currentStage($lockedMakeOrder);
            $firstStage = $resolver->firstActiveStage($lockedMakeOrder);

            if ($currentStage === null && ! $firstStage) {
                throw new DomainException('No active manufacturing workflow stage is configured.');
            }

            $availableStages = $resolver->availableTransitions($lockedMakeOrder);
            $targetStage = $availableStages->firstWhere('id', $targetWorkflowStageId);

            if (! $targetStage) {
                throw new DomainException('Workflow stage transition is not allowed.');
            }

            if ($currentStage) {
                app(AssertWorkflowStageTasksCompletedAction::class)->execute(
                    (int) $lockedMakeOrder->tenant_id,
                    (int) $lockedMakeOrder->id,
                    $currentStage,
                    'Complete all tasks for this stage before moving the make order forward.'
                );
            }

            $lockedMakeOrder->workflow_stage_id = $targetStage->id;
            if ($currentStage === null) {
                $lockedMakeOrder->status = MakeOrder::STATUS_SCHEDULED;
                $lockedMakeOrder->scheduled_at = $lockedMakeOrder->scheduled_at ?? now();
            }
            $lockedMakeOrder->tasked_by_user_id = $taskedByUserId;
            $lockedMakeOrder->save();

            app(GenerateWorkflowStageTasksAction::class)->execute(
                (int) $lockedMakeOrder->tenant_id,
                (int) $lockedMakeOrder->id,
                $targetStage
            );

            return $lockedMakeOrder->fresh(['workflowStage', 'madeByUser', 'taskedByUser']);
        });
    }
}
