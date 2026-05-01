<?php

namespace App\Providers;

use App\Models\PurchaseOrderReceiptLine;
use App\Models\Tenant;
use App\Observers\TenantObserver;
use App\Services\Purchasing\DefaultSupplierDeleteGuard;
use App\Services\Purchasing\SupplierDeleteGuard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SupplierDeleteGuard::class, DefaultSupplierDeleteGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Tenant::observe(TenantObserver::class);

        Relation::morphMap([
            'purchase_order_receipt_line' => PurchaseOrderReceiptLine::class,
        ]);

        Blade::directive('qty', function (string $expression): string {
            return "<?php echo e(\\App\\Support\\QuantityFormatter::format(...[{$expression}])); ?>";
        });

        Blade::directive('qtyForUom', function (string $expression): string {
            return "<?php echo e(\\App\\Support\\QuantityFormatter::formatForUom(...[{$expression}])); ?>";
        });
    }
}
