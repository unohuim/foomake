<?php

namespace App\Support\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolve minimum permissions required for workflow assignment visibility.
 */
class WorkflowAssignmentPermissions
{
    /**
     * Return permission slugs required before a user may be assigned workflow work for a domain.
     *
     * @return list<string>
     */
    public function requiredPermissionsForDomain(string $domainKey): array
    {
        return match ($domainKey) {
            'inventory' => ['inventory-adjustments-view', 'inventory-adjustments-execute'],
            'manufacturing' => ['inventory-make-orders-view', 'inventory-make-orders-execute'],
            'purchasing' => ['purchasing-purchase-orders-create', 'purchasing-purchase-orders-receive'],
            'sales' => ['sales-sales-orders-manage'],
            default => [],
        };
    }

    /**
     * Scope a tenant user query to users who can be assigned workflow work for a domain.
     */
    public function eligibleUsersQuery(int $tenantId, string $domainKey): Builder
    {
        $permissions = $this->requiredPermissionsForDomain($domainKey);

        $query = User::query()->where('tenant_id', $tenantId);

        if ($permissions === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $userQuery) use ($permissions): void {
            $userQuery->whereHas('roles', function (Builder $roleQuery): void {
                $roleQuery->where('name', 'super-admin');
            });

            $userQuery->orWhere(function (Builder $requiredPermissionsQuery) use ($permissions): void {
                foreach ($permissions as $permission) {
                    $requiredPermissionsQuery->whereHas(
                        'roles.permissions',
                        function (Builder $permissionQuery) use ($permission): void {
                            $permissionQuery->where('slug', $permission);
                        }
                    );
                }
            });
        });
    }

    /**
     * Determine whether a user can be assigned workflow work for a domain.
     */
    public function userCanBeAssignedToDomain(User $user, string $domainKey): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $permissions = $this->requiredPermissionsForDomain($domainKey);

        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}
