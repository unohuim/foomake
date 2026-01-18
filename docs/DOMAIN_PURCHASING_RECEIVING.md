# DOMAIN: Purchasing Receiving (Supplier Pack Options)

## Purpose

Define the canonical receiving contract for supplier purchase options that roll into Item inventory.

This document removes ambiguity for implementation and testing. It defines what receiving means
at the domain level — not workflows or UI.

---

## Scope

Applies only to PR-006 — Supplier Purchase Options.

In scope:

- Supplier pack → Item inventory conversion
- Ledger (stock_moves) effects
- Validation and failure rules

Out of scope:

- Purchase orders
- Supplier pricing
- UI
- Costing
- Planning or forecasting

---

## Core Invariants

1. Supplier purchase options are not inventory identities
2. Receiving always creates ledger entries
3. All inventory is stored in Item base UoM
4. No implicit conversions

---

## Receiving Contract

Inputs:

- ItemPurchaseOption
- pack_count (decimal > 0)

Required data:

- item_id
- pack_quantity
- pack_uom_id
- item.base_uom_id

---

## Conversion Order

1. total_pack_uom_quantity = pack_quantity × pack_count
2. Convert pack UoM → Item base UoM
3. Create receipt stock_move in base UoM

---

## Failure Rules

Receiving must throw if:

- pack_count <= 0
- pack_quantity <= 0
- required conversion missing
- cross-category conversion without item-specific mapping
- tenant mismatch

---

## Ledger Rules

- Only receipt stock moves
- Enum values per ENUMS.md
- Append-only
- Tenant enforced
