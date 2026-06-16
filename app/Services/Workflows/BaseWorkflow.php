<?php

namespace App\Services\Workflows;

use App\Contracts\Workflows\Workflowable;
use App\Support\Workflows\WorkflowDefinition;
use App\Support\Workflows\WorkflowDefinitionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base template-method workflow transition service.
 */
abstract class BaseWorkflow
{
    public function __construct(
        protected readonly WorkflowDefinitionRepository $definitions
    ) {
    }

    /**
     * Run a workflow transition with the common lifecycle.
     */
    final public function transition(Workflowable $record, string $target): Workflowable
    {
        $this->authorize($record, $target);
        $definition = $this->definitions->for($record->workflowTenantId(), $record->workflowDomainKey());
        $this->assertTransitionAllowed($record, $definition, $target);

        return DB::transaction(function () use ($record, $definition, $target): Workflowable {
            $lockedRecord = $this->lockRecord($record, $definition);
            $this->assertTransitionAllowed($lockedRecord, $definition, $target);
            $this->assertCurrentStageTasksComplete($lockedRecord, $definition, $target);
            $this->beforePersist($lockedRecord, $definition, $target);
            $this->persistMovement($lockedRecord, $definition, $target);
            $this->afterPersist($lockedRecord, $definition, $target);
            $this->afterTransition($lockedRecord, $definition, $target);

            return $this->freshRecord($lockedRecord);
        });
    }

    /**
     * Authorize a transition.
     */
    abstract protected function authorize(Workflowable $record, string $target): void;

    /**
     * Lock and return the workflow record.
     */
    abstract protected function lockRecord(Workflowable $record, WorkflowDefinition $definition): Workflowable;

    /**
     * Validate target movement.
     */
    abstract protected function assertTransitionAllowed(
        Workflowable $record,
        WorkflowDefinition $definition,
        string $target
    ): void;

    /**
     * Check generated task gating for the current stage.
     */
    protected function assertCurrentStageTasksComplete(
        Workflowable $record,
        WorkflowDefinition $definition,
        string $target
    ): void {
    }

    /**
     * Domain hook before status persistence.
     */
    protected function beforePersist(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
    }

    /**
     * Persist workflow movement.
     */
    protected function persistMovement(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
        $record->setWorkflowStatus($target);

        if ($record instanceof Model) {
            $record->save();
        }
    }

    /**
     * Domain hook after status persistence.
     */
    protected function afterPersist(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
    }

    /**
     * Domain hook after all transition effects.
     */
    protected function afterTransition(Workflowable $record, WorkflowDefinition $definition, string $target): void
    {
    }

    /**
     * Reload the record.
     */
    protected function freshRecord(Workflowable $record): Workflowable
    {
        if ($record instanceof Model) {
            /** @var Workflowable $fresh */
            $fresh = $record->fresh();

            return $fresh;
        }

        return $record;
    }
}
