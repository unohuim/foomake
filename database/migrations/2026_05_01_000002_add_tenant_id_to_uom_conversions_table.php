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
        Schema::table('uom_conversions', function (Blueprint $table): void {
            $table->dropForeign(['from_uom_id']);
            $table->dropForeign(['to_uom_id']);
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('uom_conversions_from_uom_id_to_uom_id_unique');
            $table->unique(
                ['tenant_id', 'from_uom_id', 'to_uom_id'],
                'uom_conversions_tenant_from_to_unique'
            );
            $table->foreign('from_uom_id')
                ->references('id')
                ->on('uoms')
                ->cascadeOnDelete();
            $table->foreign('to_uom_id')
                ->references('id')
                ->on('uoms')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uom_conversions', function (Blueprint $table): void {
            $table->dropForeign(['from_uom_id']);
            $table->dropForeign(['to_uom_id']);
            $table->dropUnique('uom_conversions_tenant_from_to_unique');
            $table->unique(['from_uom_id', 'to_uom_id']);
            $table->foreign('from_uom_id')
                ->references('id')
                ->on('uoms')
                ->cascadeOnDelete();
            $table->foreign('to_uom_id')
                ->references('id')
                ->on('uoms')
                ->cascadeOnDelete();
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
