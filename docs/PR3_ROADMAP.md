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
Introduce the initial lifecycle slice without inventory impact yet.

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
- This historical slice was later extended by PR3-SO-006 and PR3-SO-007; current operational stages are now database-backed rather than permanently hardcoded in this initial lifecycle shape

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
- This historical slice was later replaced by the packed-stage inventory posting model introduced by PR3-SO-006 and retained under PR3-SO-007

---

### PR3-SO-006 — Sales Order Lifecycle: Packing / Packed / Shipping

Status: Implemented

**Goal**
Extend the Sales Order lifecycle with operational statuses.

**Statuses**

- DRAFT
- OPEN
- PACKING
- PACKED
- SHIPPING
- COMPLETED
- CANCELLED

**Rules**

- `OPEN -> PACKING` starts the packing workflow
- `PACKING -> PACKED` means packing is complete
- `PACKED` is the point where fulfillment recipe components are consumed
- `PACKED -> SHIPPING` means carrier pickup or shipment handoff has occurred
- `SHIPPING -> COMPLETED` means shipment is confirmed complete
- Fulfillment recipes determine consumed components
- Make Orders remain manufacturing-only
- Do not add custom stages in this PR
- Do not add full task management in this PR unless explicitly scoped later
- PR3-SO-007 later keeps `packing`, `packed`, and `shipping` as seeded defaults but moves operational stage ordering into tenant-scoped workflow stages backed by the database

---

### PR3-SO-007 — Domain-General Workflow Stages + Tasks

Status: Implemented

**Goal**
Introduce domain-general workflow infrastructure for operational stages and generated tasks, with Sales Orders as the first consumer.

**Implemented Scope**

- This PR implements workflow infrastructure, not just a Sales Order checklist UI
- The design supports:
    - sales
    - purchasing
    - manufacturing
- Sales Orders are the first runtime domain integration
- Purchase Order and Make Order task generation remain future scope

**Implemented Data Model**

- Fixed system-owned `workflow_domains` table
- Seeded workflow domain keys:
    - `sales`
    - `purchasing`
    - `manufacturing`
- Domains are system-owned and not admin-configurable
- Domains scope workflow stages and workflow task templates
- Workflow domains are seeded deterministically with stable `sort_order`

- Tenant-scoped `workflow_stages` table
- Workflow stages belong to:
    - tenant
    - workflow domain
- Stages are operational middle stages only
- Sales Order system statuses `DRAFT`, `OPEN`, `COMPLETED`, and `CANCELLED` remain non-configurable and are not admin-managed workflow stages
- Seeded default sales operational stage keys are:
    - `packing`
    - `packed`
    - `shipping`
- These seeded sales stages are defaults only, not a permanently hardcoded lifecycle
- Future Sales Order operational-stage order is resolved from the tenant's active `sales` workflow stages ordered by `sort_order`
- `DRAFT -> OPEN` remains system-owned
- `OPEN` advances to the first active sales workflow stage
- Active sales workflow stages advance in database order
- The final active sales workflow stage advances to `COMPLETED`
- `CANCELLED` remains a terminal branch under the existing cancellation rules

- Tenant-scoped `workflow_task_templates` table
- Task templates belong to:
    - tenant
    - workflow domain
    - workflow stage
- Task template fields include:
    - `title`
    - `description`
    - `sort_order`
    - `is_active`
    - `default_assignee_user_id`

- Tenant-scoped domain-general `tasks` table
- Generated task fields include:
    - `tenant_id`
    - `workflow_domain_id`
    - `domain_record_id`
    - `workflow_stage_id`
    - `workflow_task_template_id`
    - `assigned_to_user_id`
    - `title`
    - `description`
    - `sort_order`
    - `status`
    - `completed_at`
    - `completed_by_user_id`

**Implemented Stage Rules**

- Workflow stages are database-backed, not hardcoded
- Stages have `is_active`
- Admins can create, edit, deactivate, reactivate, and reorder operational stages within a tenant/domain
- Duplicate stage keys must be prevented per tenant/domain
- The same stage key may exist in different workflow domains
- `DRAFT`, `OPEN`, `COMPLETED`, and `CANCELLED` remain system statuses and cannot be reordered through workflow admin UI
- Inactive stages are hidden by default
- The admin UI includes a show-inactive toggle
- Default sales stages are seeded idempotently per tenant and do not duplicate existing `packing`, `packed`, or `shipping` rows
- Runtime Sales Order stage resolution must use active sales workflow stages from the database rather than a hardcoded `packing -> packed -> shipping` fallback

**Implemented Task Template Rules**

- Only active task templates generate future tasks
- Inactive task templates are hidden by default
- The admin UI includes a show-inactive toggle
- Editing a task template affects future generated tasks only
- Already-generated tasks remain unchanged
- Task templates can be reordered within a stage
- Tasks are always assigned to a user
- If no assignee is configured, generated tasks default to the first user for that tenant

**Implemented Generated Task Rules**

- Task status remains simple in this PR:
    - `open`
    - `completed`
- Task completion is final in this PR
- Completed tasks cannot be reopened or uncompleted in this PR
- Ad hoc task creation remains future scope
- Generated tasks snapshot template fields at creation time:
    - `title`
    - `description`
    - `sort_order`
    - `assigned_to_user_id`
- Current-stage generated tasks are stable after creation
- Entering a future stage uses the latest active admin configuration at that time
- Completed tasks remain visible and clearly marked completed
- Open tasks are removed when an order is cancelled
- Completed task history remains visible unless a future rule changes that
- Moving an order backward does not reopen or duplicate prior open tasks
- Re-entering a stage or repeated transition calls must not duplicate generated tasks

**Implemented Sales Order Integration**

- Sales Orders are the first runtime consumer of the workflow task system
- When a Sales Order enters an operational workflow stage, the system generates tasks from active task templates matching:
    - tenant
    - sales workflow domain
    - entered workflow stage
- If no active task templates exist for that stage, no tasks are created
- The system creates at most one generated task per template per Sales Order/stage entry
- Task generation happens only after the underlying stage transition succeeds
- `OPEN -> first active sales workflow stage` runs existing fulfillment availability checks before generating tasks
- Inventory consumption still occurs at `packing -> packed` when those seeded default stages exist in the tenant workflow
- `packed -> shipping` task generation occurs only after the successful stage transition
- Forward Sales Order lifecycle transitions remain gated by:
    1. existing Sales Order domain rules such as packing and inventory rules
    2. completion of current-stage generated tasks
- If no tasks exist for a stage, workflow task gating does not block the transition
- Sales Order transitions continue using existing Sales Order manage permissions

**Implemented Admin UI**

- Navigation item: `Admin -> Workflows`
- Access is controlled by permission slug `workflow-manage`
- Admins receive `workflow-manage` by default
- The Workflows page uses Tailwind + Alpine components
- No global JavaScript state is permitted
- The page includes two accordion sections:
    - `Stages`
    - `Tasks`

Stages accordion:

- CRUD-style index
- Create/edit stages
- Deactivate/reactivate stages
- Reorder active operational stages
- Show inactive toggle
- Domain selector limited to seeded workflow domains

Tasks accordion:

- CRUD-style index
- Create/edit task templates
- Deactivate/reactivate task templates
- Reorder task templates within a stage
- Show inactive toggle
- Domain selector
- Stage selector filtered by selected domain
- Stage options should reflect newly created stages from the Stages accordion
- Default assignee selector
- Title and description fields

**Implemented Authorization Rules**

- `workflow-manage` controls access to `Admin -> Workflows` and workflow configuration CRUD
- Admins receive `workflow-manage` by default
- Assigned users may complete their assigned tasks without `workflow-manage`
- Assigned users do not need Sales Order manage permission solely to complete an assigned task
- Sales Order lifecycle transitions still require existing Sales Order manage permission
- Tenant scoping applies to workflow stages, workflow task templates, generated tasks, and any tenant-owned workflow records

**Out of Scope**

- Ad hoc task creation
- Task comments
- Task attachments
- Task due dates
- Task priorities
- Reopening completed tasks
- Multiple assignees per task
- Product-specific workflows
- Task notification system
- Purchase Order task generation
- Make Order task generation
- Custom branching workflows
- Conditional tasks
- Parallel task groups
- Full visual workflow builder
- Customer-facing task views
- Vendor-facing task views

**Implemented Test Coverage Expectations**

- Workflow domain seeding
- Workflow stage CRUD
- Workflow stage active/inactive behavior
- Workflow stage ordering
- New active sales stages change future Sales Order transition order
- Deactivating a sales stage removes it from future Sales Order transition order
- Reordering active sales stages changes future Sales Order transition order
- Seeded `packing`, `packed`, and `shipping` stages are defaults only and do not override database-backed runtime ordering
- Workflow task template CRUD
- Workflow task template active/inactive behavior
- Task generation from active templates
- No task generation from inactive templates
- No duplicate task generation
- Task snapshot behavior
- Future stages use latest active config
- Sales Order forward transition blocked by open tasks
- Sales Order advances once tasks complete
- No task gate when no tasks exist
- Cancellation removes open tasks
- Completed tasks remain visible
- Assigned user can complete assigned task
- Completed task stores `completed_at` and `completed_by_user_id`
- Completed task cannot be reopened
- `workflow-manage` gates admin configuration
- Admins receive `workflow-manage`
- Sales Order transition permission remains unchanged
- Tenant scoping

---

### PR3-SO-008 — Shared Orders CRUD Index + Detail + Line-Level CSV Import/Export

Status: Implemented

**Goal**
Refactor Sales Orders so the index is a clean shared CRUD/import/export surface and move workflow and order-line operations to the Sales Order detail page, while adding full external CSV order import/export support.

**Includes**

- Route remains `/sales/orders` for the shared Orders CRUD index
- New detail route: `/sales/orders/{salesOrder}`
- Orders index uses the same shared configured CRUD/import/export page-module pattern as Products and Customers
- Orders index remains header-only:
    - columns: `id`, `date`, `customer_name`, `city`, `status`
    - import/export actions from the shared slide-over components
    - row `View` action to `/sales/orders/{salesOrder}`
- Sales Order detail page owns:
    - order lines
    - workflow UI
    - current-stage workflow tasks
    - editable line/workflow actions when lifecycle rules allow them
- Full CSV export is line-level:
    - one CSV row per sales order line
    - repeated order header fields on every row
    - uses import identities only, not app internal order or line IDs
- Required CSV columns:
    - `external_source`
    - `order_external_id`
    - `order_date`
    - `customer_name`
    - `contact_name`
    - `city`
    - `status`
    - `external_status`
    - `line_external_id`
    - `product_external_id`
    - `product_name`
    - `quantity`
    - `unit_price`
- File-upload preview groups CSV rows into unique orders by `(tenant_id, external_source, order_external_id)`
- File-upload preview renders one compact preview record per grouped order
- WooCommerce order import remains supported through the same shared import surface

**Rules**

- `external_source` is required for CSV import
- Every row in one CSV file must use the same `external_source`
- Duplicate order detection remains tenant-scoped on `(tenant_id, external_source, order_external_id)`
- Same `external_source + order_external_id` in another tenant is allowed
- Import creates one `sales_orders` row per grouped order and one `sales_order_lines` row per grouped CSV line
- `line_external_id` is source-system line identity only; it is not `sales_order_lines.id`
- `product_external_id` is source-system product identity only; it is not `items.id`
- Missing products are created as inactive sellable items
- Missing imported products do not create fulfillment recipes
- Import creates no stock moves
- Re-import may update only `external_status` and `external_status_synced_at`
- Re-import never changes local app-controlled order status
- Unknown external statuses must fail safely rather than silently mapping to the wrong local status

---

## DOMAIN 3 — External Integration (Post-Inventory Only)

### PR3-INT-001 — External Product Import Prep

Status: Implemented

**Goal**
Prepare the app for ecommerce product imports, starting with WooCommerce, while preserving the invariant that products remain normal items.

**Includes**

- Sales → Products navigation and index
- `GET /sales/products`
- Products is a filtered sales-facing view of normal `items` where `is_sellable = true`
- Imported ecommerce products are created as normal `items`, so they also remain visible on Manufacturing → Materials
- Item fields:
    - `is_active`
    - `external_source`
    - `external_id`
- Tenant-scoped uniqueness protection on `(tenant_id, external_source, external_id)`
- Temporary prep-only stored connection state for stubbed import sources
- Deterministic local/stubbed import flow:
    - source selection
    - WooCommerce available
    - disabled placeholder sources may appear
    - connect-source state
    - preview endpoint with deterministic importable rows
    - import endpoint that accepts selected preview rows
    - bulk manufacturable / purchasable defaults
    - per-row manufacturable / purchasable overrides
- Fields:
    - external_source
    - external_id

**Out of Scope**

- Real WooCommerce API integration
- Real connector infrastructure beyond the minimal prep-only stub contract
- Sync logic
- Webhooks
- Order import
- Customer import
- Inventory sync
- Separate products table or model

---

### PR3-INT-002 — Sales Products Blade Shell + JSON List

Status: Implemented

**Goal**
Convert Sales → Products into a Blade page shell backed by modular JavaScript list/search/sort experiences for desktop and mobile using a tenant-scoped JSON endpoint, while preserving the invariant that products remain normal `items` where `is_sellable = true`.

**Includes**

- `GET /sales/products` Blade page shell
- `GET /sales/products/list` JSON list endpoint
- Desktop JavaScript products list with:
    - search
    - single-column sorting
    - sticky search/header rows
    - price column
    - row actions affordance
- Mobile JavaScript products card list with:
    - search
    - card layout
    - shared JSON list contract
    - row actions affordance
    - fixed search/action area with scrolling records list
- Existing page-level Products heading retained
- Existing Materials-style create slide-over reused only as minimally required for add-product flow
- Existing sales products permissions preserved
- JSON response contract for list rows:
    - `id`
    - `name`
    - `base_uom`
    - `price`
    - `currency`
    - `image_url`

**Out of Scope**

- Update/delete product endpoints
- Schema changes
- New permission slugs
- External image integration
- Real WooCommerce product-image pulling

**Goal**
Move Sales → Products to a Blade shell with a page-module desktop list backed by a JSON endpoint.

**Includes**

- Keep `GET /sales/products` as the user-facing page
- Add `GET /sales/products/list` JSON read model for sellable items
- Products remain normal `items` filtered by `is_sellable = true`
- Blade remains the source of truth for layout, navigation, and page shell
- Desktop list/search/sort behavior is handled by the page module
- Mobile view intentionally renders a placeholder only
- Add New Product reuses the existing create slide-over pattern with the minimum required backend support
- No schema changes
- No new permission slugs

---

### PR3-INT-003 — Ecommerce Import → Empty Fulfillment Recipes

Status: Planned

**Goal**
When ecommerce products are imported, optionally auto-create empty fulfillment recipes for each imported sellable item.

**Rules**

- Imported ecommerce products remain normal Items
- Ecommerce imports still set `is_sellable = true`
- Create an empty recipe with `recipe_type = fulfillment`
- Output item is the imported product item
- Recipe name defaults to the imported item display name
- `output_quantity = 1.000000`
- No recipe lines are created
- Recipe is treated as incomplete until lines are added
- This must be optional during import, likely default checked
- Do not create manufacturing recipes from ecommerce import
- Do not introduce fulfillment execution behavior in this PR

---

## End State

After PR3 completion:

- Sales domain fully operational
- CRM foundation established
- Sales orders impact inventory correctly
- System ready for external integrations
