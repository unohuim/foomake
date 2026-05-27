<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleCounter = 1;

    $this->makeTenant = function (string $name = 'Make Orders Crud Tenant'): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate([
                'slug' => $slug,
            ]);

            $role = Role::query()->create([
                'name' => 'make-orders-crud-role-' . $this->roleCounter,
            ]);

            $this->roleCounter++;

            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    };

    $this->makeUom = function (Tenant $tenant, string $name = 'Each', string $symbol = 'ea'): Uom {
        $suffix = Str::uuid()->toString();

        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Make Orders Crud Category ' . $suffix,
        ]);

        return Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $name,
            'symbol' => $symbol . '-' . substr(str_replace('-', '', $suffix), 0, 8),
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, string $name = 'Output Item'): Item {
        return Item::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => true,
        ]);
    };

    $this->makeRecipe = function (
        Tenant $tenant,
        Item $item,
        bool $isActive = true,
        string $name = 'Recipe A',
        string $outputQuantity = '1.000000',
        bool $publishCurrent = true
    ): Recipe {
        $recipe = Recipe::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'name' => $name,
            'is_active' => $isActive,
            'output_quantity' => $outputQuantity,
        ]);

        $version = RecipeVersion::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'version_number' => 100,
            'name' => null,
            'output_quantity' => $outputQuantity,
            'recipe_type' => Recipe::TYPE_MANUFACTURING,
            'status' => $publishCurrent ? RecipeVersion::STATUS_PUBLISHED : RecipeVersion::STATUS_DRAFT,
            'effective_from' => $publishCurrent ? now() : null,
            'effective_until' => null,
            'approved_at' => $publishCurrent ? now() : null,
            'approved_by_user_id' => null,
            'notes' => null,
        ]);

        if ($publishCurrent) {
            $recipe->forceFill(['current_version_id' => $version->id])->save();
        }

        return $recipe->fresh(['currentVersion', 'item.baseUom']);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        preg_match(
            '/<script[^>]+id="' . preg_quote($payloadId, '/') . '"[^>]*>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );

        expect($matches)->toHaveKey(1);

        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractCrudConfig = function ($response): array {
        preg_match("/data-crud-config='([^']+)'/", $response->getContent(), $matches);

        expect($matches)->toHaveKey(1);

        $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        return is_array($config) ? $config : [];
    };

    $this->getIndex = function (?User $user = null) {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get(route('manufacturing.make-orders.index'));
    };
});

it('1. redirects guests away from the make orders index', function (): void {
    ($this->getIndex)()
        ->assertRedirect(route('login'));
});

it('2. forbids authenticated users without make order view permission', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->getIndex)($user)->assertForbidden();
});

it('3. allows users with inventory make orders view permission to access the index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('Make Orders');
});

it('4. renders the shared crud page mount contract on the index', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    ($this->getIndex)($user)
        ->assertOk()
        ->assertSee('data-page="manufacturing-make-orders"', false)
        ->assertSee('data-payload="manufacturing-make-orders-payload"', false)
        ->assertSee('data-crud-config=', false)
        ->assertSee('data-crud-root', false);
});

it('5. payload keeps page module data and recipe options without embedding list rows', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    $uom = ($this->makeUom)($tenant);
    $item = ($this->makeItem)($tenant, $uom, 'Burger Patty');
    $recipe = ($this->makeRecipe)($tenant, $item, true, 'Patty Batch', '12.000000');

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $response = ($this->getIndex)($user)->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload['storeUrl'] ?? null)->toBe(route('manufacturing.make-orders.store'))
        ->and($payload['csrfToken'] ?? null)->toBeString()
        ->and($payload['canExecute'] ?? null)->toBeTrue()
        ->and($payload['recipes'] ?? [])->toHaveCount(1)
        ->and($payload['recipes'][0]['id'] ?? null)->toBe($recipe->id)
        ->and($payload)->not->toHaveKey('make_orders');
});

it('6. crud config identifies the make orders resource', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['resource'] ?? null)->toBe('make-orders');
});

it('7. crud config includes the list endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['list'] ?? null)->toBe(route('manufacturing.make-orders.list'));
});

it('8. crud config includes the create endpoint', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['create'] ?? null)->toBe(route('manufacturing.make-orders.store'));
});

it('9. crud config includes the update endpoint template for edit flows', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['update'] ?? null)->toBe(url('/manufacturing/make-orders/{id}'));
});

it('10. crud config includes the archive endpoint template', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['endpoints']['delete'] ?? null)->toBe(url('/manufacturing/make-orders/{id}'));
});

it('11. crud config includes the detail url template for view actions', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['detailUrlTemplate'] ?? null)->toBe(url('/manufacturing/make-orders/{id}'));
});

it('12. crud config defines the required make order columns in the requested order', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['columns'] ?? null)->toBe([
        'due_date',
        'recipe_name',
        'runs',
        'output_item_name',
        'qty',
        'workflow_state',
    ]);
});

it('13. crud config defines the required headers', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['headers'] ?? null)->toBe([
        'due_date' => 'Due Date',
        'recipe_name' => 'Recipe Name',
        'runs' => 'Runs',
        'output_item_name' => 'Output Item',
        'qty' => 'Qty',
        'workflow_state' => 'Workflow Stage',
    ]);
});

it('13b. make orders index recipe name uses the linked text contract to the detail route', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['rowDisplay']['columns']['recipe_name'] ?? [])->toBe([
        'kind' => 'linked-text',
        'urlExpression' => 'record.show_url',
    ]);
});

it('14. crud config exposes sortable columns for the shared list renderer', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['sortable'] ?? null)->toBe([
        'due_date',
        'recipe_name',
        'runs',
        'output_item_name',
        'qty',
        'workflow_state',
    ]);
});

it('15. crud labels expose the shared search and create copy', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['labels']['searchPlaceholder'] ?? null)->toBe('Search make orders')
        ->and($config['labels']['createTitle'] ?? null)->toBe('Create Make Order')
        ->and($config['labels']['createAriaLabel'] ?? null)->toBe('Create Make Order')
        ->and($config['labels']['actionsAriaLabel'] ?? null)->toBe('Archive make order');
});

it('16. crud permissions show only the create button and no unrelated toolbar buttons', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['permissions'] ?? null)->toBe([
        'showExport' => false,
        'showImport' => false,
        'showCreate' => true,
    ]);
});

it('17. crud actions expose archive as the direct row action for active make orders', function (): void {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);

    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $config = ($this->extractCrudConfig)(($this->getIndex)($user));

    expect($config['actions'] ?? null)->toBe([
        ['id' => 'archive', 'label' => 'Archive', 'tone' => 'warning'],
    ])
        ->and($config['rowActions'] ?? null)->toBe([
            'mode' => 'icon-button',
            'icon' => 'x-mark',
            'ariaLabel' => 'Archive make order',
        ]);
});

it('18. page blade does not render bespoke toolbar or table markup anymore', function (): void {
    $source = file_get_contents(resource_path('views/manufacturing/make-orders/index.blade.php'));

    expect($source)->not->toContain('No make orders yet')
        ->and($source)->not->toContain('<table class="min-w-full text-sm">')
        ->and($source)->not->toContain('Create a draft make order from an active recipe.');
});

it('19. page module mounts the shared crud renderer', function (): void {
    $source = file_get_contents(resource_path('js/pages/manufacturing-make-orders.js'));

    expect($source)->toContain("import { parseCrudConfig } from '../lib/crud-config';")
        ->and($source)->toContain("import { mountCrudRenderer } from '../lib/crud-page';")
        ->and($source)->toContain("import { createGenericCrud } from '../lib/generic-crud';")
        ->and($source)->toContain('mountCrudRenderer(');
});

it('20. page module maps the direct row action to the archive handler', function (): void {
    $source = file_get_contents(resource_path('js/pages/manufacturing-make-orders.js'));

    expect($source)->toContain("action.id === 'archive'")
        ->and($source)->toContain('archive(record)')
        ->and($source)->not->toContain("action.id === 'view'")
        ->and($source)->not->toContain("action.id === 'edit'");
});

it('21. shared renderer remains the owner of search create and direct row action markup', function (): void {
    $rendererSource = file_get_contents(resource_path('js/lib/crud-page.js'));

    expect($rendererSource)->toContain('data-crud-toolbar-create-button')
        ->and($rendererSource)->toContain('data-crud-direct-action-trigger')
        ->and($rendererSource)->toContain('data-crud-action-item-${escapeHtml(action.id)}')
        ->and($rendererSource)->toContain("d=\"M6 18 18 6M6 6l12 12\"");
});

it('21b. archive handler removes the local row without reloading the page and only shows errors on failure', function (): void {
    $source = file_get_contents(resource_path('js/pages/manufacturing-make-orders.js'));

    expect($source)->toContain('const removedId = data?.removed_id ?? record?.id;')
        ->and($source)->toContain('this.makeOrders = this.makeOrders.filter((entry) => entry.id !== removedId);')
        ->and($source)->toContain("this.showToast('error', 'Unable to archive make order.');")
        ->and($source)->not->toContain("this.showToast('success', 'Make order archived.');");
});

it('22. index view still includes the existing make order slide over partial', function (): void {
    $source = file_get_contents(resource_path('views/manufacturing/make-orders/index.blade.php'));

    expect($source)->toContain("@include('manufacturing.make-orders.partials.create-make-order-slide-over')");
});
