# PR ROADMAP — Inventory & Recipes Domain

This document defines the **ordered, non-negotiable PR sequence** for implementing
the inventory and recipes domain.

Each PR must:

- Be the smallest possible change
- Introduce one new invariant
- Be test-driven
- Introduce **no UI** unless explicitly stated
- Reference DOMAIN_INVENTORY_AND_RECIPES.md

---

## PR-001 — Items & Units of Measure (Foundations)

**Goal**
Establish the core inventory identity and unit system.

**Introduces**

- `items`
- `uom_categories`
- `uoms`
- `uom_conversions` (within-category only)

**Key Invariants**

- One Item = one inventory identity
- Each Item has exactly one base UoM
- Global conversions are category-safe only

**Explicitly Out of Scope**

- Inventory quantities
- Recipes
- Supplier data
- UI

---

## PR-002 — Item-Specific Unit Conversions

**Goal**
Enable cross-category conversions that are true only for a specific Item.

**Introduces**

- `item_uom_conversions`

**Key Invariants**

- Cross-category conversions are item-scoped
- Never global
- Required for count ↔ weight (e.g., patties, apples)

**Explicitly Out of Scope**

- Inventory ledger
- Recipes
- Purchasing
- UI

---

## PR-003 — Inventory Ledger (Stock Moves)

**Goal**
Establish ledger-based inventory truth.

**Introduces**

- `stock_moves`
- On-hand calculation via aggregation

**Key Invariants**

- Inventory is derived, never stored
- All quantity changes are ledger entries
- Quantities stored in Item base UoM

**Explicitly Out of Scope**

- Recipes
- Supplier pack logic
- Costing
- UI

---

## PR-004 — Inventory Counts & Adjustments

**Goal**
Allow physical inventory counting and variance correction.

**Introduces**

- `inventory_counts`
- `inventory_count_lines`
- Adjustment posting to `stock_moves`

**Key Invariants**

- Counts do not mutate stock directly
- Posting creates ledger entries
- Variance is auditable

**Explicitly Out of Scope**

- Waste classification (optional later)
- UI workflows

---

## PR-005 — Recipes (Bill of Materials)

**Goal**
Enable manufacturing via recursive recipes.

**Introduces**

- `recipes`
- `recipe_lines`
- Production consume/output stock moves

**Key Invariants**

- Many active recipes per manufacturable Item are allowed
- One default recipe per (tenant, output Item) is allowed
- Recipes consume Items and produce one Item
- Sub-recipes are allowed

**Explicitly Out of Scope**

- Labor / time
- Cost rollups
- UI editors

---

## PR-006 — Supplier Purchase Options

**Goal**
Support supplier-specific pack SKUs that roll up into a single Item.

**Introduces**

- `item_purchase_options`
- Pack-size receiving logic

**Key Invariants**

- Purchase packs are not inventory identities
- Receiving converts pack → base UoM
- Multiple supplier SKUs map to one Item

**Explicitly Out of Scope**

- Purchasing workflows
- Supplier pricing logic
- UI

---

## PR-007 — Inventory Waste Classification (Optional)

**Goal**
Differentiate waste from generic adjustments.

**Introduces**

- Waste stock move classification
- Optional reporting hooks

**Key Invariants**

- Waste is explicit and auditable
- Does not change inventory math rules

---

## Notes

- PRs must be completed in order.
- Skipping ahead is not allowed.
- UI work begins **only after PR-006** and requires explicit approval.
- Each PR should have a corresponding aider prompt stored in `docs/aider/`.

---

## Authority

This roadmap must comply with:

1. docs/AI_RULES.md
2. docs/CONVENTIONS.md
3. docs/ARCHITECTURE_INVENTORY.md
4. docs/PERMISSIONS_MATRIX.md
5. Existing tests
