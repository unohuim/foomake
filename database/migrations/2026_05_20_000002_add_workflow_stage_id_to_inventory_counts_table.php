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
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->foreignId('workflow_stage_id')
                ->nullable()
                ->after('posted_by_user_id')
                ->constrained('workflow_stages')
                ->nullOnDelete();

            $table->index(['tenant_id', 'workflow_stage_id'], 'inventory_counts_tenant_stage_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->dropIndex('inventory_counts_tenant_stage_idx');
            $table->dropConstrainedForeignId('workflow_stage_id');
        });
    }
};
