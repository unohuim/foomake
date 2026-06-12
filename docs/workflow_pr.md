# Workflow PR

This document captures the current workflow-button state in the app and the path to unify all workflow-enabled domains under one shared contract and component.

## Goal

Make `sales`, `purchasing`, `manufacturing`, `inventory counts`, and `recipe versions` use the same workflow action button, the same action payload shape, and the same state-refresh flow.

The shared contract should support:

- current state label
- available actions
- action label
- helper description
- action endpoint
- action method
- confirmation flag when needed
- terminal cancelled state
- terminal completed state

## Current State

The codebase is partially unified, but not fully.

Already shared:

- `resources/views/components/workflow-action-button.blade.php`
- `resources/js/components/workflow-action-button.js`
- shared workflow stage metadata in `workflow_stages`
- shared workflow-progress rendering on detail pages

Still mixed:

- `purchase orders` are closest to the shared contract
- `make orders` use the shared button, but still have domain-specific action routing
- `sales orders` use a shared-style action button, but their lifecycle/status contract is still distinct
- `inventory counts` still use a separate previous / submit / advance flow
- `recipe versions` use the shared button UI, but their version lifecycle is not the same as operational workflow stages

## Desired Contract

Every workflow-enabled domain should emit a server-driven `workflow` payload with the same core fields:

- `status`
- `status_label`
- `display_label`
- `currentLabel`
- `current_stage`
- `actions`
- `header_menu`
- `next_stage_action` when appropriate

Every action should use the same shape:

- `id`
- `type`
- `label`
- `description`
- `endpoint`
- `method`
- `requiresConfirmation`

The shared button should:

- show the current label when closed
- show a dropdown when multiple actions exist
- stay disabled and visible when the workflow is terminal and cancelled
- refresh from a named sync event after successful mutation

## Domain Rules

### Sales

- Uses the shared button contract
- Supports workflow transitions and terminal cancel
- Action labels and helper copy should come from workflow stage metadata where possible

### Purchasing

- Uses the shared button contract
- Supports workflow transitions, receiving-related actions, and terminal cancel
- Helper copy should come from workflow stage metadata first

### Manufacturing

- Uses the shared button contract
- Supports stage movement, make/post completion, and cancel
- The header menu should not invent a separate action system
- `DRAFT` should remain the pre-workflow state

### Inventory Counts

- Must be migrated away from the separate `previous / submit / advance` button set
- Should expose the same shared action button contract as the other domains
- Should still preserve its domain-specific workflow rules, including draft setup, submit, advance, reverse, and post

### Recipe Versions

- Uses the shared button UI
- Version lifecycle is not identical to operational workflow
- The contract should still be normalized so the header button behaves the same way
- Checkout / check-in / publish should map cleanly to shared action payloads

## Seeder / Data Requirements

The seeded workflow stages should remain the source of truth for:

- `name`
- `action_verb`
- `status_complete_label`
- `description`
- `completion_mode`
- `is_inventory_effect_stage`

The seed data needs to carry enough metadata for the shared button to render:

- action label
- helper description
- terminal completed label
- terminal cancelled label

The seeder alone is not enough. Controllers must read the seeded values and emit the shared action payload.

## Implementation Phases

### Phase 1: Normalize the payload

- unify controller payload shape across all workflow-enabled domains
- keep the action object consistent
- keep current label precedence consistent
- keep cancelled/completed terminal states consistent

### Phase 2: Normalize the component behavior

- keep the shared button as the single header action component
- ensure it can render terminal cancelled state as disabled
- ensure it can render descriptions under each action label
- ensure it can sync from named refresh events without full reloads

### Phase 3: Normalize page handlers

- route every domain through a page-module handler
- keep the page module responsible for mutation and refresh
- avoid domain-specific button markup or event shapes

### Phase 4: Normalize seed metadata

- ensure seeded stages include the helper copy needed by the shared button
- ensure cancel stages exist where the domain supports cancellation
- ensure completed labels are present and domain-correct

### Phase 5: Add tests

- assert shared action payload shape per domain
- assert cancel action behavior where supported
- assert disabled cancelled-state rendering
- assert shared description text where stage metadata exists

## Risks

- Recipes are not a pure operational workflow; they still have version semantics.
- Inventory Counts already have special submit/post behavior and task gating.
- Make Orders and Purchase Orders have inventory-impacting stages that must preserve existing ledger behavior.
- Sales may retain status semantics that are not identical to stage semantics in other domains.

## Recommendation

Proceed in this order:

1. unify the payload contract
2. normalize terminal actions
3. wire inventory counts to the shared button
4. normalize recipe version actions
5. write focused regression tests per domain

That gives one header action contract across the app without losing each domain’s specific lifecycle rules.
