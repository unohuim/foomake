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

When writing prompts for codex, instruct it to write test files AND entire implementation for the PR, but never ask it to run tests or CI - that's my job. When prompting to write test files, have codex test for at a min of 20 tests per file. Instruct codex to write test files so that they are Complete and Sufficient. In your prompts for entire PRs, ensure that codex includes all required classes AND migration files in its plan.

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
- Users with multiple business roles
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

### PR3b — Make Orders Lifecycle (Persisted)

**Goal**  
Implement persisted Make Orders with full lifecycle (Draft → Scheduled → Made/Executed), replacing direct execution. Index list at /manufacturing/make-orders.

**Includes**

- Index UI: table of Make Orders (Recipe/Output, Qty, Status, Due Date, Actions)
- Create draft: slide-over (select active recipe + output qty) → saves as DRAFT
- Schedule: set due date on draft → status SCHEDULED
- Make/Execute: on scheduled order → calls ExecuteRecipeAction → status MADE + stock moves
- Tenant isolation, permission gates, AJAX actions, toasts/errors
- Idempotent "make" (error if already made)

**Rules**

- Ledger-first on "make": issues inputs + receipts output via ExecuteRecipeAction
- BCMath canonical scale=6
- Status: DRAFT, SCHEDULED, MADE
- Active recipe required
- Due date required for schedule
- No actions after MADE

**Permissions**

- View: inventory-make-orders-view
- Manage/Execute: inventory-make-orders-execute

**Out of Scope**

- Partial execution
- Post-MADE reversal
- Reporting

---

## DOMAIN 4 — Suppliers & Purchasing (Revised)

This domain introduces **supplier management, supplier-specific material pricing, and purchasing primitives**, with clear separation between **planning prices**, **supplier prices**, and **order price snapshots**.

---

### PR2-PUR-001 — Suppliers Index + Create (AJAX)

**Goal**  
Introduce tenant-owned Suppliers as a first-class Purchasing domain.

**Includes**

- Nav: **Purchasing → Suppliers**
- Routes:
    - `GET /purchasing/suppliers`
    - `POST /purchasing/suppliers` (AJAX)
- Supplier fields:
    - `company_name` (required)
    - `url` (nullable)
    - `phone` (nullable)
    - `email` (nullable)
    - `currency_code` (nullable; defaults to tenant currency)
- Empty state + slide-over create form
- Gates:
    - View: `purchasing-suppliers-view`
    - Create: `purchasing-suppliers-manage`
- Tenancy enforced (`tenant_id`, `HasTenantScope`)

**Out of Scope**

- Edit/Delete
- Supplier detail page
- Material linking

---

### PR2-MAT-004 — Material Planning Price (Schema + UI)

**Goal**  
Add a **planning-only placeholder price** to materials.

**Includes**

- Schema:
    - `items.default_price_amount`
    - `items.default_price_currency_code`
- Defaults to tenant currency
- Editable on material create/edit
- Used for:
    - Planning
    - Forecasting
    - Early recipe costing
- Explicitly **not transactional**

**Out of Scope**

- Supplier-specific pricing
- FX conversion logic

---

### PR2-PUR-002 — Supplier CRUD (Edit + Delete)

**Goal**  
Complete supplier lifecycle management.

**Includes**

- Edit supplier (AJAX slide-over)
- Delete supplier
- Delete blocked if supplier has linked materials (future-safe)
- Gate: `purchasing-suppliers-manage`

---

### PR2-PUR-003 — Supplier ↔ Material Catalog + Pricing

**Goal**  
Define which materials are bought from which suppliers, including **supplier-specific pricing**.

**Includes**

- New tenant-owned table/model (recommended): `supplier_item_prices`
- Fields:
    - `tenant_id`
    - `supplier_id`
    - `item_id`
    - `price_amount`
    - `price_currency_code` (defaults to supplier → tenant currency)
    - `converted_amount` (tenant currency)
    - `fx_rate`
    - `fx_rate_as_of`
    - `effective_at` (or `is_current`)
- Supplier detail page:
    - `/purchasing/suppliers/{supplier}`
    - Supplier info + index of materials they sell
    - Add/remove materials + set price (AJAX)
- Material detail enhancement:
    - `/materials/{item}`
    - Section listing possible suppliers + current prices
- Gates:
    - View: `purchasing-suppliers-view`
    - Mutate: `purchasing-suppliers-manage`

**Rules**

- Many suppliers per material
- Many materials per supplier
- Prices represent **expected/current** pricing, not history

---

### PR2-PUR-004 — Purchase Orders (Draft + Pricing Snapshot)

**Goal**  
Create purchase orders with **immutable price snapshots**.

**Includes**

- Nav: **Purchasing → Orders**
- PO index + create draft (AJAX)
- PO lines sourced from supplier-material catalog
- Each PO line stores:
    - `unit_price_amount`
    - `unit_price_currency_code`
    - `converted_unit_price_amount` (tenant currency)
    - `fx_rate`
    - `fx_rate_as_of`
- Gates:
    - View: `purchasing-purchase-orders-view`
    - Create: `purchasing-purchase-orders-create`

**Rules**

- PO prices never change after creation
- Supplier price changes do **not** affect existing POs

---

### Currency & FX Rules (Domain-Wide)

- Tenant has a **default currency**
- Supplier defaults to tenant currency but may override
- Prices may be entered in foreign currencies
- FX handling:
    - Store **original amount + currency**
    - Store **converted tenant amount**
    - Persist **FX rate + rate date**
- No retroactive FX updates

---

### Pricing Layer Invariant

| Layer          | Location                 | Purpose                  |
| -------------- | ------------------------ | ------------------------ |
| Planning       | `items.default_price_*`  | Forecasting only         |
| Supplier       | `supplier_item_prices.*` | Expected buy price       |
| Purchase Order | `purchase_order_lines.*` | Legal / accounting truth |

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
- Avoid breaking changes without approval and documentation
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

This document tracks **reusable abstractions, components, and architectural patterns**
used throughout the project.

Its purpose is to:

- Prevent duplicate abstractions
- Make intent explicit for future contributors (human or AI)
- Serve as the architectural source of truth

This is an **index**, not a tutorial.

---

## Authority & References

- **Enum-like values** (database enums, CHECK constraints, and domain-level enum semantics)
  are defined canonically in **docs/ENUMS.md**.
- This document must not duplicate enum values; it may only reference their existence and usage.

---

## Entry Requirements

Each entry includes:

- **Name**
- **Type**
- **Location**
- **Purpose**
- **When to Use**
- **When Not to Use**
- **Public Interface**
- **Example Usage**

---

## Multi-Tenancy

### Single Database Tenant Scoping

**Name:** Single Database Tenant Scoping  
**Type:** Architectural Pattern  
**Location:**  
- `app/Models/Concerns/HasTenantScope.php`  
- `app/Models/Scopes/TenantScope.php`  
- `database/migrations/`

**Purpose:**  
Ensure tenant isolation by enforcing `tenant_id` on tenant-owned data and scoping queries by authenticated tenant.

**When to Use:**  
Any tenant-owned model or table.

**When Not to Use:**  
Global/system tables or authentication identity resolution.

**Public Interface:**  
- `use HasTenantScope`

**Example Usage:**  
```php
class Item extends Model
{
    use HasTenantScope;
}
```

---

### Tenant Scope Trait

**Name:** Tenant Scope Trait  
**Type:** Trait / Global Eloquent Scope  
**Location:**  
- `app/Models/Concerns/HasTenantScope.php`  
- `app/Models/Scopes/TenantScope.php`

**Purpose:**  
Apply a global scope that filters tenant-owned models by `tenant_id`.

**When to Use:**  
Any tenant-owned Eloquent model.

**When Not to Use:**  
Global/system models or auth identity models like `User`.

**Public Interface:**  
- `use HasTenantScope`

**Example Usage:**  
```php
class StockMove extends Model
{
    use HasTenantScope;
}
```

---

### User Auth Identity Safety

**Name:** User Auth Identity Safety  
**Type:** Architectural Rule  
**Location:** `app/Models/User.php`

**Purpose:**  
Keep authentication and identity resolution independent from tenant scoping.

**When to Use:**  
Authentication and identity lookup.

**When Not to Use:**  
Tenant-owned domain data queries.

**Public Interface:**  
- `User::query()`

**Example Usage:**  
```php
$user = User::where('email', $email)->first();
```

---

### Manufacturing Recipes Tenant Isolation

**Name:** Manufacturing Recipes Tenant Isolation  
**Type:** Tenancy Rule  
**Location:**  
- `docs/architecture/tenancy/ManufacturingRecipesTenantIsolation.yaml`  
- `app/Models/Recipe.php`  
- `app/Models/RecipeLine.php`

**Purpose:**  
Ensure recipe queries are tenant-scoped and cross-tenant access results in 404s.

**When to Use:**  
Recipe index/show queries and route model binding.

**When Not to Use:**  
Auth identity resolution or global/system models.

**Public Interface:**  
- `use HasTenantScope`  
- `Recipe::query()`  
- `RecipeLine::query()`

**Example Usage:**  
```php
$recipe = Recipe::query()->findOrFail($id);
```

---

### Tenant

**Name:** Tenant  
**Type:** Eloquent Model  
**Location:** `app/Models/Tenant.php`

**Purpose:**  
Represent a tenant in a single-database, multi-tenant architecture.

**When to Use:**  
Associating users and data with a tenant.

**When Not to Use:**  
Global/system configuration unrelated to a tenant.

**Public Interface:**  
- `users()`

**Example Usage:**  
```php
$tenant = Tenant::create(['tenant_name' => 'Acme Foods']);
$users = $tenant->users;
```

---

## Authorization

### Domain Authorization Layer

**Name:** Domain Authorization Layer  
**Type:** Authorization Pattern (Laravel Gates)  
**Location:** `app/Providers/AuthServiceProvider.php`

**Purpose:**  
Centralize authorization using permission slugs and Laravel Gates.

**When to Use:**  
Any access control decision.

**When Not to Use:**  
UI-only visibility decisions without backend enforcement.

**Public Interface:**  
- `Gate::allows()`  
- `Gate::authorize()`

**Example Usage:**  
```php
Gate::authorize('inventory-materials-manage');
```

---

### Manufacturing Recipes Read-Only Access

**Name:** Manufacturing Recipes Read-Only Access  
**Type:** Authorization Rule  
**Location:**  
- `docs/architecture/auth/ManufacturingRecipesReadOnlyAccess.yaml`  
- `app/Providers/AuthServiceProvider.php`  
- `app/Http/Controllers/RecipeController.php`  
- `routes/web.php`  
- `resources/views/layouts/navigation.blade.php`

**Purpose:**  
Enforce authenticated, gate-backed access to manufacturing recipe read-only pages.

**When to Use:**  
Restricting recipes index/show routes and navigation visibility.

**When Not to Use:**  
Recipe write or execution flows.

**Public Interface:**  
- `Gate::authorize('inventory-recipes-view')`  
- `@can('inventory-recipes-view')`  
- `manufacturing.recipes.*`

**Example Usage:**  
```php
Gate::authorize('inventory-recipes-view');
```

---

### Role

**Name:** Role  
**Type:** Eloquent Model  
**Location:** `app/Models/Role.php`

**Purpose:**  
Represent global roles that group permissions.

**When to Use:**  
Assigning responsibilities and permissions to users.

**When Not to Use:**  
Per-tenant role definitions.

**Public Interface:**  
- `users()`  
- `permissions()`

**Example Usage:**  
```php
$user->roles()->attach($roleId);
```

---

### Permission

**Name:** Permission  
**Type:** Eloquent Model  
**Location:** `app/Models/Permission.php`

**Purpose:**  
Store canonical permission slugs enforced via Gates.

**When to Use:**  
Authorization checks and role-permission mappings.

**When Not to Use:**  
UI-only access decisions without backend enforcement.

**Public Interface:**  
- `roles()`

**Example Usage:**  
```php
$permission->roles()->attach($roleId);
```

---

### User

**Name:** User  
**Type:** Eloquent Model  
**Location:** `app/Models/User.php`

**Purpose:**  
Represent authentication identities and role/permission checks.

**When to Use:**  
Authentication and authorization checks.

**When Not to Use:**  
Tenant-scoped domain queries.

**Public Interface:**  
- `tenant()`  
- `roles()`  
- `hasRole()`  
- `hasPermission()`

**Example Usage:**  
```php
if ($user->hasPermission('inventory-materials-manage')) {
    // ...
}
```

---

## Inventory Ledger

### StockMove

**Name:** StockMove  
**Type:** Eloquent Model / Domain Rule  
**Location:** `app/Models/StockMove.php`

**Purpose:**  
Represent append-only inventory movements that form the ledger.

**When to Use:**  
Any inventory-affecting operation such as receipts, issues, or adjustments.

**When Not to Use:**  
Storing or mutating on-hand totals directly.

**Public Interface:**  
- `tenant()`  
- `item()`  
- `uom()`  
- `source()`

**Example Usage:**  
```php
StockMove::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'uom_id' => $item->base_uom_id,
    'quantity' => '10.000000',
    'type' => 'receipt',
]);
```

---

### Stock-Move Guarded Delete

**Name:** Stock-Move Guarded Delete  
**Type:** Architectural Pattern  
**Location:** `app/Http/Controllers/ItemController.php`

**Purpose:**  
Prevent deleting materials that have stock move history.

**When to Use:**  
Deleting tenant-owned items tracked in the inventory ledger.

**When Not to Use:**  
Entities without inventory history.

**Public Interface:**  
- `ItemController::destroy()`  
- `Item::stockMoves()`

**Example Usage:**  
```http
DELETE /materials/{item}
-> 422 { "message": "Material cannot be deleted because stock moves exist." }
```

---

### Decimal Quantity Math

**Name:** Decimal Quantity Math  
**Type:** Domain Rule  
**Location:** `docs/CONVENTIONS.md`

**Purpose:**  
Define canonical rules for quantity math to avoid floating-point errors.

**When to Use:**  
Any inventory-affecting calculations or unit conversions.

**When Not to Use:**  
Non-quantity calculations.

**Public Interface:**  
- BCMath functions  
- Canonical scale rules in `docs/CONVENTIONS.md`

**Example Usage:**  
```php
$total = bcadd($a, $b, 6);
```

---

### Item

**Name:** Item  
**Type:** Eloquent Model  
**Location:** `app/Models/Item.php`

**Purpose:**  
Represent tenant-owned stock-tracked entities with inventory derived from stock moves.

**When to Use:**  
Modeling materials or products and computing on-hand quantities.

**When Not to Use:**  
Storing denormalized on-hand quantities.

**Public Interface:**  
- `baseUom()`  
- `stockMoves()`  
- `onHandQuantity()`  
- `itemUomConversions()`  
- `recipes()`  
- `activeRecipe()`

**Example Usage:**  
```php
$onHand = $item->onHandQuantity();
```

---

### InventoryCount

**Name:** InventoryCount  
**Type:** Eloquent Model  
**Location:** `app/Models/InventoryCount.php`

**Purpose:**  
Represent inventory count sessions with status derived from `posted_at`.

**When to Use:**  
Recording inventory count sessions and posting adjustments.

**When Not to Use:**  
Inventory adjustments outside a count context.

**Public Interface:**  
- `tenant()`  
- `lines()`  
- `postedByUser()`  
- `stockMoves()`  
- `getStatusAttribute()`

**Example Usage:**  
```php
$status = $inventoryCount->status;
```

---

### InventoryCountLine

**Name:** InventoryCountLine  
**Type:** Eloquent Model  
**Location:** `app/Models/InventoryCountLine.php`

**Purpose:**  
Represent line items for an inventory count session.

**When to Use:**  
Recording counted quantities for items.

**When Not to Use:**  
Recording inventory adjustments outside a count.

**Public Interface:**  
- `inventoryCount()`  
- `item()`

**Example Usage:**  
```php
$line = $count->lines()->create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'counted_quantity' => '5.000000',
]);
```

---

### PostInventoryCountAction

**Name:** PostInventoryCountAction  
**Type:** Action / Domain Service  
**Location:** `app/Actions/Inventory/PostInventoryCountAction.php`

**Purpose:**  
Post an inventory count and create ledger adjustments.

**When to Use:**  
Posting inventory count results to the ledger.

**When Not to Use:**  
Generic inventory adjustments.

**Public Interface:**  
- `execute(InventoryCount $inventoryCount, int $postedByUserId): InventoryCount`

**Example Usage:**  
```php
$action = new PostInventoryCountAction();
$action->execute($inventoryCount, $userId);
```

---

## Manufacturing

### Recipe

**Name:** Recipe  
**Type:** Eloquent Model  
**Location:** `app/Models/Recipe.php`

**Purpose:**  
Represent manufacturing recipes for items.

**When to Use:**  
Defining recipes and their line items.

**When Not to Use:**  
Non-manufacturing inventory relationships.

**Public Interface:**  
- `tenant()`  
- `item()`  
- `lines()`  
- `stockMoves()`

**Example Usage:**  
```php
$recipe = $item->recipe;
```

---

### RecipeLine

**Name:** RecipeLine  
**Type:** Eloquent Model  
**Location:** `app/Models/RecipeLine.php`

**Purpose:**  
Represent line items for a recipe.

**When to Use:**  
Capturing input items and quantities for recipes.

**When Not to Use:**  
Inventory movements or adjustments.

**Public Interface:**  
- `tenant()`  
- `recipe()`  
- `item()`

**Example Usage:**  
```php
$recipe->lines()->create([
    'tenant_id' => $tenant->id,
    'item_id' => $inputItem->id,
    'quantity' => '2.000000',
]);
```

---

### ExecuteRecipeAction

**Name:** ExecuteRecipeAction  
**Type:** Action / Domain Service  
**Location:** `app/Actions/Inventory/ExecuteRecipeAction.php`

**Purpose:**  
Execute a recipe to issue inputs and receipt outputs as stock moves.

**When to Use:**  
Manufacturing or make-order execution.

**When Not to Use:**  
Inventory adjustments or corrections.

**Public Interface:**  
- `execute(Recipe $recipe, string $outputQuantity): array`

**Example Usage:**  
```php
$action = new ExecuteRecipeAction();
$action->execute($recipe, '5.000000');
```

---

### Recipe Read Model

**Name:** Recipe Read Model  
**Type:** Read Model / UI Contract  
**Location:**  
- `docs/architecture/manufacturing/RecipeReadModel.yaml`  
- `app/Http/Controllers/RecipeController.php`  
- `resources/views/manufacturing/recipes/index.blade.php`  
- `resources/views/manufacturing/recipes/show.blade.php`

**Purpose:**  
Define the read-only data and display expectations for recipe index and detail pages.

**When to Use:**  
Rendering manufacturing recipe read-only views.

**When Not to Use:**  
Recipe creation, editing, or execution flows.

**Public Interface:**  
- `manufacturing.recipes.index`  
- `manufacturing.recipes.show`

**Example Usage:**  
```blade
<th>{{ __('Input Item') }}</th>
<th>{{ __('Quantity') }}</th>
<th>{{ __('UoM') }}</th>
```

---

## Units of Measure

### UomCategory

**Name:** UomCategory  
**Type:** Eloquent Model  
**Location:** `app/Models/UomCategory.php`

**Purpose:**  
Group units of measure into categories that define safe conversion boundaries.

**When to Use:**  
Defining conversion-safe groupings such as mass or volume.

**When Not to Use:**  
Cross-category conversion logic.

**Public Interface:**  
- `uoms()`

**Example Usage:**  
```php
$category = UomCategory::create(['name' => 'Mass']);
```

---

### Uom

**Name:** Uom  
**Type:** Eloquent Model  
**Location:** `app/Models/Uom.php`

**Purpose:**  
Represent a unit of measure belonging to a single category.

**When to Use:**  
Assigning units to items and recording quantities.

**When Not to Use:**  
Implicit unit assumptions.

**Public Interface:**  
- `category()`  
- `conversionsFrom()`  
- `conversionsTo()`

**Example Usage:**  
```php
$uom = Uom::create([
    'uom_category_id' => $category->id,
    'name' => 'Gram',
    'symbol' => 'g',
]);
```

---

### UomConversion

**Name:** UomConversion  
**Type:** Eloquent Model / Domain Rule  
**Location:** `app/Models/UomConversion.php`

**Purpose:**  
Provide safe global conversions within a single UoM category.

**When to Use:**  
Universal conversions within a category.

**When Not to Use:**  
Cross-category conversions or item-specific conversions.

**Public Interface:**  
- `fromUom()`  
- `toUom()`

**Example Usage:**  
```php
UomConversion::create([
    'from_uom_id' => $kg->id,
    'to_uom_id' => $grams->id,
    'multiplier' => '1000.00000000',
]);
```

---

### ItemUomConversion

**Name:** ItemUomConversion  
**Type:** Eloquent Model / Domain Rule  
**Location:** `app/Models/ItemUomConversion.php`

**Purpose:**  
Allow item-specific conversions, including cross-category conversions.

**When to Use:**  
Conversions that are true only for a specific item.

**When Not to Use:**  
Global conversions shared across items.

**Public Interface:**  
- `item()`  
- `fromUom()`  
- `toUom()`

**Example Usage:**  
```php
$item->itemUomConversions()->create([
    'tenant_id' => $tenant->id,
    'from_uom_id' => $count->id,
    'to_uom_id' => $grams->id,
    'conversion_factor' => '50.000000',
]);
```

---

## Purchasing

### ItemPurchaseOption

**Name:** ItemPurchaseOption  
**Type:** Eloquent Model  
**Location:** `app/Models/ItemPurchaseOption.php`

**Purpose:**  
Represent supplier-specific purchasing packs that map into item inventory.

**When to Use:**  
Receiving inventory in supplier pack quantities.

**When Not to Use:**  
Tracking inventory on-hand directly.

**Public Interface:**  
- `tenant()`  
- `item()`  
- `packUom()`

**Example Usage:**  
```php
$option = ItemPurchaseOption::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'pack_quantity' => '10.000000',
    'pack_uom_id' => $kg->id,
]);
```

---

### ReceivePurchaseOptionAction

**Name:** ReceivePurchaseOptionAction  
**Type:** Action / Domain Service  
**Location:** `app/Actions/Inventory/ReceivePurchaseOptionAction.php`

**Purpose:**  
Receive inventory from a purchase option and create a stock move.

**When to Use:**  
Receiving inventory from supplier pack quantities.

**When Not to Use:**  
Generic inventory adjustments.

**Public Interface:**  
- `execute(ItemPurchaseOption $option, string $packCount): StockMove`

**Example Usage:**  
```php
$action = new ReceivePurchaseOptionAction();
$action->execute($option, '2.000000');
```

---

## Controllers & UI Patterns

### AJAX CRUD Controller Pattern

**Name:** AJAX CRUD Controller Pattern  
**Type:** Architectural Pattern  
**Location:**  
- `app/Http/Controllers/UomCategoryController.php`  
- `app/Http/Controllers/UomController.php`  
- `app/Http/Controllers/ItemController.php`

**Purpose:**  
Handle UI-driven CRUD using JSON responses without full page reloads.

**When to Use:**  
Single-entity CRUD with fetch-based requests.

**When Not to Use:**  
Multi-step workflows or transactional orchestration.

**Public Interface:**  
- `store()`  
- `update()`  
- `destroy()`

**Example Usage:**  
```php
$response = $this->postJson('/materials', [
    'name' => 'Flour',
    'base_uom_id' => 1,
]);
```

---

### Top Navigation Dropdown

**Name:** Top Navigation Dropdown  
**Type:** UI Pattern  
**Location:** `resources/views/layouts/navigation.blade.php`

**Purpose:**  
Group navigation links under a top-level dropdown.

**When to Use:**  
A top-level domain owns mandatory supporting subdomains.

**When Not to Use:**  
Unrelated or optional domains.

**Public Interface:**  
- Blade markup using `x-dropdown` and `x-dropdown-link`

**Example Usage:**  
```blade
<x-dropdown align="left">
    <x-slot name="trigger">
        <button>Manufacturing</button>
    </x-slot>
    <x-slot name="content">
        <x-dropdown-link :href="route('materials.index')">Inventory</x-dropdown-link>
    </x-slot>
</x-dropdown>
```

---

### Slide-Over Form Pattern

**Name:** Slide-Over Form Pattern  
**Type:** UI Pattern  
**Location:** `resources/views/materials/partials/create-material-slide-over.blade.php`

**Purpose:**  
Create or edit entities without leaving the current page.

**When to Use:**  
CRUD forms with multiple fields.

**When Not to Use:**  
Confirmations or single-field actions.

**Public Interface:**  
- Blade partial with Alpine state and form markup

**Example Usage:**  
```blade
<form x-on:submit.prevent="submitCreate()">
    <input type="text" x-model="form.name" />
</form>
```

---

### Row Actions Dropdown Pattern

**Name:** Row Actions Dropdown Pattern  
**Type:** UI Pattern  
**Location:** `resources/views/materials/index.blade.php`

**Purpose:**  
Provide contextual row-level actions such as edit and delete.

**When to Use:**  
Tables or lists with multiple row actions.

**When Not to Use:**  
Primary or global actions.

**Public Interface:**  
- Dropdown trigger + content for row actions

**Example Usage:**  
```blade
<button type="button">⋮</button>
```

---

### Page-Scoped Toast Pattern

**Name:** Page-Scoped Toast Pattern  
**Type:** UI Pattern  
**Location:** `resources/views/materials/index.blade.php`

**Purpose:**  
Provide non-blocking toast feedback scoped to the current page.

**When to Use:**  
Non-blocking success or error feedback after AJAX actions.

**When Not to Use:**  
Blocking alerts or full-page loaders.

**Public Interface:**  
- Page-level `showToast(type, message)` handler

**Example Usage:**  
```js
showToast('success', 'Material deleted.');
```

---

## UI Components

### Dropdown

**Name:** Dropdown  
**Type:** Blade Component  
**Location:** `resources/views/components/dropdown.blade.php`

**Purpose:**  
Render a dropdown container with trigger and content slots.

**When to Use:**  
Inline dropdown menus for actions or navigation.

**When Not to Use:**  
Primary actions that should remain visible.

**Public Interface:**  
- `trigger` slot  
- `content` slot

**Example Usage:**  
```blade
<x-dropdown>
    <x-slot name="trigger">⋮</x-slot>
    <x-slot name="content">...</x-slot>
</x-dropdown>
```

---

### Dropdown Link

**Name:** Dropdown Link  
**Type:** Blade Component  
**Location:** `resources/views/components/dropdown-link.blade.php`

**Purpose:**  
Provide a styled link within dropdown content.

**When to Use:**  
Dropdown menus linking to routes.

**When Not to Use:**  
Standalone buttons outside dropdown menus.

**Public Interface:**  
- Standard Blade component props

**Example Usage:**  
```blade
<x-dropdown-link href="/materials">Materials</x-dropdown-link>
```

---

### Modal

**Name:** Modal  
**Type:** Blade Component  
**Location:** `resources/views/components/modal.blade.php`

**Purpose:**  
Provide a reusable modal container.

**When to Use:**  
Confirmation dialogs or short forms.

**When Not to Use:**  
Long multi-step flows.

**Public Interface:**  
- `name` prop  
- `show` prop

**Example Usage:**  
```blade
<x-modal name="confirm-delete" :show="true">...</x-modal>
```

---

### Nav Link

**Name:** Nav Link  
**Type:** Blade Component  
**Location:** `resources/views/components/nav-link.blade.php`

**Purpose:**  
Render a navigation link with active state styling.

**When to Use:**  
Top navigation links.

**When Not to Use:**  
Inline links within content.

**Public Interface:**  
- `href` prop  
- `active` prop

**Example Usage:**  
```blade
<x-nav-link href="/materials" :active="request()->routeIs('materials.index')">Materials</x-nav-link>
```

---

### Input Label

**Name:** Input Label  
**Type:** Blade Component  
**Location:** `resources/views/components/input-label.blade.php`

**Purpose:**  
Render a label for form inputs.

**When to Use:**  
Form fields requiring labels.

**When Not to Use:**  
Decorative text without input association.

**Public Interface:**  
- `for` prop  
- Slot content

**Example Usage:**  
```blade
<x-input-label for="name" value="Name" />
```

---

### Text Input

**Name:** Text Input  
**Type:** Blade Component  
**Location:** `resources/views/components/text-input.blade.php`

**Purpose:**  
Render a styled text input.

**When to Use:**  
Form inputs using standard text fields.

**When Not to Use:**  
Non-textual inputs like selects or checkboxes.

**Public Interface:**  
- Standard input props

**Example Usage:**  
```blade
<x-text-input id="name" type="text" name="name" />
```

---

### Input Error

**Name:** Input Error  
**Type:** Blade Component  
**Location:** `resources/views/components/input-error.blade.php`

**Purpose:**  
Display validation errors for a field.

**When to Use:**  
Form validation error display.

**When Not to Use:**  
Non-form error messaging.

**Public Interface:**  
- `messages` prop

**Example Usage:**  
```blade
<x-input-error :messages="$errors->get('name')" />
```

---

### Secondary Button

**Name:** Secondary Button  
**Type:** Blade Component  
**Location:** `resources/views/components/secondary-button.blade.php`

**Purpose:**  
Render a secondary action button.

**When to Use:**  
Non-primary actions in forms or dialogs.

**When Not to Use:**  
Primary actions that require emphasis.

**Public Interface:**  
- Slot content

**Example Usage:**  
```blade
<x-secondary-button>Cancel</x-secondary-button>
```

---

### Auth Session Status

**Name:** Auth Session Status  
**Type:** Blade Component  
**Location:** `resources/views/components/auth-session-status.blade.php`

**Purpose:**  
Render session status messages on auth screens.

**When to Use:**  
Login and password reset screens.

**When Not to Use:**  
General-purpose alerts outside auth flows.

**Public Interface:**  
- `status` prop

**Example Usage:**  
```blade
<x-auth-session-status :status="session('status')" />
```

---

## UI Constraints

### Alpine + Blade Quoting Rules

**Name:** Alpine + Blade Quoting Rules  
**Type:** UI Constraint  
**Location:** `docs/UI_DESIGN.md`

**Purpose:**  
Prevent Alpine parsing failures caused by mixed quoting.

**When to Use:**  
Any Blade template with Alpine directives.

**When Not to Use:**  
Templates without Alpine usage.

**Public Interface:**  
- HTML attributes use double quotes  
- Alpine JS string literals use single quotes

**Example Usage:**  
```blade
<div x-data="{ open: false }"></div>
```

---

### Page Module Contract

**Name:** Page Module Contract  
**Type:** UI Architecture Invariant  
**Location:**  
- `docs/architecture/ui/PageModuleContract.yaml`

**Purpose:**  
Define the page-scoped UI module contract for interactive Blade pages.

**When to Use:**  
Any interactive Blade page using Alpine state or fetch-based CRUD.

**When Not to Use:**  
Static Blade pages with no interactivity.

**Public Interface:**  
- `docs/architecture/ui/PageModuleContract.yaml`  
- `docs/UI_DESIGN.md`  
- `resources/js/app.js`  
- `resources/js/pages/**`

**Example Usage:**  
```blade
<script type="application/json" id="materials-index-payload">@json($payload)</script>
<div data-page="materials-index" data-payload="materials-index-payload" x-data="materialsIndex"></div>
```

---

### Page Module Guardrails

**Name:** Page Module Guardrails  
**Type:** UI Constraint  
**Location:**  
- `docs/architecture/ui/PageModuleGuardrails.yaml`  
- `scripts/ci/blade-guardrails.sh`  
- `scripts/ci/js-syntax-guardrails.sh`  
- `ci.sh`

**Purpose:**  
Fail CI when Blade templates include executable scripts or inline handlers, or when JS uses invalid optional-chaining assignments.

**When to Use:**  
Any interactive Blade view or page module change.

**When Not to Use:**  
Vendor or generated views excluded from repository checks, plus Breeze/shared layouts and components pending migration.

**Public Interface:**  
- `scripts/ci/blade-guardrails.sh`  
- `scripts/ci/js-syntax-guardrails.sh`  
- `./ci.sh`

**Example Usage:**  
```bash
./ci.sh
```

---

## Testing

### Pest Testing Framework

**Name:** Pest Testing Framework  
**Type:** Testing Infrastructure  
**Location:** `tests/Pest.php`

**Purpose:**  
Define Pest as the canonical testing framework.

**When to Use:**  
All new automated tests.

**When Not to Use:**  
New PHPUnit test classes.

**Public Interface:**  
- `uses()`  
- `it()`  
- `expect()`

**Example Usage:**  
```php
it('creates a material', function () {
    expect(true)->toBeTrue();
});
```

---


## docs/PERMISSIONS_MATRIX.md

# Permissions Matrix

This document is the source-of-truth for **authorization intent** in this repository.

- Roles are **global** (a user may have multiple roles).
- Permissions are **slugs** (kebab-case).
- Authorization is enforced via **Laravel Gates** defined in `App\Providers\AuthServiceProvider`.
- `super-admin` bypasses all gates via `Gate::before(...)`.

> Provider registration must include `App\Providers\AuthServiceProvider::class` (see `bootstrap/providers.php`).

---

## Permission Slugs

### System

- `system-tenants-manage`
- `system-users-manage`
- `system-roles-manage`

### Purchasing

- `purchasing-suppliers-view`
- `purchasing-suppliers-manage`
- `purchasing-purchase-orders-view`
- `purchasing-purchase-orders-create`
- `purchasing-purchase-orders-update`
- `purchasing-purchase-orders-manage`
- `purchasing-receiving-view`
- `purchasing-receiving-execute`

### Sales

- `sales-customers-view`
- `sales-customers-manage`
- `sales-sales-orders-view`
- `sales-sales-orders-create`
- `sales-sales-orders-update`
- `sales-sales-orders-manage`
- `sales-invoices-view`
- `sales-invoices-create`
- `sales-invoices-manage`

### Inventory

- `inventory-materials-view`
- `inventory-materials-manage`
- `inventory-products-view`
- `inventory-products-manage`
- `inventory-recipes-view`
- `inventory-adjustments-view`
- `inventory-adjustments-execute`
- `inventory-make-orders-view`
- `inventory-make-orders-execute` (does not imply view)
- `inventory-make-orders-manage`

### Reports

- `reports-view`

---

## Role Capabilities

Roles map to **business responsibilities**, not UI screens.

### Super-Admin

Platform owner role.

- Allowed: **all permissions** (Gate bypass)
- Notes: may require explicit cross-tenant flows, but gate checks always pass.

### Admin

Tenant administrator role.

- `system-users-manage`
- `system-roles-manage`
- Purchasing: all purchasing permissions
- Sales: all sales permissions
- Inventory: all inventory permissions
- `reports-view`
- Notes: **no cross-tenant access**; tenancy scoping still applies.

### Founder

Business owner/operator role (non-admin).

- Purchasing: all purchasing permissions
- Sales: all sales permissions
- Inventory: all inventory permissions
- `reports-view`

### Purchasing

Procurement-focused role.

- `purchasing-suppliers-view`
- `purchasing-suppliers-manage`
- `purchasing-purchase-orders-view`
- `purchasing-purchase-orders-create`
- `purchasing-purchase-orders-update`
- `purchasing-purchase-orders-manage`
- `purchasing-receiving-view`
- `purchasing-receiving-execute`
- `reports-view`

### Sales

Revenue-focused role.

- `sales-customers-view`
- `sales-customers-manage`
- `sales-sales-orders-view`
- `sales-sales-orders-create`
- `sales-sales-orders-update`
- `sales-sales-orders-manage`
- `sales-invoices-view`
- `sales-invoices-create`
- `sales-invoices-manage`
- `reports-view`

### Inventory

Stock and production-focused role.

- `inventory-materials-view`
- `inventory-materials-manage`
- `inventory-products-view`
- `inventory-products-manage`
- `inventory-recipes-view`
- `inventory-adjustments-view`
- `inventory-adjustments-execute`
- `inventory-make-orders-view`
- `inventory-make-orders-execute`
- `inventory-make-orders-manage`
- `reports-view`

### Tasker

Execution-only role.

- `inventory-make-orders-view`
- `inventory-make-orders-execute`
- `reports-view`

---

## Enforcement Notes

- **All permission checks** must use gates: `Gate::allows('<permission-slug>')` or `@can('<permission-slug>')`.
- Make Orders execute permission does not imply view; both gates must be evaluated where required.
- Do not hardcode role names in controllers/services (except `super-admin` bypass in `Gate::before`).
- Any new domain area must introduce permission slugs and update this matrix in the same PR.

---

## Provider Registration

Ensure `AuthServiceProvider` is registered (e.g., in `bootstrap/providers.php`):

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
];
```


## docs/ENUMS.md

# ENUMS — Canonical Enum Authority

This document defines the canonical, normative enum-like values used throughout the system.
It is the source of truth for all domain-level enum-like values referenced in database schemas, models, actions, and tests.

Do not introduce new enum values without updating this document.

---

## Change Discipline

- New enum values MUST be added here before use.
- Database enum/CHECK constraints MUST match this document.
- Tests MUST only use values defined here.
- Removing or renaming a value requires:
    - Migration
    - Backfill or data safety plan
    - Update to this document

---

## Inventory

### Inventory Count Status (Computed)

**Name:** InventoryCount status  
**Storage location(s):** Computed attribute on `InventoryCount` (no database column)  
**Allowed values:**

- `draft`
- `posted`

**Semantic meaning:**

- `draft`: The inventory count has not been posted to the ledger. (`posted_at` is `NULL`.)
- `posted`: The inventory count has been posted to the ledger. (`posted_at` is not `NULL`.)

**Notes:**

- This is a computed status derived solely from `posted_at`.
- Do not store or persist a separate status column.

---

## Manufacturing

### Make Order Status

**Name:** MakeOrder status  
**Storage location(s):** `make_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `SCHEDULED`
- `MADE`

**Semantic meaning:**

- `DRAFT`: Planned make order with no scheduled date.
- `SCHEDULED`: Due date set; still no stock moves.
- `MADE`: Executed; stock moves have been posted.

**Notes:**

- Status transitions are DRAFT → SCHEDULED → MADE.
- MADE is terminal.

---

## Stock / Inventory Ledger

### Stock Move Type

**Name:** StockMove type  
**Storage location(s):** `stock_moves.type` (enum column)  
**Allowed values:**

- `receipt`
- `issue`
- `adjustment`
- `inventory_count_adjustment`

**Semantic meaning:**

- `receipt`: Inventory added to on-hand (e.g., purchase receipt).
- `issue`: Inventory removed from on-hand (e.g., sale issue or consumption).
- `adjustment`: Generic correction movement not tied to an inventory count.
- `inventory_count_adjustment`: Variance movement generated by posting an inventory count.

**Notes:**

- `inventory_count_adjustment` must be used for inventory count posting variance.
- Do not use `adjustment` for inventory count posting.
- `inventory_count_adjustment` exists to preserve auditability and traceability.
- It allows inventory counts to be reported, reversed, or analyzed independently of generic adjustments.

---

## Conflicts / Ambiguities Report

No conflicts or ambiguities were found at time of creation based on existing migrations, models, actions, and tests.


## docs/DB_SCHEMA.md

# Database Schema Inventory (DB_SCHEMA)

This document inventories **all database tables and columns** as defined by migrations.
It exists to bootstrap **accurate, lossless context** for humans and AI.

This file is **descriptive only**.  
Migrations remain the **sole source of truth**.

---

## Global Conventions

- **DDL Authority:** `database/migrations/`
- **Enum Values:** Defined exclusively in `docs/ENUMS.md`
- **Tenant Scoping:**
    - Tables with `tenant_id` are tenant-owned
    - Exceptions (auth-safe tables) are explicitly labeled
- **Indexes:**
    - _Explicit_ → declared with `->index()` / `->unique()`
    - _Implicit (FK index)_ → created automatically with foreign keys

---

## Table Index

- cache
- cache_locks
- failed_jobs
- inventory_counts
- inventory_count_lines
- item_purchase_options
- item_uom_conversions
- items
- job_batches
- jobs
- make_orders
- password_reset_tokens
- permissions
- permission_role
- recipes
- recipe_lines
- roles
- roles_users
- sessions
- stock_moves
- tenants
- uom_categories
- uom_conversions
- uoms
- users

---

## cache

**Tenant-owned:** No  
**Purpose:** Application cache store

### Columns

| Name       | Type       | Nullable | Notes       |
| ---------- | ---------- | -------- | ----------- |
| key        | string     | No       | Primary key |
| value      | mediumText | No       | —           |
| expiration | integer    | No       | —           |

### Keys & Indexes

- PK: `key`

---

## cache_locks

**Tenant-owned:** No  
**Purpose:** Distributed cache locking

### Columns

| Name       | Type    | Nullable | Notes       |
| ---------- | ------- | -------- | ----------- |
| key        | string  | No       | Primary key |
| owner      | string  | No       | —           |
| expiration | integer | No       | —           |

### Keys & Indexes

- PK: `key`

---

## failed_jobs

**Tenant-owned:** No  
**Purpose:** Queue failure tracking

### Columns

| Name       | Type      | Nullable | Notes                         |
| ---------- | --------- | -------- | ----------------------------- |
| id         | bigint    | No       | Primary key                   |
| uuid       | string    | No       | Unique                        |
| connection | text      | No       | —                             |
| queue      | text      | No       | —                             |
| payload    | longText  | No       | —                             |
| exception  | longText  | No       | —                             |
| failed_at  | timestamp | No       | Defaults to CURRENT_TIMESTAMP |

### Keys & Indexes

- PK: `id`
- Unique: `uuid`

---

## inventory_counts

**Tenant-owned:** Yes  
**Purpose:** Inventory count sessions

### Columns

| Name              | Type      | Nullable | Notes                     |
| ----------------- | --------- | -------- | ------------------------- |
| id                | bigint    | No       | Primary key               |
| tenant_id         | bigint    | No       | FK → tenants.id (CASCADE) |
| counted_at        | timestamp | No       | —                         |
| posted_at         | timestamp | Yes      | —                         |
| posted_by_user_id | bigint    | Yes      | FK → users.id (SET NULL)  |
| notes             | text      | Yes      | —                         |
| created_at        | timestamp | Yes      | —                         |
| updated_at        | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(id, tenant_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `posted_by_user_id`

---

## inventory_count_lines

**Tenant-owned:** Yes  
**Purpose:** Line items for inventory counts

### Columns

| Name               | Type          | Nullable | Notes                     |
| ------------------ | ------------- | -------- | ------------------------- |
| id                 | bigint        | No       | Primary key               |
| tenant_id          | bigint        | No       | FK → tenants.id (CASCADE) |
| inventory_count_id | bigint        | No       | Part of composite FK      |
| item_id            | bigint        | No       | FK → items.id (CASCADE)   |
| counted_quantity   | decimal(18,6) | No       | —                         |
| notes              | text          | Yes      | —                         |
| created_at         | timestamp     | Yes      | —                         |
| updated_at         | timestamp     | Yes      | —                         |

### Foreign Keys

- `(inventory_count_id, tenant_id)` → inventory_counts.(id, tenant_id) (CASCADE)
- `item_id` → items.id (CASCADE)

### Keys & Indexes

- PK: `id`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`

---

## item_purchase_options

**Tenant-owned:** Yes  
**Purpose:** Purchase pack definitions

### Columns

| Name          | Type          | Nullable | Notes                     |
| ------------- | ------------- | -------- | ------------------------- |
| id            | bigint        | No       | Primary key               |
| tenant_id     | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id       | bigint        | No       | FK → items.id (CASCADE)   |
| supplier_id   | bigint        | Yes      | No FK                     |
| supplier_sku  | string        | Yes      | —                         |
| pack_quantity | decimal(18,6) | No       | CHECK > 0                 |
| pack_uom_id   | bigint        | No       | FK → uoms.id (CASCADE)    |
| created_at    | timestamp     | Yes      | —                         |
| updated_at    | timestamp     | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `(tenant_id, item_id)`
- Index: `(tenant_id, supplier_sku)`
- Implicit (FK index): `item_id`
- Implicit (FK index): `pack_uom_id`

---

## item_uom_conversions

**Tenant-owned:** Yes  
**Purpose:** Item-specific cross-category UoM conversions

### Columns

| Name              | Type          | Nullable | Notes                     |
| ----------------- | ------------- | -------- | ------------------------- |
| id                | bigint        | No       | Primary key               |
| tenant_id         | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id           | bigint        | No       | FK → items.id (CASCADE)   |
| from_uom_id       | bigint        | No       | FK → uoms.id (CASCADE)    |
| to_uom_id         | bigint        | No       | FK → uoms.id (CASCADE)    |
| conversion_factor | decimal(12,6) | No       | CHECK > 0                 |
| created_at        | timestamp     | Yes      | —                         |
| updated_at        | timestamp     | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, item_id, from_uom_id, to_uom_id)`
- Implicit (FK index): tenant_id, item_id, from_uom_id, to_uom_id

---

## items

**Tenant-owned:** Yes  
**Purpose:** Stock-tracked items

### Columns

| Name              | Type      | Nullable | Notes                     |
| ----------------- | --------- | -------- | ------------------------- |
| id                | bigint    | No       | Primary key               |
| tenant_id         | bigint    | No       | FK → tenants.id (CASCADE) |
| name              | string    | No       | —                         |
| is_purchasable    | boolean   | No       | Default false             |
| is_sellable       | boolean   | No       | Default false             |
| is_manufacturable | boolean   | No       | Default false             |
| base_uom_id       | bigint    | No       | FK → uoms.id              |
| created_at        | timestamp | Yes      | —                         |
| updated_at        | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Implicit (FK index): tenant_id
- Implicit (FK index): base_uom_id

---

## job_batches

**Tenant-owned:** No  
**Purpose:** Queue batch tracking

### Columns

| Name           | Type       | Nullable | Notes       |
| -------------- | ---------- | -------- | ----------- |
| id             | string     | No       | Primary key |
| name           | string     | No       | —           |
| total_jobs     | integer    | No       | —           |
| pending_jobs   | integer    | No       | —           |
| failed_jobs    | integer    | No       | —           |
| failed_job_ids | longText   | No       | —           |
| options        | mediumText | Yes      | —           |
| cancelled_at   | integer    | Yes      | —           |
| created_at     | integer    | No       | —           |
| finished_at    | integer    | Yes      | —           |

### Keys & Indexes

- PK: `id`

---

## jobs

**Tenant-owned:** No  
**Purpose:** Queue jobs

### Columns

| Name         | Type                | Nullable | Notes       |
| ------------ | ------------------- | -------- | ----------- |
| id           | bigint              | No       | Primary key |
| queue        | string              | No       | —           |
| payload      | longText            | No       | —           |
| attempts     | unsignedTinyInteger | No       | —           |
| reserved_at  | unsignedInteger     | Yes      | —           |
| available_at | unsignedInteger     | No       | —           |
| created_at   | unsignedInteger     | No       | —           |

### Keys & Indexes

- PK: `id`
- Index: `queue`

---

## make_orders

**Tenant-owned:** Yes  
**Purpose:** Persisted make orders with lifecycle

### Columns

| Name               | Type          | Nullable | Notes                                 |
| ------------------ | ------------- | -------- | ------------------------------------- |
| id                 | bigint        | No       | Primary key                           |
| tenant_id          | bigint        | No       | FK → tenants.id (CASCADE)             |
| recipe_id          | bigint        | No       | FK → recipes.id (CASCADE)             |
| output_item_id     | bigint        | No       | FK → items.id (CASCADE)               |
| output_quantity    | decimal(18,6) | No       | Canonical scale                       |
| status             | string        | No       | DRAFT, SCHEDULED, MADE                |
| due_date           | date          | Yes      | Set on schedule                       |
| scheduled_at       | timestamp     | Yes      | Set on schedule                       |
| made_at            | timestamp     | Yes      | Set on make                           |
| created_by_user_id | bigint        | Yes      | FK → users.id (SET NULL)              |
| made_by_user_id    | bigint        | Yes      | FK → users.id (SET NULL)              |
| created_at         | timestamp     | Yes      | —                                     |
| updated_at         | timestamp     | Yes      | —                                     |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, status)`
- Index: `(tenant_id, due_date)`
- Index: `(tenant_id, recipe_id)`
- Index: `(tenant_id, output_item_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `recipe_id`
- Implicit (FK index): `output_item_id`
- Implicit (FK index): `created_by_user_id`
- Implicit (FK index): `made_by_user_id`

---

## password_reset_tokens

**Tenant-owned:** No  
**Purpose:** Password reset tokens

### Columns

| Name       | Type      | Nullable | Notes       |
| ---------- | --------- | -------- | ----------- |
| email      | string    | No       | Primary key |
| token      | string    | No       | —           |
| created_at | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `email`

---

## permissions

**Tenant-owned:** No  
**Purpose:** Permission slugs

### Columns

| Name       | Type      | Nullable | Notes       |
| ---------- | --------- | -------- | ----------- |
| id         | bigint    | No       | Primary key |
| slug       | string    | No       | Unique      |
| created_at | timestamp | Yes      | —           |
| updated_at | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`
- Unique: `slug`

---

## permission_role

**Tenant-owned:** No  
**Purpose:** Role-permission mapping

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

## recipes

**Tenant-owned:** Yes  
**Purpose:** Manufacturing recipes

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id         | bigint    | No       | Primary key               |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE) |
| item_id    | bigint    | No       | FK → items.id (CASCADE)   |
| is_active  | boolean   | No       | Default true              |
| is_default | boolean   | No       | Default false             |
| created_at | timestamp | Yes      | —                         |
| updated_at | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(id, tenant_id)`
- Unique: `(tenant_id, item_id)` where `is_default = 1` (partial/filtered; driver-specific)
- Index: `(tenant_id, item_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`

---

## recipe_lines

**Tenant-owned:** Yes  
**Purpose:** Recipe line items

### Columns

| Name       | Type          | Nullable | Notes                     |
| ---------- | ------------- | -------- | ------------------------- |
| id         | bigint        | No       | Primary key               |
| tenant_id  | bigint        | No       | FK → tenants.id (CASCADE) |
| recipe_id  | bigint        | No       | Part of composite FK      |
| item_id    | bigint        | No       | FK → items.id (CASCADE)   |
| quantity   | decimal(18,6) | No       | —                         |
| created_at | timestamp     | Yes      | —                         |
| updated_at | timestamp     | Yes      | —                         |

### Foreign Keys

- `(recipe_id, tenant_id)` → recipes.(id, tenant_id) (CASCADE)
- `item_id` → items.id (CASCADE)

### Keys & Indexes

- PK: `id`
- Index: `(recipe_id, item_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`

---

## roles

**Tenant-owned:** No  
**Purpose:** Global roles

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

## roles_users

**Tenant-owned:** No  
**Purpose:** Role-user mapping

### Columns

| Name       | Type      | Nullable | Notes                   |
| ---------- | --------- | -------- | ----------------------- |
| id         | bigint    | No       | Primary key             |
| role_id    | bigint    | No       | FK → roles.id (CASCADE) |
| user_id    | bigint    | No       | FK → users.id (CASCADE) |
| created_at | timestamp | Yes      | —                       |
| updated_at | timestamp | Yes      | —                       |

### Keys & Indexes

- PK: `id`
- Unique: `(role_id, user_id)`
- Implicit (FK index): `role_id`
- Implicit (FK index): `user_id`

---

## sessions

**Tenant-owned:** No  
**Purpose:** Session storage

### Columns

| Name          | Type     | Nullable | Notes       |
| ------------- | -------- | -------- | ----------- |
| id            | string   | No       | Primary key |
| user_id       | bigint   | Yes      | —           |
| ip_address    | string   | Yes      | —           |
| user_agent    | text     | Yes      | —           |
| payload       | longText | No       | —           |
| last_activity | integer  | No       | —           |

### Keys & Indexes

- PK: `id`
- Index: `user_id`
- Index: `last_activity`

---

## stock_moves

**Tenant-owned:** Yes  
**Purpose:** Append-only inventory ledger

### Columns

| Name        | Type          | Nullable | Notes                         |
| ----------- | ------------- | -------- | ----------------------------- |
| id          | bigint        | No       | Primary key                   |
| tenant_id   | bigint        | No       | FK → tenants.id (CASCADE)     |
| item_id     | bigint        | No       | FK → items.id (CASCADE)       |
| uom_id      | bigint        | No       | FK → uoms.id (CASCADE)        |
| quantity    | decimal(18,6) | No       | Signed                        |
| type        | enum          | No       | See ENUMS.md                  |
| source_type | string        | Yes      | Polymorphic                   |
| source_id   | bigint        | Yes      | Polymorphic                   |
| created_at  | timestamp     | No       | Defaults to CURRENT_TIMESTAMP |

### Keys & Indexes

- PK: `id`
- Index: `(source_type, source_id)`
- Implicit (FK index): tenant_id, item_id, uom_id

---

## tenants

**Tenant-owned:** No  
**Purpose:** Tenant registry

### Columns

| Name        | Type      | Nullable | Notes       |
| ----------- | --------- | -------- | ----------- |
| id          | bigint    | No       | Primary key |
| tenant_name | string    | Yes      | —           |
| created_at  | timestamp | Yes      | —           |
| updated_at  | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`

---

## uom_categories

**Tenant-owned:** No  
**Purpose:** Unit-of-measure categories

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

::contentReference[oaicite:0]{index=0}


## routes/web.php

<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MakeOrderController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\UomCategoryController;
use App\Http\Controllers\UomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/manufacturing/inventory', [InventoryController::class, 'index']);

    Route::get('/inventory/counts', [InventoryCountController::class, 'index'])
        ->name('inventory.counts.index');
    Route::get('/manufacturing/inventory-counts', [InventoryCountController::class, 'index']);
    Route::post('/inventory/counts', [InventoryCountController::class, 'store'])
        ->name('inventory.counts.store');
    Route::post('/manufacturing/inventory-counts', [InventoryCountController::class, 'store']);
    Route::get('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'show'])
        ->name('inventory.counts.show');
    Route::get('/manufacturing/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'show']);
    Route::patch('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'update'])
        ->name('inventory.counts.update');
    Route::patch('/manufacturing/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'update']);
    Route::delete('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'destroy'])
        ->name('inventory.counts.destroy');
    Route::delete('/manufacturing/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'destroy']);
    Route::post('/inventory/counts/{inventoryCount}/post', [InventoryCountController::class, 'post'])
        ->name('inventory.counts.post');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/post', [InventoryCountController::class, 'post']);

    Route::post('/inventory/counts/{inventoryCount}/lines', [InventoryCountController::class, 'storeLine'])
        ->name('inventory.counts.lines.store');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/lines', [InventoryCountController::class, 'storeLine']);
    Route::patch('/inventory/counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'updateLine'])
        ->name('inventory.counts.lines.update');
    Route::patch('/manufacturing/inventory-counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'updateLine']);
    Route::delete('/inventory/counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'destroyLine'])
        ->name('inventory.counts.lines.destroy');
    Route::delete('/manufacturing/inventory-counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'destroyLine']);

    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::post('/materials', [ItemController::class, 'store'])->name('materials.store');
    Route::patch('/materials/{item}', [ItemController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{item}', [ItemController::class, 'destroy'])->name('materials.destroy');
    Route::get('/materials/uom-categories', [UomCategoryController::class, 'index'])
        ->name('materials.uom-categories.index');
    Route::get('/manufacturing/uom-categories', [UomCategoryController::class, 'index']);
    Route::post('/materials/uom-categories', [UomCategoryController::class, 'store'])
        ->name('materials.uom-categories.store');
    Route::post('/manufacturing/uom-categories', [UomCategoryController::class, 'store']);
    Route::patch('/materials/uom-categories/{uomCategory}', [UomCategoryController::class, 'update'])
        ->name('materials.uom-categories.update');
    Route::patch('/manufacturing/uom-categories/{uomCategory}', [UomCategoryController::class, 'update']);
    Route::delete('/materials/uom-categories/{uomCategory}', [UomCategoryController::class, 'destroy'])
        ->name('materials.uom-categories.destroy');
    Route::delete('/manufacturing/uom-categories/{uomCategory}', [UomCategoryController::class, 'destroy']);
    Route::get('/materials/{item}', [ItemController::class, 'show'])
        ->name('materials.show');

    Route::get('/manufacturing/uoms', [UomController::class, 'index'])
        ->name('manufacturing.uoms.index');
    Route::post('/manufacturing/uoms', [UomController::class, 'store'])
        ->name('manufacturing.uoms.store');
    Route::patch('/manufacturing/uoms/{uom}', [UomController::class, 'update'])
        ->name('manufacturing.uoms.update');
    Route::delete('/manufacturing/uoms/{uom}', [UomController::class, 'destroy'])
        ->name('manufacturing.uoms.destroy');

    Route::get('/manufacturing/recipes', [RecipeController::class, 'index'])
        ->name('manufacturing.recipes.index');
    Route::get('/manufacturing/recipes/{recipe}', [RecipeController::class, 'show'])
        ->name('manufacturing.recipes.show');
    Route::post('/manufacturing/recipes', [RecipeController::class, 'store'])
        ->name('manufacturing.recipes.store');
    Route::patch('/manufacturing/recipes/{recipe}', [RecipeController::class, 'update'])
        ->name('manufacturing.recipes.update');
    Route::delete('/manufacturing/recipes/{recipe}', [RecipeController::class, 'destroy'])
        ->name('manufacturing.recipes.destroy');
    Route::post('/manufacturing/recipes/{recipe}/lines', [RecipeController::class, 'storeLine'])
        ->name('manufacturing.recipes.lines.store');
    Route::patch('/manufacturing/recipes/{recipe}/lines/{line}', [RecipeController::class, 'updateLine'])
        ->name('manufacturing.recipes.lines.update');
    Route::delete('/manufacturing/recipes/{recipe}/lines/{line}', [RecipeController::class, 'destroyLine'])
        ->name('manufacturing.recipes.lines.destroy');

    Route::get('/manufacturing/make-orders', [MakeOrderController::class, 'index'])
        ->name('manufacturing.make-orders.index');
    Route::post('/manufacturing/make-orders', [MakeOrderController::class, 'store'])
        ->name('manufacturing.make-orders.store');
    Route::post('/manufacturing/make-orders/{makeOrder}/schedule', [MakeOrderController::class, 'schedule'])
        ->name('manufacturing.make-orders.schedule');
    Route::post('/manufacturing/make-orders/{makeOrder}/make', [MakeOrderController::class, 'make'])
        ->name('manufacturing.make-orders.make');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
