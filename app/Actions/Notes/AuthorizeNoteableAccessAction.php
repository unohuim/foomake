<?php

namespace App\Actions\Notes;

use App\Actions\Workflows\CanViewAssignedWorkflowResourceAction;
use App\Models\InventoryCount;
use App\Models\MakeOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Authorizes notes access through the supported parent resource rules.
 */
class AuthorizeNoteableAccessAction
{
    /**
     * Determine whether the user may access notes for the given parent resource.
     */
    public function execute(User $user, Model $noteable): bool
    {
        if (! property_exists($noteable, 'tenant_id') && ! isset($noteable->tenant_id)) {
            return false;
        }

        if ((int) $noteable->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        return match ($noteable::class) {
            InventoryCount::class => Gate::allows('inventory-adjustments-view')
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $user,
                    $noteable,
                    'inventory',
                    $noteable->assigned_to_user_id
                ),
            MakeOrder::class => Gate::allows('inventory-make-orders-view')
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $user,
                    $noteable,
                    'manufacturing',
                    $noteable->made_by_user_id
                ),
            PurchaseOrder::class => Gate::allows('purchasing-purchase-orders-create')
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $user,
                    $noteable,
                    'purchasing'
                ),
            SalesOrder::class => Gate::allows('sales-sales-orders-manage')
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $user,
                    $noteable,
                    'sales'
                ),
            default => false,
        };
    }
}
