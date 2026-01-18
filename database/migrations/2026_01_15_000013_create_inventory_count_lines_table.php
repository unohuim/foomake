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
        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_count_id');
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('counted_quantity', 18, 6);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['inventory_count_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('inventory_counts')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
    }
};
