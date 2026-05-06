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
            $table->string('recipe_type')
                ->default('manufacturing')
                ->after('name');

            $table->index(['tenant_id', 'recipe_type'], 'recipes_tenant_recipe_type_index');
        });

        DB::table('recipes')
            ->whereNull('recipe_type')
            ->update([
                'recipe_type' => 'manufacturing',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex('recipes_tenant_recipe_type_index');
            $table->dropColumn('recipe_type');
        });
    }
};
