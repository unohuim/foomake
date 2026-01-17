# Manufacturing Resource Planning (MRP) Application

This is a **single-database, multi-tenant Manufacturing Resource Planning (MRP) system**
designed for **small-batch food manufacturers**.

The application focuses on operational clarity, strict domain authorization,
and strong architectural guarantees.

---

## What This Application Does

The system supports tenant-scoped management of:

- Materials & products
- Purchasing & suppliers
- Inventory & make orders
- Sales orders & invoicing
- Users, roles, and permissions (global, domain-based)

Each **tenant represents a single business**.
All operational data is isolated via `tenant_id`.

---

## Architecture Overview

- Single database, tenant-scoped
- Global roles + permission slugs
- Domain-level authorization using Laravel Gates
- No UI-based authorization
- Tests are the behavioral source of truth

---

## Tech Stack

- Laravel 12
- Laravel Breeze (Blade)
- Alpine.js
- Vite
- Tailwind CSS
- Pest / PHPUnit

No Jetstream.  
No Livewire.

---

## Requirements

- PHP ^8.1
- Composer
- Node.js + npm
- MySQL (local development)

---

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

npm install
npm run dev

php artisan migrate
```

---

## Authorization Model

- Users may have multiple global roles
- Roles grant permission slugs
- Permissions are enforced using Laravel Gates
- `super-admin` bypasses all authorization checks
- Authorization is enforced at domain boundaries, not views

See `docs/PERMISSIONS_MATRIX.md` for canonical role capabilities.

---

## Multi-Tenancy Rules

- All tenant-owned tables must include `tenant_id`
- Tenant scoping is enforced by default via model scope
- Global/system tables must be explicitly documented
- The first user created for a tenant is auto-assigned `admin`
- Tenant names are nullable on creation and may be set later

---

## Documentation

Authoritative documentation lives in `docs/`:

- `AI_RULES.md` – rules for AI-assisted contributions
- `CONVENTIONS.md` – coding and architectural conventions
- `ARCHITECTURE_INVENTORY.md` – reusable abstractions & patterns
- `PERMISSIONS_MATRIX.md` – role and permission definitions

These files are considered part of the contract of the repository.

---

## Testing

- Tests define expected behavior
- Feature tests are preferred for domain logic
- Authorization and tenancy rules must be test-covered

---

## License

MIT
