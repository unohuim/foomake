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
- `admin-users-view`
- `admin-users-manage`
- `billing-subscription-manage`
- `workflow-manage`

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
- `inventory-stock-view`
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
- `admin-users-view`
- `admin-users-manage`
- `billing-subscription-manage`
- `workflow-manage`
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

Cross-domain workflow execution role.

- `purchasing-purchase-orders-receive`
- `sales-sales-orders-update`
- `inventory-stock-view`
- `inventory-adjustments-execute`
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
- `workflow-manage` gates the `Admin -> Workflows` navigation item and workflow configuration CRUD.
- Admins receive `workflow-manage` by default.
- `admin-users-view` gates tenant admin user-management visibility.
- `admin-users-manage` gates tenant user invitation creation, resend, revocation, and role changes.
- Admins receive both user-management permissions by default.
- `billing-subscription-manage` gates tenant platform billing management.
- Admins receive `billing-subscription-manage` by default.
- Assigned users may complete their own generated workflow tasks without requiring `workflow-manage`.
- `inventory-stock-view` grants read-only Stock -> Inventory availability visibility and does not grant Inventory Counts index/detail visibility.
- Inventory Counts index shows all tenant counts to `inventory-adjustments-view` users and only assigned counts/task-related counts to `inventory-adjustments-execute` users without broad view.
- Users assigned workflow-stage responsibility or generated workflow tasks must have the workflow execution/update credential required by that workflow domain before assignment.
- Assignment-scoped resource visibility is granted by the resource Gate/policy for direct resource assignees and users assigned to generated workflow-stage tasks on that resource.
- Assignment option lists must filter out users who lack the current workflow domain's required assignment credential.
- Assigned users do not require `workflow-manage` solely to complete an assigned workflow task.
- Workflow task assignment credentials are not the same as workflow ownership or workflow movement credentials.
- Tasker users may be assigned workflow tasks and may complete their own assigned tasks, but they must not move Purchase Order, Sales Order, or Make Order workflows.
- Tasker users may be assigned Inventory Count workflow responsibility and may submit an assigned draft count into workflow, but final Inventory Count workflow completion/posting requires Inventory Count view plus execute authority.
- Purchase Order workflow ownership assignment requires `purchasing-purchase-orders-create`; PO workflow movement, receiving, short close, and cancellation require both `purchasing-purchase-orders-create` and `purchasing-purchase-orders-receive`.
- Make Order workflow ownership and movement require both `inventory-make-orders-view` and `inventory-make-orders-execute`.
- Sales-order lifecycle transitions continue requiring existing Sales Order permissions even after workflow tasks are introduced.
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
