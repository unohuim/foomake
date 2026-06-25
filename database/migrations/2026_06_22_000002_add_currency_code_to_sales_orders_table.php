<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fallbackCurrency = strtoupper((string) config('app.currency_code', 'USD'));

        Schema::table('sales_orders', function (Blueprint $table) use ($fallbackCurrency): void {
            $table->char('currency_code', 3)->default($fallbackCurrency)->after('contact_id');
        });

        DB::table('sales_orders')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'sales_orders.tenant_id')
            ->select([
                'sales_orders.id',
                'customers.currency_code as customer_currency_code',
                'tenants.currency_code as tenant_currency_code',
            ])
            ->orderBy('sales_orders.id')
            ->get()
            ->each(function (object $row) use ($fallbackCurrency): void {
                $currencyCode = strtoupper((string) (
                    $row->customer_currency_code
                    ?: $row->tenant_currency_code
                    ?: $fallbackCurrency
                ));

                DB::table('sales_orders')
                    ->where('id', $row->id)
                    ->update(['currency_code' => $currencyCode]);
            });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->index(['tenant_id', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'currency_code']);
            $table->dropColumn('currency_code');
        });
    }
};
