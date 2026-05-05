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
10. docs/PR3_ROADMAP.md

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

This reconciled version reflects the **current implementation state** of the repository.

This version explicitly accounts for the **dependency chain**:

> **UoM Category → UoM → Item (Material)**

and uses **process-based top-level navigation**, with Materials grouped under **Manufacturing**.

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

## Navigation Model (Reconciled)

- **Top horizontal navigation (Breeze-style)**
- No sidebars
- No dashboard-heavy layouts

### Current Navigation Pattern

- **Purchasing** and **Manufacturing** are the current top-level domain menus
- **Materials** is reached via **Manufacturing → Materials**
- Manufacturing dropdown currently includes:
    - Inventory
    - Inventory Counts
    - Orders (Make Orders)
    - Materials
    - Recipes
    - Units of Measure
    - UoM Categories

This keeps navigation aligned with current process-based domain ownership.

---

## DOMAIN 1 — Materials (Foundation)

> Materials are the **first UI domain** because all downstream domains depend on Items.

### PR2-MAT-001 — Materials Navigation + Index ✅ (Implemented, later expanded)

**Goal**  
Expose Materials as a first-class domain with read-only visibility.

**Includes**

- Add Materials visibility within current top horizontal navigation
- Route: `/materials`
- Gate enforcement: `inventory-materials-view`
- Index view listing all Items

**UI**

- Clean list/table (Tailwind, Breeze-aligned)
- Columns: Name, Base UoM, Flags
- Empty state with “Create Material” CTA

**Implementation Note**

- The current codebase has already expanded beyond this original slice to include create/edit/delete and related UoM management.

---

## DOMAIN 1A — Units of Measure (Materials Support)

> These PRs unblock **Material creation** and must precede PR2-MAT-004.

### PR2-UOM-001 — UoM Categories CRUD (AJAX) ✅ (Implemented)

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

### PR2-UOM-002 — Units of Measure CRUD (AJAX) ✅ (Implemented)

**Goal**  
Allow managing Units of Measure within categories.

**Includes**

- Manufacturing nav dropdown entry: **Units**
- Route: `/manufacturing/uoms`
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

### PR2-UOM-004 — Default + User-Managed UoM Conversions (Global + Tenant + Item-Specific) ✅ (Implemented)

## Goal

Introduce a complete UoM conversion system with:

- System-seeded universal conversions
- Tenant-managed conversions
- Item-specific conversions (cross-category support)
- Clear separation between global, tenant, and item-level behavior

---

## Includes

- Add `tenant_id` to `uom_conversions`
- Unique constraint:
    - `(tenant_id, from_uom_id, to_uom_id)`
- Seed global conversions (`tenant_id = null`)
- Idempotent seeding (runs every seed)
- Automatic bidirectional generation
- Same-category enforcement (non-item conversions)

- Integrate existing `item_uom_conversions` into unified UI
- Support cross-category conversions at item level

---

## Global Conversion Rules

Allowed:

- Mass (g, kg, lb, oz)
- Volume (ml, l)
- Length (cm, m)

Not allowed:

- Count (ea, pc)
- Cross-category

Seeding:

- Must fail loudly
- No silent skips

---

## User (Tenant) Conversions

Navigation:

Manufacturing → Units of Measure → Conversions

Routes:

/manufacturing/uom-conversions

Includes:

- Full AJAX CRUD
- Create / Edit / Delete
- Permission: `inventory-materials-manage`

---

## Item-Specific Conversions

Handled via: `item_uom_conversions`

Includes:

- Cross-category conversions allowed
- Item-scoped (tenant-isolated)
- CRUD within same conversions interface
- Fields:
    - `item_id`
    - `from_uom_id`
    - `to_uom_id`
    - `conversion_factor`

Rules:

- Applies only to selected item
- Overrides tenant and global conversions
- Supports discrete-to-measurable mappings (e.g. `1 pc = 180 g`)

---

## UI Behavior

Single unified interface:

### Section 1 — General Conversions

- Show all conversions:
    - Global (read-only)
    - Tenant (editable)

Rules:

- Global cannot be edited or deleted
- Tenant conversions fully editable

---

### Section 2 — Item-Specific Conversions

- Separate section or tab within same page
- CRUD list scoped to selected item
- Allows cross-category definitions
- Clear visual distinction from general conversions

---

## Precedence Rules

Conversion resolution order:

1. Item-specific (`item_uom_conversions`)
2. Tenant-level (`uom_conversions.tenant_id = tenant`)
3. Global (`tenant_id = null`)

---

## Validation Rules

- Same-category enforcement for general conversions
- Cross-category allowed only for item-specific
- Unique constraint enforced
- No duplicate conversions
- Global conversions immutable
- Seeding fails on invalid config

---

## Testing (Pest Feature)

- Minimum 20 tests (target 30+)

Must cover:

- Bidirectional generation
- Idempotent seeding
- Same-category validation
- Cross-category item validation
- Global vs tenant vs item visibility
- Read-only enforcement (global)
- Precedence resolution
- Unique constraint enforcement
- Failure cases for invalid config

---

## Documentation Impact

Update:

- `DB_SCHEMA.md`
    - Add `uom_conversions.tenant_id`

- `ARCHITECTURE_INVENTORY.md`
    - UoM Conversion System
    - Conversion Precedence Pattern

Add architecture YAML:

- UoM Conversion System
- Conversion Precedence Rules

---

## Out of Scope

- Automatic inference of item-specific conversions
- Bulk import of conversions
- Conversion suggestion engine

---

## DOMAIN 1B — Materials CRUD (Now Unblocked)

### PR2-MAT-002 — Create Material (AJAX) ✅ (Implemented, renumbered)

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

### PR2-MAT-003 — Edit Material (AJAX) ✅ (Implemented)

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

### PR2-MAT-004 — Row Actions Menu + Delete ✅ (Implemented)

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

### PR2-MAT-005 — Material Detail View (Read-Only) ✅ (Implemented, later expanded)

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

## DOMAIN 2 — PR2-INV-001 — Inventory Visibility & Counts ✅ (Implemented)

_(Unchanged)_

- Inventory Overview
- Inventory Counts (Draft)
- Inventory Count Posting

---

## DOMAIN 3 — Recipes & Manufacturing

### PR3-REC-001 — Recipes Index + Detail (Read-Only) ✅ (Implemented, later expanded)

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

### PR3-REC-002 — Recipes CRUD + Recipe Lines CRUD (AJAX) ✅ (Implemented)

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

### PR3-REC-003 — Recipe Output Quantity Support + Recipe Naming ✅ (Implemented)

**Goal**  
Introduce explicit output quantity and user-defined naming for recipes.

**Problem Statement**  
Recipes currently need both explicit output quantity and a user-facing name so multiple recipes for the same output item can be distinguished.

**Includes**

- Add required `name` to recipes
- Add `output_quantity` to recipes
- Default existing records to `'0.000000'`
- Update create/edit UI to capture recipe name and quantity
- Display recipe name and output quantity in index and detail views
- Persist using BCMath string (scale = 6)

**Rules**

- Recipe name is required for new/updated recipes
- Output quantity is required for new/updated recipes
- Must be ≥ 0 (existing default = 0 allowed for legacy)
- Stored as string, not float
- Uses canonical scale = 6

**Execution Impact**

`ExecuteRecipeAction` must treat its argument as runs and scale output from the recipe-defined output quantity.

Example:
Recipe output = 10
Execute 2 runs → output receipt = 20

**Testing**

- Creation and validation
- Recipe naming and same-output differentiation
- Default backfill behavior
- Runs semantics and execution scaling correctness
- Precision handling

**Documentation Impact**

- Update architecture docs to reflect new recipe invariant

---

### PR3-MO-001 — Make Orders (Execute Recipe) ✅ (Implemented via persisted make orders)

**Goal**  
Allow executing a recipe to create ledger movements.

**Includes**

- Navigation: **Manufacturing → Orders (Make Orders)**
- Route: `/manufacturing/make-orders`
- Execution UI:
    - Select recipe
    - Enter runs
    - Submit (AJAX)
- Calls `ExecuteRecipeAction`
- Success toast + lightweight summary

**Rules**

- Ledger-first execution (issues + receipt)
- BCMath with canonical scale
- This original direct-execution-only approach was later replaced in the current implementation by persisted make orders (`PR3b`)

**Permissions**

- View: `inventory-make-orders-view`
- Execute: `inventory-make-orders-execute`

---

### PR3b — Make Orders Lifecycle (Persisted) ✅ (Implemented)

**Goal**  
Implement persisted Make Orders with full lifecycle (Draft → Scheduled → Made/Executed), replacing direct execution. Index list at /manufacturing/make-orders.

**Includes**

- Index UI: table of Make Orders (Recipe/Output, Runs, Status, Due Date, Actions)
- Create draft: slide-over (select active recipe + runs) → saves as DRAFT
- Schedule: set due date on draft → status SCHEDULED
- Make/Execute: on scheduled order → calls ExecuteRecipeAction → status MADE + stock moves
- Tenant isolation, permission gates, AJAX actions, toasts/errors
- Idempotent "make" (error if already made)

**Rules**

- Ledger-first on "make": issues inputs + receipts output via ExecuteRecipeAction
- Make Order quantity is runs, not desired output quantity
- Produced output is `runs × recipe.output_quantity`
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

This domain introduces **supplier management, supplier-specific material pricing, and purchasing primitives**, with clear separation between **planning prices**, **supplier catalog/prices**, and **order price snapshots**.

---

### PR2-PUR-001 — Suppliers Index + Create (AJAX) ✅ (Implemented)

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

### PR2-MAT-006 — Material Planning Price (Schema + UI) ✅ (Implemented)

**Goal**  
Add a **planning-only placeholder price** to materials.

**Includes**

- Schema:
    - `items.default_price_cents`
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

### PR2-PUR-002 — Supplier CRUD (Edit + Delete) ✅ (Implemented)

**Goal**  
Complete supplier lifecycle management.

**Includes**

- Edit supplier (AJAX slide-over)
- Delete supplier
- Delete blocked if supplier has linked supplier catalog records
- Gate: `purchasing-suppliers-manage`

---

### PR2-PUR-003 — Supplier ↔ Material Catalog + Pricing ✅ (Implemented)

**Goal**  
Define which materials are bought from which suppliers, including **supplier-specific pricing**.

**Includes**

- Implemented as tenant-owned supplier catalog records using:
    - `item_purchase_options`
    - `item_purchase_option_prices`
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
- The current implementation keeps supplier purchase options and separate price records for expected/current pricing

---

### PR2-PUR-004 — Purchase Orders (Draft + Pricing Snapshot) ✅ (Implemented, later expanded)

---

## Goal

Introduce Purchase Orders with **immutable pricing snapshots** captured at the moment a line is added.

This PR establishes the **foundation of the PO system**: draft creation, line management, and permanent price capture.  
This was the original foundation slice. The current implementation later expanded into lifecycle and receiving behavior covered by `PR2-PUR-005`.

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

| Layer          | Location                                                          | Purpose                  |
| -------------- | ----------------------------------------------------------------- | ------------------------ |
| Planning       | `items.default_price_cents` + `items.default_price_currency_code` | Forecasting only         |
| Supplier       | `item_purchase_options.*` + `item_purchase_option_prices.*`       | Expected buy price       |
| Purchase Order | `purchase_order_lines.*`                                          | Legal / accounting truth |

---

### PR2-PUR-005 — Purchase Order Lifecycle & Receiving ✅ (Implemented)

Goal

Introduce full Purchase Order lifecycle beyond DRAFT and implement industry-standard receiving with:

Line-level receipts

Receipt history (header + lines)

Automatic inventory impact (stock_moves)

Status transitions derived from receipts

Manual lifecycle controls where appropriate

This PR turns Purchase Orders from a pricing document into a true operational document.

Purchase Order Statuses

DRAFT

OPEN

PARTIALLY-RECEIVED

RECEIVED

BACK-ORDERED

SHORT-CLOSED

CANCELLED

Terminal States

RECEIVED

SHORT-CLOSED

CANCELLED

Receiving is not allowed in terminal states.

Status Transition Rules (Industry Standard)

DRAFT → OPEN (manual)

OPEN → CANCELLED (only if no receipts exist)

OPEN → BACK-ORDERED (manual)

BACK-ORDERED → OPEN (manual)

OPEN → PARTIALLY-RECEIVED (automatic after first receipt)

BACK-ORDERED → PARTIALLY-RECEIVED (automatic after receipt)

PARTIALLY-RECEIVED → RECEIVED (automatic when fully received)

PARTIALLY-RECEIVED → SHORT-CLOSED (manual when remaining qty is short-closed)

Receiving Model (Core of This PR)

Receiving is event-based, not status-based.

New Table — purchase_order_receipts (header)

id (PK)

tenant_id (FK)

purchase_order_id (FK)

received_at (datetime)

received_by_user_id (FK → users)

reference (nullable)

notes (nullable)

New Table — purchase_order_receipt_lines

id (PK)

tenant_id (FK)

purchase_order_receipt_id (FK)

purchase_order_line_id (FK)

received_quantity (BCMath string, canonical scale)

Inventory Impact

Each receipt line must create:

stock_moves.type = RECEIPT

stock_moves.status = POSTED

Receipts exist for audit trail.
Stock moves exist for inventory truth.

Short-Close Model

Short-close is line-level.

Users record the remaining quantity that will never be received.

When all remaining quantities are either:

Received

Short-closed

The PO becomes SHORT-CLOSED.

Permissions

New permission slug:

purchasing-purchase-orders-receive

This permission controls:

Receiving

Short-closing

Status changes beyond DRAFT and OPEN

UI Requirements
PO Index

Show status column

Row actions menu includes:

View

Receive (opens slide-over)

Contextual status actions

PO Show Page

Status control (dropdown/actions)

“Receive” button at PO level (multi-line)

“Receive” button per line (single-line)

Slide-over receive panel (AJAX)

Receiving UX Rules
Action Behavior
Receive from PO Multi-line receipt
Receive from line Single-line receipt
Submit receipt Creates receipt + stock moves + auto status update
Validation 422 JSON, no page refresh

Receiving allowed only when status is:

OPEN

BACK-ORDERED

PARTIALLY-RECEIVED

Cancellation Rule

Allowed only if no receipts exist

After receiving begins, user must short-close instead

AJAX / Controller Pattern

All endpoints are JSON-only and must follow the Ajax CRUD pattern:

Receive

Short-close

Status change

No redirects. No page refresh.

Tests (Pest Feature)

Tests must cover:

All status transitions (allow + deny)

Receiving rules

Stock move creation

Cancel prevention after receipt

Short-close rules

Permission allow/deny

Terminal state protections

Minimum 20 tests per file.

Documentation Impact

This PR requires updates to:

ENUMS.md (PO statuses)

DB_SCHEMA.md (receipt tables)

ARCHITECTURE_INVENTORY.md (Receipt + Lifecycle patterns)

Architecture YAML files for:

PO Lifecycle

## Receipt Event Pattern

---

### PR2-PUR-006 — Receiving Inventory Impact Fix ✅ (Implemented)

Goal
Ensure purchase order receiving always impacts inventory via stock moves.

Problem Statement
Current behavior allows purchase orders to reach RECEIVED without reliably creating stock moves.

Includes

Enforce stock move creation for every receipt line
Ensure:
stock_moves.type = receipt
stock_moves.status = POSTED
Guarantee linkage between receipt lines and stock moves
Ensure idempotency (no duplicate stock moves per receipt line)
Ensure receipt stock move quantity uses:
received_quantity × item_purchase_options.pack_quantity

Rules

Inventory impact is mandatory for all receipts
A PO cannot be considered RECEIVED unless inventory is updated
All quantity math uses BCMath (scale = 6)
No float math
Stock moves are the single source of truth

Validation

Receipt must fail if stock move creation fails
Use transactional integrity (receipt + stock moves)

Testing

Receipt creates stock moves
Duplicate prevention
Multi-line receipts
Status transitions tied to inventory
Permission enforcement

Documentation Impact

Clarify invariant: receiving always creates stock moves
Update architecture docs only if required

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

### PR2-UOM-TEN-001 — Tenant-Scoped Units of Measure (Schema + Refactor) ✅ (Implemented)

**Problem Statement**  
UoM Categories and Units required tenant ownership to match CRUD access expectations.

**Implementation Note**  
Tenant scoping is already implemented in the current codebase.

**Includes**

- Add `tenant_id` to:
    - `uom_categories`
    - `uoms`
- Backfill strategy
- Apply `HasTenantScope`
- Update tests
- Update `ARCHITECTURE_INVENTORY.md`
- Update `DB_SCHEMA.md`

**Result**

- UoM Categories and UoMs are tenant-owned, while system defaults continue to use `tenant_id = null`.

---

## DOMAIN 5 — UI Component Refactor (Post-PR2 Cleanup)

### PR2-UI-001 — Navigation-Only Tailwind Refactor

**Goal**  
Replace the Breeze-based navigation UI only with a modern, sleek, dark Tailwind-only navigation system.

**Includes**

- Keep the native `<nav>` element in the layout
- Use Blade components only for navigation parts:
    - `<x-nav-link>`
    - `<x-nav-dropdown>`
    - `<x-nav-dropdown-link>`
- Cover nav items, nav dropdowns, and nav dropdown links
- Preserve all existing routes, gates, `@can` checks, active states, and behavior
- Preserve desktop and mobile navigation behavior
- Remove Breeze component usage from navigation only
- Dark charcoal/navy top bar with clean white text
- Rounded nav items, floating dropdown panels, subtle shadows and borders
- Smooth hover/open states
- Multi-level dropdown readiness
- Mobile menus use accordion-style nested items, not desktop flyouts
- Do not delete shared Breeze components unless confirmed unused outside navigation

**Out of Scope**

- Sidebar redesign
- Page layout redesign
- Domain logic changes
- Route changes
- Permission changes
- Deleting Breeze/shared components used outside navigation
- Any non-navigation UI refactor

---

### PR2-UOM-003 — UoM Display Precision + Global Quantity Formatting (UI-only) ✅ (Implemented)

**Goal**  
Introduce a UoM-level display precision field and enforce consistent quantity formatting across all UI views.

**Includes**

- Add `display_precision` to `uoms` (required, default = 1, allowed range = 0–6)
- Extend UoM CRUD UI to manage `display_precision`
- Introduce a single `QuantityFormatter` abstraction for centralized display formatting
- Introduce a Blade directive/helper that wraps the formatter
- Replace all quantity rendering in Blade views across all domains:
- Materials
- Inventory
- Inventory Counts
- Recipes
- Make Orders
- Purchasing (Orders, Receipts, Short-Closures)
- Enforce trailing zeros to match display precision
- Use string-safe half-up display rounding without changing storage precision
- No changes to storage math or BCMath canonical scale (remains 6)

**Rules**

- `display_precision` cannot exceed 6
- `display_precision` may be 0 (whole-unit display)
- Formatting is UI-only; storage precision is unchanged
- Formatting must be centralized (no ad-hoc formatting in views)
- No JavaScript-side formatting
- No global JavaScript state

**Permissions**

- No new permission slugs
- Managed under existing UoM CRUD permission: `inventory-materials-manage`

**Testing**

- Pest tests only
- Minimum 20 tests per file
- Tests must be complete and sufficient
- Coverage must include:
- Validation bounds (0–6)
- Default value behavior
- Authorization
- Formatter correctness
- Trailing zero enforcement
- Precision 0 edge case
- Precision 6 edge case

**Documentation Impact**

- `DB_SCHEMA.md` must reflect the new `uoms.display_precision` column
- `ARCHITECTURE_INVENTORY.md` must include:
- `QuantityFormatter` abstraction
- Blade directive/helper usage pattern
- `ENUMS.md` unaffected

**Out of Scope**

- Any changes to storage precision or BCMath scale
- JavaScript formatting or UI-only overrides per view

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

## Sales

### Customer Contact Primary Invariant

**Name:** Customer Contact Primary Invariant  
**Type:** Domain Rule  
**Location:**  
- `docs/architecture/sales/CustomerContactPrimaryInvariant.yaml`  
- `app/Http/Controllers/CustomerContactController.php`  
- `app/Models/Customer.php`  
- `app/Models/CustomerContact.php`  

**Purpose:**  
Document the customer-contact relationship, the split first-name/last-name contact shape, and the exactly-one-primary-when-contacts-exist invariant for customer contacts.

**When to Use:**  
Any customer contact create, update, delete, or primary-designation flow on the customer detail Contacts section.

**When Not to Use:**  
Customer records without contact mutations or unrelated sales-order contact snapshots.

**Public Interface:**  
- `Customer::contacts()`  
- `sales.customers.contacts.store`  
- `sales.customers.contacts.update`  
- `sales.customers.contacts.destroy`  
- `sales.customers.contacts.primary.update`  

**Example Usage:**  
```php
$customer->contacts()->create([
    'tenant_id' => $tenant->id,
    'first_name' => 'Jane',
    'last_name' => 'Buyer',
    'is_primary' => true,
]);
```

---

### Sales Order Draft Contact Assignment

**Name:** Sales Order Draft Contact Assignment  
**Type:** Domain Rule  
**Location:**  
- `docs/architecture/sales/SalesOrderDraftContactAssignment.yaml`  
- `app/Http/Controllers/SalesOrderController.php`  
- `app/Http/Requests/Sales/StoreSalesOrderRequest.php`  
- `app/Http/Requests/Sales/UpdateSalesOrderRequest.php`  
- `app/Models/SalesOrder.php`  

**Purpose:**  
Document the sales-order customer/contact rules shared by the Sales Orders index and the customer detail Orders mini-index.

**When to Use:**  
Any editable sales-order create, update, delete, or validation flow, including customer changes that may re-default the assigned contact.

**When Not to Use:**  
Sales-order lines, pricing snapshots, fulfillment/inventory effects, invoicing, or customer-contact primary designation outside a sales-order assignment.

**Public Interface:**  
- `SalesOrder::STATUS_DRAFT`  
- `SalesOrder::STATUS_OPEN`  
- `SalesOrder::isEditable()`  
- `SalesOrder::statuses()`  
- `sales.orders.index`  
- `sales.orders.store`  
- `sales.orders.update`  
- `sales.orders.destroy`  

**Example Usage:**  
```php
$order = SalesOrder::query()->create([
    'tenant_id' => $tenant->id,
    'customer_id' => $customer->id,
    'contact_id' => $customer->contacts->firstWhere('is_primary', true)?->id,
    'status' => SalesOrder::STATUS_DRAFT,
]);
```

---

### Sales Order Line Pricing And Editable Rules

**Name:** Sales Order Line Pricing And Editable Rules  
**Type:** Domain Rule  
**Location:**  
- `docs/architecture/sales/SalesOrderLinePricingAndDraftRules.yaml`  
- `app/Http/Controllers/SalesOrderLineController.php`  
- `app/Http/Requests/Sales/StoreSalesOrderLineRequest.php`  
- `app/Http/Requests/Sales/UpdateSalesOrderLineRequest.php`  
- `app/Models/SalesOrder.php`  
- `app/Models/SalesOrderLine.php`  

**Purpose:**  
Document the editable sales-order line mutation rules, immutable unit-price snapshots, and canonical scale-6 quantity/line-total behavior shared by the Sales Orders index and the customer detail Orders mini-index.

**When to Use:**  
Any sales-order line create, delete, or quantity-update flow for editable sales orders.

**When Not to Use:**  
Sales-order header customer/contact assignment, lifecycle transitions, fulfillment, shipping, invoicing, payments, or completion inventory impact.

**Public Interface:**  
- `SalesOrder::STATUS_DRAFT`  
- `SalesOrder::STATUS_OPEN`  
- `SalesOrder::allowsLineMutations()`  
- `SalesOrder::lines()`  
- `sales.orders.lines.store`  
- `sales.orders.lines.update`  
- `sales.orders.lines.destroy`  

**Example Usage:**  
```php
$line = SalesOrderLine::query()->create([
    'tenant_id' => $tenant->id,
    'sales_order_id' => $order->id,
    'item_id' => $item->id,
    'quantity' => '2.500000',
    'unit_price_cents' => $item->default_price_cents,
    'unit_price_currency_code' => $item->default_price_currency_code,
    'line_total_cents' => '832.500000',
]);
```

---

### Sales Order Completion Inventory Impact

**Name:** Sales Order Completion Inventory Impact  
**Type:** Domain Rule  
**Location:**  
- `docs/architecture/sales/SalesOrderCompletionInventoryImpact.yaml`  
- `app/Actions/Sales/CompleteSalesOrderAction.php`  
- `app/Http/Controllers/SalesOrderStatusController.php`  
- `app/Models/StockMove.php`  

**Purpose:**  
Document the inventory-ledger effects of `OPEN -> COMPLETED` sales-order transitions and the safeguards around transactional posting.

**When to Use:**  
Completing a sales order, posting issue stock moves from sales-order lines, or validating rollback/idempotency expectations.

**When Not to Use:**  
Editable header/line mutations, cancellation flows without inventory impact, or downstream fulfillment/invoicing/payment behavior.

**Public Interface:**  
- `CompleteSalesOrderAction::execute()`  
- `SalesOrder::STATUS_OPEN`  
- `SalesOrder::STATUS_COMPLETED`  
- `sales.orders.status.update`  

**Example Usage:**  
```php
$completedOrder = $completeSalesOrderAction->execute($salesOrder);
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
Represent named manufacturing recipes for items, including output quantity per run.

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
$recipe = Recipe::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'name' => 'Batch of Patties',
    'output_quantity' => '54.000000',
]);
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
- `execute(Recipe $recipe, string $runs): array`

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
<th>{{ __('Recipe Name') }}</th>
<th>{{ __('Input Item') }}</th>
<th>{{ __('Quantity') }}</th>
<th>{{ __('UoM') }}</th>
```

---

## Units of Measure

### QuantityFormatter

**Name:** QuantityFormatter  
**Type:** Support Utility  
**Location:** `app/Support/QuantityFormatter.php`

**Purpose:**  
Centralize UI quantity string formatting using UoM display precision.

**Notes:**  
- Accepts numeric strings, ints, floats, and null.
- Clamps precision to `0..6`.
- Preserves trailing zeros to requested precision.
- Uses string-safe half-up rounding for display output.
- Uses UoM-driven precision via `display_precision`.

**When to Use:**  
Rendering quantities for HTML and page payloads.

**When Not to Use:**  
Storage math or domain arithmetic (use BCMath with canonical scale 6).

**Public Interface:**  
- `QuantityFormatter::format($quantity, $precision)`  
- `QuantityFormatter::formatForUom($quantity, $uom, $fallbackPrecision = 6)`

**Example Usage:**  
```php
$display = QuantityFormatter::formatForUom($line->quantity, $line->item?->baseUom, 1);
```

---

### Blade Quantity Directives

**Name:** Blade Quantity Directives  
**Type:** Blade Integration Pattern  
**Location:** `app/Providers/AppServiceProvider.php`

**Purpose:**  
Provide a Blade-first wrapper over `QuantityFormatter` so views do not format quantities ad-hoc.

**Notes:**  
- Quantity display in Blade should use directives backed by `QuantityFormatter`.
- JavaScript must consume backend-provided display strings; it must not reformat quantities.

**When to Use:**  
Any quantity rendered directly in Blade templates.

**When Not to Use:**  
Currency formatting or non-quantity values.

**Public Interface:**  
- `@qty($value, $precision)`  
- `@qtyForUom($value, $uom, $fallbackPrecision = 6)`

**Example Usage:**  
```blade
@qtyForUom($item->onHandQuantity(), $item->baseUom, 1)
```

---

### UomCategory

**Name:** UomCategory  
**Type:** Eloquent Model  
**Location:** `app/Models/UomCategory.php`

**Purpose:**  
Group units of measure into categories that define safe conversion boundaries.

**Notes:**  
- Tenant-owned. System defaults use `tenant_id = null`.
- Names are unique per tenant.

**When to Use:**  
Defining conversion-safe groupings such as mass or volume.

**When Not to Use:**  
Cross-category conversion logic.

**Public Interface:**  
- `uoms()`

**Example Usage:**  
```php
$category = UomCategory::create([
    'tenant_id' => $tenant->id,
    'name' => 'Mass',
]);
```

---

### Uom

**Name:** Uom  
**Type:** Eloquent Model  
**Location:** `app/Models/Uom.php`

**Purpose:**  
Represent a unit of measure belonging to a single category.

**Notes:**  
- Tenant-owned. System defaults use `tenant_id = null`.
- `symbol` is unique per tenant; `name` is not unique.

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
    'tenant_id' => $tenant->id,
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

### UoM Conversion System

**Name:** UoM Conversion System  
**Type:** Domain Rule Set / UI + Persistence Pattern  
**Location:**  
- `app/Http/Controllers/UomConversionController.php`  
- `app/Models/UomConversion.php`  
- `app/Models/ItemUomConversion.php`  
- `app/Services/Uom/SystemUomCloner.php`  
- `resources/views/manufacturing/uom-conversions/index.blade.php`

**Purpose:**  
Unify global, tenant-managed, and item-specific conversion behavior behind one manufacturing UI and one precedence-aware lookup model.

**When to Use:**  
- Managing same-category global or tenant conversions  
- Managing item-specific overrides  
- Resolving a conversion for operational workflows

**When Not to Use:**  
- Implicit ad hoc unit math outside the defined conversion system  
- Cross-category general conversions

**Public Interface:**  
- `manufacturing.uom-conversions.*` routes  
- `UomConversion`  
- `ItemUomConversion`

**Example Usage:**  
```php
Gate::authorize('inventory-materials-manage');
```

---

### Conversion Precedence Pattern

**Name:** Conversion Precedence Pattern  
**Type:** Domain Resolution Rule  
**Location:**  
- `app/Http/Controllers/UomConversionController.php`  
- `app/Actions/Inventory/ReceivePurchaseOptionAction.php`  
- `docs/architecture/uom/ConversionPrecedence.yaml`

**Purpose:**  
Resolve unit conversions deterministically when multiple scopes can define a mapping.

**When to Use:**  
- Any lookup that must choose between item-specific, tenant, and global conversions

**When Not to Use:**  
- Writes or validations that should target one explicit scope only

**Public Interface:**  
- `resolve()` behavior  
- `item-specific > tenant > global`

**Example Usage:**  
```php
// Resolution order:
// 1. item-specific
// 2. tenant
// 3. global
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

### Supplier

**Name:** Supplier  
**Type:** Eloquent Model  
**Location:** `app/Models/Supplier.php`

**Purpose:**  
Represent tenant-owned suppliers for purchasing relationships.

**When to Use:**  
Managing suppliers for purchasing workflows.

**When Not to Use:**  
Materials or inventory entities.

**Public Interface:**  
- `tenant()`

**Example Usage:**  
```php
$supplier = Supplier::create([
    'tenant_id' => $tenant->id,
    'company_name' => 'Acme Supplies',
]);
```

---

### Supplier Delete Guard

**Name:** Supplier Delete Guard  
**Type:** Domain Guard / Service Interface  
**Location:**  
- `app/Services/Purchasing/SupplierDeleteGuard.php`  
- `app/Services/Purchasing/DefaultSupplierDeleteGuard.php`  
- `app/Http/Controllers/SupplierController.php`

**Purpose:**  
Provide a seam to block supplier deletion when supplier-linked purchasing catalog records exist, without broad schema refactors.

**When to Use:**  
Deleting suppliers via AJAX endpoints with a supplier catalog link check.

**When Not to Use:**  
Delete guards for non-supplier entities.

**Public Interface:**  
- `SupplierDeleteGuard::isLinkedToMaterials(Supplier $supplier): bool`

**Example Usage:**  
```php
if ($guard->isLinkedToMaterials($supplier)) {
    return response()->json([
        'message' => 'Supplier cannot be deleted because it is linked to purchasing records.',
    ], 422);
}
```

---

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

### Purchase Order Receipt Inventory Impact

**Name:** Purchase Order Receipt Inventory Impact  
**Type:** Domain Rule  
**Location:**  
- `docs/architecture/purchasing/PurchaseOrderReceiptInventoryImpact.yaml`  
- `app/Services/Purchasing/PurchaseOrderLifecycleService.php`  
- `app/Models/PurchaseOrderReceiptLine.php`

**Purpose:**  
Ensure every purchase order receipt line posts exactly one linked stock move and updates inventory in item base units.

**When to Use:**  
Purchase order receiving and receipt-ledger audit checks.

**When Not to Use:**  
Short-close events or non-purchasing inventory adjustments.

**Public Interface:**  
- `PurchaseOrderLifecycleService::createReceipt()`  
- `PurchaseOrderReceiptLine::stockMove()`  
- `Item::onHandQuantity()`

**Example Usage:**  
```php
$baseQuantity = bcmul('2.000000', '500.000000', 6);
// $baseQuantity === '1000.000000'
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

### Shared Navigation Eligibility State

**Name:** Shared Navigation Eligibility State  
**Type:** UI Architecture Invariant  
**Location:**  
- `docs/architecture/ui/SharedNavigationEligibilityState.yaml`  
- `app/Navigation/NavigationEligibility.php`  
- `app/Http/Controllers/NavigationStateController.php`  
- `resources/views/layouts/navigation.blade.php`  
- `resources/js/navigation/refresh-navigation-state.js`  

**Purpose:**  
Centralize tenant-scoped order-navigation eligibility in backend code while letting AJAX page modules refresh stale nav DOM after successful mutations.

**When to Use:**  
Rendering or refreshing Sales Orders, Purchase Orders, or Make Orders navigation state.

**When Not to Use:**  
Authorization decisions, route protection, or any client-owned navigation authority.

**Public Interface:**  
- `NavigationEligibility::forUser()`  
- `NavigationEligibility::forTenantId()`  
- `GET /navigation/state`  
- `navigation.state`  

**Example Usage:**  
```php
$eligibility = app(\App\Navigation\NavigationEligibility::class)->forUser(auth()->user());
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
- `purchasing-purchase-orders-create`
- `purchasing-purchase-orders-receive`
- `purchasing-purchase-orders-view` (defined but not used by current purchase-order routes)
- `purchasing-purchase-orders-update` (defined but not used by current purchase-order routes)
- `purchasing-purchase-orders-manage` (defined but not used by current purchase-order routes)
- `purchasing-receiving-view` (defined but not used by current purchase-order routes)
- `purchasing-receiving-execute` (defined but not used by current purchase-order routes)

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
- `purchasing-purchase-orders-receive`
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
- Customer detail read access uses `sales-customers-view`.
- Customer contacts reuse sales-customers-manage.
- Customer contacts do not introduce a separate permission slug.
- Sales orders use `sales-sales-orders-manage` for `/sales/orders` index/create/update/delete, sales-order line CRUD, and customer detail Orders mini-index CRUD.
- Customer detail Orders mini-index read access remains under `sales-customers-view`, but its mutations still require `sales-sales-orders-manage`.
- Sales-order line create, quantity update, and delete mutations do not introduce a separate permission slug.
- Navigation clickability for Sales Orders, Purchase Orders, and Make Orders is not permission-only:
  - permissions and `@can` checks still govern whether the user may see the relevant nav branch
  - backend navigation eligibility decides whether the order item renders as clickable or visible-but-disabled
  - eligibility is tenant-scoped and shared by Blade navigation and `GET /navigation/state`
- Current purchase-order routes use a two-gate model:
  - `purchasing-purchase-orders-create` for index/show/create/update/delete and line mutations
  - `purchasing-purchase-orders-receive` for receipts, short-closes, and manual status transitions
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

## Purchasing

### Purchase Order Status

**Name:** PurchaseOrder status  
**Storage location(s):** `purchase_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `OPEN`
- `PARTIALLY-RECEIVED`
- `RECEIVED`
- `BACK-ORDERED`
- `SHORT-CLOSED`
- `CANCELLED`

**Semantic meaning:**

- `DRAFT`: Purchase order is being assembled and may be edited.
- `OPEN`: Purchase order has been issued to the supplier.
- `PARTIALLY-RECEIVED`: Some items have been received, balances remain.
- `RECEIVED`: All ordered items have been received.
- `BACK-ORDERED`: Order contains backordered items.
- `SHORT-CLOSED`: Order closed with a short receipt.
- `CANCELLED`: Order has been cancelled.

**Notes:**

- Derived statuses are set by receiving/short-close events.

---

## Sales

### Sales Order Status

**Name:** SalesOrder status  
**Storage location(s):** `sales_orders.status` (string column)  
**Allowed values:**

- `DRAFT`
- `OPEN`
- `COMPLETED`
- `CANCELLED`

**Semantic meaning:**

- `DRAFT`: Sales order is editable. Header fields and sales-order lines may be created, updated, or deleted. No inventory impact exists yet.
- `OPEN`: Sales order remains editable. Header fields and sales-order lines may still be created, updated, or deleted. No inventory impact exists yet.
- `COMPLETED`: Terminal state. The order is no longer editable. Transitioning from `OPEN` to `COMPLETED` posts inventory issue stock moves.
- `CANCELLED`: Terminal state. The order is no longer editable. Cancelling never posts inventory issue stock moves.

**Notes:**

- Allowed transitions are:
    - `DRAFT -> OPEN`
    - `DRAFT -> CANCELLED`
    - `OPEN -> COMPLETED`
    - `OPEN -> CANCELLED`
- `COMPLETED` and `CANCELLED` are terminal.
- Sales-order headers and lines may only be mutated while the order is `DRAFT` or `OPEN`.
- Older roadmap-era statuses such as `CONFIRMED` and `FULFILLED` are not valid statuses.

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

### Stock Move Status

**Name:** StockMove status  
**Storage location(s):** `stock_moves.status` (string column)  
**Allowed values:**

- `DRAFT`
- `SUBMITTED`
- `POSTED`

**Semantic meaning:**

- `DRAFT`: Stock move is staged and not yet applied.
- `SUBMITTED`: Stock move is queued for posting.
- `POSTED`: Stock move is applied to the ledger.

**Notes:**

- Sales-order completion inventory impact posts `issue` stock moves with `status = POSTED`.
- Purchase-receipt and inventory-count posting flows also use `POSTED` when the move is ledger-valid.

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
- customer_contacts
- customers
- failed_jobs
- inventory_counts
- inventory_count_lines
- item_purchase_options
- item_purchase_option_prices
- item_uom_conversions
- items
- job_batches
- jobs
- make_orders
- password_reset_tokens
- permissions
- permission_role
- purchase_order_lines
- purchase_orders
- recipes
- recipe_lines
- roles
- roles_users
- sales_orders
- sales_order_lines
- sessions
- stock_moves
- suppliers
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

## customers

**Tenant-owned:** Yes  
**Purpose:** Sales customer records

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id         | bigint    | No       | Primary key               |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE) |
| name       | string    | No       | —                         |
| status     | string    | No       | Defaults to `active`      |
| notes      | text      | Yes      | —                         |
| address_line_1 | string | Yes      | —                         |
| address_line_2 | string | Yes      | —                         |
| city       | string    | Yes      | —                         |
| region     | string    | Yes      | —                         |
| postal_code | string   | Yes      | —                         |
| country_code | char(2) | Yes      | —                         |
| formatted_address | text | Yes    | —                         |
| latitude   | decimal(10,7) | Yes  | —                         |
| longitude  | decimal(10,7) | Yes  | —                         |
| address_provider | string | Yes   | Reserved for future mapping integration |
| address_provider_id | string | Yes | Reserved for future mapping integration |
| created_at | timestamp | Yes      | —                         |
| updated_at | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, name)`
- Index: `(tenant_id, status)`
- Implicit (FK index): `tenant_id`

---

## customer_contacts

**Tenant-owned:** Yes  
**Purpose:** Customer-contact relationship records for the customer detail Contacts section

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id         | bigint    | No       | Primary key               |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE) |
| customer_id | bigint   | No       | FK → customers.id (CASCADE) |
| first_name | string    | No       | —                         |
| last_name  | string    | No       | —                         |
| email      | string    | Yes      | —                         |
| phone      | string    | Yes      | —                         |
| role       | string    | Yes      | —                         |
| is_primary | boolean   | No       | Defaults to `false`       |
| created_at | timestamp | Yes      | —                         |
| updated_at | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `(customer_id, is_primary)`
- Index: `(tenant_id, customer_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `customer_id`

---

## sales_orders

**Tenant-owned:** Yes  
**Purpose:** Sales order headers shared by the Sales Orders index and the customer detail Orders mini-index

### Columns

| Name       | Type      | Nullable | Notes                                |
| ---------- | --------- | -------- | ------------------------------------ |
| id         | bigint    | No       | Primary key                          |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE)            |
| customer_id | bigint   | No       | FK → customers.id (CASCADE)          |
| contact_id | bigint    | Yes      | FK → customer_contacts.id (SET NULL) |
| status     | string    | No       | Defaults to `DRAFT`; allowed values are defined in `docs/ENUMS.md` |
| created_at | timestamp | Yes      | —                                    |
| updated_at | timestamp | Yes      | —                                    |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, status)`
- Index: `(tenant_id, customer_id)`
- Index: `(tenant_id, contact_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `customer_id`
- Implicit (FK index): `contact_id`

### Behavioral Notes

- Sales order headers remain editable only while `status` is `DRAFT` or `OPEN`.
- `COMPLETED` and `CANCELLED` are terminal.
- Transitioning from `OPEN` to `COMPLETED` may create one stock move per sales-order line.
- `DRAFT -> OPEN`, `DRAFT -> CANCELLED`, and `OPEN -> CANCELLED` create no stock moves.

---

## sales_order_lines

**Tenant-owned:** Yes  
**Purpose:** Sales order line items with immutable price snapshots for the Sales Orders index and customer detail Orders mini-index

### Columns

| Name       | Type          | Nullable | Notes                                  |
| ---------- | ------------- | -------- | -------------------------------------- |
| id         | bigint        | No       | Primary key                            |
| tenant_id  | bigint        | No       | FK → tenants.id (CASCADE)              |
| sales_order_id | bigint    | No       | FK → sales_orders.id (CASCADE)         |
| item_id    | bigint        | No       | FK → items.id (CASCADE)                |
| quantity   | decimal(18,6) | No       | Canonical BCMath quantity string       |
| unit_price_cents | unsignedInteger | No | Immutable unit price snapshot in minor currency units |
| unit_price_currency_code | char(3) | No | Immutable unit price snapshot currency |
| line_total_cents | decimal(18,6) | No | Line total in minor currency units     |
| created_at | timestamp     | Yes      | —                                      |
| updated_at | timestamp     | Yes      | —                                      |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, sales_order_id)`
- Index: `(sales_order_id, item_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `sales_order_id`
- Implicit (FK index): `item_id`

### Behavioral Notes

- Sales order line mutations are allowed only while the parent sales order is `DRAFT` or `OPEN`.
- On `OPEN -> COMPLETED`, each line may generate exactly one posted `stock_moves` ledger entry with `source_type = App\Models\SalesOrderLine` and `source_id = sales_order_lines.id`.

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

## item_purchase_option_prices

**Tenant-owned:** Yes  
**Purpose:** Purchase option price snapshots

### Columns

| Name                    | Type           | Nullable | Notes                                         |
| ----------------------- | -------------- | -------- | --------------------------------------------- |
| id                      | bigint         | No       | Primary key                                   |
| tenant_id               | bigint         | No       | FK → tenants.id (CASCADE)                     |
| item_purchase_option_id | bigint         | No       | FK → item_purchase_options.id (CASCADE)       |
| price_cents             | unsignedInt    | No       | —                                             |
| price_currency_code     | char(3)        | No       | —                                             |
| converted_price_cents   | unsignedInt    | No       | —                                             |
| fx_rate                 | decimal(18,8)  | No       | —                                             |
| fx_rate_as_of           | date           | No       | —                                             |
| effective_at            | timestamp      | No       | —                                             |
| ended_at                | timestamp      | Yes      | —                                             |
| created_at              | timestamp      | Yes      | —                                             |
| updated_at              | timestamp      | Yes      | —                                             |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, item_purchase_option_id)` (ipop_prices_tenant_option_idx)
- Index: `(item_purchase_option_id, ended_at)` (ipop_prices_option_ended_idx)
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_purchase_option_id`

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
| default_price_cents | integer | Yes      | Unsigned                  |
| default_price_currency_code | char(3) | Yes | —                        |
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
| output_quantity    | decimal(18,6) | No       | Stored runs; canonical scale          |
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

## purchase_order_lines

**Tenant-owned:** Yes  
**Purpose:** Purchase order line items with price snapshots

### Columns

| Name                       | Type           | Nullable | Notes                                        |
| -------------------------- | -------------- | -------- | -------------------------------------------- |
| id                         | bigint         | No       | Primary key                                  |
| tenant_id                  | bigint         | No       | FK → tenants.id (CASCADE)                    |
| purchase_order_id          | bigint         | No       | Part of composite FK                         |
| item_id                    | bigint         | No       | FK → items.id (CASCADE)                      |
| item_purchase_option_id    | bigint         | No       | FK → item_purchase_options.id (CASCADE)      |
| pack_count                 | integer        | No       | Unsigned, CHECK ≥ 1                          |
| unit_price_cents           | integer        | No       | Unsigned                                     |
| line_subtotal_cents        | integer        | No       | Unsigned, unit_price_cents * pack_count      |
| unit_price_amount          | integer        | No       | Unsigned, snapshot cents                     |
| unit_price_currency_code   | char(3)        | No       | Snapshot currency                            |
| converted_unit_price_amount | integer        | No       | Unsigned, snapshot converted cents           |
| fx_rate                    | decimal(18,8)  | No       | Snapshot FX rate                             |
| fx_rate_as_of              | date           | No       | Snapshot FX rate date                        |
| created_at                 | timestamp      | Yes      | —                                            |
| updated_at                 | timestamp      | Yes      | —                                            |

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

| Name                | Type        | Nullable | Notes                     |
| ------------------- | ----------- | -------- | ------------------------- |
| id                  | bigint      | No       | Primary key               |
| tenant_id           | bigint      | No       | FK → tenants.id (CASCADE) |
| created_by_user_id  | bigint      | Yes      | FK → users.id (SET NULL)  |
| supplier_id         | bigint      | Yes      | FK → suppliers.id (SET NULL) |
| order_date          | date        | Yes      | —                         |
| shipping_cents      | integer     | Yes      | Unsigned                  |
| tax_cents           | integer     | Yes      | Unsigned                  |
| po_subtotal_cents   | integer     | No       | Unsigned, default 0       |
| po_grand_total_cents | integer     | No       | Unsigned, default 0       |
| po_number           | string      | Yes      | —                         |
| notes               | text        | Yes      | —                         |
| status              | string      | No       | See ENUMS.md              |
| created_at          | timestamp   | Yes      | —                         |
| updated_at          | timestamp   | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(id, tenant_id)`
- Index: `tenant_id`
- Index: `(tenant_id, status)`
- Index: `(tenant_id, supplier_id)`
- Implicit (FK index): `created_by_user_id`
- Implicit (FK index): `supplier_id`

---

## purchase_order_receipts

**Tenant-owned:** Yes  
**Purpose:** Receipt event headers for purchase orders

### Columns

| Name                | Type      | Nullable | Notes                                   |
| ------------------- | --------- | -------- | --------------------------------------- |
| id                  | bigint    | No       | Primary key                             |
| tenant_id           | bigint    | No       | FK → tenants.id (CASCADE)               |
| purchase_order_id   | bigint    | No       | Part of composite FK                    |
| received_at         | datetime  | No       | —                                       |
| received_by_user_id | bigint    | No       | FK → users.id                           |
| reference           | string    | Yes      | —                                       |
| notes               | text      | Yes      | —                                       |
| created_at          | timestamp | Yes      | —                                       |
| updated_at          | timestamp | Yes      | —                                       |

### Foreign Keys

- `(purchase_order_id, tenant_id)` → purchase_orders.(id, tenant_id) (CASCADE)
- `received_by_user_id` → users.id

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`

---

## purchase_order_receipt_lines

**Tenant-owned:** Yes  
**Purpose:** Receipt event line items for purchase orders

### Columns

| Name                     | Type           | Nullable | Notes                                   |
| ------------------------ | -------------- | -------- | --------------------------------------- |
| id                       | bigint         | No       | Primary key                             |
| tenant_id                | bigint         | No       | FK → tenants.id (CASCADE)               |
| purchase_order_receipt_id | bigint        | No       | FK → purchase_order_receipts.id (CASCADE) |
| purchase_order_line_id   | bigint         | No       | FK → purchase_order_lines.id (CASCADE)  |
| stock_move_id            | bigint         | Yes      | FK → stock_moves.id (SET NULL)          |
| received_quantity        | decimal(18,6)  | No       | Pack count                              |
| created_at               | timestamp      | Yes      | —                                       |
| updated_at               | timestamp      | Yes      | —                                       |

### Foreign Keys

- `purchase_order_receipt_id` → purchase_order_receipts.id (CASCADE)
- `purchase_order_line_id` → purchase_order_lines.id (CASCADE)
- `stock_move_id` → stock_moves.id (SET NULL)

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `purchase_order_receipt_id`
- Index: `purchase_order_line_id`
- Unique: `stock_move_id`
- Implicit (FK index): `purchase_order_receipt_id`
- Implicit (FK index): `purchase_order_line_id`
- Implicit (FK index): `stock_move_id`

---

## purchase_order_short_closures

**Tenant-owned:** Yes  
**Purpose:** Short-close event headers for purchase orders

### Columns

| Name                   | Type      | Nullable | Notes                                   |
| ---------------------- | --------- | -------- | --------------------------------------- |
| id                     | bigint    | No       | Primary key                             |
| tenant_id              | bigint    | No       | FK → tenants.id (CASCADE)               |
| purchase_order_id      | bigint    | No       | Part of composite FK                    |
| short_closed_at        | datetime  | No       | —                                       |
| short_closed_by_user_id | bigint   | No       | FK → users.id                           |
| reference              | string    | Yes      | —                                       |
| notes                  | text      | Yes      | —                                       |
| created_at             | timestamp | Yes      | —                                       |
| updated_at             | timestamp | Yes      | —                                       |

### Foreign Keys

- `(purchase_order_id, tenant_id)` → purchase_orders.(id, tenant_id) (CASCADE)
- `short_closed_by_user_id` → users.id

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`

---

## purchase_order_short_closure_lines

**Tenant-owned:** Yes  
**Purpose:** Short-close event line items for purchase orders

### Columns

| Name                            | Type           | Nullable | Notes                                   |
| ------------------------------- | -------------- | -------- | --------------------------------------- |
| id                              | bigint         | No       | Primary key                             |
| tenant_id                       | bigint         | No       | FK → tenants.id (CASCADE)               |
| purchase_order_short_closure_id | bigint         | No       | FK → purchase_order_short_closures.id (CASCADE) |
| purchase_order_line_id          | bigint         | No       | FK → purchase_order_lines.id (CASCADE)  |
| short_closed_quantity           | decimal(18,6)  | No       | Pack count                              |
| created_at                      | timestamp      | Yes      | —                                       |
| updated_at                      | timestamp      | Yes      | —                                       |

### Keys & Indexes

- PK: `id`
- Index: `tenant_id`
- Index: `purchase_order_short_closure_id`
- Index: `purchase_order_line_id`
- Implicit (FK index): `purchase_order_short_closure_id`
- Implicit (FK index): `purchase_order_line_id`

---

## recipes

**Tenant-owned:** Yes  
**Purpose:** Manufacturing recipes

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id              | bigint        | No       | Primary key               |
| tenant_id       | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id         | bigint        | No       | FK → items.id (CASCADE)   |
| name            | string        | No       | User-defined recipe name  |
| output_quantity | decimal(18,6) | No       | Canonical scale           |
| is_active       | boolean       | No       | Default true              |
| is_default      | boolean       | No       | Default false             |
| created_at      | timestamp     | Yes      | —                         |
| updated_at      | timestamp     | Yes      | —                         |

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
| status      | string        | No       | See ENUMS.md                  |
| source_type | string        | Yes      | Polymorphic source discriminator |
| source_id   | bigint        | Yes      | Polymorphic source primary key |
| created_at  | timestamp     | No       | Defaults to CURRENT_TIMESTAMP |

### Keys & Indexes

- PK: `id`
- Index: `(source_type, source_id)`
- Implicit (FK index): tenant_id, item_id, uom_id

### Behavioral Notes

- Purchase receipt stock moves may use `source_type = purchase_order_receipt_line`.
- Sales-order completion stock moves use `source_type = App\Models\SalesOrderLine` and `source_id = sales_order_lines.id`.
- Sales-order completion creates `issue` stock moves in the item base UoM with the line quantity as a signed negative ledger amount.
- Negative inventory is allowed in V1. Sales-order completion does not perform availability blocking.

---

## suppliers

**Tenant-owned:** Yes  
**Purpose:** Supplier registry

### Columns

| Name          | Type      | Nullable | Notes                     |
| ------------- | --------- | -------- | ------------------------- |
| id            | bigint    | No       | Primary key               |
| tenant_id     | bigint    | No       | FK → tenants.id (CASCADE) |
| company_name  | string    | No       | —                         |
| url           | string    | Yes      | —                         |
| phone         | string    | Yes      | —                         |
| email         | string    | Yes      | —                         |
| currency_code | string    | Yes      | —                         |
| created_at    | timestamp | Yes      | —                         |
| updated_at    | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, company_name)`
- Implicit (FK index): tenant_id

---

## tenants

**Tenant-owned:** No  
**Purpose:** Tenant registry

### Columns

| Name        | Type      | Nullable | Notes       |
| ----------- | --------- | -------- | ----------- |
| id          | bigint    | No       | Primary key |
| tenant_name | string    | Yes      | —           |
| currency_code | string  | Yes      | Default config('app.currency_code', 'USD') |
| created_at  | timestamp | Yes      | —           |
| updated_at  | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`

---

## uom_categories

**Tenant-owned:** Yes (system defaults use `tenant_id = NULL`)  
**Purpose:** Unit-of-measure categories

### Columns

| Name       | Type      | Nullable | Notes                      |
| ---------- | --------- | -------- | -------------------------- |
| id         | bigint    | No       | Primary key                |
| tenant_id  | bigint    | Yes      | FK → tenants.id (CASCADE)  |
| name       | string    | No       | Unique per tenant          |
| created_at | timestamp | Yes      | —                          |
| updated_at | timestamp | Yes      | —                          |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, name)`
- Implicit (FK index): `tenant_id`

---

## uom_conversions

**Tenant-owned:** Mixed (`tenant_id = null` for global/system rows)  
**Purpose:** Global and tenant-managed UoM conversions

### Columns

| Name        | Type          | Nullable | Notes                                               |
| ----------- | ------------- | -------- | --------------------------------------------------- |
| id          | bigint        | No       | Primary key                                         |
| tenant_id   | bigint        | Yes      | FK → tenants.id (CASCADE); `null` for global rows   |
| from_uom_id | bigint        | No       | FK → uoms.id (CASCADE)                              |
| to_uom_id   | bigint        | No       | FK → uoms.id (CASCADE)                              |
| multiplier  | decimal(18,8) | No       | Stored precision for general conversion multiplier   |
| created_at  | timestamp     | Yes      | —                                                   |
| updated_at  | timestamp     | Yes      | —                                                   |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, from_uom_id, to_uom_id)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `from_uom_id`
- Implicit (FK index): `to_uom_id`

---

## uoms

**Tenant-owned:** Yes (system defaults use `tenant_id = NULL`)  
**Purpose:** Units of measure

### Columns

| Name            | Type      | Nullable | Notes                            |
| --------------- | --------- | -------- | -------------------------------- |
| id              | bigint    | No       | Primary key                      |
| tenant_id       | bigint    | Yes      | FK → tenants.id (CASCADE)        |
| uom_category_id | bigint    | No       | FK → uom_categories.id (CASCADE) |
| name            | string    | No       | Not unique                       |
| symbol          | string    | No       | Unique per tenant                |
| display_precision | unsignedTinyInteger | No | Default `1`; UI display precision (0..6) |
| created_at      | timestamp | Yes      | —                                |
| updated_at      | timestamp | Yes      | —                                |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, symbol)`
- Implicit (FK index): `tenant_id`
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
- Domain menu items are **process-based top-level entries**
- Current implementation groups functionality under top-level dropdowns such as:
    - Purchasing
    - Manufacturing

- No nested mega-menus initially
- Active state must be subtle (underline or tone shift)

### Navigation Implementation

- The native `<nav>` element must remain in `resources/views/layouts/navigation.blade.php`
- Navigation parts must use:
    - `<x-nav-link>`
    - `<x-nav-dropdown>`
    - `<x-nav-dropdown-link>`
- Navigation should use atomic Blade components, not a monolithic `<x-nav>` component
- Breeze navigation component usage is not allowed in the navigation layout going forward
- Shared Breeze components must not be deleted unless confirmed unused outside navigation
- Desktop dropdowns use floating panels
- Mobile nested navigation uses accordion-style groups
- Mobile must not reuse desktop flyout behavior
- Dark charcoal/navy top navigation is approved for this application
- Styling must remain calm, operational, and restrained
- Tailwind utilities only
- Preserve subtle active states, rounded nav items, subtle borders, and soft shadows
- Do not introduce flashy, marketing-style, or dashboard-heavy navigation
- Navigation refactors must preserve route names, gates, `@can` checks, active states, and existing behavior
- When navigation availability is gated by server-rendered domain prerequisites, AJAX page modules may patch stale local nav DOM after a successful mutation, but must not introduce global JS state or client-side authority
- Shared navigation eligibility is backend-owned and must be rendered from the same tenant-scoped contract in Blade and JSON refresh endpoints
- Disabled navigation items remain visible but inactive
- If domain prerequisites become true after AJAX create, update, or delete flows, the affected navigation item may become enabled without a full browser refresh
- If domain prerequisites later become false, the affected navigation item must return to a visible but inactive state without a full browser refresh
- Navigation refresh behavior must not use websockets or global JavaScript state

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
- Page modules remain the target pattern for new interactive pages.
- During the current staged migration, Alpine directives in Blade are allowed, but executable `<script>` tags remain forbidden.

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

---

## Page Notes

### Customer Detail Contacts Section

- The customer detail page may expose a Contacts section to read-only customer viewers.
- Each contact row and form uses `first_name`, `last_name`, and optional email, phone, and role fields.
- Contact create, edit, delete, and set-primary controls must render only for users who can manage customers.
- When contacts exist, the UI must always present exactly one primary contact for that customer.
- Contacts are managed in-page with AJAX mutations and JSON validation feedback.

### `/manufacturing/uom-conversions`

This page follows the page-module + AJAX CRUD pattern and is split into two sections:

- **General Conversions**
    - Shows global and tenant conversions together
    - Global rows are visible but read-only
    - Tenant rows are editable and deletable
    - Create/edit uses a right-docked slide-over
- **Item-Specific Conversions**
    - Shows item-scoped overrides
    - Create/edit/delete is available from the same page
    - Item-specific conversions may cross categories because they are item-bound

Behavioral rules reflected in the UI:

- Global conversions are system-seeded and immutable in the UI
- Tenant conversions are user-managed and stay within a single UoM category
- Item-specific conversions override tenant and global conversions for the selected item
- All create/edit/delete actions are AJAX-only and do not redirect
- Validation errors render inline; success feedback uses page-scoped toast behavior

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
- Suppliers

---

### Manufacturing Domain

Focus: production execution and operational primitives.

**Dropdown items:**

- Orders (Make Orders)
- Inventory
- Inventory Counts
- Materials
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
- **No executable `<script>` blocks** in Blade templates

All UI logic **must** live in:
resources/js/pages/\*\*

For pages not yet migrated to the page-module pattern, Alpine directives may remain in Blade while fetch/state orchestration is progressively moved into page modules.

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

For page-module and page-module-compatible interactive screens, 422 responses must be normalized into this shape.

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

### Migration Status

- The current implementation is in a staged migration state.
- Breeze layouts/components and Alpine directives are still present in active Blade views.
- New or migrated interactive pages should follow the page-module contract.
- Existing Blade/Alpine interactions may remain until they are intentionally migrated.

Framework-agnostic

They are mandatory, not stylistic.

::contentReference[oaicite:0]{index=0}

## routes/web.php
<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemPurchaseOptionPriceController;
use App\Http\Controllers\MakeOrderController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NavigationStateController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderLineController;
use App\Http\Controllers\PurchaseOrderReceiptController;
use App\Http\Controllers\PurchaseOrderShortClosureController;
use App\Http\Controllers\PurchaseOrderStatusController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesOrderLineController;
use App\Http\Controllers\SalesOrderStatusController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPurchaseOptionController;
use App\Http\Controllers\UomCategoryController;
use App\Http\Controllers\UomConversionController;
use App\Http\Controllers\UomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/navigation/state', NavigationStateController::class)
        ->name('navigation.state');

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
    Route::get('/manufacturing/uom-conversions', [UomConversionController::class, 'index'])
        ->name('manufacturing.uom-conversions.index');
    Route::post('/manufacturing/uom-conversions', [UomConversionController::class, 'store'])
        ->name('manufacturing.uom-conversions.store');
    Route::patch('/manufacturing/uom-conversions/{conversion}', [UomConversionController::class, 'update'])
        ->name('manufacturing.uom-conversions.update');
    Route::delete('/manufacturing/uom-conversions/{conversion}', [UomConversionController::class, 'destroy'])
        ->name('manufacturing.uom-conversions.destroy');
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

    Route::get('/purchasing/suppliers', [SupplierController::class, 'index'])
        ->name('purchasing.suppliers.index');
    Route::post('/purchasing/suppliers', [SupplierController::class, 'store'])
        ->name('purchasing.suppliers.store');
    Route::get('/purchasing/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->name('purchasing.suppliers.show');
    Route::patch('/purchasing/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('purchasing.suppliers.update');
    Route::delete('/purchasing/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->name('purchasing.suppliers.destroy');
    Route::post('/purchasing/suppliers/{supplier}/purchase-options', [SupplierPurchaseOptionController::class, 'store'])
        ->name('purchasing.suppliers.purchase-options.store');
    Route::delete('/purchasing/suppliers/{supplier}/purchase-options/{option}', [SupplierPurchaseOptionController::class, 'destroy'])
        ->name('purchasing.suppliers.purchase-options.destroy');
    Route::post('/purchasing/purchase-options/{option}/prices', [ItemPurchaseOptionPriceController::class, 'store'])
        ->name('purchasing.purchase-options.prices.store');

    Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])
        ->name('purchasing.orders.index');
    Route::post('/purchasing/orders', [PurchaseOrderController::class, 'store'])
        ->name('purchasing.orders.store');
    Route::get('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->name('purchasing.orders.show');
    Route::patch('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->name('purchasing.orders.update');
    Route::put('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
    Route::delete('/purchasing/orders/{purchaseOrderId}', [PurchaseOrderController::class, 'destroy'])
        ->name('purchasing.orders.destroy');
    Route::patch('/purchasing/orders/{purchaseOrder}/status', [PurchaseOrderStatusController::class, 'update'])
        ->name('purchasing.orders.status.update');
    Route::post('/purchasing/orders/{purchaseOrder}/receipts', [PurchaseOrderReceiptController::class, 'store'])
        ->name('purchasing.orders.receipts.store');
    Route::post('/purchasing/orders/{purchaseOrder}/short-closures', [PurchaseOrderShortClosureController::class, 'store'])
        ->name('purchasing.orders.short-closures.store');

    Route::post('/purchasing/orders/{purchaseOrderId}/lines', [PurchaseOrderLineController::class, 'store'])
        ->name('purchasing.orders.lines.store');
    Route::patch('/purchasing/orders/{purchaseOrder}/lines/{line}', [PurchaseOrderLineController::class, 'update'])
        ->name('purchasing.orders.lines.update');
    Route::delete('/purchasing/orders/{purchaseOrderId}/lines/{lineId}', [PurchaseOrderLineController::class, 'destroy'])
        ->name('purchasing.orders.lines.destroy');

    Route::get('/sales/customers', [CustomerController::class, 'index'])
        ->name('sales.customers.index');
    Route::get('/sales/customers/{customer}', [CustomerController::class, 'show'])
        ->name('sales.customers.show');
    Route::post('/sales/customers', [CustomerController::class, 'store'])
        ->name('sales.customers.store');
    Route::patch('/sales/customers/{customer}', [CustomerController::class, 'update'])
        ->name('sales.customers.update');
    Route::delete('/sales/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('sales.customers.destroy');
    Route::post('/sales/customers/{customer}/contacts', [CustomerContactController::class, 'store'])
        ->name('sales.customers.contacts.store');
    Route::patch('/sales/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])
        ->name('sales.customers.contacts.update');
    Route::delete('/sales/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])
        ->name('sales.customers.contacts.destroy');
    Route::patch('/sales/customers/{customer}/contacts/{contact}/primary', [CustomerContactController::class, 'setPrimary'])
        ->name('sales.customers.contacts.primary.update');

    Route::get('/sales/orders', [SalesOrderController::class, 'index'])
        ->name('sales.orders.index');
    Route::post('/sales/orders', [SalesOrderController::class, 'store'])
        ->name('sales.orders.store');
    Route::patch('/sales/orders/{salesOrder}', [SalesOrderController::class, 'update'])
        ->name('sales.orders.update');
    Route::patch('/sales/orders/{salesOrder}/status', [SalesOrderStatusController::class, 'update'])
        ->name('sales.orders.status.update');
    Route::delete('/sales/orders/{salesOrder}', [SalesOrderController::class, 'destroy'])
        ->name('sales.orders.destroy');
    Route::post('/sales/orders/{salesOrder}/lines', [SalesOrderLineController::class, 'store'])
        ->name('sales.orders.lines.store');
    Route::patch('/sales/orders/{salesOrder}/lines/{line}', [SalesOrderLineController::class, 'update'])
        ->name('sales.orders.lines.update');
    Route::delete('/sales/orders/{salesOrder}/lines/{line}', [SalesOrderLineController::class, 'destroy'])
        ->name('sales.orders.lines.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/manufacturing/uom-conversions/resolve', [UomConversionController::class, 'resolve'])
    ->name('manufacturing.uom-conversions.resolve');
Route::post('/manufacturing/uom-conversions/items', [UomConversionController::class, 'storeItem'])
    ->name('manufacturing.uom-conversions.items.store');
Route::patch('/manufacturing/uom-conversions/items/{itemConversion}', [UomConversionController::class, 'updateItem'])
    ->name('manufacturing.uom-conversions.items.update');
Route::delete('/manufacturing/uom-conversions/items/{itemConversion}', [UomConversionController::class, 'destroyItem'])
    ->name('manufacturing.uom-conversions.items.destroy');

require __DIR__ . '/auth.php';

## docs/PR3_ROADMAP.md
# PR3_ROADMAP — Sales + CRM Foundations

This roadmap defines the third major phase of work: introducing the **Sales domain (CRM foundations + Sales Orders)**, fully integrated with inventory before any external integrations.

---

## Core Principles

- Domain-segmented PRs
- UI + backend together per PR
- AJAX-first CRUD
- Smallest possible change per PR
- No speculative integrations
- Inventory must be source of truth before external sync

---

## Navigation Model

Top-level navigation:

- **Sales**

Dropdown includes:

- Customers
- Sales Orders

---

## DOMAIN 1 — Customers

### PR3-CUST-001 — Customers CRUD

Status: Implemented

**Goal**
Introduce customers as tenant-owned entities.

**Includes**

- Route: `/sales/customers`
- Full CRUD (AJAX)
- Fields:
    - name (required)
    - status (active/inactive/archived)
    - notes (nullable)
    - address_line_1 (nullable)
    - address_line_2 (nullable)
    - city (nullable)
    - region (nullable)
    - postal_code (nullable)
    - country_code (nullable, 2 chars)
    - formatted_address (nullable)
    - latitude (nullable, system/provider-managed)
    - longitude (nullable, system/provider-managed)
    - address_provider (nullable, system/provider-managed)
    - address_provider_id (nullable, system/provider-managed)
- Navigation: Sales → Customers
- Create/edit uses the slide-over pattern
- Create defaults `status` to `active`
- Create has no status dropdown
- Index shows active customers only
- Index columns: Name, Address, Actions
- Notes are shown on the customer detail view, not the index
- Destroy archives instead of hard-deleting

**Permissions**

- `sales-customers-manage`

---

### PR3-CUST-002 — Contacts (1:N)

Status: Implemented

**Goal**
Allow multiple contacts per customer.

**Includes**

- Nested under customer detail
- Customer detail page includes a Contacts section
- CRUD (AJAX)
- Fields:
    - first_name
    - last_name
    - email (nullable)
    - phone (nullable)
    - role (nullable)
    - is_primary
    - customer_id
    - tenant_id

**Rules**

- 1 customer → many contacts
- A customer may have multiple contacts
- Exactly one contact must be primary when contacts exist
- The first contact created for a customer becomes primary automatically
- Additional contacts are not primary by default
- Setting a new primary contact unsets the previous primary for the same customer
- Primary designation is scoped per customer, not tenant-wide
- A primary contact cannot be deleted while other contacts exist
- The only contact for a customer may be deleted, leaving zero contacts
- Contacts section mutations return JSON and do not redirect

**Permissions**

- Customer detail read access uses `sales-customers-view`
- Customer contacts reuse `sales-customers-manage`

---

### PR3-CUST-003 — Customer Detail View

**Goal**
Provide a stable read model.

**Includes**

- Route: `/sales/customers/{customer}`
- Sections:
    - Core fields
    - Contacts section

---

## DOMAIN 2 — Sales Orders

### PR3-SO-001 — Sales Orders (Draft)

Status: Implemented

**Goal**
Introduce draft sales orders.

**Includes**

- Route: `/sales/orders`
- Sales Orders index view
- Customer detail Orders mini-index
- Shared AJAX CRUD backend for both UI surfaces
- Create/edit/delete draft orders (AJAX, no browser refresh)
- Fields:
    - customer_id (required)
    - contact_id (nullable)
    - status = DRAFT
- Create/edit forms allow changing customer and contact
- Contacts are scoped to the selected customer
- On create, missing `contact_id` defaults to the selected customer’s primary contact when one exists
- On edit, changing `customer_id` resets `contact_id` to the new customer’s primary contact unless a valid contact for the new customer is explicitly submitted
- A contact from the previous customer is never preserved after customer change
- Sales → Orders remains visible but disabled unless the tenant has at least one customer and at least one sellable item
- Statuses later expanded by PR3-SO-004 / PR3-SO-005 still preserve the same shared AJAX CRUD surface

**Permissions**

- `sales-sales-orders-manage`

---

### PR3-SO-002 — Sales Order Lines

Status: Implemented

**Goal**
Allow adding items to existing draft sales orders.

**Includes**

- Add/remove lines and edit line quantity (AJAX, no browser refresh)
- Shared JSON backend for both UI surfaces:
    - Sales Orders index
    - Customer detail Orders mini-index
- Fields:
    - item_id
    - quantity (BCMath string, canonical scale 6)
    - unit_price_cents snapshot
    - unit_price_currency_code snapshot
    - line_total_cents
- Sales → Orders remains visible but disabled unless the tenant has at least one customer and at least one sellable item
- Customer detail create-order button remains visible but disabled when no sellable items exist

**Rules**

- Sales order lines may be added, removed, or quantity-edited only while the parent sales order is editable in the current lifecycle slice
- Only items with `is_sellable = true` may be added
- Quantity uses BCMath-compatible string math with canonical scale 6
- Unit price snapshot is captured when the line is created
- Quantity edits recalculate `line_total_cents` from the stored immutable unit price snapshot
- Existing line unit price snapshot data is never changed by later item price changes
- Mutations return JSON for AJAX consumers and do not redirect
- Tenant isolation applies to sales orders, sales order lines, and items
- No lifecycle, fulfillment, invoicing, payments, shipping, or inventory impact is introduced in this PR

---

### PR3-SO-003 — Pricing Snapshot Invariant

Status: Subsumed by PR3-SO-002

**Goal**
Ensure immutable pricing at line creation.

**Includes**

- Store:
    - unit_price_cents
    - unit_price_currency_code
- Values never change after write

---

### PR3-SO-004 — Sales Order Lifecycle

Status: Implemented

**Goal**
Introduce lifecycle without inventory impact yet.

**Statuses**

- DRAFT
- OPEN
- COMPLETED
- CANCELLED

**Rules**

- Allowed transitions:
    - `DRAFT -> OPEN`
    - `DRAFT -> CANCELLED`
    - `OPEN -> COMPLETED`
    - `OPEN -> CANCELLED`
- `COMPLETED` and `CANCELLED` are terminal
- Header and line editing are allowed only while the order is `DRAFT` or `OPEN`
- Header and line editing are blocked while the order is `COMPLETED` or `CANCELLED`
- Lifecycle status changes return JSON and do not redirect
- `DRAFT -> OPEN` and any cancellation transition create no stock moves
- Older roadmap-era statuses `CONFIRMED` and `FULFILLED` are not valid

---

### PR3-SO-005 — Inventory Impact (Critical)

Status: Implemented

**Goal**
Sales orders must update inventory.

**Includes**

- On `OPEN -> COMPLETED`:
    - Create one `stock_moves` row per sales-order line
    - Use `type = issue`
    - Use `status = POSTED`
    - Use the item base UoM
    - Link the row with `source_type = App\Models\SalesOrderLine` and `source_id = sales_order_lines.id`
    - Reduce on-hand inventory through the ledger

**Rules**

- Inventory is source of truth
- BCMath required
- Transactional integrity
- Completion does not check availability in V1
- Negative inventory is allowed in V1
- `CANCELLED` creates no stock moves
- Retrying completion must not create duplicate stock moves
- If stock move creation fails, the order must remain `OPEN` and no partial stock moves may persist

---

## DOMAIN 3 — External Integration (Post-Inventory Only)

### PR3-INT-001 — External Hooks (Prep)

**Goal**
Prepare for Shopify/WooCommerce.

**Includes**

- Fields:
    - external_source
    - external_id

**Out of Scope**

- Sync logic
- Webhooks

---

## End State

After PR3 completion:

- Sales domain fully operational
- CRM foundation established
- Sales orders impact inventory correctly
- System ready for external integrations
