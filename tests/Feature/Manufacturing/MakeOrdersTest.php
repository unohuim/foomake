<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\MakeOrderLine;
use App\Models\Permission;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionLine;
use App\Models\Role;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeTenant = function (string $name): Tenant {
        return Tenant::factory()->create([
            'tenant_name' => $name,
        ]);
    };

    $this->makeUom = function (Tenant $tenant): Uom {
        $suffix = (string) Str::uuid();

        $category = UomCategory::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . $suffix,
        ]);

        return Uom::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . $suffix,
            'symbol' => 'u' . str_replace('-', '', $suffix),
        ]);
    };

    $this->makeUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, string $name, bool $manufacturable = false): Item {
        return Item::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'base_uom_id' => $uom->id,
            'is_stockable' => true,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => $manufacturable,
        ]);
    };

    $this->makeRecipe = function (
        Tenant $tenant,
        Item $outputItem,
        bool $isActive = true,
        string $name = 'Recipe A',
        string $outputQuantity = '1.000000',
        string $recipeType = Recipe::TYPE_MANUFACTURING,
        bool $publishCurrent = true
    ): Recipe {
        $recipe = Recipe::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'item_id' => $outputItem->id,
            'recipe_type' => $recipeType,
            'name' => $name,
            'is_active' => $isActive,
            'output_quantity' => $outputQuantity,
        ]);

        $version = RecipeVersion::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'version_number' => 100,
            'name' => null,
            'output_quantity' => $outputQuantity,
            'recipe_type' => $recipeType,
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

    $this->addRecipeLine = function (Tenant $tenant, Recipe $recipe, Item $inputItem, string $quantity): RecipeVersionLine {
        RecipeLine::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'item_id' => $inputItem->id,
            'quantity' => $quantity,
        ]);

        $version = $recipe->currentVersion ?? $recipe->versions()->latest('id')->firstOrFail();

        return RecipeVersionLine::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'recipe_version_id' => $version->id,
            'input_item_id' => $inputItem->id,
            'uom_id' => $inputItem->base_uom_id,
            'quantity' => $quantity,
            'sort_order' => ((int) $version->lines()->max('sort_order')) + 1,
        ]);
    };

    $this->makeOrder = function (Tenant $tenant, Recipe $recipe, User $user, array $overrides = []): MakeOrder {
        $runs = (string) ($overrides['runs'] ?? $overrides['output_quantity'] ?? '1.000000');
        $recipeOutputQuantity = (string) ($recipe->currentVersion?->output_quantity ?? $recipe->output_quantity ?? '0.000000');

        $makeOrder = MakeOrder::query()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $recipe->current_version_id,
            'output_item_id' => $recipe->item_id,
            'runs' => $runs,
            'expected_output_qty' => bcmul($runs, $recipeOutputQuantity, 6),
            'actual_output_qty' => $overrides['actual_output_qty'] ?? $overrides['actual_output_quantity'] ?? null,
            'status' => 'DRAFT',
            'due_date' => null,
            'scheduled_at' => null,
            'made_at' => null,
            'created_by_user_id' => $user->id,
            'made_by_user_id' => $user->id,
        ], $overrides));

        $recipe->loadMissing(['currentVersion.lines']);

        if ($recipe->currentVersion) {
            foreach ($recipe->currentVersion->lines as $versionLine) {
                MakeOrderLine::query()->forceCreate([
                    'tenant_id' => $tenant->id,
                    'make_order_id' => $makeOrder->id,
                    'source_recipe_version_line_id' => $versionLine->id,
                    'input_item_id' => $versionLine->input_item_id,
                    'uom_id' => $versionLine->uom_id,
                    'planned_quantity' => bcmul(
                        (string) $versionLine->quantity,
                        (string) $makeOrder->runs,
                        6
                    ),
                    'actual_quantity' => null,
                    'line_type' => MakeOrderLine::TYPE_RECIPE,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $makeOrder->fresh(['recipeVersion.lines', 'lines.inputItem.baseUom']);
    };

    $this->grantPermission = function (User $user, string $slug): void {
        $permission = Permission::query()->firstOrCreate([
            'slug' => $slug,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $slug . '-' . $user->id,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    };

    $this->grantPermissions = function (User $user, array $slugs): void {
        foreach ($slugs as $slug) {
            ($this->grantPermission)($user, $slug);
        }
    };

    $this->createManufacturingWorkflowStages = function (
        Tenant $tenant,
        array $stages = [
            ['key' => 'production', 'name' => 'Production', 'button_text' => 'Production', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
            ['key' => 'completed', 'name' => 'Completed', 'button_text' => 'Completed', 'sort_order' => 20, 'is_inventory_effect_stage' => false],
        ]
    ): array {
        $domain = WorkflowDomain::query()->firstOrCreate(
            ['key' => 'manufacturing'],
            ['name' => 'Manufacturing']
        );

        return array_map(function (array $stage, int $index) use ($tenant, $domain): WorkflowStage {
            return WorkflowStage::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'workflow_domain_id' => $domain->id,
                'key' => $stage['key'],
            ], [
                'name' => $stage['name'],
                'button_text' => $stage['button_text'] ?? $stage['name'],
                'description' => $stage['name'] . ' stage.',
                'sort_order' => $stage['sort_order'],
                'is_active' => true,
                'is_inventory_effect_stage' => ($stage['is_inventory_effect_stage'] ?? ($index === 0)) === true,
            ]);
        }, $stages, array_keys($stages));
    };

    $this->extractPayload = function ($response, string $payloadId): array {
        $html = $response->getContent();
        $pattern = '/<script type="application\\/json" id="' . preg_quote($payloadId, '/') . '">\s*(.*?)\s*<\\/script>/s';

        preg_match($pattern, $html, $matches);

        expect($matches)->not->toBeEmpty();

        $json = $matches[1] ?? '';
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    };

    $this->listMakeOrders = function (User $user, array $query = []) {
        return $this->actingAs($user)->getJson(route('manufacturing.make-orders.list', $query));
    };

    $this->updateMakeOrder = function (User $user, MakeOrder $makeOrder, array $payload = []) {
        return $this->actingAs($user)->patchJson(route('manufacturing.make-orders.update', $makeOrder), $payload);
    };

    $this->archiveMakeOrder = function (User $user, MakeOrder $makeOrder) {
        return $this->actingAs($user)->deleteJson(route('manufacturing.make-orders.destroy', $makeOrder));
    };

    $this->moveMakeOrderWorkflowStage = function (User $user, MakeOrder $makeOrder, int $workflowStageId) {
        return $this->actingAs($user)->patchJson(route('manufacturing.make-orders.workflow-stage.update', $makeOrder), [
            'workflow_stage_id' => $workflowStageId,
        ]);
    };

    $this->updateMakeOrderAssignment = function (User $user, MakeOrder $makeOrder, int|string|null $madeByUserId) {
        return $this->actingAs($user)->patchJson(route('manufacturing.make-orders.assignment.update', $makeOrder), [
            'made_by_user_id' => $madeByUserId,
        ]);
    };

    $this->updateMakeOrderDueDate = function (User $user, MakeOrder $makeOrder, ?string $dueDate) {
        return $this->actingAs($user)->patchJson(route('manufacturing.make-orders.due-date.update', $makeOrder), [
            'due_date' => $dueDate,
        ]);
    };

    $this->updateMakeOrderDetails = function (User $user, MakeOrder $makeOrder, array $payload) {
        return $this->actingAs($user)->patchJson(route('manufacturing.make-orders.details.update', $makeOrder), $payload);
    };
});

test('guests are redirected to login for make orders routes', function () {
    $this->get(route('manufacturing.make-orders.index'))
        ->assertRedirect(route('login'));

    $this->post(route('manufacturing.make-orders.store'))
        ->assertRedirect(route('login'));

    $this->post(route('manufacturing.make-orders.schedule', 1))
        ->assertRedirect(route('login'));

    $this->post(route('manufacturing.make-orders.make', 1))
        ->assertRedirect(route('login'));

    $this->patch(route('manufacturing.make-orders.assignment.update', 1))
        ->assertRedirect(route('login'));
});

test('make orders schema includes nullable workflow_stage_id after migrations', function () {
    expect(Schema::hasColumn('make_orders', 'workflow_stage_id'))->toBeTrue();

    $columnInfo = collect(DB::select("PRAGMA table_info('make_orders')"))
        ->firstWhere('name', 'workflow_stage_id');

    expect($columnInfo)->not->toBeNull()
        ->and((int) ($columnInfo->notnull ?? 1))->toBe(0);
});

test('make orders schema includes canonical runs expected_output_qty and nullable actual_output_qty after migrations', function () {
    expect(Schema::hasColumn('make_orders', 'runs'))->toBeTrue()
        ->and(Schema::hasColumn('make_orders', 'expected_output_qty'))->toBeTrue()
        ->and(Schema::hasColumn('make_orders', 'actual_output_qty'))->toBeTrue();

    $columnInfo = collect(DB::select("PRAGMA table_info('make_orders')"))
        ->firstWhere('name', 'actual_output_qty');

    expect($columnInfo)->not->toBeNull()
        ->and((int) ($columnInfo->notnull ?? 1))->toBe(0);
});

test('make orders schema workflow_stage_id references workflow_stages and does not expose assigned_to_user_id', function () {
    $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('make_orders')"));
    $workflowStageForeignKey = $foreignKeys->firstWhere('from', 'workflow_stage_id');

    expect($workflowStageForeignKey)->not->toBeNull()
        ->and($workflowStageForeignKey->table ?? null)->toBe('workflow_stages')
        ->and(Schema::hasColumn('make_orders', 'assigned_to_user_id'))->toBeFalse();
});

test('make order model uses workflowStage and madeByUser relations for workflow and ownership fields', function () {
    $makeOrder = new MakeOrder();

    expect($makeOrder->workflowStage()->getForeignKeyName())->toBe('workflow_stage_id')
        ->and($makeOrder->madeByUser()->getForeignKeyName())->toBe('made_by_user_id')
        ->and(in_array('workflow_stage_id', $makeOrder->getFillable(), true))->toBeTrue()
        ->and(in_array('runs', $makeOrder->getFillable(), true))->toBeTrue()
        ->and(in_array('expected_output_qty', $makeOrder->getFillable(), true))->toBeTrue()
        ->and(in_array('actual_output_qty', $makeOrder->getFillable(), true))->toBeTrue()
        ->and(in_array('made_by_user_id', $makeOrder->getFillable(), true))->toBeTrue()
        ->and(in_array('assigned_to_user_id', $makeOrder->getFillable(), true))->toBeFalse()
        ->and(array_key_exists('runs', $makeOrder->getCasts()))->toBeTrue()
        ->and(array_key_exists('expected_output_qty', $makeOrder->getCasts()))->toBeTrue()
        ->and(array_key_exists('actual_output_qty', $makeOrder->getCasts()))->toBeTrue();
});

test('users without inventory-make-orders-view cannot access make orders index', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();
});

test('execute permission allows create and schedule but not view access', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertForbidden();

    $storeResponse = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.000000',
        ])
        ->assertCreated();

    $makeOrderId = $storeResponse->json('data.id');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrderId), [
            'due_date' => '2026-02-01',
        ])
        ->assertOk();
});

test('view permission can access make orders index and payload lists tenant scoped orders', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Output A', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Batch of Patties');

    ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.500000',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $response
        ->assertSee('Runs')
        ->assertDontSee('Output quantity')
        ->assertSee('data-page="manufacturing-make-orders"', false)
        ->assertSee('data-payload="manufacturing-make-orders-payload"', false)
        ->assertSee('<script type="application/json"', false);

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload)->toHaveKey('recipes')
        ->and($payload)->not->toHaveKey('make_orders')
        ->and($payload['storeUrl'] ?? null)->toBe(route('manufacturing.make-orders.store'));
});

test('make order selection payload uses recipe names for same-item recipes', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Patties', true);

    ($this->makeRecipe)($tenant, $output, true, 'Batch of Patties', '54.000000');
    ($this->makeRecipe)($tenant, $output, true, 'Drum of Patties', '324.000000');

    $response = $this->actingAs($user)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk()
        ->assertSee('Batch of Patties')
        ->assertSee('Drum of Patties');

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect(collect($payload['recipes'] ?? [])->pluck('name')->all())
        ->toContain('Batch of Patties', 'Drum of Patties');
});

test('make orders index is tenant scoped and empty state returns empty payload list', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-view');

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);

    $outputA = ($this->makeItem)($tenantA, $uomA, 'Tenant A Output', true);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Tenant B Output', true);

    $recipeA = ($this->makeRecipe)($tenantA, $outputA, true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);

    ($this->makeOrder)($tenantB, $recipeB, ($this->makeUser)($tenantB), [
        'runs' => '1.000000',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($userA)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $payload = ($this->extractPayload)($response, 'manufacturing-make-orders-payload');

    expect($payload['recipes'])->toBeArray();

    ($this->makeOrder)($tenantA, $recipeA, $userA, [
        'runs' => '3.000000',
        'status' => 'DRAFT',
    ]);

    $second = $this->actingAs($userA)
        ->get(route('manufacturing.make-orders.index'))
        ->assertOk();

    $payloadTwo = ($this->extractPayload)($second, 'manufacturing-make-orders-payload');

    expect($payloadTwo['recipes'])->toHaveCount(1);
    expect($payloadTwo['recipes'][0]['item_name'])->toBe($outputA->name);
});

test('list endpoint requires make order view permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    ($this->listMakeOrders)($user)
        ->assertForbidden();
});

test('list payload includes due date recipe name runs output item workflow state and calculated qty', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    [$workflowStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'prep', 'name' => 'Prep', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Patties', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Patty Batch', '12.500000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.000000',
        'status' => 'SCHEDULED',
        'workflow_stage_id' => $workflowStage->id,
        'due_date' => '2026-02-12',
        'scheduled_at' => now(),
    ]);

    $response = ($this->listMakeOrders)($user)
        ->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row)->toHaveKeys([
        'id',
        'recipe_id',
        'recipe_name',
        'output_item_id',
        'output_item_name',
        'runs',
        'status',
        'workflow_state',
        'due_date',
        'qty',
        'show_url',
    ]);

    expect($row['due_date'])->toBe('2026-02-12');
    expect($row['recipe_name'])->toBe('Patty Batch');
    expect($row['runs'])->toBe('2.000000');
    expect($row['output_item_name'])->toBe('Patties');
    expect($row['status'])->toBe('SCHEDULED');
    expect($row['workflow_state'])->toBe('Prep');
    expect($row['qty'])->toBe('25.000000');
    expect($row['show_url'])->toBe(route('manufacturing.make-orders.show', $makeOrder));
});

test('list payload displays draft as the visible workflow state when workflow_stage_id is null even if lifecycle status is scheduled', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Sauce Batch', '4.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => null,
        'due_date' => '2026-02-12',
        'scheduled_at' => now(),
    ]);

    $row = collect(($this->listMakeOrders)($user)->assertOk()->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row['workflow_state'])->toBe(MakeOrder::STATUS_DRAFT)
        ->and($row['status'])->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('list payload reflects renamed configured workflow stages instead of lifecycle status text', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    [$workflowStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'production', 'name' => 'Production', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Soup Batch', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $workflowStage->id,
        'scheduled_at' => now(),
    ]);

    $initialRow = collect(($this->listMakeOrders)($user)->assertOk()->json('data'))->firstWhere('id', $makeOrder->id);

    $workflowStage->forceFill(['name' => 'Cook'])->save();

    $renamedRow = collect(($this->listMakeOrders)($user)->assertOk()->json('data'))->firstWhere('id', $makeOrder->fresh()->id);

    expect($initialRow['workflow_state'])->toBe('Production')
        ->and($renamedRow['workflow_state'])->toBe('Cook')
        ->and($renamedRow['status'])->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('list qty calculation uses recipe output quantity multiplied by runs with canonical string math', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Sauce', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Sauce Batch', '1.234500');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.500000',
        'status' => 'DRAFT',
    ]);

    $response = ($this->listMakeOrders)($user)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row['qty'])->toBe(bcmul('1.234500', '2.500000', 6));
    expect($row['qty'])->toBe('3.086250');
});

test('list qty calculation does not assume float math for small decimals', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Spice Mix', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Spice Batch', '0.100000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '0.200000',
        'status' => 'DRAFT',
    ]);

    $response = ($this->listMakeOrders)($user)->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $makeOrder->id);

    expect($row['qty'])->toBe('0.020000')
        ->and($row['qty'])->not->toBe('0.02');
});

test('edit updates the existing make order and returns the selected record payload', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $outputTwo = ($this->makeItem)($tenant, $uom, 'Buns', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Bread Batch', '10.000000');
    $recipeTwo = ($this->makeRecipe)($tenant, $outputTwo, true, 'Bun Batch', '6.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '1.500000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrder)($user, $makeOrder, [
        'recipe_id' => $recipeTwo->id,
        'runs' => '3.250000',
        'due_date' => '2026-02-18',
    ])
        ->assertOk()
        ->assertJsonPath('data.recipe_id', $recipeTwo->id)
        ->assertJsonPath('data.recipe_name', 'Bun Batch')
        ->assertJsonPath('data.output_item_name', 'Buns')
        ->assertJsonPath('data.runs', '3.250000')
        ->assertJsonPath('data.due_date', '2026-02-18')
        ->assertJsonPath('data.qty', '19.500000');

    $makeOrder->refresh();

    expect($makeOrder->recipe_id)->toBe($recipeTwo->id);
    expect($makeOrder->output_item_id)->toBe($outputTwo->id);
    expect($makeOrder->runs)->toBe('3.250000');
    expect($makeOrder->expected_output_qty)->toBe('19.500000');
    expect($makeOrder->due_date?->format('Y-m-d'))->toBe('2026-02-18');
});

test('edit preserves validation behavior for recipe and runs fields', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Bread Batch', '10.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '1.500000',
        'status' => 'DRAFT',
    ]);

    ($this->updateMakeOrder)($user, $makeOrder, [
        'recipe_id' => $recipe->id,
        'runs' => '0',
        'due_date' => '2026-02-18',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    ($this->updateMakeOrder)($user, $makeOrder, [
        'recipe_id' => 999999,
        'runs' => '2.000000',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

test('details quantity update recalculates expected_output_qty and canonicalizes runs when runs change', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Expected Output Recipe', '4.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.000000',
        'status' => MakeOrder::STATUS_DRAFT,
    ]);

    ($this->updateMakeOrderDetails)($user, $makeOrder, [
        'field' => 'runs',
        'runs' => '3',
        'expected_output_qty' => '8.000000',
        'actual_output_qty' => null,
    ])->assertOk()
        ->assertJsonPath('data.runs', '3.000000')
        ->assertJsonPath('data.expected_output_qty', '12.000000');

    expect($makeOrder->fresh()->runs)->toBe('3.000000')
        ->and($makeOrder->fresh()->expected_output_qty)->toBe('12.000000');
});

test('details quantity update ignores stale submitted expected_output_qty when runs change', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Manual Expected Recipe', '4.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.000000',
        'expected_output_qty' => '9.500000',
        'status' => MakeOrder::STATUS_DRAFT,
    ]);

    ($this->updateMakeOrderDetails)($user, $makeOrder, [
        'field' => 'runs',
        'runs' => '3.000000',
        'expected_output_qty' => 'not-a-qty',
        'actual_output_qty' => null,
    ])->assertOk()
        ->assertJsonPath('data.expected_output_qty', '12.000000');

    expect($makeOrder->fresh()->runs)->toBe('3.000000')
        ->and($makeOrder->fresh()->expected_output_qty)->toBe('12.000000');
});

test('details quantity update accepts integer style runs input and still persists canonical scale 6 runs with recalculated expected output', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Integer Runs Recipe', '4.500000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.000000',
        'status' => MakeOrder::STATUS_DRAFT,
    ]);

    ($this->updateMakeOrderDetails)($user, $makeOrder, [
        'field' => 'runs',
        'runs' => '6',
    ])->assertOk()
        ->assertJsonPath('data.runs', '6.000000')
        ->assertJsonPath('data.expected_output_qty', '27.000000');

    expect($makeOrder->fresh()->runs)->toBe('6.000000')
        ->and($makeOrder->fresh()->expected_output_qty)->toBe('27.000000');
});

test('details quantity update rejects direct expected_output_qty edits and persists manual actual_output_qty edits', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Manual Detail Edit Recipe', '4.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '2.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
    ]);

    ($this->updateMakeOrderDetails)($user, $makeOrder, [
        'field' => 'expected_output_qty',
        'expected_output_qty' => '11.250000',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['field']);

    ($this->updateMakeOrderDetails)($user, $makeOrder->fresh(), [
        'field' => 'actual_output_qty',
        'actual_output_qty' => '10.125',
    ])->assertOk()
        ->assertJsonPath('data.actual_output_qty', '10.125000');

    expect($makeOrder->fresh()->expected_output_qty)->toBe('8.000000')
        ->and($makeOrder->fresh()->actual_output_qty)->toBe('10.125000');
});

test('archive transitions an eligible make order to cancelled and removes it from the active list', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-10',
        'scheduled_at' => now(),
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertOk()
        ->assertJsonPath('removed_id', $makeOrder->id)
        ->assertJsonPath('data.status', MakeOrder::STATUS_CANCELLED);

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe(MakeOrder::STATUS_CANCELLED);

    $listResponse = ($this->listMakeOrders)($user)->assertOk();

    expect(collect($listResponse->json('data'))->pluck('id')->all())->not->toContain($makeOrder->id);
});

test('archive response returns a direct row-removal contract for ajax index updates', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_DRAFT,
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertOk()
        ->assertJsonPath('removed_id', $makeOrder->id)
        ->assertJsonPath('message', 'Archived.');
});

test('archive requires execute permission even when view permission exists', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'DRAFT',
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertForbidden();
});

test('archive is tenant scoped and returns not found across tenants', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);
    $userB = ($this->makeUser)($tenantB);
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => 'SCHEDULED',
        'scheduled_at' => now(),
        'due_date' => '2026-02-02',
    ]);

    ($this->archiveMakeOrder)($userA, $makeOrderB)
        ->assertNotFound();
});

test('archive rejects made make orders as an invalid terminal state change', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'MADE',
        'made_at' => now(),
        'made_by_user_id' => $user->id,
    ]);

    ($this->archiveMakeOrder)($user, $makeOrder)
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Made make orders cannot be archived.',
        ]);

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe('MADE');
});

test('line removal requires execute permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user);

    $line = MakeOrderLine::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'make_order_id' => $makeOrder->id,
        'source_recipe_version_line_id' => null,
        'input_item_id' => $input->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '2.000000',
        'actual_quantity' => null,
        'line_type' => MakeOrderLine::TYPE_MANUAL_ADJUSTMENT,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.make-orders.lines.destroy', [$makeOrder, $line]))
        ->assertForbidden();
});

test('line removal is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');

    $userB = ($this->makeUser)($tenantB);
    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $inputB = ($this->makeItem)($tenantB, $uomB, 'Salt B');
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB);

    $lineB = MakeOrderLine::query()->forceCreate([
        'tenant_id' => $tenantB->id,
        'make_order_id' => $makeOrderB->id,
        'source_recipe_version_line_id' => null,
        'input_item_id' => $inputB->id,
        'uom_id' => $uomB->id,
        'planned_quantity' => '2.000000',
        'actual_quantity' => null,
        'line_type' => MakeOrderLine::TYPE_MANUAL_ADJUSTMENT,
    ]);

    $this->actingAs($userA)
        ->deleteJson(route('manufacturing.make-orders.lines.destroy', [$makeOrderB, $lineB]))
        ->assertNotFound();
});

test('line removal returns remaining ingredient rows for instant ui reconciliation', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $inputA = ($this->makeItem)($tenant, $uom, 'Salt');
    $inputB = ($this->makeItem)($tenant, $uom, 'Yeast');
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user);

    $lineA = MakeOrderLine::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'make_order_id' => $makeOrder->id,
        'source_recipe_version_line_id' => null,
        'input_item_id' => $inputA->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '2.000000',
        'actual_quantity' => null,
        'line_type' => MakeOrderLine::TYPE_MANUAL_ADJUSTMENT,
    ]);

    $lineB = MakeOrderLine::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'make_order_id' => $makeOrder->id,
        'source_recipe_version_line_id' => null,
        'input_item_id' => $inputB->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '1.000000',
        'actual_quantity' => null,
        'line_type' => MakeOrderLine::TYPE_MANUAL_ADJUSTMENT,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson(route('manufacturing.make-orders.lines.destroy', [$makeOrder, $lineA]))
        ->assertOk()
        ->assertJsonPath('deleted_line_id', $lineA->id);

    expect(collect($response->json('lines'))->pluck('id')->all())
        ->toBe([$lineB->id])
        ->and(collect($response->json('lines'))->pluck('id')->all())
        ->not->toContain($lineA->id);
});

test('line removal is blocked for invalid terminal make order states', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_MADE,
    ]);

    $line = MakeOrderLine::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'make_order_id' => $makeOrder->id,
        'source_recipe_version_line_id' => null,
        'input_item_id' => $input->id,
        'uom_id' => $uom->id,
        'planned_quantity' => '2.000000',
        'actual_quantity' => null,
        'line_type' => MakeOrderLine::TYPE_MANUAL_ADJUSTMENT,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('manufacturing.make-orders.lines.destroy', [$makeOrder, $line]))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Only draft or scheduled make orders can be edited.',
        ]);
});

test('cancelled make orders remain terminal for schedule and make actions', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_CANCELLED,
        'due_date' => '2026-02-12',
        'scheduled_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-02-13',
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Cancelled make orders cannot be scheduled.',
        ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Cancelled make orders cannot be made.',
        ]);
});

test('create draft make order validates payload and does not create stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id', 'runs']);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => 999999,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '-1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '0',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.0000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    $beforeMoves = StockMove::query()->count();

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '2.000000',
        ])
        ->assertCreated();

    $makeOrderId = $response->json('data.id');
    $makeOrder = MakeOrder::query()->findOrFail($makeOrderId);

    expect($makeOrder->status)->toBe('DRAFT');
    expect($makeOrder->output_item_id)->toBe($output->id);
    expect($makeOrder->runs)->toBe('2.000000');
    expect($makeOrder->expected_output_qty)->toBe('2.000000');
    expect($makeOrder->actual_output_qty)->toBeNull();
    expect($makeOrder->created_by_user_id)->toBe($user->id);
    expect($makeOrder->made_by_user_id)->toBe($user->id);
    expect($makeOrder->workflow_stage_id)->toBeNull();
    expect($makeOrder->made_at)->toBeNull();
    expect($response->json('data.runs'))->toBe('2.000000');
    expect($response->json('data.expected_output_qty'))->toBe('2.000000');
    expect($response->json('data.made_by_user_id'))->toBe($user->id);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('recipe entry point make order creation auto assigns made_by_user_id snapshots current version lines and returns the make order detail url', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Entry Point Recipe', '5.000000');
    $inputA = ($this->makeItem)($tenant, $uom, 'Flour');
    $inputB = ($this->makeItem)($tenant, $uom, 'Water');
    $lineA = ($this->addRecipeLine)($tenant, $recipe, $inputA, '2.500000');
    $lineB = ($this->addRecipeLine)($tenant, $recipe, $inputB, '1.250000');

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
            'runs' => '1.000000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.made_by_user_id', $user->id);

    $makeOrderId = (int) $response->json('data.id');
    $makeOrder = MakeOrder::query()->findOrFail($makeOrderId);
    $snapshotRows = MakeOrderLine::query()
        ->where('make_order_id', $makeOrderId)
        ->orderBy('id')
        ->get(['source_recipe_version_line_id', 'input_item_id', 'planned_quantity'])
        ->map(fn (MakeOrderLine $line): array => [
            'source_recipe_version_line_id' => (int) $line->source_recipe_version_line_id,
            'input_item_id' => (int) $line->input_item_id,
            'planned_quantity' => (string) $line->planned_quantity,
        ])->all();

    expect($makeOrder->made_by_user_id)->toBe($user->id)
        ->and($makeOrder->created_by_user_id)->toBe($user->id)
        ->and($makeOrder->recipe_version_id)->toBe($recipe->fresh()->current_version_id)
        ->and($makeOrder->workflow_stage_id)->toBeNull()
        ->and($makeOrder->status)->toBe(MakeOrder::STATUS_DRAFT)
        ->and($response->json('data.show_url'))->toBe(route('manufacturing.make-orders.show', $makeOrderId))
        ->and($snapshotRows)->toBe([
            [
                'source_recipe_version_line_id' => $lineA->id,
                'input_item_id' => $inputA->id,
                'planned_quantity' => '2.500000',
            ],
            [
                'source_recipe_version_line_id' => $lineB->id,
                'input_item_id' => $inputB->id,
                'planned_quantity' => '1.250000',
            ],
        ]);
});

test('recipe scoped make order creation rejects recipes without a current published version', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Draft Only Recipe', '5.000000', Recipe::TYPE_MANUFACTURING, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.recipes.make-orders.store', $recipe), [
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);
});

test('create rejects inactive recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, false);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);
});

test('create accepts fractional runs and stores them without float conversion', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Fractional Runs Recipe', '54.000000');

    $response = $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipe->id,
            'runs' => '2.500000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.runs', '2.500000');

    $makeOrder = MakeOrder::query()->findOrFail($response->json('data.id'));

    expect($makeOrder->runs)->toBe('2.500000')
        ->and($makeOrder->expected_output_qty)->toBe('135.000000');
});

test('schedule enters workflow by assigning the first configured manufacturing stage and keeps stock moves unchanged', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    [$firstStage, $secondStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'prep', 'name' => 'Prep', 'sort_order' => 5, 'is_inventory_effect_stage' => true],
        ['key' => 'cook', 'name' => 'Cook', 'sort_order' => 15, 'is_inventory_effect_stage' => false],
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true);

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'DRAFT',
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-02-10',
        ])
        ->assertOk()
        ->assertJsonPath('data.workflow_stage_id', $firstStage->id)
        ->assertJsonPath('data.workflow_state', 'Prep');

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe('SCHEDULED');
    expect($makeOrder->workflow_stage_id)->toBe($firstStage->id);
    expect($makeOrder->due_date?->format('Y-m-d'))->toBe('2026-02-10');
    expect($makeOrder->scheduled_at)->not->toBeNull();
    expect($makeOrder->made_at)->toBeNull();
    expect($makeOrder->workflow_stage_id)->not->toBe($secondStage->id);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('schedule rejects inactive recipe invalid due date and missing active workflow stages', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, false);

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => 'DRAFT',
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => 'not-a-date',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrder), [
            'due_date' => '2026-02-10',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $activeRecipe = ($this->makeRecipe)($tenant, $output, true);
    $draftMakeOrder = ($this->makeOrder)($tenant, $activeRecipe, $user, [
        'status' => 'DRAFT',
    ]);

    $domain = WorkflowDomain::query()->firstOrCreate(
        ['key' => 'manufacturing'],
        ['name' => 'Manufacturing']
    );

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', $domain->id)
        ->delete();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.schedule', $draftMakeOrder), [
            'due_date' => '2026-02-11',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow_stage_id']);
});

test('make treats make order quantity as runs and scales recipe inputs and outputs correctly', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $inputA = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $inputB = ($this->makeItem)($tenant, $uom, 'Water', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Run Scaling Recipe', '10.000000');
    ($this->addRecipeLine)($tenant, $recipe, $inputA, '2.000000');
    ($this->addRecipeLine)($tenant, $recipe, $inputB, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '3.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    $makeOrder->refresh();

    expect($makeOrder->status)->toBe('MADE');
    expect($makeOrder->made_at)->not->toBeNull();
    expect($makeOrder->made_by_user_id)->toBe($user->id);
    expect($makeOrder->actual_output_qty)->toBeNull();

    $moves = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->orderBy('id')
        ->get();

    expect($moves)->toHaveCount($beforeMoves + 3);

    $newMoves = $moves->slice(-3)->values();
    $moveByItem = $newMoves->keyBy('item_id');

    expect((string) $moveByItem[$inputA->id]->quantity)->toBe('-6.000000');
    expect($moveByItem[$inputA->id]->type)->toBe('issue');
    expect($moveByItem[$inputA->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$inputA->id]->uom_id)->toBe($inputA->base_uom_id);

    expect((string) $moveByItem[$inputB->id]->quantity)->toBe('-3.000000');
    expect($moveByItem[$inputB->id]->type)->toBe('issue');
    expect($moveByItem[$inputB->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$inputB->id]->uom_id)->toBe($inputB->base_uom_id);

    expect((string) $moveByItem[$output->id]->quantity)->toBe('30.000000');
    expect($moveByItem[$output->id]->type)->toBe('receipt');
    expect($moveByItem[$output->id]->tenant_id)->toBe($tenant->id);
    expect($moveByItem[$output->id]->uom_id)->toBe($output->base_uom_id);
});

test('make can persist actual output quantity and use it for the receipt stock move', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Actual Output Recipe', '10.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '3.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder), [
            'actual_output_qty' => '27.125',
        ])
        ->assertOk()
        ->assertJsonPath('data.actual_output_qty', '27.125000');

    $makeOrder->refresh();

    $receipt = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('source_id', $makeOrder->id)
        ->where('source_type', MakeOrder::class)
        ->where('item_id', $output->id)
        ->where('type', 'receipt')
        ->firstOrFail();

    expect($makeOrder->status)->toBe(MakeOrder::STATUS_MADE)
        ->and($makeOrder->actual_output_qty)->toBe('27.125000')
        ->and((string) $receipt->quantity)->toBe('27.125000');
});

test('make rejects invalid actual output quantity formats', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Soup', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Actual Output Validation Recipe', '10.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '3.000000',
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder), [
            'actual_output_qty' => '27.1250001',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['actual_output_qty']);

    $makeOrder->refresh();

    expect($makeOrder->actual_output_qty)->toBeNull()
        ->and($makeOrder->status)->toBe(MakeOrder::STATUS_SCHEDULED)
        ->and(StockMove::query()->count())->toBe($beforeMoves);
});

test('make is blocked when already made and creates no additional stock moves', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true);
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Make order is already made.',
        ]);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('make preserves an existing make order owner when execution is performed by another user', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $owner = ($this->makeUser)($tenant);
    $executor = ($this->makeUser)($tenant);
    ($this->grantPermission)($owner, 'inventory-make-orders-execute');
    ($this->grantPermission)($executor, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Owner Preserve Recipe', '5.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $owner, [
        'runs' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
        'made_by_user_id' => $owner->id,
    ]);

    $this->actingAs($executor)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertOk();

    expect($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_MADE)
        ->and($makeOrder->fresh()->made_by_user_id)->toBe($owner->id);
});

test('make rejects inactive recipe', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, false);
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('tenant isolation is enforced for store, schedule, and make', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');

    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermission)($userA, 'inventory-make-orders-execute');

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true);
    $userB = ($this->makeUser)($tenantB);

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.store'), [
            'recipe_id' => $recipeB->id,
            'runs' => '1.000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_id']);

    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => 'DRAFT',
    ]);

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.schedule', $makeOrderB), [
            'due_date' => '2026-02-01',
        ])
        ->assertNotFound();

    $this->actingAs($userA)
        ->postJson(route('manufacturing.make-orders.make', $makeOrderB))
        ->assertNotFound();
});

test('make blocks execution when recipe output quantity is zero', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Zero Output Recipe', '0.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => '1.000000',
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipe_version_id']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
});

test('make blocks execution when runs are negative or zero on persisted orders', function (string $runs) {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-execute');

    $uom = ($this->makeUom)($tenant);
    $input = ($this->makeItem)($tenant, $uom, 'Flour', false);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);

    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Blocked Runs Recipe', '5.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.000000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'runs' => $runs,
        'status' => 'SCHEDULED',
        'due_date' => '2026-02-01',
        'scheduled_at' => now(),
    ]);

    $beforeMoves = StockMove::query()->count();

    $this->actingAs($user)
        ->postJson(route('manufacturing.make-orders.make', $makeOrder))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['runs']);

    expect(StockMove::query()->count())->toBe($beforeMoves);
})->with([
    '0.000000',
    '-1.000000',
]);

test('make order detail loads draft workflow entry from configured workflow stages', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$firstStage, $secondStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'production', 'name' => 'Mix', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
        ['key' => 'completed', 'name' => 'Bake', 'sort_order' => 20, 'is_inventory_effect_stage' => false],
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_DRAFT,
        'due_date' => '2026-06-01',
        'scheduled_at' => null,
        'workflow_stage_id' => null,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
    ]);

    $payload = ($this->extractPayload)(
        $this->actingAs($user)->get(route('manufacturing.make-orders.show', $makeOrder))->assertOk(),
        'manufacturing-make-orders-show-payload'
    );

    expect(data_get($payload, 'workflow.current_stage'))->toBeNull()
        ->and(data_get($payload, 'workflow.available_stages'))->toHaveCount(1)
        ->and(data_get($payload, 'workflow.available_stages.0.id'))->toBe($firstStage->id)
        ->and(data_get($payload, 'workflow.available_stages.0.name'))->toBe('Mix')
        ->and(data_get($payload, 'makeOrder.workflow_state'))->toBe(MakeOrder::STATUS_DRAFT)
        ->and(data_get($payload, 'workflow.transition_url'))->toBe(route('manufacturing.make-orders.workflow-stage.update', $makeOrder));

    expect($secondStage->id)->not->toBe(data_get($payload, 'workflow.available_stages.0.id'));
});

test('moving a draft make order into workflow assigns the first configured stage by sort order', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$firstStage, $secondStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'production', 'name' => 'Scheduled', 'sort_order' => 5, 'is_inventory_effect_stage' => true],
        ['key' => 'completed', 'name' => 'Cook', 'sort_order' => 15, 'is_inventory_effect_stage' => false],
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_DRAFT,
        'workflow_stage_id' => null,
        'scheduled_at' => null,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $firstStage->id)
        ->assertOk()
        ->assertJsonPath('data.workflow_stage_id', $firstStage->id)
        ->assertJsonPath('data.workflow_state', 'Scheduled');

    expect($makeOrder->fresh()->workflow_stage_id)->toBe($firstStage->id)
        ->and($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_SCHEDULED)
        ->and($makeOrder->fresh()->scheduled_at)->not->toBeNull()
        ->and($makeOrder->fresh()->workflow_stage_id)->not->toBe($secondStage->id);
});

test('moving make order workflow stage updates workflow_stage_id without overloading lifecycle status', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage, $completedStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'workflow_stage_id' => $productionStage->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $completedStage->id)
        ->assertOk()
        ->assertJsonPath('data.workflow_stage_id', $completedStage->id)
        ->assertJsonPath('data.status', MakeOrder::STATUS_SCHEDULED)
        ->assertJsonPath('data.workflow_state', $completedStage->name);

    expect($makeOrder->fresh()->workflow_stage_id)->toBe($completedStage->id)
        ->and($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('invalid make order workflow stage transition is rejected', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'production', 'name' => 'Production', 'sort_order' => 10],
    ]);

    $otherTenant = ($this->makeTenant)('Tenant B');
    [$otherStage] = ($this->createManufacturingWorkflowStages)($otherTenant, [
        ['key' => 'foreign-stage', 'name' => 'Foreign Stage', 'sort_order' => 10],
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'workflow_stage_id' => $productionStage->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $otherStage->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow_stage_id']);
});

test('draft workflow entry is rejected when no active manufacturing workflow stage exists', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $domain = WorkflowDomain::query()->firstOrCreate(
        ['key' => 'manufacturing'],
        ['name' => 'Manufacturing']
    );

    WorkflowStage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('workflow_domain_id', $domain->id)
        ->delete();

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_DRAFT,
        'workflow_stage_id' => null,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, 999999)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow_stage_id']);
});

test('make order workflow stage transitions require execute permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermission)($user, 'inventory-make-orders-view');

    [$productionStage, $completedStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'workflow_stage_id' => $productionStage->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $completedStage->id)
        ->assertForbidden();
});

test('make order workflow stage transitions are tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    ($this->grantPermissions)($userA, ['inventory-make-orders-view', 'inventory-make-orders-execute']);
    $userB = ($this->makeUser)($tenantB);

    [$productionStageB, $completedStageB] = ($this->createManufacturingWorkflowStages)($tenantB);

    $uomA = ($this->makeUom)($tenantA);
    $uomB = ($this->makeUom)($tenantB);
    $outputA = ($this->makeItem)($tenantA, $uomA, 'Bread A', true);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeA = ($this->makeRecipe)($tenantA, $outputA, true, 'Recipe A', '5.000000');
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true, 'Recipe B', '5.000000');
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'workflow_stage_id' => $productionStageB->id,
        'tasked_by_user_id' => $userB->id,
        'made_by_user_id' => $userB->id,
    ]);

    expect($recipeA)->toBeInstanceOf(Recipe::class);

    ($this->moveMakeOrderWorkflowStage)($userA, $makeOrderB, $completedStageB->id)
        ->assertNotFound();
});

test('open workflow tasks gate make order stage transitions until completed', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage, $completedStage] = ($this->createManufacturingWorkflowStages)($tenant);
    $domain = WorkflowDomain::query()->where('key', 'manufacturing')->firstOrFail();

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
        'workflow_stage_id' => $productionStage->id,
        'tasked_by_user_id' => $user->id,
        'made_by_user_id' => $user->id,
    ]);

    Task::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'domain_record_id' => $makeOrder->id,
        'workflow_stage_id' => $productionStage->id,
        'workflow_task_template_id' => null,
        'assigned_to_user_id' => $user->id,
        'title' => 'Confirm setup',
        'description' => 'Complete setup before continuing.',
        'sort_order' => 10,
        'status' => Task::STATUS_OPEN,
        'completed_at' => null,
        'completed_by_user_id' => null,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $completedStage->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow_stage_id']);
});

test('authorized user can change make order owner via made_by_user_id without changing stage or lifecycle status', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $user->id,
        'scheduled_at' => now(),
    ]);

    $lineSnapshot = $makeOrder->lines()->orderBy('id')->get(['input_item_id', 'planned_quantity'])->map(fn (MakeOrderLine $line): array => [
        'input_item_id' => (int) $line->input_item_id,
        'planned_quantity' => (string) $line->planned_quantity,
    ])->all();
    $recipeVersionSnapshot = RecipeVersionLine::query()
        ->where('recipe_version_id', $makeOrder->recipe_version_id)
        ->orderBy('id')
        ->get(['input_item_id', 'quantity'])
        ->map(fn (RecipeVersionLine $line): array => [
            'input_item_id' => (int) $line->input_item_id,
            'quantity' => (string) $line->quantity,
        ])->all();

    ($this->updateMakeOrderAssignment)($user, $makeOrder, $assignee->id)
        ->assertOk()
        ->assertJsonPath('data.made_by_user_id', $assignee->id)
        ->assertJsonPath('workflow.made_by_user_id', $assignee->id)
        ->assertJsonPath('workflow.owner_user_name', $assignee->name);

    $makeOrder->refresh();

    expect($makeOrder->made_by_user_id)->toBe($assignee->id)
        ->and($makeOrder->workflow_stage_id)->toBe($productionStage->id)
        ->and($makeOrder->status)->toBe(MakeOrder::STATUS_SCHEDULED)
        ->and($makeOrder->lines()->orderBy('id')->get(['input_item_id', 'planned_quantity'])->map(fn (MakeOrderLine $line): array => [
            'input_item_id' => (int) $line->input_item_id,
            'planned_quantity' => (string) $line->planned_quantity,
        ])->all())->toBe($lineSnapshot)
        ->and(RecipeVersionLine::query()
            ->where('recipe_version_id', $makeOrder->recipe_version_id)
            ->orderBy('id')
            ->get(['input_item_id', 'quantity'])
            ->map(fn (RecipeVersionLine $line): array => [
                'input_item_id' => (int) $line->input_item_id,
                'quantity' => (string) $line->quantity,
            ])->all())->toBe($recipeVersionSnapshot);
});

test('authorized user can clear make order owner back to unassigned', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $assignee->id,
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderAssignment)($user, $makeOrder, null)
        ->assertOk()
        ->assertJsonPath('data.made_by_user_id', null)
        ->assertJsonPath('workflow.made_by_user_id', null)
        ->assertJsonPath('workflow.owner_user_name', null);

    expect($makeOrder->fresh()->made_by_user_id)->toBeNull()
        ->and($makeOrder->fresh()->workflow_stage_id)->toBe($productionStage->id)
        ->and($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('cross tenant make order owner user id is rejected', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $user = ($this->makeUser)($tenantA);
    $foreignAssignee = ($this->makeUser)($tenantB);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenantA);

    $uom = ($this->makeUom)($tenantA);
    $output = ($this->makeItem)($tenantA, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenantA, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenantA, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $user->id,
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderAssignment)($user, $makeOrder, $foreignAssignee->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['made_by_user_id']);

    expect($makeOrder->fresh()->made_by_user_id)->toBe($user->id)
        ->and($makeOrder->fresh()->workflow_stage_id)->toBe($productionStage->id)
        ->and($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('make order owner update requires execute permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $viewer = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermission)($viewer, 'inventory-make-orders-view');

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $viewer, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $viewer->id,
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderAssignment)($viewer, $makeOrder, $assignee->id)
        ->assertForbidden();
});

test('make order owner update is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    $assigneeB = ($this->makeUser)($tenantB);
    ($this->grantPermissions)($userA, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStageB] = ($this->createManufacturingWorkflowStages)($tenantB);

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true, 'Recipe B', '5.000000');
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStageB->id,
        'made_by_user_id' => $userB->id,
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderAssignment)($userA, $makeOrderB, $assigneeB->id)
        ->assertNotFound();
});

test('authorized user can update make order due date without mutating assignment workflow stage or snapshots', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage, $completedStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $input = ($this->makeItem)($tenant, $uom, 'Salt');
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    ($this->addRecipeLine)($tenant, $recipe, $input, '1.250000');

    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $assignee->id,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
    ]);

    $lineSnapshot = $makeOrder->lines()->orderBy('id')->get(['input_item_id', 'planned_quantity'])->map(fn (MakeOrderLine $line): array => [
        'input_item_id' => (int) $line->input_item_id,
        'planned_quantity' => (string) $line->planned_quantity,
    ])->all();
    $recipeVersionSnapshot = RecipeVersionLine::query()
        ->where('recipe_version_id', $makeOrder->recipe_version_id)
        ->orderBy('id')
        ->get(['input_item_id', 'quantity'])
        ->map(fn (RecipeVersionLine $line): array => [
            'input_item_id' => (int) $line->input_item_id,
            'quantity' => (string) $line->quantity,
        ])->all();

    ($this->updateMakeOrderDueDate)($user, $makeOrder, '2026-06-15')
        ->assertOk()
        ->assertJsonPath('data.due_date', '2026-06-15')
        ->assertJsonPath('data.made_by_user_id', $assignee->id)
        ->assertJsonPath('data.workflow_stage_id', $productionStage->id)
        ->assertJsonPath('workflow.due_date', '2026-06-15')
        ->assertJsonPath('workflow.made_by_user_id', $assignee->id)
        ->assertJsonPath('workflow.current_stage.id', $productionStage->id)
        ->assertJsonPath('workflow.next_stage_action.id', $completedStage->id)
        ->assertJsonPath('workflow.due_date_update_url', route('manufacturing.make-orders.due-date.update', $makeOrder))
        ->assertJsonPath('workflow.assignment_update_url', route('manufacturing.make-orders.assignment.update', $makeOrder));

    $makeOrder->refresh();

    expect($makeOrder->due_date?->format('Y-m-d'))->toBe('2026-06-15')
        ->and($makeOrder->made_by_user_id)->toBe($assignee->id)
        ->and($makeOrder->workflow_stage_id)->toBe($productionStage->id)
        ->and($makeOrder->status)->toBe(MakeOrder::STATUS_SCHEDULED)
        ->and($makeOrder->lines()->orderBy('id')->get(['input_item_id', 'planned_quantity'])->map(fn (MakeOrderLine $line): array => [
            'input_item_id' => (int) $line->input_item_id,
            'planned_quantity' => (string) $line->planned_quantity,
        ])->all())->toBe($lineSnapshot)
        ->and(RecipeVersionLine::query()
            ->where('recipe_version_id', $makeOrder->recipe_version_id)
            ->orderBy('id')
            ->get(['input_item_id', 'quantity'])
            ->map(fn (RecipeVersionLine $line): array => [
                'input_item_id' => (int) $line->input_item_id,
                'quantity' => (string) $line->quantity,
            ])->all())->toBe($recipeVersionSnapshot);
});

test('authorized user can clear make order due date when business rules allow it', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $user->id,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderDueDate)($user, $makeOrder, null)
        ->assertOk()
        ->assertJsonPath('data.due_date', null)
        ->assertJsonPath('workflow.due_date', null)
        ->assertJsonPath('workflow.made_by_user_id', $user->id)
        ->assertJsonPath('workflow.current_stage.id', $productionStage->id);

    expect($makeOrder->fresh()->due_date)->toBeNull()
        ->and($makeOrder->fresh()->made_by_user_id)->toBe($user->id)
        ->and($makeOrder->fresh()->workflow_stage_id)->toBe($productionStage->id)
        ->and($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('invalid make order due date update is rejected', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
    ]);

    ($this->actingAs($user)->patchJson(route('manufacturing.make-orders.due-date.update', $makeOrder), [
        'due_date' => 'not-a-date',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);

    expect($makeOrder->fresh()->due_date?->format('Y-m-d'))->toBe('2026-06-01')
        ->and($makeOrder->fresh()->workflow_stage_id)->toBe($productionStage->id)
        ->and($makeOrder->fresh()->status)->toBe(MakeOrder::STATUS_SCHEDULED);
});

test('make order due date update requires execute permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $viewer = ($this->makeUser)($tenant);
    ($this->grantPermission)($viewer, 'inventory-make-orders-view');

    [$productionStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $viewer, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderDueDate)($viewer, $makeOrder, '2026-06-10')
        ->assertForbidden();
});

test('make order due date update is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermissions)($userA, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStageB] = ($this->createManufacturingWorkflowStages)($tenantB);

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true, 'Recipe B', '5.000000');
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStageB->id,
        'due_date' => '2026-06-01',
        'scheduled_at' => now(),
    ]);

    ($this->updateMakeOrderDueDate)($userA, $makeOrderB, '2026-06-10')
        ->assertNotFound();
});

test('make order details quantity update requires execute permission', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $viewer = ($this->makeUser)($tenant);
    ($this->grantPermission)($viewer, 'inventory-make-orders-view');

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $viewer, [
        'status' => MakeOrder::STATUS_SCHEDULED,
    ]);

    ($this->updateMakeOrderDetails)($viewer, $makeOrder, [
        'field' => 'actual_output_qty',
        'actual_output_qty' => '4.500000',
    ])->assertForbidden();
});

test('make order details quantity update is tenant scoped', function () {
    $tenantA = ($this->makeTenant)('Tenant A');
    $tenantB = ($this->makeTenant)('Tenant B');
    $userA = ($this->makeUser)($tenantA);
    $userB = ($this->makeUser)($tenantB);
    ($this->grantPermissions)($userA, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $uomB = ($this->makeUom)($tenantB);
    $outputB = ($this->makeItem)($tenantB, $uomB, 'Bread B', true);
    $recipeB = ($this->makeRecipe)($tenantB, $outputB, true, 'Recipe B', '5.000000');
    $makeOrderB = ($this->makeOrder)($tenantB, $recipeB, $userB, [
        'status' => MakeOrder::STATUS_SCHEDULED,
    ]);

    ($this->updateMakeOrderDetails)($userA, $makeOrderB, [
        'field' => 'actual_output_qty',
        'actual_output_qty' => '4.500000',
    ])->assertNotFound();
});

test('moving workflow stage does not erase make order owner', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $assignee = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    [$productionStage, $completedStage] = ($this->createManufacturingWorkflowStages)($tenant);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_SCHEDULED,
        'workflow_stage_id' => $productionStage->id,
        'made_by_user_id' => $assignee->id,
        'scheduled_at' => now(),
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $completedStage->id)
        ->assertOk()
        ->assertJsonPath('data.made_by_user_id', $assignee->id)
        ->assertJsonPath('workflow.made_by_user_id', $assignee->id)
        ->assertJsonPath('workflow.owner_user_name', $assignee->name);

    expect($makeOrder->fresh()->made_by_user_id)->toBe($assignee->id)
        ->and($makeOrder->fresh()->workflow_stage_id)->toBe($completedStage->id);
});

test('entering workflow from draft does not erase make order owner and generated tasks keep independent assignees', function () {
    $tenant = ($this->makeTenant)('Tenant A');
    $user = ($this->makeUser)($tenant);
    $makeOrderAssignee = ($this->makeUser)($tenant);
    $taskAssignee = ($this->makeUser)($tenant);
    ($this->grantPermissions)($user, ['inventory-make-orders-view', 'inventory-make-orders-execute']);

    $domain = WorkflowDomain::query()->firstOrCreate(
        ['key' => 'manufacturing'],
        ['name' => 'Manufacturing']
    );

    [$productionStage, $completedStage] = ($this->createManufacturingWorkflowStages)($tenant, [
        ['key' => 'production', 'name' => 'Mix', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
        ['key' => 'completed', 'name' => 'Pack', 'sort_order' => 20, 'is_inventory_effect_stage' => false],
    ]);

    WorkflowTaskTemplate::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'workflow_domain_id' => $domain->id,
        'workflow_stage_id' => $productionStage->id,
        'title' => 'Template placeholder',
        'description' => 'Ignored runtime task seed probe.',
        'sort_order' => 1,
        'default_assignee_user_id' => $taskAssignee->id,
        'is_active' => true,
    ]);

    $uom = ($this->makeUom)($tenant);
    $output = ($this->makeItem)($tenant, $uom, 'Bread', true);
    $recipe = ($this->makeRecipe)($tenant, $output, true, 'Workflow Recipe', '5.000000');
    $makeOrder = ($this->makeOrder)($tenant, $recipe, $user, [
        'status' => MakeOrder::STATUS_DRAFT,
        'workflow_stage_id' => null,
        'made_by_user_id' => $makeOrderAssignee->id,
        'scheduled_at' => null,
    ]);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder, $productionStage->id)
        ->assertOk()
        ->assertJsonPath('data.made_by_user_id', $makeOrderAssignee->id)
        ->assertJsonPath('workflow.made_by_user_id', $makeOrderAssignee->id)
        ->assertJsonPath('workflow.current_stage.id', $productionStage->id);

    $generatedTask = Task::query()
        ->where('tenant_id', $tenant->id)
        ->where('domain_record_id', $makeOrder->id)
        ->where('workflow_stage_id', $productionStage->id)
        ->orderBy('id')
        ->first();

    expect($makeOrder->fresh()->made_by_user_id)->toBe($makeOrderAssignee->id)
        ->and($generatedTask)->not->toBeNull()
        ->and($generatedTask?->assigned_to_user_id)->toBe($taskAssignee->id)
        ->and($generatedTask?->assigned_to_user_id)->not->toBe($makeOrderAssignee->id);

    ($this->moveMakeOrderWorkflowStage)($user, $makeOrder->fresh(), $completedStage->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['workflow_stage_id']);
});
