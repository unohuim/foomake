<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->date('order_date')->nullable()->after('contact_id');
            $table->string('external_source')->nullable()->after('status');
            $table->string('external_id')->nullable()->after('external_source');
            $table->string('external_status')->nullable()->after('external_id');
            $table->timestamp('external_status_synced_at')->nullable()->after('external_status');

            $table->unique(
                ['tenant_id', 'external_source', 'external_id'],
                'sales_orders_tenant_source_external_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropUnique('sales_orders_tenant_source_external_unique');
            $table->dropColumn([
                'order_date',
                'external_source',
                'external_id',
                'external_status',
                'external_status_synced_at',
            ]);
        });
    }
};
