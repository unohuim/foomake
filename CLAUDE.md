# CLAUDE.md — FM Repository

Single-database, multi-tenant Manufacturing Resource Planning (MRP) system for small-batch food manufacturers.

---

## Authoritative Docs (Read Before Acting)

| File | Authority |
|---|---|
| `docs/AI_RULES.md` | Workflow and completion rules |
| `docs/CONVENTIONS.md` | Mandatory coding conventions |
| `docs/ARCHITECTURE_INVENTORY.md` | Approved abstractions and patterns |
| `docs/PERMISSIONS_MATRIX.md` | Canonical roles and permission slugs |
| `docs/ENUMS.md` | All enum values — do not duplicate elsewhere |
| `docs/architecture/**/*.yaml` | Primary source of architectural truth |
| `database/migrations/**` | Source of truth for DB schema |
| `docs/DB_SCHEMA.md` | Contextual reference only |
| `composer.lock`, `package-lock.json` | Dependency reality |

If any of these conflict, stop and ask.

---

## Tech Stack

- **Laravel 12** — Blade, Gates, Eloquent
- **Alpine.js** — lightweight interactivity only, no global JS state
- **Vite + Tailwind CSS** — only styling system, no native CSS or inline styles
- **Pest** — all new tests must use Pest, not PHPUnit classes
- No Jetstream. No Livewire.

---

## Dev Commands

```bash
composer install && npm install
php artisan migrate
npm run dev          # Vite dev server
./ci.sh              # Full CI — run this to validate; do not skip
```

---

## Workflow

- Present a plan and get approval before writing any code.
- Must be >95% certain of requirements before proposing a plan.
- Ask clarifying questions one at a time, stating current certainty level first.
- Never infer intent from partial context.

---

## Completion Gate

Never declare a task, PR, or change set "complete", "finished", or "ready" until explicitly approved in chat.

Every response must end in one of:
- "Awaiting human review"
- "Awaiting approval to proceed"
- "Awaiting requested changes"

---

## CI & Execution

- Do **not** run `./ci.sh` or any test/CI commands unless explicitly told to.
- Do **not** auto-commit or auto-merge.
- Propose the exact commands for the human to run; stop there.

---

## Change Discipline

- Prefer the smallest possible change.
- Never refactor unless explicitly requested.
- No new top-level directories without approval.
- No global JavaScript state unless explicitly approved.
- No new abstractions without approval — check `docs/ARCHITECTURE_INVENTORY.md` first.

---

## PHP Standards

- **PSR-12** formatting is mandatory.
- PHPDoc required on classes, public/protected methods, and complex private methods.
- Use strict typing where appropriate; avoid magic strings.
- Fail fast at boundaries — validate in controllers and form requests, not deep in services.

---

## Testing Standards

- All new tests use **Pest** (`it()`, `expect()`, `uses()`).
- Do not declare global functions in test files — use `beforeEach()` closures instead.
- Use fully-qualified exception names (`\DomainException::class`).
- Every permission must have explicit allow/deny test coverage.
- Feature tests are preferred for domain-facing behavior.
- No implementation begins until proposed tests are reviewed and approved.

---

## Multi-Tenancy (Non-Negotiable)

- All tenant-owned tables must include `tenant_id`.
- Apply `use HasTenantScope` to every tenant-owned Eloquent model.
- `User` model does **not** use `HasTenantScope` — it is an auth identity.
- The first user created for a tenant is auto-assigned `admin`.
- Never bypass tenant scoping outside of explicit global/system contexts.

---

## Authorization

- All access checks must use Laravel Gates or Policies — never hard-coded role names.
- Permission slugs follow `{domain}-{resource}-{action}` (e.g. `sales-customers-manage`).
- `super-admin` bypasses all gates via `Gate::before`.
- Authorization is enforced at domain boundaries, never in views alone.
- Any new domain area must introduce permission slugs and update `docs/PERMISSIONS_MATRIX.md` in the same PR.

See `docs/PERMISSIONS_MATRIX.md` for the full canonical slug list.

---

## Decimal Quantity Math

All quantity math in inventory and purchasing must use BCMath at scale 6:

```php
$total = bcadd($a, $b, 6);   // correct
$total = $a + $b;             // never
```

- Quantities are stored and passed as **strings**, never floats.
- No alternative math libraries without explicit approval.

---

## Key Architectural Patterns

**Before writing anything, check `docs/ARCHITECTURE_INVENTORY.md`** for an existing abstraction.

| Pattern | Where |
|---|---|
| Configured CRUD Page Module | `resources/js/lib/crud-page.js`, `crud-config.js` |
| Import Slide-Over | `resources/js/lib/import-module.js`, `import-config.js` |
| Export Slide-Over | `resources/js/lib/export-module.js` |
| Reusable Combobox | `resources/views/components/combobox.blade.php` |
| Navigation Eligibility | `app/Navigation/NavigationEligibility.php` |
| StockMove Ledger | `app/Models/StockMove.php` — append-only, never mutate on-hand directly |
| QuantityFormatter | `app/Support/QuantityFormatter.php` — for display only, not math |
| Blade directives | `@qty()`, `@qtyForUom()` — use in Blade, not ad-hoc formatting |

---

## Database & Migrations

- Migrations must be explicit and reversible.
- Never combine unrelated schema changes in one migration.
- Never modify a migration that has already been applied.
- `database/migrations/**` is the source of truth; `docs/DB_SCHEMA.md` is context only.

---

## New Abstractions

If a new abstraction is needed, before proposing it:
1. State the problem it solves.
2. Explain why `docs/ARCHITECTURE_INVENTORY.md` is insufficient.
3. Propose the minimal public API.
4. Identify the correct `docs/architecture/<domain>/` location.

Once approved: add a YAML file under `docs/architecture/**` and update `docs/ARCHITECTURE_INVENTORY.md` in the same PR.

---

## Prohibited Actions (Without Explicit Approval)

- Changes directly on the default branch
- Auto-committing or auto-merging
- Running `./ci.sh` or any tests/scripts
- Refactoring beyond requested scope
- Introducing global JS state or hidden side effects
- Modifying architecture, dependencies, or conventions
- Proceeding with unclear requirements

---

## Ignore Paths

Never read, index, modify, or reason about:

- `storage/**`
- `vendor/**`
- `node_modules/**`
- `public/build/**`
- `*.log`
- `.env`
- `bootstrap/cache/**`
