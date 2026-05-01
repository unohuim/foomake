# PR2_ROADMAP — UI + Domain Completion (Post-PR-006)

This roadmap defines the **second major phase** of work: completing **Items, Inventory, Suppliers, and Manufacturing**
with **modern, minimalist UI**, aligned with **Breeze** and constrained by `UI_DESIGN.md`.

This reconciled version reflects the **current implementation state** of the repository.

This version explicitly accounts for the **dependency chain**:

> **UoM Category → UoM → Item (Material)**

and uses **process-based top-level navigation**, with Materials grouped under **Manufacturing**.

---

## Core Principles

- **Domain-segmented PRs**
- **UI + backend together per PR**
- **AJAX-first CRUD**
- **Smallest possible change per PR**
- No speculative UI or abstractions
- Reusable components only when repetition appears
- Backend invariants are already complete (PR-001 → PR-006)

---

## Navigation Model (Reconciled)

- **Top horizontal navigation (Breeze-style)**
- No sidebars
- No dashboard-heavy layouts

### Current Navigation Pattern

- **Purchasing** and **Manufacturing** are the current top-level domain menus
- **Materials** is reached via **Manufacturing → Materials**
- Manufacturing dropdown currently includes:
    - Inventory
    - Inventory Counts
    - Orders (Make Orders)
    - Materials
    - Recipes
    - Units of Measure
    - UoM Categories

This keeps navigation aligned with current process-based domain ownership.

---

## DOMAIN 1 — Materials (Foundation)

> Materials are the **first UI domain** because all downstream domains depend on Items.

### PR2-MAT-001 — Materials Navigation + Index ✅ (Implemented, later expanded)

**Goal**  
Expose Materials as a first-class domain with read-only visibility.

**Includes**

- Add Materials visibility within current top horizontal navigation
- Route: `/materials`
- Gate enforcement: `inventory-materials-view`
- Index view listing all Items

**UI**

- Clean list/table (Tailwind, Breeze-aligned)
- Columns: Name, Base UoM, Flags
- Empty state with “Create Material” CTA

**Implementation Note**

- The current codebase has already expanded beyond this original slice to include create/edit/delete and related UoM management.

---

## DOMAIN 1A — Units of Measure (Materials Support)

> These PRs unblock **Material creation** and must precede PR2-MAT-004.

### PR2-UOM-001 — UoM Categories CRUD (AJAX)

**Goal**  
Allow managing UoM Categories required by Units and Items.

**Includes**

- Materials nav dropdown entry: **UoM Categories**
- Route: `/materials/uom-categories`
- List + Create + Edit + Delete (AJAX)
- Minimal fields (e.g. name)
- Empty state + CTA

**Permissions**

- `inventory-materials-manage`

---

### PR2-UOM-002 — Units of Measure CRUD (AJAX)

**Goal**  
Allow managing Units of Measure within categories.

**Includes**

- Manufacturing nav dropdown entry: **Units**
- Route: `/manufacturing/uoms`
- List filtered/grouped by category
- Create + Edit + Delete (AJAX)
- Requires category selection
- Inline validation

**Rules**

- Must belong to exactly one category
- No conversions handled here

**Permissions**

- `inventory-materials-manage`

---

## DOMAIN 1B — Materials CRUD (Now Unblocked)

### PR2-MAT-002 — Create Material (AJAX) _(Renumbered)_

**Goal**  
Allow creating a Material once UoMs exist.

**Includes**

- “Create Material” primary action
- Slide-over form
- AJAX POST create
- Optimistic list update
- Validation + error handling

**Form Fields**

- Required:
    - `name`
    - `base_uom_id`
- Optional:
    - `is_purchasable`
    - `is_sellable`
    - `is_manufacturable`

**Rules**

- Save blocked if no UoMs exist
- Clear empty-state guidance (“Create a Unit of Measure first”)

**Permissions**

- `inventory-materials-manage`

---

### PR2-MAT-003 — Edit Material (AJAX)

**Goal**  
Allow editing an existing Material safely.

**Includes**

- Edit action
- Reuse slide-over form
- AJAX PATCH update
- Success + error toasts

**Constraints**

- `base_uom_id` locked if stock moves exist
- Backend enforced + UI hint

---

### PR2-MAT-004 — Row Actions Menu + Delete

**Goal**  
Introduce contextual actions and safe deletion.

**Includes**

- Vertical “⋮” actions dropdown
- Edit / Delete actions
- Confirmation modal
- AJAX delete

**Rules**

- Deletion blocked if stock moves exist
- Non-blocking error messaging

---

### PR2-MAT-005 — Material Detail View (Read-Only)

**Goal**  
Provide a stable anchor for future expansions.

**Includes**

- Route: `/materials/{item}`
- Read-only detail view
- Sections:
    - Core fields
    - Flags
    - Base UoM

**Future Tabs**

- Conversions
- Inventory
- Purchase Options

---

## DOMAIN 2 — PR2-INV-001 — Inventory Visibility & Counts

_(Unchanged)_

- Inventory Overview
- Inventory Counts (Draft)
- Inventory Count Posting

---

## DOMAIN 3 — Recipes & Manufacturing

### PR3-REC-001 — Recipes Index + Detail (Read-Only)

**Goal**  
Expose recipes as a visible domain with a stable read-only anchor for later CRUD and execution.

**Includes**

- Navigation entry under **Manufacturing**
- Routes:
    - `/manufacturing/recipes`
    - `/manufacturing/recipes/{recipe}`
- Gate enforcement: existing inventory view permission
- Index list of recipes (Output Item, Active, Updated)
- Read-only detail sections:
    - Output Item
    - Active flag
    - Lines (read-only)

**Out of Scope**

- Create / Edit / Delete
- Line editing
- Make order execution

---

### PR3-REC-002 — Recipes CRUD + Recipe Lines CRUD (AJAX)

**Goal**  
Enable full recipe authoring with minimal, calm AJAX-first UX.

**Includes**

- Create/edit recipe via slide-over
- AJAX create/update/delete for recipes
- Recipe line management (AJAX):
    - Add line (input item + quantity)
    - Edit line
    - Delete line
- Inline validation + error handling
- Success toasts

**Rules**

- Exactly one output item per recipe
- Lines cannot reference the output item
- Canonical decimal quantity math
- Many active recipes per output item are allowed
- Only one default recipe per (tenant, output item) is allowed
- `is_active` and `is_default` are independent flags
- Setting `is_default=true` unsets the prior default for the same (tenant, output item)
- Defaults are tenant-scoped and item-scoped only
- Deleting a default recipe leaves no default (no auto-promotion)
- Default enforcement exists at both the application layer (transactional) and database layer (unique constraint/partial index)
- No implicit activation or defaulting occurs

**Clarifications Added**

- Active vs default semantics are now explicit to reflect the introduction of `is_default` and the corrected allowance of multiple active recipes per output item.

**Permissions**

- `inventory-make-orders-manage`

---

### PR3-REC-003 — Recipe Output Quantity Support

**Goal**  
Introduce explicit output quantity for recipes.

**Problem Statement**  
Recipes currently define output item and UoM but not quantity.

**Includes**

- Add `output_quantity` to recipes
- Default existing records to `'0.000000'`
- Update create/edit UI to capture quantity
- Display output quantity in index and detail views
- Persist using BCMath string (scale = 6)

**Rules**

- Output quantity is required for new/updated recipes
- Must be ≥ 0 (existing default = 0 allowed for legacy)
- Stored as string, not float
- Uses canonical scale = 6

**Execution Impact**

`ExecuteRecipeAction` must scale inputs relative to defined output quantity.

Example:
Recipe output = 10
Execute 20 → multiplier = 2

**Testing**

- Creation and validation
- Default backfill behavior
- Execution scaling correctness
- Precision handling

**Documentation Impact**

- Update architecture docs to reflect new recipe invariant

---

### PR3-MO-001 — Make Orders (Execute Recipe) _(Superseded in implementation by persisted make orders)_

**Goal**  
Allow executing a recipe to create ledger movements.

**Includes**

- Navigation: **Manufacturing → Orders (Make Orders)**
- Route: `/manufacturing/make-orders`
- Execution UI:
    - Select recipe
    - Enter output quantity
    - Submit (AJAX)
- Calls `ExecuteRecipeAction`
- Success toast + lightweight summary

**Rules**

- Ledger-first execution (issues + receipt)
- BCMath with canonical scale
- This original direct-execution-only approach was later replaced in the current implementation by persisted make orders (`PR3b`)

**Permissions**

- View: `inventory-make-orders-view`
- Execute: `inventory-make-orders-execute`

---

### PR3b — Make Orders Lifecycle (Persisted)

**Goal**  
Implement persisted Make Orders with full lifecycle (Draft → Scheduled → Made/Executed), replacing direct execution. Index list at /manufacturing/make-orders.

**Includes**

- Index UI: table of Make Orders (Recipe/Output, Qty, Status, Due Date, Actions)
- Create draft: slide-over (select active recipe + output qty) → saves as DRAFT
- Schedule: set due date on draft → status SCHEDULED
- Make/Execute: on scheduled order → calls ExecuteRecipeAction → status MADE + stock moves
- Tenant isolation, permission gates, AJAX actions, toasts/errors
- Idempotent "make" (error if already made)

**Rules**

- Ledger-first on "make": issues inputs + receipts output via ExecuteRecipeAction
- BCMath canonical scale=6
- Status: DRAFT, SCHEDULED, MADE
- Active recipe required
- Due date required for schedule
- No actions after MADE

**Permissions**

- View: inventory-make-orders-view
- Manage/Execute: inventory-make-orders-execute

**Out of Scope**

- Partial execution
- Post-MADE reversal
- Reporting

---

## DOMAIN 4 — Suppliers & Purchasing (Revised)

This domain introduces **supplier management, supplier-specific material pricing, and purchasing primitives**, with clear separation between **planning prices**, **supplier catalog/prices**, and **order price snapshots**.

---

### PR2-PUR-001 — Suppliers Index + Create (AJAX)

**Goal**  
Introduce tenant-owned Suppliers as a first-class Purchasing domain.

**Includes**

- Nav: **Purchasing → Suppliers**
- Routes:
    - `GET /purchasing/suppliers`
    - `POST /purchasing/suppliers` (AJAX)
- Supplier fields:
    - `company_name` (required)
    - `url` (nullable)
    - `phone` (nullable)
    - `email` (nullable)
    - `currency_code` (nullable; defaults to tenant currency)
- Empty state + slide-over create form
- Gates:
    - View: `purchasing-suppliers-view`
    - Create: `purchasing-suppliers-manage`
- Tenancy enforced (`tenant_id`, `HasTenantScope`)

**Out of Scope**

- Edit/Delete
- Supplier detail page
- Material linking

---

### PR2-MAT-006 — Material Planning Price (Schema + UI)

**Goal**  
Add a **planning-only placeholder price** to materials.

**Includes**

- Schema:
    - `items.default_price_cents`
    - `items.default_price_currency_code`
- Defaults to tenant currency
- Editable on material create/edit
- Used for:
    - Planning
    - Forecasting
    - Early recipe costing
- Explicitly **not transactional**

**Out of Scope**

- Supplier-specific pricing
- FX conversion logic

---

### PR2-PUR-002 — Supplier CRUD (Edit + Delete)

**Goal**  
Complete supplier lifecycle management.

**Includes**

- Edit supplier (AJAX slide-over)
- Delete supplier
- Delete blocked if supplier has linked supplier catalog records
- Gate: `purchasing-suppliers-manage`

---

### PR2-PUR-003 — Supplier ↔ Material Catalog + Pricing

**Goal**  
Define which materials are bought from which suppliers, including **supplier-specific pricing**.

**Includes**

- Implemented as tenant-owned supplier catalog records using:
    - `item_purchase_options`
    - `item_purchase_option_prices`
- Fields:
    - `tenant_id`
    - `supplier_id`
    - `item_id`
    - `price_amount`
    - `price_currency_code` (defaults to supplier → tenant currency)
    - `converted_amount` (tenant currency)
    - `fx_rate`
    - `fx_rate_as_of`
    - `effective_at` (or `is_current`)
- Supplier detail page:
    - `/purchasing/suppliers/{supplier}`
    - Supplier info + index of materials they sell
    - Add/remove materials + set price (AJAX)
- Material detail enhancement:
    - `/materials/{item}`
    - Section listing possible suppliers + current prices
- Gates:
    - View: `purchasing-suppliers-view`
    - Mutate: `purchasing-suppliers-manage`

**Rules**

- Many suppliers per material
- Many materials per supplier
- The current implementation keeps supplier purchase options and separate price records for expected/current pricing

---

### PR2-PUR-004 — Purchase Orders (Draft + Pricing Snapshot)

---

## Goal

Introduce Purchase Orders with **immutable pricing snapshots** captured at the moment a line is added.

This PR establishes the **foundation of the PO system**: draft creation, line management, and permanent price capture.  
This was the original foundation slice. The current implementation later expanded into lifecycle and receiving behavior covered by `PR2-PUR-005`.

---

## Navigation

**Purchasing → Orders**

Routes introduced:

- `/purchasing/orders` — Index
- `/purchasing/orders/{id}` — Show / Manage lines

---

## Includes (In Scope)

- Purchase Order index page
- Ability to **create a draft PO** via AJAX
- Ability to **delete a PO**
- Ability to **add lines** to a PO via AJAX
- Ability to **remove lines** from a PO via AJAX
- Lines are sourced strictly from the **supplier-material catalog**
- All monetary fields stored as **integer cents**
- All suppliers in this PR use the **same currency as the tenant**
- Taxes are **ignored**
- Shipping is the **only manual monetary input**
- Pricing is **fully calculated**, except shipping

---

## Critical Concept — Pricing Snapshot

When a line is added to a PO, the following fields must be permanently written to the line:

- `unit_price_amount` (supplier currency, integer cents)
- `unit_price_currency_code`
- `converted_unit_price_amount` (tenant currency, integer cents)
- `fx_rate`
- `fx_rate_as_of`

### Immutable Rule

> These values must NEVER change after being written.

If the supplier later changes prices, or FX rates change, **existing PO lines must remain untouched**.

This is the core guarantee of this PR.

---

## Line Endpoints (AJAX)

- Add line to PO
- Remove line from PO

---

## Permissions (Single Gate for This PR)

All PO behavior in this PR is controlled by **one permission only**:

`purchasing-purchase-orders-create`

This permission allows:

- Viewing index
- Viewing a PO
- Creating a PO
- Deleting a PO
- Adding lines
- Removing lines

There is **no separate "view" permission** in this PR.

---

## Purchase Order Header Fields (In Scope)

| Field            | Type            | Notes                         |
| ---------------- | --------------- | ----------------------------- |
| `supplier_id`    | nullable FK     | Required before leaving DRAFT |
| `order_date`     | nullable date   | Required before leaving DRAFT |
| `shipping_cents` | nullable int    | Required before leaving DRAFT |
| `po_number`      | nullable string | Optional forever              |
| `notes`          | nullable text   | Optional                      |
| `status`         | enum            | Defaults to `DRAFT`           |

---

## Draft Behavior

When a user creates a PO:

- A new record is immediately created
- Status is always `DRAFT`
- All header fields are nullable
- User enters header fields manually later
- System does **not** auto-populate anything except defaults

To move out of DRAFT (future PR):

- `supplier_id`
- `order_date`
- `shipping_cents`

---

## Explicitly Out of Scope (Handled in Future PRs)

- Status workflow enforcement
- Receiving inventory
- Cancelling logic
- Taxes
- Multi-currency suppliers
- Editing prices
- Editing snapshot data
- Lifecycle transitions beyond DRAFT
- Accounting integration
- Approval flows

---

### Currency & FX Rules (Domain-Wide)

- Tenant has a **default currency**
- Supplier defaults to tenant currency but may override
- Prices may be entered in foreign currencies
- FX handling:
    - Store **original amount + currency**
    - Store **converted tenant amount**
    - Persist **FX rate + rate date**
- No retroactive FX updates

---

### Pricing Layer Invariant

| Layer          | Location                                                          | Purpose                  |
| -------------- | ----------------------------------------------------------------- | ------------------------ |
| Planning       | `items.default_price_cents` + `items.default_price_currency_code` | Forecasting only         |
| Supplier       | `item_purchase_options.*` + `item_purchase_option_prices.*`       | Expected buy price       |
| Purchase Order | `purchase_order_lines.*`                                          | Legal / accounting truth |

---

### PR2-PUR-005 — Purchase Order Lifecycle & Receiving

Goal

Introduce full Purchase Order lifecycle beyond DRAFT and implement industry-standard receiving with:

Line-level receipts

Receipt history (header + lines)

Automatic inventory impact (stock_moves)

Status transitions derived from receipts

Manual lifecycle controls where appropriate

This PR turns Purchase Orders from a pricing document into a true operational document.

Purchase Order Statuses

DRAFT

OPEN

PARTIALLY-RECEIVED

RECEIVED

BACK-ORDERED

SHORT-CLOSED

CANCELLED

Terminal States

RECEIVED

SHORT-CLOSED

CANCELLED

Receiving is not allowed in terminal states.

Status Transition Rules (Industry Standard)

DRAFT → OPEN (manual)

OPEN → CANCELLED (only if no receipts exist)

OPEN → BACK-ORDERED (manual)

BACK-ORDERED → OPEN (manual)

OPEN → PARTIALLY-RECEIVED (automatic after first receipt)

BACK-ORDERED → PARTIALLY-RECEIVED (automatic after receipt)

PARTIALLY-RECEIVED → RECEIVED (automatic when fully received)

PARTIALLY-RECEIVED → SHORT-CLOSED (manual when remaining qty is short-closed)

Receiving Model (Core of This PR)

Receiving is event-based, not status-based.

New Table — purchase_order_receipts (header)

id (PK)

tenant_id (FK)

purchase_order_id (FK)

received_at (datetime)

received_by_user_id (FK → users)

reference (nullable)

notes (nullable)

New Table — purchase_order_receipt_lines

id (PK)

tenant_id (FK)

purchase_order_receipt_id (FK)

purchase_order_line_id (FK)

received_quantity (BCMath string, canonical scale)

Inventory Impact

Each receipt line must create:

stock_moves.type = RECEIPT

stock_moves.status = POSTED

Receipts exist for audit trail.
Stock moves exist for inventory truth.

Short-Close Model

Short-close is line-level.

Users record the remaining quantity that will never be received.

When all remaining quantities are either:

Received

Short-closed

The PO becomes SHORT-CLOSED.

Permissions

New permission slug:

purchasing-purchase-orders-receive

This permission controls:

Receiving

Short-closing

Status changes beyond DRAFT and OPEN

UI Requirements
PO Index

Show status column

Row actions menu includes:

View

Receive (opens slide-over)

Contextual status actions

PO Show Page

Status control (dropdown/actions)

“Receive” button at PO level (multi-line)

“Receive” button per line (single-line)

Slide-over receive panel (AJAX)

Receiving UX Rules
Action Behavior
Receive from PO Multi-line receipt
Receive from line Single-line receipt
Submit receipt Creates receipt + stock moves + auto status update
Validation 422 JSON, no page refresh

Receiving allowed only when status is:

OPEN

BACK-ORDERED

PARTIALLY-RECEIVED

Cancellation Rule

Allowed only if no receipts exist

After receiving begins, user must short-close instead

AJAX / Controller Pattern

All endpoints are JSON-only and must follow the Ajax CRUD pattern:

Receive

Short-close

Status change

No redirects. No page refresh.

Tests (Pest Feature)

Tests must cover:

All status transitions (allow + deny)

Receiving rules

Stock move creation

Cancel prevention after receipt

Short-close rules

Permission allow/deny

Terminal state protections

Minimum 20 tests per file.

Documentation Impact

This PR requires updates to:

ENUMS.md (PO statuses)

DB_SCHEMA.md (receipt tables)

ARCHITECTURE_INVENTORY.md (Receipt + Lifecycle patterns)

Architecture YAML files for:

PO Lifecycle

## Receipt Event Pattern

---

### PR2-PUR-006 — Receiving Inventory Impact Fix

Goal
Ensure purchase order receiving always impacts inventory via stock moves.

Problem Statement
Current behavior allows purchase orders to reach RECEIVED without reliably creating stock moves.

Includes

Enforce stock move creation for every receipt line
Ensure:
stock_moves.type = RECEIPT
stock_moves.status = POSTED
Guarantee linkage between receipt lines and stock moves
Ensure idempotency (no duplicate stock moves per receipt line)

Rules

Inventory impact is mandatory for all receipts
A PO cannot be considered RECEIVED unless inventory is updated
All quantity math uses BCMath (scale = 6)
No float math
Stock moves are the single source of truth

Validation

Receipt must fail if stock move creation fails
Use transactional integrity (receipt + stock moves)

Testing

Receipt creates stock moves
Duplicate prevention
Multi-line receipts
Status transitions tied to inventory
Permission enforcement

Documentation Impact

Clarify invariant: receiving always creates stock moves
Update architecture docs only if required

## DOMAIN 5 — Shared UI Infrastructure (As Needed)

> Created **only when duplication appears**.

- Top-nav dropdown
- Actions dropdown (⋮)
- Slide-over
- Modal
- Toast
- Empty state
- Confirm dialog

Each component:

- Single responsibility
- Documented in `ARCHITECTURE_INVENTORY.md`

---

## End State

After PR2 completion:

- Fully usable MRP for small-batch food manufacturers
- Clear dependency-aware UX
- Minimal, calm Breeze-aligned UI
- No UI-driven domain logic
- Clean, reviewable PR history

---

### PR2-UOM-TEN-001 — Tenant-Scoped Units of Measure (Schema + Refactor) ✅ (Implemented)

**Problem Statement**  
UoM Categories and Units required tenant ownership to match CRUD access expectations.

**Implementation Note**  
Tenant scoping is already implemented in the current codebase.

**Includes**

- Add `tenant_id` to:
    - `uom_categories`
    - `uoms`
- Backfill strategy
- Apply `HasTenantScope`
- Update tests
- Update `ARCHITECTURE_INVENTORY.md`
- Update `DB_SCHEMA.md`

**Result**

- UoM Categories and UoMs are tenant-owned, while system defaults continue to use `tenant_id = null`.

---

## DOMAIN 5 — UI Component Refactor (Post-PR2 Cleanup)

### PR2-UI-001 — Remove Breeze UI Components

**Goal**  
Replace Breeze Blade UI components with Tailwind-only markup.

**Includes**

- Replace Breeze nav + dropdown components
- Preserve routes, permissions, and behavior
- No visual redesign

**Out of Scope**

- Domain logic changes
- New features

**Testing**

- No new domain tests
- Optional UI smoke checks

---

### PR2-UOM-003 — UoM Display Precision + Global Quantity Formatting (UI-only) ✅ (Implemented)

**Goal**  
Introduce a UoM-level display precision field and enforce consistent quantity formatting across all UI views.

**Includes**

- Add `display_precision` to `uoms` (required, default = 1, allowed range = 0–6)
- Extend UoM CRUD UI to manage `display_precision`
- Introduce a single `QuantityFormatter` abstraction for centralized display formatting
- Introduce a Blade directive/helper that wraps the formatter
- Replace all quantity rendering in Blade views across all domains:
- Materials
- Inventory
- Inventory Counts
- Recipes
- Make Orders
- Purchasing (Orders, Receipts, Short-Closures)
- Enforce trailing zeros to match display precision
- Use string-safe half-up display rounding without changing storage precision
- No changes to storage math or BCMath canonical scale (remains 6)

**Rules**

- `display_precision` cannot exceed 6
- `display_precision` may be 0 (whole-unit display)
- Formatting is UI-only; storage precision is unchanged
- Formatting must be centralized (no ad-hoc formatting in views)
- No JavaScript-side formatting
- No global JavaScript state

**Permissions**

- No new permission slugs
- Managed under existing UoM CRUD permission: `inventory-materials-manage`

**Testing**

- Pest tests only
- Minimum 20 tests per file
- Tests must be complete and sufficient
- Coverage must include:
- Validation bounds (0–6)
- Default value behavior
- Authorization
- Formatter correctness
- Trailing zero enforcement
- Precision 0 edge case
- Precision 6 edge case

**Documentation Impact**

- `DB_SCHEMA.md` must reflect the new `uoms.display_precision` column
- `ARCHITECTURE_INVENTORY.md` must include:
- `QuantityFormatter` abstraction
- Blade directive/helper usage pattern
- `ENUMS.md` unaffected

**Out of Scope**

- Any changes to storage precision or BCMath scale
- JavaScript formatting or UI-only overrides per view
