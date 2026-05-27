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
        Schema::table('make_orders', function (Blueprint $table): void {
            $table->decimal('actual_output_quantity', 18, 6)
                ->nullable()
                ->after('output_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('make_orders', function (Blueprint $table): void {
            $table->dropColumn('actual_output_quantity');
        });
    }
};
