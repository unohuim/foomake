<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // 1. No auth → no constraint (login, sessions, tests)
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        // 2. Super-admin → no constraint
        if ($user?->hasRole('super-admin')) {
            return;
        }

        // 3. No tenant → fail open (never hard-fail auth)
        if (! $user?->tenant_id) {
            return;
        }

        // 4. Apply tenant isolation
        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $user->tenant_id
        );
    }
}
