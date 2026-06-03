# ENUMS — Canonical Enum Authority

This document defines the canonical, normative enum-like values used throughout the system.
It is the source of truth for all domain-level enum-like values referenced in database schemas, models, actions, and tests.

Do not introduce new enum values without updating this document.

---

## Change Discipline

- New enum values MUST be added here before use.
- Database enum/CHECK constraints MUST match this document.
- Tests MUST only use values defined here.
- Removing or renaming a value requires:
    - Migration
    - Backfill or data safety plan
    - Update to this document

---

## Notes

### Note Visibility

**Name:** Note visibility
**Storage location(s):** `notes.visibility` (string column)
**Allowed values:**

- `internal`

**Semantic meaning:**

- `internal`: Tenant-internal plain-text note visible to users authorized to access the parent resource.

**Notes:**

- V1 supports internal notes only.
- Visibility does not override parent resource authorization or tenant scoping.
- Do not introduce external/customer-facing note visibility without an explicit migration, backfill plan, and UI/authorization update.

---

## Inventory

### Inventory Count Status (Computed)

**Name:** InventoryCount status  
**Storage location(s):** Computed attribute on `InventoryCount` (no database column)  
**Allowed values:**

- `draft`
- `posted`

**Semantic meaning:**

- `draft`: The inventory count has not been posted to the ledger. (`posted_at` is `NULL`.)
- `posted`: The inventory count has been posted to the ledger. (`posted_at` is not `NULL`.)

**Notes:**

- This is a computed status derived solely from `posted_at`.
- Do not store or persist a separate status column.
- This lifecycle value is not a workflow-stage status and must not be rendered as an Inventory workflow header/status-complete label.
- Inventory workflow-stage `status_complete_label` options are provided by the workflow status option provider and include workflow-derived labels such as `SCHEDULED`, `COUNTED`, and `COMPLETED`; these are separate from computed `draft` / `posted` lifecycle values.

---

## Manufacturing

### Recipe Type

**Name:** Recipe type  
**Storage location(s):** `recipes.recipe_type` (string column)  
**Allowed values:**

- `manufacturing`
- `fulfillment`

**Semantic meaning:**

- `manufacturing`: Recipe is eligible for manufacturing execution and Make Orders when its output item is manufacturable.
- `fulfillment`: Recipe is reserved for fulfillment-style output composition and is not eligible for Make Orders in this phase.

**Notes:**

- Display labels are `Manufacturing` and `Fulfillment`.
- Manufacturing recipes require a manufacturable output item.
- Fulfillment recipes require a sellable output item.
- An item that is both manufacturable and sellable may use either recipe type.
- An item that is neither manufacturable nor sellable may not be used as a recipe output item.

---

### Make Order Status

**Name:** MakeOrder status  
**Storage location(s):** `make_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `SCHEDULED`
- `MADE`
- `CANCELLED`

**Semantic meaning:**

- `DRAFT`: Planned make order with no scheduled date.
- `SCHEDULED`: Due date set; still no stock moves.
- `MADE`: Executed; stock moves have been posted.
- `CANCELLED`: Archived or cancelled make order. No stock moves are posted by cancellation.

**Notes:**

- Status transitions are DRAFT → SCHEDULED → MADE, with archive/cancel transitions from eligible non-MADE orders to CANCELLED.
- MADE and CANCELLED are terminal.

---

### Recipe Version Status

**Name:** RecipeVersion status  
**Storage location(s):** `recipe_versions.status` (string column)  
**Allowed values:**

- `DRAFT`
- `PUBLISHED`
- `ARCHIVED`

**Semantic meaning:**

- `DRAFT`: Editable execution template that is not eligible for new Make Orders.
- `PUBLISHED`: Published execution template. Multiple versions may be published historically, but only `recipes.current_version_id` is current.
- `ARCHIVED`: Inactive execution template kept for historical reference only.

**Notes:**

- Legacy persisted `APPROVED` values must be treated as `PUBLISHED` during migration and read-model normalization until old records are rewritten safely.
- Recipe parent names remain on `recipes.name`; version names are optional internal labels only.
- Version status is lifecycle only. Currentness is controlled only by `recipes.current_version_id`.
- Make Orders must reference the recipe version pointed to by `recipes.current_version_id`.

---

## Purchasing

### Purchase Order Status

**Name:** PurchaseOrder status  
**Storage location(s):** `purchase_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `CREATED`
- `PARTIALLY_RECEIVED`
- `RECEIVED`
- `COMPLETED`
- `CANCELLED`

**Semantic meaning:**

- `DRAFT`: Purchase order is being assembled and may be edited.
- `CREATED`: Purchase order has been issued to the supplier and may receive inventory.
- `PARTIALLY_RECEIVED`: One or more receipt lines have been posted, and at least one receivable balance remains open.
- `RECEIVED`: All line balances have been received or short-closed.
- `COMPLETED`: Purchase order lifecycle is complete.
- `CANCELLED`: Purchase order has been cancelled and is terminal.

**Notes:**

- `purchase_orders.status` must persist only the six values above.
- Purchasing workflow-stage `status_complete_label` options are provided by the workflow status option provider and include these persisted values plus `OPEN` for workflow-derived stage configuration.
- Default seeded purchasing stages do not include a separate `PARTIALLY_RECEIVED` stage; receipt logic persists `PARTIALLY_RECEIVED` while the PO remains in the normal Receiving stage.
- Purchase Order workflow displays may derive status from workflow fields during migration; the legacy column remains mirrored temporarily.
- Back Order and Short Close are actions/events, not status values.
- Do not use `PARTIAL` or `RECEIVING` as purchase order statuses.

---

## Sales

### Customer Type

**Name:** Customer type  
**Storage location(s):** `customers.customer_type` (string column)  
**Allowed values:**

- `business`
- `consumer`

**Semantic meaning:**

- `business`: Customer is treated as a business/commercial account.
- `consumer`: Customer is treated as an individual/consumer account.

**Notes:**

- Display labels are `Business` and `Consumer`.
- Default value is `business`.

### Sales Order Status

**Name:** SalesOrder status  
**Storage location(s):** `sales_orders.status` (string column)  
**Fixed system values:**

- `DRAFT`
- `OPEN`
- `COMPLETED`
- `CANCELLED`

**Operational workflow-stage-backed values:**

- Uppercase form of each active tenant `workflow_stages.key` in the `sales` workflow domain
- New tenants are seeded by default with:
    - `PACKING`
    - `PACKED`
    - `SHIPPING`

**Semantic meaning:**

- `DRAFT`: Sales order is editable. Header fields and sales-order lines may be created, updated, or deleted. No inventory impact exists yet.
- `OPEN`: Sales order remains editable. Header fields and sales-order lines may still be created, updated, or deleted. No inventory impact exists yet.
- `COMPLETED`: Terminal state. Shipment is confirmed complete. No additional inventory impact occurs in this transition.
- `CANCELLED`: Terminal state. The order is no longer editable. Cancelling never posts inventory issue stock moves.

**Seeded default operational values:**

- `PACKING`: Default seeded sales workflow stage where operational packing begins. No stock moves have been posted yet.
- `PACKED`: Result of completing the default seeded `Packing` workflow stage, where inventory consumption has been posted.
- `SHIPPING`: Default seeded sales workflow stage where carrier pickup or shipment handoff has occurred.

**Notes:**

- `DRAFT`, `OPEN`, `COMPLETED`, and `CANCELLED` are system-owned values and are not admin-configurable workflow stages.
- Operational stage values are database-backed rather than hardcoded. Runtime transitions use the active tenant `sales` workflow stages ordered by `sort_order`.
- Transition shape is:
    - `DRAFT -> OPEN`
    - `OPEN -> first active sales workflow stage`
    - active workflow stages advance in database order
    - final active workflow stage -> `COMPLETED`
- Orders may still branch to `CANCELLED` where allowed by the current sales-order rules.
- `COMPLETED` and `CANCELLED` are terminal.
- Sales-order headers and lines may only be mutated while the order is `DRAFT` or `OPEN`.
- Availability checks still apply before entering the first operational stage.
- Under the seeded default sales workflow, `OPEN -> PACKING` checks availability only and creates no stock moves.
- Under the seeded default sales workflow, `PACKING -> PACKED` posts inventory issue stock moves in a single transaction.
- Cancelling from `PACKED` appends reversing stock moves and preserves the original audit trail.
- Older roadmap-era statuses such as `CONFIRMED` and `FULFILLED` are not valid statuses.

### Sales Order External Status Mapping

**Name:** SalesOrder external status mapping  
**Storage location(s):** `sales_orders.external_status` (nullable string column), import preview rows, import store rows  
**Mapped external values:**

- `completed`
- `cancelled`
- `canceled`
- `refunded`
- `failed`
- `processing`
- `pending`
- `pending payment`
- `on-hold`
- `on hold`
- `draft`
- `new`

**Semantic meaning:**

- `completed` maps to local `COMPLETED`
- `cancelled`, `canceled`, `refunded`, and `failed` map to local `CANCELLED`
- `processing`, `pending`, `pending payment`, `on-hold`, `on hold`, `draft`, and `new` map to local `OPEN`

**Notes:**

- `external_status` remains a nullable string and is not an enum or enum-backed cast.
- External status sync is informational and duplicate-safe; local Sales Order status remains app-controlled.
- Re-import may update only `external_status` and `external_status_synced_at`.
- Unknown external statuses must fail safely during preview or import rather than silently mapping to the wrong local status.

### Task Status

**Name:** Task status  
**Storage location(s):** `tasks.status` (string column)  
**Allowed values:**

- `open`
- `completed`

**Semantic meaning:**

- `open`: Generated task is outstanding and may still block a forward transition for its current workflow stage.
- `completed`: Generated task has been completed and remains visible as history.

**Notes:**

- Task completion is final in this PR slice.
- Completed tasks cannot be reopened or uncompleted.

---

## Stock / Inventory Ledger

### Stock Move Type

**Name:** StockMove type  
**Storage location(s):** `stock_moves.type` (enum column)  
**Allowed values:**

- `receipt`
- `issue`
- `adjustment`
- `inventory_count_adjustment`

**Semantic meaning:**

- `receipt`: Inventory added to on-hand (e.g., purchase receipt).
- `issue`: Inventory removed from on-hand (e.g., sale issue or consumption).
- `adjustment`: Generic correction movement not tied to an inventory count.
- `inventory_count_adjustment`: Variance movement generated by posting an inventory count.

**Notes:**

- `inventory_count_adjustment` must be used for inventory count posting variance.
- Do not use `adjustment` for inventory count posting.
- `inventory_count_adjustment` exists to preserve auditability and traceability.
- It allows inventory counts to be reported, reversed, or analyzed independently of generic adjustments.

### Stock Move Status

**Name:** StockMove status  
**Storage location(s):** `stock_moves.status` (string column)  
**Allowed values:**

- `DRAFT`
- `SUBMITTED`
- `POSTED`

**Semantic meaning:**

- `DRAFT`: Stock move is staged and not yet applied.
- `SUBMITTED`: Stock move is queued for posting.
- `POSTED`: Stock move is applied to the ledger.

**Notes:**

- Sales-order packed inventory impact posts `issue` stock moves with `status = POSTED`.
- Cancelling a packed sales order appends reversing stock moves while preserving the original issue moves.
- Purchase-receipt and inventory-count posting flows also use `POSTED` when the move is ledger-valid.

---

## Conflicts / Ambiguities Report

No conflicts or ambiguities were found at time of creation based on existing migrations, models, actions, and tests.
