<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof User) {
            return;
        }

        // Only apply scoping after authentication has resolved a user.
        if (! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        if (! $user?->tenant_id) {
            return;
        }

        // 3. Super-admin bypass ONLY for User scoping (prevents role lookup recursion/hangs)
        if ($model instanceof User) {
            if ($this->isSuperAdmin((int) $user->getAuthIdentifier())) {
                return;
            }
        }

        // 4. Apply tenant isolation
        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $user->tenant_id
        );
    }

    private function isSuperAdmin(int $userId): bool
    {
        return DB::table('roles_users')
            ->join('roles', 'roles.id', '=', 'roles_users.role_id')
            ->where('roles_users.user_id', $userId)
            ->where('roles.name', 'super-admin')
            ->exists();
    }
}
