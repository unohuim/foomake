# Architecture Inventory

This document tracks **reusable abstractions, components, and architectural patterns**
used throughout the project.

Its purpose is to:

- Prevent duplicate abstractions
- Make intent explicit for future contributors (human or AI)
- Serve as the architectural source of truth

This is an **index**, not a tutorial.

---

## Authority & References

- **Enum-like values** (database enums, CHECK constraints, and domain-level enum semantics)
  are defined canonically in **docs/ENUMS.md**.
- This document must not duplicate enum values; it may only reference their existence and usage.

---

## Entry Requirements

Each entry includes:

- **Name**
- **Type**
- **Location**
- **Purpose**
- **When to Use**
- **When Not to Use**
- **Public Interface**
- **Example Usage**

---

## Development Quality

### Best Practice Accountability

**Name:** Best Practice Accountability
**Type:** Engineering Quality Invariant
**Location:**
- `docs/architecture/development/BestPracticeAccountability.yaml`
- `AGENTS.md`
- `docs/AI_RULES.md`
- `docs/CONVENTIONS.md`

**Purpose:**
Require contributors to choose best-practice implementations and surface non-best-practice code encountered during scoped work.

**When to Use:**
Implementing or reviewing code, tests, docs, configuration, integrations, or UI.

**When Not to Use:**
Untouched unrelated code outside approved scope, or explicitly approved temporary deviations with documented follow-up.

**Public Interface:**
- `AGENTS.md`
- `docs/AI_RULES.md`
- `docs/CONVENTIONS.md`

**Rules:**
Contributors must flag non-best-practice code in the touched area before continuing, identify the risk, name the better pattern, and either fix it within approved scope or request approval.

**Example Usage:**
If connector code stores historical rows, current status queries must select active current rows and flag newest-row-only selection as unsafe.

---

## Multi-Tenancy

### Single Database Tenant Scoping

**Name:** Single Database Tenant Scoping
**Type:** Architectural Pattern
**Location:**
- `app/Models/Concerns/HasTenantScope.php`
- `app/Models/Scopes/TenantScope.php`
- `database/migrations/`

**Purpose:**
Ensure tenant isolation by enforcing `tenant_id` on tenant-owned data and scoping queries by authenticated tenant.

**When to Use:**
Any tenant-owned model or table.

**When Not to Use:**
Global/system tables or authentication identity resolution.

**Public Interface:**
- `use HasTenantScope`

**Example Usage:**
```php
class Item extends Model
{
    use HasTenantScope;
}
```

---

### Tenant Scope Trait

**Name:** Tenant Scope Trait
**Type:** Trait / Global Eloquent Scope
**Location:**
- `app/Models/Concerns/HasTenantScope.php`
- `app/Models/Scopes/TenantScope.php`

**Purpose:**
Apply a global scope that filters tenant-owned models by `tenant_id`.

**When to Use:**
Any tenant-owned Eloquent model.

**When Not to Use:**
Global/system models or auth identity models like `User`.

**Public Interface:**
- `use HasTenantScope`

**Example Usage:**
```php
class StockMove extends Model
{
    use HasTenantScope;
}
```

## Authentication

### Email Verification Grace Period

**Name:** Email Verification Grace Period
**Type:** Authentication Access Boundary
**Location:**
- `docs/architecture/auth/EmailVerificationGracePeriod.yaml`
- `app/Support/Auth/EmailVerificationGracePeriod.php`
- `app/Http/Middleware/EnsureEmailVerifiedOrInGracePeriod.php`
- `resources/views/layouts/app.blade.php`

**Purpose:**
Allow newly registered unverified users temporary app access before hard email-verification lockout.

**When to Use:**
Checking app-route access for an unverified authenticated user, rendering the soft verification banner, or blocking billing checkout before full email verification.

**When Not to Use:**
Tenant billing entitlement, domain authorization, or tenant scoping.

**Public Interface:**
- `EmailVerificationGracePeriod::isActive()`
- `EmailVerificationGracePeriod::endsAt()`
- `EnsureEmailVerifiedOrInGracePeriod`
- `verification.grace-banner.destroy`

**Example Usage:**
```php
if (app(EmailVerificationGracePeriod::class)->isActive($user)) {
    return $next($request);
}
```

## Billing

### Tenant Billing Entitlement

**Name:** Tenant Billing Entitlement
**Type:** Platform Access Boundary
**Location:**
- `docs/architecture/billing/TenantBillingEntitlement.yaml`
- `app/Support/Billing/TenantBillingEntitlement.php`
- `app/Support/Billing/StripeCheckoutSessionFactory.php`
- `app/Support/Billing/StripeWebhookHandler.php`
- `app/Http/Middleware/EnsureTenantBillingAccess.php`
- `app/Models/Tenant.php`

**Purpose:**
Gate authenticated application access by tenant platform billing state independently from user roles and domain permissions.

**When to Use:**
Checking whether a tenant may access protected application routes, rendering tenant billing status, or applying provider-confirmed subscription state.

**When Not to Use:**
Domain authorization, tenant data scoping, tenant sales invoices, or customer payments.

**Public Interface:**
- `TenantBillingEntitlement::hasAccess()`
- `TenantBillingEntitlement::isTrialActive()`
- `TenantBillingEntitlement::hasActiveSubscription()`
- `StripeCheckoutSessionFactory::createForTenant()`
- `StripeWebhookHandler::handle()`
- `billing-subscription-manage`

**Example Usage:**
```php
if (! app(TenantBillingEntitlement::class)->hasAccess($tenant)) {
    return redirect()->route('billing.index');
}
```

## Inventory

### Inventory Balance Read Model

**Name:** Inventory Balance Read Model
**Type:** Derived Read Model
**Location:**
- `app/Models/InventoryBalance.php`
- `app/Actions/Inventory/ApplyStockMoveToInventoryBalanceAction.php`
- `app/Actions/Inventory/CalculateItemOnHandQuantityAction.php`
- `app/Actions/Inventory/CanConvertInventoryBalancesToUomAction.php`

**Purpose:**
Maintain per-item per-UoM inventory balances derived from posted stock moves so inventory reads can convert and sum compact balance rows instead of scanning the full ledger.

**When to Use:**
Calculating on-hand inventory, validating conversion-covered base UoM changes, or applying newly posted stock moves to derived balances.

**When Not to Use:**
Replacing the immutable stock-move ledger or storing planned supply/demand.

**Public Interface:**
- `InventoryBalance`
- `ApplyStockMoveToInventoryBalanceAction::execute()`
- `CalculateItemOnHandQuantityAction::execute()`
- `CanConvertInventoryBalancesToUomAction::execute()`

**Example Usage:**
```php
$onHand = app(CalculateItemOnHandQuantityAction::class)->execute($item);
```

### Configured CRUD Page Module Pattern

**Name:** Configured CRUD Page Module Pattern
**Type:** UI Architectural Pattern
**Location:**
- `app/Http/Controllers/SalesProductController.php`
- `app/Http/Controllers/CustomerController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/SupplierController.php`
- `app/Http/Controllers/MaterialController.php`
- `app/Http/Controllers/InventoryCountController.php`
- `app/Http/Controllers/Admin/UserManagementController.php`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/views/sales/customers/index.blade.php`
- `resources/views/purchasing/orders/index.blade.php`
- `resources/views/purchasing/suppliers/index.blade.php`
- `resources/views/materials/index.blade.php`
- `resources/views/inventory/counts/index.blade.php`
- `resources/views/manufacturing/make-orders/index.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/js/lib/crud-config.js`
- `resources/js/lib/generic-crud.js`
- `resources/js/lib/crud-card-page.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/js/pages/sales-customers-index.js`
- `resources/js/pages/purchasing-orders-index.js`
- `resources/js/pages/purchasing-suppliers-index.js`
- `resources/js/pages/materials-index.js`
- `resources/js/pages/inventory-counts-index.js`
- `resources/js/pages/manufacturing-make-orders.js`
- `resources/js/pages/admin-users-index.js`

**Purpose:**
Provide a mount-only Blade shell plus server-configured shared CRUD card renderer so index pages reuse one toolbar, card grid or opt-in stacked list, empty-state, and row-action pattern without global JavaScript state.

**When to Use:**
Any interactive CRUD index page that can express its list, row display, actions, and optional import/export behavior from a server-generated contract.

**When Not to Use:**
Static pages, multi-step workflows, or pages that cannot express their behavior through the shared CRUD contract.

**Public Interface:**
- `data-crud-config`
- `data-crud-root`
- `createGenericCrud(parseCrudConfig(rootEl))`
- `mountCrudCardRenderer(rootEl, config)`
- optional `detailUrlTemplate`

**Example Usage:**
```php
$crudConfig = [
    'resource' => 'materials',
    'endpoints' => [
        'list' => route('materials.list'),
        'create' => route('materials.store'),
        'update' => url('/materials/{id}'),
        'delete' => url('/materials/{id}'),
    ],
    'detailUrlTemplate' => url('/materials/{id}'),
];
```

Notes:
- Products, Customers, Purchase Orders, Suppliers, Materials, Inventory Counts, Make Orders, and Admin Users are current reference implementations.
- `detailUrlTemplate` is optional and is used for row/card links.
- Create flows stay on the index, refresh the list, close any create panel, and show toast feedback after success.

### Configured CRUD Card Page Renderer

**Name:** Configured CRUD Card Page Renderer
**Type:** UI Architectural Pattern
**Location:**
- `docs/architecture/ui/ConfiguredCrudCardPageRenderer.yaml`
- `resources/js/lib/crud-card-page.js`
- `resources/js/pages/materials-index.js`

**Purpose:**
Provide a sibling renderer for configured CRUD index pages that preserves the shared CRUD config, toolbar, search, action, and toggle contracts while rendering desktop records as responsive Tailwind cards or an opt-in stacked list instead of a table.

**When to Use:**
Configured CRUD index pages where desktop users benefit from compact visual summaries or stacked list rows rather than dense table comparison.

**When Not to Use:**
Dense operational lists that need table-style scanning, static pages, or reusable detail-section subsections.

**Public Interface:**
- `mountCrudCardRenderer(targetEl, config)`
- `data-crud-config`
- `data-crud-root`
- optional `desktopCard`
- optional `mobileCard`
- optional `desktopList`

**Example Usage:**
```js
mountCrudCardRenderer(crudRootEl, {
    ...crud,
    desktopCard: {
        titleExpression: "record.item || '—'",
        titleAsideExpression: "record.updated_at || ''",
        titleAsideSecondaryExpression: "record.total_display || ''",
        titleBadgesExpression: 'record.status ? [{ label: record.status, tone: record.status_tone || "gray" }] : []',
        subtitleAsideExpression: "record.lines_count ? `Items: ${record.lines_count}` : ''",
        showBody: false,
        compact: true,
        statsExpression: 'inventoryAvailabilityStats(record)',
        urlExpression: 'record.show_url',
    },
    mobileCard: {
        titleExpression: "record.item || '—'",
        titleBadgesExpression: 'record.status ? [{ label: record.status, tone: record.status_tone || "gray" }] : []',
        subtitleExpression: "record.supplier_name || ''",
        titleAsideStatsExpression: 'record.on_hand_display ? [{ label: "On hand", value: record.on_hand_display }] : []',
        centerStatsExpression: 'record.on_hand_display ? [{ label: "On hand", value: record.on_hand_display }] : []',
        iconRowStatsExpression: 'record.on_hand_display ? [{ label: "QTY", value: record.on_hand_display }] : []',
        showBody: false,
        showToggle: false,
    },
});
```

Notes:
- `desktopCard` may configure title-adjacent badges, subtitle-aside text, secondary right-side text, compact height, and body visibility.
- `mobileCard` may configure title-adjacent badges, right-side text, title-aside stats, secondary row values, centered stats, icon-row stats, toggle visibility, and body visibility.
- Badge tone mappings are page-module decisions; the shared renderer owns the Tailwind classes for supported semantic tones.
- The card grid renders two columns at the smallest non-mobile breakpoint and three columns at larger desktop breakpoints.

### Toggle

**Name:** Toggle
**Type:** UI Component Pattern
**Location:**
- `resources/views/components/ui/toggle.blade.php`
- `resources/js/components/toggle.js`
- `resources/js/lib/crud-card-page.js`

**Purpose:**
Provide a reusable Tailwind and Alpine on/off switch that emits row-aware change events without owning persistence.

**When to Use:**
Boolean settings or row state that should be changed through a page-owned AJAX handler.

**When Not to Use:**
Non-boolean values, authorization decisions, validation, or save-success indicators.

**Public Interface:**
- `<x-ui.toggle name="is_active" event="material-active-toggle" />`
- `renderToggle({ name, checkedExpression, disabledExpression, eventName, handler, ariaLabelExpression })`
- Toggle event detail with `name`, `checked`, `value`, `row`, `record`, and `id`

**Example Usage:**
```php
<x-ui.toggle name="is_active" :checked="$item->is_active" event="material-active-toggle" />
```

### Markdown Marketing Pages

**Name:** Markdown Marketing Pages
**Type:** Public Page Pattern
**Location:**
- `resources/content/marketing/`
- `app/Support/Marketing/MarketingPageRepository.php`
- `app/Http/Controllers/MarketingPageController.php`
- `resources/views/marketing/show.blade.php`
- `docs/architecture/ui/MarketingPages.yaml`

**Purpose:**
Render repo-managed public FooMake marketing pages from markdown files with SEO metadata from YAML front matter.

Marketing pages intentionally use `/learn/{slug}` instead of a root-level catch-all so public SEO pages cannot shadow app, auth, admin, or domain routes. The sitemap route exposes only public pages loaded from `resources/content/marketing/`.

**When to Use:**
Public marketing and SEO pages that should live in the Laravel repository without WordPress, a database-backed CMS, or an admin editor.

**When Not to Use:**
Authenticated application pages, tenant-owned content, or user-editable content management.

**Public Interface:**
- `resources/content/marketing/{slug}.md`
- `GET /learn/{slug}`
- `GET /sitemap.xml`
- `MarketingPageRepository::find()`
- `MarketingPageRepository::all()`

**Example Usage:**
```markdown
---
title: "MRP Software for Small Food Manufacturers"
description: "FooMake helps small food manufacturers manage recipes, inventory, purchasing, production, and sales orders without spreadsheets."
slug: "food-manufacturing-mrp"
headline: "MRP software built for small food manufacturers"
cta_label: "Start beta access"
cta_url: "/#register"
---
```

### First-Party Attribution

**Name:** First-Party Attribution
**Type:** Marketing Privacy Pattern
**Location:**
- `app/Http/Middleware/CaptureVisitorAttribution.php`
- `app/Actions/Marketing/LinkVisitorAttributionToUserAction.php`
- `app/Models/VisitorAttribution.php`
- `database/migrations/2026_06_05_000001_create_visitor_attributions_table.php`
- `docs/architecture/marketing/FirstPartyAttribution.yaml`
- `resources/views/layouts/google-analytics.blade.php`

**Purpose:**
Capture minimal first-party source attribution for anonymous public-page visitors and link it to a registered user when registration occurs.

**When to Use:**
Public marketing attribution, registration source linkage, and future demo request source linkage.

**When Not to Use:**
Behavioral tracking, Google Tag Manager, advertising pixels, heatmaps, or session replay.

**Public Interface:**
- `foomake_visitor_id` cookie
- `VisitorAttribution`
- `CaptureVisitorAttribution` middleware
- `LinkVisitorAttributionToUserAction::execute()`
- `resources/views/layouts/google-analytics.blade.php`

**Example Usage:**
```php
$linkAttribution->execute(
    $request->cookies->get(CaptureVisitorAttribution::COOKIE_NAME),
    $user,
);
```

## Integrations

### WordPress WooCommerce Plugin

**Name:** WordPress WooCommerce Plugin
**Type:** Integration Package Boundary
**Location:**
- `docs/architecture/integrations/WordPressWooCommercePlugin.yaml`
- `integrations/wordpress/foomake-connector`
- `app/Support/Integrations/WordPressPluginArchive.php`
- `app/Http/Controllers/ProfileConnectorController.php`
- `app/Http/Controllers/WordPressPluginApiController.php`

**Purpose:**
Define the repository-owned WordPress plugin package boundary for FooMake-specific WooCommerce connector distribution.

**When to Use:**
Maintaining the installable WordPress plugin source package, serving an authenticated downloadable zip from FooMake, or serving FooMake-authorized WooCommerce preview data from the paired WordPress site.

**When Not to Use:**
Tenant connector credential persistence, import persistence, or generated zip source control.

**Rules:**
Laravel remains authoritative for tenant connector credential storage, plugin pairing state, duplicate detection, and import persistence. WordPress-side REST endpoints must require the FooMake-to-plugin site access token before returning WooCommerce data.

**Public Interface:**
- `integrations/wordpress/foomake-connector/foomake-connector.php`
- `integrations/wordpress/foomake-connector/readme.txt`
- `WordPressPluginArchive::build()`
- `profile.connectors.woocommerce.plugin.download`
- `admin_post_foomake_connector_start_pairing`
- `admin_post_foomake_connector_complete_pairing`
- `GET /wp-json/foomake/v1/customers`
- `GET /wp-json/foomake/v1/products`
- `GET /wp-json/foomake/v1/orders`

**Example Usage:**
```php
$archivePath = app(WordPressPluginArchive::class)->build();

return response()
    ->download($archivePath, 'foomake-connector.zip')
    ->deleteFileAfterSend(true);
```

### WordPress Plugin Pairing

**Name:** WordPress Plugin Pairing
**Type:** Integration Trust Boundary
**Location:**
- `docs/architecture/integrations/WordPressPluginPairing.yaml`
- `app/Http/Controllers/ProfileConnectorController.php`
- `app/Http/Controllers/WordPressPluginApiController.php`
- `app/Models/WordPressPluginConnection.php`
- `app/Models/WordPressPluginPairingCode.php`
- `integrations/wordpress/foomake-connector/foomake-connector.php`

**Purpose:**
Define the approved trust handshake between an installed WordPress plugin and a FooMake tenant.

**When to Use:**
Pairing an installed WordPress plugin with a FooMake tenant, issuing or validating a plugin token, or revoking plugin access.

**When Not to Use:**
WooCommerce credential storage, customer/product/order import logic, or browser session authentication for plugin API calls.

**Rules:**
Plugin access tokens are stored raw only in WordPress and hashed in FooMake. FooMake-to-plugin site access tokens are stored in WordPress, encrypted in FooMake, scoped to one tenant/plugin UUID, revoked with the plugin connection, and may be issued to existing connections during authenticated status refresh.

Each tenant may have only one current active WooCommerce WordPress plugin connection; pairing a new plugin UUID revokes older active plugin rows.

**Public Interface:**
- `profile.connectors.wordpress.pair`
- `profile.connectors.wordpress.pair.approve`
- `profile.connectors.wordpress-plugin.destroy`
- `api.wordpress-plugin.pairing.start`
- `api.wordpress-plugin.pairing.complete`
- `api.wordpress-plugin.status`
- `WordPressPluginConnection`
- `WordPressPluginPairingCode`

**Example Usage:**
```text
WordPress plugin starts pairing, FooMake tenant admin approves, plugin exchanges the approved code for a bearer token.
```

### Google Search Console OAuth

**Name:** Google Search Console OAuth
**Type:** Integration Trust Boundary
**Location:**
- `docs/architecture/integrations/GoogleSearchConsoleOAuth.yaml`
- `config/apiurls.php`
- `config/services.php`
- `app/Http/Controllers/ProfileConnectorController.php`
- `app/Integrations/GoogleSearchConsole/GoogleSearchConsoleAdapter.php`
- `app/Models/GoogleSearchConsoleConnection.php`
- `app/Services/GoogleSearchConsoleReportService.php`
- `resources/js/components/connectors/GoogleSearchConsoleConnectorCard.vue`
- `routes/console.php`

**Purpose:**
Provide tenant-scoped, readonly Google Search Console OAuth access for search performance retrieval.

**When to Use:**
Connecting Search Console, refreshing Google OAuth tokens, or fetching FooMake search performance rows.

**When Not to Use:**
Google Docs/Drive generation, Google Analytics reporting, or public marketing tracking.

**Public Interface:**
- `profile.connectors.google-search-console.connect`
- `profile.connectors.google-search-console.callback`
- `profile.connectors.google-search-console.destroy`
- `profile.connectors.google-search-console.refresh`
- `profile.connectors.google-search-console.performance`
- `profile.connectors.google-search-console.report`
- `admin.marketing.search-console.data`
- `GoogleSearchConsoleAdapter`
- `GoogleSearchConsoleReportService`
- `GoogleSearchConsoleConnection`
- `search-console:report`

**Example Usage:**
```php
$rows = $client->searchAnalytics($connection, now()->subDays(28), now()->subDay(), ['query'], 10);
```

```bash
php artisan search-console:report
```

Browser downloads should use `GET /profile/connectors/google-search-console/report` so the markdown file lands on the user's machine instead of the server filesystem.

## Inertia / Vue

### Inertia Route Migration

**Name:** Inertia Route Migration
**Type:** UI Architecture Invariant
**Location:**
- `docs/architecture/ui/InertiaRouteMigration.yaml`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/views/inertia.blade.php`
- `resources/js/inertia-app.js`
- `resources/js/pages/**`
- `resources/js/layouts/**`
- `resources/js/components/**`

**Purpose:**
Allow route-by-route migration from Blade and Alpine to Inertia and Vue while preserving server-side Laravel authority and existing Blade routes until they are explicitly migrated.

Sales customer detail is an approved Inertia route migration. The Inertia page owns page-level orchestration and retrieves its mutable read model from `sales.customers.show.payload`.

Sales products index is an approved Inertia route migration. The page owns products-specific list/create/import/export orchestration and renders records through shared Vue index/card components.

Sales orders detail is an approved Inertia route migration. The Inertia page owns detail orchestration and retrieves its mutable read model from `sales.orders.show.payload`.

Admin marketing is an approved Inertia route migration. The page is super-admin-only and starts with Search Console connection status plus available performance views.

**When to Use:**
Migrating a route to Inertia/Vue, creating new Vue-backed public pages, or building shared Vue guest/auth shells.

**When Not to Use:**
Unmigrated Blade routes, backend domain behavior, authorization, validation, tenancy, or replacing server authority with client state.

**Public Interface:**
- `Inertia::render()`
- `resources/views/inertia.blade.php`
- `resources/js/inertia-app.js`
- `resources/js/pages/Home.vue`
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/Admin/Marketing.vue`
- `resources/js/pages/Profile/Connectors/Index.vue`
- `resources/js/pages/Profile/Connectors/WordPressPair.vue`
- `resources/js/pages/Sales/Customers/Show.vue`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/js/pages/Sales/Orders/Index.vue`
- `resources/js/pages/Sales/Orders/Show.vue`
- `resources/js/layouts/GuestShell.vue`
- `resources/js/layouts/AuthShell.vue`
- `resources/js/components/AuthDrawer.vue`
- `resources/js/components/BaseSlideUpDrawer.vue`
- `resources/js/components/DesktopSidebar.vue`
- `resources/js/components/InfiniteHorizontalNavRail.vue`
- `resources/js/components/MobileBottomNav.vue`
- `resources/js/components/NavIcon.vue`
- `resources/js/components/ResourceIndex.vue`
- `resources/js/components/ResourceCardGrid.vue`
- `resources/js/components/ResourceDetailHeaderBreadcrumb.vue`
- `resources/js/components/ResourceCardGrid.vue`
- `resources/js/components/ResourceCreateDrawer.vue`
- `resources/js/components/ResourceExportDrawer.vue`
- `resources/js/components/ResourceImportDrawer.vue`
- `resources/js/components/connectors/WooCommerceConnectorCard.vue`
- `resources/js/components/connectors/WordPressPluginConnectionCard.vue`
- `resources/js/components/connectors/WordPressPluginDownload.vue`

**Example Usage:**
```php
return Inertia::render('Home', [
    'hero' => [
        'headline' => 'Keep the day’s batches moving without another clipboard.',
    ],
]);
```

### Base Drawer

**Name:** Base Drawer
**Type:** UI Component Abstraction
**Location:**
- `docs/architecture/ui/BaseDrawer.yaml`
- `resources/js/components/BaseDrawer.vue`
- `resources/js/components/*Drawer.vue`

**Purpose:**
Provide one shared Vue drawer boundary for overlay, outside-click closing, Escape closing, accessibility wiring, and 500ms slide/fade drawer transition behavior.

**When to Use:**
Building an Inertia Vue drawer or slideout that should share the standard drawer interaction model.

**When Not to Use:**
Unmigrated Blade/Alpine drawers, centered modal dialogs, or feature-specific form/request orchestration.

**Public Interface:**
- `open`
- `labelledBy`
- `closeLabel`
- `panelClass`
- `closeButtonClass`
- `close`
- default slot

**Transition Contract:**
Drawer panel movement, panel opacity, and backdrop opacity transition over 500ms for both expand and collapse. Visible drawer content must remain mounted until the 500ms collapse transition has completed.

**Layer Contract:**
Drawer overlays use the shared app-overlay layer and remain above page chrome, sticky headers, nav bars, toasts, and page content.

**Example Usage:**
```vue
<BaseDrawer
    :open="authOpen"
    labelled-by="auth-drawer-title"
    @close="closeAuth"
>
    <AuthDrawerContent />
</BaseDrawer>
```

### Base Slide Up Drawer

**Name:** Base Slide Up Drawer
**Type:** UI Component Abstraction
**Location:**
- `docs/architecture/ui/BaseSlideUpDrawer.yaml`
- `resources/js/components/BaseSlideUpDrawer.vue`
- `resources/js/components/MobileBottomNav.vue`

**Purpose:**
Provide one shared Vue bottom-sheet drawer boundary for mobile authenticated navigation menus.

**When to Use:**
Building mobile Inertia Vue menus that slide up from the bottom viewport edge while sharing overlay, outside-click closing, Escape closing, accessibility wiring, and 500ms slide/fade transition behavior.

**When Not to Use:**
Desktop sidebar flyouts, drop-up account menus, unmigrated Blade/Alpine drawers, centered modal dialogs, or feature-specific form/request orchestration.

**Public Interface:**
- `open`
- `labelledBy`
- `close`
- default slot

**Transition Contract:**
Bottom-sheet movement, panel opacity, and backdrop opacity transition over 500ms for both expand and collapse. Visible drawer content must remain mounted until the 500ms collapse transition has completed.

**Layer Contract:**
Bottom-sheet overlays use the shared app-overlay layer and remain above page chrome, sticky headers, nav bars, toasts, and page content.

**Style Contract:**
Mobile navigation and account drawer headers visually inherit from the mobile nav bars with dark navy backgrounds and plain header content. Bottom-sheet top corners use slight rounding rather than large rounded corners.

Nested items inside mobile navigation drawers render as inline accordions, not separate boxed containers.

**Example Usage:**
```vue
<BaseSlideUpDrawer
    :open="Boolean(activeEntry)"
    labelled-by="mobile-nav-drawer-title"
    @close="closeMenu"
>
    <MobileNavMenu :entry="activeEntry" />
</BaseSlideUpDrawer>
```

### Infinite Horizontal Nav Rail

**Name:** Infinite Horizontal Nav Rail
**Type:** UI Component Abstraction
**Location:**
- `docs/architecture/ui/InfiniteHorizontalNavRail.yaml`
- `resources/js/components/InfiniteHorizontalNavRail.vue`
- `resources/js/components/MobileBottomNav.vue`

**Purpose:**
Provide a reusable mobile horizontal navigation rail that fits all items when possible and falls back to an infinite swipe rail when items overflow.

**When to Use:**
Rendering compact mobile navigation actions in constrained horizontal space, while reusing the bottom navigation scroll-hint and fit-measurement behavior.

**When Not to Use:**
Desktop sidebar navigation, long-form tab lists where native wrapping is clearer, domain data tables, carousels, or content galleries.

**Public Interface:**
- `entries`
- `activeKey`
- `storageKey`
- `select`

**Behavior Contract:**
The rail renders one normal item set when all entries fit. It renders repeated item sets only when entries overflow. The scroll hint arrow appears only when overflowing and hides after three completed scroll bursts. A caller-provided per-user localStorage key may persist the hint acknowledgement as UI education state only.

**Example Usage:**
```vue
<InfiniteHorizontalNavRail
    :entries="navigationGroups"
    :active-key="activeMenuKey"
    :storage-key="scrollHintStorageKey"
    @select="openMenu"
/>
```

### Resource Index Vue

**Name:** Resource Index Vue
**Type:** UI Component Abstraction
**Location:**
- `docs/architecture/ui/ResourceIndexVue.yaml`
- `resources/js/components/ResourceIndex.vue`

**Purpose:**
Provide the Inertia/Vue equivalent of the existing Blade configured resource index shell while preserving backend-owned resource contracts.

**When to Use:**
Building an Inertia/Vue CRUD resource index page, reusing the standard resource index toolbar, bounded height shell, search field, toolbar actions, and scroll-contained records pane.

**When Not to Use:**
Unmigrated Blade/Alpine resource index pages, resource detail pages, or domain-specific create/import/export/validation/authorization behavior.

**Public Interface:**
- `labels`
- `permissions`
- `records`
- `search`
- `loading`
- `error`
- `boundedHeightClass`
- `ResourceCardGrid` `title`
- `ResourceCardGrid` `titleAside`
- `update:search`
- `search`
- `create`
- `import`
- `export`
- default/empty/loading/error slots
- toolbar action slots

**Behavior Contract:**
ResourceIndex owns only the generic shell and toolbar interaction surface. Records render through slots, data arrives through props, user intent leaves through events, and the records pane remains the only scrollable region.

**Example Usage:**
```vue
<ResourceIndex
    v-model:search="search"
    :records="records"
    :labels="resource.labels"
    :permissions="resource.permissions"
    :loading="loading"
    :error="error"
    @search="fetchRecords"
    @create="openCreatePanel"
>
    <template #default="{ records }">
        <ResourceCards :records="records" />
    </template>
</ResourceIndex>
```

### Base Dropdown

**Name:** Base Dropdown
**Type:** UI Component Abstraction
**Location:**
- `docs/architecture/ui/BaseDropdown.yaml`
- `resources/js/components/BaseDropdown.vue`

**Purpose:**
Provide one shared Vue dropdown boundary for trigger rendering, teleported menu positioning, outside-click closing, Escape closing, and viewport-change closing.

**When to Use:**
Compact Inertia/Vue action menus, especially vertical-dots row actions in Vue resource cards or lists.

**When Not to Use:**
Unmigrated Blade/Alpine dropdowns, drawers, slide-up panels, centered dialogs, or domain-specific action orchestration.

**Public Interface:**
- `ariaLabel`
- `align`
- `buttonClass`
- `menuClass`
- `offset`
- trigger slot
- default slot

**Behavior Contract:**
BaseDropdown owns only local dropdown visibility, fixed teleported menu positioning, outside-click close, Escape close, and viewport-change close. Menu actions remain page-owned and should call the slot-provided `close` callback when selection should collapse the dropdown.

**Layer Contract:**
Dropdown menus use the shared app-menu layer and render above drawer overlays when both are present.

**Example Usage:**
```vue
<BaseDropdown aria-label="Customer actions">
    <template #trigger>
        <VerticalDotsIcon />
    </template>

    <template #default="{ close }">
        <button type="button" role="menuitem" @click="close(); edit(record)">
            Edit
        </button>
    </template>
</BaseDropdown>
```

### Resource Index Drawer Vue

**Name:** Resource Index Drawer Vue
**Type:** UI Component Abstraction
**Location:**
- `docs/architecture/ui/ResourceIndexDrawerVue.yaml`
- `resources/js/components/ResourceCreateDrawer.vue`
- `resources/js/components/ResourceExportDrawer.vue`
- `resources/js/components/ResourceImportDrawer.vue`

**Purpose:**
Provide Vue drawer dependencies for Inertia resource index pages while preserving backend-owned resource contracts and page-owned domain behavior.

**When to Use:**
Building create, export, or import drawers for an Inertia/Vue resource index page, or migrating configured Blade CRUD index dependencies to Vue one route at a time.

**When Not to Use:**
Unmigrated Blade/Alpine resource index pages, resource detail drawers, or domain-specific field validation/import/export/persistence behavior.

**Public Interface:**
- `ResourceCreateDrawer`
- `ResourceExportDrawer`
- `ResourceImportDrawer`
- `open`
- `labels`
- `submitting`
- `error/errors`
- `close`
- `submit`
- slots for default content, footer, error, bulk options, preview rows, and preview states
- `showBulkOptions`
- `toggle-row-selection`
- `toggle-visible-selection`

**Behavior Contract:**
The drawer components compose `BaseDrawer`, inherit the standard 500ms slide/fade transition, receive state through props, and emit user intent through events. Resource-specific form fields, import row rendering, export URL authority, submit payloads, authorization, validation, and persistence remain owned by the page component and backend.

**Example Usage:**
```vue
<ResourceCreateDrawer
    :open="createOpen"
    title="Add Material"
    :submitting="submitting"
    :error="generalError"
    @close="closeCreate"
    @submit="submitCreate"
>
    <MaterialFields v-model="form" :errors="errors" />
</ResourceCreateDrawer>
```

### Reusable CRUD Detail Section Pattern

**Name:** Reusable CRUD Detail Section Pattern
**Type:** UI Architectural Pattern
**Location:**
- `resources/js/lib/js-crud-section.js`
- `resources/js/pages/materials-show.js`
- `resources/js/pages/purchasing-suppliers-show.js`
- `resources/js/pages/inventory-count-show.js`
- `resources/views/materials/show.blade.php`
- `resources/views/purchasing/suppliers/show.blade.php`
- `resources/views/inventory/counts/show.blade.php`

**Purpose:**
Provide a shared expandable detail-section CRUD surface for record sublists such as supplier packages, purchase orders, and inventory count materials.

**When to Use:**
- Detail pages that manage a scoped child-record collection with list/create/update/delete behavior
- Row-action menus that must escape beyond the section/card boundary

**When Not to Use:**
- Index pages already covered by the configured CRUD page module
- One-off embedded forms without list state

**Public Interface:**
- `data-js-crud-section-root`
- `data-section-key`
- `mountCrudSection(rootEl, { section, adapters })`

**Example Usage:**
```html
<div data-js-crud-section-root data-section-key="countLines"></div>
<div data-js-crud-section-root data-section-key="tasks"></div>
```

Notes:
- Reusable CRUD detail sections must keep their outer shell `overflow-visible` so row-action dropdowns are not clipped.
- Shared CRUD add/search rows use a dedicated layout contract rather than the generic actions slot: the search/select control belongs on the left and the add button belongs on the right.
- Shared compact add/search rows do not render a visible field label unless a future screen explicitly opts into one.
- Shared CRUD detail sections default to 5 rows per page through the section pagination contract; endpoints should honor `per_page` when list data is loaded remotely, while future per-page selectors can opt in through the same config.
- Inventory Count detail uses this pattern with a `Materials` section and a read-only `Tasks` section that reuses the existing task completion route/payload contract.
- Material detail uses this pattern for `Supplier Packages`, `Recipes`, `Purchase Orders`, and `Make Orders`; `Supplier Packages` and `Recipes` render near the top and default open, while `Purchase Orders` and `Make Orders` render near the bottom and default collapsed.
- Supplier detail uses this pattern for `Supplier Packages`, scoped to the current supplier. The create slide-over selects the Material while the supplier is fixed by the page context, and row actions use the shared vertical dots menu for `Edit`, `Purchase`, and `Delete`.
- Supplier detail uses this pattern for `Purchase Orders`, scoped to purchase orders whose `supplier_id` matches the current supplier. Its `+` action posts to the existing Purchase Order create endpoint with the supplier fixed by page context and redirects to the created draft PO detail page.
- Material detail reuses the existing section abstraction for manufacturable-only `Recipes` and `Make Orders` sections rather than introducing a bespoke accordion/detail implementation.
- Material detail section rows expose record detail links where an existing detail surface is available, using the shared row-action `View` contract rather than bespoke row-click behavior; this applies to Purchase Order rows, Recipe rows, and Make Order rows.
- Material detail `Supplier Packages` rows do not expose `View` or `Edit`; their `Purchase` action posts directly to the material-scoped draft purchase-order endpoint, creates a one-pack PO line from the selected supplier package, and redirects to the new Purchase Order detail page.
- Material detail `Recipes` plus opens the existing recipe create slide-over in place, prefilled with the current material as the output item, and successful recipe create redirects to the created recipe detail page.
- Material detail recipe-row `Make` creates a draft Make Order directly from the selected recipe current version and redirects to the created make-order detail page when `show_url` is returned.
- Material detail `Inventory Counts` `+` reuses the standard Inventory Count create slide-over contract and mounts that shared form under a dedicated live Alpine create component in the resource-detail overlay area inside the Material page scope.
- The Material detail page module must open that mounted create component directly so the same `showCountForm` state bound by the shared Inventory Count slide-over is toggled by the `+` action.
- The Material detail Inventory Counts section `createAction` must pass a concrete handler config object into the page adapter, using `handlerKey: openInventoryCountCreate`; the adapter must not assume `action` exists when the shared section fails to pass it.
- Recipe and recipe-version dropdown or header `Make` actions follow the same direct-create-and-redirect contract instead of opening a dedicated Make Order slide-over.
- Recipe detail uses the same reusable CRUD detail-section pattern for a `Make Orders` accordion scoped to one recipe; the right-aligned shared `+` action now posts directly to the recipe-scoped create endpoint, create always uses the recipe current published version, and checked-out drafts are never used.
- Recipe detail `Make Orders` create eligibility is reactive: publishing the first or current recipe version updates the shared section action contract immediately so the right-aligned `+` appears without a full page refresh.
- Recipe detail `Versions` rows follow the shared row-action dropdown contract; draft rows expose `Publish`, and publish success may trigger a temporary row highlight when the newly current version re-sorts to the top.
- Recipe detail `Versions` toolbar toggles use the shared compact switch contract in the reusable detail-section renderer, with left-aligned label text and a right-aligned pill track rather than a visible native checkbox.
- Material detail supplier-package create success must refresh the page-local Purchase Order create slide-over supplier/package option data so newly created packages can be selected without a full page reload.
- Recipe detail header owns one active-version status or action menu, using a Tailwind-only split-button style through the shared header action slot rather than rendering one status-action button in every Version row.
- That header split-button must show the active version caption visibly in the closed state; icon-only status or action controls are not acceptable.
- Recipe detail header renders `Version {x.xx}` beside that split-button in the right-side action area and must not keep a stale standalone version chip in the left metadata group.
- Recipe detail closed header captions use `Checked-Out` for the current user's checked-out active version, otherwise `Draft`, `Published`, or `Archived` from the active version lifecycle.
- Recipe detail active-version identity uses the current user's checked-out version first, otherwise `recipes.current_version_id`, otherwise the most recent version, and the header plus Ingredients section should read from that same resolved display-version identity.
- Header active-version menu options mirror the active Version row's valid actions from the same shared action builder source, and publish from that header menu refreshes Make Orders create eligibility immediately.
- Header active-version menu helper descriptions also come from that shared action catalog so row-equivalent action intent does not drift.
- Publishing a new recipe version archives all previously published versions for that same recipe before moving `recipes.current_version_id`.
- Publishing an older recipe draft first promotes its version number to the next highest available recipe version number before it becomes current.
- Recipe detail section order is `Make Orders`, `Ingredients`, then `Versions`; `Make Orders` defaults open while `Ingredients` and `Versions` default collapsed.
- Recipe detail `Make Orders` may opt into a shared mobile page-size contract so the same reusable section renders the latest `3` rows first on mobile while retaining shared pagination controls for remaining rows.
- Recipe detail `Make Orders` rows may reuse the shared badges row to show both workflow state and a compact `v{x.xx}` recipe-version snapshot badge on the same line.
- Recipes index row menus expose `Make` when the recipe has a current published manufacturing version and the current user can execute Make Orders.
- Make Orders index primary recipe-name rows use the shared linked-text CRUD contract to open the Make Order detail page.
- Make Order detail uses the shared resource detail header pattern plus `Details`, `Tasks`, and `Ingredients` detail sections in that order.
- The Make Order `Details` section keeps `Runs`, `Expected Output`, `Actual Output`, `Due Date`, and `Assigned To` together in one compact responsive grid; `Runs` come from `make_orders.runs`, expected output is persisted on `make_orders.expected_output_qty`, and actual output stays separate as `make_orders.actual_output_qty` when present.
- Editing `Runs` must recalculate `expected_output_qty` immediately from `runs × recipe_version.output_quantity`, and stale client-submitted expected-output values must be ignored.
- The Make Order `Expected Output` field may render read-only in the detail UI because it is a derived planning quantity, while `Actual Output`, `Due Date`, and `Assigned To` remain editable.
- `Runs` should render compactly without unnecessary trailing scale-6 decimals in the detail UI, while Expected Output and Actual Output should render using the output item base UOM display precision.
- On mobile, `Runs` / `Expected Output` / `Actual Output` stay on one compact row and `Due Date` / `Assigned To` stay on a second compact row.
- Recipe and Recipe Version `output_quantity` remain the per-run output contract and must not be reinterpreted as Make Order runs.
- Shared workflow-enabled resource headers may expose one compact next-stage action through the shared header action slot when a valid configured transition exists.
- Shared workflow-enabled resource headers may expose one compact draft-entry action through the same header action slot when a pre-workflow draft can enter the first configured stage.
- Shared resource headers may render grouped metadata rows through the shared header metadata slot; Make Orders use recipe/runs on the first row and output item/expected output on the second row.
- Shared resource headers may render a compact visible-state chip beside the title when the resource uses a workflow-stage-driven status surface.
- Make Order visible state is `DRAFT` while `workflow_stage_id` is null, then tenant-configured `workflow_stages.name` after workflow entry; lifecycle `status` remains lifecycle-only while `workflow_stage_id` tracks the operational workflow position.
- Make Order detail keeps that visible state beside the `Make Order {id}` title rather than repeating it in the compact metadata rows.
- Make Order workflow ownership metadata lives on `make_orders.made_by_user_id`, auto-assigns to the creator on Make Order creation, and is edited from the Details section through the shared dropdown-select pattern.
- `make_orders.assigned_to_user_id` is not part of the Make Order schema and must not be used for Make Order ownership.
- Make Order due date is edited inline from the Details section through a compact autosave date input.
- Make Order assignee options are tenant-scoped, include an explicit `Unassigned` choice, autosave on change, and reject cross-tenant assignment.
- Make Order due-date changes must not mutate lifecycle `status`, operational `workflow_stage_id`, `make_order_lines`, `recipe_version_lines`, workflow tasks, or task templates.
- Make Order assignee changes must not mutate lifecycle `status`, operational `workflow_stage_id`, `make_order_lines`, `recipe_version_lines`, workflow tasks, or task templates.
- Make Order stage transitions must preserve `made_by_user_id`.
- Make Orders may optionally persist a nullable `actual_output_qty` at make time; when present it is the completion-truth output quantity and overrides expected produced quantity for the output receipt stock move only.
- When `actual_output_qty` is absent, completed output still falls back to `expected_output_qty`.
- `actual_output_qty` does not introduce actual ingredient-usage tracking in this phase.
- Generated Make Order workflow task assignees remain independent from Make Order assignee metadata.
- Shared detail-section UX should prefer compact inline autosave controls for safe single-field updates and avoid extra save-button rows.
- Shared detail sections should not duplicate stage-movement controls already owned by the shared header workflow action.
- Recipe-scoped Make Order create may redirect directly to the created Make Order detail page through the shared section create-success hook when `show_url` is returned.
- Make Order header, index, Details section, and shared Material/Recipe detail rows all use the same workflow-stage-driven display rule after workflow entry.
- Make Order detail and workflow transition endpoints seed default manufacturing stages only when the tenant has no configured manufacturing workflow stages; existing configured stages remain authoritative.
- Reusable detail sections may disable the vertical-dots row menu through `showRowActionsMenu: false`; the default remains enabled for existing section consumers, and disabled sections may surface their configured row actions inline instead.
- Make Order detail ingredient rows may opt out of the row-actions menu and render a direct inline `x-mark` remove button when removal is the only row-level action exposed there.
- Make Orders index rows may opt out of the row-actions menu and render a direct inline `x-mark` archive button when archive is the only row-level action exposed there.
- Both of those direct row actions remain AJAX-first, update the UI immediately without a full-page refresh, and show feedback only on failure.

### Dashboard Todo Section

**Name:** Dashboard Todo Section
**Type:** UI / Read Model Pattern
**Location:**
- `app/Actions/Workflows/CanViewAssignedWorkflowResourceAction.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Support/Workflows/WorkflowAssignmentPermissions.php`
- `resources/js/pages/Dashboard.vue`
- `resources/js/components/DashboardTodo.vue`
- `tests/Feature/DashboardTodoTest.php`

**Purpose:**
Surface assigned workflow responsibilities and generated workflow-stage tasks on the authenticated dashboard without introducing a standalone tasks module.

**When to Use:**
Fixed dashboard visibility for existing assigned work that already has a safe resource detail route and a matching permission Gate.

Assignment controls for workflow-owned records or generated workflow tasks should use the shared workflow assignment permission map so users are only assigned work they have the right workflow credential to perform.

Resource detail Gates/policies remain the source of truth. Assigned workflow resource visibility is granted through `CanViewAssignedWorkflowResourceAction` when the current user is directly assigned to the resource or assigned to a generated workflow-stage task on that resource.

Dashboard Todo only surfaces open work. Workflow responsibilities include assigned make orders, inventory counts, and purchase orders while excluding completed, posted, made, and cancelled resources; stage task rows require an open task and a non-terminal related resource. Todo rows show the workflow domain name separately from the resource label. Workflow responsibility badges show the current stage name, or `Draft` when the workflow has not entered a stage; task badges show the task status.

**When Not to Use:**
Dashboard customization, draggable widgets, ad hoc task creation, task completion controls, or new task routes.

**Public Interface:**
- Dashboard route `/dashboard`
- `Todo` detail-section accordion
- `CanViewAssignedWorkflowResourceAction::execute()`
- `WorkflowAssignmentPermissions::eligibleUsersQuery()`
- Existing resource detail route links

**Example Usage:**
```php
// Assigned Make Orders use the documented Make Order ownership field.
MakeOrder::query()->where('made_by_user_id', auth()->id());
```

### Notes Activity Feed

**Name:** Notes Activity Feed
**Type:** UI / Domain Pattern
**Location:**
- `app/Actions/Notes/AuthorizeNoteableAccessAction.php`
- `app/Actions/Notes/BuildNotesFeedPayloadAction.php`
- `app/Http/Controllers/NoteController.php`
- `app/Models/Concerns/HasNotes.php`
- `app/Models/Note.php`
- `resources/views/components/notes-feed.blade.php`
- `resources/js/components/notes-feed.js`
- `docs/architecture/ui/NotesActivityFeed.yaml`

**Purpose:**
Provide a reusable tenant-scoped Activity & Notes timeline for explicitly supported resource detail pages.

**When to Use:**
Internal plain-text comments on supported tenant-owned resource detail pages where parent-resource authorization decides note visibility and creation.

**When Not to Use:**
Customer-facing notes, attachments, rich text, mood pickers, mentions, editing, deletion, or resources that have not explicitly opted into `HasNotes`.

**Public Interface:**
- `HasNotes::notes()`
- Resource note index/store routes
- `<x-notes-feed :config="$payload['notesFeed']" />`
- `notesFeed` Alpine component

**Example Usage:**
```php
$payload['notesFeed'] = app(BuildNotesFeedPayloadAction::class)->execute(
    $inventoryCount,
    route('inventory.counts.notes.index', $inventoryCount),
    route('inventory.counts.notes.store', $inventoryCount)
);
```

### Shared Ingredients Detail Section Pattern

**Name:** Shared Ingredients Detail Section Pattern
**Type:** Manufacturing UI Pattern
**Location:**
- `resources/views/components/ingredients-detail-section.blade.php`
- `resources/views/manufacturing/recipes/show.blade.php`
- `resources/views/manufacturing/make-orders/show.blade.php`

**Purpose:**
Keep Recipe Ingredients and Make Order Ingredients visually and behaviorally consistent through one shared detail-section abstraction.

**When to Use:**
- Rendering ingredient lines on Recipe detail
- Rendering ingredient snapshot lines on Make Order detail

**When Not to Use:**
- Non-ingredient child collections
- Resource index pages

Notes:
- The shared Ingredients section composes the reusable detail-section shell rather than reimplementing accordion markup.
- The compact combobox plus plus-button add bar is shared between Recipe and Make Order detail.
- The shared Ingredients add row inherits the shared detail-section add-row contract so the search/select control stays left-aligned and the add button stays right-aligned.
- The shared compact Ingredients add row intentionally omits a visible field label.
- The shared section preserves dropdown and row-action overflow visibility.
- Recipe Ingredients persist to `recipe_version_lines`.
- Make Order Ingredients persist to `make_order_lines`.
- Recipe Ingredients stay version-aware and edit only through the checked-out draft context.
- Make Order Ingredients are editable snapshots and must not mutate recipe version lines.
- Make Order Details uses the shared detail-section shell, defaults open, and keeps runs, expected output, actual output, and workflow metadata separate from lifecycle status.

### Make Order Details Section Pattern

**Name:** Make Order Details Section Pattern
**Type:** Manufacturing UI Pattern
**Location:**
- `docs/architecture/manufacturing/MakeOrderWorkflowSection.yaml`
- `app/Http/Controllers/MakeOrderController.php`
- `app/Services/Workflows/MakeOrderWorkflow.php`
- `resources/views/manufacturing/make-orders/show.blade.php`
- `resources/js/pages/manufacturing-make-orders-show.js`

**Purpose:**
Render compact Runs, Expected Output, Actual Output, Due Date, and Assigned To detail on Make Order detail while keeping workflow-stage movement in the shared header action and current-stage tasks in a separate detail section.

**When to Use:**
- Showing Make Order runs, expected output, and actual output separately
- Editing Make Order due date
- Editing Make Order workflow ownership assignment
- Surfacing the shared header workflow transition action for a Make Order

**When Not to Use:**
- Recipe versioning
- Lifecycle status transitions such as making or cancelling

**Public Interface:**
- `manufacturing.make-orders.due-date.update`
- `manufacturing.make-orders.workflow-stage.update`
- `MakeOrderWorkflow::moveToStage()`
- `MakeOrderWorkflow::availableTransitions()`
- `MakeOrderWorkflow::responsePayload()`

### Resource Detail Layout Pattern

**Name:** Resource Detail Layout Pattern
**Type:** UI Architectural Pattern
**Location:**
- [docs/architecture/ui/ResourceDetailLayoutPattern.yaml](docs/architecture/ui/ResourceDetailLayoutPattern.yaml)

**Purpose:**
Provide a shared resource-detail shell where top navigation and the page header remain sticky while only the detail content pane scrolls.

**Rules:**
- Resource detail pages that need sticky shell behavior must use `x-resource-detail-layout` rather than duplicating page-local sticky wrappers.
- The top navigation remains sticky above the page header.
- The page header remains sticky beneath the navigation bar.
- The detail content pane is the only scrollable region owned by the resource detail shell.
- Sticky shell behavior remains opt-in through the shared app layout and does not become the default for non-detail pages.
- Resource detail pages keep sticky chrome with a scrollable content area; top navigation and page header remain visible while only the detail content pane scrolls.
- Slide-over overlays used by resource detail pages must render outside the detail scroll container so sticky chrome and overflow contexts do not clip the backdrop or panel.
- Slide-over overlays used by resource detail pages must appear above sticky navigation and page headers.
- Resource-detail slide-over backdrops cover the viewport and support backdrop or outside-click close behavior.

### Workflow Stage Inventory Effect Invariant

**Name:** Workflow Stage Inventory Effect Invariant
**Type:** Workflow Configuration Invariant
**Location:**
- `docs/architecture/workflows/WorkflowStageInventoryEffectInvariant.yaml`
- `app/Actions/Workflows/EnforceWorkflowStageInventoryEffectInvariantAction.php`
- `database/migrations/2026_05_20_000001_add_is_inventory_effect_stage_to_workflow_stages_table.php`

**Purpose:**
Allow workflow stages to identify which stock-impacting operational stage owns inventory posting while preventing workflow admin from leaving stock-impacting workflow domains without exactly one active inventory-effect stage.

**When to Use:**
- Stock workflow-stage admin configuration
- Sales runtime inventory-effect stage resolution

**When Not to Use:**
- Purchase-order runtime receiving
- Make-order runtime execution
- Inventory-count runtime posting

**Public Interface:**
- `EnforceWorkflowStageInventoryEffectInvariantAction::normalizeAndAssert()`
- `WorkflowStage::$is_inventory_effect_stage`

**Example Usage:**
```php
$stage->is_inventory_effect_stage = true;
```

Notes:
- The fixed workflow domains are `sales`, `purchasing`, `manufacturing`, and `inventory`.
- Default seeded stock-impacting stage sets are tenant-scoped and idempotent.
- Default Sales inventory impact is assigned to the seeded `Packing` stage, which completes to `PACKED`; there is no separate default `Packed` workflow stage.
- Sales, Purchasing, Manufacturing, and Inventory each enforce exactly one active inventory-effect stage when stage defaults or admin edits touch the domain.

### Workflow Definition

**Name:** Workflow Definition
**Type:** Runtime Read Model
**Location:**
- `docs/architecture/workflows/WorkflowDefinition.yaml`
- `app/Providers/AppServiceProvider.php`
- `app/Support/Workflows/WorkflowDefinition.php`
- `app/Support/Workflows/WorkflowDefinitionRepository.php`

**Purpose:**
Load one tenant/domain workflow configuration once per request for runtime stage lookup without mutating default workflow configuration.

**When to Use:**
- Resolving current, previous, next, and target stages during workflow runtime
- Building workflow response payloads after a transition

**When Not to Use:**
- Seeding workflow defaults
- Repairing workflow configuration

**Public Interface:**
- `AppServiceProvider` scoped `WorkflowDefinitionRepository` binding
- `WorkflowDefinitionRepository::for()`
- `WorkflowDefinition::activeStages()`
- `WorkflowDefinition::stageByStatus()`
- `WorkflowDefinition::progressSteps()`

**Example Usage:**
```php
$definition = $definitions->for($tenantId, 'sales');
$nextStage = $definition->nextStageAfter($currentStage);
```

### Base Workflow

**Name:** Base Workflow
**Type:** Template Method Service
**Location:**
- `docs/architecture/workflows/BaseWorkflow.yaml`
- `app/Contracts/Workflows/Workflowable.php`
- `app/Services/Workflows/BaseWorkflow.php`
- `app/Services/Workflows/MakeOrderWorkflow.php`
- `app/Services/Workflows/PurchaseOrderWorkflow.php`
- `app/Services/Workflows/SalesOrderWorkflow.php`

**Purpose:**
Provide one shared workflow transition algorithm while domain workflow services supply authorization, validation, and side effects.

**When to Use:**
- Implementing workflow transitions for a domain
- Moving transition orchestration out of controllers

**When Not to Use:**
- Workflow admin CRUD
- Passive progress rendering without mutation

**Public Interface:**
- `Workflowable`
- `BaseWorkflow::transition()`
- `MakeOrderWorkflow::moveToStage()`
- `MakeOrderWorkflow::responsePayload()`
- `PurchaseOrderWorkflow::completeStage()`
- `PurchaseOrderWorkflow::cancel()`
- `PurchaseOrderWorkflow::responsePayload()`
- `SalesOrderWorkflow::transition()`
- `SalesOrderWorkflow::responsePayload()`

**Example Usage:**
```php
$salesOrder = $salesOrderWorkflow->transition($salesOrder, $targetStatus);
```

### Workflow Stage Task Gating

**Name:** Workflow Stage Task Gating
**Type:** Workflow Domain Rule
**Location:**
- `docs/architecture/workflows/WorkflowStageTaskGating.yaml`
- `app/Actions/Workflows/AssertWorkflowStageTasksCompletedAction.php`
- `app/Actions/Workflows/GenerateWorkflowStageTasksAction.php`
- `app/Services/Workflows/InventoryCountWorkflow.php`

**Purpose:**
Apply one shared stage-entry task generation and stage-exit blocking rule across adopted workflow domains.

**When to Use:**
- Sales-order stage transitions
- Inventory-count stage transitions
- Workflow-context manual tasks that should display beside generated tasks but not block transitions

**When Not to Use:**
- Draft setup outside workflow stages
- Manual task transition blocking

**Public Interface:**
- `AssertWorkflowStageTasksCompletedAction::execute()`
- `GenerateWorkflowStageTasksAction::execute()`

**Example Usage:**
```php
$generateWorkflowStageTasksAction->execute($tenantId, $recordId, $stage, $preferredAssigneeUserId);
```

---

### User Auth Identity Safety

**Name:** User Auth Identity Safety
**Type:** Architectural Rule
**Location:** `app/Models/User.php`

**Purpose:**
Keep authentication and identity resolution independent from tenant scoping.

**When to Use:**
Authentication and identity lookup.

**When Not to Use:**
Tenant-owned domain data queries.

**Public Interface:**
- `User::query()`

**Example Usage:**
```php
$user = User::where('email', $email)->first();
```

---

### Manufacturing Recipes Tenant Isolation

**Name:** Manufacturing Recipes Tenant Isolation
**Type:** Tenancy Rule
**Location:**
- `docs/architecture/tenancy/ManufacturingRecipesTenantIsolation.yaml`
- `app/Models/Recipe.php`
- `app/Models/RecipeLine.php`

**Purpose:**
Ensure recipe queries are tenant-scoped and cross-tenant access results in 404s.

**When to Use:**
Recipe index/show queries and route model binding.

**When Not to Use:**
Auth identity resolution or global/system models.

**Public Interface:**
- `use HasTenantScope`
- `Recipe::query()`
- `RecipeLine::query()`

**Example Usage:**
```php
$recipe = Recipe::query()->findOrFail($id);
```

---

### Recipe Output Eligibility

**Name:** Recipe Output Eligibility
**Type:** Domain Rule
**Location:**
- `docs/architecture/manufacturing/RecipeReadModel.yaml`
- `docs/architecture/inventory/ExecuteRecipeAction.yaml`
- `app/Http/Controllers/RecipeController.php`
- `app/Models/Recipe.php`

**Purpose:**
Constrain which normal `items` may be used as recipe outputs and which `recipe_type` values each item supports.

**When to Use:**
Recipe creation, recipe updates, recipe output pickers, and manufacturing execution gating.

**When Not to Use:**
Generic item listing, purchasing rules, or sales import filtering unrelated to recipes.

**Public Interface:**
- `Recipe::recipeTypeEligibilityError(Item $item, ?string $recipeType)`
- recipe output candidate payload from `RecipeController`

**Example Usage:**
```php
$error = Recipe::recipeTypeEligibilityError($item, $recipeType);
```

Notes:
- Output candidates are normal `items` where `is_manufacturable = true` or `is_sellable = true`.
- `manufacturing` recipes require `is_manufacturable = true`.
- `fulfillment` recipes require `is_sellable = true`.
- Items with both flags may use both recipe types.
- Items with neither flag are excluded from recipe output pickers and rejected server-side.

---

### Item Stockability / Inventory Tracking Eligibility

**Name:** Item Stockability / Inventory Tracking Eligibility
**Type:** Domain Rule
**Location:**
- `docs/architecture/inventory/Item.yaml`
- `docs/architecture/inventory/StockMove.yaml`
- `app/Models/Item.php`
- `database/migrations/2026_05_25_000001_add_is_stockable_to_items_table.php`

**Purpose:**
Separate general item participation in business workflows from stock-ledger participation.

**When to Use:**
Any purchasing, manufacturing, sales, or counting workflow that might otherwise create stock moves.

**When Not to Use:**
Permission checks, recipe output eligibility, or generic item naming/UoM concerns.

**Public Interface:**
- `items.is_stockable`
- `Item::$casts['is_stockable']`

**Example Usage:**
```php
if ($item->is_stockable) {
    // create stock move
}
```

Notes:
- `is_stockable` defaults `false`.
- Non-stockable items may still be purchased, received, sold, manufactured, counted, or used in recipes.
- Those workflows must skip stock-move creation for non-stockable items instead of blocking the workflow.
- On-hand inventory is meaningful only for stockable items because it is derived from the stock-move ledger.

---

### Item External Import Identity

**Name:** Item External Import Identity
**Type:** Domain Import Rule
**Location:**
- `docs/architecture/inventory/Item.yaml`
- `app/Http/Controllers/SalesProductController.php`
- `app/Http/Requests/Sales/ImportExternalProductsRequest.php`
- `app/Services/WordPressPluginProductPreviewService.php`
- `integrations/wordpress/foomake-connector/foomake-connector.php`

**Purpose:**
Define how imported products use tenant-scoped external identity for duplicate preview, existing-item matching, and fulfillment-safe ecommerce imports.

**When to Use:**
Sales product preview/import flows that read or write `external_source` and `external_id`.
WooCommerce product preview may use stored API credentials or a paired WordPress plugin connection.

**When Not to Use:**
Internal items without an external identity, or generic item CRUD unrelated to import behavior.

**Public Interface:**
- `external_source`
- `external_id`
- `default_price_currency_code`
- preview duplicate metadata on import rows
- fulfillment import summary field `fulfillment_recipes_not_attempted_existing_item`

**Example Usage:**
```php
$existing = Item::query()
    ->where('tenant_id', $tenantId)
    ->whereRaw('LOWER(TRIM(external_source)) = ?', ['woocommerce'])
    ->whereRaw('TRIM(external_id) = ?', ['101'])
    ->first();
```

Notes:
- Duplicate identity is tenant-scoped normalized `external_source` plus exact trimmed `external_id`.
- Preview rows may be marked duplicate and excluded from default selection before import.
- Ecommerce imports may update an existing matched item and still return the existing fulfillment import summary contract rather than failing the whole request.
- Priced external rows should preserve source-provided `default_price_currency_code`; rows without currency fall back to the tenant/app currency.

---

### Tenant

**Name:** Tenant
**Type:** Eloquent Model
**Location:** `app/Models/Tenant.php`

**Purpose:**
Represent a tenant in a single-database, multi-tenant architecture.

**When to Use:**
Associating users and data with a tenant.

**When Not to Use:**
Global/system configuration unrelated to a tenant.

**Public Interface:**
- `users()`

**Example Usage:**
```php
$tenant = Tenant::create(['tenant_name' => 'Acme Foods']);
$users = $tenant->users;
```

---

## Authorization

### Domain Authorization Layer

**Name:** Domain Authorization Layer
**Type:** Authorization Pattern (Laravel Gates)
**Location:** `app/Providers/AuthServiceProvider.php`, `bootstrap/app.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `resources/js/pages/Home.vue`

**Purpose:**
Centralize authorization using permission slugs and Laravel Gates.

**When to Use:**
Any access control decision.

**When Not to Use:**
UI-only visibility decisions without backend enforcement.

**Public Interface:**
- `Gate::allows()`
- `Gate::authorize()`
- `/?auth=login`

Notes:
- Browser unauthenticated and expired-session reauth redirects open the public home login drawer instead of sending users to `/login`.
- JSON/API callers keep JSON unauthenticated or expired-session responses.

**Example Usage:**
```php
Gate::authorize('inventory-materials-manage');
```

---

### Workflow Manage Permission

**Name:** Workflow Manage Permission
**Type:** Authorization Rule
**Location:**
- `docs/PERMISSIONS_MATRIX.md`
- `docs/PR3_ROADMAP.md`

**Purpose:**
Document the gate that controls workflow-configuration access from the profile dropdown `Connectors -> Workflows` entry.

**When to Use:**
Workflow stage and workflow task-template configuration surfaces.

**When Not to Use:**
Assigned-user task completion or existing sales-order lifecycle transitions that retain their current permissions.

**Public Interface:**
- `workflow-manage`

**Example Usage:**
```php
Gate::authorize('workflow-manage');
```

---

### Tenant User Invitation Pattern

**Name:** Tenant User Invitation Pattern
**Type:** Auth / Tenancy Pattern
**Location:**
- `docs/architecture/auth/TenantUserInvitation.yaml`
- `app/Models/TenantUserInvitation.php`
- `app/Http/Controllers/Admin/UserManagementController.php`
- `app/Http/Controllers/Auth/InvitedRegisteredUserController.php`
- `database/migrations/2026_05_31_000001_create_tenant_user_invitations_table.php`

**Purpose:**
Allow tenant admins to invite users into their tenant while normal self-registration keeps tenant creation and email verification behavior.

**When to Use:**
Tenant admin member invitation and invited registration flows.

**When Not to Use:**
Normal self-registration, password reset, login, or cross-tenant system administration.

**Public Interface:**
- `admin-users-view`
- `admin-users-manage`
- `TenantUserInvitation::findByPlainToken()`
- `admin.users.*` routes
- `invitations.register.*` routes

**Example Usage:**
```php
$invitation = TenantUserInvitation::findByPlainToken($token);
```

Notes:
- Invitation URL tokens are stored as hashes.
- Invitation emails link to the guest `/invitations/{token}/register` token route, not admin invitation-management routes.
- Revoked invitations cannot be accepted.
- Invited registration attaches or completes the user in the invitation tenant, verifies the invited email, and assigns the selected role.
- Normal registration remains responsible for creating a tenant and first admin user, then requires email verification before protected app access.

---

## UI

### Configured CRUD Page Module Pattern

**Name:** Configured CRUD Page Module Pattern
**Type:** UI Architectural Pattern
**Location:**
- `docs/architecture/ui/ConfiguredCrudPageModulePattern.yaml`
- `resources/js/lib/crud-config.js`
- `resources/js/lib/crud-card-page.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/js/pages/Sales/Orders/Index.vue`
- `resources/js/pages/sales-customers-index.js`
- `resources/js/pages/purchasing-suppliers-index.js`

**Purpose:**
Provide a shared config-driven CRUD page shell where toolbar actions, card-grid rendering, and common AJAX behavior are owned by a reusable renderer rather than resource-specific Blade markup.

**When to Use:**
Interactive CRUD index pages that can express their card list, toolbar actions, and row rendering contract through server-generated config.

**When Not to Use:**
Static pages or workflows that cannot fit the shared CRUD action and rendering contract.

**Public Interface:**
- `data-crud-config`
- `endpoints.list`
- `endpoints.create`
- `endpoints.importPreview`
- `endpoints.importStore`
- `endpoints.export` when export is enabled
- `permissions.showImport`
- `permissions.showExport`
- `permissions.showCreate`

**Example Usage:**
```php
$crudConfig = [
    'resource' => 'products',
    'endpoints' => [
        'list' => route('sales.products.list'),
        'export' => route('sales.products.export'),
        'create' => route('sales.products.store'),
        'importPreview' => route('sales.products.import.preview'),
        'importStore' => route('sales.products.import.store'),
    ],
    'permissions' => [
        'showExport' => true,
        'showImport' => true,
        'showCreate' => true,
    ],
];
```

Notes:
- Shared toolbar actions such as `Export`, `Import`, and `Add` must be driven by CRUD config and rendered by the shared CRUD page module, not hardcoded per resource in Blade.

### Configured CRUD Card Page Renderer

**Name:** Configured CRUD Card Page Renderer
**Type:** UI Architectural Pattern
**Location:**
- `docs/architecture/ui/ConfiguredCrudCardPageRenderer.yaml`
- `resources/js/lib/crud-card-page.js`
- `resources/js/pages/materials-index.js`

**Purpose:**
Render configured CRUD index records as responsive desktop Tailwind cards or an opt-in stacked list while preserving the shared CRUD toolbar, search, action, toggle, and mount-shell contracts.

**When to Use:**
Configured CRUD index pages where card summaries or compact stacked rows communicate the resource better than dense table columns.

**When Not to Use:**
Dense list pages that need table comparison or reusable detail-section subsections.

**Public Interface:**
- `mountCrudCardRenderer(targetEl, config)`
- `desktopCard`
- `mobileCard`
- `desktopList`
- `data-crud-config`
- `data-crud-root`

**Example Usage:**
```js
mountCrudCardRenderer(crudRootEl, {
    ...crud,
    desktopCard: {
        titleExpression: "record.item || '—'",
        titleAsideExpression: "record.updated_at || ''",
        titleAsideSecondaryExpression: "record.total_display || ''",
        titleBadgesExpression: 'record.status ? [{ label: record.status, tone: record.status_tone || "gray" }] : []',
        subtitleAsideExpression: "record.lines_count ? `Items: ${record.lines_count}` : ''",
        showBody: false,
        compact: true,
        statsExpression: 'inventoryAvailabilityStats(record)',
        urlExpression: 'record.show_url',
    },
    mobileCard: {
        titleExpression: "record.item || '—'",
        titleBadgesExpression: 'record.status ? [{ label: record.status, tone: record.status_tone || "gray" }] : []',
        subtitleExpression: "record.supplier_name || ''",
        titleAsideStatsExpression: 'record.on_hand_display ? [{ label: "On hand", value: record.on_hand_display }] : []',
        centerStatsExpression: 'record.on_hand_display ? [{ label: "On hand", value: record.on_hand_display }] : []',
        iconRowStatsExpression: 'record.on_hand_display ? [{ label: "QTY", value: record.on_hand_display }] : []',
        showBody: false,
        showToggle: false,
    },
});
```

Notes:
- `desktopCard` may configure title-adjacent badges, subtitle-aside text, secondary right-side text, compact height, and body visibility.
- `mobileCard` may configure title-adjacent badges, right-side text, title-aside stats, secondary row values, centered stats, icon-row stats, toggle visibility, and body visibility.
- Badge tone mappings are page-module decisions; the shared renderer owns the Tailwind classes for supported semantic tones.
- The card grid renders two columns at the smallest non-mobile breakpoint and three columns at larger desktop breakpoints.

### Reusable Combobox Pattern

**Name:** Reusable Combobox Pattern
**Type:** UI Architecture Invariant
**Location:**
- `docs/architecture/ui/ReusableComboboxPattern.yaml`
- `resources/views/components/combobox.blade.php`
- `resources/views/components/combo-item.blade.php`
- `resources/js/components/combobox.js`

**Purpose:**
Provide a reusable searchable single-select combobox with hidden-input form submission, preserved option metadata, and local keyboard behavior.

**When to Use:**
Large option sets that are no longer manageable in a native select and still need standard scalar form submission.

**When Not to Use:**
Small native selects, multi-select workflows, or cases where page-specific business rules would have to be hard-coded into the generic component.

**Public Interface:**
- `<x-combobox>`
- `<x-combo-item>`

**Example Usage:**
```blade
<x-combobox
    x-model="createForm.item_id"
    name="item_id"
    label="Output Item"
    options-expression="filteredCreateItems()"
    error-expression="createErrors.item_id[0] || ''"
/>
```

Notes:
- The combobox is generic; caller-owned page/module state supplies the visible option set.
- Recipe-specific `has_recipe` filtering remains in the Recipes page module rather than the generic component.

---

## Sales

### Customer Contact Primary Invariant

**Name:** Customer Contact Primary Invariant
**Type:** Domain Rule
**Location:**
- `docs/architecture/sales/CustomerContactPrimaryInvariant.yaml`
- `app/Http/Controllers/CustomerContactController.php`
- `app/Models/Customer.php`
- `app/Models/CustomerContact.php`

**Purpose:**
Document the customer-contact relationship, the split first-name/last-name contact shape, and the exactly-one-primary-when-contacts-exist invariant for customer contacts.

**When to Use:**
Any customer contact create, update, delete, or primary-designation flow on the customer detail Contacts section.

**When Not to Use:**
Customer records without contact mutations or unrelated sales-order contact snapshots.

**Public Interface:**
- `Customer::contacts()`
- `sales.customers.contacts.store`
- `sales.customers.contacts.update`
- `sales.customers.contacts.destroy`
- `sales.customers.contacts.primary.update`

**Example Usage:**
```php
$customer->contacts()->create([
    'tenant_id' => $tenant->id,
    'first_name' => 'Jane',
    'last_name' => 'Buyer',
    'is_primary' => true,
]);
```

---

### Sales Order Draft Contact Assignment

**Name:** Sales Order Draft Contact Assignment
**Type:** Domain Rule
**Location:**
- `docs/architecture/sales/SalesOrderDraftContactAssignment.yaml`
- `app/Http/Controllers/SalesOrderController.php`
- `app/Http/Requests/Sales/StoreSalesOrderRequest.php`
- `app/Http/Requests/Sales/UpdateSalesOrderRequest.php`
- `app/Models/SalesOrder.php`

**Purpose:**
Document the sales-order customer/contact rules used by editable Sales Order header flows on the shared Orders index shell and the Sales Order detail page.

**When to Use:**
Any editable sales-order create, update, delete, or validation flow, including customer changes that may re-default the assigned contact.

**When Not to Use:**
Sales-order lines, pricing snapshots, fulfillment/inventory effects, invoicing, or customer-contact primary designation outside a sales-order assignment.

**Public Interface:**
- `SalesOrder::STATUS_DRAFT`
- `SalesOrder::STATUS_OPEN`
- `SalesOrder::isEditable()`
- `SalesOrder::statuses()`
- `sales.orders.index`
- `sales.orders.store`
- `sales.orders.update`
- `sales.orders.destroy`

**Example Usage:**
```php
$order = SalesOrder::query()->create([
    'tenant_id' => $tenant->id,
    'customer_id' => $customer->id,
    'contact_id' => $customer->contacts->firstWhere('is_primary', true)?->id,
    'status' => SalesOrder::STATUS_DRAFT,
]);
```

---

### Sales Order Currency

**Name:** Sales Order Currency
**Type:** Domain Rule
**Location:**
- `docs/architecture/sales/SalesOrderCurrency.yaml`
- `app/Models/Customer.php`
- `app/Models/SalesOrder.php`
- `app/Http/Controllers/CustomerController.php`
- `app/Http/Controllers/SalesOrderController.php`
- `app/Http/Controllers/SalesOrderLineController.php`
- `database/migrations/2026_06_22_000001_add_currency_code_to_customers_table.php`
- `database/migrations/2026_06_22_000002_add_currency_code_to_sales_orders_table.php`

**Purpose:**
Document customer preferred sales currency, sales-order currency snapshots, and single-currency sales order line pricing without foreign exchange conversion.

**When to Use:**
Creating customers, creating sales orders, adding sales-order lines, or rendering sales-order prices and totals.

**When Not to Use:**
Purchasing supplier price conversion, automatic product price conversion, or cross-currency reporting.

**Public Interface:**
- `customers.currency_code`
- `sales_orders.currency_code`
- `sales_order_lines.unit_price_currency_code`
- `sales.orders.store`
- `sales.orders.lines.store`

**Example Usage:**
```php
$order = SalesOrder::query()->create([
    'tenant_id' => $tenant->id,
    'customer_id' => $customer->id,
    'currency_code' => $customer->currency_code ?? $tenant->currency_code,
    'status' => SalesOrder::STATUS_DRAFT,
]);
```

---

### Sales Order Line Pricing And Editable Rules

**Name:** Sales Order Line Pricing And Editable Rules
**Type:** Domain Rule
**Location:**
- `docs/architecture/sales/SalesOrderLinePricingAndDraftRules.yaml`
- `app/Http/Controllers/SalesOrderLineController.php`
- `app/Http/Requests/Sales/StoreSalesOrderLineRequest.php`
- `app/Http/Requests/Sales/UpdateSalesOrderLineRequest.php`
- `app/Models/SalesOrder.php`
- `app/Models/SalesOrderLine.php`

**Purpose:**
Document the editable sales-order line mutation rules, immutable unit-price snapshots, canonical scale-6 quantity/line-total behavior, and optional source-line identity used on the Sales Order detail page.

**When to Use:**
Any sales-order line create, delete, or quantity-update flow for editable sales orders.

**When Not to Use:**
Sales-order header customer/contact assignment, lifecycle transitions, fulfillment, shipping, invoicing, payments, or completion inventory impact.

**Public Interface:**
- `SalesOrder::STATUS_DRAFT`
- `SalesOrder::STATUS_OPEN`
- `SalesOrder::allowsLineMutations()`
- `SalesOrder::lines()`
- `sales.orders.lines.store`
- `sales.orders.lines.update`
- `sales.orders.lines.destroy`

**Example Usage:**
```php
$line = SalesOrderLine::query()->create([
    'tenant_id' => $tenant->id,
    'sales_order_id' => $order->id,
    'item_id' => $item->id,
    'quantity' => '2.500000',
    'unit_price_cents' => $item->default_price_cents,
    'unit_price_currency_code' => $item->default_price_currency_code,
    'line_total_cents' => '832.500000',
]);
```

---

### Sales Order External CSV Import Export

**Name:** Sales Order External CSV Import Export
**Type:** Domain Import Export Rule
**Location:**
- `docs/architecture/sales/SalesOrderExternalCsvImportExport.yaml`
- `app/Http/Controllers/SalesOrderController.php`
- `app/Http/Requests/Sales/PreviewExternalSalesOrderImportRequest.php`
- `app/Http/Requests/Sales/ImportExternalSalesOrdersRequest.php`
- `app/Models/SalesOrder.php`
- `app/Models/SalesOrderLine.php`
- `app/Services/WordPressPluginOrderPreviewService.php`
- `integrations/wordpress/foomake-connector/foomake-connector.php`
- `resources/js/pages/Sales/Orders/Index.vue`

**Purpose:**
Document the line-level external CSV export contract, grouped file-upload preview/import behavior, tenant-scoped duplicate identity, and external status sync rules for Sales Orders.

**When to Use:**
Any Sales Orders CSV export, file-upload preview/import flow, duplicate identity check, or external status sync behavior.
WooCommerce order preview may use stored API credentials or a paired WordPress plugin connection.

**When Not to Use:**
Native Sales Order header forms, native Sales Order line mutations on the detail page, or inventory-impacting workflow transitions.

**Public Interface:**
- `sales.orders.export`
- `sales.orders.import.preview`
- `sales.orders.import.store`
- `GET /wp-json/foomake/v1/orders`
- `external_source`
- `external_id`
- `external_status`
- `external_status_synced_at`
- `sales_order_lines.external_id`

**Example Usage:**
```text
external_source,order_external_id,order_date,customer_name,contact_name,city,status,external_status,line_external_id,product_external_id,product_name,quantity,unit_price
legacy_csv,SO-1001,2026-05-14,Ada Buyer,Jane Buyer,Toronto,OPEN,processing,LINE-1,SKU-2001,Imported Item,2.000000,15.00
legacy_csv,SO-1001,2026-05-14,Ada Buyer,Jane Buyer,Toronto,OPEN,processing,LINE-2,SKU-2002,Imported Item 2,1.000000,5.00
```

---

### Sales Order Packing Inventory Impact

**Name:** Sales Order Packing Inventory Impact
**Type:** Domain Rule
**Location:**
  - `docs/architecture/sales/SalesOrderCompletionInventoryImpact.yaml`
  - `app/Actions/Sales/BuildSalesOrderIssuePlanAction.php`
  - `app/Http/Controllers/SalesOrderStatusController.php`
  - `app/Models/SalesOrder.php`
  - `app/Models/StockMove.php`
  - `app/Services/Workflows/SalesOrderWorkflow.php`

**Purpose:**
Document the inventory-ledger effects of Sales Order operational-stage progression, including availability checks, transactional issue posting, and packed-order reversals under the seeded default sales workflow.

**When to Use:**
Moving a sales order into packing, posting packed inventory issue moves, or cancelling a packed order with reversal moves.

**When Not to Use:**
Editable header/line mutations, shipping/completion transitions without inventory impact, or downstream invoicing/payment behavior.

**Public Interface:**
  - `BuildSalesOrderIssuePlanAction::execute()`
  - `SalesOrderWorkflow::transition()`
  - `SalesOrderWorkflow::responsePayload()`
  - `SalesOrder::STATUS_OPEN`
  - `SalesOrder::STATUS_PACKING`
  - `SalesOrder::STATUS_PACKED`
  - `SalesOrder::STATUS_SHIPPING`
  - `SalesOrder::STATUS_COMPLETED`
  - `sales.orders.status.update`

**Example Usage:**
```php
$packedOrder = $packSalesOrderAction->execute($salesOrder, $buildSalesOrderIssuePlanAction);
```

---

### Workflow Domain

**Name:** Workflow Domain
**Type:** System-Owned Domain Rule
**Location:**
- `docs/architecture/workflows/WorkflowDomain.yaml`
- `docs/PR3_ROADMAP.md`

**Purpose:**
Define the fixed domain layer that scopes tenant-owned workflow stages, workflow task templates, and generated tasks across operational modules.

**When to Use:**
Domain-general workflow infrastructure for sales first, with purchasing and manufacturing later.

**When Not to Use:**
Admin-managed taxonomy creation or product-specific workflow overrides.

**Public Interface:**
- `docs/architecture/workflows/WorkflowDomain.yaml`

**Example Usage:**
```text
sales -> workflow stages -> workflow task templates -> generated tasks
```

---

### Workflow Stage

**Name:** Workflow Stage
**Type:** Tenant-Scoped Domain Rule
**Location:**
- `docs/architecture/workflows/WorkflowStage.yaml`
- `docs/PR3_ROADMAP.md`

**Purpose:**
Define the database-backed operational middle-stage abstraction that can be configured per tenant and workflow domain.

**When to Use:**
Operational stage ordering, activation, and admin CRUD behavior within a workflow domain.

**When Not to Use:**
System lifecycle statuses that remain hard-coded domain rules.

**Public Interface:**
- `docs/architecture/workflows/WorkflowStage.yaml`
- `workflow-manage`

**Example Usage:**
```text
tenant sales stages ordered by sort_order
```

---

### Workflow Task Template

**Name:** Workflow Task Template
**Type:** Tenant-Scoped Domain Rule
**Location:**
- `docs/architecture/workflows/WorkflowTaskTemplate.yaml`
- `docs/PR3_ROADMAP.md`

**Purpose:**
Define the admin-managed template abstraction used to generate stage-specific tasks.

**When to Use:**
Task-definition CRUD, activation, ordering, and assignee defaults for a workflow stage.

**When Not to Use:**
Retroactively mutating existing generated tasks or creating ad hoc tasks outside the configured template flow.

**Public Interface:**
- `docs/architecture/workflows/WorkflowTaskTemplate.yaml`
- `workflow-manage`

**Example Usage:**
```text
packing stage template: Print packing slip
```

---

### Task

**Name:** Task
**Type:** Tenant-Scoped Task Record Rule
**Location:**
- `docs/architecture/workflows/Task.yaml`
- `docs/PR3_ROADMAP.md`
- `app/Http/Controllers/TaskController.php`

**Purpose:**
Define the tenant-owned task record for generated workflow task snapshots and manually assigned operational follow-up tasks.

**When to Use:**
Stage-entry task generation, manual assigned detail-page tasks with due dates, assigned-user completion, and immutable snapshot behavior for workflow tasks.

**When Not to Use:**
General project-management features, comments, due dates, multi-assignee tasks, task reopening flows, or manual task workflow gating.

**Public Interface:**
- `docs/architecture/workflows/Task.yaml`

**Example Usage:**
```text
sales order 42 enters current stage -> generate tenant-scoped stage tasks
make order 42 detail -> create manual assigned task with workflow context, but no transition gate
inventory count draft detail -> create manual assigned task with workflow domain and record context, but no stage
manual task -> due_date appears on task rows and dashboard todo when present
```

---

### Sales Order Workflow Task Gating

**Name:** Sales Order Workflow Task Gating
**Type:** Domain Rule
**Location:**
- `docs/architecture/workflows/SalesOrderWorkflowTaskGating.yaml`
- `docs/PR3_ROADMAP.md`

**Purpose:**
Define the rule that forward sales-order operational transitions are blocked by open current-stage workflow tasks in addition to existing sales-order domain guards.

**When to Use:**
The interaction between sales-order stage entry, generated task creation, and forward lifecycle gating.

**When Not to Use:**
Draft editing, unrelated task systems, or purchasing/manufacturing task generation before those integrations are implemented.

**Public Interface:**
- `docs/architecture/workflows/SalesOrderWorkflowTaskGating.yaml`

**Example Usage:**
```text
current operational stage -> next stage is blocked while current-stage generated tasks remain open
```

---

### Sales Products Filtered Item View

**Name:** Sales Products Filtered Item View
**Type:** Read Model / Domain Invariant
**Location:**
- `app/Http/Controllers/SalesProductController.php`
- `app/Models/Item.php`
- `resources/js/pages/Sales/Products/Index.vue`
- `routes/web.php`

**Purpose:**
Document that Sales → Products is a sales-facing filtered view of normal tenant-owned items rather than a separate product entity, exposed as a mount-only Blade shell with a shared JSON-configured CRUD renderer.

**When to Use:**
Rendering, listing, searching, sorting, creating, or importing sales-facing products while preserving the shared `Item` identity.

**When Not to Use:**
Introducing a separate products table/model or treating imported products as distinct from materials.

**Public Interface:**
- `sales.products.index`
- `sales.products.list`
- `sales.products.store`
- `sales.products.import.preview`
- `sales.products.import.store`
- `Item::query()->where('is_sellable', true)`

**Notes:**
- `/sales/products` remains the user-facing page route and renders the Blade shell.
- Desktop table rows and mobile cards are sourced from the same JSON list endpoint rather than Blade-rendered records.
- This slice does not introduce product update/delete endpoints or a separate product entity.

**Example Usage:**
```php
$products = Item::query()
    ->where('is_sellable', true)
    ->orderBy('name')
    ->get();
```

---

### Manufacturing Recipes Read-Only Access

**Name:** Manufacturing Recipes Read-Only Access
**Type:** Authorization Rule
**Location:**
- `docs/architecture/auth/ManufacturingRecipesReadOnlyAccess.yaml`
- `app/Providers/AuthServiceProvider.php`
- `app/Http/Controllers/RecipeController.php`
- `routes/web.php`
- `resources/views/layouts/navigation.blade.php`

**Purpose:**
Enforce authenticated, gate-backed access to manufacturing recipe read-only pages.

**When to Use:**
Restricting recipes index/show routes and navigation visibility.

**When Not to Use:**
Recipe write or execution flows.

**Public Interface:**
- `Gate::authorize('inventory-recipes-view')`
- `@can('inventory-recipes-view')`
- `manufacturing.recipes.*`

**Example Usage:**
```php
Gate::authorize('inventory-recipes-view');
```

---

### Role

**Name:** Role
**Type:** Eloquent Model
**Location:** `app/Models/Role.php`

**Purpose:**
Represent global roles that group permissions.

**When to Use:**
Assigning responsibilities and permissions to users.

**When Not to Use:**
Per-tenant role definitions.

**Public Interface:**
- `users()`
- `permissions()`

**Example Usage:**
```php
$user->roles()->attach($roleId);
```

---

### Permission

**Name:** Permission
**Type:** Eloquent Model
**Location:** `app/Models/Permission.php`

**Purpose:**
Store canonical permission slugs enforced via Gates.

**When to Use:**
Authorization checks and role-permission mappings.

**When Not to Use:**
UI-only access decisions without backend enforcement.

**Public Interface:**
- `roles()`

**Example Usage:**
```php
$permission->roles()->attach($roleId);
```

---

### User

**Name:** User
**Type:** Eloquent Model
**Location:** `app/Models/User.php`

**Purpose:**
Represent authentication identities and role/permission checks.

**When to Use:**
Authentication and authorization checks.

**When Not to Use:**
Tenant-scoped domain queries.

**Public Interface:**
- `tenant()`
- `roles()`
- `hasRole()`
- `hasPermission()`

**Example Usage:**
```php
if ($user->hasPermission('inventory-materials-manage')) {
    // ...
}
```

---

## Inventory Ledger

### StockMove

**Name:** StockMove
**Type:** Eloquent Model / Domain Rule
**Location:** `app/Models/StockMove.php`

**Purpose:**
Represent append-only inventory movements that form the ledger.

**When to Use:**
Any inventory-affecting operation such as receipts, issues, or adjustments.

**When Not to Use:**
Storing or mutating on-hand totals directly.

**Public Interface:**
- `tenant()`
- `item()`
- `uom()`
- `source()`

**Example Usage:**
```php
StockMove::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'uom_id' => $item->base_uom_id,
    'quantity' => '10.000000',
    'type' => 'receipt',
]);
```

---

### Stock-Move Guarded Delete

**Name:** Stock-Move Guarded Delete
**Type:** Architectural Pattern
**Location:** `app/Http/Controllers/ItemController.php`

**Purpose:**
Prevent deleting materials that have stock move history.

**When to Use:**
Deleting tenant-owned items tracked in the inventory ledger.

**When Not to Use:**
Entities without inventory history.

**Public Interface:**
- `ItemController::destroy()`
- `Item::stockMoves()`

**Example Usage:**
```http
DELETE /materials/{item}
-> 422 { "message": "Material cannot be deleted because stock moves exist." }
```

---

### Decimal Quantity Math

**Name:** Decimal Quantity Math
**Type:** Domain Rule
**Location:** `docs/CONVENTIONS.md`

**Purpose:**
Define canonical rules for quantity math to avoid floating-point errors.

**When to Use:**
Any inventory-affecting calculations or unit conversions.

**When Not to Use:**
Non-quantity calculations.

**Public Interface:**
- BCMath functions
- Canonical scale rules in `docs/CONVENTIONS.md`

**Example Usage:**
```php
$total = bcadd($a, $b, 6);
```

---

### Inventory Availability Read Model

**Name:** Inventory Availability Read Model
**Type:** Read Model / Domain Rule
**Location:**
- `docs/architecture/inventory/InventoryAvailabilityReadModel.yaml`
- `app/Support/Inventory/InventoryAvailabilityIndexReadModel.php`
- `app/Support/Inventory/InventoryAvailabilityCalculator.php`
- `app/Http/Controllers/MaterialController.php`

**Purpose:**
Provide one tenant-scoped availability contract for the materials index and single-item availability reads.

**When to Use:**
Rendering materials availability cards or resolving availability for one item.

**When Not to Use:**
Posting stock moves or mutating operational records.

**Public Interface:**
- `InventoryAvailabilityIndexReadModel::rows()`
- `InventoryAvailabilityIndexReadModel::rowForItem()`
- `InventoryAvailabilityCalculator::forItem(Item $item)`

**Rules:**
- Availability math remains canonical BCMath at scale 6.
- UI-facing inventory quantities must render using the item base UoM `display_precision`.
- The read model may expose both canonical quantity fields and backend-formatted display fields for the same row.
- Workflow-enabled Purchase Orders count as BUY/open supply while they have an active current purchasing workflow stage and are not cancelled.
- Purchase Orders without workflow state count as BUY/open supply unless they are completed or cancelled, preserving availability for rows created before workflow initialization.
- Buy/open purchase-order quantities are calculated as package count × supplier package quantity × resolved package-UoM-to-item-base-UoM conversion, matching UoMs by normalized symbol rather than requiring conversion-row UoM ids to match package/base UoM ids.
- Buy/open purchase-order conversion precedence is tenant/general, then global, then item-specific fallback; conversion resolution supports direct, reciprocal, and multi-step symbol paths through defined conversion records.
- Missing required package-UoM conversion excludes that PO line from BUY availability rather than displaying package-UoM quantities as base-UoM quantities.

**Example Usage:**
```php
$rows = $indexReadModel->rows($tenantId, 'flour');
$availability = $calculator->forItem($item);
```

---

### Item

**Name:** Item
**Type:** Eloquent Model
**Location:** `app/Models/Item.php`

**Purpose:**
Represent tenant-owned stock-tracked entities with inventory derived from stock moves.

**When to Use:**
Modeling materials or products and computing on-hand quantities.

**When Not to Use:**
Storing denormalized on-hand quantities.

**Public Interface:**
- `baseUom()`
- `stockMoves()`
- `onHandQuantity()`
- `itemUomConversions()`
- `recipes()`
- `activeRecipe()`

**Example Usage:**
```php
$onHand = $item->onHandQuantity();
```

---

### InventoryCount

**Name:** InventoryCount
**Type:** Eloquent Model
**Location:**
- `app/Models/InventoryCount.php`
- `app/Services/Workflows/InventoryCountWorkflow.php`

**Purpose:**
Represent inventory count sessions whose posted lifecycle remains derived from `posted_at` while draft setup stays outside workflow stages and submitted counts progress through the tenant-configured Inventory workflow.

**When to Use:**
Recording inventory count sessions and posting adjustments.

**When Not to Use:**
Inventory adjustments outside a count context.

**Public Interface:**
- `tenant()`
- `lines()`
- `postedByUser()`
- `createdByUser()`
- `taskedByUser()`
- `assignedToUser()`
- `workflowStage()`
- `stockMoves()`
- `getStatusAttribute()`
- `InventoryCountWorkflow::submit()`
- `InventoryCountWorkflow::advance()`
- `InventoryCountWorkflow::previous()`
- `InventoryCountWorkflow::postCompatible()`
- `InventoryCountWorkflow::responsePayload()`

**Example Usage:**
```php
$status = $inventoryCount->status;
```

Notes:
- Draft counts stay outside workflow stages until they are submitted into the first active Inventory workflow stage.
- Count-level audit is tracked with `created_by_user_id` and `tasked_by_user_id`.
- Draft creation does not require assignment; if a count-level assignee is present it is reused when workflow tasks are generated.
- Workflow task assignment uses generated `tasks.assigned_to_user_id` rather than a separate inventory-count-only task system.
- Draft detail exposes only the next valid workflow action, defaulting to `SCHEDULE` when seeded inventory stages are unchanged.
- Moving past the seeded Scheduling stage requires at least one attached Inventory Count material line.
- With seeded Inventory stages, pressing Schedule enters automatic Scheduling, completes it, and lands in manual Counting with visible status `SCHEDULED`.
- Submitted Inventory Counts may expose previous-stage and next-stage actions using configured action verb. Previous-stage movement is Inventory Count specific and never reverses posted stock.
- Inventory Count detail uses the shared resource-detail header plus a compact `Details` section ahead of the reusable `Materials` and `Tasks` sections. The header shows the counted date with a clean workflow-derived status badge underneath, while Count Date, Assigned To, and Notes live in the AJAX-autosaved `Details` section.
- Inventory Count detail workflow-status badges must never show the computed posted lifecycle value; `posted` is storage lifecycle only and not a workflow-stage status.
- Workflow stages separate current-state display from transition-button copy: stage labels use `workflow_stages.name`, while workflow action buttons use `workflow_stages.action_verb`.
- Seeded workflow stages are core (`is_core = true`) and cannot be deleted, deactivated, reordered, or edited across identity/status-completion fields; user-created stages default to non-core.
- Inventory Count detail mounts reusable `Materials` and `Tasks` sections through shared `js-crud-section` payload/config rendering; the `Tasks` section is not bespoke markup, uses the existing `tasks.complete` route contract, disables the shared dots menu through config, always shows `Assigned By`, then swaps `Assigned To` for `Completed By` once the task is completed, and shows a visible inline `Complete` action only while the task is incomplete and completable.
- Inventory Count detail `Materials` section uses the shared compact add-row contract in Draft: a reusable combobox on the left plus a `+` button on the right adds an existing selected material line through AJAX and must not open Material or Item creation.
- Inventory Count detail `Materials` rows use two mutually exclusive modes: Draft rows use a direct inline rounded `x-mark` remove action instead of the vertical-dots row menu, while submitted workflow-stage rows hide removal and expose an AJAX Qty input on the right side instead.
- Draft Inventory Count detail `Materials` rows do not show QTY labels or QTY inputs, and workflow-stage rows do not show remove actions.
- Inventory Count detail `Details` metadata may still be updated after workflow entry, but posting / inventory-effect stages remain mutation-locked.
- Seeded Inventory Count workflow stages use Scheduling, Counting, and Completing as stage names. Scheduling is automatic and completes to `SCHEDULED`, Counting is manual and completes to `COUNTED`, and Completing is the manual inventory-effect stage that posts only when the current stage is completed.
- Entering any workflow stage hides the Inventory Count Materials combobox add row and server-side line creation is rejected.
- Workflow-stage Inventory Count QTY edits use the existing line update route, normalize counted quantities to canonical scale 6, and return refreshed row payloads for immediate shared-section updates.
- Workflow-stage Inventory Count QTY inputs display using the counted item's base UoM display precision instead of raw canonical scale-6 storage.
- Successful workflow-stage Inventory Count QTY AJAX saves show a transient row-scoped green check-circle immediately before the `QTY` label and do not show that success state on failed saves.
- Blank Inventory Count Materials notes render nothing; the detail row no longer shows `Notes: —`.
- Draft Inventory Count line removal stays AJAX-first and returns `deleted_line_id` plus remaining lines so the shared section can remove the row immediately without a page refresh.
- Stockable Materials detail pages may mount an `Inventory Counts` reusable CRUD section that lists count lines for the current material, shows count date / assigned user / UOM / counted quantity, and uses the shared section plus-button to open the same shared Inventory Count create slide-over contract and open-create event path used by the Inventory Counts index page.
- Successful empty `Inventory Counts` section loads must render only the configured empty state; `Unable to load records.` is reserved for actual fetch failures.
- Material-scoped Inventory Count creation may prefill the current material server-side and redirect to the created Inventory Count detail page when `show_url` is returned.
- Stockable Material detail pages may also render a compact inventory stats strip directly under the header. That strip always shows `On hand` and `Net Qty`, then conditionally adds `Open SO`, `Open PO`, and `Open MO` only when the item qualifies.
- Material detail net quantity uses the formula `on hand - open sales + open purchase + open make outputs - open make ingredients`, with all quantity math kept at canonical scale 6 and displayed through the shared quantity formatter.
- Open make-order output in that stats strip and in the inventory availability read model uses `expected_output_qty`, while completed make-order output reaches `On Hand` and `Net Qty` through ledger stock moves that use `actual_output_qty` when present.
- Shared section metadata rendering filters explicit empty metadata values so mutually exclusive task-row labels do not render placeholder rows.
- Inventory Count index `Status` reflects the workflow-derived status label rather than the current stage label or posted lifecycle label.
- Count lines may leave `counted_quantity` blank during draft/setup, but posting must fail until every line has a quantity.
- Posting inventory counts creates stock moves only for stockable count lines.
- The direct `/inventory/counts/{count}/post` route remains a compatibility path and may move the count to the Inventory inventory-effect stage before posting.

---

### InventoryCountLine

**Name:** InventoryCountLine
**Type:** Eloquent Model
**Location:** `app/Models/InventoryCountLine.php`

**Purpose:**
Represent line items for an inventory count session.

**When to Use:**
Recording counted quantities for items.

**When Not to Use:**
Recording inventory adjustments outside a count.

**Public Interface:**
- `inventoryCount()`
- `item()`
- `uom()`
- `snapshotUom()`

**Rules:**
- Count lines snapshot the counted UoM in `uom_id` when the material is added.
- Historical count rows display the line `uom_id`, not the material's current base UoM.
- Snapshot UoM resolution allows tenant UoMs and global system UoMs, but never another tenant's UoM.
- Posting converts line quantities into the material's current base UoM before writing variance stock moves.

**Example Usage:**
```php
$line = $count->lines()->create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'uom_id' => $item->base_uom_id,
    'counted_quantity' => '5.000000',
]);
```

---

### PostInventoryCountAction

**Name:** PostInventoryCountAction
**Type:** Action / Domain Service
**Location:** `app/Actions/Inventory/PostInventoryCountAction.php`

**Purpose:**
Post an inventory count and create ledger adjustments.

**When to Use:**
Posting inventory count results to the ledger.

**When Not to Use:**
Generic inventory adjustments.

**Public Interface:**
- `execute(InventoryCount $inventoryCount, int $postedByUserId): InventoryCount`

**Example Usage:**
```php
$action = new PostInventoryCountAction();
$action->execute($inventoryCount, $userId);
```

---

## Manufacturing

### Recipe

**Name:** Recipe
**Type:** Eloquent Model
**Location:** `app/Models/Recipe.php`

**Purpose:**
Represent the stable parent identity for named recipes while delegating execution details to version records.

**When to Use:**
Defining recipe parents and parent-level metadata.

**When Not to Use:**
Non-manufacturing inventory relationships.

**Public Interface:**
- `tenant()`
- `item()`
- `currentVersion()`
- `versions()`
- `lines()`
- `stockMoves()`

**Example Usage:**
```php
$recipe = Recipe::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'name' => 'Batch of Patties',
    'is_active' => true,
]);
```

---

### Recipe Versioning Pattern

**Name:** Recipe Versioning Pattern
**Type:** Manufacturing Domain Pattern
**Location:**
- `docs/architecture/manufacturing/RecipeVersioning.yaml`
- `app/Models/Recipe.php`
- `app/Models/RecipeVersion.php`
- `app/Models/RecipeVersionLine.php`

**Purpose:**
Separate stable recipe identity from version-owned execution templates and ingredient lines.

**When to Use:**
Capturing or changing recipe execution behavior without mutating historical Make Orders.

**When Not to Use:**
Parent-level display metadata such as the main recipe name or default flag.

**Public Interface:**
- `Recipe::currentVersion()`
- `Recipe::versions()`
- `RecipeVersion::lines()`

**Example Usage:**
```php
$recipe->versions()->create([
    'tenant_id' => $recipe->tenant_id,
    'version_number' => 101,
    'recipe_type' => 'manufacturing',
    'output_quantity' => '54.000000',
    'status' => 'DRAFT',
]);
```

Notes:
- `recipes.current_version_id` is the single pointer to the current version.
- Version status is lifecycle, checkout is user editing context, and neither is a synonym for currentness.
- Recipe detail headers and Make Order defaults must read type and output quantity from the displayed version context, with current published as fallback.
- Recipe creation seeds version `1.00` as a draft, without auto-checkout and without setting `recipes.current_version_id`.
- Published version actions are `View`, `Duplicate`, and `Archive`; published versions do not expose `Checkout` or direct `Edit`.
- Draft version actions are `View/Edit`, `Duplicate`, and `Delete`.
- Archived versions stay hidden by default behind the `View Archived` toggle.

---

### Recipe Version Checkout Pattern

**Name:** Recipe Version Checkout Pattern
**Type:** Manufacturing Domain Pattern
**Location:**
- `docs/architecture/manufacturing/RecipeVersionCheckout.yaml`
- `app/Models/RecipeVersionCheckout.php`
- `app/Http/Controllers/RecipeController.php`

**Purpose:**
Persist per-user recipe-version editing context without introducing a recipe-wide lock.

**When to Use:**
Checking out, checking in, publishing, or resolving the version a user should see on the recipe detail page.

**When Not to Use:**
Choosing the version for Make Orders or expressing lifecycle status.

Notes:
- Checkout controls editing context only.
- Users without an open checkout must see the current published version.
- Users without an open checkout fall back to the latest non-archived version when no current published version exists yet.

**Public Interface:**
- `Recipe::openCheckoutForUser()`
- `Recipe::displayVersionForUser()`
- `RecipeVersion::checkouts()`

---

### Recipe Ingredients Editing Pattern

**Name:** Recipe Ingredients Editing Pattern
**Type:** Manufacturing Domain Pattern
**Location:**
- `docs/architecture/manufacturing/RecipeIngredientsEditing.yaml`
- `app/Models/RecipeVersionLine.php`
- `app/Http/Controllers/RecipeController.php`
- `resources/views/manufacturing/recipes/show.blade.php`

**Purpose:**
Keep recipe ingredients version-owned and editable only through a checked-out draft context on the recipe detail page.

**When to Use:**
Displaying, adding, or editing recipe ingredients in the recipe detail view.

**When Not to Use:**
Editing parent recipe lines directly or deriving Make Order inputs from live recipe data.

Notes:
- Ingredients come from `recipe_version_lines`.
- The displayed ingredient section follows the current user's checkout context, otherwise the current published version.
- Ingredient quantity formatting follows the ingredient item's UoM `display_precision`, while storage remains canonical scale 6.
- The add bar uses the shared compact combobox-plus detail-section pattern and stays hidden in read-only mode.
- The Ingredients section must use the shared detail-section card contract rather than page-local accordion markup.
- Parent-level recipe line editing UI must stay hidden.

---

### Breadcrumbs Component

**Name:** Breadcrumbs Component
**Type:** UI Component
**Location:**
- `docs/architecture/ui/Breadcrumbs.yaml`
- `resources/views/components/ui/breadcrumbs.blade.php`

**Purpose:**
Render one reusable full-width Blade breadcrumb bar with a Home link, connected chevron separators, and accessible current-page state.

**When to Use:**
Resource detail breadcrumb trails and other page-level breadcrumb bars.

**When Not to Use:**
Primary application navigation or workflow step indicators.

**Public Interface:**
- `<x-ui.breadcrumbs :items="$breadcrumbItems" />`

Notes:
- The component renders Home itself; page-provided items should not include Home.
- The last item is current by default unless an item explicitly sets `current`.
- Page-local breadcrumb bars and retired legacy breadcrumb components should not be used.

---

### Workflow Progress Panel

**Name:** Workflow Progress Panel
**Type:** UI Component / Read Model Pattern
**Location:**
- `docs/architecture/ui/WorkflowProgressPanel.yaml`
- `app/Actions/Workflows/BuildWorkflowProgressStepsAction.php`
- `resources/views/components/ui/workflow-progress.blade.php`

**Purpose:**
Render a read-only progress panel for workflow-enabled resource detail pages using tenant-configured workflow stages, with optional `DRAFT` display.

**When to Use:**
Sales Order, Purchase Order, Make Order, Inventory Count, or future workflow-enabled detail pages that need passive workflow position display below the resource header.

**When Not to Use:**
Workflow transitions, task completion, workflow stage administration, breadcrumbs, or primary navigation.

**Public Interface:**
- `BuildWorkflowProgressStepsAction::execute()`
- `<x-ui.workflow-progress :steps="$workflowProgressSteps" :do_draft="true" />`

Notes:
- `do_draft` defaults to `false`; when false, the visual progress bar omits `DRAFT`.
- When `do_draft` is true, the first visual step is `DRAFT`.
- Active tenant-configured stage names render in stage order.
- Completed workflows render with no current step and mark completed stages as checked, including `DRAFT` only when `do_draft` is true.
- Mobile rendering uses a horizontal read-only stage selector: all stage numbers remain visible, completed stages use the same filled-indigo visual language as desktop, and selecting a stage number only reveals that stage label without submitting workflow changes.
- The component is read-only and must not expose transition controls.
- Existing resource authorization remains the source of truth.

---

### Loading Overlay

**Name:** Loading Overlay
**Type:** UI Component
**Location:**
- `resources/views/components/ui/loading-overlay.blade.php`

**Purpose:**
Render a reusable blocking loading overlay for explicit user-initiated AJAX mutations while the app waits on a server response.

**When to Use:**
Long-running workflow mutations, checkout confirmations, or other explicit server writes that need to block repeat submits.

**When Not to Use:**
Passive page initialization, background polling, or non-blocking status indicators.

**Public Interface:**
- `<x-ui.loading-overlay title="Working..." subtitle="Waiting for the server response." />`
- `title`
- `subtitle`

**Example Usage:**
```blade
<x-ui.loading-overlay
    title="Saving..."
    subtitle="Waiting for the server response."
    x-show="loading"
    x-transition.opacity
/>
```

---

### Shared Resource Detail Header Breadcrumb Component

**Name:** Shared Resource Detail Header Breadcrumb Component
**Type:** UI Layout Pattern
**Location:**
- `docs/architecture/ui/ResourceDetailHeaderBreadcrumb.yaml`
- `resources/views/components/ui/breadcrumbs.blade.php`
- `resources/views/components/resource-detail-header-breadcrumb.blade.php`
- `resources/js/components/ResourceDetailHeaderBreadcrumb.vue`
- `resources/views/materials/show.blade.php`
- `resources/views/manufacturing/recipes/show.blade.php`

**Purpose:**
Keep detail-page titles and breadcrumbs aligned through one reusable header contract with the breadcrumb rendered at the bottom of the header.

**When to Use:**
Resource detail pages that render a title and breadcrumb trail.

**When Not to Use:**
Index pages or standalone navigation bars.

**Public Interface:**
- `<x-resource-detail-header-breadcrumb />`
- `<x-ui.breadcrumbs />`
- `resources/js/components/ResourceDetailHeaderBreadcrumb.vue`

Notes:
- Recipes and Materials must not hand-roll their own resource header or breadcrumb wrappers.
- Connected chevron separators and the horizontal border lines above and below the breadcrumb trail are part of the component contract.
- Vue detail breadcrumbs inside AuthShell span the content pane, not the full viewport, so they do not render beneath sidebar navigation.
- Breadcrumb Home and first resource segments stay visible as width tightens; trailing record crumbs truncate first.
- Migrated Vue detail pages may use the ResourceDetailHeaderBreadcrumb header slot for a domain-specific visual header, but it must replace the default header above the breadcrumb rather than render as a separate body section.
- Domain-specific Vue headers are page headers, not cards; they must span the header width with square outer edges unless a referenced design explicitly says otherwise.
- Domain-specific Vue headers must fit the established resource header height and must not introduce extra vertical gaps before the breadcrumb.

### Recipe Detail Make Orders Section

**Name:** Recipe Detail Make Orders Section
**Type:** Manufacturing UI Pattern
**Location:**
- `resources/views/manufacturing/recipes/show.blade.php`
- `app/Http/Controllers/RecipeController.php`
- `resources/js/lib/js-crud-section.js`

**Purpose:**
Expose recipe-scoped Make Order history and direct creation through the shared detail CRUD section.

**When to Use:**
Listing or creating Make Orders from a recipe detail page.

**When Not to Use:**
Creating Make Orders from drafts or from a checked-out display context.

Notes:
- The Recipe detail section order is Make Orders, Ingredients, then Versions.
- The Make Orders section defaults open.
- The plus button remains right-aligned through the shared CRUD section create-wrapper contract.
- Row primary text links to the Make Order detail page through the shared primary-link contract.

---

### Starting Inventory Visibility Pattern

**Name:** Starting Inventory Visibility Pattern
**Type:** Inventory Domain Pattern
**Location:**
- `app/Http/Controllers/ItemController.php`
- `app/Models/InventoryCount.php`
- `app/Models/StockMove.php`

**Purpose:**
Make material starting quantity visible through the user-facing inventory counts surface while preserving stock moves as the canonical append-only inventory ledger.

**When to Use:**
Creating a material with a positive starting quantity.

**When Not to Use:**
General receiving, purchase-order posting, or later stock adjustments.

**Public Interface:**
- `ItemController::store()`
- `InventoryCount::stockMoves()`

**Rules:**
- Positive starting quantity for a stockable material creates an immediately completed and posted `Initial Stock` inventory count.
- The opening-balance stock effect should reuse the existing inventory-count posting behavior instead of creating a disconnected one-off inventory mutation.
- Non-stockable materials must reject starting quantity input instead of silently ignoring it.

**Public Interface:**
- `RecipeVersion::lines()`
- `RecipeController::storeIngredient()`
- `RecipeController::updateIngredient()`

---

### Make Order Recipe Snapshot Pattern

**Name:** Make Order Recipe Snapshot Pattern
**Type:** Manufacturing Domain Pattern
**Location:**
- `docs/architecture/manufacturing/MakeOrderRecipeSnapshot.yaml`
- `app/Models/MakeOrder.php`
- `app/Models/MakeOrderLine.php`
- `app/Http/Controllers/MakeOrderController.php`

**Purpose:**
Snapshot the current published recipe version and its lines onto each Make Order so later recipe changes do not mutate execution history.

**When to Use:**
Creating or executing Make Orders.

**When Not to Use:**
Live recipe planning queries that should resolve the current version directly.

Notes:
- Make Orders must resolve `recipe_version_id` from `recipes.current_version_id`.
- User checked-out recipe versions are excluded from Make Order selection.

**Public Interface:**
- `MakeOrder::recipeVersion()`
- `MakeOrder::lines()`

**Example Usage:**
```php
$makeOrder->lines()->create([
    'tenant_id' => $makeOrder->tenant_id,
    'input_item_id' => $item->id,
    'planned_quantity' => '4.000000',
    'line_type' => 'recipe',
]);
```

---

### Breadcrumb/Header Alignment Rule

**Name:** Breadcrumb/Header Alignment Rule
**Type:** UI Layout Invariant
**Location:**
- `docs/architecture/ui/ResourceDetailBreadcrumbAlignment.yaml`
- `resources/views/components/ui/breadcrumbs.blade.php`
- `resources/views/components/resource-detail-header-breadcrumb.blade.php`
- `resources/views/manufacturing/recipes/show.blade.php`

**Purpose:**
Keep resource-detail breadcrumbs and titles aligned within one container so the home icon and title share the same left edge.

**When to Use:**
Rendering breadcrumbs above a resource detail title.

**When Not to Use:**
Index pages or standalone navigation regions.

Notes:
- Breadcrumbs and the header title must share the same layout container.
- Breadcrumbs must not drift in an independent max-width wrapper.

**Public Interface:**
- `resources/views/components/ui/breadcrumbs.blade.php`
- `resources/views/components/resource-detail-header-breadcrumb.blade.php`
- `resources/views/manufacturing/recipes/show.blade.php`

---

### RecipeLine

**Name:** RecipeLine
**Type:** Eloquent Model
**Location:** `app/Models/RecipeLine.php`

**Purpose:**
Represent line items for a recipe.

**When to Use:**
Capturing input items and quantities for recipes.

**When Not to Use:**
Inventory movements or adjustments.

**Public Interface:**
- `tenant()`
- `recipe()`
- `item()`

**Example Usage:**
```php
$recipe->lines()->create([
    'tenant_id' => $tenant->id,
    'item_id' => $inputItem->id,
    'quantity' => '2.000000',
]);
```

---

### ExecuteRecipeAction

**Name:** ExecuteRecipeAction
**Type:** Action / Domain Service
**Location:** `app/Actions/Inventory/ExecuteRecipeAction.php`

**Purpose:**
Execute a recipe to issue inputs and receipt outputs as stock moves.

**When to Use:**
Manufacturing or make-order execution.

**When Not to Use:**
Inventory adjustments or corrections.

**Public Interface:**
- `execute(Recipe $recipe, string $runs): array`

**Example Usage:**
```php
$action = new ExecuteRecipeAction();
$action->execute($recipe, '5.000000');
```

---

### Recipe Read Model

**Name:** Recipe Read Model
**Type:** Read Model / UI Contract
**Location:**
- `docs/architecture/manufacturing/RecipeReadModel.yaml`
- `app/Http/Controllers/RecipeController.php`
- `resources/views/manufacturing/recipes/index.blade.php`
- `resources/views/manufacturing/recipes/show.blade.php`

**Purpose:**
Define the read-only data and display expectations for recipe index and detail pages.

**When to Use:**
Rendering manufacturing recipe read-only views.

**When Not to Use:**
Recipe creation, editing, or execution flows.

**Public Interface:**
- `manufacturing.recipes.index`
- `manufacturing.recipes.show`

**Example Usage:**
```blade
<th>{{ __('Recipe Name') }}</th>
<th>{{ __('Input Item') }}</th>
<th>{{ __('Quantity') }}</th>
<th>{{ __('UoM') }}</th>
```

---

## Units of Measure

### QuantityFormatter

**Name:** QuantityFormatter
**Type:** Support Utility
**Location:** `app/Support/QuantityFormatter.php`

**Purpose:**
Centralize UI quantity string formatting using UoM display precision.

**Notes:**
- Accepts numeric strings, ints, floats, and null.
- Clamps precision to `0..6`.
- Preserves trailing zeros to requested precision.
- Uses string-safe half-up rounding for display output.
- Uses UoM-driven precision via `display_precision`.

**When to Use:**
Rendering quantities for HTML and page payloads.

**When Not to Use:**
Storage math or domain arithmetic (use BCMath with canonical scale 6).

**Public Interface:**
- `QuantityFormatter::format($quantity, $precision)`
- `QuantityFormatter::formatForUom($quantity, $uom, $fallbackPrecision = 6)`

**Example Usage:**
```php
$display = QuantityFormatter::formatForUom($line->quantity, $line->item?->baseUom, 1);
```

---

### Blade Quantity Directives

**Name:** Blade Quantity Directives
**Type:** Blade Integration Pattern
**Location:** `app/Providers/AppServiceProvider.php`

**Purpose:**
Provide a Blade-first wrapper over `QuantityFormatter` so views do not format quantities ad-hoc.

**Notes:**
- Quantity display in Blade should use directives backed by `QuantityFormatter`.
- JavaScript must consume backend-provided display strings; it must not reformat quantities.

**When to Use:**
Any quantity rendered directly in Blade templates.

**When Not to Use:**
Currency formatting or non-quantity values.

**Public Interface:**
- `@qty($value, $precision)`
- `@qtyForUom($value, $uom, $fallbackPrecision = 6)`

**Example Usage:**
```blade
@qtyForUom($item->onHandQuantity(), $item->baseUom, 1)
```

---

### UomCategory

**Name:** UomCategory
**Type:** Eloquent Model
**Location:** `app/Models/UomCategory.php`

**Purpose:**
Group units of measure into categories that define safe conversion boundaries.

**Notes:**
- Tenant-owned. System defaults use `tenant_id = null`.
- Names are unique per tenant.

**When to Use:**
Defining conversion-safe groupings such as mass or volume.

**When Not to Use:**
Cross-category conversion logic.

**Public Interface:**
- `uoms()`

**Example Usage:**
```php
$category = UomCategory::create([
    'tenant_id' => $tenant->id,
    'name' => 'Mass',
]);
```

---

### Uom

**Name:** Uom
**Type:** Eloquent Model
**Location:** `app/Models/Uom.php`

**Purpose:**
Represent a unit of measure belonging to a single category.

**Notes:**
- Tenant-owned. System defaults use `tenant_id = null`.
- `symbol` is unique per tenant; `name` is not unique.

**When to Use:**
Assigning units to items and recording quantities.

**When Not to Use:**
Implicit unit assumptions.

**Public Interface:**
- `category()`
- `conversionsFrom()`
- `conversionsTo()`

**Example Usage:**
```php
$uom = Uom::create([
    'tenant_id' => $tenant->id,
    'uom_category_id' => $category->id,
    'name' => 'Gram',
    'symbol' => 'g',
]);
```

---

### UomConversion

**Name:** UomConversion
**Type:** Eloquent Model / Domain Rule
**Location:** `app/Models/UomConversion.php`

**Purpose:**
Provide safe global conversions within a single UoM category.

**When to Use:**
Universal conversions within a category.

**When Not to Use:**
Cross-category conversions or item-specific conversions.

**Public Interface:**
- `fromUom()`
- `toUom()`

**Example Usage:**
```php
UomConversion::create([
    'from_uom_id' => $kg->id,
    'to_uom_id' => $grams->id,
    'multiplier' => '1000.00000000',
]);
```

---

### UoM Conversion System

**Name:** UoM Conversion System
**Type:** Domain Rule Set / UI + Persistence Pattern
**Location:**
- `app/Http/Controllers/UomConversionController.php`
- `app/Models/UomConversion.php`
- `app/Models/ItemUomConversion.php`
- `app/Services/Uom/SystemUomCloner.php`
- `resources/views/manufacturing/uom-conversions/index.blade.php`

**Purpose:**
Unify global, tenant-managed, and item-specific conversion behavior behind one manufacturing UI and one precedence-aware lookup model.

**When to Use:**
- Managing same-category global or tenant conversions
- Managing item-specific overrides
- Resolving a conversion for operational workflows

**When Not to Use:**
- Implicit ad hoc unit math outside the defined conversion system
- Cross-category general conversions

**Public Interface:**
- `manufacturing.uom-conversions.*` routes
- `UomConversion`
- `ItemUomConversion`

**Example Usage:**
```php
Gate::authorize('inventory-materials-manage');
```

---

### Conversion Precedence Pattern

**Name:** Conversion Precedence Pattern
**Type:** Domain Resolution Rule
**Location:**
- `app/Http/Controllers/UomConversionController.php`
- `app/Actions/Inventory/ReceivePurchaseOptionAction.php`
- `docs/architecture/uom/ConversionPrecedence.yaml`

**Purpose:**
Resolve unit conversions deterministically when multiple scopes can define a mapping.

**When to Use:**
- Any lookup that must choose between item-specific, tenant, and global conversions

**When Not to Use:**
- Writes or validations that should target one explicit scope only

**Public Interface:**
- `resolve()` behavior
- `item-specific > tenant > global`

**Example Usage:**
```php
// Resolution order:
// 1. item-specific
// 2. tenant
// 3. global
```

---

### ItemUomConversion

**Name:** ItemUomConversion
**Type:** Eloquent Model / Domain Rule
**Location:** `app/Models/ItemUomConversion.php`

**Purpose:**
Allow item-specific conversions, including cross-category conversions.

**When to Use:**
Conversions that are true only for a specific item.

**When Not to Use:**
Global conversions shared across items.

**Public Interface:**
- `item()`
- `fromUom()`
- `toUom()`

**Example Usage:**
```php
$item->itemUomConversions()->create([
    'tenant_id' => $tenant->id,
    'from_uom_id' => $count->id,
    'to_uom_id' => $grams->id,
    'conversion_factor' => '50.000000',
]);
```

---

## Purchasing

### Supplier

**Name:** Supplier
**Type:** Eloquent Model
**Location:** `app/Models/Supplier.php`

**Purpose:**
Represent tenant-owned suppliers for purchasing relationships.

**When to Use:**
Managing suppliers for purchasing workflows.

**When Not to Use:**
Materials or inventory entities.

**Public Interface:**
- `tenant()`

**Example Usage:**
```php
$supplier = Supplier::create([
    'tenant_id' => $tenant->id,
    'company_name' => 'Acme Supplies',
]);
```

---

### Supplier Delete Guard

**Name:** Supplier Delete Guard
**Type:** Domain Guard / Service Interface
**Location:**
- `app/Services/Purchasing/SupplierDeleteGuard.php`
- `app/Services/Purchasing/DefaultSupplierDeleteGuard.php`
- `app/Http/Controllers/SupplierController.php`

**Purpose:**
Provide a seam to block supplier deletion when supplier-linked purchasing catalog records exist, without broad schema refactors.

**When to Use:**
Deleting suppliers via AJAX endpoints with a supplier catalog link check.

**When Not to Use:**
Delete guards for non-supplier entities.

**Public Interface:**
- `SupplierDeleteGuard::isLinkedToMaterials(Supplier $supplier): bool`

**Example Usage:**
```php
if ($guard->isLinkedToMaterials($supplier)) {
    return response()->json([
        'message' => 'Supplier cannot be deleted because it is linked to purchasing records.',
    ], 422);
}
```

---

### ItemPurchaseOption

**Name:** ItemPurchaseOption
**Type:** Eloquent Model
**Location:** `app/Models/ItemPurchaseOption.php`

**Purpose:**
Represent supplier-specific purchasing packs that map into item inventory.

**When to Use:**
Receiving inventory in supplier pack quantities.

**When Not to Use:**
Tracking inventory on-hand directly.

**Public Interface:**
- `tenant()`
- `item()`
- `packUom()`

**Notes:**
- Pack UoM must match the item base UoM or have a resolvable direct, reciprocal, or multi-step path through item-specific, tenant/general, or global conversions before create/update is allowed.
- Missing conversion responses must include enough item/UOM context for the supplier-package slide-over to open a quick item-conversion modal without navigating away.

**Example Usage:**
```php
$option = ItemPurchaseOption::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'pack_quantity' => '10.000000',
    'pack_uom_id' => $kg->id,
]);
```

### Supplier Package Form Config

**Name:** Supplier Package Form Config
**Type:** Reusable Purchasing Form Contract
**Location:**
- `app/Support/Purchasing/SupplierPackageFormConfig.php`
- `docs/architecture/purchasing/SupplierPackageFormConfig.yaml`

**Purpose:**
Keep the supplier-package drawer form design consistent across Material detail and Supplier detail while allowing each page to provide its own fixed parent context and endpoints.

**When to Use:**
Supplier-package create/edit drawer fields inside reusable detail-section CRUD surfaces.

**When Not to Use:**
Unrelated purchasing forms, persistence, validation, or authorization.

**Public Interface:**
- `SupplierPackageFormConfig::createAction()`
- `SupplierPackageFormConfig::fieldsForMaterial()`
- `SupplierPackageFormConfig::fieldsForSupplier()`

**Example Usage:**
```php
$formConfig = new SupplierPackageFormConfig();

$section['createAction'] = $formConfig->createAction(['supplier_id' => $supplier->id]);
$section['fields'] = $formConfig->fieldsForSupplier($request, $supplier);
```

### Purchase Order Lifecycle

**Name:** Purchase Order Lifecycle
**Type:** Domain Rule
**Location:**
- `docs/architecture/purchasing/PurchaseOrderLifecycle.yaml`
- `app/Models/PurchaseOrder.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/PurchaseOrderStatusController.php`
- `app/Http/Controllers/PurchaseOrderWorkflowController.php`
- `app/Services/Purchasing/PurchaseOrderLifecycleService.php`
- `app/Services/Workflows/PurchaseOrderWorkflow.php`

**Purpose:**
Track Purchase Order lifecycle through the shared workflow foundation while mirroring the legacy status column during migration.

**Rules:**
- PO detail status is derived from workflow state: `workflow_cancelled_at` means `CANCELLED`, no completed stage means `DRAFT`, receipt-derived `PARTIALLY_RECEIVED` is displayed while the PO remains in Receiving, otherwise the last completed stage's `status_complete_label`.
- PO index, detail header, action-button state, row badges, and page JSON payloads must use workflow-derived status, not the legacy `purchase_orders.status` mirror column.
- PO detail actions are driven by reusable workflow JSON and complete the current stage through AJAX.
- Workflow action dropdown labels are presented in natural case from `workflow_stages.action_verb`.
- The PO Receive action opens one PO-level multi-line receipt slide-over and submits one receipt event.
- Receipt events with remaining receivable balance persist `PARTIALLY_RECEIVED`; additional partial receipts keep that status until all receivable balances are received or short-closed.
- Purchasing workflow-stage `status_complete_label` values must come from `WorkflowStatusOptions`; `purchase_orders.status` remains constrained to persisted PO statuses, while the workflow-stage dropdown may also expose `OPEN` for workflow-derived stage configuration. Default seeded stages keep one normal Receiving stage and do not create a duplicate `PARTIALLY_RECEIVED` receiving stage.
- The configured inventory-impacting PO stage cannot be completed directly by the generic workflow-complete endpoint.
- Created to Received requires the Receive slide-over; receipt submit records receipt details and then advances workflow fields when balances are closed.
- Cancel transitions eligible purchase orders to persisted terminal status `CANCELLED`.
- Back Order and Short Close are action events or markers, not `purchase_orders.status` values.
- Purchase orders may carry one nullable workflow assignee in `purchase_orders.assigned_to_user_id`; assignment options are tenant-scoped and limited to purchasing workflow-eligible users.
- Line-level tax is entered with one decimal place, stored in basis points, and calculated per line for PO tax totals.
- PO detail Details fields autosave individually and must not depend on a section-level Save button.
- PO detail header, shipping, and line edits remain editable until the configured purchasing inventory-effect stage has completed; cancellation, completed workflow, or inventory-effect completion locks edits.
- Supplier changes on a draft PO clear existing PO lines server-side because supplier-package options are supplier-scoped.
- Draft PO Items add flow uses one clean supplier-scoped supplier-package combobox plus an icon `+` button; it must not expose separate item and package-option selectors.
- Draft PO line quantity and tax are edited inline and autosaved through the line update endpoint, which must return refreshed line and PO totals JSON.
- Draft PO line quantity input and validation use whole package counts only.
- Backend transition rules and gates are authoritative.

**Public Interface:**
- `PurchaseOrder::statuses()`
- `PurchaseOrderStatusController::update()`
- `PurchaseOrderWorkflowController::complete()`
- `PurchaseOrderWorkflowController::cancel()`
- `PurchaseOrderLifecycleService::createReceipt()`
- `PurchaseOrderLifecycleService::createShortClosure()`
- `PurchaseOrderWorkflow::completeStage()`
- `PurchaseOrderWorkflow::cancel()`
- `PurchaseOrderWorkflow::responsePayload()`

### Purchase Order Receipt Inventory Impact

**Name:** Purchase Order Receipt Inventory Impact
**Type:** Domain Rule
**Location:**
- `docs/architecture/purchasing/PurchaseOrderReceiptInventoryImpact.yaml`
- `app/Services/Purchasing/PurchaseOrderLifecycleService.php`
- `app/Models/PurchaseOrderReceiptLine.php`

**Purpose:**
Ensure purchase order receipt lines are recorded while stockable receipt lines post linked stock moves in item base units.

**When to Use:**
Purchase order receiving and receipt-ledger audit checks.

**When Not to Use:**
Short-close events or non-purchasing inventory adjustments.

**Public Interface:**
- `PurchaseOrderLifecycleService::createReceipt()`
- `PurchaseOrderReceiptLine::stockMove()`
- `Item::onHandQuantity()`

**Notes:**
- Receipt quantity uses package count × package quantity × resolved package-UoM-to-base-UoM conversion.
- Zero and blank submitted receipt quantities are ignored, but each receipt event must include at least one positive received quantity.
- One receipt submit creates one receipt header and one receipt line per positive received line.
- Conversion precedence is item-specific, tenant/general, then global; missing conversion blocks receiving transactionally.
- Conversion may resolve through direct, reciprocal, or multi-step paths through defined conversion records.

**Example Usage:**
```php
$baseQuantity = bcmul('2.000000', '500.000000', 6);
// $baseQuantity === '1000.000000'
```

---

### ReceivePurchaseOptionAction

**Name:** ReceivePurchaseOptionAction
**Type:** Action / Domain Service
**Location:** `app/Actions/Inventory/ReceivePurchaseOptionAction.php`

**Purpose:**
Receive inventory from a purchase option and create a stock move.

**When to Use:**
Receiving inventory from supplier pack quantities.

**When Not to Use:**
Generic inventory adjustments.

**Public Interface:**
- `execute(ItemPurchaseOption $option, string $packCount): StockMove`
- `baseQuantityFor(ItemPurchaseOption $option, string $packCount): string`

**Example Usage:**
```php
$action = new ReceivePurchaseOptionAction();
$action->execute($option, '2.000000');
```

---

## Controllers & UI Patterns

### AJAX CRUD Controller Pattern

**Name:** AJAX CRUD Controller Pattern
**Type:** Architectural Pattern
**Location:**
- `app/Http/Controllers/UomCategoryController.php`
- `app/Http/Controllers/UomController.php`
- `app/Http/Controllers/ItemController.php`

**Purpose:**
Handle UI-driven CRUD using JSON responses without full page reloads.

**When to Use:**
Single-entity CRUD with fetch-based requests.

**When Not to Use:**
Multi-step workflows or transactional orchestration.

**Public Interface:**
- `store()`
- `update()`
- `destroy()`

**Example Usage:**
```php
$response = $this->postJson('/materials', [
    'name' => 'Flour',
    'base_uom_id' => 1,
]);
```

---

### Shared Navigation Eligibility State

**Name:** Shared Navigation Eligibility State
**Type:** UI Architecture Invariant
**Location:**
- `docs/architecture/ui/SharedNavigationEligibilityState.yaml`
- `app/Navigation/NavigationEligibility.php`
- `app/Http/Controllers/NavigationStateController.php`
- `resources/views/layouts/navigation.blade.php`
- `resources/js/navigation/refresh-navigation-state.js`

**Purpose:**
Centralize tenant-scoped order-navigation eligibility in backend code while letting AJAX page modules refresh stale nav DOM after successful mutations.

**When to Use:**
Rendering or refreshing Sales Orders, Purchase Orders, or Make Orders navigation state.

**When Not to Use:**
Authorization decisions, route protection, or any client-owned navigation authority.

**Public Interface:**
- `NavigationEligibility::forUser()`
- `NavigationEligibility::forTenantId()`
- `GET /navigation/state`
- `navigation.state`

**Example Usage:**
```php
$eligibility = app(\App\Navigation\NavigationEligibility::class)->forUser(auth()->user());
```

---

### Top Navigation Dropdown

**Name:** Top Navigation Dropdown
**Type:** UI Pattern
**Location:** `resources/views/layouts/navigation.blade.php`

**Purpose:**
Group navigation links under a top-level dropdown.

**When to Use:**
A top-level domain owns mandatory supporting subdomains.

**When Not to Use:**
Unrelated or optional domains.

**Public Interface:**
- Blade markup using `x-dropdown` and `x-dropdown-link`

**Example Usage:**
```blade
<x-dropdown align="left">
    <x-slot name="trigger">
        <button>Manufacturing</button>
    </x-slot>
    <x-slot name="content">
        <x-dropdown-link :href="route('materials.index')">Inventory</x-dropdown-link>
    </x-slot>
</x-dropdown>
```

---

### Slide-Over Form Pattern

**Name:** Slide-Over Form Pattern
**Type:** UI Pattern
**Location:** `resources/views/components/slide-over-shell.blade.php`

**Purpose:**
Create or edit entities without leaving the current page.

**When to Use:**
CRUD forms with multiple fields.

**When Not to Use:**
Confirmations or single-field actions.

**Public Interface:**
- `<x-slide-over-shell>` Blade component
- Props: `open`, `close`, optional `submit`, `title`, `title-expression`, `description`, `description-expression`, `title-id`, and `max-width`
- Named `footer` slot for resource-specific actions

**Example Usage:**
```blade
<x-slide-over-shell
    open="isCreateOpen"
    close="closeCreate()"
    submit="submitCreate()"
    title="Create Material"
    title-id="create-material-slide-over-title"
>
    <input type="text" x-model="form.name" />
    <x-slot name="footer">
        <button type="submit">Save</button>
    </x-slot>
</x-slide-over-shell>
```

---

### Import Slide-Over Preview Pattern

**Name:** Import Slide-Over Preview Pattern
**Type:** UI Pattern
**Location:**
- `docs/architecture/ui/ImportSlideOverPreviewPattern.yaml`
- `app/Http/Controllers/SalesProductController.php`
- `app/Http/Controllers/CustomerController.php`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/views/sales/customers/index.blade.php`
- `resources/js/lib/import-config.js`
- `resources/js/lib/import-module.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/js/pages/sales-customers-index.js`

**Purpose:**
Provide a reusable config-driven import slide-over component that owns the full import UX and lifecycle, auto-loads previews from the chosen source, keeps bulk options and preview records in accordions, and preserves backend import authority.

**When to Use:**
Preview-first import slide-overs that combine source selection, duplicate-aware row visibility, config-driven preview rendering, approved data adapters, and resource-specific endpoints without leaving the current page.

**When Not to Use:**
One-step uploads with no preview, or workflows that require global JavaScript state or client-owned import authority.

**Public Interface:**
- `data-import-config`
- `resources/js/lib/import-config.js`
- `resources/js/lib/import-module.js`
- `createImportModule({ config, adapters, callbacks }).mount(hostComponent)`

**Key Rules:**
- The shared import component owns the import slide-over markup rather than relying on resource-specific Blade form markup.
- Resource pages may not render import slide-over form markup, import preview cards, import source controls, import bulk controls, or import footer actions directly.
- Minimum preview-row height is the UX goal.
- Preview rows must render as a single compact horizontal row with a left-truncated primary value and a right-aligned secondary value.
- Preview rows must not render a second metadata line.
- Preview rows must not render a status badge.
- Products: truncated name left, price right only.
- Customers: truncated name left, city right only.
- Preview loading auto-starts when a connected external source is selected or when a valid local file has been read.
- The slide-over must not require a manual Load Preview button.
- Bulk options and preview records render as separate accordions, with bulk options default collapsed and preview records default open.
- Preview rows render as responsive cards rather than a table.
- Duplicate rows are hidden by default.
- Preview row selection, search text, duplicate visibility, and row override state are owned by the shared import component rather than the resource page module.
- Resource-specific data-shape differences may enter the shared import component only through approved adapters for local-row parsing, preview-row normalization, import-row payload building, submit-body shaping, and import-success callbacks.
- Approved adapters may shape data but may not own import markup, control visibility, or override import lifecycle behavior.

**Example Usage:**
```js
const importConfig = parseImportConfig(rootEl);
const importModule = createImportModule({
    config: importConfig,
    adapters: {
        parseLocalRows,
        normalizePreviewRow,
        buildImportRowPayload,
        buildSubmitBody,
    },
    callbacks: {
        onImportSuccess: refreshProducts,
    },
});

importModule.mount(rootEl);
```

Notes:
- Products and Customers now consume the same shared import component path and no longer keep import slide-over form markup in Blade.
- Bulk Import Options defaults collapsed while Preview Records accordion defaults open.
- Preview records render as responsive cards; duplicate rows remain in DOM state and are hidden by default.
- Loading labels, preview display expressions, messages, and bulk option defaults are provided through the server-generated import config rather than hardcoded in the resource page module.
- The preview records area is the only scrollable region inside the slide-over.
- Full row data may still exist internally for validation, duplicate detection, search, and import payload correctness, but extra detail must not render in preview rows.

---

### Export Slide-Over Pattern

**Name:** Export Slide-Over Pattern
**Type:** UI Pattern
**Location:**
- `docs/architecture/ui/ExportSlideOverPattern.yaml`
- `resources/js/lib/export-module.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/js/pages/sales-customers-index.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/views/sales/customers/index.blade.php`

**Purpose:**
Provide a reusable config-driven export slide-over component that owns the full export UX and lifecycle, derives export requests from the shared CRUD contract, and preserves backend route authority.

**When to Use:**
Shared export slide-overs that need identical structure and lifecycle across multiple CRUD resources while keeping export endpoints and CSV authority on the server.

**When Not to Use:**
One-click downloads with no scope chooser, or workflows that require global JavaScript state or client-owned export authority.

**Public Interface:**
- `data-crud-config`
- `resources/js/lib/export-module.js`
- `createExportModule({ config }).mount(hostComponent)`

**Key Rules:**
- The shared export component owns the export slide-over markup rather than relying on resource-specific Blade form markup.
- Resource pages may not render export slide-over form markup, export scope controls, export footer actions, or export validation/error markup directly.
- Resource pages may not own export lifecycle methods such as `openExportPanel`, `closeExportPanel`, `resetExportState`, `buildExportUrl`, or `submitExport` outside the shared export component.
- Export scope, descriptive copy, unavailable-message copy, submit/cancel labels, and visibility must come from the server-generated CRUD contract or safe shared defaults.
- Export URL construction remains config-driven and must not hardcode resource-specific endpoints in shared JavaScript.

**Example Usage:**
```js
const crud = createGenericCrud(parseCrudConfig(rootEl));
const exportModule = createExportModule({ config: crud });

exportModule.mount(rootEl);
```

Notes:
- Products and Customers now consume the same shared export component path and no longer keep export slide-over form markup in Blade.
- Export differences are expressed through CRUD config labels, permissions, and endpoints rather than page-local Blade or page-local lifecycle code.

---

### Row Actions Dropdown Pattern

**Name:** Row Actions Dropdown Pattern
**Type:** UI Pattern
**Location:** `resources/views/components/dropdown.blade.php`

**Purpose:**
Provide contextual row-level actions such as edit and delete.

**When to Use:**
Tables or lists with multiple row actions.

**When Not to Use:**
Primary or global actions.

**Public Interface:**
- Dropdown trigger + content for row actions

**Example Usage:**
```blade
<button type="button">⋮</button>
```

---

### Page-Scoped Toast Pattern

**Name:** Page-Scoped Toast Pattern
**Type:** UI Pattern
**Location:** `resources/views/components/ui/toast.blade.php`

**Purpose:**
Provide non-blocking toast feedback scoped to the current page.

**When to Use:**
Non-blocking success or error feedback after AJAX actions.

**When Not to Use:**
Blocking alerts or full-page loaders.

**Public Interface:**
- `<x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />`
- Page-level `showToast(type, message)` handler owns state and timing

**Example Usage:**
```blade
<x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />
```

---

## UI Components

### Dropdown

**Name:** Dropdown
**Type:** Blade Component
**Location:** `resources/views/components/dropdown.blade.php`

**Purpose:**
Render a dropdown container with trigger and content slots.

**When to Use:**
Inline dropdown menus for actions or navigation.

**When Not to Use:**
Primary actions that should remain visible.

**Public Interface:**
- `trigger` slot
- `content` slot

**Example Usage:**
```blade
<x-dropdown>
    <x-slot name="trigger">⋮</x-slot>
    <x-slot name="content">...</x-slot>
</x-dropdown>
```

---

### Dropdown Link

**Name:** Dropdown Link
**Type:** Blade Component
**Location:** `resources/views/components/dropdown-link.blade.php`

**Purpose:**
Provide a styled link within dropdown content.

**When to Use:**
Dropdown menus linking to routes.

**When Not to Use:**
Standalone buttons outside dropdown menus.

**Public Interface:**
- Standard Blade component props

**Example Usage:**
```blade
<x-dropdown-link href="/materials">Materials</x-dropdown-link>
```

---

### Simple UI Dropdown

**Name:** Simple UI Dropdown
**Type:** Blade / Alpine Component
**Location:**
- `resources/views/components/ui/dropdown.blade.php`
- `docs/architecture/ui/SimpleDropdown.yaml`

**Purpose:**
Render a Tailwind Plus-inspired simple dropdown shell for compact header and toolbar selection menus.

**When to Use:**
Simple selection or command menus that need click-away behavior and Alpine transitions.

**When Not to Use:**
Shared row-action menus or searchable combobox/select inputs.

**Public Interface:**
- `trigger` slot
- default slot
- `align`, `width`, `buttonClass`, `menuClass`

**Example Usage:**
```blade
<x-ui.dropdown align="left" width="w-56">
    <x-slot name="trigger">Current value</x-slot>
    <button type="button" role="menuitem">Option</button>
</x-ui.dropdown>
```

---

### Multi-Step Action Button

**Name:** Multi-Step Action Button
**Type:** Blade / Alpine Component
**Location:**
- `resources/views/components/workflow-action-button.blade.php`
- `resources/js/components/workflow-action-button.js`

**Purpose:**
Render a compact header action button that shows the current derived state label and exposes multiple server-driven step actions from the same dropdown while the shared loading overlay blocks repeat submits.

**When to Use:**
Detail headers for workflow-enabled or versioned records where one button should expose the next valid actions.

**When Not to Use:**
Row action menus, free-form dropdowns, or single immutable buttons.

**Public Interface:**
- `<x-workflow-action-button :workflow="$state" mode="dispatch" action-event-name="..." sync-event-name="..." sync-state-key="..." />`
- `workflowActionButton(workflow, csrfToken, options)`

**Example Usage:**
```blade
<x-workflow-action-button
    :workflow="$payload['workflow']"
    mode="dispatch"
    action-event-name="purchase-order-status-action"
    sync-event-name="workflow-updated"
    sync-state-key="workflow"
/>
```

---

### Modal

**Name:** Modal
**Type:** Blade Component
**Location:** `resources/views/components/modal.blade.php`

**Purpose:**
Provide a reusable modal container.

**When to Use:**
Confirmation dialogs or short forms.

**When Not to Use:**
Long multi-step flows.

**Public Interface:**
- `name` prop
- `show` prop

**Example Usage:**
```blade
<x-modal name="confirm-delete" :show="true">...</x-modal>
```

---

### Nav Link

**Name:** Nav Link
**Type:** Blade Component
**Location:** `resources/views/components/nav-link.blade.php`

**Purpose:**
Render a navigation link with active state styling.

**When to Use:**
Top navigation links.

**When Not to Use:**
Inline links within content.

**Public Interface:**
- `href` prop
- `active` prop

**Example Usage:**
```blade
<x-nav-link href="/materials" :active="request()->routeIs('materials.index')">Materials</x-nav-link>
```

---

### Input Label

**Name:** Input Label
**Type:** Blade Component
**Location:** `resources/views/components/input-label.blade.php`

**Purpose:**
Render a label for form inputs.

**When to Use:**
Form fields requiring labels.

**When Not to Use:**
Decorative text without input association.

**Public Interface:**
- `for` prop
- Slot content

**Example Usage:**
```blade
<x-input-label for="name" value="Name" />
```

---

### Text Input

**Name:** Text Input
**Type:** Blade Component
**Location:** `resources/views/components/text-input.blade.php`

**Purpose:**
Render a styled text input.

**When to Use:**
Form inputs using standard text fields.

**When Not to Use:**
Non-textual inputs like selects or checkboxes.

**Public Interface:**
- Standard input props

**Example Usage:**
```blade
<x-text-input id="name" type="text" name="name" />
```

---

### Input Error

**Name:** Input Error
**Type:** Blade Component
**Location:** `resources/views/components/input-error.blade.php`

**Purpose:**
Display validation errors for a field.

**When to Use:**
Form validation error display.

**When Not to Use:**
Non-form error messaging.

**Public Interface:**
- `messages` prop

**Example Usage:**
```blade
<x-input-error :messages="$errors->get('name')" />
```

---

### Secondary Button

**Name:** Secondary Button
**Type:** Blade Component
**Location:** `resources/views/components/secondary-button.blade.php`

**Purpose:**
Render a secondary action button.

**When to Use:**
Non-primary actions in forms or dialogs.

**When Not to Use:**
Primary actions that require emphasis.

**Public Interface:**
- Slot content

**Example Usage:**
```blade
<x-secondary-button>Cancel</x-secondary-button>
```

---

### Auth Session Status

**Name:** Auth Session Status
**Type:** Blade Component
**Location:** `resources/views/components/auth-session-status.blade.php`

**Purpose:**
Render session status messages on auth screens.

**When to Use:**
Login and password reset screens.

**When Not to Use:**
General-purpose alerts outside auth flows.

**Public Interface:**
- `status` prop

**Example Usage:**
```blade
<x-auth-session-status :status="session('status')" />
```

---

## UI Constraints

### Alpine + Blade Quoting Rules

**Name:** Alpine + Blade Quoting Rules
**Type:** UI Constraint
**Location:** `docs/UI_DESIGN.md`

**Purpose:**
Prevent Alpine parsing failures caused by mixed quoting.

**When to Use:**
Any Blade template with Alpine directives.

**When Not to Use:**
Templates without Alpine usage.

**Public Interface:**
- HTML attributes use double quotes
- Alpine JS string literals use single quotes

**Example Usage:**
```blade
<div x-data="{ open: false }"></div>
```

---

### Page Module Contract

**Name:** Page Module Contract
**Type:** UI Architecture Invariant
**Location:**
- `docs/architecture/ui/PageModuleContract.yaml`

**Purpose:**
Define the page-scoped UI module contract for interactive Blade pages.

**When to Use:**
Any interactive Blade page using Alpine state or fetch-based CRUD.

**When Not to Use:**
Static Blade pages with no interactivity.

**Public Interface:**
- `docs/architecture/ui/PageModuleContract.yaml`
- `docs/UI_DESIGN.md`
- `resources/js/app.js`
- `resources/js/pages/**`
- `data-page`
- `data-payload`

**Example Usage:**
```blade
<script type="application/json" id="materials-index-payload">@json($payload)</script>
<div data-page="materials-index" data-payload="materials-index-payload" x-data="materialsIndex"></div>
```

---

### Page Module Guardrails

**Name:** Page Module Guardrails
**Type:** UI Constraint
**Location:**
- `docs/architecture/ui/PageModuleGuardrails.yaml`
- `scripts/ci/blade-guardrails.sh`
- `scripts/ci/js-syntax-guardrails.sh`
- `ci.sh`

**Purpose:**
Fail CI when Blade templates include executable scripts or inline handlers, or when JS uses invalid optional-chaining assignments.

**When to Use:**
Any interactive Blade view or page module change.

**When Not to Use:**
Vendor or generated views excluded from repository checks, plus Breeze/shared layouts and components pending migration.

**Public Interface:**
- `scripts/ci/blade-guardrails.sh`
- `scripts/ci/js-syntax-guardrails.sh`
- `./ci.sh`

**Example Usage:**
```bash
./ci.sh
```

---

### Configured CRUD Page Module Pattern

**Name:** Configured CRUD Page Module Pattern
**Type:** UI Architectural Pattern
**Location:**
- `docs/architecture/ui/ConfiguredCrudPageModulePattern.yaml`
- `resources/js/lib/crud-config.js`
- `resources/js/lib/export-module.js`
- `resources/js/lib/import-config.js`
- `resources/js/lib/import-module.js`
- `resources/js/lib/generic-crud.js`
- `resources/js/lib/crud-card-page.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/js/pages/sales-customers-index.js`
- `resources/js/pages/purchasing-suppliers-index.js`
- `resources/js/pages/admin-users-index.js`
- `resources/js/pages/Sales/Products/Index.vue`
- `resources/views/sales/customers/index.blade.php`
- `resources/views/purchasing/suppliers/index.blade.php`
- `resources/views/admin/users/index.blade.php`

**Purpose:**
Centralize a shared config-driven CRUD card renderer behind server-generated page contracts while keeping Blade index pages mount-only, moving import UX and lifecycle into a shared import component, and leaving page-specific create/export behavior plus approved data adapters in each page module.

**When to Use:**
Interactive Blade CRUD pages that share toolbar, desktop card or stacked-list rendering, mobile row cards, action menus, and list/create/import/sort mechanics but need different routes, columns, row display rules, or page-specific callbacks. All future CRUD index pages should use this abstraction unless a separately approved architecture entry says otherwise.

**When Not to Use:**
Static pages, or domain workflows that exceed generic CRUD concerns.

**Public Interface:**
- `docs/architecture/ui/ConfiguredCrudPageModulePattern.yaml`
- `data-crud-config`
- `data-import-config`
- `resources/js/lib/crud-config.js`
- `resources/js/lib/export-module.js`
- `resources/js/lib/import-config.js`
- `resources/js/lib/import-module.js`
- `resources/js/lib/generic-crud.js`
- `resources/js/lib/crud-card-page.js`

**Current Reference Implementations:**
- Sales Products
- Sales Customers
- Purchasing Suppliers
- Admin Users
- Recipes

**Key Rules:**
- Blade index shells remain mount-only for CRUD concerns and must provide a bounded viewport-height container for the shared CRUD module.
- CRUD pages that use the shared import abstraction emit a separate `data-import-config` contract instead of embedding import internals into the CRUD config.
- `data-crud-root` must fill the available bounded height with `h-full` / `min-h-0`-compatible layout so the shared renderer can size its records pane correctly.
- The shared CRUD card renderer owns toolbar layout, search input, create/import/export buttons, desktop cards or opt-in stacked lists, mobile row cards, empty states, and row-level action rendering.
- The shared CRUD card renderer may render the default vertical-dots row menu or configured row toggles when the CRUD config opts into them.
- Toolbar and page chrome remain outside the records scroller; the records/results area is the only scrollable region for CRUD list rendering.
- Desktop and mobile variants follow the same scroll-containment contract: header/toolbar stays fixed in the component shell while only records scroll.
- Shared export helpers own export panel markup, open/close/reset lifecycle, scope selection, validation/error display, config-driven URL building, and export submission wiring without introducing global state.
- Shared import helpers own import panel markup, source-switch preview loading, local CSV caching, selection rules, duplicate visibility, preview row rendering, validation display, and import submit wiring without introducing global state.
- Shared import preview rows must stay minimum-height and single-line; page modules may not expand them into stacked detail cards.
- Shared import preview rows may render only the approved resource-specific left/right compact values from the server-generated import config.
- Resource pages may provide export differences only through the server-generated CRUD contract; export-specific copy, endpoint, and visibility must not be hardcoded in page-local Blade or page-local JavaScript.
- Resource pages may provide only declarative import config plus approved data adapters such as local-row parsing, preview-row normalization, import-row payload building, submit-body shaping, and import-success callbacks.
- Resource pages must not contain export slide-over form markup or page-local export UX overrides.
- Resource pages must not contain import slide-over form markup or page-local import UX overrides.
- Resource pages must not reintroduce custom preview markup or preview-only view overrides to bypass the shared compact preview-row contract.

Notes:
- Sales Products and Sales Customers both mount the shared export component from `resources/js/lib/export-module.js`; neither page keeps export slide-over form markup in Blade.
- Sales Products and Sales Customers both mount the shared import component from `resources/js/lib/import-module.js`; neither page keeps import slide-over form markup in Blade.
- Sales Customers now exposes a full export surface through `sales.customers.export`, `endpoints.export`, and shared export-component wiring rather than a no-op export toolbar callback.

**Example Usage:**
```blade
<div
    class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
    Inertia::render('Sales/Products/Index')
    payload prop
    data-crud-config='@json($crudConfig)'
    x-data="salesProductsIndex"
>
    <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
        <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>
    </div>
</div>
```

---

### Smart Number Input

**Name:** Smart Number Input
**Type:** UI Component Pattern
**Location:**
- `docs/architecture/ui/SmartNumberInput.yaml`
- `resources/views/components/ui/smart-number-input.blade.php`
- `resources/js/components/smart-number-input.js`

**Purpose:**
Provide one reusable Blade + Alpine number-entry control that formats display text with grouped digits while preserving canonical raw values for server-owned persistence.

**When to Use:**
Numeric input fields for quantities, counts, money amounts, prices, taxes, percentages, and decimal values where the caller can bind or submit a canonical scalar value.

**When Not to Use:**
Fields whose visible label intentionally asks for a storage unit such as cents, or JavaScript-rendered string templates where a Blade component cannot safely mount.

**Public Interface:**
- `<x-ui.smart-number-input name="quantity" type="decimal" precision="6" />`
- Blade-rendered numeric fields use `<x-ui.smart-number-input ... />`
- JavaScript-rendered numeric fields use the shared JS smart-number renderer in `resources/js/lib/js-crud-section.js`
- `type`: `integer`, `decimal`, `money`, or `percent`
- `raw-mode`: `value` or `cents`
- Optional event payload with `name`, `rawValue`, `displayValue`, `type`, `currency`, `precision`, and `source`

Notes:
- Both renderers reuse the same `smartNumberInput` Alpine data module so live comma formatting and raw-value behavior do not drift.
- Quantity precision comes from the item/UOM display precision when item context exists; canonical server normalization remains backend-owned.
- Both renderers dispatch `smart-number-input:changed` for parent listeners; the component does not persist data or show save-success UI.
- Input-source changed events debounce for 300 milliseconds by default; change and blur source events dispatch immediately after clearing any pending input debounce.
- Save-success indicators such as green checkmarks belong to parent page logic after a successful persistence response.

**Example Usage:**
```blade
<x-ui.smart-number-input
    name="shipping_amount"
    type="money"
    x-model="form.shipping_amount"
    after-change="autosaveField('shipping_amount')"
/>
```

---

## Testing

### Pest Testing Framework

**Name:** Pest Testing Framework
**Type:** Testing Infrastructure
**Location:** `tests/Pest.php`

**Purpose:**
Define Pest as the canonical testing framework.

**When to Use:**
All new automated tests.

**When Not to Use:**
New PHPUnit test classes.

**Public Interface:**
- `uses()`
- `it()`
- `expect()`

**Example Usage:**
```php
it('creates a material', function () {
    expect(true)->toBeTrue();
});
```

---
