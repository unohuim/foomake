# UI_DESIGN.md — Canonical UI Direction & Constraints

This document defines the **authoritative UI design rules** for this repository.

Its purpose is to:

- Prevent UI drift across many PRs
- Constrain AI and human contributions
- Preserve a **minimal, modern, Breeze-aligned UI**
- Enable consistent, reusable UI components

This is **not a style guide** or design system.
It is a **set of hard constraints and allowed patterns**.

If a UI decision is not allowed here, it must be explicitly approved.

---

## Authority

This document is **authoritative**.

In the event of conflict:

1. `docs/CONVENTIONS.md`
2. `docs/UI_DESIGN.md`
3. PR-specific notes

All UI-related PRs must comply.

---

## Core UI Philosophy

### Design Ethos

- **Minimalist**
- **Content-first**
- **Low visual noise**
- **Calm by default**
- **Progressive disclosure**

The UI should feel:

- Professional
- Quiet
- Modern
- Unopinionated
- Operational (not “dashboardy”)

---

## Layout Rules

### Global Layout

- Use **Laravel Breeze default layout** as the baseline
- No sidebars
- No card-heavy dashboards unless justified
- White/light neutral background
- Generous whitespace

### Navigation

- **Top horizontal navigation only**
- Left-aligned app identity
- Domain menu items appear inline:
    - Materials
    - Products
    - Recipes
    - Suppliers
    - Purchase Orders
    - Make Orders

- No nested mega-menus initially
- Active state must be subtle (underline or tone shift)

---

## Technology Constraints

### Allowed

- Blade
- Alpine.js
- Native JavaScript
- Tailwind CSS utilities
- AJAX / fetch-based interactions

### Disallowed (without approval)

- SPA frameworks (React, Vue, etc.)
- Global JS state
- Client-side routing
- CSS files or inline styles
- UI libraries (Flowbite, Headless UI, etc.)

---

## Interaction Patterns

### CRUD Philosophy

- **AJAX-first**
- No full-page reloads for CRUD
- Server remains source of truth
- Optimistic UI allowed but must reconcile errors

### Modals & Panels

- Slide-overs preferred for create/edit
- Modals for confirmation and short forms
- Never stack modals

### Tables & Lists

- Clean, flat lists
- No heavy borders
- Subtle dividers only when necessary
- Vertical “⋮” actions menu on the far right
- Row click ≠ edit (explicit actions only)

---

## Empty States

Empty states are **required**, not optional.

Must include:

- Clear statement of absence
- Single primary action (e.g. “Create Material”)
- Calm tone
- No illustrations unless extremely subtle

---

## Feedback & Status

### Required Patterns

- Loading states (skeletons preferred)
- Success toasts (short-lived)
- Inline validation errors
- Non-blocking error messages

### Disallowed

- Alert spam
- Blocking full-page loaders
- Silent failures

---

## Reusable Components Policy

Reusable UI components:

- Must be **explicitly created**, not copy-pasted
- Must be documented briefly in `ARCHITECTURE_INVENTORY.md`
- Must have a **single clear responsibility**

Expected shared components include:

- Dropdown (⋮ actions)
- Modal
- Slide-over
- Toast
- Empty state
- Confirm dialog

---

## Visual Constraints

### Color

- Default Tailwind palette
- No custom brand colors yet
- Use color sparingly and semantically:
    - Red = destructive
    - Yellow = warning
    - Green = success
    - Blue = primary action

### Typography

- Default Breeze typography
- No custom fonts
- No decorative text

---

## Icons

- Heroicons only
- Outline style preferred
- Icons must aid clarity, not decoration

---

## Non-Goals (Explicit)

This UI is **not**:

- A marketing site
- A data visualization playground
- A design experiment
- A SPA
- A mobile-first app (desktop-first for now)

---

## Change Discipline

Any deviation from this document requires:

- Explicit PR note
- Clear justification
- Approval before implementation

---

## Final Principle

If a UI element feels:

> “cool”, “flashy”, or “impressive”

It is probably **wrong** for this system.

## Clarity, calmness, and restraint win.

## UI Quoting & Alpine Safety Rules (Mandatory)

These rules exist to prevent silent Alpine parsing failures and Blade-rendered JavaScript leakage into the UI.

### HTML Attribute Quoting

- All HTML attributes MUST use double quotes (").
- Single-quoted HTML attributes are forbidden.

Correct:

<div x-data="{}"></div>

Incorrect:

<div x-data='{}'></div>

### Alpine.js JavaScript String Quoting

Inside any Alpine directive (x-data, x-init, x-on, @click, etc.):

- All JavaScript string literals MUST use single quotes (').
- Double quotes are forbidden inside Alpine JS objects.

This applies to URLs, method names, headers, error messages, and manually written JSON keys.

### Blade + Alpine Interop Rule

Blade helpers inside Alpine must not introduce double quotes into the Alpine JS context.
Alpine must only see single-quoted JS strings.

### Why This Rule Exists

Alpine expressions live inside HTML attributes.
Mixing quote types causes silent Alpine parse failures and raw JavaScript rendering in the UI.

### Enforcement Expectations

PR reviewers must reject violations.
AI-generated UI code must be corrected before commit.
Investigate quote violations first when JS appears in rendered UI.

---

## Navigation Model — Process-Based Domains

The application uses **process-based top-level navigation**, not entity-based navigation.

Top-level items represent **business functions**.  
Entities may appear in multiple domains with **domain-specific behavior and attributes**.

### Top-Level Navigation

- **Sales**
- **Purchasing**
- **Manufacturing**
- **Reports**

---

### Sales Domain

Focus: revenue generation and customer fulfillment.

**Dropdown items:**

- Orders
- Customers
- Products  
  _(Items where `is_sellable = true`, with sales-specific attributes such as pricing, taxes, and terms)_

---

### Purchasing Domain

Focus: supplier relationships and inbound procurement.

**Dropdown items:**

- Orders
- Bills / Invoices
- Suppliers
- Products  
  _(Items where `is_purchasable = true`, with purchasing-specific attributes such as pack sizes, costs, and lead times)_

---

### Manufacturing Domain

Focus: production execution and operational primitives.

**Dropdown items:**

- Orders (Make Orders)
- Inventory
- Recipes
- Units of Measure (UoM)
- UoM Categories

Manufacturing owns **inventory mechanics and unit semantics**.  
Sales and Purchasing consume these primitives but do not define them.

---

### Design Rationale

- Navigation reflects **how the business operates**, not how data is stored.
- Products are **contextual**, not singular — behavior differs per domain.
- Manufacturing centralizes stock, units, and recipes to avoid duplication.
- This structure scales cleanly as domains expand without menu sprawl.

---
