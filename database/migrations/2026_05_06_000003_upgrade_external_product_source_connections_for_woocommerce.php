<?php

use App\Models\ExternalProductSourceConnection;
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
        Schema::table('external_product_source_connections', function (Blueprint $table): void {
            $table->text('store_url')->nullable()->after('connected_at');
            $table->text('consumer_key')->nullable()->after('store_url');
            $table->text('consumer_secret')->nullable()->after('consumer_key');
            $table->string('status')->default(ExternalProductSourceConnection::STATUS_DISCONNECTED)->after('consumer_secret');
            $table->timestamp('last_verified_at')->nullable()->after('status');
            $table->text('last_error')->nullable()->after('last_verified_at');
        });

        DB::table('external_product_source_connections')
            ->where('is_connected', true)
            ->update([
                'status' => ExternalProductSourceConnection::STATUS_CONNECTED,
            ]);

        DB::table('external_product_source_connections')
            ->where('is_connected', false)
            ->update([
                'status' => ExternalProductSourceConnection::STATUS_DISCONNECTED,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_product_source_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'store_url',
                'consumer_key',
                'consumer_secret',
                'status',
                'last_verified_at',
                'last_error',
            ]);
        });
    }
};
