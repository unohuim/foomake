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
        Schema::create('purchase_order_short_closures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('purchase_order_id');

            $table->dateTime('short_closed_at');

            $table->foreignId('short_closed_by_user_id')->constrained('users');

            $table->string('reference')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign(
                ['purchase_order_id', 'tenant_id'],
                'fk_posc_po_tenant'
            )->references(['id', 'tenant_id'])
                ->on('purchase_orders')
                ->cascadeOnDelete();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_short_closures', function (Blueprint $table) {
            $table->dropForeign('fk_posc_po_tenant');
        });

        Schema::dropIfExists('purchase_order_short_closures');
    }
};
