<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\EmailVerificationGraceBannerController;
use App\Http\Controllers\BillingCheckoutController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemPurchaseOptionPriceController;
use App\Http\Controllers\MakeOrderController;
use App\Http\Controllers\MarketingPageController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialDraftPurchaseOrderController;
use App\Http\Controllers\MaterialPurchaseOrderController;
use App\Http\Controllers\MaterialSupplierPackageController;
use App\Http\Controllers\NavigationStateController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileConnectorController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderLineController;
use App\Http\Controllers\PurchaseOrderReceiptController;
use App\Http\Controllers\PurchaseOrderShortClosureController;
use App\Http\Controllers\PurchaseOrderStatusController;
use App\Http\Controllers\PurchaseOrderWorkflowController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesOrderLineController;
use App\Http\Controllers\SalesOrderStatusController;
use App\Http\Controllers\SalesProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPurchaseOrderController;
use App\Http\Controllers\SupplierPurchaseOptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskCompletionController;
use App\Http\Controllers\UomCategoryController;
use App\Http\Controllers\UomConversionController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowStageController;
use App\Http\Controllers\WorkflowTaskTemplateController;
use App\Http\Middleware\EnsureEmailVerifiedOrInGracePeriod;
use App\Http\Middleware\EnsureTenantBillingAccess;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/learn/{slug}', [MarketingPageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('marketing.pages.show');

Route::get('/sitemap.xml', [MarketingPageController::class, 'sitemap'])
    ->name('marketing.sitemap');

Route::view('/privacy', 'privacy')
    ->name('privacy');

Route::post('/billing/stripe/webhook', [BillingWebhookController::class, 'store'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('billing.stripe.webhook');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', EnsureEmailVerifiedOrInGracePeriod::class, EnsureTenantBillingAccess::class])
    ->name('dashboard');

Route::middleware(['auth', EnsureEmailVerifiedOrInGracePeriod::class])->group(function () {
    Route::delete('/email-verification-grace-banner', [EmailVerificationGraceBannerController::class, 'destroy'])
        ->name('verification.grace-banner.destroy');
    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing.index');
    Route::post('/billing/checkout', [BillingCheckoutController::class, 'store'])
        ->name('billing.checkout.store');
});

Route::middleware(['auth', EnsureEmailVerifiedOrInGracePeriod::class, EnsureTenantBillingAccess::class])->group(function () {
    Route::get('/navigation/state', NavigationStateController::class)
        ->name('navigation.state');

    Route::get('/inventory/counts', [InventoryCountController::class, 'index'])
        ->name('inventory.counts.index');
    Route::get('/manufacturing/inventory-counts', [InventoryCountController::class, 'index']);
    Route::get('/inventory/counts/list', [InventoryCountController::class, 'list'])
        ->name('inventory.counts.list');
    Route::get('/manufacturing/inventory-counts/list', [InventoryCountController::class, 'list']);
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
    Route::post('/inventory/counts/{inventoryCount}/submit', [InventoryCountController::class, 'submit'])
        ->name('inventory.counts.submit');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/submit', [InventoryCountController::class, 'submit']);
    Route::post('/inventory/counts/{inventoryCount}/advance', [InventoryCountController::class, 'advance'])
        ->name('inventory.counts.advance');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/advance', [InventoryCountController::class, 'advance']);
    Route::post('/inventory/counts/{inventoryCount}/previous', [InventoryCountController::class, 'previous'])
        ->name('inventory.counts.previous');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/previous', [InventoryCountController::class, 'previous']);
    Route::post('/inventory/counts/{inventoryCount}/post', [InventoryCountController::class, 'post'])
        ->name('inventory.counts.post');
    Route::post('/manufacturing/inventory-counts/{inventoryCount}/post', [InventoryCountController::class, 'post']);

    Route::get('/inventory/counts/{inventoryCount}/lines', [InventoryCountController::class, 'listLines'])
        ->name('inventory.counts.lines.index');
    Route::get('/manufacturing/inventory-counts/{inventoryCount}/lines', [InventoryCountController::class, 'listLines']);
    Route::get('/inventory/counts/{inventoryCount}/tasks', [InventoryCountController::class, 'listTasks'])
        ->name('inventory.counts.tasks.index');
    Route::get('/manufacturing/inventory-counts/{inventoryCount}/tasks', [InventoryCountController::class, 'listTasks']);
    Route::get('/inventory/counts/{inventoryCount}/notes', [NoteController::class, 'inventoryCountIndex'])
        ->name('inventory.counts.notes.index');
    Route::post('/inventory/counts/{inventoryCount}/notes', [NoteController::class, 'inventoryCountStore'])
        ->name('inventory.counts.notes.store');
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
    Route::get('/materials/list', [MaterialController::class, 'list'])->name('materials.list');
    Route::post('/materials', [ItemController::class, 'store'])->name('materials.store');
    Route::patch('/materials/{item}', [ItemController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{item}', [ItemController::class, 'destroy'])->name('materials.destroy');
    Route::get('/materials/{item}/supplier-packages', [MaterialSupplierPackageController::class, 'index'])
        ->name('materials.supplier-packages.index');
    Route::get('/materials/{item}/purchase-orders', [MaterialPurchaseOrderController::class, 'index'])
        ->name('materials.purchase-orders.index');
    Route::get('/materials/{item}/recipes', [RecipeController::class, 'listForMaterial'])
        ->name('materials.recipes.index');
    Route::get('/materials/{item}/inventory-counts', [ItemController::class, 'listInventoryCounts'])
        ->name('materials.inventory-counts.index');
    Route::get('/materials/{item}/make-orders', [MakeOrderController::class, 'listForMaterial'])
        ->name('materials.make-orders.index');
    Route::post('/materials/{item}/purchase-orders', [MaterialDraftPurchaseOrderController::class, 'store'])
        ->name('materials.purchase-orders.store');
    Route::post('/materials/{item}/supplier-packages', [MaterialSupplierPackageController::class, 'store'])
        ->name('materials.supplier-packages.store');
    Route::post('/materials/{item}/inventory-counts', [ItemController::class, 'storeInventoryCount'])
        ->name('materials.inventory-counts.store');
    Route::patch('/materials/{item}/supplier-packages/{option}', [MaterialSupplierPackageController::class, 'update'])
        ->name('materials.supplier-packages.update');
    Route::delete('/materials/{item}/supplier-packages/{option}', [MaterialSupplierPackageController::class, 'destroy'])
        ->name('materials.supplier-packages.destroy');
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
    Route::post('/manufacturing/uom-conversions/resolve', [UomConversionController::class, 'resolve'])
        ->name('manufacturing.uom-conversions.resolve');
    Route::post('/manufacturing/uom-conversions/items', [UomConversionController::class, 'storeItem'])
        ->name('manufacturing.uom-conversions.items.store');
    Route::patch('/manufacturing/uom-conversions/items/{itemConversion}', [UomConversionController::class, 'updateItem'])
        ->name('manufacturing.uom-conversions.items.update');
    Route::delete('/manufacturing/uom-conversions/items/{itemConversion}', [UomConversionController::class, 'destroyItem'])
        ->name('manufacturing.uom-conversions.items.destroy');
    Route::get('/manufacturing/recipes', [RecipeController::class, 'index'])
        ->name('manufacturing.recipes.index');
    Route::get('/manufacturing/recipes/list', [RecipeController::class, 'list'])
        ->name('manufacturing.recipes.list');
    Route::get('/manufacturing/recipes/{recipe}/versions/list', [RecipeController::class, 'listVersions'])
        ->name('manufacturing.recipes.versions.index');
    Route::get('/manufacturing/recipes/{recipe}/make-orders', [MakeOrderController::class, 'listForRecipe'])
        ->name('manufacturing.recipes.make-orders.index');
    Route::post('/manufacturing/recipes/{recipe}/make-orders', [MakeOrderController::class, 'storeForRecipe'])
        ->name('manufacturing.recipes.make-orders.store');
    Route::get('/manufacturing/recipes/{recipe}', [RecipeController::class, 'show'])
        ->name('manufacturing.recipes.show');
    Route::post('/manufacturing/recipes', [RecipeController::class, 'store'])
        ->name('manufacturing.recipes.store');
    Route::patch('/manufacturing/recipes/{recipe}', [RecipeController::class, 'update'])
        ->name('manufacturing.recipes.update');
    Route::delete('/manufacturing/recipes/{recipe}', [RecipeController::class, 'destroy'])
        ->name('manufacturing.recipes.destroy');
    Route::post('/manufacturing/recipes/{recipe}/versions', [RecipeController::class, 'storeVersion'])
        ->name('manufacturing.recipes.versions.store');
    Route::patch('/manufacturing/recipes/{recipe}/versions/{version}', [RecipeController::class, 'updateVersion'])
        ->name('manufacturing.recipes.versions.update');
    Route::delete('/manufacturing/recipes/{recipe}/versions/{version}', [RecipeController::class, 'destroyVersion'])
        ->name('manufacturing.recipes.versions.destroy');
    Route::post('/manufacturing/recipes/{recipe}/versions/{version}/checkout', [RecipeController::class, 'checkoutVersion'])
        ->name('manufacturing.recipes.versions.checkout');
    Route::post('/manufacturing/recipes/{recipe}/versions/{version}/check-in', [RecipeController::class, 'checkInVersion'])
        ->name('manufacturing.recipes.versions.check-in');
    Route::patch('/manufacturing/recipes/{recipe}/versions/{version}/publish', [RecipeController::class, 'publishVersion'])
        ->name('manufacturing.recipes.versions.publish');
    Route::patch('/manufacturing/recipes/{recipe}/versions/{version}/approve', [RecipeController::class, 'publishVersion'])
        ->name('manufacturing.recipes.versions.approve');
    Route::post('/manufacturing/recipes/{recipe}/versions/{version}/duplicate', [RecipeController::class, 'duplicateVersion'])
        ->name('manufacturing.recipes.versions.duplicate');
    Route::patch('/manufacturing/recipes/{recipe}/versions/{version}/archive', [RecipeController::class, 'archiveVersion'])
        ->name('manufacturing.recipes.versions.archive');
    Route::post('/manufacturing/recipes/{recipe}/versions/{version}/ingredients', [RecipeController::class, 'storeIngredient'])
        ->name('manufacturing.recipes.ingredients.store');
    Route::patch('/manufacturing/recipes/{recipe}/versions/{version}/ingredients/{line}', [RecipeController::class, 'updateIngredient'])
        ->name('manufacturing.recipes.ingredients.update');
    Route::post('/manufacturing/recipes/{recipe}/lines', [RecipeController::class, 'storeLine'])
        ->name('manufacturing.recipes.lines.store');
    Route::patch('/manufacturing/recipes/{recipe}/lines/{line}', [RecipeController::class, 'updateLine'])
        ->name('manufacturing.recipes.lines.update');
    Route::delete('/manufacturing/recipes/{recipe}/lines/{line}', [RecipeController::class, 'destroyLine'])
        ->name('manufacturing.recipes.lines.destroy');

    Route::get('/manufacturing/make-orders', [MakeOrderController::class, 'index'])
        ->name('manufacturing.make-orders.index');
    Route::get('/manufacturing/make-orders/list', [MakeOrderController::class, 'list'])
        ->name('manufacturing.make-orders.list');
    Route::get('/manufacturing/make-orders/{makeOrder}', [MakeOrderController::class, 'show'])
        ->name('manufacturing.make-orders.show');
    Route::post('/manufacturing/make-orders', [MakeOrderController::class, 'store'])
        ->name('manufacturing.make-orders.store');
    Route::patch('/manufacturing/make-orders/{makeOrder}', [MakeOrderController::class, 'update'])
        ->name('manufacturing.make-orders.update');
    Route::patch('/manufacturing/make-orders/{makeOrder}/details', [MakeOrderController::class, 'updateDetailsQuantities'])
        ->name('manufacturing.make-orders.details.update');
    Route::patch('/manufacturing/make-orders/{makeOrder}/due-date', [MakeOrderController::class, 'updateDueDate'])
        ->name('manufacturing.make-orders.due-date.update');
    Route::patch('/manufacturing/make-orders/{makeOrder}/assignment', [MakeOrderController::class, 'updateAssignment'])
        ->name('manufacturing.make-orders.assignment.update');
    Route::patch('/manufacturing/make-orders/{makeOrder}/workflow-stage', [MakeOrderController::class, 'updateWorkflowStage'])
        ->name('manufacturing.make-orders.workflow-stage.update');
    Route::delete('/manufacturing/make-orders/{makeOrder}', [MakeOrderController::class, 'destroy'])
        ->name('manufacturing.make-orders.destroy');
    Route::post('/manufacturing/make-orders/{makeOrder}/lines', [MakeOrderController::class, 'storeLine'])
        ->name('manufacturing.make-orders.lines.store');
    Route::patch('/manufacturing/make-orders/{makeOrder}/lines/{line}', [MakeOrderController::class, 'updateLine'])
        ->name('manufacturing.make-orders.lines.update');
    Route::delete('/manufacturing/make-orders/{makeOrder}/lines/{line}', [MakeOrderController::class, 'destroyLine'])
        ->name('manufacturing.make-orders.lines.destroy');
    Route::post('/manufacturing/make-orders/{makeOrder}/schedule', [MakeOrderController::class, 'schedule'])
        ->name('manufacturing.make-orders.schedule');
    Route::post('/manufacturing/make-orders/{makeOrder}/make', [MakeOrderController::class, 'make'])
        ->name('manufacturing.make-orders.make');
    Route::get('/manufacturing/make-orders/{makeOrder}/notes', [NoteController::class, 'makeOrderIndex'])
        ->name('manufacturing.make-orders.notes.index');
    Route::post('/manufacturing/make-orders/{makeOrder}/notes', [NoteController::class, 'makeOrderStore'])
        ->name('manufacturing.make-orders.notes.store');

    Route::get('/purchasing/suppliers', [SupplierController::class, 'index'])
        ->name('purchasing.suppliers.index');
    Route::get('/purchasing/suppliers/list', [SupplierController::class, 'list'])
        ->name('purchasing.suppliers.list');
    Route::post('/purchasing/suppliers', [SupplierController::class, 'store'])
        ->name('purchasing.suppliers.store');
    Route::get('/purchasing/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->name('purchasing.suppliers.show');
    Route::patch('/purchasing/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('purchasing.suppliers.update');
    Route::delete('/purchasing/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->name('purchasing.suppliers.destroy');
    Route::get('/purchasing/suppliers/{supplier}/purchase-orders', [SupplierPurchaseOrderController::class, 'index'])
        ->name('purchasing.suppliers.purchase-orders.index');
    Route::get('/purchasing/suppliers/{supplier}/purchase-options', [SupplierPurchaseOptionController::class, 'index'])
        ->name('purchasing.suppliers.purchase-options.index');
    Route::post('/purchasing/suppliers/{supplier}/purchase-options', [SupplierPurchaseOptionController::class, 'store'])
        ->name('purchasing.suppliers.purchase-options.store');
    Route::patch('/purchasing/suppliers/{supplier}/purchase-options/{option}', [SupplierPurchaseOptionController::class, 'update'])
        ->name('purchasing.suppliers.purchase-options.update');
    Route::delete('/purchasing/suppliers/{supplier}/purchase-options/{option}', [SupplierPurchaseOptionController::class, 'destroy'])
        ->name('purchasing.suppliers.purchase-options.destroy');
    Route::post('/purchasing/purchase-options/{option}/prices', [ItemPurchaseOptionPriceController::class, 'store'])
        ->name('purchasing.purchase-options.prices.store');

    Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])
        ->name('purchasing.orders.index');
    Route::get('/purchasing/orders/list', [PurchaseOrderController::class, 'list'])
        ->name('purchasing.orders.list');
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
    Route::post('/purchasing/orders/{purchaseOrder}/workflow/complete', [PurchaseOrderWorkflowController::class, 'complete'])
        ->name('purchasing.orders.workflow.complete');
    Route::post('/purchasing/orders/{purchaseOrder}/workflow/cancel', [PurchaseOrderWorkflowController::class, 'cancel'])
        ->name('purchasing.orders.workflow.cancel');
    Route::post('/purchasing/orders/{purchaseOrder}/receipts', [PurchaseOrderReceiptController::class, 'store'])
        ->name('purchasing.orders.receipts.store');
    Route::post('/purchasing/orders/{purchaseOrder}/short-closures', [PurchaseOrderShortClosureController::class, 'store'])
        ->name('purchasing.orders.short-closures.store');
    Route::get('/purchasing/orders/{purchaseOrder}/notes', [NoteController::class, 'purchaseOrderIndex'])
        ->name('purchasing.orders.notes.index');
    Route::post('/purchasing/orders/{purchaseOrder}/notes', [NoteController::class, 'purchaseOrderStore'])
        ->name('purchasing.orders.notes.store');

    Route::post('/purchasing/orders/{purchaseOrderId}/lines', [PurchaseOrderLineController::class, 'store'])
        ->name('purchasing.orders.lines.store');
    Route::patch('/purchasing/orders/{purchaseOrder}/lines/{line}', [PurchaseOrderLineController::class, 'update'])
        ->name('purchasing.orders.lines.update');
    Route::delete('/purchasing/orders/{purchaseOrderId}/lines/{lineId}', [PurchaseOrderLineController::class, 'destroy'])
        ->name('purchasing.orders.lines.destroy');

    Route::get('/sales/customers', [CustomerController::class, 'index'])
        ->name('sales.customers.index');
    Route::get('/sales/customers/list', [CustomerController::class, 'list'])
        ->name('sales.customers.list');
    Route::get('/sales/customers/export', [CustomerController::class, 'export'])
        ->name('sales.customers.export');
    Route::post('/sales/customers/import-preview', [CustomerController::class, 'previewImport'])
        ->name('sales.customers.import.preview');
    Route::post('/sales/customers/imports', [CustomerController::class, 'storeImport'])
        ->name('sales.customers.import.store');
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
    Route::get('/sales/orders/list', [SalesOrderController::class, 'list'])
        ->name('sales.orders.list');
    Route::get('/sales/orders/export', [SalesOrderController::class, 'export'])
        ->name('sales.orders.export');
    Route::post('/sales/orders/import-preview', [SalesOrderController::class, 'previewImport'])
        ->name('sales.orders.import.preview');
    Route::post('/sales/orders/imports', [SalesOrderController::class, 'storeImport'])
        ->name('sales.orders.import.store');
    Route::post('/sales/orders', [SalesOrderController::class, 'store'])
        ->name('sales.orders.store');
    Route::get('/sales/orders/{salesOrder}', [SalesOrderController::class, 'show'])
        ->name('sales.orders.show');
    Route::patch('/sales/orders/{salesOrder}', [SalesOrderController::class, 'update'])
        ->name('sales.orders.update');
    Route::patch('/sales/orders/{salesOrder}/status', [SalesOrderStatusController::class, 'update'])
        ->name('sales.orders.status.update');
    Route::delete('/sales/orders/{salesOrder}', [SalesOrderController::class, 'destroy'])
        ->name('sales.orders.destroy');
    Route::get('/sales/orders/{salesOrder}/notes', [NoteController::class, 'salesOrderIndex'])
        ->name('sales.orders.notes.index');
    Route::post('/sales/orders/{salesOrder}/notes', [NoteController::class, 'salesOrderStore'])
        ->name('sales.orders.notes.store');
    Route::post('/sales/orders/{salesOrder}/lines', [SalesOrderLineController::class, 'store'])
        ->name('sales.orders.lines.store');
    Route::patch('/sales/orders/{salesOrder}/lines/{line}', [SalesOrderLineController::class, 'update'])
        ->name('sales.orders.lines.update');
    Route::delete('/sales/orders/{salesOrder}/lines/{line}', [SalesOrderLineController::class, 'destroy'])
        ->name('sales.orders.lines.destroy');

    Route::get('/sales/products', [SalesProductController::class, 'index'])
        ->name('sales.products.index');
    Route::get('/sales/products/list', [SalesProductController::class, 'list'])
        ->name('sales.products.list');
    Route::get('/sales/products/export', [SalesProductController::class, 'export'])
        ->name('sales.products.export');
    Route::post('/sales/products', [SalesProductController::class, 'store'])
        ->name('sales.products.store');
    Route::patch('/sales/products/{item}', [SalesProductController::class, 'update'])
        ->name('sales.products.update');
    Route::post('/sales/products/import-preview', [SalesProductController::class, 'preview'])
        ->name('sales.products.import.preview');
    Route::post('/sales/products/imports', [SalesProductController::class, 'storeImport'])
        ->name('sales.products.import.store');

    Route::get('/admin/workflows', [WorkflowController::class, 'index'])
        ->name('admin.workflows.index');
    Route::post('/admin/workflows/stages', [WorkflowStageController::class, 'store'])
        ->name('admin.workflows.stages.store');
    Route::patch('/admin/workflows/stages/{workflowStage}', [WorkflowStageController::class, 'update'])
        ->name('admin.workflows.stages.update');
    Route::delete('/admin/workflows/stages/{workflowStage}', [WorkflowStageController::class, 'destroy'])
        ->name('admin.workflows.stages.destroy');
    Route::post('/admin/workflows/stages/reorder', [WorkflowStageController::class, 'reorder'])
        ->name('admin.workflows.stages.reorder');
    Route::post('/admin/workflows/task-templates', [WorkflowTaskTemplateController::class, 'store'])
        ->name('admin.workflows.task-templates.store');
    Route::patch('/admin/workflows/task-templates/{workflowTaskTemplate}', [WorkflowTaskTemplateController::class, 'update'])
        ->name('admin.workflows.task-templates.update');
    Route::post('/admin/workflows/task-templates/reorder', [WorkflowTaskTemplateController::class, 'reorder'])
        ->name('admin.workflows.task-templates.reorder');
    Route::patch('/tasks/{task}/complete', [TaskCompletionController::class, 'update'])
        ->name('tasks.complete');
    Route::post('/tasks', [TaskController::class, 'store'])
        ->name('tasks.store');

    Route::get('/admin/users', [UserManagementController::class, 'index'])
        ->name('admin.users.index');
    Route::get('/admin/users/list', [UserManagementController::class, 'list'])
        ->name('admin.users.list');
    Route::post('/admin/users/invitations', [UserManagementController::class, 'storeInvitation'])
        ->name('admin.users.invitations.store');
    Route::post('/admin/users/invitations/{invitation}/resend', [UserManagementController::class, 'resendInvitation'])
        ->name('admin.users.invitations.resend');
    Route::delete('/admin/users/invitations/{invitation}', [UserManagementController::class, 'revokeInvitation'])
        ->name('admin.users.invitations.revoke');
    Route::get('/admin/users/invitations/{invitation}', [UserManagementController::class, 'showInvitation'])
        ->name('admin.users.invitations.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/connectors', [ProfileConnectorController::class, 'index'])
        ->name('profile.connectors.index');
    Route::post('/profile/connectors/woocommerce', [ProfileConnectorController::class, 'storeWooCommerce'])
        ->name('profile.connectors.woocommerce.store');
    Route::delete('/profile/connectors/woocommerce', [ProfileConnectorController::class, 'destroyWooCommerce'])
        ->name('profile.connectors.woocommerce.destroy');
    Route::post('/sales/products/import-sources/{source}/connect', [ProfileConnectorController::class, 'storeWooCommerce'])
        ->name('sales.products.import.connect');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
