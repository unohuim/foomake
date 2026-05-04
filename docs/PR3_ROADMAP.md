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

- Sales order lines may be added, removed, or quantity-edited only while the parent sales order is `DRAFT`
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

**Goal**
Introduce lifecycle without inventory impact yet.

**Statuses**

- DRAFT
- CONFIRMED
- FULFILLED

---

### PR3-SO-005 — Inventory Impact (Critical)

**Goal**
Sales orders must update inventory.

**Includes**

- On fulfillment:
    - Create stock_moves.type = issue
    - Reduce inventory

**Rules**

- Inventory is source of truth
- BCMath required
- Transactional integrity

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
