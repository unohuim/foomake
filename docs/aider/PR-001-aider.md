# AIDER PROMPT — PR-001 Items & Units of Measure

Read and obey, in order:

- docs/AI_RULES.md
- docs/CONVENTIONS.md
- docs/ARCHITECTURE_INVENTORY.md
- docs/PERMISSIONS_MATRIX.md
- docs/DOMAIN_INVENTORY_AND_RECIPES.md
- docs/PR_ROADMAP.md

## Goal

Introduce the Item model and Units of Measure foundation.
NO inventory, NO recipes, NO UI.

## In Scope

- Migrations: items, uom_categories, uoms, uom_conversions (within-category only)
- Models for the above
- Tests proving base UoM + category-safe conversion

## Out of Scope

Inventory, recipes, purchasing, UI, refactors.
