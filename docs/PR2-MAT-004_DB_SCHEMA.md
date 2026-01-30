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

| Name              | Type      | Nullable | Notes                         |
| ----------------- | --------- | -------- | ----------------------------- |
| id                | bigint    | No       | Primary key                   |
| tenant_id         | bigint    | No       | FK → tenants.id (CASCADE)     |
| counted_at        | timestamp | No       | —                             |
| posted_at         | timestamp | Yes      | —                             |
| posted_by_user_id | bigint    | Yes      | FK → users.id (SET NULL)      |
| notes             | text      | Yes      | —                             |
| created_at        | timestamp | Yes      | —                             |
| updated_at        | timestamp | Yes      | —                             |

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

| Name               | Type          | Nullable | Notes                             |
| ------------------ | ------------- | -------- | --------------------------------- |
| id                 | bigint        | No       | Primary key                       |
| tenant_id          | bigint        | No       | FK → tenants.id (CASCADE)         |
| inventory_count_id | bigint        | No       | Part of composite FK              |
| item_id            | bigint        | No       | FK → items.id (CASCADE)           |
| counted_quantity   | decimal(18,6) | No       | —                                 |
| notes              | text          | Yes      | —                                 |
| created_at         | timestamp     | Yes      | —                                 |
| updated_at         | timestamp     | Yes      | —                                 |

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

| Name          | Type       | Nullable | Notes       |
| ------------- | ---------- | -------- | ----------- |
| id            | string     | No       | Primary key |
| name          | string     | No       | —           |
| total_jobs    | integer    | No       | —           |
| pending_jobs  | integer    | No       | —           |
| failed_jobs   | integer    | No       | —           |
| failed_job_ids| longText   | No       | —           |
| options       | mediumText | Yes      | —           |
| cancelled_at  | integer    | Yes      | —           |
| created_at    | integer    | No       | —           |
| finished_at   | integer    | Yes      | —           |

### Keys & Indexes

- PK: `id`

---

## jobs

**Tenant-owned:** No  
**Purpose:** Queue jobs

### Columns

| Name         | Type              | Nullable | Notes |
| ------------ | ----------------- | -------- | ----- |
| id           | bigint            | No       | Primary key |
| queue        | string            | No       | —     |
| payload      | longText          | No       | —     |
| attempts     | unsignedTinyInteger | No     | —     |
| reserved_at  | unsignedInteger   | Yes      | —     |
| available_at | unsignedInteger   | No       | —     |
| created_at   | unsignedInteger   | No       | —     |

### Keys & Indexes

- PK: `id`
- Index: `queue`

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

| Name       | Type      | Nullable | Notes   |
| ---------- | --------- | -------- | ------- |
| id         | bigint    | No       | Primary key |
| slug       | string    | No       | Unique  |
| created_at | timestamp | Yes      | —       |
| updated_at | timestamp | Yes      | —       |

### Keys & Indexes

- PK: `id`
- Unique: `slug`

---

## permission_role

**Tenant-owned:** No  
**Purpose:** Role-permission mapping

### Columns

| Name          | Type      | Nullable | Notes                           |
| ------------- | --------- | -------- | ------------------------------- |
| id            | bigint    | No       | Primary key                     |
| permission_id | bigint    | No       | FK → permissions.id (CASCADE)   |
| role_id       | bigint    | No       | FK → roles.id (CASCADE)         |
| created_at    | timestamp | Yes      | —                               |
| updated_at    | timestamp | Yes      | —                               |

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

| Name       | Type          | Nullable | Notes                             |
| ---------- | ------------- | -------- | --------------------------------- |
| id         | bigint        | No       | Primary key                       |
| tenant_id  | bigint        | No       | FK → tenants.id (CASCADE)         |
| recipe_id  | bigint        | No       | Part of composite FK              |
| item_id    | bigint        | No       | FK → items.id (CASCADE)           |
| quantity   | decimal(18,6) | No       | —                                 |
| created_at | timestamp     | Yes      | —                                 |
| updated_at | timestamp     | Yes      | —                                 |

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

| Name       | Type      | Nullable | Notes   |
| ---------- | --------- | -------- | ------- |
| id         | bigint    | No       | Primary key |
| name       | string    | No       | Unique  |
| created_at | timestamp | Yes      | —       |
| updated_at | timestamp | Yes      | —       |

### Keys & Indexes

- PK: `id`
- Unique: `name`

---

## roles_users

**Tenant-owned:** No  
**Purpose:** Role-user mapping

### Columns

| Name       | Type      | Nullable | Notes                     |
| ---------- | --------- | -------- | ------------------------- |
| id         | bigint    | No       | Primary key               |
| role_id    | bigint    | No       | FK → roles.id (CASCADE)   |
| user_id    | bigint    | No       | FK → users.id (CASCADE)   |
| created_at | timestamp | Yes      | —                         |
| updated_at | timestamp | Yes      | —                         |

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

| Name          | Type    | Nullable | Notes       |
| ------------- | ------- | -------- | ----------- |
| id            | string  | No       | Primary key |
| user_id       | bigint  | Yes      | —           |
| ip_address    | string  | Yes      | —           |
| user_agent    | text    | Yes      | —           |
| payload       | longText| No       | —           |
| last_activity | integer | No       | —           |

### Keys & Indexes

- PK: `id`
- Index: `user_id`
- Index: `last_activity`

---

## stock_moves

**Tenant-owned:** Yes  
**Purpose:** Append-only inventory ledger

### Columns

| Name        | Type          | Nullable | Notes                     |
| ----------- | ------------- | -------- | ------------------------- |
| id          | bigint        | No       | Primary key               |
| tenant_id   | bigint        | No       | FK → tenants.id (CASCADE) |
| item_id     | bigint        | No       | FK → items.id (CASCADE)   |
| uom_id      | bigint        | No       | FK → uoms.id (CASCADE)    |
| quantity    | decimal(18,6) | No       | Signed                    |
| type        | enum          | No       | See ENUMS.md              |
| source_type | string        | Yes      | Polymorphic               |
| source_id   | bigint        | Yes      | Polymorphic               |
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

| Name       | Type      | Nullable | Notes   |
| ---------- | --------- | -------- | ------- |
| id         | bigint    | No       | Primary key |
| name       | string    | No       | Unique  |
| created_at | timestamp | Yes      | —       |
| updated_at | timestamp | Yes      | —       |

### Keys & Indexes

- PK: `id`
- Unique: `name`

---

## uom_conversions

**Tenant-owned:** No  
**Purpose:** Global UoM conversions

### Columns

| Name       | Type          | Nullable | Notes                   |
| ---------- | ------------- | -------- | ----------------------- |
| id         | bigint        | No       | Primary key             |
| from_uom_id| bigint        | No       | FK → uoms.id (CASCADE)  |
| to_uom_id  | bigint        | No       | FK → uoms.id (CASCADE)  |
| multiplier | decimal(18,8) | No       | —                       |
| created_at | timestamp     | Yes      | —                       |
| updated_at | timestamp     | Yes      | —                       |

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

| Name            | Type      | Nullable | Notes                         |
| --------------- | --------- | -------- | ----------------------------- |
| id              | bigint    | No       | Primary key                   |
| uom_category_id | bigint    | No       | FK → uom_categories.id (CASCADE) |
| name            | string    | No       | Unique                        |
| symbol          | string    | No       | Unique                        |
| created_at      | timestamp | Yes      | —                             |
| updated_at      | timestamp | Yes      | —                             |

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
