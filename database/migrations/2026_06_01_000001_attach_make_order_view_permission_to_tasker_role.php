<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Attach documented workflow assignment access to the tasker role.
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
            ->whereIn('slug', $this->permissionSlugs())
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
    }

    /**
     * Keep existing role permissions intact on rollback.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid removing permissions a deployment may
        // have granted before this data migration ran.
    }

    /**
     * @return list<string>
     */
    private function permissionSlugs(): array
    {
        return [
            'purchasing-purchase-orders-create',
            'purchasing-purchase-orders-receive',
            'sales-sales-orders-manage',
            'inventory-adjustments-view',
            'inventory-adjustments-execute',
            'inventory-make-orders-view',
            'inventory-make-orders-execute',
        ];
    }
};
