# Workflow Abstraction Plan

## Goal

Refactor workflow runtime behavior so the shared workflow process lives in one reusable abstraction, while each domain supplies only its domain-specific rules and side effects.

Target split:

- 95% shared workflow transition behavior
- 5% domain-specific behavior

This plan is documentation only. It does not approve implementation by itself.

## Current Problem

Workflow data is shared across domains, but workflow runtime behavior is fragmented.

Current shared pieces include:

- `WorkflowDomain`
- `WorkflowStage`
- `WorkflowTaskTemplate`
- `Task`
- `GenerateWorkflowStageTasksAction`
- `AssertWorkflowStageTasksCompletedAction`
- `BuildWorkflowProgressStepsAction`

Current issues:

- Sales, purchasing, manufacturing, and inventory each resolve workflow stages differently.
- Sales runtime repeatedly calls workflow default seeding during normal transition requests.
- Stage lookup causes repeated database reads inside the same request.
- Transition behavior is spread across controllers, actions, and domain services.
- Some sales workflow actions still carry stage-specific historical names.

## Non-Negotiable Rule

Workflow default seeding must not happen during normal workflow transitions.

Default workflow stages should be created only during:

- tenant creation
- `db:seed`
- explicit repair/admin command
- explicit workflow-admin reset action

Runtime workflow code must read existing configuration. It must not call `updateOrCreate()` for default stages.

## Proposed Abstractions

### `WorkflowDefinition`

Read-only in-memory object for one tenant and one workflow domain.

Holds:

- workflow domain id
- domain key
- active stages in runtime order
- stage lookup by id
- stage lookup by key
- stage lookup by completed status label
- inventory-effect stage

It performs no writes.

### `WorkflowDefinitionRepository`

Loads a `WorkflowDefinition`.

Responsibilities:

- load workflow config once
- cache within the current request
- optionally use Laravel cache across requests
- provide cache invalidation when workflow admin changes stages

It must not seed default stages.

### `Workflowable`

Interface or trait for records that participate in workflows.

Examples:

- `SalesOrder`
- `PurchaseOrder`
- `MakeOrder`
- `InventoryCount`

Possible methods:

```php
public function workflowDomainKey(): string;

public function workflowRecordId(): int;

public function workflowTenantId(): int;

public function workflowStatus(): string;

public function setWorkflowStatus(string $status): void;
```

If a domain uses explicit workflow-stage fields, it can also expose:

```php
public function currentWorkflowStageId(): ?int;

public function lastCompletedWorkflowStageId(): ?int;

public function setCurrentWorkflowStageId(?int $stageId): void;

public function setLastCompletedWorkflowStageId(?int $stageId): void;
```

This layer exposes workflow state only. It should not perform transitions.

### `BaseWorkflow`

Abstract service class using the template-method pattern.

It owns the common transition algorithm.

The main transition method should be shared and preferably final:

```php
final public function transition(Workflowable $record, string $target): Workflowable
{
    $this->authorize($record, $target);
    $definition = $this->definitions->for($record->workflowTenantId(), $record->workflowDomainKey());
    $movement = $this->resolveMovement($record, $definition, $target);

    return DB::transaction(function () use ($record, $definition, $movement, $target): Workflowable {
        $lockedRecord = $this->lockRecord($record);

        $this->assertTransitionAllowed($lockedRecord, $definition, $movement);
        $this->assertCurrentStageTasksComplete($lockedRecord, $movement);

        $this->beforePersist($lockedRecord, $definition, $movement, $target);

        $this->persistMovement($lockedRecord, $definition, $movement);

        $this->afterPersist($lockedRecord, $definition, $movement, $target);

        $this->generateEnteredStageTasks($lockedRecord, $movement);

        return $lockedRecord->fresh();
    });
}
```

Domain subclasses customize hooks, not the whole algorithm.

### Domain Workflow Classes

Domain-specific classes extend `BaseWorkflow`.

Examples:

- `SalesOrderWorkflow`
- `PurchaseOrderWorkflow`
- `MakeOrderWorkflow`
- `InventoryCountWorkflow`

They provide:

- permission slug
- terminal statuses
- editable statuses
- status-to-stage mapping rules
- cancellation behavior
- inventory effect behavior
- domain-specific validation

Example:

```php
final class SalesOrderWorkflow extends BaseWorkflow
{
    protected function permission(): string
    {
        return 'sales-sales-orders-manage';
    }

    protected function afterPersist(
        Workflowable $record,
        WorkflowDefinition $definition,
        WorkflowMovement $movement,
        string $target
    ): void {
        if (! $movement->completedStage?->is_inventory_effect_stage) {
            return;
        }

        $this->postSalesOrderInventory($record);
    }
}
```

## Common Transition Steps

These should live in `BaseWorkflow`:

1. Authorize the user.
2. Load workflow definition once.
3. Resolve current stage.
4. Resolve target or next stage.
5. Validate transition is allowed.
6. Lock the domain record.
7. Check current-stage generated tasks are complete.
8. Run domain hook before persistence.
9. Persist workflow/status movement.
10. Run domain hook after persistence.
11. Generate tasks for the entered stage.
12. Delete or close open generated tasks on cancellation where configured.
13. Reload the record.
14. Build shared workflow response payload.

## Domain-Specific Work

These should remain in subclasses or domain effect services:

- Sales inventory issue posting
- Sales fulfillment recipe planning
- Sales cancellation reversal moves
- Purchasing receipt inventory posting
- Manufacturing ingredient issue and output receipt
- Inventory count posting
- Domain-specific status constraints
- Domain-specific assignment rules

## Performance Rules

Runtime transitions must:

- never seed workflow defaults
- never call `updateOrCreate()` for workflow stages
- load domain stages once per request
- avoid repeated stage queries for current, previous, next, and label lookup
- use cached lookup maps instead of repeated database queries
- avoid rebuilding the same workflow response pieces multiple times

## Cache Strategy

Use request-level memory first.

Optional cross-request cache key:

```text
workflow-definition:{tenant_id}:{domain_key}
```

Invalidate when:

- workflow stage is created
- workflow stage is updated
- workflow stage is deactivated/reactivated
- workflow stage order changes
- workflow task template changes, if task template data is included in the cached definition

Task templates may be cached separately if needed.

## Controller Shape

Controllers should become thin.

Example:

```php
public function update(
    UpdateSalesOrderStatusRequest $request,
    SalesOrder $salesOrder,
    SalesOrderWorkflow $workflow
): JsonResponse {
    $updated = $workflow->transition($salesOrder, $request->validated('status'));

    return response()->json(
        $workflow->responsePayload($updated)
    );
}
```

## Migration Path

1. Create `WorkflowDefinition` and `WorkflowDefinitionRepository`.
2. Remove runtime seeding from workflow stage resolvers.
3. Add request-level workflow definition caching.
4. Add `Workflowable` contract or trait to one domain first.
5. Introduce `BaseWorkflow`.
6. Move Sales transition logic into `SalesOrderWorkflow`.
7. Convert Purchasing, Manufacturing, and Inventory after Sales is stable.
8. Remove stage-specific legacy action names once behavior is covered by the shared workflow class.

## Expected Result

After refactor:

- workflow config is read once per domain/request
- normal transitions perform no seeding writes
- controllers stop owning transition orchestration
- common behavior is implemented once
- domain-specific code is isolated to hooks/effects
- stage renaming becomes safer because transitions operate on configured workflow definitions instead of hardcoded stage names
