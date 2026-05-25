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
            DB::statement('CREATE INDEX recipes_default_lookup_idx ON recipes (tenant_id, item_id, is_default)');
        }

        if ($driver === 'mysql') {
            DB::statement('CREATE INDEX recipes_default_lookup_idx ON recipes (tenant_id, item_id, is_default)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS recipes_default_lookup_idx');
        }

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX recipes_default_lookup_idx ON recipes');
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
