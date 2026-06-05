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
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->string('name')->default('Inventory Count')->after('id');
        });

        DB::table('inventory_counts')
            ->select('id')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $count): void {
                DB::table('inventory_counts')
                    ->where('id', $count->id)
                    ->update([
                        'name' => 'Inventory Count #' . $count->id,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
