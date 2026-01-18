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
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_purchasable')->default(false)->after('name');
            $table->boolean('is_sellable')->default(false)->after('is_purchasable');
            $table->boolean('is_manufacturable')->default(false)->after('is_sellable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'is_purchasable',
                'is_sellable',
                'is_manufacturable',
            ]);
        });
    }
};
