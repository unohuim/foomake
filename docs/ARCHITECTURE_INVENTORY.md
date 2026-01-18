# Architecture Inventory

This document tracks **reusable abstractions, components, and architectural patterns**
used throughout the project.

Its purpose is to:

- Prevent duplicate abstractions
- Make intent explicit for future contributors (human or AI)
- Serve as the architectural source of truth

This is an **index**, not a tutorial.

---

## How to Use This Document

- **Before creating a new abstraction**, review this inventory.
- **When introducing a reusable abstraction**, add an entry in the same PR.
- Entries must be factual, minimal, and descriptive.
- Absence from this file implies the abstraction **does not exist**.

---

## Entry Requirements

Each entry must include:

- **Name**
- **Type** (Model, Trait, Scope, Provider, Pattern, etc.)
- **Location**
- **Purpose**
- **When to Use**
- **When Not to Use**
- **Public Interface**
- **Example Usage**

---

## Inventory

---

## Multi-Tenancy Architecture

### Single Database, Tenant ID Scoping

**Name:** Single Database Tenant Scoping  
**Type:** Architectural Pattern  
**Location:** Across all tenant-owned models + migrations (enforced via tenant scope)

**Purpose:**  
Ensure tenant isolation in a single database by requiring `tenant_id` on tenant-owned tables and scoping queries to the authenticated user's tenant.

**Rules:**

- All tenant-owned tables must include `tenant_id`
- Tenant scoping is enforced by default via the tenant global scope on tenant-owned models
- Cross-tenant access must be explicit and justified
- Auth flows must not break when unauthenticated

**When to Use:**

- Any tenant-owned domain table/model

**When Not to Use:**

- Global/system tables (roles, permissions, etc.)
- Authentication identity resolution

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
**Type:** Trait + Global Eloquent Scope  
**Location:**

- `app/Models/Concerns/HasTenantScope.php`
- `app/Models/Scopes/TenantScope.php`

**Purpose:**  
Enforce tenant isolation by automatically scoping tenant-owned models via `tenant_id`
resolved from authenticated user context.

**Rules:**

- This is the **only permitted tenant resolution mechanism** for domain models
- Scope is a no-op when no authenticated user exists

**When to Use:**

- Any tenant-owned Eloquent model

**When Not to Use:**

- Global/system models
- Auth identity resolution models (e.g., `User`)

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

### User Model (Auth Identity Safety)

**Name:** User Auth Identity Safety  
**Type:** Architectural Rule / Pattern  
**Location:** `app/Models/User.php`

**Purpose:**  
Ensure authentication and identity resolution are never affected by tenant scoping.

**Rules:**

- `User` must NOT use `HasTenantScope`
- Users remain globally queryable even when authenticated
- Tenant isolation is enforced at domain boundaries, not identity resolution

**When to Use:**

- Authentication, authorization, and identity lookup

**When Not to Use:**

- Tenant-owned domain data queries

**Public Interface:**

- N/A (rule enforced by convention + tests)

**Example Usage:**

```php
// Safe: user identity lookup must not be tenant-scoped
$user = User::where('email', $email)->first();
```

---

### Tenant Model

**Name:** Tenant  
**Type:** Eloquent Model  
**Location:** `app/Models/Tenant.php`

**Purpose:**  
Represent a business tenant in a single-database, multi-tenant architecture.

**When to Use:**

- Establishing tenant ownership
- Associating users with a tenant

**When Not to Use:**

- Global/system configuration unrelated to a business

**Public Interface:**

- `users()`

**Example Usage:**

```php
$tenant = Tenant::create(['tenant_name' => 'FooMake']);
$users = $tenant->users;
```

---

## Authorization Layer

### Domain Authorization Layer

**Name:** Domain Authorization Layer  
**Type:** Authorization Pattern (Laravel Gates)  
**Location:** `app/Providers/AuthServiceProvider.php`

**Purpose:**  
Centralize authorization using global roles, permission slugs, and Gates.

**Rules:**

- UI visibility is never the source of truth
- Permission slugs are canonical
- `super-admin` bypasses all checks via `Gate::before`

**When to Use:**

- Any access control decision
- Any read/write permission enforcement

**When Not to Use:**

- UI-only visibility logic without backend enforcement

**Public Interface:**

- Gate slugs (e.g. `inventory-products-manage`)
- `Gate::allows()`
- `Gate::authorize()`

**Example Usage:**

```php
Gate::authorize('sales-customers-view');
```

---

### Role Model

**Name:** Role  
**Type:** Eloquent Model  
**Location:** `app/Models/Role.php`

**Purpose:**  
Represent global roles that describe business responsibilities.

**When to Use:**

- Assigning responsibilities to users
- Grouping permissions

**When Not to Use:**

- Per-tenant role definitions (not supported)

**Public Interface:**

- `users()`
- `permissions()`

**Example Usage:**

```php
$user->roles()->attach($roleId);
```

---

### Permission Model

**Name:** Permission  
**Type:** Eloquent Model  
**Location:** `app/Models/Permission.php`

**Purpose:**  
Store canonical permission slugs enforced via Gates.

**When to Use:**

- Authorization checks
- Mapping permissions to roles

**When Not to Use:**

- UI-only visibility logic without backend enforcement

**Public Interface:**

- `roles()`

**Example Usage:**

```php
$permission->roles()->attach($roleId);
```

---

## Inventory & Units of Measure

### UoM Category

**Name:** UomCategory  
**Type:** Eloquent Model  
**Location:** `app/Models/UomCategory.php` (and `uom_categories` table)

**Purpose:**  
Group units of measure into categories that define safe conversion boundaries.

**When to Use:**

- Defining conversion-safe groupings (mass, volume, count, etc.)

**When Not to Use:**

- Cross-category conversion logic (handled item-specifically)

**Public Interface:**

- `uoms()`

**Example Usage:**

```php
$category = UomCategory::create(['name' => 'Mass']);
$uoms = $category->uoms;
```

---

### UoM

**Name:** Uom  
**Type:** Eloquent Model  
**Location:** `app/Models/Uom.php` (and `uoms` table)

**Purpose:**  
Represent a unit of measure, belonging to a single UoM category.

**When to Use:**

- Assigning units to items
- Recording quantities with explicit units

**When Not to Use:**

- Implicit unit assumptions

**Public Interface:**

- `uomCategory()`

**Example Usage:**

```php
$grams = Uom::create([
    'uom_category_id' => $category->id,
    'name' => 'Gram',
    'symbol' => 'g',
]);
```

---

### Global UoM Conversions

**Name:** Global UoM Conversions  
**Type:** Domain Rule / Model Constraint  
**Location:**

- `app/Models/UomConversion.php`
- `uom_conversions` table

**Purpose:**  
Provide safe, reusable unit conversions that are **category-bound**.

**Rules:**

- Conversions are allowed **only within the same UoM category**
- Cross-category conversions are **explicitly forbidden** at the global level

**When to Use:**

- Mass ↔ mass (e.g. kg ↔ g)
- Volume ↔ volume
- Any universally true conversion

**When Not to Use:**

- Count ↔ weight
- Item-specific assumptions (e.g. patties, apples)

**Public Interface:**

- `UomConversion::create()`

**Example Usage:**

```php
UomConversion::create([
    'from_uom_id' => $kg->id,
    'to_uom_id' => $grams->id,
    'conversion_factor' => '1000',
]);
```

---

### Item-Specific UoM Conversions

**Name:** Item-Specific UoM Conversions  
**Type:** Eloquent Model + Domain Rule  
**Location:**

- `app/Models/ItemUomConversion.php`
- `item_uom_conversions` table

**Purpose:**  
Allow **cross-category unit conversions** that are true **only for a specific Item**.

**Rules:**

- Cross-category conversions are **never global**
- All conversions are **item-scoped and tenant-scoped**
- Conversion factors must be **strictly greater than zero**
- No global fallback or conversion engine exists

**When to Use:**

- Count ↔ weight conversions tied to a physical item
- Any non-universal unit relationship

**When Not to Use:**

- Global conversions
- Conversion chaining or inference

**Public Interface:**

- `Item::itemUomConversions()`
- Item lookup helpers (project-specific)

**Example Usage:**

```php
$item->itemUomConversions()->create([
    'from_uom_id' => $count->id,
    'to_uom_id' => $grams->id,
    'conversion_factor' => '50.0',
]);
```

---

## Inventory Ledger

### Stock Move (Append-Only Inventory Ledger)

**Name:** StockMove  
**Type:** Eloquent Model + Domain Rule  
**Location:**

- `app/Models/StockMove.php`
- `stock_moves` table

**Purpose:**  
Represent immutable inventory movements.  
On-hand quantity is derived strictly as the sum of related stock moves.

**Rules:**

- Append-only: updates and deletes are forbidden
- Quantity is signed (`+` receipt, `-` issue/adjustment)
- Inventory is derived, never stored
- `uom_id` must match `items.base_uom_id`

**Append-only enforcement (required to be explicit):**

- **Model-level**: override `save()` to disallow updates, and override `delete()` to always throw  
  (or equivalent explicit model mechanism documented here)
- **Database-level**: no `updated_at` column; `updated_at` disabled in the model

**When to Use:**

- Any inventory-affecting operation (receiving, selling, consuming, adjusting)

**When Not to Use:**

- Storing or mutating on-hand totals directly
- Caching or snapshotting inventory state

**Public Interface:**

- `Item::stockMoves()`
- `Item::onHandQuantity(): string`

**Example Usage:**

```php
StockMove::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'uom_id' => $item->base_uom_id,
    'quantity' => '10.0',
    'type' => 'receipt',
]);
```

---

### Item Model (Inventory-Derived On-Hand)

**Name:** Item  
**Type:** Eloquent Model + Domain Rule  
**Location:** `app/Models/Item.php`

**Purpose:**  
Represent a stock-tracked material or product. Inventory is derived from the ledger.

**Rules:**

- Each Item has exactly one base UoM (`base_uom_id`)
- No on-hand quantity column exists on `items`
- On-hand is computed as the sum of `stock_moves.quantity` for the item

**When to Use:**

- Representing tenant-owned stock tracked entities

**When Not to Use:**

- Tracking inventory via denormalized columns

**Public Interface:**

- `stockMoves()`
- `onHandQuantity(): string`
- `itemUomConversions()`

**Example Usage:**

```php
$onHand = $item->onHandQuantity();
```

---

## Testing Infrastructure

### Pest Test Framework

**Name:** Pest  
**Type:** Testing Infrastructure  
**Location:** `tests/`

**Purpose:**  
Canonical test framework for all automated tests.

**Rules:**

- All new automated tests MUST be written in Pest
- PHPUnit tests are legacy-only and must not be introduced for new tests

**When to Use:**

- Any new automated test

**When Not to Use:**

- New PHPUnit test classes

**Public Interface:**

- `it(...)`, `expect(...)`, `uses(...)` (Pest API)

**Example Usage:**

```php
it('computes on-hand as sum of stock moves', function () {
    expect($item->onHandQuantity())->toBe('10');
});
```
