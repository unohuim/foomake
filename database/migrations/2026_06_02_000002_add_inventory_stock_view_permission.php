<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add narrow stock inventory read access without granting Inventory Counts visibility.
     */
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert([
            'slug' => 'inventory-stock-view',
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('slug', 'inventory-stock-view')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin', 'founder', 'inventory', 'tasker'])
            ->pluck('id')
            ->each(function (int $roleId) use ($permissionId): void {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Remove only the permission introduced by this migration.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'inventory-stock-view')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
