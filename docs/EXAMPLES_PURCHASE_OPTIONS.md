# Examples: Supplier Purchase Options & Receiving

Canonical examples used as fixtures.

---

## Example 1 — 10kg Pack

Item:

- Base UoM: g

Conversion:

- 1 kg = 1000 g

Receive:

- 1 × 10kg pack

Result:

- receipt, 10000 g

---

## Example 2 — 20kg Pack

Receive:

- 2 × 20kg packs

Result:

- receipt, 40000 g

---

## Example 3 — Cross-Category

Item:

- Base UoM: g
- 1 patty = 113 g

Pack:

- 40 patties

Result:

- receipt, 4520 g

---

## Example 4 — Invalid

Cross-category without item conversion

Result:

- throw
- no stock_moves
