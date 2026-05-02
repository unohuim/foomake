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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_line_1')->nullable()->after('notes');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('region')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('region');
            $table->char('country_code', 2)->nullable()->after('postal_code');
            $table->text('formatted_address')->nullable()->after('country_code');
            $table->decimal('latitude', 10, 7)->nullable()->after('formatted_address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('address_provider')->nullable()->after('longitude');
            $table->string('address_provider_id')->nullable()->after('address_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'city',
                'region',
                'postal_code',
                'country_code',
                'formatted_address',
                'latitude',
                'longitude',
                'address_provider',
                'address_provider_id',
            ]);
        });
    }
};
