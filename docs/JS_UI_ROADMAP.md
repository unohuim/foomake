# PR-X ROADMAP — UI Conventions Refactor (Framework-Agnostic)

## Goal

Eliminate inline Blade JavaScript and standardize all UI behavior behind page modules,
enabling safe Alpine usage today and low-risk framework swaps later.

---

## PR-X-00 — UI Refactor Baseline (No Behavior Changes)

**Scope**

- Introduce page boot contract:
    - Root element uses `data-page`
    - Page payload provided via `<script type="application/json">`
- Add JS structure:
    - `resources/js/pages/`
    - `resources/js/lib/`
    - `resources/js/ui/`
- Add a page loader in `resources/js/app.js`

**Boot Requirements (Learned Constraints)**

- **Module discovery in production builds**
    - Page loader must use `import.meta.glob('./pages/*.js')` + `import.meta.glob('./pages/**/*.js')`
    - Avoid dynamic import strings like `import(\`./pages/${slug}.js\`)` because built assets may not include
      unresolved paths reliably in production.
- **Alpine boot order**
    - When `[data-page]` exists: **load payload → load page module → call `mount()` (register Alpine.data) → then `Alpine.start()`**
    - Ensure `Alpine.start()` runs **exactly once** (guard against double-start).
- **Global state**
    - No new globals introduced by this refactor.
    - `window.Alpine = Alpine` may remain temporarily if already present, but must not be expanded with new global state.

**Out of Scope**

- No UI behavior changes
- No business logic changes

---

## PR-X-01 — Shared UI Primitives

**Scope**

- Payload parser (safe JSON parsing with fallback)
- API client
    - CSRF injection
    - JSON headers
    - 422 normalization
- Page-scoped toast helper
- Optional confirm helper

**Rules**

- All fetch, error, and toast logic must use these primitives.
- **422 normalization must support stable UI bindings**
    - When UI binds like `errors.field[0]`, error objects must be normalized to a stable shape
      (e.g., missing keys become empty arrays) to avoid runtime “cannot read property [0]” errors.

---

## PR-X-02 — Manufacturing / Inventory Pages Migration

**Scope**

- Inventory Counts (index + show)
- Remove all inline `<script>` blocks from Blade
- Blade files contain:
    - Markup
    - Alpine directives
    - JSON payload only
- Move all logic to `resources/js/pages/**`
- Register Alpine components via JS (no globals)

**Rules**

- Blade partials are presentational and event-emitting only.
- **No optional-chaining assignment LHS**
    - Never use patterns like `el?.textContent = ...` or `obj?.prop = ...` (Vite/Rollup parse errors).
    - Use guarded assignment:
      `const el = ...; if (el) { el.textContent = ...; }`

---

## PR-X-03 — Materials + Units of Measure Pages Migration

**Scope**

- Materials index / CRUD
- UoM Categories CRUD
- Units of Measure CRUD
- Apply same page-module pattern as PR-X-02

**Rules**

- Follow PR-X-02 rules (no inline scripts, page modules only, no optional-chaining assignment LHS).
- Ensure error objects used by templates have stable shapes when indexed.

---

## PR-X-04 — Remaining Interactive Pages

**Scope**

- Any remaining pages with custom JS
- Migrate page-by-page to keep PRs reviewable

**Rules**

- Apply all constraints from PR-X-00 → PR-X-03 consistently across each migrated page.

---

## PR-X-05 — Guardrails

**Scope**

- CI check forbidding `<script>` tags in Blade templates
    - Exception: `<script type="application/json">`
- Optional dev-only console warning if a page module fails to mount

**Additional Guardrails (Recommended)**

- Add a check to prevent optional-chaining assignment LHS in `resources/js/pages/**`
  (simple grep/rg pattern in CI is sufficient).

---

## PR-X-06 — Documentation (Optional, Explicit)

**Triggered only with approval**

- Document the UI Page Module Convention
- Reference (not rewrite) `UI_DESIGN.md`

---

## Bootstrap Invariants (Non-Negotiable)

- Blade templates contain **no executable JavaScript**
- All UI logic lives in `resources/js/pages/**`
- Pages are mounted via `data-page`
- Payloads are JSON and parsed once
- **When `data-page` exists: register Alpine components before `Alpine.start()`**
- No global JavaScript state
- Framework-agnostic by design
