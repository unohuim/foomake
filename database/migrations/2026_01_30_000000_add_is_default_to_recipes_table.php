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
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX recipes_default_unique ON recipes (tenant_id, item_id) WHERE is_default = 1');
        }

        if ($driver === 'mysql') {
            DB::statement('CREATE UNIQUE INDEX recipes_default_unique ON recipes (tenant_id, (CASE WHEN is_default = 1 THEN item_id ELSE NULL END))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS recipes_default_unique');
        }

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX recipes_default_unique ON recipes');
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
