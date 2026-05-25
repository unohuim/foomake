# Make Orders (Manufacturing)

## Overview

Persisted Make Orders support planning and execution with a defined lifecycle that remains separate from the visible workflow-state UI:
DRAFT → SCHEDULED → MADE, with archive/cancel transitions to CANCELLED from eligible non-MADE orders.

Make Order quantity represents recipe runs, not desired output quantity.
Produced output is calculated as:

- `runs × recipe_version.output_quantity`

Make Orders reference both the stable recipe parent and the exact current published recipe version used at creation.
Make Orders always resolve the recipe version from `recipes.current_version_id`.
User recipe-version checkouts do not affect Make Order selection.
Make Order input lines are snapshotted from the selected recipe version and execution consumes those snapshot lines, not live recipe/version lines.

The Manufacturing navigation label is `Make Orders`.

## Creation Entry Points

- Recipes index row `Make Order` creates a draft Make Order directly and redirects to the created Make Order detail page when `show_url` is returned.
- Recipe detail `Make Orders` reusable CRUD section lists Make Orders for that recipe and shows a right-aligned `+` action through the shared section action contract.
- The Recipe detail `+` action opens a recipe-scoped Make Order create slide-over.
- The recipe-scoped slide-over does not ask the user to select the recipe again.
- The Recipe detail `+` action appears only when the recipe has a current published version in `recipes.current_version_id`.
- Publishing the first or current recipe version makes the Recipe detail `+` action available immediately without a full page refresh.
- Publishing from the Recipe detail header active-version menu uses the same publish domain action and refreshes Recipe detail Make Order create eligibility immediately.
- Recipe detail slide-over create submits to the recipe-scoped Make Order create endpoint and redirects to the created Make Order detail page when `show_url` is returned.
- Recipe detail current published version row `Make Order` creates a draft Make Order directly from `recipes.current_version_id`.
- Material detail `Recipes` section row `Make` creates a draft Make Order directly from the selected recipe and redirects to the created Make Order detail page when `show_url` is returned.
- Draft-only recipes do not expose working Make entry points and backend creation is rejected until a current published version exists.
- Recipe detail Make Orders rows link directly to the created Make Order detail page through the shared section row-title link contract.
- Recipe detail Make Orders rows show the visible workflow-state badge and a compact `v{x.xx}` recipe-version badge on the same title row so the snapshot version is visible without opening the Make Order.
- Make Orders index recipe-name rows link directly to the Make Order detail page through the shared CRUD linked-text contract.

## Detail Page

- Make Order detail uses the shared resource detail header and breadcrumb component.
- Title renders `Make Order {id}`.
- The visible workflow/state chip sits beside the title:
  - `DRAFT` while `workflow_stage_id` is `NULL`
  - the current configured `workflow_stages.name` once workflow has started
- Breadcrumb renders `Home / Make Orders / Make Order {id}`.
- Core details live in the header chips and meta, not a separate `Core` section.
- Header stays compact and surfaces:
  - first metadata row: recipe name and runs
  - second metadata row: output item and expected output quantity
- Make Order lifecycle `status` is lifecycle-only and must not be rendered as the visible workflow-stage chip in the workflow-enabled header.
- When a draft Make Order can enter workflow, the header shows the shared workflow-entry action using the first active configured manufacturing workflow stage.
- When a valid next configured workflow stage exists, the header shows a shared next-stage action button using the existing workflow transition system.
- Header workflow-entry and next-stage button labels must come from configured `workflow_stages.name` values for the tenant manufacturing workflow domain.
- Workflow-only details such as due date and assigned user live in the Workflow section rather than being duplicated in the header.

## Detail Sections

- Recipe detail `Make Orders` shows newest-first rows and, on mobile only, initially loads the latest `3` rows through the shared detail-section pagination contract.
- Remaining mobile Recipe detail Make Orders rows stay accessible through shared pagination controls.
- Make Order detail section order is:
  1. `Workflow`
  2. `Tasks`
  3. `Ingredients`

## Workflow Section

- Make Order detail includes a `Workflow` accordion/detail section using the shared detail-section shell.
- Workflow defaults open through the shared detail-section `defaultOpen` contract.
- Workflow reads configured tenant `workflow_stages` for the `manufacturing` workflow domain.
- Workflow entry from `DRAFT` assigns the first active configured manufacturing workflow stage ordered by `sort_order`.
- If no active manufacturing workflow stage exists, workflow entry must fail cleanly and must not invent a fallback stage.
- Workflow stage movement uses the existing workflow-stage and task-gating system through the shared header action rather than a duplicate section-local move-stage control.
- Lifecycle `status` remains separate from operational `workflow_stage_id`.
- `workflow_stage_id` is the operational workflow position for the Make Order.
- Draft Make Orders remain outside operational workflow stages until they explicitly enter workflow.
- The Workflow section keeps due date and assigned/current responsible user on one compact row when space allows.
- Due date is editable inline from the Workflow section and autosaves on change.
- Due date may be cleared when business rules allow no due date.
- Make Orders auto-assign ownership to the authenticated user on creation through `make_orders.made_by_user_id`.
- Recipe-scoped Make Order create starts as `DRAFT` with `workflow_stage_id = NULL`.
- Recipe-scoped Make Order create snapshots the current published recipe version lines into `make_order_lines`.
- Recipe-scoped Make Order create must continue to use `recipes.current_version_id`, never a checked-out draft or display-only version.
- Make Order assignment is editable from the Workflow section through a compact shared user dropdown/select pattern.
- Assignment lives on `make_orders.made_by_user_id`.
- `make_orders.assigned_to_user_id` is not part of the Make Order schema and must not be used for Make Order ownership.
- Assignment is workflow ownership metadata only.
- Assignment is separate from lifecycle `status`, operational `workflow_stage_id`, and generated workflow task assignees.
- Assignment options must be limited to users from the same tenant.
- Assignment may be cleared back to `Unassigned`.
- Assignment autosaves on dropdown/select change.
- The Workflow section must not render a separate `Save Due Date` button.
- The Workflow section must not render a separate `Save Assignee` button or an extra save-button row for this single-field update.
- The Workflow section must not duplicate `Current Stage` display or render a duplicate `Move Stage` block when the shared header workflow action already exists.
- Updating due date must not mutate `workflow_stage_id`, lifecycle `status`, `make_order_lines`, `recipe_version_lines`, workflow tasks, or task templates.
- Updating assignment must not mutate `workflow_stage_id`, lifecycle `status`, `make_order_lines`, `recipe_version_lines`, workflow tasks, or task templates.
- Moving workflow stage must not erase `made_by_user_id`.

## Tasks Section

- Make Order detail includes a separate `Tasks` accordion/detail section using the shared detail-section shell.
- Tasks defaults closed through the shared detail-section `defaultOpen` contract.
- The Tasks section renders current-stage workflow tasks only.
- The Tasks section owns task completion actions and keeps them separate from workflow metadata editing.
- Moving workflow stage must continue to respect current-stage task gating regardless of the Tasks section being collapsed.

## Ingredients Section

- Make Order detail includes an `Ingredients` accordion section using the shared Ingredients detail section pattern.
- Ingredients defaults open through the shared detail-section `defaultOpen` contract.
- Ingredients are editable snapshots stored in `make_order_lines`, not `recipe_version_lines`.
- Columns are `Ingredient`, `UOM`, `Qty`, and `On Hand`.
- `Qty` is displayed using the ingredient item UoM display precision while storage remains canonical scale `6`.
- `On Hand` is read from tenant inventory availability derived from stock truth.
- The add bar uses a compact combobox plus plus-button pattern through the shared section actions area.
- Combobox dropdowns and row-action menus must not be clipped by the section shell or table wrapper.
- Ingredient row actions are `View`, `Make`, `Purchase`, and `Remove`.
- `View` links to the material detail page for the ingredient item.
- `Make` appears only when the ingredient item is manufacturable and an existing valid Make flow URL exists.
- `Purchase` appears only when the ingredient item is purchasable and an existing valid purchase/detail flow URL exists.
- `Remove` deletes the `make_order_line` snapshot row when the Make Order remains editable.

## Lifecycle

### DRAFT

- Create a Make Order record.
- Select a recipe by recipe name.
- `DRAFT` means the Make Order has not entered workflow yet.
- `workflow_stage_id` may be `NULL`.
- No stock moves are created.

### SCHEDULED

- `SCHEDULED` remains lifecycle-only.
- `SCHEDULED` is not a visible Make Order workflow-stage label unless a tenant explicitly configures a manufacturing workflow stage named `Scheduled`.
- No stock moves are created.

### MADE

- Executing a Make Order posts stock moves from the snapshotted make-order lines and receipts the computed output quantity.
- Status changes to MADE.
- `made_at` is set.
- Order is locked from further actions.

### CANCELLED

- Archive maps to Make Order cancellation.
- Only eligible non-MADE orders may be cancelled.
- No stock moves are created by cancellation.
- Cancelled orders are terminal and are excluded from the active index/list surfaces.

## Rules

- Tenant-scoped access on all reads/writes.
- Active recipe required for create, schedule, and make.
- Current published recipe version required for create and edit.
- User checked-out recipe versions are never used for Make Orders.
- Visible workflow/state labels use:
  - `DRAFT` when `workflow_stage_id` is `NULL`
  - configured `workflow_stages.name` when `workflow_stage_id` is set
- Make Orders index, detail header, Workflow section, and Make Order rows in shared detail sections must all use configured workflow-stage names after workflow entry.
- Cross-tenant users must never appear as Make Order assignee options and cross-tenant assignment IDs must be rejected.
- Recipe version output quantity must be greater than zero to execute.
- Runs must be greater than zero to execute.
- Input snapshot consumption scales by runs when the Make Order is created.
- Output receipt quantity equals `runs × recipe_version.output_quantity`.
- Make is idempotent; repeated make returns an error and creates no moves.
- Cancelled make orders cannot be scheduled or made.
- Stock moves use source_type = make_order.
