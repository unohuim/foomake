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
            $table->timestamp('workflow_cancelled_at')->nullable()->after('posted_at');
            $table->foreignId('workflow_cancelled_by_user_id')
                ->nullable()
                ->after('workflow_cancelled_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'workflow_cancelled_at'], 'inventory_counts_tenant_cancelled_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->dropIndex('inventory_counts_tenant_cancelled_idx');
            $table->dropConstrainedForeignId('workflow_cancelled_by_user_id');
            $table->dropColumn('workflow_cancelled_at');
        });
    }
};
