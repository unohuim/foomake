<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keep tasker workflow access assignment-scoped instead of broad resource-scoped.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'tasker')
            ->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('permissions')
            ->whereIn('slug', [
                'purchasing-purchase-orders-receive',
                'sales-sales-orders-update',
                'inventory-adjustments-execute',
                'inventory-make-orders-execute',
            ])
            ->pluck('id')
            ->each(function (int $permissionId) use ($roleId): void {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'purchasing-purchase-orders-create',
                'sales-sales-orders-manage',
                'inventory-adjustments-view',
                'inventory-make-orders-view',
            ])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    /**
     * Leave existing role mappings unchanged on rollback.
     */
    public function down(): void
    {
        // Intentionally left blank so rollback does not grant broad resource visibility.
    }
};
