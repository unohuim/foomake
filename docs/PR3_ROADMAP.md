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

**Goal**
Allow multiple contacts per customer.

**Includes**

- Nested under customer detail
- CRUD (AJAX)
- Fields:
    - name
    - email
    - phone
    - role

**Rules**

- 1 customer → many contacts

**Permissions**

- `sales-contacts-manage`

---

### PR3-CUST-003 — Customer Detail View

**Goal**
Provide a stable read model.

**Includes**

- Route: `/sales/customers/{customer}`
- Sections:
    - Core fields
    - Contacts list

---

## DOMAIN 2 — Sales Orders

### PR3-SO-001 — Sales Orders (Draft)

**Goal**
Introduce draft sales orders.

**Includes**

- Route: `/sales/orders`
- Create draft (AJAX)
- Fields:
    - customer_id (required)
    - contact_id (nullable)
    - status = DRAFT

**Permissions**

- `sales-orders-manage`

---

### PR3-SO-002 — Sales Order Lines

**Goal**
Allow adding items to sales orders.

**Includes**

- Add/remove lines (AJAX)
- Fields:
    - item_id
    - quantity (BCMath string)
    - unit_price snapshot

**Rules**

- BCMath enforced (scale = 6)
- No float math

---

### PR3-SO-003 — Pricing Snapshot Invariant

**Goal**
Ensure immutable pricing at line creation.

**Includes**

- Store:
    - unit_price_amount
    - currency
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
