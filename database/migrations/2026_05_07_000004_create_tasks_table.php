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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_domain_id')->constrained('workflow_domains')->cascadeOnDelete();
            $table->unsignedBigInteger('domain_record_id');
            $table->foreignId('workflow_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('workflow_task_template_id')->nullable()->constrained('workflow_task_templates')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique([
                'tenant_id',
                'workflow_domain_id',
                'domain_record_id',
                'workflow_stage_id',
                'workflow_task_template_id',
            ], 'tasks_generated_template_unique');
            $table->index(['tenant_id', 'workflow_domain_id', 'domain_record_id'], 'tasks_tenant_domain_record_idx');
            $table->index(['tenant_id', 'workflow_stage_id', 'status'], 'tasks_tenant_stage_status_idx');
            $table->index(['tenant_id', 'assigned_to_user_id', 'status'], 'tasks_tenant_assignee_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
