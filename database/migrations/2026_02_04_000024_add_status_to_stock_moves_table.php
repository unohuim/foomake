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
        Schema::table('stock_moves', function (Blueprint $table) {
            $table->string('status')->default('POSTED');
        });

        DB::table('stock_moves')->update(['status' => 'POSTED']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_moves', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
