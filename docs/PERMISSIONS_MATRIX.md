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
- `workflow-manage` (planned for future Admin → Workflows configuration)

### Purchasing

- `purchasing-suppliers-view`
- `purchasing-suppliers-manage`
- `purchasing-purchase-orders-create`
- `purchasing-purchase-orders-receive`
- `purchasing-purchase-orders-view` (defined but not used by current purchase-order routes)
- `purchasing-purchase-orders-update` (defined but not used by current purchase-order routes)
- `purchasing-purchase-orders-manage` (defined but not used by current purchase-order routes)
- `purchasing-receiving-view` (defined but not used by current purchase-order routes)
- `purchasing-receiving-execute` (defined but not used by current purchase-order routes)

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
- `inventory-recipes-view`
- `inventory-adjustments-view`
- `inventory-adjustments-execute`
- `inventory-make-orders-view`
- `inventory-make-orders-execute` (does not imply view)
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
- `workflow-manage` (planned)
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
- `purchasing-purchase-orders-receive`
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
- `inventory-recipes-view`
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
- Customer detail read access uses `sales-customers-view`.
- Customer contacts reuse sales-customers-manage.
- Customer contacts do not introduce a separate permission slug.
- Sales orders use `sales-sales-orders-manage` for `/sales/orders` index/create/update/delete, sales-order line CRUD, and customer detail Orders mini-index CRUD.
- Customer detail Orders mini-index read access remains under `sales-customers-view`, but its mutations still require `sales-sales-orders-manage`.
- Sales-order line create, quantity update, and delete mutations do not introduce a separate permission slug.
- `workflow-manage` is planned to gate the future `Admin -> Workflows` navigation item and workflow configuration CRUD.
- Admins are planned to receive `workflow-manage` by default when the feature is implemented.
- Assigned users are planned to complete their own generated workflow tasks without requiring `workflow-manage`.
- Assigned users are planned not to require `sales-sales-orders-manage` solely to complete an assigned workflow task.
- Sales-order lifecycle transitions are planned to continue requiring existing Sales Order permissions even after workflow tasks are introduced.
- Navigation clickability for Sales Orders, Purchase Orders, and Make Orders is not permission-only:
  - permissions and `@can` checks still govern whether the user may see the relevant nav branch
  - backend navigation eligibility decides whether the order item renders as clickable or visible-but-disabled
  - eligibility is tenant-scoped and shared by Blade navigation and `GET /navigation/state`
- Current purchase-order routes use a two-gate model:
  - `purchasing-purchase-orders-create` for index/show/create/update/delete and line mutations
  - `purchasing-purchase-orders-receive` for receipts, short-closes, and manual status transitions
- Make Orders execute permission does not imply view; both gates must be evaluated where required.
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
