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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('trial_ends_at')->nullable()->after('currency_code');
            $table->timestamp('billing_exempt_until')->nullable()->after('trial_ends_at');
            $table->string('billing_provider')->nullable()->after('billing_exempt_until');
            $table->string('billing_provider_customer_id')->nullable()->after('billing_provider');
            $table->string('billing_provider_subscription_id')->nullable()->after('billing_provider_customer_id');
            $table->string('billing_subscription_status')->nullable()->after('billing_provider_subscription_id');
            $table->timestamp('billing_subscription_ends_at')->nullable()->after('billing_subscription_status');

            $table->index('trial_ends_at');
            $table->index('billing_subscription_status');
            $table->index('billing_provider_customer_id');
        });

        DB::table('tenants')
            ->whereNull('trial_ends_at')
            ->update([
                'trial_ends_at' => now()->addDays(7),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['trial_ends_at']);
            $table->dropIndex(['billing_subscription_status']);
            $table->dropIndex(['billing_provider_customer_id']);

            $table->dropColumn([
                'trial_ends_at',
                'billing_exempt_until',
                'billing_provider',
                'billing_provider_customer_id',
                'billing_provider_subscription_id',
                'billing_subscription_status',
                'billing_subscription_ends_at',
            ]);
        });
    }
};
