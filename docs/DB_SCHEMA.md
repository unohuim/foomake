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
- external_product_source_connections
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
- tasks
- uom_categories
- uom_conversions
- uoms
- users
- workflow_domains
- workflow_stages
- workflow_task_templates

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

## external_product_source_connections

**Tenant-owned:** Yes  
**Purpose:** Minimal prep-only stored connection state for stubbed external product imports

### Columns

| Name             | Type      | Nullable | Notes                     |
| ---------------- | --------- | -------- | ------------------------- |
| id               | bigint    | No       | Primary key               |
| tenant_id        | bigint    | No       | FK → tenants.id (CASCADE) |
| source           | string    | No       | Stub source key           |
| connection_label | string    | Yes      | Optional local label      |
| is_connected     | boolean   | No       | Default true              |
| connected_at     | timestamp | Yes      | —                         |
| created_at       | timestamp | Yes      | —                         |
| updated_at       | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, source)`
- Implicit (FK index): `tenant_id`

---

## sales_orders

**Tenant-owned:** Yes  
**Purpose:** Sales order headers shared by the Sales Orders index, the Sales Order detail page, and grouped external-import identity

### Columns

| Name       | Type      | Nullable | Notes                                |
| ---------- | --------- | -------- | ------------------------------------ |
| id         | bigint    | No       | Primary key                          |
| tenant_id  | bigint    | No       | FK → tenants.id (CASCADE)            |
| customer_id | bigint   | No       | FK → customers.id (CASCADE)          |
| contact_id | bigint    | Yes      | FK → customer_contacts.id (SET NULL) |
| order_date | date      | Yes      | Actual order date used by CRUD index/export and external imports |
| status     | string    | No       | Defaults to `DRAFT`; allowed values are defined in `docs/ENUMS.md` |
| external_source | string | Yes    | Imported/source-system order identity namespace |
| external_id | string   | Yes      | Imported/source-system order identity value |
| external_status | string | Yes    | Raw external/source-system order status |
| external_status_synced_at | timestamp | Yes | Last time raw external status was synced |
| created_at | timestamp | Yes      | —                                    |
| updated_at | timestamp | Yes      | —                                    |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, status)`
- Index: `(tenant_id, customer_id)`
- Index: `(tenant_id, contact_id)`
- Unique: `(tenant_id, external_source, external_id)` (`sales_orders_tenant_source_external_unique`)
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `customer_id`
- Implicit (FK index): `contact_id`

### Behavioral Notes

- Sales order headers remain editable only while `status` is `DRAFT` or `OPEN`.
- Sales Orders index remains header-only; order lines and workflow/task UI live on the Sales Order detail page.
- External CSV import/export uses source-system order identity only; app internal order IDs are not part of the import contract.
- Re-import may update only `external_status` and `external_status_synced_at`; it never changes the local app-controlled `status`.
- Imported terminal and non-terminal external orders create no stock moves during import.
- `COMPLETED` and `CANCELLED` are terminal.
- `DRAFT`, `OPEN`, `COMPLETED`, and `CANCELLED` are system statuses.
- Operational middle stages are derived from active tenant `workflow_stages` rows in the `sales` workflow domain and are persisted as uppercase stage keys in `sales_orders.status`.
- New tenants are seeded by default with `packing`, `packed`, and `shipping`, but runtime stage order is database-backed by `sort_order`.
- Entering the first operational stage checks fulfillment availability, reserves nothing, and creates no stock moves.
- Moving from the seeded `packing` stage to the seeded `packed` stage posts inventory issue stock moves in a single transaction.
- Later operational stages and the final operational-stage-to-`COMPLETED` transition create no stock moves.
- Cancellation before packed inventory posting creates no stock moves.
- Cancelling from the seeded `packed` stage appends reversing stock moves and preserves the original issue moves for audit history.

---

## sales_order_lines

**Tenant-owned:** Yes  
**Purpose:** Sales order line items with immutable price snapshots and optional imported source-line identity for the Sales Order detail page and external CSV import/export

### Columns

| Name       | Type          | Nullable | Notes                                  |
| ---------- | ------------- | -------- | -------------------------------------- |
| id         | bigint        | No       | Primary key                            |
| tenant_id  | bigint        | No       | FK → tenants.id (CASCADE)              |
| sales_order_id | bigint    | No       | FK → sales_orders.id (CASCADE)         |
| item_id    | bigint        | No       | FK → items.id (CASCADE)                |
| external_id | string       | Yes      | Imported/source-system line identity; not `sales_order_lines.id` |
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
- Index: `(tenant_id, sales_order_id, external_id)` (`sales_order_lines_tenant_order_external_idx`)
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `sales_order_id`
- Implicit (FK index): `item_id`

### Behavioral Notes

- Sales order line mutations are allowed only while the parent sales order is `DRAFT` or `OPEN`.
- External CSV export emits one row per sales order line and repeats order header fields on each exported row.
- External CSV file-upload import groups rows into unique orders by `(tenant_id, external_source, order_external_id)` and creates one sales-order line per grouped CSV row.
- `external_id` is the source-system line ID when present; it is not used as the local primary key.
- On the seeded `packing -> packed` transition, each line may generate exactly one posted `stock_moves` ledger entry with `source_type = App\Models\SalesOrderLine` and `source_id = sales_order_lines.id`.

---

## workflow_domains

**Tenant-owned:** No  
**Purpose:** Fixed system-owned workflow-domain records used to scope workflow stages, task templates, and generated tasks

### Columns

| Name       | Type      | Nullable | Notes       |
| ---------- | --------- | -------- | ----------- |
| id         | bigint    | No       | Primary key |
| key        | string    | No       | Unique canonical domain key |
| name       | string    | No       | Display name |
| sort_order | unsignedInteger | No | Stable UI/runtime ordering |
| created_at | timestamp | Yes      | —           |
| updated_at | timestamp | Yes      | —           |

### Keys & Indexes

- PK: `id`
- Unique: `key`

---

## workflow_stages

**Tenant-owned:** Yes  
**Purpose:** Tenant-scoped operational workflow stages within a fixed workflow domain

### Columns

| Name              | Type      | Nullable | Notes                               |
| ----------------- | --------- | -------- | ----------------------------------- |
| id                | bigint    | No       | Primary key                         |
| tenant_id         | bigint    | No       | FK → tenants.id (CASCADE)           |
| workflow_domain_id | bigint   | No       | FK → workflow_domains.id (CASCADE)  |
| key               | string    | No       | Tenant/domain-scoped operational key |
| name              | string    | No       | Display name                        |
| description       | text      | Yes      | —                                   |
| sort_order        | unsignedInteger | No | Runtime order within domain         |
| is_active         | boolean   | No       | Default true                        |
| created_at        | timestamp | Yes      | —                                   |
| updated_at        | timestamp | Yes      | —                                   |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, workflow_domain_id, key)` (`wfs_tenant_domain_key_uniq`)
- Index: `(tenant_id, workflow_domain_id, is_active)` (`wfs_tenant_domain_active_idx`)
- Index: `(tenant_id, workflow_domain_id, sort_order)` (`wfs_tenant_domain_sort_idx`)
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `workflow_domain_id`

---

## workflow_task_templates

**Tenant-owned:** Yes  
**Purpose:** Tenant-scoped task-template configuration for workflow-stage task generation

### Columns

| Name                    | Type      | Nullable | Notes                                    |
| ----------------------- | --------- | -------- | ---------------------------------------- |
| id                      | bigint    | No       | Primary key                              |
| tenant_id               | bigint    | No       | FK → tenants.id (CASCADE)                |
| workflow_domain_id      | bigint    | No       | FK → workflow_domains.id (CASCADE)       |
| workflow_stage_id       | bigint    | No       | FK → workflow_stages.id (CASCADE)        |
| title                   | string    | No       | Snapshotted into generated task          |
| description             | text      | Yes      | Snapshotted into generated task          |
| sort_order              | unsignedInteger | No | Runtime order within stage               |
| is_active               | boolean   | No       | Default true                             |
| default_assignee_user_id | bigint   | Yes      | FK → users.id (SET NULL)                 |
| created_at              | timestamp | Yes      | —                                        |
| updated_at              | timestamp | Yes      | —                                        |

### Keys & Indexes

- PK: `id`
- Index: `(tenant_id, workflow_stage_id, is_active)` (`wft_tenant_stage_active_idx`)
- Index: `(tenant_id, workflow_domain_id, workflow_stage_id)` (`wft_tenant_domain_stage_idx`)
- Index: `(tenant_id, workflow_domain_id)` (`wft_tenant_domain_key_idx`)
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `workflow_domain_id`
- Implicit (FK index): `workflow_stage_id`
- Implicit (FK index): `default_assignee_user_id`

---

## tasks

**Tenant-owned:** Yes  
**Purpose:** Tenant-scoped generated workflow tasks snapshotting task-template data against a domain record

### Columns

| Name                      | Type      | Nullable | Notes                                   |
| ------------------------- | --------- | -------- | --------------------------------------- |
| id                        | bigint    | No       | Primary key                             |
| tenant_id                 | bigint    | No       | FK → tenants.id (CASCADE)               |
| workflow_domain_id        | bigint    | No       | FK → workflow_domains.id (CASCADE)      |
| domain_record_id          | unsignedBigInteger | No | Domain-specific record identifier      |
| workflow_stage_id         | bigint    | No       | FK → workflow_stages.id (CASCADE)       |
| workflow_task_template_id | bigint    | Yes      | FK → workflow_task_templates.id (SET NULL) |
| assigned_to_user_id       | bigint    | No       | FK → users.id (CASCADE)                 |
| title                     | string    | No       | Snapshot of template title              |
| description               | text      | Yes      | Snapshot of template description        |
| sort_order                | unsignedInteger | No | Snapshot of template sort order         |
| status                    | string    | No       | Defaults to `open`                      |
| completed_at              | timestamp | Yes      | —                                       |
| completed_by_user_id      | bigint    | Yes      | FK → users.id (SET NULL)                |
| created_at                | timestamp | Yes      | —                                       |
| updated_at                | timestamp | Yes      | —                                       |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, workflow_domain_id, domain_record_id, workflow_stage_id, workflow_task_template_id)` (`tasks_generated_template_unique`)
- Index: `(tenant_id, workflow_domain_id, domain_record_id)` (`tasks_tenant_domain_record_idx`)
- Index: `(tenant_id, workflow_stage_id, status)` (`tasks_tenant_stage_status_idx`)
- Index: `(tenant_id, assigned_to_user_id, status)` (`tasks_tenant_assignee_status_idx`)
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `workflow_domain_id`
- Implicit (FK index): `workflow_stage_id`
- Implicit (FK index): `workflow_task_template_id`
- Implicit (FK index): `assigned_to_user_id`
- Implicit (FK index): `completed_by_user_id`

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
| base_uom_id       | bigint    | No       | FK → uoms.id              |
| is_active         | boolean   | No       | Default true              |
| is_purchasable    | boolean   | No       | Default false             |
| is_sellable       | boolean   | No       | Default false             |
| is_manufacturable | boolean   | No       | Default false             |
| default_price_cents | integer | Yes      | Minor currency units      |
| default_price_currency_code | char(3) | Yes | —                        |
| image_url         | string    | Yes      | Remote image URL only; no local image storage |
| external_source   | string    | Yes      | Prep-only external source key |
| external_id       | string    | Yes      | Prep-only external identity   |
| created_at        | timestamp | Yes      | —                         |
| updated_at        | timestamp | Yes      | —                         |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, external_source, external_id)`
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
**Purpose:** Recipe definitions for manufacturing and fulfillment output composition

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id              | bigint        | No       | Primary key               |
| tenant_id       | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id         | bigint        | No       | FK → items.id (CASCADE)   |
| recipe_type     | string        | No       | Allowed values defined in `docs/ENUMS.md` |
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
- Index: `(tenant_id, recipe_type)`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`

### Behavioral Notes

- `recipe_type` is required and must use values defined in `docs/ENUMS.md`.
- Recipe output candidates are normal `items` where `is_manufacturable = true` or `is_sellable = true`.
- Output items where both flags are false are invalid for recipes.
- Fulfillment recipes normalize `output_quantity` to `1.000000` on save.
- Output quantity storage remains canonical scale `6`; UI display precision is derived from the output item base UoM.

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
