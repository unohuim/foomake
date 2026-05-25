# Abstraction Review

## Scope

This review focused on the existing architectural seams that already exist in the repository and on places where repeated code is now large enough to justify an approved abstraction.

Reviewed inputs:

- `AGENTS.md`
- `README.md`
- `docs/AI_RULES.md`
- `docs/CONVENTIONS.md`
- `docs/ARCHITECTURE_INVENTORY.md`
- `docs/testing/testing-standards.yaml`
- `docs/architecture/**/*.yaml`

Reviewed implementation surfaces:

- Large controllers in `app/Http/Controllers/**`
- Shared UI libraries in `resources/js/lib/**`
- Page modules in `resources/js/pages/**`
- Shared Blade components in `resources/views/components/**`
- Representative feature suites in `tests/Feature/**`

This document is recommendation-only. It does not approve new abstractions by itself.

## Summary

The strongest abstraction opportunities are not new UI widgets. They are:

1. a shared page-scoped JSON mutation helper for Alpine page modules
2. a PHP builder for reusable detail-section config arrays
3. page-specific payload assembler classes to split read-model assembly out of oversized controllers
4. shared Pest support objects for recurring tenant, permission, UoM, and payload extraction setup
5. a normalized page-payload key contract so page modules can share more helpers safely

These would reduce code volume, lower drift risk, and align better with the existing documented architecture than adding more one-off components.

## Findings

### 1. High: shared page-scoped JSON mutation helper is missing

**Problem**

Many Alpine page modules repeat the same fetch lifecycle:

- set busy state
- send JSON with `X-CSRF-TOKEN`
- handle `422`
- parse JSON
- show toast
- optionally refresh sections or redirect

The same pattern is repeated often enough that bug fixes will drift.

**Evidence**

- `resources/js/pages/manufacturing-recipes-show.js`
- `resources/js/pages/manufacturing-make-orders-show.js`
- `resources/js/pages/materials-show.js`
- `resources/js/pages/sales-customers-show.js`
- `resources/js/pages/sales-orders-show.js`
- `resources/js/pages/purchasing-orders-show.js`
- `resources/js/pages/admin-workflows-index.js`

There is already shared fetch logic for generic CRUD and detail sections:

- `resources/js/lib/generic-crud.js`
- `resources/js/lib/js-crud-section.js`

But non-CRUD page mutations still hand-roll the request pipeline.

**Why existing architecture is insufficient**

`docs/architecture/ui/PageModuleContract.yaml` explicitly allows shared stateless helpers, but the current shared helpers stop at CRUD boundaries. The repeated page-local mutation logic is now large enough that it is no longer just “page-owned orchestration.”

**Recommended abstraction**

Add a small stateless helper dedicated to page-scoped JSON mutations.

Minimal surface area:

- `requestJson({ url, method, csrfToken, body })`
- returns a normalized result with:
  - `ok`
  - `status`
  - `data`
  - `validationErrors`
  - `message`

Optional follow-on helpers:

- `readValidationErrors(result)`
- `readShowUrl(result)`

Keep toast ownership in the page module. Do not move page state into the helper.

**Suggested architecture location**

- New YAML: `docs/architecture/ui/PageScopedJsonMutationPattern.yaml`

**Future reuse cases**

- Recipe detail version actions, ingredient edits, and make-order create
- Make Order detail workflow transitions, due-date edits, and assignee edits
- Customer and Sales Order detail inline mutations
- Admin workflow stage and task-template maintenance pages

### 2. High: reusable detail-section config arrays need a PHP builder

**Problem**

Controllers build large nested section config arrays by hand. The shape is shared, but the authoring style is not.

That increases:

- typo risk in config keys
- inconsistent defaults
- repeated nested array noise
- friction when the shared detail-section contract changes

**Evidence**

Repeated config builders exist in:

- `app/Http/Controllers/ItemController.php`
  - `supplierPackagesSectionConfig()`
  - `purchaseOrdersSectionConfig()`
  - `recipesSectionConfig()`
  - `makeOrdersSectionConfig()`
- `app/Http/Controllers/RecipeController.php`
  - `versionsSectionConfig()`
  - `ingredientsSectionConfig()`
  - `makeOrdersSectionConfig()`
- `app/Http/Controllers/InventoryCountController.php`
  - `countLinesSectionConfig()`
  - `tasksSectionConfig()`

The repeated shape includes the same keys:

- `resource`
- `title`
- `description`
- `emptyState`
- `csrfToken`
- `defaultOpen`
- `permissions`
- `endpoints`
- `fields`
- `rowLayout`
- `actions`

**Why existing architecture is insufficient**

`docs/architecture/ui/ReusableDetailSection.yaml` documents the contract well, but there is no server-side abstraction that makes compliant config authoring easy.

**Recommended abstraction**

Add a small PHP builder or value object for reusable detail-section config.

Minimal surface area:

- `DetailSectionConfig::make($resource, $title)`
- `->description(...)`
- `->emptyState(...)`
- `->defaultOpen(...)`
- `->permissions([...])`
- `->endpoints([...])`
- `->fields([...])`
- `->rowLayout([...])`
- `->actions([...])`
- `->toArray()`

This should not attempt to be generic form-schema magic. It should only reduce structural duplication around the already-approved config shape.

**Suggested architecture location**

- Extend `docs/architecture/ui/ReusableDetailSection.yaml`
- If a separate entry is preferred: `docs/architecture/ui/DetailSectionConfigBuilder.yaml`

**Future reuse cases**

- Material detail sections
- Recipe detail sections
- Inventory Count detail sections
- Any future Supplier or Purchase Order detail subsections that adopt `mountCrudSection`

### 3. High: oversized controllers need page payload assemblers

**Problem**

Several controllers now mix four responsibilities:

- authorization and HTTP handling
- domain write orchestration
- read-model shaping
- page or section config assembly

That makes them hard to reason about and hard to change safely.

**Evidence**

Current file sizes:

- `app/Http/Controllers/RecipeController.php` — 2292 lines
- `app/Http/Controllers/MakeOrderController.php` — 1568 lines
- `app/Http/Controllers/SalesOrderController.php` — 1474 lines
- `app/Http/Controllers/CustomerController.php` — 1395 lines
- `app/Http/Controllers/InventoryCountController.php` — 1143 lines

Representative examples:

- `RecipeController` owns publish, duplicate, checkout, ingredient payloads, active-version header payloads, section configs, and read-model formatting
- `MakeOrderController` owns index CRUD config, detail payloads, workflow payloads, line payloads, and lifecycle actions

**Why existing architecture is insufficient**

The codebase already uses domain actions for some write flows:

- `app/Actions/Manufacturing/MoveMakeOrderWorkflowStageAction.php`
- `app/Actions/Inventory/ExecuteRecipeAction.php`
- `app/Actions/Tasks/CompleteTaskAction.php`

But equivalent read-model assembly is still mostly controller-local. `docs/architecture/manufacturing/RecipeReadModel.yaml` documents the read contract, but there is no dedicated assembler class enforcing it.

**Recommended abstraction**

Do not introduce one global “payload service.” Extract page-specific assembler classes per domain.

Minimal surface area:

- `BuildRecipeDetailPayloadAction`
- `BuildMakeOrderDetailPayloadAction`
- `BuildSalesOrderDetailPayloadAction`
- `BuildCustomerDetailPayloadAction`

Each class should accept the already-loaded domain model plus user context and return one typed array payload for the page module.

**Suggested architecture location**

- Extend existing read-model YAMLs such as:
  - `docs/architecture/manufacturing/RecipeReadModel.yaml`
- Add parallel domain read-model YAMLs only where the abstraction becomes approved and real

**Future reuse cases**

- reuse between `show()` and mutation responses that need to rehydrate the same page
- reduce drift between initial page payloads and AJAX success payloads
- make focused tests easier by isolating payload assembly from controller transport logic

### 4. Medium-High: page payload key naming is inconsistent enough to block better sharing

**Problem**

The same payload concepts use inconsistent naming conventions across pages:

- `csrfToken` and `csrf_token`
- `indexUrl` and `index_url`
- `storeUrl` and `store_url`
- `showUrl` and `show_url`

This forces every page module to normalize its own boundary and reduces the value of shared helpers.

**Evidence**

- `app/Http/Controllers/RecipeController.php`
- `app/Http/Controllers/MakeOrderController.php`
- `app/Http/Controllers/SalesOrderController.php`
- `app/Http/Controllers/CustomerController.php`
- matching readers in `resources/js/pages/**`

Examples:

- `resources/js/pages/manufacturing-recipes-show.js` reads `safePayload.csrf_token`
- `resources/js/pages/sales-orders-show.js` reads `safePayload.csrfToken`
- `resources/js/pages/manufacturing-make-orders-show.js` reads `safePayload.csrf_token`
- `resources/js/pages/purchasing-orders-index.js` reads `safePayload.storeUrl`

**Why existing architecture is insufficient**

`docs/architecture/ui/PageModuleContract.yaml` defines the structural rules for page payload bootstrapping, but it does not yet enforce a naming convention for page-module payload keys.

**Recommended abstraction**

This is primarily a contract abstraction, not a widget abstraction.

Minimal surface area:

- standardize top-level page-module boot payloads to camelCase
- keep API row payload keys unchanged unless the shared renderer requires otherwise

Example contract:

- page boot payloads: `csrfToken`, `indexUrl`, `storeUrl`, `sections`
- raw resource rows may remain domain-shaped if they are already consumed by generic renderers

**Suggested architecture location**

- Extend `docs/architecture/ui/PageModuleContract.yaml`

**Future reuse cases**

- easier adoption of a shared `requestJson(...)` helper
- less page-local normalization code
- more predictable payload inspection in tests

### 5. Medium-High: test support setup is heavily duplicated

**Problem**

A large number of Pest files repeat the same setup closures for:

- tenant creation
- permission grants
- UoM creation
- item creation
- JSON payload extraction from Blade script tags

This repetition is now large enough to create drift and review noise.

**Evidence**

The same closure names recur across many suites:

- `$this->makeTenant`
- `$this->grantPermission`
- `$this->makeUom`
- `$this->extractPayload`

Representative files:

- `tests/Feature/Manufacturing/RecipeVersioningTest.php`
- `tests/Feature/Manufacturing/RecipeDetailVersionsSectionTest.php`
- `tests/Feature/Manufacturing/MakeOrdersTest.php`
- `tests/Feature/Materials/MaterialShowTest.php`
- `tests/Feature/Sales/CustomersTest.php`
- `tests/Feature/Purchasing/PurchaseOrders/*`

**Why existing architecture is insufficient**

`docs/testing/testing-standards.yaml` correctly forbids global test functions, but the repository has no shared alternative such as support traits or helper objects.

**Recommended abstraction**

Add test support traits or helper classes under `tests/Support/` rather than global functions.

Minimal surface area:

- `InteractsWithTenants`
- `InteractsWithPermissions`
- `InteractsWithUoms`
- `ExtractsBladePayload`

Possible usage:

- `uses(RefreshDatabase::class, InteractsWithTenants::class, ExtractsBladePayload::class);`

or a single small support object attached in `beforeEach()`.

Keep domain-specific fixtures local. Only extract the truly cross-suite primitives.

**Suggested architecture location**

- New test-support documentation section, or extend `docs/testing/testing-standards.yaml` after approval

**Future reuse cases**

- all manufacturing feature suites
- purchasing suites with repeated tenancy and permission setup
- sales suites with repeated payload extraction and auth setup

### 6. Medium: server-side row presentation is still inconsistent across pages

**Problem**

Some pages ship fully formatted row display metadata from PHP, while others still compute badge text and tone in page-local JavaScript.

That leaves presentation logic split across languages and makes shared row rendering less authoritative.

**Evidence**

JavaScript-side presentation helpers still exist in:

- `resources/js/pages/materials-show.js`
  - `supplierPackageStateDisplay()`
  - `purchaseOrderStatusDisplay()`
  - `recipeStateDisplay()`
  - `makeOrderStatusDisplay()`

Server-side display shaping already exists in:

- `app/Http/Controllers/RecipeController.php`
- `app/Http/Controllers/MakeOrderController.php`
- `app/Http/Controllers/InventoryCountController.php`

**Why existing architecture is insufficient**

The shared section renderer already expects a `display.*` contract. The architecture does not need a new renderer. It needs stronger enforcement that controllers, not page modules, own row presentation metadata where the state is already domain-known.

**Recommended abstraction**

Do not create one global status presenter. Standardize on a rule:

- shared CRUD row display metadata should be fully assembled on the server whenever the row state already exists in server payloads

Minimal surface area:

- move JS-local badge text/tone mapping into controller payload builders for the affected sections
- keep the shared renderer presentation-only

**Suggested architecture location**

- Extend `docs/architecture/ui/ReusableDetailSection.yaml`
- optionally extend `docs/architecture/ui/ConfiguredCrudPageModulePattern.yaml`

**Future reuse cases**

- Material detail sections
- future supplier or purchase-order child sections
- less JS branching around status labels and badge tones

## Recommendation Order

If only a few abstractions are approved, the highest-value order is:

1. page-scoped JSON mutation helper
2. test support helpers
3. detail-section config builder
4. page payload assembler classes
5. payload key normalization
6. server-side row presentation standardization

## Non-Recommendations

These areas do not currently justify a new abstraction:

- a single global lifecycle action catalog across all domains
- a generic “domain service” layer for every controller
- replacing Blade or Alpine with a more stateful frontend pattern

The existing architecture is already correctly opinionated toward:

- domain-specific actions for write flows
- shared UI renderers for generic CRUD shells
- page-local orchestration where behavior is still genuinely page-specific

The main gap is not absence of components. It is absence of a few disciplined seams around payload assembly, request plumbing, and test support.
