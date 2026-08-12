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
        Schema::table('wordpress_plugin_connections', function (Blueprint $table): void {
            $table->text('site_access_token')->nullable()->after('access_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wordpress_plugin_connections', function (Blueprint $table): void {
            $table->dropColumn('site_access_token');
        });
    }
};
