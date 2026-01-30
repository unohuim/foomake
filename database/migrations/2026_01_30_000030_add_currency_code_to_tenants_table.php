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
        $defaultCurrency = (string) config('app.currency_code', 'USD');

        Schema::table('tenants', function (Blueprint $table) use ($defaultCurrency) {
            $table->string('currency_code', 3)->nullable()->default($defaultCurrency)->after('tenant_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });
    }
};
