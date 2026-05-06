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

it('8. products index payload includes ajax endpoints and navigation state url', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('sales-products-index-payload', false);

    $payload = ($this->extractPayload)($response, 'sales-products-index-payload');

    expect($payload['previewUrl'] ?? null)->toBe(route('sales.products.import.preview'))
        ->and($payload['importUrl'] ?? null)->toBe(route('sales.products.import.store'))
        ->and($payload['connectUrlBase'] ?? null)->toBe(url('/sales/products/import-sources'))
        ->and($payload['navigationStateUrl'] ?? null)->toBe(route('navigation.state'));
});

it('9. products index payload includes WooCommerce as an enabled source', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)->get(route('sales.products.index'));
    $payload = ($this->extractPayload)($response, 'sales-products-index-payload');
    $wooCommerce = collect($payload['sources'] ?? [])->firstWhere('value', 'woocommerce');

    expect($wooCommerce)->not->toBeNull()
        ->and($wooCommerce['enabled'] ?? null)->toBeTrue();
});

it('10. products index payload may include disabled placeholder sources', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $response = $this->actingAs($user)->get(route('sales.products.index'));
    $payload = ($this->extractPayload)($response, 'sales-products-index-payload');
    $shopify = collect($payload['sources'] ?? [])->firstWhere('value', 'shopify');

    expect($shopify)->not->toBeNull()
        ->and($shopify['enabled'] ?? null)->toBeFalse();
});

it('11. renders the import button for users who can manage product imports', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Import Products');
});

it('12. hides the import button for view-only users', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('Import Products');
});

it('13. renders a slide-over root for the import workflow', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('data-products-import-panel', false)
        ->assertSee('aria-modal="true"', false);
});

it('13a. products import ui includes a preview loading state contract', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-products-view', 'inventory-products-manage']);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Loading preview...');

    $pageModuleSource = file_get_contents(base_path('resources/js/pages/sales-products-index.js'));

    expect($pageModuleSource)->toContain('isLoadingPreview')
        ->and($pageModuleSource)->toContain('Loading preview...');
});

it('14. shows sellable items on the products index', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Visible Sellable Item',
        'is_sellable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Visible Sellable Item');
});

it('15. hides non-sellable items from the products index', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Hidden Non Sellable Item',
        'is_sellable' => false,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertDontSee('Hidden Non Sellable Item');
});

it('16. enforces tenant isolation on the products index', function () {
    $tenant = ($this->makeTenant)('Current Tenant');
    $otherTenant = ($this->makeTenant)('Other Tenant');
    $tenantUom = ($this->makeUom)($tenant);
    $otherUom = ($this->makeUom)($otherTenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    ($this->makeItem)($tenant, $tenantUom, [
        'name' => 'Tenant Product',
        'is_sellable' => true,
    ]);
    ($this->makeItem)($otherTenant, $otherUom, [
        'name' => 'Other Tenant Product',
        'is_sellable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Tenant Product')
        ->assertDontSee('Other Tenant Product');
});

it('17. keeps inactive sellable items visible on the products index', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Inactive Sellable Product',
        'is_sellable' => true,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Inactive Sellable Product');
});

it('18. shows inactive status for inactive sellable items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Inactive Status Product',
        'is_sellable' => true,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Inactive')
        ->assertSee('Inactive Status Product');
});

it('19. shows active status for active sellable items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');
    ($this->makeItem)($tenant, $uom, [
        'name' => 'Active Status Product',
        'is_sellable' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Active')
        ->assertSee('Active Status Product');
});

it('20. imported ecommerce items remain normal items and appear on the products index', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->grantPermission)($user, 'inventory-products-view');

    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Imported Woo Product',
        'is_sellable' => true,
        'external_source' => 'woocommerce',
        'external_id' => 'woo-1001',
    ]);

    expect($item)->toBeInstanceOf(Item::class);

    $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Imported Woo Product');
});

it('21. imported ecommerce items still appear on manufacturing materials because they are normal items', function () {
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

    $this->actingAs($user)
        ->get(route('materials.index'))
        ->assertOk()
        ->assertSee('Shared Item Product');
});

it('22. protects the invariant that no separate products table exists', function () {
    expect(Schema::hasTable('products'))->toBeFalse();
});

it('23. protects the invariant that no dedicated Product model exists', function () {
    expect(file_exists(app_path('Models/Product.php')))->toBeFalse();
});
