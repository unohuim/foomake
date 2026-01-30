# DOMAIN: Inventory, Items, Units, and Recipes

## Purpose

This document defines the **canonical domain model** for inventory, units of measure, and recipes
(BOMs) in this MRP system. It is the source of truth for all future implementation work.

This system is designed for **small-batch food manufacturers** operating in a **single-database,
multi-tenant** Laravel application.

No UI behavior is defined here. This document is **domain-first**.

---

## Core Goals

- Track accurate inventory for all stockable items
- Support recursive recipes (products made from products)
- Support multiple units of measure with reliable conversions
- Support supplier-specific purchasing packs and SKUs
- Allow inventory adjustments and variance tracking
- Remain extensible without premature abstraction

---

## Non-Goals (Explicitly Out of Scope)

- Labor / time tracking
- Cost rollups
- Multi-location inventory
- Lot / batch / expiry tracking
- UI workflows or layouts

---

## Fundamental Invariants

1. **Everything stock-tracked is an Item**
    - Raw materials, sub-recipes, finished goods, packaging
    - There is no separate “materials” table at the data level

2. **Each Item has exactly one base unit of measure**
    - This is the unit in which inventory truth is stored
    - Example: barley flour → grams

3. **Inventory truth is ledger-based**
    - On-hand quantity is derived from summing stock movements
    - No cached quantity columns

4. **Recipes consume Items and produce exactly one Item**
    - Recipes may consume other manufactured Items (recursive BOMs)

5. **Supplier pack sizes are not Items**
    - They are purchasing options that roll up into a single Item’s inventory

6. **Cross-category unit conversions are item-specific**
    - Example: 1 patty = 113 g (true only for that item)

---

## Core Entities

### Items

Represents the internal inventory identity.

Key properties:

- Tenant-scoped
- One row per stocked identity
- Flags control behavior (purchasable, sellable, manufacturable)

Used to power:

- Materials view
- Products view
- Recipes view

---

### Units of Measure (UoM)

#### UoM Categories

Examples:

- Mass
- Volume
- Count

#### UoMs

Examples:

- g, kg, lb
- ml, l
- each, piece, patty

#### Global Conversions

- Allowed **only within the same category**
- Example: kg → g

---

### Item-Specific UoM Conversions

Used for **cross-category conversions**.

Examples:

- 1 patty → 113 g
- 1 apple → 182 g

Rules:

- Scoped to (tenant, item)
- Never global
- Used for display, entry, and conversion to base unit

---

## Recipes (Bill of Materials)

- A Recipe:
    - Produces exactly one output Item
    - Consumes one or more input Items
- Inputs may be raw materials or other manufactured Items
- Manufacturable Items may have **many active** Recipes
- Only **one default** Recipe is allowed per (tenant, output Item)
- Setting a Recipe as default unsets any prior default for the same (tenant, output Item)
- Default selection must not affect other tenants or other output Items
- Deleting the default Recipe leaves **no default** (no auto-promotion)

No labor, time, or costing included at this stage.

---

## Inventory (Ledger Model)

### Stock Moves

Every inventory change is a recorded movement.

Movement types include:

- Purchase receipt
- Sale issue
- Production consume
- Production output
- Adjustment
- Waste (optional; may be folded into adjustment)

Rules:

- Quantities are stored in the Item’s base unit
- Entered quantities may use any valid unit convertible to base

---

## Inventory Counts & Variance

- Users may perform ad-hoc inventory counts
- Counts compare system quantity vs physical count
- Posting a count generates adjustment stock moves
- Negative variance may optionally be classified as waste

---

## Supplier Purchasing Options

Represents **how an Item is bought**, not what it is.

Examples:

- Barley flour 20kg bag
- Barley flour 10kg bag

Properties:

- Supplier-specific SKU or product_id
- Optional manufacturer identifier
- Pack quantity + pack UoM
- Converts into Item base unit upon receipt

Multiple purchase options may exist for the same Item.

---

## UI Implications (Non-Binding)

These are **views over Items**, not separate data models:

- Materials
- Products
- Recipes

UI must never be treated as the source of truth.

---

## Planned PR Breakdown

1. Items + Units of Measure (no inventory)
2. Item-specific UoM conversions
3. Inventory ledger (stock moves)
4. Recipes (BOMs)
5. Supplier purchase options

Each PR:

- Smallest possible change
- Tests first
- No UI unless explicitly requested

---

## Authority

This document must comply with:

1. docs/AI_RULES.md
2. docs/CONVENTIONS.md
3. docs/ARCHITECTURE_INVENTORY.md
4. docs/PERMISSIONS_MATRIX.md
5. Existing tests

If conflict exists, higher authority wins.
