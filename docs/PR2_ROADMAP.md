# PR2_ROADMAP — UI + Domain Completion (Post-PR-006)

This roadmap defines the **second major phase** of work: completing **Items, Inventory, Suppliers, and Manufacturing**
with **modern, minimalist UI**, aligned with **Breeze** and constrained by `UI_DESIGN.md`.

This version explicitly accounts for the **dependency chain**:

> **UoM Category → UoM → Item (Material)**

and introduces a **Materials top-nav dropdown** instead of separate top-level menu items.

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

## Navigation Model (Revised)

- **Top horizontal navigation (Breeze-style)**
- No sidebars
- No dashboard-heavy layouts

### Materials Navigation Pattern

- **Materials** is a **top-level nav item**
- Click → `/materials` (Materials index)
- Hover → dropdown menu with:
    - Materials
    - UoM Categories
    - Units of Measure

This establishes Materials as the _parent domain_ for its required support entities.

---

## DOMAIN 1 — Materials (Foundation)

> Materials are the **first UI domain** because all downstream domains depend on Items.

### PR2-MAT-001 — Materials Navigation + Index ✅ (Completed)

**Goal**  
Expose Materials as a first-class domain with read-only visibility.

**Includes**

- Add **Materials** to top horizontal navigation
- Route: `/materials`
- Gate enforcement: `inventory-materials-view`
- Index view listing all Items

**UI**

- Clean list/table (Tailwind, Breeze-aligned)
- Columns: Name, Base UoM, Flags
- Empty state with “Create Material” CTA (non-functional)

**Out of Scope**

- Create / Edit / Delete
- UoM management

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

- Materials nav dropdown entry: **Units**
- Route: `/materials/uoms`
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

### PR3-MO-001 — Make Orders (Execute Recipe)

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
- No persisted make-order record unless approved

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

This domain introduces **supplier management, supplier-specific material pricing, and purchasing primitives**, with clear separation between **planning prices**, **supplier prices**, and **order price snapshots**.

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

### PR2-MAT-004 — Material Planning Price (Schema + UI)

**Goal**  
Add a **planning-only placeholder price** to materials.

**Includes**

- Schema:
    - `items.default_price_amount`
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
- Delete blocked if supplier has linked materials (future-safe)
- Gate: `purchasing-suppliers-manage`

---

### PR2-PUR-003 — Supplier ↔ Material Catalog + Pricing

**Goal**  
Define which materials are bought from which suppliers, including **supplier-specific pricing**.

**Includes**

- New tenant-owned table/model (recommended): `supplier_item_prices`
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
- Prices represent **expected/current** pricing, not history

---

### PR2-PUR-004 — Purchase Orders (Draft + Pricing Snapshot)

**Goal**  
Create purchase orders with **immutable price snapshots**.

**Includes**

- Nav: **Purchasing → Orders**
- PO index + create draft (AJAX)
- PO lines sourced from supplier-material catalog
- Each PO line stores:
    - `unit_price_amount`
    - `unit_price_currency_code`
    - `converted_unit_price_amount` (tenant currency)
    - `fx_rate`
    - `fx_rate_as_of`
- Gates:
    - View: `purchasing-purchase-orders-view`
    - Create: `purchasing-purchase-orders-create`

**Rules**

- PO prices never change after creation
- Supplier price changes do **not** affect existing POs

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

| Layer          | Location                 | Purpose                  |
| -------------- | ------------------------ | ------------------------ |
| Planning       | `items.default_price_*`  | Forecasting only         |
| Supplier       | `supplier_item_prices.*` | Expected buy price       |
| Purchase Order | `purchase_order_lines.*` | Legal / accounting truth |

---

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

### PR2-UOM-TEN-001 — Tenant-Scoped Units of Measure (Schema + Refactor)

**Problem Statement**  
UoM Categories and Units are currently **global**, but CRUD access implies tenant ownership.

**Decision**  
Defer tenancy alignment to a **dedicated PR**.

**Includes (Planned)**

- Add `tenant_id` to:
    - `uom_categories`
    - `uoms`
- Backfill strategy
- Apply `HasTenantScope`
- Update tests
- Update `ARCHITECTURE_INVENTORY.md`
- Update `DB_SCHEMA.md`

**Out of Scope (for now)**

- Any tenancy changes in PR2-UOM-001 / PR2-UOM-002

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
