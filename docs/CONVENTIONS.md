# Conventions

This document defines the **mandatory development conventions** for this repository.  
All human and AI-assisted contributions must comply.

These rules are contractual.

---

## Core Principles

- Prefer **clarity over cleverness**
- Prefer **explicit behavior over implicit magic**
- Prefer **small, reviewable changes**
- Match **existing patterns** before proposing new ones
- Architecture and rules are enforced through tests and documentation

---

## Project Structure

- Follow Laravel default structure unless explicitly documented
- Do not introduce new top-level directories without approval
- Configuration belongs in `config/`, not application logic
- Shared logic must be centralized
- Duplication is acceptable only when intentional and scoped

---

## PHP Conventions

- **PSR-12** formatting is mandatory
- PHPDoc is required for:
    - Classes
    - Public and protected methods
    - Complex private methods
    - Non-obvious parameters or return values
- Use strict typing where appropriate
- Avoid magic strings and numbers when meaning matters

---

## Database & Migrations

- Migrations must be **explicit and reversible**
- Never combine unrelated schema changes in a single migration
- Never modify migrations after they have been applied
- Seeders must be deterministic and safe to re-run

---

## Testing Conventions

- Tests are written **before or alongside** behavior changes
- Feature tests are preferred for user- or domain-facing behavior
- Tests must assert **intent**, not implementation details
- Avoid brittle tests that rely on ordering, timing, or side effects

---

## Frontend Conventions

- Blade is the primary templating system
- Alpine.js is used for lightweight interactivity
- No global JavaScript state unless explicitly approved
- Prefer progressive enhancement over JavaScript-first solutions

---

## Naming & Readability

- Names must be descriptive and intention-revealing
- Avoid abbreviations unless widely understood
- Favor readability over brevity

---

## Dependency Management

- All dependencies must be explicitly declared
- `composer.lock` and `package-lock.json` are authoritative and must be committed
- Do not upgrade dependencies without justification and review

---

## Error Handling & Validation

- Fail fast when assumptions are violated
- Validate inputs at boundaries (controllers, requests, jobs)
- Do not silently swallow errors

---

## Documentation

- Update documentation when rules, workflows, or architecture change
- Inline comments explain **why**, not **what**
- README and docs files are part of the repository contract

---

## Deviations

- Deviations require explicit approval
- Temporary deviations must document intent and follow-up

---

## Architecture Inventory

Reusable abstractions and patterns are tracked centrally.

- All reusable backend or frontend abstractions **must** be recorded in  
  `docs/ARCHITECTURE_INVENTORY.md`
- Each entry must include:
    - Purpose
    - Location
    - Public interface
    - Example usage
- New abstractions require:
    - Justification in the PR
    - A same-PR inventory entry

---

## Security

- Treat all user input as untrusted
- Use Laravel’s built-in security features
- Do not introduce security-sensitive changes without explicit review
- Secrets, credentials, and tokens must never be committed

---

## Performance

- Prefer correctness and clarity before optimization
- Watch for N+1 queries, redundant work, and unnecessary loops
- Performance optimizations require measurable justification
- Do not introduce caching, queues, or background jobs without approval

---

## Versioning & Compatibility

- Follow semantic versioning where applicable
- Avoid breaking changes without approval and documentation
- Database and API changes must consider backward compatibility
- Deprecations must be intentional and staged

---

## Styling & CSS

- **Tailwind CSS is the only permitted styling system**
- No native CSS files or inline styles
- All styling must use Tailwind utility classes
- Customizations require Tailwind config changes and approval

---

## Authorization & Roles

This application uses **global roles** and **domain-based permissions**.

### Principles

- Roles describe **job responsibility**, not UI access
- Authorization is enforced at **domain boundaries**
- Views are never a source of truth

### Role & Permission Model

- Users may have **multiple roles**
- Roles grant permissions via permission slugs
- Permissions are enforced using **Laravel Gates**
- `super-admin` bypasses all checks via `Gate::before`

### Authorization Rules

- No hard-coded role checks in controllers or views
- All access checks must use Gates or Policies
- Permission slugs are canonical and domain-oriented

### Naming Conventions

Permission slugs follow:

{domain}-{resource}-{action}

Examples:

- `sales-customers-view`
- `inventory-products-manage`
- `purchasing-purchase-orders-create`

### Testing

- Every permission must have explicit allow/deny tests
- Tests must assert both positive and negative cases

---

## Tenancy Requirements

- Single-database, tenant-scoped architecture
- All tenant-owned tables **must** include `tenant_id`
- Tenant scoping is enforced by default via model scope
- Global tables must be explicitly documented as global
- The first user created for a tenant is automatically assigned `admin`

This rule is foundational and non-negotiable.
