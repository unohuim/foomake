# Conventions

This document defines the development conventions for this repository.  
All human and AI-assisted contributions must follow these rules.

---

## General Principles

- Prefer clarity over cleverness.
- Prefer explicit code over implicit behavior.
- Prefer small, reviewable changes over broad refactors.
- Match existing patterns before introducing new ones.

---

## Project Structure

- Follow Laravel’s default directory conventions unless explicitly documented otherwise.
- Do not introduce new top-level directories without approval.
- Configuration belongs in `config/`, not hardcoded in application logic.
- Shared logic should be centralized; duplication is acceptable only when intentional and scoped.

---

## PHP Conventions

- PSR-12 formatting is mandatory.
- PHPDoc is required for:
    - Classes
    - Public and protected methods
    - Complex private methods
    - Non-obvious parameters or return values
- Use strict typing where appropriate.
- Avoid magic numbers and strings; extract constants when meaning matters.

---

## Database & Migrations

- Migrations must be explicit and reversible.
- Never combine unrelated schema changes in a single migration.
- Do not modify existing migrations after they have been applied.
- Seeders should be deterministic and safe to re-run.

---

## Testing Conventions

- Tests are written before implementation for behavior changes.
- Feature tests are preferred over unit tests for user-facing behavior.
- Tests should assert intent, not implementation details.
- Avoid brittle tests that depend on ordering, timing, or side effects.

---

## Frontend Conventions

- Blade is the primary templating system.
- Alpine.js is used for lightweight interactivity.
- No global JavaScript state unless explicitly approved.
- Prefer progressive enhancement over JavaScript-first solutions.

---

## Naming & Readability

- Use descriptive, intention-revealing names.
- Avoid abbreviations unless they are widely understood.
- Favor readability over brevity in method and variable names.

---

## Dependency Management

- All dependencies must be declared explicitly.
- `composer.lock` and `package-lock.json` are authoritative and must be committed.
- Do not upgrade dependencies without a clear reason and review.

---

## Error Handling & Validation

- Fail fast and loudly when assumptions are violated.
- Validate inputs at boundaries (controllers, requests, jobs).
- Do not silently swallow errors.

---

## Documentation

- Update documentation when developer workflows, commands, or rules change.
- Inline comments should explain _why_, not _what_.
- README and rules files are considered part of the contract of the repo.

---

## Deviations

- Any deviation from these conventions must be explicitly approved.
- Temporary deviations should be documented with intent and follow-up plans.

---

## Architecture Inventory

To prevent duplicate abstractions, this repository maintains an inventory of reusable components and patterns.

- Reusable PHP modules (e.g., services, actions, DTOs, helpers) must be recorded in `docs/ARCHITECTURE_INVENTORY.md`.
- Reusable frontend modules (e.g., Alpine components, JS utilities) must also be recorded there.
- Each entry must include: purpose, location, public API, and a short usage example.
- Before creating a new abstraction, search the inventory and reuse existing modules when possible.
- New abstractions require justification in the PR description and must be added to the inventory as part of the same PR.

---

## Security

- Treat all user input as untrusted by default.
- Use Laravel’s built-in security features (validation, CSRF protection, authorization, hashing) instead of custom implementations.
- Do not introduce security-sensitive changes without explicit review.
- Secrets, credentials, and tokens must never be hardcoded or committed to the repository.

---

## Performance

- Prefer clear, correct implementations before optimization.
- Be mindful of N+1 queries, unnecessary loops, and redundant work.
- Performance optimizations must be justified by measurable impact.
- Do not introduce caching, queues, or background processing without clear intent and approval.

---

## Versioning & Compatibility

- Follow semantic versioning principles where applicable.
- Avoid breaking changes without explicit approval and documentation.
- Database and API changes must consider backward compatibility.
- Deprecations should be intentional, documented, and staged when possible.

---

## Styling & CSS

- Tailwind CSS is the **only** permitted styling framework.
- Do not add or modify native CSS files or inline styles.
- All styling must be expressed using Tailwind utility classes.
- Custom styles or extensions must go through Tailwind configuration and require explicit approval.
