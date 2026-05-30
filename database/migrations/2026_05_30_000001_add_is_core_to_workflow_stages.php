<?php

use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\Tenant;
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
        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->boolean('is_core')->default(false)->after('is_active');
        });

        $purchasingDomainIds = DB::table('workflow_domains')
            ->where('key', 'purchasing')
            ->pluck('id');

        if ($purchasingDomainIds->isNotEmpty()) {
            DB::table('workflow_stages')
                ->whereIn('workflow_domain_id', $purchasingDomainIds)
                ->where('key', 'partially_received')
                ->where('status_complete_label', 'PARTIALLY_RECEIVED')
                ->delete();
        }

        Tenant::query()->each(function (Tenant $tenant): void {
            app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropColumn('is_core');
        });
    }
};
