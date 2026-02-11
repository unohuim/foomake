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
        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_order_id');
            $table->dateTime('received_at');
            $table->foreignId('received_by_user_id')->constrained('users');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['purchase_order_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
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
        Schema::dropIfExists('purchase_order_receipts');
    }
};
