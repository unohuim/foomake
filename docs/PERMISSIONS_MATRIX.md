# Permissions Matrix

This document is the source-of-truth for **authorization intent** in this repository.

- Roles are **global** (a user may have multiple roles).
- Permissions are **slugs** (kebab-case).
- Authorization is enforced via **Laravel Gates** defined in `App\Providers\AuthServiceProvider`.
- `super-admin` bypasses all gates via `Gate::before(...)`.

> Provider registration must include `App\Providers\AuthServiceProvider::class` (see `bootstrap/providers.php`).

---

## Permission Slugs

### System

- `system-tenants-manage`
- `system-users-manage`
- `system-roles-manage`

### Purchasing

- `purchasing-suppliers-view`
- `purchasing-suppliers-manage`
- `purchasing-purchase-orders-view`
- `purchasing-purchase-orders-create`
- `purchasing-purchase-orders-update`
- `purchasing-purchase-orders-manage`
- `purchasing-receiving-view`
- `purchasing-receiving-execute`

### Sales

- `sales-customers-view`
- `sales-customers-manage`
- `sales-sales-orders-view`
- `sales-sales-orders-create`
- `sales-sales-orders-update`
- `sales-sales-orders-manage`
- `sales-invoices-view`
- `sales-invoices-create`
- `sales-invoices-manage`

### Inventory

- `inventory-materials-view`
- `inventory-materials-manage`
- `inventory-products-view`
- `inventory-products-manage`
- `inventory-adjustments-view`
- `inventory-adjustments-execute`
- `inventory-make-orders-view`
- `inventory-make-orders-execute`
- `inventory-make-orders-manage`

### Reports

- `reports-view`

---

## Role Capabilities

Roles map to **business responsibilities**, not UI screens.

### Super-Admin

Platform owner role.

- Allowed: **all permissions** (Gate bypass)
- Notes: may require explicit cross-tenant flows, but gate checks always pass.

### Admin

Tenant administrator role.

- `system-users-manage`
- `system-roles-manage`
- Purchasing: all purchasing permissions
- Sales: all sales permissions
- Inventory: all inventory permissions
- `reports-view`
- Notes: **no cross-tenant access**; tenancy scoping still applies.

### Founder

Business owner/operator role (non-admin).

- Purchasing: all purchasing permissions
- Sales: all sales permissions
- Inventory: all inventory permissions
- `reports-view`

### Purchasing

Procurement-focused role.

- `purchasing-suppliers-view`
- `purchasing-suppliers-manage`
- `purchasing-purchase-orders-view`
- `purchasing-purchase-orders-create`
- `purchasing-purchase-orders-update`
- `purchasing-purchase-orders-manage`
- `purchasing-receiving-view`
- `purchasing-receiving-execute`
- `reports-view`

### Sales

Revenue-focused role.

- `sales-customers-view`
- `sales-customers-manage`
- `sales-sales-orders-view`
- `sales-sales-orders-create`
- `sales-sales-orders-update`
- `sales-sales-orders-manage`
- `sales-invoices-view`
- `sales-invoices-create`
- `sales-invoices-manage`
- `reports-view`

### Inventory

Stock and production-focused role.

- `inventory-materials-view`
- `inventory-materials-manage`
- `inventory-products-view`
- `inventory-products-manage`
- `inventory-adjustments-view`
- `inventory-adjustments-execute`
- `inventory-make-orders-view`
- `inventory-make-orders-execute`
- `inventory-make-orders-manage`
- `reports-view`

### Tasker

Execution-only role.

- `inventory-make-orders-view`
- `inventory-make-orders-execute`
- `reports-view`

---

## Enforcement Notes

- **All permission checks** must use gates: `Gate::allows('<permission-slug>')` or `@can('<permission-slug>')`.
- Do not hardcode role names in controllers/services (except `super-admin` bypass in `Gate::before`).
- Any new domain area must introduce permission slugs and update this matrix in the same PR.

---

## Provider Registration

Ensure `AuthServiceProvider` is registered (e.g., in `bootstrap/providers.php`):

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
];
```
