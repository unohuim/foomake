<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_purchase_option_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_purchase_option_id')
                ->constrained('item_purchase_options')
                ->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->char('price_currency_code', 3);
            $table->unsignedInteger('converted_price_cents');
            $table->decimal('fx_rate', 18, 8);
            $table->date('fx_rate_as_of');
            $table->timestamp('effective_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'item_purchase_option_id'], 'ipop_prices_tenant_option_idx');
            $table->index(['item_purchase_option_id', 'ended_at'], 'ipop_prices_option_ended_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_purchase_option_prices');
    }
};
