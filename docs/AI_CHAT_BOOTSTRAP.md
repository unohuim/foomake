# AI_CHAT_BOOTSTRAP.md — Core Project Context for LLM Sessions

This file is the single source to paste at the beginning of new LLM chats for full project alignment.

Paste the entire content (or as much as context allows) when starting a session.

Authority Order (highest to lowest — conflicts resolved by this order):

1. docs/AI_CHAT_CODEX.md
2. docs/PR2_ROADMAP.md
3. docs/CONVENTIONS.md
4. docs/ARCHITECTURE_INVENTORY.md
5. docs/PERMISSIONS_MATRIX.md
6. docs/ENUMS.md
7. docs/DB_SCHEMA.md
8. docs/UI_DESIGN.md
9. routes/web.php (main web routes — included here for complete bootstrap context)

## docs/AI_CHAT_CODEX.md

# AI Chat Bootstrap (READ FIRST)

You are assisting with development on this repository.

Your role is a **liaison between the human and Codex CLI**, which is connected to an OpenAI agent running in the human’s local development environment.

Codex CLI is allowed to **edit and create application code and test files only**.
Codex (and any AI) may **not modify internal documentation** unless explicitly instructed.

This file exists to bootstrap **new LLM chat sessions** and enforce correct working mode
before _any_ planning or implementation occurs.

When writing prompts for codex, instruct it to write test files first AND entire implementation for the PR, but never ask it to run CI - that's my job. When prompting to write test files, have codex test for at a min of 20 tests per file. Instruct codex to write test files so that they are Complete and Sufficient. In your prompts for entire PRs, ensure that codex includes all required classes AND migration files in its plan.

---

## Rules for how You Respond

1. Never write code or a prompt without approval or an explicit request to do so.
2. Always answer questions with the least amount of words possible(aim < 30 words), without degrading your message. If you have lots of information to provide, start very high level, and offer more detail but don't assume it initially.

---

## Operating Assumptions (Critical)

- You do **not** have implicit context.
- Assume you know **nothing** beyond what is explicitly provided in this chat.
- You must become fully aligned with this project **before proposing or writing any code**.
- Codex is **not an autopilot**. It acts only after explicit approval.
- **The human owns execution** (running tests, committing code, CI).

Read this entire document before beginning bootstrapping.

Your **very first response** after consuming this document must be to begin **document intake**.
Do **not** ask about the PR until all required documents have been consumed.

---

## Bootstrapping Requirements (Mandatory)

Before doing any work, you must reach **≥95% certainty** about:

- The project
- The roadmap
- The current PR scope

Until then, you are in **intake and alignment mode only**.

---

## Step 1 — Document Intake (Strict)

The authoritative documents are listed in **Section 2**. The contents of those documents have been included in this document.

You must:

- Read everything in this document, including those documents that are consolidated into this one.
- Explicitly acknowledge receipt of each document
- **Do not** summarize, critique, or propose changes during intake

---

## Step 2 — Certainty Alignment

After all required documents are provided:

- State your current certainty level
- Ask **clarifying questions one at a time** to increase certainty
- Do **not** propose a plan or solution during this phase

---

## Step 3 — Ready State

Once certainty is **≥95%**:

- Explicitly state that you are ready to assist
- Request approval to propose a plan

No implementation may begin before this point.

---

## 3A. Test-First & Execution Authority (Critical)

This repository follows a **human-in-the-loop test workflow**.

- Codex may **write or modify test files** when explicitly approved.
- **Codex must never run tests unless explicitly instructed.**
- **The human always runs tests manually**, after reviewing and possibly refining
  any AI-generated test drafts.
- When instructed to write tests:
    - Codex must write tests so that they are condsidered Complete and Sufficient, with at min 20 tests per file.
    - Codex must **wait for human review and approval**
    - No application code may be written until tests are approved

This rule exists to:

- Preserve human judgment over correctness and intent
- Avoid AI masking test failures
- Allow collaborative improvement of test quality before execution

Violation of this rule requires immediate stop and correction.

---

## 3B. Post-CI Documentation Update Stage (Conditional, Required When Applicable)

After CI passes and the human considers the PR implementation complete, the next step is:

1. **Ask whether documentation updates are required for this PR.**
2. If the human says **no**, stop (PR is done).
3. If the human says **yes**, proceed with the Documentation Update Workflow below.

Codex may only modify documentation when the human explicitly instructs it to do so.

### Documentation Update Workflow (When Required)

When documentation updates are required, Codex must:

- Update any impacted authoritative docs (see “Authority Order” below) **only as needed**
- Ensure documentation reflects architectural **invariants** and canonical rules
- Keep changes minimal, precise, and easy to diff

This stage is triggered **only when required** (not every PR).

### Architecture Documentation Requirements (When Required)

If the PR introduces or changes an architectural concept, invariant, or reusable pattern:

- Codex must create or update one or more **architecture YAML files** under:
    - `docs/architecture/**.yaml`
- The YAML files must follow the canonical architecture documentation system rules
  defined in:
    - `docs/architecture/README.yaml` (schema, key order, and constraints)

Important:

- `docs/ARCHITECTURE_INVENTORY.md` is **not legacy**.
- When architecture YAML is created/updated, Codex must also update
  `docs/ARCHITECTURE_INVENTORY.md` so it remains useful for LLM bootstrapping.

`docs/ARCHITECTURE_INVENTORY.md` is bootstrap-facing and must stay aligned with:

- `docs/CONVENTIONS.md`
- `docs/ENUMS.md`
- `docs/architecture/**.yaml`

---

## 1. What This Application Is

This application is a **multi-tenant MRP (Manufacturing Resource Planning) system**
for **small-batch food manufacturers**.

It supports:

- Tenants (independent businesses)
- Users with multiple global roles
- Materials & finished products
- Purchasing & suppliers
- Inventory & production (make orders)
- Sales orders, invoicing, and reporting

Each tenant represents **one business**.
All operational data is **tenant-scoped**.

---

## 2. Authority Order (Non-Negotiable)

The following documents are the **source of truth**, in strict priority order:

1. docs/PR2_ROADMAP.md
2. docs/CONVENTIONS.md
3. docs/ARCHITECTURE_INVENTORY.md (bootstrap-facing, required)
4. docs/PERMISSIONS_MATRIX.md
5. docs/ENUMS.md
6. docs/DB_SCHEMA.md
7. docs/UI_DESIGN.md
8. docs/architecture/README.yaml
9. routes/web.php
10. docs/architecture/ui/PageModuleContract.yaml

If any conflict exists, **higher priority always wins**.
For architecture invariants specifically, follow:

- `docs/CONVENTIONS.md`, then `docs/ENUMS.md`, then `docs/architecture/**.yaml`, then code.

---

## 3. Required Working Mode

You must operate in **consultative mode**:

- Never propose a plan unless you are **>95% certain** of requirements
- Increase certainty by asking **one clarifying question at a time**
- Always state your **certainty level** before asking a question
- Do **not** implement anything without explicit approval

If unsure, **stop immediately and ask**.

---

## 4. Core Architecture (High-Level)

- Single-database, multi-tenant architecture
- `tenants.id` is authoritative for tenant-owned data
- Tenant context is resolved from the authenticated user
- User model is **not globally tenant-scoped**
- Users may have multiple global roles
- Roles express **business responsibility**, not UI access
- Permissions are explicit, slug-based
- Authorization enforced via Laravel Gates / Policies
- UI visibility is **never** a source of truth
- A `super-admin` role exists with explicit Gate bypass

---

## 5. Critical Constraints (Do Not Break)

- Laravel authentication flows must continue to work:
    - Registration
    - Login
    - Password reset
- Tenant scoping must not affect unauthenticated auth flows
- “Unauthenticated = no access” enforced via routes/gates,
  **not global model scopes**
- The **smallest possible change per PR**

## docs/PR2_ROADMAP.md

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

- `/materials/{id}` show page
- Read-only summary
- Future placeholder for recipe links

---

## DOMAIN 2 — Inventory (Manufacturing Core)

> This is the operational core of the app: counts, stock moves, and constraints.

### PR2-INV-001 — Inventory Counts: Index + Create (AJAX)

**Goal**  
Create inventory count sessions for physical audits.

**Includes**

- Navigation: **Manufacturing → Inventory**
- Inventory count index page
- “Start Count” AJAX action
- `inventory_counts` table
- `inventory_count_lines` table
- Count lines editable in draft state

**Rules**

- All quantities stored as `decimal(18,6)`
- No negative quantities
- Counts cannot be edited after “submitted”

---

### PR2-INV-002 — Inventory Count Lines + Submit

**Goal**  
Allow adding items to inventory counts and submitting results.

**Includes**

- Add lines to count
- Quantity entry
- Submit action
- Stock moves generated on submit

**Rules**

- Stock moves must be created transactionally
- Differences between expected and counted quantity generate moves
- Counts become immutable after submit

---

### PR2-INV-003 — Inventory Count Review

**Goal**  
Provide post-submit review of count adjustments.

**Includes**

- Read-only count view
- Read-only stock move list
- Audit metadata

---

### PR2-INV-004 — Stock Moves (Index)

**Goal**  
Expose stock move log.

**Includes**

- `/manufacturing/inventory/moves`
- Read-only list of all stock moves

---

### PR2-INV-005 — Inventory Count Edit Lock Enforcement

**Goal**  
Block editing of submitted counts.

**Includes**

- Backend enforcement
- UI error messages

---

### PR2-INV-006 — Inventory Count Delete (Draft Only)

**Goal**  
Allow deletion of draft inventory counts.

**Includes**

- Delete action
- Must be DRAFT

---

### PR2-INV-007 — Inventory Count Export (CSV)

**Goal**  
Export count sheets.

**Includes**

- CSV export action
- All count lines

---

### PR2-INV-008 — Inventory Count Unsubmit

**Goal**  
Allow unsubmit of a count, reversing moves.

**Includes**

- Reverse stock moves
- DRAFT state restore

---

## DOMAIN 3 — Suppliers (Purchasing Core)

> Suppliers are required for purchasing and define which items can be bought.

### PR2-SUP-001 — Suppliers Navigation + Index ✅ (Completed)

**Goal**  
Expose Suppliers with read-only visibility.

**Includes**

- Nav: **Purchasing → Suppliers**
- Route: `/purchasing/suppliers`
- Gate enforcement: `purchasing-suppliers-view`
- Index view listing suppliers

**Out of Scope**

- Create / Edit / Delete

---

### PR2-SUP-002 — Supplier Detail (Read-Only)

**Goal**  
Show supplier summary and purchasable items.

**Includes**

- `/purchasing/suppliers/{id}`
- Purchasable materials list

---

### PR2-SUP-003 — Supplier Item Catalog (Purchasing Products)

**Goal**  
Define which materials are bought from which suppliers, including **supplier-specific pack sizes and prices**.

**Includes**

- Item list filtered to `is_purchasable`
- Pack options per item
- Supplier price entries
- Ability to set default price for each pack
- Currency at supplier level

---

### PR2-SUP-004 — Supplier Item Catalog UI (AJAX)

**Goal**  
Create the UI for managing supplier-specific purchase options, including prices.

---

### PR2-PUR-001 — Purchasing Products Index

**Goal**  
Expose purchasable items in purchasing context.

---

### PR2-PUR-002 — Supplier Price History

**Goal**  
Track changes to supplier prices over time.

---

### PR2-PUR-003 — Purchase Requests (Planning)

**Goal**  
Allow staff to plan and request procurement.

---

### PR2-PUR-004 — Purchase Orders (Draft + Pricing Snapshot)

---

## Goal

Introduce Purchase Orders with **immutable pricing snapshots** captured at the moment a line is added.

This PR establishes the **foundation of the PO system**: draft creation, line management, and permanent price capture.  
Workflow, lifecycle, and advanced status rules are intentionally **out of scope**.

---

## Navigation

**Purchasing → Orders**

Routes introduced:

- `/purchasing/orders` — Index
- `/purchasing/orders/{id}` — Show / Manage lines

---

## Includes (In Scope)

- Purchase Order index page
- Ability to **create a draft PO** via AJAX
- Ability to **delete a PO**
- Ability to **add lines** to a PO via AJAX
- Ability to **remove lines** from a PO via AJAX
- Lines are sourced strictly from the **supplier-material catalog**
- All monetary fields stored as **integer cents**
- All suppliers in this PR use the **same currency as the tenant**
- Taxes are **ignored**
- Shipping is the **only manual monetary input**
- Pricing is **fully calculated**, except shipping

---

## Critical Concept — Pricing Snapshot

When a line is added to a PO, the following fields must be permanently written to the line:

- `unit_price_amount` (supplier currency, integer cents)
- `unit_price_currency_code`
- `converted_unit_price_amount` (tenant currency, integer cents)
- `fx_rate`
- `fx_rate_as_of`

### Immutable Rule

> These values must NEVER change after being written.

If the supplier later changes prices, or FX rates change, **existing PO lines must remain untouched**.

This is the core guarantee of this PR.

---

## Line Endpoints (AJAX)

- Add line to PO
- Remove line from PO

---

## Permissions (Single Gate for This PR)

All PO behavior in this PR is controlled by **one permission only**:

`purchasing-purchase-orders-create`

This permission allows:

- Viewing index
- Viewing a PO
- Creating a PO
- Deleting a PO
- Adding lines
- Removing lines

There is **no separate "view" permission** in this PR.

---

## Purchase Order Header Fields (In Scope)

| Field            | Type            | Notes                         |
| ---------------- | --------------- | ----------------------------- |
| `supplier_id`    | nullable FK     | Required before leaving DRAFT |
| `order_date`     | nullable date   | Required before leaving DRAFT |
| `shipping_cents` | nullable int    | Required before leaving DRAFT |
| `po_number`      | nullable string | Optional forever              |
| `notes`          | nullable text   | Optional                      |
| `status`         | enum            | Defaults to `DRAFT`           |

---

## Draft Behavior

When a user creates a PO:

- A new record is immediately created
- Status is always `DRAFT`
- All header fields are nullable
- User enters header fields manually later
- System does **not** auto-populate anything except defaults

To move out of DRAFT (future PR):

- `supplier_id`
- `order_date`
- `shipping_cents`

---

## Explicitly Out of Scope (Handled in Future PRs)

- Status workflow enforcement
- Receiving inventory
- Cancelling logic
- Taxes
- Multi-currency suppliers
- Editing prices
- Editing snapshot data
- Lifecycle transitions beyond DRAFT
- Accounting integration
- Approval flows

---

### Pricing Layer Invariant

| Layer          | Location                                                          | Purpose                  |
| -------------- | ----------------------------------------------------------------- | ------------------------ |
| Planning       | `items.default_price_cents` + `items.default_price_currency_code` | Forecasting only         |
| Supplier       | `supplier_item_prices.*`                                          | Expected buy price       |
| Purchase Order | `purchase_order_lines.*`                                          | Legal / accounting truth |

---

### PO Header Fields (canonical)

- `supplier_id`
- `order_date`
- `tax_cents`
- `shipping_cents`
- `po_number`
- `notes`
- `status`

---

### PO Line Fields (canonical)

- `item_id`
- `purchase_order_id`
- `pack_count`
- `unit_price_cents`
- `unit_price_amount`
- `unit_price_currency_code`
- `converted_unit_price_amount`
- `fx_rate`
- `fx_rate_as_of`

---

### Draft-Only Rules (Hard Enforced)

Only **Draft** purchase orders can be modified or deleted.

The following actions must be blocked (422) if status != `DRAFT`:

- Add line
- Remove line
- Delete PO

---

### Pricing Snapshot Enforcement

Pricing is **snapshotted at line creation** and must never change after that.

### Example

Supplier changes a pack price from $10 → $12.  
Existing PO lines remain $10.

---

### Draft-only Totals

Subtotal and grand total are stored on the PO and must be recalculated on any line change.

### Always recalculated on:

- Add line
- Remove line

### Totals formula:

```
subtotal = sum(line_subtotal)
grand_total = subtotal + shipping
```

---

### Notes

- Taxes ignored in this PR
- FX support is shallow; suppliers share tenant currency
- Full lifecycle in future PR

## docs/CONVENTIONS.md

# Conventions

This document defines the **mandatory development conventions** for this repository.  
All human and AI-assisted contributions must comply.

These rules are contractual.

---

## Core Principles

- Prefer **clarity over cleverness**
- Prefer **explicit behavior over implicit magic**
- Prefer **small, reviewable changes**
- Match **existing patterns** before proposing new ones
- Architecture and rules are enforced through tests and documentation

---

## Project Structure

- Follow Laravel default structure unless explicitly documented
- Do not introduce new top-level directories without approval
- Configuration belongs in `config/`, not application logic
- Shared logic must be centralized
- Duplication is acceptable only when intentional and scoped

---

## PHP Conventions

- **PSR-12** formatting is mandatory
- PHPDoc is required for:
    - Classes
    - Public and protected methods
    - Complex private methods
    - Non-obvious parameters or return values
- Use strict typing where appropriate
- Avoid magic strings and numbers when meaning matters

---

## Database & Migrations

- Migrations must be **explicit and reversible**
- Never combine unrelated schema changes in a single migration
- Never modify migrations after they have been applied
- Seeders must be deterministic and safe to re-run

---

## Testing Conventions

- All new automated tests MUST be written using **Pest**.
- PHPUnit-style test classes MUST NOT be introduced for new tests.
- Existing PHPUnit tests may remain untouched unless explicitly refactored.
- If test framework usage is unclear, AI must stop and ask before proceeding.

- Tests are written **before or alongside** behavior changes
- Feature tests are preferred for user- or domain-facing behavior
- Tests must assert **intent**, not implementation details
- Avoid brittle tests that rely on ordering, timing, or side effects

- Every permission must have explicit allow/deny tests
- Tests must assert both positive and negative cases

### Test File Safety Rules

To avoid cross-test contamination and CI-only failures:

- **Do not declare global functions in test files.**  
  Pest loads all test files into a shared PHP runtime; global helper functions
  (`function foo() {}`) will collide across files.
    - Use `beforeEach()` closures (`$this->makeX = fn () => ...`) instead.
    - Alternatively, define helpers as local closures inside the test.

- **When asserting or throwing PHP built-in exceptions, use fully-qualified names**  
  (e.g. `\DomainException::class`) or a proper `use` import.
    - Avoid bare `DomainException::class` without qualification, which can lead to
      warnings like “use statement has no effect” or inconsistent behavior.

These rules are mandatory for all new tests.

---

## Frontend Conventions

- Blade is the primary templating system
- Alpine.js is used for lightweight interactivity
- No global JavaScript state unless explicitly approved
- Prefer progressive enhancement over JavaScript-first solutions

---

## Naming & Readability

- Names must be descriptive and intention-revealing
- Avoid abbreviations unless widely understood
- Favor readability over brevity

---

## Dependency Management

- All dependencies must be explicitly declared
- `composer.lock` and `package-lock.json` are authoritative and must be committed
- Do not upgrade dependencies without justification and review

---

## Error Handling & Validation

- Fail fast when assumptions are violated
- Validate inputs at boundaries (controllers, requests, jobs)
- Do not silently swallow errors

---

## Documentation

- Update documentation when rules, workflows, or architecture change
- Inline comments explain **why**, not **what**
- README and docs files are part of the repository contract

---

## Deviations

- Deviations require explicit approval
- Temporary deviations must document intent and follow-up

---

## Architecture Inventory

Reusable abstractions and patterns are tracked centrally.

- All reusable backend or frontend abstractions **must** be recorded in  
  `docs/ARCHITECTURE_INVENTORY.md`
- Each entry must include:
    - Purpose
    - Location
    - Public interface
    - Example usage
- New abstractions require:
    - Justification in the PR
    - A same-PR inventory entry

---

## Security

- Treat all user input as untrusted
- Use Laravel’s built-in security features
- Do not introduce security-sensitive changes without explicit review
- Secrets, credentials, and tokens must never be committed

---

## Performance

- Prefer correctness and clarity before optimization
- Watch for N+1 queries, redundant work, and unnecessary loops
- Performance optimizations require measurable justification
- Do not introduce caching, queues, or background jobs without approval

---

## Versioning & Compatibility

- Follow semantic versioning where applicable
- Avoid breaking changes without approval
- Database and API changes must consider backward compatibility
- Deprecations must be intentional and staged

---

## Styling & CSS

- **Tailwind CSS is the only permitted styling system**
- No native CSS files or inline styles
- All styling must use Tailwind utility classes
- Customizations require Tailwind config changes and approval

---

## Authorization & Roles

This application uses **global roles** and **domain-based permissions**.

### Principles

- Roles describe **job responsibility**, not UI access
- Authorization is enforced at **domain boundaries**
- Views are never a source of truth

### Role & Permission Model

- Users may have **multiple roles**
- Roles grant permissions via permission slugs
- Permissions are enforced using **Laravel Gates**
- `super-admin` bypasses all checks via `Gate::before`

### Authorization Rules

- No hard-coded role checks in controllers or views
- All access checks must use Gates or Policies
- Permission slugs are canonical and domain-oriented

### Naming Conventions

Permission slugs follow:

{domain}-{resource}-{action}

Examples:

- `sales-customers-view`
- `inventory-products-manage`
- `purchasing-purchase-orders-create`

---

## Tenancy Requirements

- Single-database, tenant-scoped architecture
- All tenant-owned tables **must** include `tenant_id`
- Tenant scoping is enforced by default via model scope
- Global tables must be explicitly documented as global
- The first user created for a tenant is automatically assigned `admin`

## This rule is foundational and non-negotiable.

## Decimal Quantity Math (Inventory & Purchasing)

To avoid floating-point rounding errors, all quantity math in inventory and purchasing
domains must follow these rules:

- **All quantities are represented as strings**, never floats.
- **All arithmetic must use BCMath** (`bcadd`, `bcmul`, `bcdiv`, etc.).
- A **single canonical scale** must be used everywhere, matching
  `stock_moves.quantity` (e.g. scale = 6).
- **No implicit casting** to float at any point.
- **No alternative math libraries** (e.g. BigDecimal, Brick\Math) may be introduced
  without explicit approval.

These rules apply to:

- Stock moves
- Receiving logic
- Unit conversions
- Any inventory-affecting calculations

## docs/ARCHITECTURE_INVENTORY.md

# Architecture Inventory

This document lists reusable architectural components, patterns, and conventions already present in the codebase.  
It is intended to support **new contributors and AI tools**, and must remain aligned with core architecture YAML files.

**This is a bootstrap document only.** The YAML files under `docs/architecture/` are authoritative.

---

## Core Patterns

### 1. Tenant Scoping via HasTenantScope Trait

**Purpose:** Ensure tenant-owned models are automatically scoped to the current tenant.

**Location:** `app/Models/Concerns/HasTenantScope.php`

**Interface:** `use HasTenantScope;`

**Usage Example:**

```php
class Item extends Model
{
    use HasTenantScope;
}
```

---

### 2. Domain-Scoped Authorization via Gates

**Purpose:** Enforce domain permissions using named gates and role-permission mappings.

**Location:** `app/Providers/AuthServiceProvider.php`

**Interface:** `Gate::define('domain-permission-slug', fn ($user) => ...)`

**Usage Example:**

```php
Gate::authorize('inventory-materials-manage');
```

---

### 3. AJAX CRUD Controller Pattern

**Purpose:** Standardize JSON-based create/update/delete endpoints with validation and stable error shape.

**Location:** `docs/architecture/ui/AjaxCrudControllerPattern.yaml`

**Interface:**

- `store(...)` returns JSON + 201
- `update(...)` returns JSON + 200
- `destroy(...)` returns JSON + 200
- Validation errors return JSON + 422

---

### 4. Page Module Contract

**Purpose:** Enforce safe UI composition with JSON payloads and page-scoped JS modules.

**Location:** `docs/architecture/ui/PageModuleContract.yaml`

**Interface:**

- Blade: JSON payload only
- JS page module exports `mount(root, payload)`
- `data-page` + `data-payload` required

---

### 5. Page-Scoped Toast Pattern

**Purpose:** Standardize success/error toasts in page modules.

**Location:** `docs/architecture/ui/PageScopedToastPattern.yaml`

**Interface:**

- `showToast(type, message)`
- Types: success / error

---

### 6. Row Actions Dropdown Pattern

**Purpose:** Shared UI for per-row actions (edit/delete).

**Location:** `docs/architecture/ui/RowActionsDropdownPattern.yaml`

**Interface:**

- `⋮` menu on right
- Action list inside dropdown

---

### 7. Slide-Over Form Pattern

**Purpose:** Standard UI for create/edit forms.

**Location:** `docs/architecture/ui/SlideOverFormPattern.yaml`

**Interface:**

- Right-side panel
- Title + description
- Save/cancel actions

---

### 8. Materials Top Nav Dropdown Pattern

**Purpose:** Standard placement of Materials domain items in top nav.

**Location:** `docs/architecture/ui/MaterialsTopNavDropdownPattern.yaml`

**Interface:**

- Top-nav dropdown
- Materials
- UoM Categories
- UoMs

---

## Architecture References

These YAML files contain authoritative structural rules.

- `docs/architecture/tenancy/TenantScopeTrait.yaml`
- `docs/architecture/tenancy/SingleDatabaseTenantScoping.yaml`
- `docs/architecture/auth/PermissionModel.yaml`
- `docs/architecture/ui/AjaxCrudControllerPattern.yaml`
- `docs/architecture/ui/PageModuleContract.yaml`

---

**Note:** This file is derived and must stay aligned with the YAML architecture definitions.

## docs/PERMISSIONS_MATRIX.md

# Permissions Matrix

This document maps permission slugs to allowed roles.

Permissions are enforced through Laravel Gates.

---

## Roles (global)

- `super-admin`
- `admin`
- `purchasing`
- `inventory`
- `manufacturing`
- `sales`

---

## Permissions

| Permission Slug                      | Allowed Roles                  |
| ------------------------------------ | ------------------------------ |
| `inventory-materials-view`           | super-admin, admin, inventory  |
| `inventory-materials-manage`         | super-admin, admin, inventory  |
| `inventory-uom-categories-manage`    | super-admin, admin, inventory  |
| `inventory-uoms-manage`              | super-admin, admin, inventory  |
| `inventory-items-manage`             | super-admin, admin, inventory  |
| `inventory-items-view`               | super-admin, admin, inventory  |
| `purchasing-suppliers-view`          | super-admin, admin, purchasing |
| `purchasing-suppliers-manage`        | super-admin, admin, purchasing |
| `purchasing-supplier-catalog-manage` | super-admin, admin, purchasing |
| `purchasing-item-prices-manage`      | super-admin, admin, purchasing |

## docs/ENUMS.md

# ENUMS

Canonical enums used across the system.

---

## User Roles

- `super-admin`
- `admin`
- `inventory`
- `purchasing`
- `manufacturing`
- `sales`

---

## Supplier Type

- `LOCAL`
- `IMPORT`

---

## Stock Move Type

- `ADJUSTMENT`
- `RECEIPT`
- `CONSUMPTION`
- `PRODUCTION`

---

## Stock Move Status

- `DRAFT`
- `SUBMITTED`
- `POSTED`

---

## Item Purchase Option Type

- `CASE`
- `PALLET`

---

## Item Type

- `RAW`
- `PACKAGING`
- `FINISHED`

## docs/DB_SCHEMA.md

# DB_SCHEMA

This document reflects the canonical database structure (single DB, tenant scoped).
Migrations are authoritative; this file is contextual.

The format below must be preserved when updating.

---

## Tables

- inventory_counts
- inventory_count_lines
- inventory_items
- item_purchase_options
- item_purchase_option_prices
- item_uom_conversions
- items
- permissions
- purchase_order_lines
- purchase_orders
- role_permission
- roles
- stock_moves
- supplier_item_prices
- suppliers
- tenants
- uom_categories
- uom_conversions
- uoms
- users

---

## inventory_counts

**Tenant-owned:** Yes  
**Purpose:** Stock count sessions

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id         | bigint    | No       | Primary key               |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE) |
| status     | string    | No       | See ENUMS.md              |
| created_at | timestamp | Yes      | —                         |
| updated_at | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(id, tenant_id)`
- Index: `tenant_id`

---

## inventory_count_lines

**Tenant-owned:** Yes  
**Purpose:** Count line items

### Columns

| Name               | Type          | Nullable | Notes                     |
| ------------------ | ------------- | -------- | ------------------------- |
| id                 | bigint        | No       | Primary key               |
| tenant_id          | bigint        | No       | FK → tenants.id (CASCADE) |
| inventory_count_id | bigint        | No       | FK → inventory_counts.id  |
| item_id            | bigint        | No       | FK → items.id (CASCADE)   |
| counted_quantity   | decimal(18,6) | No       | Quantity counted          |
| expected_quantity  | decimal(18,6) | No       | Snapshot at time of count |
| created_at         | timestamp     | Yes      | —                         |
| updated_at         | timestamp     | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `(inventory_count_id, item_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`

---

## inventory_items

**Tenant-owned:** Yes  
**Purpose:** Items in inventory scope

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id         | bigint    | No       | Primary key               |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE) |
| item_id    | bigint    | No       | FK → items.id (CASCADE)   |
| is_active  | boolean   | No       | —                         |
| created_at | timestamp | Yes      | —                         |
| updated_at | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Implicit (FK index): `item_id`

---

## item_purchase_options

**Tenant-owned:** Yes  
**Purpose:** Purchase pack options per item

### Columns

| Name        | Type      | Nullable | Notes                     |
| ----------- | --------- | -------- | ------------------------- |
| id          | bigint    | No       | Primary key               |
| tenant_id   | bigint    | No       | FK → tenants.id (CASCADE) |
| item_id     | bigint    | No       | FK → items.id (CASCADE)   |
| name        | string    | No       | —                         |
| pack_size   | string    | No       | Quantity string (BCMath)  |
| pack_uom_id | bigint    | No       | FK → uoms.id (CASCADE)    |
| created_at  | timestamp | Yes      | —                         |
| updated_at  | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `(item_id, pack_uom_id)`
- Implicit (FK index): `item_id`
- Implicit (FK index): `pack_uom_id`

---

## item_purchase_option_prices

**Tenant-owned:** Yes  
**Purpose:** Prices for each purchase option

### Columns

| Name                    | Type      | Nullable | Notes                                   |
| ----------------------- | --------- | -------- | --------------------------------------- |
| id                      | bigint    | No       | Primary key                             |
| tenant_id               | bigint    | No       | FK → tenants.id (CASCADE)               |
| item_purchase_option_id | bigint    | No       | FK → item_purchase_options.id (CASCADE) |
| supplier_id             | bigint    | No       | FK → suppliers.id (CASCADE)             |
| price_amount            | integer   | No       | Unsigned, snapshot cents                |
| price_currency_code     | char(3)   | No       | ISO currency                            |
| created_at              | timestamp | Yes      | —                                       |
| updated_at              | timestamp | Yes      | —                                       |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `item_purchase_option_id`
- Index: `supplier_id`

---

## item_uom_conversions

**Tenant-owned:** Yes  
**Purpose:** UoM conversions per item

### Columns

| Name        | Type          | Nullable | Notes                     |
| ----------- | ------------- | -------- | ------------------------- |
| id          | bigint        | No       | Primary key               |
| tenant_id   | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id     | bigint        | No       | FK → items.id (CASCADE)   |
| from_uom_id | bigint        | No       | FK → uoms.id (CASCADE)    |
| to_uom_id   | bigint        | No       | FK → uoms.id (CASCADE)    |
| multiplier  | decimal(18,8) | No       | —                         |
| created_at  | timestamp     | Yes      | —                         |
| updated_at  | timestamp     | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `item_id`
- Index: `(from_uom_id, to_uom_id)`
- Implicit (FK index): `from_uom_id`
- Implicit (FK index): `to_uom_id`

---

## items

**Tenant-owned:** Yes  
**Purpose:** Canonical item records

### Columns

| Name                        | Type      | Nullable | Notes                     |
| --------------------------- | --------- | -------- | ------------------------- |
| id                          | bigint    | No       | Primary key               |
| tenant_id                   | bigint    | No       | FK → tenants.id (CASCADE) |
| name                        | string    | No       | —                         |
| is_purchasable              | boolean   | No       | —                         |
| is_sellable                 | boolean   | No       | —                         |
| is_manufacturable           | boolean   | No       | —                         |
| default_price_cents         | integer   | No       | Unsigned                  |
| default_price_currency_code | char(3)   | No       | ISO currency              |
| created_at                  | timestamp | Yes      | —                         |
| updated_at                  | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`

---

## permissions

**Tenant-owned:** No  
**Purpose:** Authorization permissions

### Columns

| Name        | Type      | Nullable | Notes       |
| ----------- | --------- | -------- | ----------- |
| id          | bigint    | No       | Primary key |
| slug        | string    | No       | Unique      |
| description | string    | No       | —           |
| created_at  | timestamp | Yes      | —           |
| updated_at  | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`
- Unique: `slug`

---

## purchase_order_lines

**Tenant-owned:** Yes  
**Purpose:** Purchase order line items with price snapshots

### Columns

| Name                        | Type          | Nullable | Notes                                    |
| --------------------------- | ------------- | -------- | ---------------------------------------- |
| id                          | bigint        | No       | Primary key                              |
| tenant_id                   | bigint        | No       | FK → tenants.id (CASCADE)                |
| purchase_order_id           | bigint        | No       | Part of composite FK                     |
| item_id                     | bigint        | No       | FK → items.id (CASCADE)                  |
| item_purchase_option_id     | bigint        | No       | FK → item_purchase_options.id (CASCADE)  |
| pack_count                  | integer       | No       | Unsigned, CHECK ≥ 1                      |
| unit_price_cents            | integer       | No       | Unsigned                                 |
| line_subtotal_cents         | integer       | No       | Unsigned, unit_price_cents \* pack_count |
| unit_price_amount           | integer       | No       | Unsigned, snapshot cents                 |
| unit_price_currency_code    | char(3)       | No       | Snapshot currency                        |
| converted_unit_price_amount | integer       | No       | Unsigned, snapshot converted cents       |
| fx_rate                     | decimal(18,8) | No       | Snapshot FX rate                         |
| fx_rate_as_of               | date          | No       | Snapshot FX rate date                    |
| created_at                  | timestamp     | Yes      | —                                        |
| updated_at                  | timestamp     | Yes      | —                                        |

### Foreign Keys

- `(purchase_order_id, tenant_id)` → purchase_orders.(id, tenant_id) (CASCADE)
- `item_id` → items.id (CASCADE)
- `item_purchase_option_id` → item_purchase_options.id (CASCADE)

### Keys & Indexes

- PK: `id`
- Index: `(purchase_order_id, item_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`
- Implicit (FK index): `item_purchase_option_id`

---

## purchase_orders

**Tenant-owned:** Yes  
**Purpose:** Purchase order headers

### Columns

| Name                 | Type      | Nullable | Notes                        |
| -------------------- | --------- | -------- | ---------------------------- |
| id                   | bigint    | No       | Primary key                  |
| tenant_id            | bigint    | No       | FK → tenants.id (CASCADE)    |
| created_by_user_id   | bigint    | Yes      | FK → users.id (SET NULL)     |
| supplier_id          | bigint    | Yes      | FK → suppliers.id (SET NULL) |
| order_date           | date      | Yes      | —                            |
| shipping_cents       | integer   | Yes      | Unsigned                     |
| tax_cents            | integer   | Yes      | Unsigned                     |
| po_subtotal_cents    | integer   | No       | Unsigned, default 0          |
| po_grand_total_cents | integer   | No       | Unsigned, default 0          |
| po_number            | string    | Yes      | —                            |
| notes                | text      | Yes      | —                            |
| status               | string    | No       | See ENUMS.md                 |
| created_at           | timestamp | Yes      | —                            |
| updated_at           | timestamp | Yes      | —                            |

### Keys & Indexes

- PK: `id`
- Unique: `(id, tenant_id)`
- Index: `tenant_id`
- Index: `(tenant_id, status)`
- Index: `(tenant_id, supplier_id)`
- Implicit (FK index): `created_by_user_id`
- Implicit (FK index): `supplier_id`

---

## role_permission

**Tenant-owned:** No  
**Purpose:** Join table for roles and permissions

### Columns

| Name          | Type      | Nullable | Notes                         |
| ------------- | --------- | -------- | ----------------------------- |
| id            | bigint    | No       | Primary key                   |
| permission_id | bigint    | No       | FK → permissions.id (CASCADE) |
| role_id       | bigint    | No       | FK → roles.id (CASCADE)       |
| created_at    | timestamp | Yes      | —                             |
| updated_at    | timestamp | Yes      | —                             |

### Keys & Indexes

- PK: `id`
- Unique: `(permission_id, role_id)`
- Implicit (FK index): `permission_id`
- Implicit (FK index): `role_id`

---

## roles

**Tenant-owned:** No  
**Purpose:** Role definitions

### Columns

| Name       | Type      | Nullable | Notes       |
| ---------- | --------- | -------- | ----------- |
| id         | bigint    | No       | Primary key |
| name       | string    | No       | Unique      |
| created_at | timestamp | Yes      | —           |
| updated_at | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`
- Unique: `name`

---

## stock_moves

**Tenant-owned:** Yes  
**Purpose:** Inventory movements

### Columns

| Name       | Type          | Nullable | Notes                     |
| ---------- | ------------- | -------- | ------------------------- |
| id         | bigint        | No       | Primary key               |
| tenant_id  | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id    | bigint        | No       | FK → items.id (CASCADE)   |
| quantity   | decimal(18,6) | No       | BCMath quantity           |
| type       | string        | No       | See ENUMS.md              |
| status     | string        | No       | See ENUMS.md              |
| created_at | timestamp     | Yes      | —                         |
| updated_at | timestamp     | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Implicit (FK index): `item_id`

---

## supplier_item_prices

**Tenant-owned:** Yes  
**Purpose:** Supplier item price history

### Columns

| Name                    | Type      | Nullable | Notes                                   |
| ----------------------- | --------- | -------- | --------------------------------------- |
| id                      | bigint    | No       | Primary key                             |
| tenant_id               | bigint    | No       | FK → tenants.id (CASCADE)               |
| supplier_id             | bigint    | No       | FK → suppliers.id (CASCADE)             |
| item_id                 | bigint    | No       | FK → items.id (CASCADE)                 |
| item_purchase_option_id | bigint    | No       | FK → item_purchase_options.id (CASCADE) |
| price_cents             | integer   | No       | Unsigned                                |
| currency_code           | char(3)   | No       | ISO currency                            |
| created_at              | timestamp | Yes      | —                                       |
| updated_at              | timestamp | Yes      | —                                       |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `supplier_id`
- Index: `item_id`
- Index: `item_purchase_option_id`

---

## suppliers

**Tenant-owned:** Yes  
**Purpose:** Supplier records

### Columns

| Name          | Type      | Nullable | Notes                     |
| ------------- | --------- | -------- | ------------------------- |
| id            | bigint    | No       | Primary key               |
| tenant_id     | bigint    | No       | FK → tenants.id (CASCADE) |
| name          | string    | No       | —                         |
| currency_code | char(3)   | Yes      | ISO currency              |
| created_at    | timestamp | Yes      | —                         |
| updated_at    | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`

---

## tenants

**Tenant-owned:** No  
**Purpose:** Tenant accounts

### Columns

| Name       | Type      | Nullable | Notes                |
| ---------- | --------- | -------- | -------------------- |
| id         | bigint    | No       | Primary key          |
| name       | string    | Yes      | Nullable on creation |
| created_at | timestamp | Yes      | —                    |
| updated_at | timestamp | Yes      | —                    |

### Keys & Indexes

- PK: `id`
- Unique: `name`

---

## uom_categories

**Tenant-owned:** No  
**Purpose:** Unit of measure categories

### Columns

| Name       | Type      | Nullable | Notes       |
| ---------- | --------- | -------- | ----------- |
| id         | bigint    | No       | Primary key |
| name       | string    | No       | Unique      |
| created_at | timestamp | Yes      | —           |
| updated_at | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`
- Unique: `name`

---

## uom_conversions

**Tenant-owned:** No  
**Purpose:** Global UoM conversions

### Columns

| Name        | Type          | Nullable | Notes                  |
| ----------- | ------------- | -------- | ---------------------- |
| id          | bigint        | No       | Primary key            |
| from_uom_id | bigint        | No       | FK → uoms.id (CASCADE) |
| to_uom_id   | bigint        | No       | FK → uoms.id (CASCADE) |
| multiplier  | decimal(18,8) | No       | —                      |
| created_at  | timestamp     | Yes      | —                      |
| updated_at  | timestamp     | Yes      | —                      |

### Keys & Indexes

- PK: `id`
- Unique: `(from_uom_id, to_uom_id)`
- Implicit (FK index): `from_uom_id`
- Implicit (FK index): `to_uom_id`

---

## uoms

**Tenant-owned:** No  
**Purpose:** Units of measure

### Columns

| Name            | Type      | Nullable | Notes                            |
| --------------- | --------- | -------- | -------------------------------- |
| id              | bigint    | No       | Primary key                      |
| uom_category_id | bigint    | No       | FK → uom_categories.id (CASCADE) |
| name            | string    | No       | Unique                           |
| symbol          | string    | No       | Unique                           |
| created_at      | timestamp | Yes      | —                                |
| updated_at      | timestamp | Yes      | —                                |

### Keys & Indexes

- PK: `id`
- Unique: `name`
- Unique: `symbol`
- Implicit (FK index): `uom_category_id`

---

## users

**Tenant-owned:** No (auth-safe)  
**Purpose:** Authentication identities

### Columns

| Name              | Type      | Nullable | Notes                     |
| ----------------- | --------- | -------- | ------------------------- |
| id                | bigint    | No       | Primary key               |
| name              | string    | No       | —                         |
| email             | string    | No       | Unique                    |
| email_verified_at | timestamp | Yes      | —                         |
| password          | string    | No       | —                         |
| remember_token    | string    | Yes      | —                         |
| created_at        | timestamp | Yes      | —                         |
| updated_at        | timestamp | Yes      | —                         |
| tenant_id         | bigint    | No       | FK → tenants.id (CASCADE) |

### Keys & Indexes

- PK: `id`
- Unique: `email`
- Index: `tenant_id`

---

**End of DB_SCHEMA**

## docs/UI_DESIGN.md

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

## routes/web.php

<?php

use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\InventoryCountLineController;
use App\Http\Controllers\ItemPurchaseOptionController;
use App\Http\Controllers\ItemPurchaseOptionPriceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderLineController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/materials', function () {
        return redirect('/materials/uoms');
    })->name('materials');

    Route::get('/materials/uoms', function () {
        return redirect('/manufacturing/uoms');
    })->name('materials.uoms');

    Route::get('/materials/uom-categories', function () {
        return redirect('/manufacturing/uom-categories');
    })->name('materials.uomCategories');

    Route::prefix('manufacturing')->group(function () {
        Route::get('/uoms', [\App\Http\Controllers\UomController::class, 'index'])
            ->name('manufacturing.uoms');
        Route::get('/uom-categories', [\App\Http\Controllers\UomCategoryController::class, 'index'])
            ->name('manufacturing.uom-categories');
    });

    Route::get('/materials/items', [\App\Http\Controllers\ItemController::class, 'index'])
        ->name('materials.items');

    Route::prefix('purchasing')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])
            ->name('purchasing.suppliers.index');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
            ->name('purchasing.suppliers.show');
        Route::get('/suppliers/{supplier}/catalog', [SupplierController::class, 'catalog'])
            ->name('purchasing.suppliers.catalog');
        Route::post('/suppliers/{supplier}/catalog', [SupplierController::class, 'storeCatalog'])
            ->name('purchasing.suppliers.catalog.store');
        Route::patch('/suppliers/{supplier}/catalog/{itemPurchaseOption}', [SupplierController::class, 'updateCatalog'])
            ->name('purchasing.suppliers.catalog.update');
        Route::delete('/suppliers/{supplier}/catalog/{itemPurchaseOption}', [SupplierController::class, 'destroyCatalog'])
            ->name('purchasing.suppliers.catalog.destroy');
        Route::post('/suppliers/{supplier}/catalog/{itemPurchaseOption}/prices', [ItemPurchaseOptionPriceController::class, 'store'])
            ->name('purchasing.suppliers.catalog.prices.store');

        Route::get('/orders', [PurchaseOrderController::class, 'index'])
            ->name('purchasing.orders.index');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])
            ->name('purchasing.orders.store');
        Route::get('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->name('purchasing.orders.show');
        Route::patch('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
            ->name('purchasing.orders.update');
        Route::put('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::delete('/orders/{purchaseOrderId}', [PurchaseOrderController::class, 'destroy'])
            ->name('purchasing.orders.destroy');

        Route::post('/orders/{purchaseOrderId}/lines', [PurchaseOrderLineController::class, 'store'])
            ->name('purchasing.orders.lines.store');
        Route::patch('/orders/{purchaseOrder}/lines/{line}', [PurchaseOrderLineController::class, 'update'])
            ->name('purchasing.orders.lines.update');
        Route::delete('/orders/{purchaseOrderId}/lines/{lineId}', [PurchaseOrderLineController::class, 'destroy'])
            ->name('purchasing.orders.lines.destroy');
    });

    Route::prefix('manufacturing')->group(function () {
        Route::get('/inventory-counts', [InventoryCountController::class, 'index'])
            ->name('inventory-counts.index');
        Route::post('/inventory-counts', [InventoryCountController::class, 'store'])
            ->name('inventory-counts.store');
        Route::get('/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'show'])
            ->name('inventory-counts.show');
        Route::patch('/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'update'])
            ->name('inventory-counts.update');
        Route::delete('/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'destroy'])
            ->name('inventory-counts.destroy');

        Route::post('/inventory-counts/{inventoryCount}/lines', [InventoryCountLineController::class, 'store'])
            ->name('inventory-counts.lines.store');
        Route::patch('/inventory-counts/{inventoryCount}/lines/{line}', [InventoryCountLineController::class, 'update'])
            ->name('inventory-counts.lines.update');
        Route::delete('/inventory-counts/{inventoryCount}/lines/{line}', [InventoryCountLineController::class, 'destroy'])
            ->name('inventory-counts.lines.destroy');
    });
});
