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
**Location:** `app/Providers/AuthServiceProvider.php`

**Purpose:**  
Centralize authorization using permission slugs and Laravel Gates.

**When to Use:**  
Any access control decision.

**When Not to Use:**  
UI-only visibility decisions without backend enforcement.

**Public Interface:**  
- `Gate::allows()`  
- `Gate::authorize()`

**Example Usage:**  
```php
Gate::authorize('inventory-materials-manage');
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
**Location:** `app/Models/InventoryCount.php`

**Purpose:**  
Represent inventory count sessions with status derived from `posted_at`.

**When to Use:**  
Recording inventory count sessions and posting adjustments.

**When Not to Use:**  
Inventory adjustments outside a count context.

**Public Interface:**  
- `tenant()`  
- `lines()`  
- `postedByUser()`  
- `stockMoves()`  
- `getStatusAttribute()`

**Example Usage:**  
```php
$status = $inventoryCount->status;
```

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

**Example Usage:**  
```php
$line = $count->lines()->create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
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
Represent manufacturing recipes for items.

**When to Use:**  
Defining recipes and their line items.

**When Not to Use:**  
Non-manufacturing inventory relationships.

**Public Interface:**  
- `tenant()`  
- `item()`  
- `lines()`  
- `stockMoves()`

**Example Usage:**  
```php
$recipe = $item->recipe;
```

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
- `execute(Recipe $recipe, string $outputQuantity): array`

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
<th>{{ __('Input Item') }}</th>
<th>{{ __('Quantity') }}</th>
<th>{{ __('UoM') }}</th>
```

---

## Units of Measure

### UomCategory

**Name:** UomCategory  
**Type:** Eloquent Model  
**Location:** `app/Models/UomCategory.php`

**Purpose:**  
Group units of measure into categories that define safe conversion boundaries.

**When to Use:**  
Defining conversion-safe groupings such as mass or volume.

**When Not to Use:**  
Cross-category conversion logic.

**Public Interface:**  
- `uoms()`

**Example Usage:**  
```php
$category = UomCategory::create(['name' => 'Mass']);
```

---

### Uom

**Name:** Uom  
**Type:** Eloquent Model  
**Location:** `app/Models/Uom.php`

**Purpose:**  
Represent a unit of measure belonging to a single category.

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

**Example Usage:**  
```php
$option = ItemPurchaseOption::create([
    'tenant_id' => $tenant->id,
    'item_id' => $item->id,
    'pack_quantity' => '10.000000',
    'pack_uom_id' => $kg->id,
]);
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
**Location:** `resources/views/materials/partials/create-material-slide-over.blade.php`

**Purpose:**  
Create or edit entities without leaving the current page.

**When to Use:**  
CRUD forms with multiple fields.

**When Not to Use:**  
Confirmations or single-field actions.

**Public Interface:**  
- Blade partial with Alpine state and form markup

**Example Usage:**  
```blade
<form x-on:submit.prevent="submitCreate()">
    <input type="text" x-model="form.name" />
</form>
```

---

### Row Actions Dropdown Pattern

**Name:** Row Actions Dropdown Pattern  
**Type:** UI Pattern  
**Location:** `resources/views/materials/index.blade.php`

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
**Location:** `resources/views/materials/index.blade.php`

**Purpose:**  
Provide non-blocking toast feedback scoped to the current page.

**When to Use:**  
Non-blocking success or error feedback after AJAX actions.

**When Not to Use:**  
Blocking alerts or full-page loaders.

**Public Interface:**  
- Page-level `showToast(type, message)` handler

**Example Usage:**  
```js
showToast('success', 'Material deleted.');
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
