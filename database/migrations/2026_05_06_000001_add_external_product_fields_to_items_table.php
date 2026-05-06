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
        Schema::table('items', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('base_uom_id');
            $table->string('external_source')->nullable()->after('default_price_currency_code');
            $table->string('external_id')->nullable()->after('external_source');

            $table->unique(
                ['tenant_id', 'external_source', 'external_id'],
                'items_tenant_source_external_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropUnique('items_tenant_source_external_unique');
            $table->dropColumn([
                'is_active',
                'external_source',
                'external_id',
            ]);
        });
    }
};
