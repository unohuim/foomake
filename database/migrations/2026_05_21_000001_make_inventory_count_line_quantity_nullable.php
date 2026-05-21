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
        Schema::table('inventory_count_lines', function (Blueprint $table): void {
            $table->decimal('counted_quantity', 18, 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_count_lines', function (Blueprint $table): void {
            $table->decimal('counted_quantity', 18, 6)->nullable(false)->change();
        });
    }
};
