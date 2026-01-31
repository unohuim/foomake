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
        Schema::table('uom_categories', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('uom_categories_name_unique');
            $table->unique(['tenant_id', 'name'], 'uom_categories_tenant_name_unique');
        });

        Schema::table('uoms', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('uoms_name_unique');
            $table->dropUnique('uoms_symbol_unique');
            $table->unique(['tenant_id', 'symbol'], 'uoms_tenant_symbol_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uoms', function (Blueprint $table): void {
            $table->dropUnique('uoms_tenant_symbol_unique');
            $table->unique('symbol');
            $table->unique('name');
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('uom_categories', function (Blueprint $table): void {
            $table->dropUnique('uom_categories_tenant_name_unique');
            $table->unique('name');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
