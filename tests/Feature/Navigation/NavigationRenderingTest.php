<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;

    $this->makeTenant = function (string $name = 'Tenant A'): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUser = function (Tenant $tenant, bool $verified = true): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => $verified ? now() : null,
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'navigation-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->grantSuperAdmin = function (User $user): void {
        $role = Role::query()->firstOrCreate([
            'name' => 'super-admin',
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->render = function (User $user, string $routeName = 'dashboard', array $parameters = []) {
        return $this->actingAs($user)->get(route($routeName, $parameters));
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        static $uomCounter = 1;

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Navigation Category ' . $uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Navigation UoM ' . $uomCounter,
            'symbol' => 'nav-' . $uomCounter,
        ]);

        $uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        static $itemCounter = 1;

        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Navigation Item ' . $itemCounter,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
        ], $attributes));

        $itemCounter++;

        return $item;
    };
});

it('renders the authenticated layout navigation for a verified user', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('<nav', false)
        ->assertSee('Dashboard');
});

it('redirects guests away from dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('shows the manufacturing group only when at least one manufacturing route is permitted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->render)($user)
        ->assertOk()
        ->assertDontSee('Manufacturing');

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Manufacturing');
});

it('shows the purchasing group only when at least one purchasing route is permitted', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->render)($user)
        ->assertOk()
        ->assertDontSee('Purchasing');

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Purchasing');
});

it('shows the materials link when the user has inventory materials view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Materials')
        ->assertSee(route('materials.index'), false);
});

it('shows inventory links when the user has inventory adjustments view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-adjustments-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Inventory')
        ->assertSee('Inventory Counts')
        ->assertSee(route('inventory.index'), false)
        ->assertSee(route('inventory.counts.index'), false);
});

it('shows recipes link when the user has inventory recipes view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-recipes-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Recipes')
        ->assertSee(route('manufacturing.recipes.index'), false);
});

it('shows make orders link when the user has inventory make orders view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermission)($user, 'inventory-make-orders-view');

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Recipe A',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Orders (Make Orders)')
        ->assertSee(route('manufacturing.make-orders.index'), false);
});

it('shows suppliers link when the user has purchasing suppliers view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Suppliers')
        ->assertSee(route('purchasing.suppliers.index'), false);
});

it('shows purchase orders link when the user has purchasing purchase orders create permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    Supplier::query()->create([
        'tenant_id' => $tenant->id,
        'company_name' => 'Supplier A',
    ]);

    ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Orders')
        ->assertSee(route('purchasing.orders.index'), false);
});

it('renders sales orders as clickable when eligible', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    Customer::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Northwind',
        'status' => Customer::STATUS_ACTIVE,
    ]);

    ($this->makeItem)($tenant, $uom, ['is_sellable' => true]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-sales-orders-nav-link="desktop"', false)
        ->assertSee('href="' . route('sales.orders.index') . '"', false);
});

it('renders sales orders as visible but inactive when ineligible', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-sales-orders-nav-disabled="desktop"', false)
        ->assertDontSee('href="' . route('sales.orders.index') . '"', false);
});

it('renders purchase orders as clickable when eligible', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    Supplier::query()->create([
        'tenant_id' => $tenant->id,
        'company_name' => 'Supply Co',
    ]);

    ($this->makeItem)($tenant, $uom, ['is_purchasable' => true]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-purchase-orders-nav-link="desktop"', false)
        ->assertSee('href="' . route('purchasing.orders.index') . '"', false);
});

it('renders purchase orders as visible but inactive when ineligible', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-purchase-orders-nav-disabled="desktop"', false)
        ->assertDontSee('href="' . route('purchasing.orders.index') . '"', false);
});

it('renders make orders as clickable when eligible', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermission)($user, 'inventory-make-orders-view');

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Recipe A',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-make-orders-nav-link="desktop"', false)
        ->assertSee('href="' . route('manufacturing.make-orders.index') . '"', false);
});

it('renders make orders as visible but inactive when ineligible', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-make-orders-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-make-orders-nav-disabled="desktop"', false)
        ->assertDontSee('href="' . route('manufacturing.make-orders.index') . '"', false);
});

it('renders disabled nav items without clickable hrefs', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $content = ($this->render)($user)
        ->assertOk()
        ->getContent();

    expect($content)->toContain('data-sales-orders-nav-disabled="desktop"')
        ->and($content)->toContain('data-purchase-orders-nav-disabled="desktop"')
        ->and($content)->toContain('data-make-orders-nav-disabled="desktop"')
        ->and($content)->not->toContain('href="' . route('sales.orders.index') . '"')
        ->and($content)->not->toContain('href="' . route('purchasing.orders.index') . '"')
        ->and($content)->not->toContain('href="' . route('manufacturing.make-orders.index') . '"');
});

it('renders enabled nav items with their expected route hrefs', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $makeItem = ($this->makeItem)($tenant, $uom, [
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    Customer::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Customer A',
        'status' => Customer::STATUS_ACTIVE,
    ]);

    Supplier::query()->create([
        'tenant_id' => $tenant->id,
        'company_name' => 'Supplier A',
    ]);

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $makeItem->id,
        'name' => 'Recipe A',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('href="' . route('sales.orders.index') . '"', false)
        ->assertSee('href="' . route('purchasing.orders.index') . '"', false)
        ->assertSee('href="' . route('manufacturing.make-orders.index') . '"', false);
});

it('preserves existing permission gates for gated order navigation items', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    Customer::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Customer A',
        'status' => Customer::STATUS_ACTIVE,
    ]);

    Supplier::query()->create([
        'tenant_id' => $tenant->id,
        'company_name' => 'Supplier A',
    ]);

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Recipe A',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertDontSee('data-sales-orders-nav-link="desktop"', false)
        ->assertDontSee('data-purchase-orders-nav-link="desktop"', false)
        ->assertDontSee('data-make-orders-nav-link="desktop"', false);
});

it('preserves active state behavior for enabled order navigation links', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, ['is_manufacturable' => true]);

    ($this->grantPermission)($user, 'inventory-make-orders-view');

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Recipe A',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    ($this->render)($user, 'manufacturing.make-orders.index')
        ->assertOk()
        ->assertSee('aria-current="page"', false)
        ->assertSee('data-make-orders-nav-link="desktop"', false);
});

it('renders active dashboard route state correctly', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('aria-current="page"', false);
});

it('renders dropdown triggers for grouped navigation', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-nav-dropdown-trigger="purchasing"', false)
        ->assertSee('data-nav-dropdown-trigger="manufacturing"', false);
});

it('renders grouped dropdown links with the correct hrefs', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, [
        'is_purchasable' => true,
        'is_manufacturable' => true,
    ]);

    ($this->grantPermissions)($user, [
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
        'inventory-materials-view',
        'inventory-adjustments-view',
        'inventory-recipes-view',
        'inventory-make-orders-view',
        'inventory-materials-manage',
    ]);

    Supplier::query()->create([
        'tenant_id' => $tenant->id,
        'company_name' => 'Supplier A',
    ]);

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Recipe A',
        'output_quantity' => '1.000000',
        'is_active' => true,
        'is_default' => false,
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('href="' . route('purchasing.orders.index') . '"', false)
        ->assertSee('href="' . route('purchasing.suppliers.index') . '"', false)
        ->assertSee('href="' . route('inventory.index') . '"', false)
        ->assertSee('href="' . route('inventory.counts.index') . '"', false)
        ->assertSee('href="' . route('materials.index') . '"', false)
        ->assertSee('href="' . route('manufacturing.recipes.index') . '"', false)
        ->assertSee('href="' . route('manufacturing.make-orders.index') . '"', false)
        ->assertSee('href="' . route('manufacturing.uoms.index') . '"', false)
        ->assertSee('href="' . route('manufacturing.uom-conversions.index') . '"', false)
        ->assertSee('href="' . route('materials.uom-categories.index') . '"', false);
});

it('renders mobile navigation markup', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-nav-mobile-panel', false)
        ->assertSee('sm:hidden', false);
});

it('renders mobile nested groups as accordion sections', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, [
        'inventory-materials-view',
        'purchasing-suppliers-view',
    ]);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('data-nav-mobile-group="purchasing"', false)
        ->assertSee('data-nav-mobile-group="manufacturing"', false)
        ->assertSee('aria-controls="mobile-nav-purchasing"', false)
        ->assertSee('aria-controls="mobile-nav-manufacturing"', false);
});

it('removes old breeze navigation component usage from the navigation view source', function () {
    $source = file_get_contents(resource_path('views/layouts/navigation.blade.php'));

    expect($source)->not->toContain('<x-dropdown');
    expect($source)->not->toContain('<x-dropdown-link');
    expect($source)->not->toContain('<x-responsive-nav-link');
});

it('keeps the native nav element in the navigation view source', function () {
    $source = file_get_contents(resource_path('views/layouts/navigation.blade.php'));

    expect($source)->toContain('<nav');
});

it('uses the shared navigation refresh helper in the relevant ajax page modules', function () {
    $customerSource = file_get_contents(resource_path('js/pages/sales-customers-index.js'));
    $materialsSource = file_get_contents(resource_path('js/pages/materials-index.js'));
    $suppliersSource = file_get_contents(resource_path('js/pages/purchasing-suppliers-index.js'));
    $recipesSource = file_get_contents(resource_path('js/pages/manufacturing-recipes-index.js'));

    expect($customerSource)->toContain("refreshNavigationState(this.navigationStateUrl)")
        ->and($materialsSource)->toContain("refreshNavigationState(this.navigationStateUrl)")
        ->and($suppliersSource)->toContain("refreshNavigationState(this.navigationStateUrl)")
        ->and($recipesSource)->toContain("refreshNavigationState(this.navigationStateUrl)");
});

it('keeps shared navigation refresh local and does not introduce global javascript state', function () {
    $source = file_get_contents(resource_path('js/navigation/refresh-navigation-state.js'));

    expect($source)->not->toContain('window.')
        ->and($source)->toContain('export async function refreshNavigationState');
});

it('hides unauthorized manufacturing and purchasing links', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Materials')
        ->assertDontSee('Suppliers')
        ->assertDontSee('Orders (Make Orders)')
        ->assertDontSee('Recipes')
        ->assertDontSee('Units of Measure')
        ->assertDontSee('UoM Conversions')
        ->assertDontSee('UoM Categories');
});

it('allows super admin to see all permitted navigation groups and links', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantSuperAdmin)($user);

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Purchasing')
        ->assertSee('Manufacturing')
        ->assertSee('Suppliers')
        ->assertSee('Orders')
        ->assertSee('Inventory')
        ->assertSee('Inventory Counts')
        ->assertSee('Materials')
        ->assertSee('Recipes')
        ->assertSee('Orders (Make Orders)')
        ->assertSee('Units of Measure')
        ->assertSee('UoM Conversions')
        ->assertSee('UoM Categories');
});

it('keeps route names unchanged for dashboard and navigation destinations', function () {
    expect(route('dashboard'))->toBe(url('/dashboard'));
    expect(route('purchasing.orders.index'))->toBe(url('/purchasing/orders'));
    expect(route('purchasing.suppliers.index'))->toBe(url('/purchasing/suppliers'));
    expect(route('inventory.index'))->toBe(url('/inventory'));
    expect(route('inventory.counts.index'))->toBe(url('/inventory/counts'));
    expect(route('materials.index'))->toBe(url('/materials'));
    expect(route('manufacturing.recipes.index'))->toBe(url('/manufacturing/recipes'));
    expect(route('manufacturing.make-orders.index'))->toBe(url('/manufacturing/make-orders'));
    expect(route('manufacturing.uoms.index'))->toBe(url('/manufacturing/uoms'));
    expect(route('manufacturing.uom-conversions.index'))->toBe(url('/manufacturing/uom-conversions'));
    expect(route('materials.uom-categories.index'))->toBe(url('/materials/uom-categories'));
});

it('does not show manufacturing group when only purchasing permissions are present', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'purchasing-suppliers-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Purchasing')
        ->assertDontSee('Manufacturing');
});

it('does not show purchasing group when only manufacturing permissions are present', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-materials-view');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Manufacturing')
        ->assertDontSee('Purchasing');
});
