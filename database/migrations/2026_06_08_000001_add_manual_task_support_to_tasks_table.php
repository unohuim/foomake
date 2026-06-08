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
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('source')->default('generated')->after('tenant_id');
            $table->foreignId('workflow_domain_id')->nullable()->change();
            $table->unsignedBigInteger('domain_record_id')->nullable()->change();
            $table->foreignId('workflow_stage_id')->nullable()->change();
            $table->index(['tenant_id', 'source', 'status'], 'tasks_tenant_source_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_tenant_source_status_idx');
            $table->foreignId('workflow_domain_id')->nullable(false)->change();
            $table->unsignedBigInteger('domain_record_id')->nullable(false)->change();
            $table->foreignId('workflow_stage_id')->nullable(false)->change();
            $table->dropColumn('source');
        });
    }
};
