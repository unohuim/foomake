# Architecture Inventory

This document tracks reusable abstractions, components, and patterns used throughout the project.

Its purpose is to prevent duplication, improve discoverability, and provide a shared mental model for both human and AI contributors.

---

## How to Use This Document

- Before creating a new abstraction, review this inventory to determine whether an existing solution already exists.
- When introducing a new reusable component, add an entry here as part of the same PR.
- Entries should be concise, factual, and descriptive—this is an index, not a tutorial.

---

## Entry Requirements

Each inventory entry must include:

- **Name**
- **Type** (e.g. Service, Action, DTO, Helper, Blade Component, JS Module)
- **Location** (file path)
- **Purpose**
- **When to Use**
- **When Not to Use**
- **Public API / Interface**
- **Example Usage**

---

## Inventory

### Tenant Scope Trait

**Name:** Tenant Scope Trait  
**Type:** Trait + Global Scope  
**Location:** `app/Models/Concerns/HasTenantScope.php`, `app/Models/Scopes/TenantScope.php`

**Purpose:**  
Apply tenant scoping to tenant-owned models based on the authenticated user’s tenant.

**When to Use:**

- Any tenant-owned model that must be scoped by `tenant_id`
- Enforcing tenant isolation by default

**When Not to Use:**

- Global models that intentionally bypass tenant scoping

**Public Interface:**

- `use HasTenantScope` on models

**Example Usage:**

```php
class User extends Authenticatable
{
    use HasTenantScope;
}
```

### Tenant Model

**Name:** Tenant  
**Type:** Eloquent Model  
**Location:** `app/Models/Tenant.php`

**Purpose:**  
Represent a tenant entity for single-database multi-tenancy.

**When to Use:**

- Establishing tenant ownership on tenant-scoped records
- Resolving tenant information for users

**When Not to Use:**

- Global configuration not tied to a tenant

**Public Interface:**

- `users()` relationship

**Example Usage:**

```php
$tenant = Tenant::first();
$users = $tenant->users;
```

### Role Model

**Name:** Role  
**Type:** Eloquent Model  
**Location:** `app/Models/Role.php`

**Purpose:**  
Represent global roles and link them to permissions and users.

**When to Use:**

- Assigning one or more roles to users
- Defining permission sets

**When Not to Use:**

- Per-tenant role management (not supported)

**Public Interface:**

- `users()` relationship  
- `permissions()` relationship

**Example Usage:**

```php
$role->permissions()->sync($permissionIds);
```

### Permission Model

**Name:** Permission  
**Type:** Eloquent Model  
**Location:** `app/Models/Permission.php`

**Purpose:**  
Store canonical permission slugs used by gates and role mappings.

**When to Use:**

- Authorization checks via gates
- Role-permission mappings

**When Not to Use:**

- UI-only visibility without backend enforcement

**Public Interface:**

- `roles()` relationship

**Example Usage:**

```php
$permission->roles()->attach($roleId);
```

---

## Authorization Layer

**Name:** Global Role + Domain Authorization Layer  
**Type:** Authorization Pattern (Gates / Policies)  
**Location:** `app/Providers/AuthServiceProvider.php`, `app/Policies/*`

**Purpose:**  
Provide a centralized, testable authorization mechanism based on global roles and business domains.

**When to Use:**

- Any access control decision
- Any domain-level permission check

**When Not to Use:**

- UI-only visibility decisions without backend enforcement

**Public Interface:**

- Gate abilities (e.g. `view-inventory`, `manage-sales`)
- Policy methods where appropriate

**Example Usage:**

```php
Gate::authorize('manage-inventory');
```
