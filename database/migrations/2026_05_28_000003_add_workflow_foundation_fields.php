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
            $table->string('action_verb')->nullable()->after('button_text');
            $table->string('status_complete_label')->nullable()->after('action_verb');
            $table->string('completion_mode')->default('manual')->after('status_complete_label');
        });

        DB::table('workflow_stages')->update([
            'action_verb' => DB::raw('button_text'),
        ]);

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropColumn('button_text');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('current_workflow_stage_id')
                ->nullable()
                ->after('back_ordered_by_user_id')
                ->constrained('workflow_stages')
                ->nullOnDelete();
            $table->foreignId('last_completed_workflow_stage_id')
                ->nullable()
                ->after('current_workflow_stage_id')
                ->constrained('workflow_stages')
                ->nullOnDelete();
            $table->timestamp('workflow_cancelled_at')
                ->nullable()
                ->after('last_completed_workflow_stage_id');

            $table->index(['tenant_id', 'current_workflow_stage_id'], 'po_tenant_current_workflow_stage_idx');
            $table->index(['tenant_id', 'last_completed_workflow_stage_id'], 'po_tenant_last_workflow_stage_idx');
        });

        Tenant::query()->each(function (Tenant $tenant): void {
            app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
            $this->backfillPurchaseOrderWorkflowState($tenant);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('po_tenant_current_workflow_stage_idx');
            $table->dropIndex('po_tenant_last_workflow_stage_idx');
            $table->dropConstrainedForeignId('last_completed_workflow_stage_id');
            $table->dropConstrainedForeignId('current_workflow_stage_id');
            $table->dropColumn('workflow_cancelled_at');
        });

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->string('button_text')->nullable()->after('name');
        });

        DB::table('workflow_stages')->update([
            'button_text' => DB::raw('action_verb'),
        ]);

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropColumn('action_verb');
            $table->dropColumn('status_complete_label');
            $table->dropColumn('completion_mode');
        });
    }

    /**
     * Backfill workflow state for existing Purchase Orders while legacy status remains.
     */
    private function backfillPurchaseOrderWorkflowState(Tenant $tenant): void
    {
        $domainId = DB::table('workflow_domains')
            ->where('key', 'purchasing')
            ->value('id');

        if (! $domainId) {
            return;
        }

        $stages = DB::table('workflow_stages')
            ->where('tenant_id', $tenant->id)
            ->where('workflow_domain_id', $domainId)
            ->whereIn('key', ['creating', 'receiving', 'completing'])
            ->pluck('id', 'key');

        DB::table('purchase_orders')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'DRAFT')
            ->update([
                'current_workflow_stage_id' => $stages['creating'] ?? null,
                'last_completed_workflow_stage_id' => null,
            ]);

        DB::table('purchase_orders')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'CREATED')
            ->update([
                'current_workflow_stage_id' => $stages['receiving'] ?? null,
                'last_completed_workflow_stage_id' => $stages['creating'] ?? null,
            ]);

        DB::table('purchase_orders')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'RECEIVED')
            ->update([
                'current_workflow_stage_id' => $stages['completing'] ?? null,
                'last_completed_workflow_stage_id' => $stages['receiving'] ?? null,
            ]);

        DB::table('purchase_orders')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'COMPLETED')
            ->update([
                'current_workflow_stage_id' => null,
                'last_completed_workflow_stage_id' => $stages['completing'] ?? null,
            ]);

        DB::table('purchase_orders')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'CANCELLED')
            ->whereNull('workflow_cancelled_at')
            ->update([
                'workflow_cancelled_at' => DB::raw('COALESCE(cancelled_at, CURRENT_TIMESTAMP)'),
            ]);
    }
};
