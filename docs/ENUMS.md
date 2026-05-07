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

**Semantic meaning:**

- `DRAFT`: Planned make order with no scheduled date.
- `SCHEDULED`: Due date set; still no stock moves.
- `MADE`: Executed; stock moves have been posted.

**Notes:**

- Status transitions are DRAFT → SCHEDULED → MADE.
- MADE is terminal.

---

## Purchasing

### Purchase Order Status

**Name:** PurchaseOrder status  
**Storage location(s):** `purchase_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `OPEN`
- `PARTIALLY-RECEIVED`
- `RECEIVED`
- `BACK-ORDERED`
- `SHORT-CLOSED`
- `CANCELLED`

**Semantic meaning:**

- `DRAFT`: Purchase order is being assembled and may be edited.
- `OPEN`: Purchase order has been issued to the supplier.
- `PARTIALLY-RECEIVED`: Some items have been received, balances remain.
- `RECEIVED`: All ordered items have been received.
- `BACK-ORDERED`: Order contains backordered items.
- `SHORT-CLOSED`: Order closed with a short receipt.
- `CANCELLED`: Order has been cancelled.

**Notes:**

- Derived statuses are set by receiving/short-close events.

---

## Sales

### Sales Order Status

**Name:** SalesOrder status  
**Storage location(s):** `sales_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `OPEN`
- `PACKING`
- `PACKED`
- `SHIPPING`
- `COMPLETED`
- `CANCELLED`

**Semantic meaning:**

- `DRAFT`: Sales order is editable. Header fields and sales-order lines may be created, updated, or deleted. No inventory impact exists yet.
- `OPEN`: Sales order remains editable. Header fields and sales-order lines may still be created, updated, or deleted. No inventory impact exists yet.
- `PACKING`: Operational packing workflow has started. No stock moves have been posted yet.
- `PACKED`: Inventory consumption has been posted. The order is ready to move to shipping.
- `SHIPPING`: Carrier pickup or shipment handoff has occurred. No additional inventory impact occurs in this state.
- `COMPLETED`: Terminal state. Shipment is confirmed complete. No additional inventory impact occurs in this transition.
- `CANCELLED`: Terminal state. The order is no longer editable. Cancelling never posts inventory issue stock moves.

**Notes:**

- Allowed transitions are:
    - `DRAFT -> OPEN`
    - `OPEN -> PACKING`
    - `OPEN -> CANCELLED`
    - `PACKING -> PACKED`
    - `PACKING -> CANCELLED`
    - `PACKED -> SHIPPING`
    - `PACKED -> CANCELLED`
    - `SHIPPING -> COMPLETED`
    - `SHIPPING` cannot be cancelled in this phase
- `COMPLETED` and `CANCELLED` are terminal.
- Sales-order headers and lines may only be mutated while the order is `DRAFT` or `OPEN`.
- `OPEN -> PACKING` checks availability only and creates no stock moves.
- `PACKING -> PACKED` posts inventory issue stock moves in a single transaction.
- Cancelling from `PACKED` appends reversing stock moves and preserves the original audit trail.
- Older roadmap-era statuses such as `CONFIRMED` and `FULFILLED` are not valid statuses.

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
