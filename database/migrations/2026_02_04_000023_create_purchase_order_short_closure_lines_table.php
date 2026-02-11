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
        Schema::create('purchase_order_short_closure_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('purchase_order_short_closure_id');
            $table->unsignedBigInteger('purchase_order_line_id');

            if (DB::getDriverName() === 'sqlite') {
                $table->text('short_closed_quantity');
            } else {
                $table->decimal('short_closed_quantity', 18, 6);
            }

            $table->timestamps();

            // MySQL identifier limit: explicit, short FK names (avoid ->constrained()).
            $table->foreign('purchase_order_short_closure_id', 'fk_poscl_posc')
                ->references('id')
                ->on('purchase_order_short_closures')
                ->cascadeOnDelete();

            $table->foreign('purchase_order_line_id', 'fk_poscl_pol')
                ->references('id')
                ->on('purchase_order_lines')
                ->cascadeOnDelete();

            // Explicit, short index names (your error is from the auto-generated one).
            $table->index('tenant_id', 'idx_poscl_tenant');
            $table->index('purchase_order_short_closure_id', 'idx_poscl_posc');
            $table->index('purchase_order_line_id', 'idx_poscl_pol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_short_closure_lines');
    }
};
