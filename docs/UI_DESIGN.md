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

### Page Module Contract (Enforced)

- Blade templates must not include executable `<script>` tags (JSON payloads only).
- Inline JavaScript handlers in Blade are forbidden; use page modules instead.

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

## UI Execution & Page Module Rules (Mandatory)

Every interactive page **must** follow this contract.

---

### Page Contract (Non-Negotiable)

Each interactive page **must** have:

- A **single root element** with:
    - `data-page="page-slug"`
    - `data-payload="payload-script-id"`
- A **single** `<script type="application/json">` payload block
- **No executable JavaScript** in Blade templates

All UI logic **must** live in:
resources/js/pages/\*\*

---

### Page Module Contract

Each page module **must**:

- Export a `mount(rootEl, payload)` function
- Register its Alpine component **inside `mount`**
- Never assume Alpine has already started

---

### Alpine Boot Order (Critical)

Alpine **must not start** until **after** all page modules are registered.

Required guarantees:

- Page module is resolved
- `Alpine.data(...)` is registered
- `Alpine.start()` runs **exactly once**, afterward

Violations cause:

- `x-data` expressions failing silently
- Production-only hydration bugs
- Inconsistent behavior between dev and build

---

### Production-Safe Module Loading

Dynamic string imports are **forbidden**.

The page loader **must** use:

```js
import.meta.glob("./pages/**/*.js");
```

This ensures:

Vite production builds include all page modules

No missing-module failures after build

Static discoverability of UI logic

---

Alpine Safety Rules (Mandatory)

1. Optional-Chaining Assignment Is Forbidden

This is invalid JavaScript and will break builds:

el?.textContent = value ❌

Required pattern:

const el = ...
if (el) {
el.textContent = value
}

2. Stable Error Object Shapes

Any error object referenced in Blade like:

x-text="errors.name[0]"

must always exist as an array, even when empty.

Forbidden:

errors = {}

Required:

errors = { name: [], base_uom_id: [] }

422 responses must be normalized into this shape.

3. Alpine Expressions Must Be Defensive

Alpine expressions must be safe during:

Initial render

Empty payloads

Validation failures

Post-submit updates

If an expression can throw, it is invalid.

Page-Local Reactivity Rules

UI must update immediately after create/edit/delete

Page refreshes to reflect state are forbidden

Server is source of truth; UI reconciles response data

Arrays must be mutated via:

push

splice

filtered reassignment

Needing a refresh indicates a broken implementation.

Global JavaScript State

No global JS state allowed

window.Alpine permitted only as a compatibility bridge

No page logic may depend on globals

All state must be page-scoped.

Enforcement

Violations of this section are hard blockers.

PRs must be rejected if they introduce:

Inline executable JS in Blade

Incorrect Alpine boot order

Unstable error bindings

Optional-chaining assignments

Page reloads for UI updates

Design Intent

These rules ensure the UI remains:

Predictable

Debuggable

Production-safe

Framework-agnostic

They are mandatory, not stylistic.

::contentReference[oaicite:0]{index=0}
