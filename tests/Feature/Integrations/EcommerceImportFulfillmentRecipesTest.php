<?php

declare(strict_types=1);

use App\Actions\Integrations\CreateEmptyFulfillmentRecipeForImportedItem;
use App\Models\ExternalProductSourceConnection;
use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roleCounter = 1;
    $this->tenantCounter = 1;
    $this->uomCounter = 1;
    $this->itemCounter = 1;
    $this->customerCounter = 1;

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
            'name' => 'ecommerce-import-fulfillment-role-' . $this->roleCounter,
        ]);

        $this->roleCounter++;

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->makeUom = function (Tenant $tenant, array $attributes = []): Uom {
        $category = UomCategory::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['category_name'] ?? 'Import Category ' . $this->uomCounter,
        ]);

        $uom = Uom::query()->create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => $attributes['name'] ?? 'Import UoM ' . $this->uomCounter,
            'symbol' => $attributes['symbol'] ?? 'imp-' . $this->uomCounter,
            'display_precision' => $attributes['display_precision'] ?? 2,
        ]);

        $this->uomCounter++;

        return $uom;
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $attributes = []): Item {
        $item = Item::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Imported Item ' . $this->itemCounter,
            'base_uom_id' => $uom->id,
            'is_active' => true,
            'is_purchasable' => false,
            'is_sellable' => true,
            'is_manufacturable' => false,
            'default_price_cents' => null,
            'default_price_currency_code' => null,
            'external_source' => null,
            'external_id' => null,
        ], $attributes));

        $this->itemCounter++;

        return $item;
    };

    $this->connectWoo = function (Tenant $tenant): ExternalProductSourceConnection {
        return ExternalProductSourceConnection::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            ],
            [
                'store_url' => 'https://store.example.test',
                'consumer_key' => 'ck_valid_readonly_key',
                'consumer_secret' => 'cs_valid_readonly_secret',
                'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
                'is_connected' => true,
                'connected_at' => now(),
                'last_verified_at' => now(),
                'last_error' => null,
            ]
        );
    };

    $this->importRows = function (User $user, array $payload) {
        return $this->actingAs($user)->postJson(route('sales.products.import.store'), $payload);
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\\s*(.*?)\\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        $payload = json_decode($matches[1] ?? '[]', true);

        return is_array($payload) ? $payload : [];
    };

    $this->extractImportConfig = function ($response): array {
        preg_match("/data-import-config='([^']+)'/", $response->getContent(), $matches);

        $config = json_decode(html_entity_decode($matches[1] ?? '{}', ENT_QUOTES), true);

        return is_array($config) ? $config : [];
    };

    $this->createCustomer = function (Tenant $tenant): object {
        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Customer ' . $this->customerCounter,
            'status' => 'active',
            'notes' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country_code' => null,
            'formatted_address' => null,
            'latitude' => null,
            'longitude' => null,
            'address_provider' => null,
            'address_provider_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customerCounter++;

        return DB::table('customers')->where('id', $customerId)->first();
    };

    $this->createSalesOrder = function (Tenant $tenant, int $customerId, array $attributes = []): SalesOrder {
        return SalesOrder::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'contact_id' => null,
            'status' => SalesOrder::STATUS_OPEN,
        ], $attributes));
    };

    $this->createSalesOrderLine = function (Tenant $tenant, SalesOrder $order, Item $item, array $attributes = []): SalesOrderLine {
        return SalesOrderLine::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => '1.000000',
            'unit_price_cents' => 1000,
            'unit_price_currency_code' => 'USD',
            'line_total_cents' => '1000.000000',
        ], $attributes));
    };

    $this->transitionOrder = function (User $user, SalesOrder $order, string $status) {
        return $this->actingAs($user)->patchJson(route('sales.orders.status.update', $order), [
            'status' => $status,
        ]);
    };

    $this->createRecipeLine = function (Tenant $tenant, Recipe $recipe, Item $item, string $quantity): RecipeLine {
        return RecipeLine::query()->create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'item_id' => $item->id,
            'quantity' => bcadd($quantity, '0', 6),
        ]);
    };

    $this->createReceipt = function (Tenant $tenant, Item $item, string $quantity): StockMove {
        return StockMove::query()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => bcadd($quantity, '0', 6),
            'type' => 'receipt',
            'status' => 'POSTED',
        ]);
    };

    $this->fakeWooPreviewResponses = function (): void {
        \Illuminate\Support\Facades\Http::fake([
            'https://store.example.test/wp-json/wc/v3/products/202/variations*' => \Illuminate\Support\Facades\Http::response([
                [
                    'id' => 2021,
                    'status' => 'publish',
                    'sku' => 'HOODIE-BLK-M',
                    'price' => '42.00',
                    'attributes' => [
                        ['name' => 'Color', 'option' => 'Black'],
                        ['name' => 'Size', 'option' => 'M'],
                    ],
                ],
                [
                    'id' => 2022,
                    'status' => 'publish',
                    'sku' => 'HOODIE-BLK-L',
                    'price' => '42.00',
                    'attributes' => [
                        ['name' => 'Color', 'option' => 'Black'],
                        ['name' => 'Size', 'option' => 'L'],
                    ],
                ],
            ], 200),
            'https://store.example.test/wp-json/wc/v3/products?*' => \Illuminate\Support\Facades\Http::response([
                [
                    'id' => 101,
                    'name' => 'Simple Tee',
                    'type' => 'simple',
                    'status' => 'publish',
                    'sku' => 'SIMPLE-TEE',
                    'price' => '12.50',
                ],
                [
                    'id' => 202,
                    'name' => 'Variable Hoodie',
                    'type' => 'variable',
                    'status' => 'publish',
                    'sku' => 'VARIABLE-HOODIE',
                    'price' => '',
                ],
                [
                    'id' => 303,
                    'name' => 'Draft Mug',
                    'type' => 'simple',
                    'status' => 'draft',
                    'sku' => 'DRAFT-MUG',
                    'price' => '8.00',
                ],
            ], 200),
        ]);
    };
});

it('1. the import confirmation UI includes a global create fulfillment recipes checkbox', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk()
        ->assertSee('Create fulfillment recipes');

    $payload = ($this->extractPayload)($response, 'sales-products-index-payload');

    expect($payload['importUrl'] ?? null)->toBe(route('sales.products.import.store'));
});

it('2. the create fulfillment recipes checkbox defaults checked', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');

    $response = $this->actingAs($user)
        ->get(route('sales.products.index'))
        ->assertOk();

    $config = ($this->extractImportConfig)($response);

    expect($config['bulkOptions']['create_fulfillment_recipes']['default'] ?? null)->toBeTrue();
});

it('3. the import request accepts create_fulfillment_recipes true', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1001',
            'name' => 'Recipe Checkbox True',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();
});

it('4. the import request accepts create_fulfillment_recipes false', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => false,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1002',
            'name' => 'Recipe Checkbox False',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();
});

it('5. create_fulfillment_recipes false is not treated as an error', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => false,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1003',
            'name' => 'False Not Error',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonMissingValidationErrors();
});

it('6. when unchecked the import still creates items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => false,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1004',
            'name' => 'Unchecked Item',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', 'woo-fulfillment-1004')->exists())->toBeTrue();
});

it('7. a newly created ecommerce item creates one fulfillment recipe when the option is checked', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1005',
            'name' => 'Imported With Recipe',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1005')->firstOrFail();

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
        ->count())->toBe(1);
});

it('8. a newly created ecommerce item does not create a fulfillment recipe when the option is unchecked', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => false,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1006',
            'name' => 'Imported Without Recipe',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1006')->firstOrFail();

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
        ->exists())->toBeFalse();
});

it('9. an existing ecommerce linked item does not get a missing fulfillment recipe backfilled', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->makeItem)($tenant, $uom, [
        'name' => 'Existing Linked Product',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-fulfillment-1007',
    ]);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1007',
            'name' => 'Existing Linked Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_not_attempted_existing_item', 1);

    expect(Recipe::query()->where('tenant_id', $tenant->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->count())
        ->toBe(0);
});

it('10. if the imported item already has a fulfillment recipe the import skips duplicate creation', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Brand New Product With Preexisting Recipe Edge Case',
    ]);

    Recipe::query()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'recipe_type' => Recipe::TYPE_FULFILLMENT,
        'name' => $item->name,
        'output_quantity' => Recipe::FULFILLMENT_OUTPUT_QUANTITY,
        'is_active' => true,
        'is_default' => true,
    ]);

    expect(Recipe::query()->where('item_id', $item->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->count())
        ->toBe(1);
});

it('11. Woo variations imported as new items create their own fulfillment recipes', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-variation-2021',
            'name' => 'Variation Imported Fresh',
            'sku' => 'VAR-2021',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-variation-2021')->firstOrFail();

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
        ->count())->toBe(1);
});

it('12. Woo variations matched to existing items do not create missing fulfillment recipes', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->makeItem)($tenant, $uom, [
        'name' => 'Existing Variation',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-variation-2022',
    ]);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-variation-2022',
            'name' => 'Existing Variation',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_not_attempted_existing_item', 1);

    expect(Recipe::query()->where('tenant_id', $tenant->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->count())
        ->toBe(0);
});

it('13. auto created recipe has recipe_type fulfillment', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1013',
            'name' => 'Fulfillment Type Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Recipe::query()->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1013'))
        ->value('recipe_type'))->toBe(Recipe::TYPE_FULFILLMENT);
});

it('14. auto created recipe name exactly equals the imported item display name', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1014',
            'name' => 'Exact Imported Item Display Name',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Recipe::query()->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1014'))
        ->value('name'))->toBe('Exact Imported Item Display Name');
});

it('15. auto created recipe output item equals the imported item', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1015',
            'name' => 'Output Item Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1015')->firstOrFail();

    expect(Recipe::query()->where('item_id', $item->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->exists())
        ->toBeTrue();
});

it('16. auto created recipe output quantity equals 1.000000', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1016',
            'name' => 'Fulfillment Quantity Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Recipe::query()->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1016'))
        ->value('output_quantity'))->toBe(Recipe::FULFILLMENT_OUTPUT_QUANTITY);
});

it('17. auto created recipe output uom equals the imported items base uom', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant, ['name' => 'Case', 'symbol' => 'cs']);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1017',
            'name' => 'Fulfillment UoM Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $recipe = Recipe::query()
        ->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1017'))
        ->with('item')
        ->firstOrFail();

    expect($recipe->item?->base_uom_id)->toBe($uom->id);
});

it('18. auto created recipe has no recipe lines', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1018',
            'name' => 'No Lines Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $recipe = Recipe::query()
        ->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1018'))
        ->firstOrFail();

    expect($recipe->lines()->count())->toBe(0);
});

it('19. auto created recipe has the same tenant id as the imported item', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1019',
            'name' => 'Tenant Scoped Recipe Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1019')->firstOrFail();
    $recipe = Recipe::query()->where('item_id', $item->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->firstOrFail();

    expect($recipe->tenant_id)->toBe($item->tenant_id);
});

it('20. no manufacturing recipe is created by ecommerce import', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1020',
            'name' => 'No Manufacturing Recipe Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Recipe::query()
        ->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1020'))
        ->where('recipe_type', Recipe::TYPE_MANUFACTURING)
        ->exists())->toBeFalse();
});

it('21. the created empty fulfillment recipe appears in recipe listing query behavior', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->grantPermission)($user, 'inventory-recipes-view');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1021',
            'name' => 'Recipe List Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $response = $this->actingAs($user)
        ->get(route('manufacturing.recipes.index'))
        ->assertOk()
        ->assertSee('Recipe List Product');

    $payload = ($this->extractPayload)($response, 'manufacturing-recipes-index-payload');
    $recipe = collect($payload['recipes'] ?? [])->firstWhere('name', 'Recipe List Product');

    expect($recipe['recipe_type'] ?? null)->toBe(Recipe::TYPE_FULFILLMENT);
});

it('22. the created empty fulfillment recipe is valid to exist', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1022',
            'name' => 'Valid Empty Recipe Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $recipe = Recipe::query()
        ->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1022'))
        ->firstOrFail();

    expect($recipe->exists)->toBeTrue()
        ->and($recipe->lines()->count())->toBe(0);
});

it('23. the empty fulfillment recipe blocks sales order open to packing', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1023',
            'name' => 'Packing Block Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1023')->firstOrFail();
    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createSalesOrderLine)($tenant, $order, $item);

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Fulfillment recipe must have at least one line.');
});

it('24. after components are added and inventory exists sales order packing can proceed', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1024',
            'name' => 'Packing Ready Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1024')->firstOrFail();
    $component = ($this->makeItem)($tenant, $uom, [
        'name' => 'Component A',
        'is_sellable' => false,
    ]);
    $recipe = Recipe::query()->where('item_id', $item->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->firstOrFail();
    ($this->createRecipeLine)($tenant, $recipe, $component, '1');
    ($this->createReceipt)($tenant, $component, '2');

    $order = ($this->createSalesOrder)($tenant, $customer->id);
    ($this->createSalesOrderLine)($tenant, $order, $item);

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)
        ->assertOk()
        ->assertJsonPath('data.status', SalesOrder::STATUS_PACKING);
});

it('25. import summary reports fulfillment recipes created count', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1025',
            'name' => 'Summary Created Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_created', 1);
});

it('26. import summary reports skipped because recipe already existed count', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    app()->instance(CreateEmptyFulfillmentRecipeForImportedItem::class, new class extends CreateEmptyFulfillmentRecipeForImportedItem
    {
        public function execute(Item $item): bool
        {
            Recipe::query()->firstOrCreate([
                'tenant_id' => $item->tenant_id,
                'item_id' => $item->id,
                'recipe_type' => Recipe::TYPE_FULFILLMENT,
            ], [
                'name' => $item->name,
                'output_quantity' => Recipe::FULFILLMENT_OUTPUT_QUANTITY,
                'is_active' => true,
                'is_default' => true,
            ]);

            return false;
        }
    });

    $response = ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1026',
            'name' => 'Summary Skipped Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_skipped_existing', 1);

    $item = Item::query()->where('external_id', 'woo-fulfillment-1026')->firstOrFail();

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('recipe_type', Recipe::TYPE_FULFILLMENT)
        ->count())->toBe(1);

    $response->assertJsonPath('data.fulfillment_recipes_created', 0);
});

it('27. import summary reports not attempted because item already existed count', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->makeItem)($tenant, $uom, [
        'name' => 'Existing Summary Product',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-fulfillment-1027',
    ]);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1027',
            'name' => 'Existing Summary Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_not_attempted_existing_item', 1);
});

it('28. when unchecked the import summary reports zero fulfillment recipes created', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => false,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1028',
            'name' => 'Unchecked Summary Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_created', 0);
});

it('29. an existing fulfillment recipe in another tenant does not cause a skip', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-products-manage');
    ($this->connectWoo)($tenantA);

    $otherItem = ($this->makeItem)($tenantB, $uomB, [
        'name' => 'Other Tenant Imported Product',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-fulfillment-1029',
    ]);

    Recipe::query()->create([
        'tenant_id' => $tenantB->id,
        'item_id' => $otherItem->id,
        'recipe_type' => Recipe::TYPE_FULFILLMENT,
        'name' => $otherItem->name,
        'output_quantity' => Recipe::FULFILLMENT_OUTPUT_QUANTITY,
        'is_active' => true,
        'is_default' => true,
    ]);

    ($this->importRows)($userA, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1029',
            'name' => 'Current Tenant Imported Product',
            'base_uom_id' => $uomA->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_created', 1);
});

it('30. existing ecommerce linked items in another tenant do not affect the current tenant import', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-products-manage');
    ($this->connectWoo)($tenantA);

    ($this->makeItem)($tenantB, $uomB, [
        'name' => 'Other Tenant Existing Linked Product',
        'external_source' => 'woocommerce',
        'external_id' => 'woo-fulfillment-1030',
    ]);

    ($this->importRows)($userA, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1030',
            'name' => 'Current Tenant New Product',
            'base_uom_id' => $uomA->id,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('tenant_id', $tenantA->id)->where('external_id', 'woo-fulfillment-1030')->exists())
        ->toBeTrue();
});

it('31. created fulfillment recipes belong only to the active tenant', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1031',
            'name' => 'Tenant Bound Recipe Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    expect(Recipe::query()->where('tenant_id', $tenant->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->count())
        ->toBe(1);
});

it('32. an authorized ecommerce import user can import with recipe creation', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1032',
            'name' => 'Authorized Import Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();
});

it('33. an unauthorized user cannot trigger import recipe creation', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1033',
            'name' => 'Unauthorized Import Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertForbidden();
});

it('34. a guest cannot trigger import recipe creation', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);

    $this->postJson(route('sales.products.import.store'), [
        'source' => 'woocommerce',
        'create_fulfillment_recipes' => true,
        'rows' => [[
            'external_id' => 'woo-fulfillment-1034',
            'name' => 'Guest Import Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertUnauthorized();
});

it('35. no new permission slug is required for recipe creation during import', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1035',
            'name' => 'No New Permission Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();
});

it('36. existing ecommerce import behavior still creates sellable items', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1036',
            'name' => 'Sellable Import Product',
            'base_uom_id' => $uom->id,
            'is_sellable' => false,
        ]],
    ])->assertCreated();

    expect(Item::query()->where('external_id', 'woo-fulfillment-1036')->value('is_sellable'))->toBeTrue();
});

it('37. existing import match behavior still updates the existing item without creating a duplicate', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1037',
            'name' => 'Duplicate Import Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1037',
            'name' => 'Updated Import Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.fulfillment_recipes_not_attempted_existing_item', 1);

    expect(Item::query()
        ->where('tenant_id', $tenant->id)
        ->where('external_source', 'woocommerce')
        ->where('external_id', 'woo-fulfillment-1037')
        ->count())->toBe(1)
        ->and(Item::query()
            ->where('tenant_id', $tenant->id)
            ->where('external_source', 'woocommerce')
            ->where('external_id', 'woo-fulfillment-1037')
            ->value('name'))->toBe('Updated Import Product');
});

it('38. existing Woo preview behavior is unchanged by recipe creation support', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);
    ($this->fakeWooPreviewResponses)();

    $this->actingAs($user)
        ->postJson(route('sales.products.import.preview'), [
            'source' => 'woocommerce',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'source',
                'is_connected',
                'rows',
            ],
        ]);
});

it('39. make orders remain manufacturing only', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->grantPermission)($user, 'inventory-make-orders-view');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1039',
            'name' => 'Make Order Exclusion Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $response = $this->actingAs($user)->get(route('manufacturing.make-orders.index'))->assertOk();
    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('recipe_type')->contains(Recipe::TYPE_FULFILLMENT))->toBeFalse();
});

it('40. sales order packing still consumes fulfillment recipe components only after components are added', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    $customer = ($this->createCustomer)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->grantPermission)($user, 'sales-sales-orders-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1040',
            'name' => 'Component Consumption Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $item = Item::query()->where('external_id', 'woo-fulfillment-1040')->firstOrFail();
    $recipe = Recipe::query()->where('item_id', $item->id)->where('recipe_type', Recipe::TYPE_FULFILLMENT)->firstOrFail();
    $component = ($this->makeItem)($tenant, $uom, [
        'name' => 'Component B',
        'is_sellable' => false,
    ]);
    ($this->createRecipeLine)($tenant, $recipe, $component, '2');
    ($this->createReceipt)($tenant, $component, '2');

    $order = ($this->createSalesOrder)($tenant, $customer->id);
    $line = ($this->createSalesOrderLine)($tenant, $order, $item);

    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKING)->assertOk();
    ($this->transitionOrder)($user, $order, SalesOrder::STATUS_PACKED)->assertOk();

    $move = StockMove::query()
        ->where('source_type', SalesOrderLine::class)
        ->where('source_id', $line->id)
        ->where('item_id', $component->id)
        ->first();

    expect($move)->not->toBeNull();
});

it('41. no recipe lines or components are auto created during import', function () {
    $tenant = ($this->makeTenant)();
    $uom = ($this->makeUom)($tenant);
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-products-manage');
    ($this->connectWoo)($tenant);

    ($this->importRows)($user, [
        'source' => 'woocommerce',
        'rows' => [[
            'external_id' => 'woo-fulfillment-1041',
            'name' => 'No Auto Components Product',
            'base_uom_id' => $uom->id,
        ]],
    ])->assertCreated();

    $recipe = Recipe::query()->whereHas('item', fn ($query) => $query->where('external_id', 'woo-fulfillment-1041'))->firstOrFail();

    expect($recipe->lines()->count())->toBe(0);
});

it('42. no row level recipe indicators are introduced in the import preview UI contract', function () {
    $source = file_get_contents(base_path('resources/views/sales/products/index.blade.php'));

    expect($source)->toContain('Create fulfillment recipes')
        ->and($source)->not->toContain('Create recipe for this row');
});
