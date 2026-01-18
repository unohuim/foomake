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
use Illuminate\Database\QueryException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeUom = function (): Uom {
        $category = UomCategory::create([
            'name' => 'Category ' . uniqid(),
        ]);

        return Uom::create([
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
    $uom = ($this->makeUom)();

    $this->actingAs($user);

    $flour = ($this->makeItem)($tenant, $uom, ['name' => 'Flour']);
    $bread = ($this->makeItem)($tenant, $uom, [
        'name' => 'Bread',
        'is_manufacturable' => true,
    ]);

    $recipe = Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $bread->id,
        'is_active' => true,
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
        ->and($receiptMoves->first()->quantity)->toBe('3.000000');

    $flour->refresh();
    $bread->refresh();

    expect($flour->onHandQuantity())->toBe('-6.000000');
    expect($bread->onHandQuantity())->toBe('3.000000');
});

it('executes recursive recipes via stock moves', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)();

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
        'is_active' => true,
    ]);

    $subRecipe->lines()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $raw->id,
        'quantity' => '2.000000',
    ]);

    $parentRecipe = Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $parentItem->id,
        'is_active' => true,
    ]);

    $parentRecipe->lines()->create([
        'tenant_id' => $tenant->id,
        'item_id' => $subItem->id,
        'quantity' => '1.000000',
    ]);

    $action = new ExecuteRecipeAction();

    $action->execute($subRecipe, '1.000000');
    $action->execute($parentRecipe, '1.000000');

    $raw->refresh();
    $subItem->refresh();
    $parentItem->refresh();

    expect($raw->onHandQuantity())->toBe('-2.000000');
    expect($subItem->onHandQuantity())->toBe('0.000000');
    expect($parentItem->onHandQuantity())->toBe('1.000000');
});

it('allows multiple inactive recipes but only one active recipe per item', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)();

    $this->actingAs($user);

    $item = ($this->makeItem)($tenant, $uom, [
        'name' => 'Product',
        'is_manufacturable' => true,
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'is_active' => false,
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'is_active' => false,
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'is_active' => true,
    ]);

    expect(function () use ($tenant, $item) {
        try {
            Recipe::create([
                'tenant_id' => $tenant->id,
                'item_id' => $item->id,
                'is_active' => true,
            ]);
        } catch (QueryException $e) {
            throw new InvalidArgumentException('Only one active recipe per item is allowed.', previous: $e);
        }
    })->toThrow(InvalidArgumentException::class);
});

it('requires manufacturable items for recipes', function () {
    $tenant = ($this->makeTenant)();
    $user = ($this->makeTenantUser)($tenant);
    $uom = ($this->makeUom)();

    $this->actingAs($user);

    $item = ($this->makeItem)($tenant, $uom, ['name' => 'Non-Manufacturable']);

    expect(function () use ($tenant, $item) {
        Recipe::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'is_active' => true,
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('scopes recipes by tenant and prevents cross-tenant execution', function () {
    $tenantA = ($this->makeTenant)();
    $tenantB = ($this->makeTenant)();

    $userA = ($this->makeTenantUser)($tenantA);
    $userB = ($this->makeTenantUser)($tenantB);

    $uom = ($this->makeUom)();

    $this->actingAs($userA);

    $inputA = ($this->makeItem)($tenantA, $uom, ['name' => 'Input A']);
    $outputA = ($this->makeItem)($tenantA, $uom, [
        'name' => 'Output A',
        'is_manufacturable' => true,
    ]);

    $recipeA = Recipe::create([
        'tenant_id' => $tenantA->id,
        'item_id' => $outputA->id,
        'is_active' => true,
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
