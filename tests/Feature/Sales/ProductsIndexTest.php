<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;

    $this->makeTenant = function (?string $name = null): Tenant {
        $tenant = Tenant::factory()->create([
            'tenant_name' => $name ?? 'Tenant ' . $this->tenantCounter,
        ]);

        $this->tenantCounter++;

        return $tenant;
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->create([
            'name' => 'sales-products-role-' . $this->roleCounter,
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

    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Products Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Products UoM ' . $this->uomCounter,
            'symbol' => 'prod-' . $this->uomCounter,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Products Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $props = $response->viewData('page')['props'] ?? null;

        if ($payloadId === 'sales-products-index-payload' && is_array($props) && isset($props['payload'])) {
            return $props['payload'];
        }

        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->renderDashboard = function (User $user) {
        return $this->actingAs($user)->get(route('dashboard'));
    };
});

it('1. redirects unauthenticated users away from the products index', function () {
    $this->get(route('sales.products.index'))
        ->assertRedirect(route('login'));
});

it('2. forbids authenticated users without product permissions from the products index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertForbidden();
});

it('3. allows users with inventory-products-view to access the products index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Products');
});

it('4. allows users with inventory-products-manage to access the products index', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-manage');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Products');
});

it('5. renders the sales navigation products link when the user has product view permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee('data-nav-dropdown-trigger="sales"', false)
        ->assertSee(route('sales.products.index'), false)
        ->assertSee('Products');
});

it('6. does not render the sales navigation products link when the user lacks product permission', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->renderDashboard)($user)
        ->assertOk()
        ->assertDontSee(route('sales.products.index'), false);
});

it('7. keeps the sales navigation visible for a products-only user', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    ($this->renderDashboard)($user)
        ->assertOk()
        ->assertSee('data-nav-dropdown-trigger="sales"', false);
});

it('8. page payload includes the list endpoint and import endpoints', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index')
            ->where('payload.listUrl', route('sales.products.list'))
            ->where('payload.previewUrl', route('sales.products.import.preview'))
            ->where('payload.importUrl', route('sales.products.import.store'))
            ->where('payload.connectUrlBase', url('/sales/products/import-sources'))
            ->where('payload.navigationStateUrl', route('navigation.state')));
});

it('9. page payload does not embed products records as the source of truth', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Payload Hidden Product',
        'is_sellable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->missing('payload.products')
            ->where('payload.listUrl', route('sales.products.list')));
});

it('10. page payload still includes WooCommerce as an enabled source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();
    $wooCommerce = collect($response->viewData('page')['props']['payload']['sources'] ?? [])->firstWhere('value', 'woocommerce');

    expect($wooCommerce)->not->toBeNull()
        ->and($wooCommerce['enabled'] ?? null)->toBeTrue();
});

it('11. page payload may include disabled placeholder sources', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();
    $shopify = collect($response->viewData('page')['props']['payload']['sources'] ?? [])->firstWhere('value', 'shopify');

    expect($shopify)->not->toBeNull()
        ->and($shopify['enabled'] ?? null)->toBeFalse();
});

it('12. page includes the js mount element', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index'));
});

it('13. page renders the Inertia products index component', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index'));
});

it('14. page does not render old blade owned desktop or mobile containers', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('data-products-mobile', false)
        ->assertDontSee('data-products-desktop', false);
});

it('15. old mobile placeholder text is removed from the page', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('view not designed yet');
});

it('16. products heading remains on the page and renders once', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSeeText('Products')
        ->assertDontSeeText('Products are the sales-facing view of normal sellable items.');
});

it('17. old explanatory copy is removed from the page shell', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('Products are the sales-facing view of normal sellable items.');
});

it('18. old flags table column is removed from the page shell', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('<th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Flags</th>', false);
});

it('19. page does not render blade product table rows as the source of truth', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Shell Hidden Product',
        'is_sellable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('Shell Hidden Product');
});

it('20. crud config enables import actions for users who can manage product imports', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();

    $config = $response->viewData('page')['props']['crudConfig'] ?? [];

    expect($config['permissions']['showImport'] ?? null)->toBeTrue()
        ->and($config['labels']['importTitle'] ?? null)->toBe('Import Products');
});

it('21. crud config disables import actions for view only users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();

    $config = $response->viewData('page')['props']['crudConfig'] ?? [];

    expect($config['permissions']['showImport'] ?? null)->toBeFalse();
});

it('22. exposes the import config contract without server rendered import markup', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sales/Products/Index')
            ->has('importConfig'))
        ->assertDontSee('data-products-import-panel', false)
        ->assertDontSee('data-shared-import-panel', false);
});

it('23. products import drawer includes a preview loading state contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('data-products-import-preview-loading', false);

    $importComponentSource = file_get_contents(base_path('resources/js/components/ResourceImportDrawer.vue'));

    expect($importComponentSource)->toContain('loadingPreview')
        ->and($importComponentSource)->toContain('preview-loading')
        ->and($importComponentSource)->toContain('resolvedLabels.loadingPreview');
});

it('24. imported ecommerce items still appear on manufacturing materials because they are normal items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-materials-view']);

    ($this->makeItem)($tenant, $uom, [
        'name' => 'Shared Item Product',
        'is_sellable' => true,
        'external_source' => 'woocommerce',
        'external_id' => 'woo-2001',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('materials.list'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->contains('Shared Item Product'))->toBeTrue();
});

it('25. protects the invariant that no separate products table exists', function () {
    expect(Schema::hasTable('products'))->toBeFalse();
});

it('26. protects the invariant that no dedicated Product model exists', function () {
    expect(file_exists(app_path('Models/Product.php')))->toBeFalse();
});
