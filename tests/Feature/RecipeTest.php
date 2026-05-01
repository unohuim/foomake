<?php

use App\Actions\Inventory\ExecuteRecipeAction;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\StockMove;
use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeUom = function (Tenant $tenant): Uom {
        $category = UomCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Category ' . uniqid(),
        ]);

        return Uom::create([
            'tenant_id' => $tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Uom ' . uniqid(),
            'symbol' => 'u' . substr(uniqid(), -6),
        ]);
    };

    $this->makeTenant = function (): Tenant {
        return Tenant::create([
            'tenant_name' => 'Tenant ' . uniqid(),
        ]);
    };

    $this->makeTenantUser = function (Tenant $tenant): User {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    };

    $this->makeItem = function (Tenant $tenant, Uom $uom, array $overrides = []): Item {
        $data = [
            'tenant_id' => $tenant->id,
            'name' => 'Item ' . uniqid(),
            'base_uom_id' => $uom->id,
            'is_purchasable' => false,
            'is_sellable' => false,
            'is_manufacturable' => false,
        ];

        return Item::create(array_merge($data, $overrides));
    };
});

it('executes a simple recipe and derives inventory from stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    $this->actingAs($user);

    $flour = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $bread = ($this->makeItem)($tenant, $uom, [
        'name' => 'Bread',
        'is_manufacturable' => true,
    ]);

    $recipe = Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $bread->id,
        'name' => 'Simple Recipe',
        'is_active' => true,
        'output_quantity' => '10.000000',
    ]);

    $recipe->lines()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $flour->id,
        'quantity' => '2.000000',
    ]);

    $action = new ExecuteRecipeAction();
    $action->execute($recipe, '3.000000');

    $issueMoves = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $flour->id)
        ->where('type', 'issue')
        ->orderBy('id')
        ->get();

    $receiptMoves = StockMove::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $bread->id)
        ->where('type', 'receipt')
        ->orderBy('id')
        ->get();

    expect($issueMoves)->toHaveCount(1)
        ->and($issueMoves->first()->quantity)->toBe('-6.000000');

    expect($receiptMoves)->toHaveCount(1)
        ->and($receiptMoves->first()->quantity)->toBe('30.000000');

    $flour->refresh();
    $bread->refresh();

    expect($flour->onHandQuantity())->toBe('-6.000000');
    expect($bread->onHandQuantity())->toBe('30.000000');
});

it('executes recursive recipes via stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    $this->actingAs($user);

    $raw = ($this->makeItem)($tenant, $uom, ['name' => 'Raw']);
    $subItem = ($this->makeItem)($tenant, $uom, [
        'name' => 'Sub Item',
        'is_manufacturable' => true,
    ]);
    $parentItem = ($this->makeItem)($tenant, $uom, [
        'name' => 'Parent Item',
        'is_manufacturable' => true,
    ]);

    $subRecipe = Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $subItem->id,
        'name' => 'Batch of Patties',
        'is_active' => true,
        'output_quantity' => '4.000000',
    ]);

    $subRecipe->lines()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $raw->id,
        'quantity' => '2.000000',
    ]);

    $parentRecipe = Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $parentItem->id,
        'name' => 'Drum of Patties',
        'is_active' => true,
        'output_quantity' => '2.000000',
    ]);

    $parentRecipe->lines()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $subItem->id,
        'quantity' => '1.000000',
    ]);

    $action = new ExecuteRecipeAction();

    $action->execute($subRecipe, '2.000000');
    $action->execute($parentRecipe, '4.000000');

    $raw->refresh();
    $subItem->refresh();
    $parentItem->refresh();

    expect($raw->onHandQuantity())->toBe('-4.000000');
    expect($subItem->onHandQuantity())->toBe('4.000000');
    expect($parentItem->onHandQuantity())->toBe('8.000000');
});

it('allows multiple active recipes per item', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    $this->actingAs($user);

    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Product',
        'is_manufacturable' => true,
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Batch of Patties',
        'is_active' => true,
        'output_quantity' => '1.000000',
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Drum of Patties',
        'is_active' => true,
        'output_quantity' => '2.000000',
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'name' => 'Simple Recipe',
        'is_active' => true,
        'output_quantity' => '3.000000',
    ]);

    expect(Recipe::query()
        ->where('tenant_id', $tenant->id)
        ->where('item_id', $item->id)
        ->where('is_active', true)
        ->count()
    )->toBe(3);
});

it('requires manufacturable items for recipes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    $this->actingAs($user);

    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Non-Manufacturable']);

    expect(function () use ($tenant, $item) {
        Recipe::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'name' => 'Simple Recipe',
            'is_active' => true,
            'output_quantity' => '1.000000',
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('scopes recipes by tenant and prevents cross-tenant execution', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeTenantUser)($tenantA);
    $userB = ($this->makeTenantUser)($tenantB);

    $uom = ($this->makeUom)($tenantA);

    $this->actingAs($userA);

    $inputA = ($this->makeItem)($tenantA, $uom, ['name' => 'Input A']);
    $outputA = ($this->makeItem)($tenantA, $uom, [
        'name' => 'Output A',
        'is_manufacturable' => true,
    ]);

    $recipeA = Recipe::create([
        'tenant_id' => $tenantA->id,
        'item_id' => $outputA->id,
        'name' => 'Simple Recipe',
        'is_active' => true,
        'output_quantity' => '5.000000',
    ]);

    $recipeA->lines()->create([
        'tenant_id' => $tenantA->id,
        'item_id' => $inputA->id,
        'quantity' => '1.000000',
    ]);

    $this->actingAs($userB);

    expect(Recipe::find($recipeA->id))->toBeNull();

    $action = new ExecuteRecipeAction();
    $before = StockMove::count();

    expect(function () use ($action, $recipeA) {
        $action->execute($recipeA, '1.000000');
    })->toThrow(InvalidArgumentException::class);

    expect(StockMove::count())->toBe($before);
});

it('blocks execution when recipe output quantity is zero', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)($tenant);

    $this->actingAs($user);

    $flour = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $bread = ($this->makeItem)($tenant, $uom, [
        'name' => 'Bread',
        'is_manufacturable' => true,
    ]);

    $recipe = Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $bread->id,
        'name' => 'Simple Recipe',
        'is_active' => true,
        'output_quantity' => '0.000000',
    ]);

    $recipe->lines()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $flour->id,
        'quantity' => '2.000000',
    ]);

    $action = new ExecuteRecipeAction();

    expect(function () use ($action, $recipe) {
        $action->execute($recipe, '1.000000');
    })->toThrow(InvalidArgumentException::class);
});
