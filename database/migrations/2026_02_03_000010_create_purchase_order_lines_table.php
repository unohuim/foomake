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
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('item_purchase_option_id')
                ->constrained('item_purchase_options')
                ->cascadeOnDelete();
            $table->unsignedInteger('pack_count');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('line_subtotal_cents');
            $table->unsignedInteger('unit_price_amount');
            $table->char('unit_price_currency_code', 3);
            $table->unsignedInteger('converted_unit_price_amount');
            $table->string('fx_rate', 20);
            $table->date('fx_rate_as_of');
            $table->timestamps();

            $table->foreign(['purchase_order_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('purchase_orders')
                ->cascadeOnDelete();

            $table->index(['purchase_order_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
