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

## DOMAIN 4 — Suppliers & Purchasing

_(Unchanged)_

- Suppliers CRUD
- Item Purchase Options
- Purchase Orders

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
