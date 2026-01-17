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

### Tenant Scope Trait

**Name:** Tenant Scope Trait  
**Type:** Trait + Global Eloquent Scope  
**Location:**

- `app/Models/Concerns/HasTenantScope.php`
- `app/Models/Scopes/TenantScope.php`

**Purpose:**  
Automatically scope tenant-owned models by `tenant_id` based on the authenticated user.

**When to Use:**

- Any model that represents tenant-owned data
- Enforcing tenant isolation by default

**When Not to Use:**

- Global/system models (e.g. roles, permissions)
- Explicit cross-tenant queries

**Public Interface:**

- `use HasTenantScope`

**Example Usage:**

```php
class User extends Authenticatable
{
    use HasTenantScope;
}
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
- Scoping tenant-owned records

**When Not to Use:**

- Global/system configuration unrelated to a business

**Public Interface:**

- `users()`

**Example Usage:**

```php
$tenant = Tenant::create();
$users = $tenant->users;
```

---

### Role Model

**Name:** Role  
**Type:** Eloquent Model  
**Location:** `app/Models/Role.php`

**Purpose:**  
Represent global roles that define business responsibilities.

**When to Use:**

- Assigning capabilities to users
- Grouping permissions by responsibility

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
Store canonical permission slugs enforced via Laravel Gates.

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

## Authorization Layer

### Domain Authorization Layer

**Name:** Domain Authorization Layer  
**Type:** Authorization Pattern (Laravel Gates)  
**Location:** `app/Providers/AuthServiceProvider.php`

**Purpose:**  
Provide a centralized, domain-driven authorization system using:

- Global roles
- Permission slugs
- Laravel Gates

**Key Rules:**

- Authorization is enforced at the domain level
- UI visibility must never be the source of truth
- `super-admin` bypasses all checks via `Gate::before`

**When to Use:**

- Any access control decision
- Any read/write permission enforcement

**When Not to Use:**

- Purely cosmetic UI decisions

**Public Interface:**

- Gate slugs (e.g. `sales-customers-view`)
- `Gate::allows()`
- `Gate::authorize()`

**Example Usage:**

```php
Gate::authorize('sales-customers-view');
```

---

## Multi-Tenancy Architecture

### Single Database, Tenant ID Scoping

**Type:** Architectural Pattern

**Rules:**

- All tenant-owned tables must include `tenant_id`
- Tenant scoping is enforced by default via model scope
- Cross-tenant access must be explicit and justified
- The first user created for a tenant is auto-assigned the `admin` role

This pattern is **foundational** and may not be bypassed without approval.
