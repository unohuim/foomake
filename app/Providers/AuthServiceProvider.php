<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $permissions = [
            'system-tenants-manage',
            'system-users-manage',
            'system-roles-manage',
            'purchasing-suppliers-view',
            'purchasing-suppliers-manage',
            'purchasing-purchase-orders-view',
            'purchasing-purchase-orders-create',
            'purchasing-purchase-orders-update',
            'purchasing-purchase-orders-manage',
            'purchasing-receiving-execute',
            'purchasing-receiving-view',
            'sales-customers-view',
            'sales-customers-manage',
            'sales-sales-orders-view',
            'sales-sales-orders-create',
            'sales-sales-orders-update',
            'sales-sales-orders-manage',
            'sales-invoices-view',
            'sales-invoices-create',
            'sales-invoices-manage',
            'inventory-materials-view',
            'inventory-materials-manage',
            'inventory-products-view',
            'inventory-products-manage',
            'inventory-adjustments-execute',
            'inventory-adjustments-view',
            'inventory-make-orders-view',
            'inventory-make-orders-execute',
            'inventory-make-orders-manage',
            'reports-view',
        ];

        Gate::before(function (?User $user) {
            if (!$user) {
                return null;
            }

            if ($user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        foreach ($permissions as $permission) {
            Gate::define($permission, function (User $user) use ($permission): bool {
                return $user->roles()
                    ->join('permission_role', 'roles.id', '=', 'permission_role.role_id')
                    ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                    ->where('permissions.slug', $permission)
                    ->exists();
            });
        }
    }
}
