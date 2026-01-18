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
use InvalidArgumentException;

uses(RefreshDatabase::class);

function makeUom(): Uom
{
    $category = UomCategory::create([
        'name' => 'Category ' . uniqid(),
    ]);

    return Uom::create([
        'uom_category_id' => $category->id,
        'name' => 'Uom ' . uniqid(),
        'symbol' => 'u' . substr(uniqid(), -6),
    ]);
}

function makeTenant(): Tenant
{
    return Tenant::create([
        'tenant_name' => 'Tenant ' . uniqid(),
    ]);
}

function makeTenantUser(Tenant $tenant): User
{
    return User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
}

function makeItem(Tenant $tenant, Uom $uom, array $overrides = []): Item
{
    $data = [
        'tenant_id' => $tenant->id,
        'name' => 'Item ' . uniqid(),
        'base_uom_id' => $uom->id,
        'is_purchasable' => false,
        'is_sellable' => false,
        'is_manufacturable' => false,
    ];

    return Item::create(array_merge($data, $overrides));
}

it('executes a simple recipe and derives inventory from stock moves', function () {
    $tenant = makeTenant();
    $user = makeTenantUser($tenant);
    $uom = makeUom();

    $this->actingAs($user);

    $flour = makeItem($tenant, $uom, ['name' => 'Flour']);
    $bread = makeItem($tenant, $uom, [
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
    $action->execute($recipe, '3');

    $issueMove = StockMove::where('item_id', $flour->id)->first();
    $receiptMove = StockMove::where('item_id', $bread->id)->first();

    expect($issueMove)->not->toBeNull()
        ->and($issueMove->type)->toBe('issue')
        ->and($issueMove->quantity)->toBe('-6.000000');

    expect($receiptMove)->not->toBeNull()
        ->and($receiptMove->type)->toBe('receipt')
        ->and($receiptMove->quantity)->toBe('3.000000');

    $flour->refresh();
    $bread->refresh();

    expect($flour->onHandQuantity())->toBe('-6.000000');
    expect($bread->onHandQuantity())->toBe('3.000000');
});

it('executes recursive recipes via stock moves', function () {
    $tenant = makeTenant();
    $user = makeTenantUser($tenant);
    $uom = makeUom();

    $this->actingAs($user);

    $raw = makeItem($tenant, $uom, ['name' => 'Raw']);
    $subItem = makeItem($tenant, $uom, [
        'name' => 'Sub Item',
        'is_manufacturable' => true,
    ]);
    $parentItem = makeItem($tenant, $uom, [
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
    $action->execute($subRecipe, '1');
    $action->execute($parentRecipe, '1');

    $raw->refresh();
    $subItem->refresh();
    $parentItem->refresh();

    expect($raw->onHandQuantity())->toBe('-2.000000');
    expect($subItem->onHandQuantity())->toBe('0.000000');
    expect($parentItem->onHandQuantity())->toBe('1.000000');
});

it('prevents more than one active recipe per item', function () {
    $tenant = makeTenant();
    $user = makeTenantUser($tenant);
    $uom = makeUom();

    $this->actingAs($user);

    $item = makeItem($tenant, $uom, [
        'name' => 'Product',
        'is_manufacturable' => true,
    ]);

    Recipe::create([
        'tenant_id' => $tenant->id,
        'item_id' => $item->id,
        'is_active' => true,
    ]);

    expect(function () use ($tenant, $item) {
        Recipe::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'is_active' => true,
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('requires manufacturable items for recipes', function () {
    $tenant = makeTenant();
    $user = makeTenantUser($tenant);
    $uom = makeUom();

    $this->actingAs($user);

    $item = makeItem($tenant, $uom, ['name' => 'Non-Manufacturable']);

    expect(function () use ($tenant, $item) {
        Recipe::create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'is_active' => true,
        ]);
    })->toThrow(InvalidArgumentException::class);
});

it('scopes recipes by tenant', function () {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $userA = makeTenantUser($tenantA);
    $userB = makeTenantUser($tenantB);
    $uom = makeUom();

    $this->actingAs($userA);

    $item = makeItem($tenantA, $uom, [
        'name' => 'Scoped Item',
        'is_manufacturable' => true,
    ]);

    $recipe = Recipe::create([
        'tenant_id' => $tenantA->id,
        'item_id' => $item->id,
        'is_active' => true,
    ]);

    $this->actingAs($userB);

    expect(Recipe::find($recipe->id))->toBeNull();
});
