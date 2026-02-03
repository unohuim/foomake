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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('order_date')->nullable();
            $table->unsignedInteger('shipping_cents')->nullable();
            $table->unsignedInteger('tax_cents')->nullable();
            $table->unsignedInteger('po_subtotal_cents')->default(0);
            $table->unsignedInteger('po_grand_total_cents')->default(0);
            $table->string('po_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('DRAFT');
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
