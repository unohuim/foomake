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
        Schema::table('items', function (Blueprint $table): void {
            $table->unsignedInteger('default_price_cents')->nullable()->after('is_manufacturable');
            $table->char('default_price_currency_code', 3)->nullable()->after('default_price_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn(['default_price_cents', 'default_price_currency_code']);
        });
    }
};
