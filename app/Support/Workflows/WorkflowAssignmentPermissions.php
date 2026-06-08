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
            'inventory' => ['inventory-adjustments-execute'],
            'manufacturing' => ['inventory-make-orders-execute'],
            'purchasing' => ['purchasing-purchase-orders-receive'],
            'sales' => ['sales-sales-orders-update'],
            default => [],
        };
    }

    /**
     * Return permission slugs required before a user may own or be responsible for workflow movement.
     *
     * @return list<string>
     */
    public function requiredOwnerPermissionsForDomain(string $domainKey): array
    {
        return match ($domainKey) {
            'inventory' => ['inventory-adjustments-execute'],
            'manufacturing' => ['inventory-make-orders-view', 'inventory-make-orders-execute'],
            'purchasing' => ['purchasing-purchase-orders-create'],
            'sales' => ['sales-sales-orders-manage'],
            default => [],
        };
    }

    /**
     * Return permission slugs required before a user may move a workflow or edit operational workflow sections.
     *
     * @return list<string>
     */
    public function requiredOperatorPermissionsForDomain(string $domainKey): array
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
        return $this->eligibleUsersForPermissionsQuery(
            $tenantId,
            $this->requiredPermissionsForDomain($domainKey)
        );
    }

    /**
     * Scope a tenant user query to users who can own workflow movement for a domain.
     */
    public function ownerEligibleUsersQuery(int $tenantId, string $domainKey): Builder
    {
        return $this->eligibleUsersForPermissionsQuery(
            $tenantId,
            $this->requiredOwnerPermissionsForDomain($domainKey)
        );
    }

    /**
     * Determine whether a user can be assigned workflow work for a domain.
     */
    public function userCanBeAssignedToDomain(User $user, string $domainKey): bool
    {
        return $this->userHasAllPermissions($user, $this->requiredPermissionsForDomain($domainKey));
    }

    /**
     * Determine whether a user can own workflow movement for a domain.
     */
    public function userCanOwnWorkflowDomain(User $user, string $domainKey): bool
    {
        return $this->userHasAllPermissions($user, $this->requiredOwnerPermissionsForDomain($domainKey));
    }

    /**
     * Determine whether a user can move workflows and edit operational workflow sections for a domain.
     */
    public function userCanOperateWorkflowDomain(User $user, string $domainKey): bool
    {
        return $this->userHasAllPermissions($user, $this->requiredOperatorPermissionsForDomain($domainKey));
    }

    /**
     * Scope a tenant user query to users with all requested permission slugs.
     *
     * @param list<string> $permissions
     */
    private function eligibleUsersForPermissionsQuery(int $tenantId, array $permissions): Builder
    {
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
     * Determine whether a user has all requested permission slugs.
     *
     * @param list<string> $permissions
     */
    private function userHasAllPermissions(User $user, array $permissions): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

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
