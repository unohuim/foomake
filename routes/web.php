<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MakeOrderController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UomCategoryController;
use App\Http\Controllers\UomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/manufacturing/inventory', [InventoryController::class, 'index']);

    Route::get('/inventory/counts', [InventoryCountController::class, 'index'])
        ->name('inventory.counts.index');
    Route::get('/manufacturing/inventory-counts', [InventoryCountController::class, 'index']);
    Route::post('/inventory/counts', [InventoryCountController::class, 'store'])
        ->name('inventory.counts.store');
    Route::post('/manufacturing/inventory-counts', [InventoryCountController::class, 'store']);
    Route::get('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'show'])
        ->name('inventory.counts.show');
    Route::get('/manufacturing/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'show']);
    Route::patch('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'update'])
        ->name('inventory.counts.update');
    Route::patch('/manufacturing/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'update']);
    Route::delete('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'destroy'])
        ->name('inventory.counts.destroy');
    Route::delete('/manufacturing/inventory-counts/{inventoryCount}', [InventoryCountController::class, 'destroy']);
    Route::post('/inventory/counts/{inventoryCount}/post', [InventoryCountController::class, 'post'])
        ->name('inventory.counts.post');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/post', [InventoryCountController::class, 'post']);

    Route::post('/inventory/counts/{inventoryCount}/lines', [InventoryCountController::class, 'storeLine'])
        ->name('inventory.counts.lines.store');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/lines', [InventoryCountController::class, 'storeLine']);
    Route::patch('/inventory/counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'updateLine'])
        ->name('inventory.counts.lines.update');
    Route::patch('/manufacturing/inventory-counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'updateLine']);
    Route::delete('/inventory/counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'destroyLine'])
        ->name('inventory.counts.lines.destroy');
    Route::delete('/manufacturing/inventory-counts/{inventoryCount}/lines/{line}', [InventoryCountController::class, 'destroyLine']);

    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::post('/materials', [ItemController::class, 'store'])->name('materials.store');
    Route::patch('/materials/{item}', [ItemController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{item}', [ItemController::class, 'destroy'])->name('materials.destroy');
    Route::get('/materials/uom-categories', [UomCategoryController::class, 'index'])
        ->name('materials.uom-categories.index');
    Route::get('/manufacturing/uom-categories', [UomCategoryController::class, 'index']);
    Route::post('/materials/uom-categories', [UomCategoryController::class, 'store'])
        ->name('materials.uom-categories.store');
    Route::post('/manufacturing/uom-categories', [UomCategoryController::class, 'store']);
    Route::patch('/materials/uom-categories/{uomCategory}', [UomCategoryController::class, 'update'])
        ->name('materials.uom-categories.update');
    Route::patch('/manufacturing/uom-categories/{uomCategory}', [UomCategoryController::class, 'update']);
    Route::delete('/materials/uom-categories/{uomCategory}', [UomCategoryController::class, 'destroy'])
        ->name('materials.uom-categories.destroy');
    Route::delete('/manufacturing/uom-categories/{uomCategory}', [UomCategoryController::class, 'destroy']);
    Route::get('/materials/{item}', [ItemController::class, 'show'])
        ->name('materials.show');

    Route::get('/manufacturing/uoms', [UomController::class, 'index'])
        ->name('manufacturing.uoms.index');
    Route::post('/manufacturing/uoms', [UomController::class, 'store'])
        ->name('manufacturing.uoms.store');
    Route::patch('/manufacturing/uoms/{uom}', [UomController::class, 'update'])
        ->name('manufacturing.uoms.update');
    Route::delete('/manufacturing/uoms/{uom}', [UomController::class, 'destroy'])
        ->name('manufacturing.uoms.destroy');

    Route::get('/manufacturing/recipes', [RecipeController::class, 'index'])
        ->name('manufacturing.recipes.index');
    Route::get('/manufacturing/recipes/{recipe}', [RecipeController::class, 'show'])
        ->name('manufacturing.recipes.show');
    Route::post('/manufacturing/recipes', [RecipeController::class, 'store'])
        ->name('manufacturing.recipes.store');
    Route::patch('/manufacturing/recipes/{recipe}', [RecipeController::class, 'update'])
        ->name('manufacturing.recipes.update');
    Route::delete('/manufacturing/recipes/{recipe}', [RecipeController::class, 'destroy'])
        ->name('manufacturing.recipes.destroy');
    Route::post('/manufacturing/recipes/{recipe}/lines', [RecipeController::class, 'storeLine'])
        ->name('manufacturing.recipes.lines.store');
    Route::patch('/manufacturing/recipes/{recipe}/lines/{line}', [RecipeController::class, 'updateLine'])
        ->name('manufacturing.recipes.lines.update');
    Route::delete('/manufacturing/recipes/{recipe}/lines/{line}', [RecipeController::class, 'destroyLine'])
        ->name('manufacturing.recipes.lines.destroy');

    Route::get('/manufacturing/make-orders', [MakeOrderController::class, 'index'])
        ->name('manufacturing.make-orders.index');
    Route::post('/manufacturing/make-orders', [MakeOrderController::class, 'store'])
        ->name('manufacturing.make-orders.store');
    Route::post('/manufacturing/make-orders/{makeOrder}/schedule', [MakeOrderController::class, 'schedule'])
        ->name('manufacturing.make-orders.schedule');
    Route::post('/manufacturing/make-orders/{makeOrder}/make', [MakeOrderController::class, 'make'])
        ->name('manufacturing.make-orders.make');

    Route::get('/purchasing/suppliers', [SupplierController::class, 'index'])
        ->name('purchasing.suppliers.index');
    Route::post('/purchasing/suppliers', [SupplierController::class, 'store'])
        ->name('purchasing.suppliers.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
