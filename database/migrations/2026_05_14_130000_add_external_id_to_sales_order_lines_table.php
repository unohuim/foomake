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
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('item_id');
            $table->index(['tenant_id', 'sales_order_id', 'external_id'], 'sales_order_lines_tenant_order_external_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropIndex('sales_order_lines_tenant_order_external_idx');
            $table->dropColumn('external_id');
        });
    }
};
