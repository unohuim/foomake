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
        Schema::table('inventory_count_lines', function (Blueprint $table): void {
            $table->foreignId('uom_id')
                ->nullable()
                ->after('item_id')
                ->constrained('uoms')
                ->nullOnDelete();
        });

        DB::table('inventory_count_lines')
            ->whereNull('uom_id')
            ->orderBy('id')
            ->eachById(function (object $line): void {
                $baseUomId = DB::table('items')
                    ->where('id', $line->item_id)
                    ->value('base_uom_id');

                if ($baseUomId === null) {
                    return;
                }

                DB::table('inventory_count_lines')
                    ->where('id', $line->id)
                    ->update(['uom_id' => $baseUomId]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_count_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uom_id');
        });
    }
};
