<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenancyRolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate([
            'tenant_name' => 'FooMake',
        ]);

        $systemPermissions = [
            'system-tenants-manage',
            'system-users-manage',
            'system-roles-manage',
        ];

        $purchasingPermissions = [
            'purchasing-suppliers-view',
            'purchasing-suppliers-manage',
            'purchasing-purchase-orders-view',
            'purchasing-purchase-orders-create',
            'purchasing-purchase-orders-update',
            'purchasing-purchase-orders-manage',
            'purchasing-receiving-view',
            'purchasing-receiving-execute',
        ];

        $salesPermissions = [
            'sales-customers-view',
            'sales-customers-manage',
            'sales-sales-orders-view',
            'sales-sales-orders-create',
            'sales-sales-orders-update',
            'sales-sales-orders-manage',
            'sales-invoices-view',
            'sales-invoices-create',
            'sales-invoices-manage',
        ];

        $inventoryPermissions = [
            'inventory-materials-view',
            'inventory-materials-manage',
            'inventory-products-view',
            'inventory-products-manage',
            'inventory-adjustments-view',
            'inventory-adjustments-execute',
            'inventory-make-orders-view',
            'inventory-make-orders-execute',
            'inventory-make-orders-manage',
        ];

        $reportsPermissions = [
            'reports-view',
        ];

        $allBusinessPermissions = array_merge(
            $purchasingPermissions,
            $salesPermissions,
            $inventoryPermissions,
            $reportsPermissions
        );

        $permissionSlugs = array_merge(
            $systemPermissions,
            $allBusinessPermissions
        );

        $permissions = collect($permissionSlugs)
            ->mapWithKeys(function (string $slug) {
                return [$slug => Permission::firstOrCreate(['slug' => $slug])];
            });

        $rolePermissions = [
            'super-admin' => $permissionSlugs,
            'admin' => array_merge($systemPermissions, $allBusinessPermissions),
            'founder' => $allBusinessPermissions,
            'purchasing' => array_merge($purchasingPermissions, $reportsPermissions),
            'sales' => array_merge($salesPermissions, $reportsPermissions),
            'inventory' => array_merge($inventoryPermissions, $reportsPermissions),
            'tasker' => array_merge(['inventory-make-orders-execute'], $reportsPermissions),
        ];

        foreach ($rolePermissions as $roleName => $permissionList) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            $permissionIds = $permissions
                ->only($permissionList)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        $this->call(AdminLoginUserSeeder::class);

        $adminUser = User::where('email', 'colquhoun.r@gmail.com')->first();
        $superAdminRole = Role::where('name', 'super-admin')->first();

        if ($adminUser && $superAdminRole) {
            if (!$adminUser->tenant_id) {
                $adminUser->tenant_id = $tenant->id;
                $adminUser->save();
            }

            $adminUser->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
