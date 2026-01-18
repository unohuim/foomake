# Database Schema Inventory

This document inventories all database tables and columns as defined by migrations.
It is intended for bootstrapping context for humans and AI.

## Global Notes

- Migrations are the source of truth for DDL.
- Enum values are defined in `docs/ENUMS.md` (this document only references their existence).
- Tenant scoping is reported per table based on the presence of a `tenant_id` column.

---

## cache

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| key | string | No | — | Primary key |
| value | mediumText | No | — | — |
| expiration | integer | No | — | — |

**Primary Key**

- key

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- None

**Checks / Enums**

- None

---

## cache_locks

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| key | string | No | — | Primary key |
| owner | string | No | — | — |
| expiration | integer | No | — | — |

**Primary Key**

- key

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- None

**Checks / Enums**

- None

---

## failed_jobs

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| uuid | string | No | — | Unique |
| connection | text | No | — | — |
| queue | text | No | — | — |
| payload | longText | No | — | — |
| exception | longText | No | — | — |
| failed_at | timestamp | No | CURRENT_TIMESTAMP | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- Unique: uuid

**Checks / Enums**

- None

---

## inventory_count_lines

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| inventory_count_id | bigint unsigned | No | — | FK (composite) → inventory_counts.(id, tenant_id) (onDelete: cascade) |
| item_id | bigint unsigned | No | — | FK → items.id (onDelete: cascade); implicit index (Laravel default) |
| counted_quantity | decimal(18,6) | No | — | — |
| notes | text | Yes | — | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- item_id → items.id (onDelete: cascade)
- (inventory_count_id, tenant_id) → inventory_counts.(id, tenant_id) (onDelete: cascade)

**Indexes & Unique Constraints**

- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): item_id

**Checks / Enums**

- None

---

## inventory_counts

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| counted_at | timestamp | No | — | — |
| posted_at | timestamp | Yes | — | — |
| posted_by_user_id | bigint unsigned | Yes | — | FK → users.id (onDelete: set null); implicit index (Laravel default) |
| notes | text | Yes | — | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- posted_by_user_id → users.id (onDelete: set null)

**Indexes & Unique Constraints**

- Unique: (id, tenant_id)
- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): posted_by_user_id

**Checks / Enums**

- None

---

## item_purchase_options

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| item_id | bigint unsigned | No | — | FK → items.id (onDelete: cascade); implicit index (Laravel default) |
| supplier_id | bigint unsigned | Yes | — | No foreign key constraint |
| supplier_sku | string | Yes | — | — |
| pack_quantity | decimal(18,6) | No | — | Check constraint (pack_quantity > 0) |
| pack_uom_id | bigint unsigned | No | — | FK → uoms.id (onDelete: cascade); implicit index (Laravel default) |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- item_id → items.id (onDelete: cascade)
- pack_uom_id → uoms.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Index: tenant_id
- Index: (tenant_id, item_id)
- Index: (tenant_id, supplier_sku)
- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): item_id
- Implicit index (Laravel default): pack_uom_id

**Checks / Enums**

- Check: pack_quantity > 0 (MySQL/pgsql only)

---

## item_uom_conversions

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| item_id | bigint unsigned | No | — | FK → items.id (onDelete: cascade); implicit index (Laravel default) |
| from_uom_id | bigint unsigned | No | — | FK → uoms.id (onDelete: cascade); implicit index (Laravel default) |
| to_uom_id | bigint unsigned | No | — | FK → uoms.id (onDelete: cascade); implicit index (Laravel default) |
| conversion_factor | decimal(12,6) | No | — | Check constraint (conversion_factor > 0) |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- item_id → items.id (onDelete: cascade)
- from_uom_id → uoms.id (onDelete: cascade)
- to_uom_id → uoms.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: (tenant_id, item_id, from_uom_id, to_uom_id) [item_uom_conversions_unique]
- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): item_id
- Implicit index (Laravel default): from_uom_id
- Implicit index (Laravel default): to_uom_id

**Checks / Enums**

- Check: conversion_factor > 0 (MySQL/pgsql only)

---

## items

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| name | string | No | — | — |
| base_uom_id | bigint unsigned | No | — | FK → uoms.id; implicit index (Laravel default) |
| is_purchasable | boolean | No | false | — |
| is_sellable | boolean | No | false | — |
| is_manufacturable | boolean | No | false | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- base_uom_id → uoms.id

**Indexes & Unique Constraints**

- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): base_uom_id

**Checks / Enums**

- None

---

## jobs

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| queue | string | No | — | Index |
| payload | longText | No | — | — |
| attempts | unsignedTinyInteger | No | — | — |
| reserved_at | unsignedInteger | Yes | — | — |
| available_at | unsignedInteger | No | — | — |
| created_at | unsignedInteger | No | — | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- Index: queue

**Checks / Enums**

- None

---

## job_batches

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | string | No | — | Primary key |
| name | string | No | — | — |
| total_jobs | integer | No | — | — |
| pending_jobs | integer | No | — | — |
| failed_jobs | integer | No | — | — |
| failed_job_ids | longText | No | — | — |
| options | mediumText | Yes | — | — |
| cancelled_at | integer | Yes | — | — |
| created_at | integer | No | — | — |
| finished_at | integer | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- None

**Checks / Enums**

- None

---

## password_reset_tokens

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| email | string | No | — | Primary key |
| token | string | No | — | — |
| created_at | timestamp | Yes | — | — |

**Primary Key**

- email

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- None

**Checks / Enums**

- None

---

## permission_role

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| permission_id | bigint unsigned | No | — | FK → permissions.id (onDelete: cascade); implicit index (Laravel default) |
| role_id | bigint unsigned | No | — | FK → roles.id (onDelete: cascade); implicit index (Laravel default) |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- permission_id → permissions.id (onDelete: cascade)
- role_id → roles.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: (permission_id, role_id)
- Implicit index (Laravel default): permission_id
- Implicit index (Laravel default): role_id

**Checks / Enums**

- None

---

## permissions

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| slug | string | No | — | Unique |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- Unique: slug

**Checks / Enums**

- None

---

## recipes

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| item_id | bigint unsigned | No | — | FK → items.id (onDelete: cascade); implicit index (Laravel default) |
| is_active | boolean | No | true | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- item_id → items.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: (id, tenant_id)
- Index: (tenant_id, item_id)
- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): item_id

**Checks / Enums**

- None

---

## recipe_lines

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| recipe_id | bigint unsigned | No | — | FK (composite) → recipes.(id, tenant_id) (onDelete: cascade) |
| item_id | bigint unsigned | No | — | FK → items.id (onDelete: cascade); implicit index (Laravel default) |
| quantity | decimal(18,6) | No | — | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- item_id → items.id (onDelete: cascade)
- (recipe_id, tenant_id) → recipes.(id, tenant_id) (onDelete: cascade)

**Indexes & Unique Constraints**

- Index: (recipe_id, item_id)
- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): item_id

**Checks / Enums**

- None

---

## roles

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| name | string | No | — | Unique |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- Unique: name

**Checks / Enums**

- None

---

## roles_users

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| role_id | bigint unsigned | No | — | FK → roles.id (onDelete: cascade); implicit index (Laravel default) |
| user_id | bigint unsigned | No | — | FK → users.id (onDelete: cascade); implicit index (Laravel default) |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- role_id → roles.id (onDelete: cascade)
- user_id → users.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: (role_id, user_id)
- Implicit index (Laravel default): role_id
- Implicit index (Laravel default): user_id

**Checks / Enums**

- None

---

## sessions

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | string | No | — | Primary key |
| user_id | bigint unsigned | Yes | — | Index |
| ip_address | string(45) | Yes | — | — |
| user_agent | text | Yes | — | — |
| payload | longText | No | — | — |
| last_activity | integer | No | — | Index |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- Index: user_id
- Index: last_activity

**Checks / Enums**

- None

---

## stock_moves

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default) |
| item_id | bigint unsigned | No | — | FK → items.id (onDelete: cascade); implicit index (Laravel default) |
| uom_id | bigint unsigned | No | — | FK → uoms.id (onDelete: cascade); implicit index (Laravel default) |
| quantity | decimal(18,6) | No | — | — |
| type | enum | No | — | Enum values in docs/ENUMS.md |
| source_type | string | Yes | — | Part of polymorphic relation; implicit composite index (Laravel default) |
| source_id | bigint unsigned | Yes | — | Part of polymorphic relation; implicit composite index (Laravel default) |
| created_at | timestamp | No | CURRENT_TIMESTAMP | — |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)
- item_id → items.id (onDelete: cascade)
- uom_id → uoms.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Implicit index (Laravel default): tenant_id
- Implicit index (Laravel default): item_id
- Implicit index (Laravel default): uom_id
- Implicit index (Laravel default): (source_type, source_id)

**Checks / Enums**

- Enum: stock_moves.type (values in docs/ENUMS.md)

---

## tenants

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| tenant_name | string | Yes | — | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- None

**Checks / Enums**

- None

---

## uom_categories

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| name | string | No | — | Unique |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- None

**Indexes & Unique Constraints**

- Unique: name

**Checks / Enums**

- None

---

## uom_conversions

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| from_uom_id | bigint unsigned | No | — | FK → uoms.id (onDelete: cascade); implicit index (Laravel default) |
| to_uom_id | bigint unsigned | No | — | FK → uoms.id (onDelete: cascade); implicit index (Laravel default) |
| multiplier | decimal(18,8) | No | — | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- from_uom_id → uoms.id (onDelete: cascade)
- to_uom_id → uoms.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: (from_uom_id, to_uom_id)
- Implicit index (Laravel default): from_uom_id
- Implicit index (Laravel default): to_uom_id

**Checks / Enums**

- None

---

## uoms

**Tenant-scoped:** No

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| uom_category_id | bigint unsigned | No | — | FK → uom_categories.id (onDelete: cascade); implicit index (Laravel default) |
| name | string | No | — | Unique |
| symbol | string | No | — | Unique |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |

**Primary Key**

- id

**Foreign Keys**

- uom_category_id → uom_categories.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: name
- Unique: symbol
- Implicit index (Laravel default): uom_category_id

**Checks / Enums**

- None

---

## users

**Tenant-scoped:** Yes

**Columns**

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| id | bigint unsigned | No | — | Primary key |
| name | string | No | — | — |
| email | string | No | — | Unique |
| email_verified_at | timestamp | Yes | — | — |
| password | string | No | — | — |
| remember_token | string(100) | Yes | — | — |
| created_at | timestamp | Yes | — | — |
| updated_at | timestamp | Yes | — | — |
| tenant_id | bigint unsigned | No | — | FK → tenants.id (onDelete: cascade); implicit index (Laravel default); explicit index |

**Primary Key**

- id

**Foreign Keys**

- tenant_id → tenants.id (onDelete: cascade)

**Indexes & Unique Constraints**

- Unique: email
- Index: tenant_id
- Implicit index (Laravel default): tenant_id

**Checks / Enums**

- None
