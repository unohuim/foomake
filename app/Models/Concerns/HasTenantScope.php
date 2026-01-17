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
        // No auth → do nothing (critical for login, sessions, tests)
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Super-admin → no tenant constraint
        if ($user->hasRole('super-admin')) {
            return;
        }

        // No tenant assigned → do nothing (safety)
        if (! $user->tenant_id) {
            return;
        }

        // Apply tenant isolation
        $builder->where(
            $model->getTable() . '.tenant_id',
            $user->tenant_id
        );
    }
}
