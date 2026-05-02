<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
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

    ($this->grantPermission)($user, 'inventory-make-orders-view');

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

    ($this->grantPermission)($user, 'purchasing-purchase-orders-create');

    ($this->render)($user)
        ->assertOk()
        ->assertSee('Orders')
        ->assertSee(route('purchasing.orders.index'), false);
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

    ($this->grantPermissions)($user, [
        'purchasing-suppliers-view',
        'purchasing-purchase-orders-create',
        'inventory-materials-view',
        'inventory-adjustments-view',
        'inventory-recipes-view',
        'inventory-make-orders-view',
        'inventory-materials-manage',
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
