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
        Schema::create('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_receipt_id')
                ->constrained('purchase_order_receipts')
                ->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')
                ->constrained('purchase_order_lines')
                ->cascadeOnDelete();
            if (DB::getDriverName() === 'sqlite') {
                $table->text('received_quantity');
            } else {
                $table->decimal('received_quantity', 18, 6);
            }
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('purchase_order_receipt_id');
            $table->index('purchase_order_line_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_receipt_lines');
    }
};
