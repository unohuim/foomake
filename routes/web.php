<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfileController;
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

    Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::post('/materials', [ItemController::class, 'store'])->name('materials.store');
    Route::patch('/materials/{item}', [ItemController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{item}', [ItemController::class, 'destroy'])->name('materials.destroy');
    Route::get('/materials/uom-categories', [UomCategoryController::class, 'index'])
        ->name('materials.uom-categories.index');
    Route::post('/materials/uom-categories', [UomCategoryController::class, 'store'])
        ->name('materials.uom-categories.store');
    Route::patch('/materials/uom-categories/{uomCategory}', [UomCategoryController::class, 'update'])
        ->name('materials.uom-categories.update');
    Route::delete('/materials/uom-categories/{uomCategory}', [UomCategoryController::class, 'destroy'])
        ->name('materials.uom-categories.destroy');
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
