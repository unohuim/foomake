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
- jobs
- job_batches
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

| Name              | Type      | Nullable | Notes                    |
| ----------------- | --------- | -------- | ------------------------ |
| id                | bigint    | No       | Primary key              |
| tenant_id         | bigint    | No       | FK → tenants.id          |
| counted_at        | timestamp | No       | —                        |
| posted_at         | timestamp | Yes      | —                        |
| posted_by_user_id | bigint    | Yes      | FK → users.id (SET NULL) |
| notes             | text      | Yes      | —                        |

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

| Name               | Type          | Nullable | Notes                |
| ------------------ | ------------- | -------- | -------------------- |
| id                 | bigint        | No       | Primary key          |
| tenant_id          | bigint        | No       | FK → tenants.id      |
| inventory_count_id | bigint        | No       | Part of composite FK |
| item_id            | bigint        | No       | FK → items.id        |
| counted_quantity   | decimal(18,6) | No       | —                    |
| notes              | text          | Yes      | —                    |

### Foreign Keys

- `(inventory_count_id, tenant_id)` → inventory_counts.(id, tenant_id)
- `item_id` → items.id

### Keys & Indexes

- PK: `id`
- Implicit (FK index): `tenant_id`
- Implicit (FK index): `item_id`

---

## item_purchase_options

**Tenant-owned:** Yes  
**Purpose:** Purchase pack definitions

### Columns

| Name          | Type          | Nullable | Notes           |
| ------------- | ------------- | -------- | --------------- |
| id            | bigint        | No       | Primary key     |
| tenant_id     | bigint        | No       | FK → tenants.id |
| item_id       | bigint        | No       | FK → items.id   |
| supplier_id   | bigint        | Yes      | No FK           |
| supplier_sku  | string        | Yes      | —               |
| pack_quantity | decimal(18,6) | No       | CHECK > 0       |
| pack_uom_id   | bigint        | No       | FK → uoms.id    |

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

| Name              | Type          | Nullable | Notes           |
| ----------------- | ------------- | -------- | --------------- |
| id                | bigint        | No       | Primary key     |
| tenant_id         | bigint        | No       | FK → tenants.id |
| item_id           | bigint        | No       | FK → items.id   |
| from_uom_id       | bigint        | No       | FK → uoms.id    |
| to_uom_id         | bigint        | No       | FK → uoms.id    |
| conversion_factor | decimal(12,6) | No       | CHECK > 0       |

### Keys & Indexes

- PK: `id`
- Unique: `(tenant_id, item_id, from_uom_id, to_uom_id)`
- Implicit (FK index): tenant_id, item_id, from_uom_id, to_uom_id

---

## items

**Tenant-owned:** Yes  
**Purpose:** Stock-tracked items

### Columns

| Name              | Type    | Nullable | Notes           |
| ----------------- | ------- | -------- | --------------- |
| id                | bigint  | No       | Primary key     |
| tenant_id         | bigint  | No       | FK → tenants.id |
| name              | string  | No       | —               |
| base_uom_id       | bigint  | No       | FK → uoms.id    |
| is_purchasable    | boolean | No       | Default false   |
| is_sellable       | boolean | No       | Default false   |
| is_manufacturable | boolean | No       | Default false   |

### Keys & Indexes

- PK: `id`
- Implicit (FK index): tenant_id
- Implicit (FK index): base_uom_id

---

## stock_moves

**Tenant-owned:** Yes  
**Purpose:** Append-only inventory ledger

### Columns

| Name        | Type          | Nullable | Notes             |
| ----------- | ------------- | -------- | ----------------- |
| id          | bigint        | No       | Primary key       |
| tenant_id   | bigint        | No       | FK → tenants.id   |
| item_id     | bigint        | No       | FK → items.id     |
| uom_id      | bigint        | No       | FK → uoms.id      |
| quantity    | decimal(18,6) | No       | Signed            |
| type        | enum          | No       | See ENUMS.md      |
| source_type | string        | Yes      | Polymorphic       |
| source_id   | bigint        | Yes      | Polymorphic       |
| created_at  | timestamp     | No       | CURRENT_TIMESTAMP |

### Keys & Indexes

- PK: `id`
- Index: `(source_type, source_id)`
- Implicit (FK index): tenant_id, item_id, uom_id

---

## users

**Tenant-owned:** No (auth-safe)  
**Purpose:** Authentication identities

### Columns

| Name      | Type   | Nullable | Notes           |
| --------- | ------ | -------- | --------------- |
| id        | bigint | No       | Primary key     |
| name      | string | No       | —               |
| email     | string | No       | Unique          |
| password  | string | No       | —               |
| tenant_id | bigint | No       | FK → tenants.id |

### Keys & Indexes

- PK: `id`
- Unique: `email`
- Index: `tenant_id`

---

**End of DB_SCHEMA**
