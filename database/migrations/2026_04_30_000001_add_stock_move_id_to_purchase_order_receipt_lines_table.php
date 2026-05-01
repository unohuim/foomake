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
        Schema::table('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->foreignId('stock_move_id')
                ->nullable()
                ->after('purchase_order_line_id')
                ->constrained('stock_moves')
                ->nullOnDelete();
        });

        $mappings = DB::table('purchase_order_receipt_lines')
            ->leftJoin('stock_moves', function ($join): void {
                $join->on('stock_moves.source_id', '=', 'purchase_order_receipt_lines.id')
                    ->where('stock_moves.source_type', '=', 'purchase_order_receipt_line');
            })
            ->whereNotNull('stock_moves.id')
            ->get([
                'purchase_order_receipt_lines.id as receipt_line_id',
                'stock_moves.id as stock_move_id',
            ]);

        foreach ($mappings as $mapping) {
            DB::table('purchase_order_receipt_lines')
                ->where('id', $mapping->receipt_line_id)
                ->update(['stock_move_id' => $mapping->stock_move_id]);
        }

        Schema::table('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->unique('stock_move_id', 'po_receipt_lines_stock_move_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->dropUnique('po_receipt_lines_stock_move_id_unique');
            $table->dropConstrainedForeignId('stock_move_id');
        });
    }
};
