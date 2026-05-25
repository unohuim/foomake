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
        Schema::table('make_orders', function (Blueprint $table): void {
            $table->foreignId('workflow_stage_id')
                ->nullable()
                ->after('due_date')
                ->constrained('workflow_stages')
                ->nullOnDelete();
            $table->foreignId('tasked_by_user_id')
                ->nullable()
                ->after('workflow_stage_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('tasked_by_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'workflow_stage_id'], 'mkord_tenant_stage_idx');
            $table->index(['tenant_id', 'assigned_to_user_id'], 'mkord_tenant_assign_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('make_orders', function (Blueprint $table): void {
            $table->dropIndex('mkord_tenant_stage_idx');
            $table->dropIndex('mkord_tenant_assign_idx');
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropConstrainedForeignId('tasked_by_user_id');
            $table->dropConstrainedForeignId('workflow_stage_id');
        });
    }
};
