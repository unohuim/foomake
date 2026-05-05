<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemPurchaseOptionPriceController;
use App\Http\Controllers\MakeOrderController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NavigationStateController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderLineController;
use App\Http\Controllers\PurchaseOrderReceiptController;
use App\Http\Controllers\PurchaseOrderShortClosureController;
use App\Http\Controllers\PurchaseOrderStatusController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesOrderLineController;
use App\Http\Controllers\SalesOrderStatusController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPurchaseOptionController;
use App\Http\Controllers\UomCategoryController;
use App\Http\Controllers\UomConversionController;
use App\Http\Controllers\UomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/navigation/state', NavigationStateController::class)
        ->name('navigation.state');

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
    Route::get('/manufacturing/uom-conversions', [UomConversionController::class, 'index'])
        ->name('manufacturing.uom-conversions.index');
    Route::post('/manufacturing/uom-conversions', [UomConversionController::class, 'store'])
        ->name('manufacturing.uom-conversions.store');
    Route::patch('/manufacturing/uom-conversions/{conversion}', [UomConversionController::class, 'update'])
        ->name('manufacturing.uom-conversions.update');
    Route::delete('/manufacturing/uom-conversions/{conversion}', [UomConversionController::class, 'destroy'])
        ->name('manufacturing.uom-conversions.destroy');
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
    Route::get('/purchasing/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->name('purchasing.suppliers.show');
    Route::patch('/purchasing/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('purchasing.suppliers.update');
    Route::delete('/purchasing/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->name('purchasing.suppliers.destroy');
    Route::post('/purchasing/suppliers/{supplier}/purchase-options', [SupplierPurchaseOptionController::class, 'store'])
        ->name('purchasing.suppliers.purchase-options.store');
    Route::delete('/purchasing/suppliers/{supplier}/purchase-options/{option}', [SupplierPurchaseOptionController::class, 'destroy'])
        ->name('purchasing.suppliers.purchase-options.destroy');
    Route::post('/purchasing/purchase-options/{option}/prices', [ItemPurchaseOptionPriceController::class, 'store'])
        ->name('purchasing.purchase-options.prices.store');

    Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])
        ->name('purchasing.orders.index');
    Route::post('/purchasing/orders', [PurchaseOrderController::class, 'store'])
        ->name('purchasing.orders.store');
    Route::get('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->name('purchasing.orders.show');
    Route::patch('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->name('purchasing.orders.update');
    Route::put('/purchasing/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
    Route::delete('/purchasing/orders/{purchaseOrderId}', [PurchaseOrderController::class, 'destroy'])
        ->name('purchasing.orders.destroy');
    Route::patch('/purchasing/orders/{purchaseOrder}/status', [PurchaseOrderStatusController::class, 'update'])
        ->name('purchasing.orders.status.update');
    Route::post('/purchasing/orders/{purchaseOrder}/receipts', [PurchaseOrderReceiptController::class, 'store'])
        ->name('purchasing.orders.receipts.store');
    Route::post('/purchasing/orders/{purchaseOrder}/short-closures', [PurchaseOrderShortClosureController::class, 'store'])
        ->name('purchasing.orders.short-closures.store');

    Route::post('/purchasing/orders/{purchaseOrderId}/lines', [PurchaseOrderLineController::class, 'store'])
        ->name('purchasing.orders.lines.store');
    Route::patch('/purchasing/orders/{purchaseOrder}/lines/{line}', [PurchaseOrderLineController::class, 'update'])
        ->name('purchasing.orders.lines.update');
    Route::delete('/purchasing/orders/{purchaseOrderId}/lines/{lineId}', [PurchaseOrderLineController::class, 'destroy'])
        ->name('purchasing.orders.lines.destroy');

    Route::get('/sales/customers', [CustomerController::class, 'index'])
        ->name('sales.customers.index');
    Route::get('/sales/customers/{customer}', [CustomerController::class, 'show'])
        ->name('sales.customers.show');
    Route::post('/sales/customers', [CustomerController::class, 'store'])
        ->name('sales.customers.store');
    Route::patch('/sales/customers/{customer}', [CustomerController::class, 'update'])
        ->name('sales.customers.update');
    Route::delete('/sales/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('sales.customers.destroy');
    Route::post('/sales/customers/{customer}/contacts', [CustomerContactController::class, 'store'])
        ->name('sales.customers.contacts.store');
    Route::patch('/sales/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])
        ->name('sales.customers.contacts.update');
    Route::delete('/sales/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])
        ->name('sales.customers.contacts.destroy');
    Route::patch('/sales/customers/{customer}/contacts/{contact}/primary', [CustomerContactController::class, 'setPrimary'])
        ->name('sales.customers.contacts.primary.update');

    Route::get('/sales/orders', [SalesOrderController::class, 'index'])
        ->name('sales.orders.index');
    Route::post('/sales/orders', [SalesOrderController::class, 'store'])
        ->name('sales.orders.store');
    Route::patch('/sales/orders/{salesOrder}', [SalesOrderController::class, 'update'])
        ->name('sales.orders.update');
    Route::patch('/sales/orders/{salesOrder}/status', [SalesOrderStatusController::class, 'update'])
        ->name('sales.orders.status.update');
    Route::delete('/sales/orders/{salesOrder}', [SalesOrderController::class, 'destroy'])
        ->name('sales.orders.destroy');
    Route::post('/sales/orders/{salesOrder}/lines', [SalesOrderLineController::class, 'store'])
        ->name('sales.orders.lines.store');
    Route::patch('/sales/orders/{salesOrder}/lines/{line}', [SalesOrderLineController::class, 'update'])
        ->name('sales.orders.lines.update');
    Route::delete('/sales/orders/{salesOrder}/lines/{line}', [SalesOrderLineController::class, 'destroy'])
        ->name('sales.orders.lines.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/manufacturing/uom-conversions/resolve', [UomConversionController::class, 'resolve'])
    ->name('manufacturing.uom-conversions.resolve');
Route::post('/manufacturing/uom-conversions/items', [UomConversionController::class, 'storeItem'])
    ->name('manufacturing.uom-conversions.items.store');
Route::patch('/manufacturing/uom-conversions/items/{itemConversion}', [UomConversionController::class, 'updateItem'])
    ->name('manufacturing.uom-conversions.items.update');
Route::delete('/manufacturing/uom-conversions/items/{itemConversion}', [UomConversionController::class, 'destroyItem'])
    ->name('manufacturing.uom-conversions.items.destroy');

require __DIR__ . '/auth.php';
