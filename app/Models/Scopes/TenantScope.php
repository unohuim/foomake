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
        // Unauthenticated → no constraint (login, sessions, tests)
        if (! Auth::check()) {
            return;
        }

        // During the authentication process, the guard may be "checked" but the user
        // instance is not yet resolved. In that window, do not scope User queries.
        if ($model instanceof User && ! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        // No tenant → fail open (never hard-fail auth)
        if (! $user?->tenant_id) {
            return;
        }

        // Super-admin bypass for User queries (safe: raw DB, no Eloquent recursion)
        if ($model instanceof User && $this->isSuperAdmin((int) $user->getAuthIdentifier())) {
            return;
        }

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
