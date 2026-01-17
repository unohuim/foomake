<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof User) {
            return;
        }

        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        if (! $user?->tenant_id) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $user->tenant_id
        );
    }
}
