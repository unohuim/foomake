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
        if (! Schema::hasColumn('make_orders', 'workflow_stage_id')) {
            Schema::table('make_orders', function (Blueprint $table): void {
                $table->foreignId('workflow_stage_id')
                    ->nullable()
                    ->constrained('workflow_stages')
                    ->nullOnDelete();

                $table->index(['tenant_id', 'workflow_stage_id'], 'mkord_tenant_stage_idx');
            });
        }

        if (! Schema::hasColumn('make_orders', 'tasked_by_user_id')) {
            Schema::table('make_orders', function (Blueprint $table): void {
                $table->foreignId('tasked_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('make_orders', 'assigned_to_user_id')) {
            $this->dropMakeOrderAssignedToUserIndexIfExists('mkord_tenant_assign_idx');
            $this->dropMakeOrderAssignedToUserIndexIfExists('mord_tenant_assign_idx');

            Schema::table('make_orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assigned_to_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('make_orders', 'assigned_to_user_id')) {
            Schema::table('make_orders', function (Blueprint $table): void {
                $table->foreignId('assigned_to_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index(['tenant_id', 'assigned_to_user_id'], 'mkord_tenant_assign_idx');
            });
        }
    }

    /**
     * Drop a legacy Make Order assigned-user index when present.
     */
    private function dropMakeOrderAssignedToUserIndexIfExists(string $indexName): void
    {
        if (! $this->makeOrdersIndexExists($indexName)) {
            return;
        }

        Schema::table('make_orders', function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    /**
     * Determine whether the given index currently exists on make_orders.
     */
    private function makeOrdersIndexExists(string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('make_orders')");

            return collect($indexes)->contains(
                fn (object $index): bool => (string) ($index->name ?? '') === $indexName
            );
        }

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM make_orders');

            return collect($indexes)->contains(
                fn (object $index): bool => (string) ($index->Key_name ?? '') === $indexName
            );
        }

        return false;
    }
};
