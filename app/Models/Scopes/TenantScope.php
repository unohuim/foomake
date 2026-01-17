<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Class TenantScope
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to the given query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($user->hasRole('super-admin')) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $user->tenant_id);
    }
}
