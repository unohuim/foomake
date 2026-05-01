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
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('output_quantity', 18, 6)
                ->default('0.000000')
                ->after('item_id');
        });

        DB::table('recipes')
            ->whereNull('output_quantity')
            ->update([
                'output_quantity' => '0.000000',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('output_quantity');
        });
    }
};
