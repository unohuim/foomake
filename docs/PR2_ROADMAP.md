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

---

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

---

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

---

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

## DOMAIN 2 - PR2-INV-001 — Inventory Visibility & Counts

_(Unchanged)_

- Inventory Overview
- Inventory Counts (Draft)
- Inventory Count Posting

---

## DOMAIN 3 — Recipes & Manufacturing

_(Unchanged)_

- Recipes CRUD
- Recipe Lines
- Make Orders (Execute Recipe)

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
UoM Categories and Units are currently **global**, but CRUD access implies tenant ownership, creating a domain inconsistency.

**Decision**  
Defer tenancy alignment to a **dedicated PR** to avoid scope creep in PR2-UOM-001 / PR2-UOM-002.

**Includes (Planned)**

- Add `tenant_id` to:
    - `uom_categories`
    - `uoms`
- Backfill strategy for existing records
- Apply `HasTenantScope` to UoM models
- Update authorization + tenant isolation tests
- Update `docs/ARCHITECTURE_INVENTORY.md`
- Update `docs/DB_SCHEMA.md`

**Explicitly Out of Scope (for now)**

- Any tenancy changes to UoMs or categories in PR2-UOM-001 / PR2-UOM-002

**Rationale**

- Preserves small, reviewable PRs
- Avoids schema churn mid-domain
- Makes tenancy change explicit, test-driven, and auditable

---

## DOMAIN 5 — UI Component Refactor (Post-PR2 Cleanup)

### PR2-UI-001 — Remove Breeze UI Components (Refactor)

**Goal**  
Replace Breeze Blade UI components with project-owned Tailwind-only markup, without changing domain behavior.

**Motivation**  
Breeze components (`x-nav-link`, `x-dropdown`, `x-dropdown-link`, etc.) are scaffolding conveniences. This project should own its UI primitives directly using Tailwind patterns.

**Includes**

- Replace Breeze navigation components with explicit Tailwind markup:
    - Top nav links
    - Dropdown triggers + dropdown links
    - Responsive nav behavior
- Replace any other Breeze UI components introduced during PR2 (only if present)
- Preserve existing routes, permissions, and page behavior
- No visual redesign beyond matching current UI closely

**Out of Scope**

- Any domain logic changes
- Any new features or UX redesign
- Rewriting already-stable screens unless they depend on Breeze components

**Testing**

- No new domain tests required
- Add minimal UI smoke coverage only if existing patterns allow (optional)

**Notes**

- Keep changes tightly scoped: only replace components currently in use.
- Prefer incremental replacements as files are touched, but this PR is the dedicated cleanup.

---
