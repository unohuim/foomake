<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\UpdateSalesOrderStatusRequest;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Handle sales order lifecycle status updates.
 */
class SalesOrderStatusController extends Controller
{
    /**
     * Update the lifecycle status for a sales order.
     */
    public function update(UpdateSalesOrderStatusRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('sales-sales-orders-manage');

        $targetStatus = (string) $request->validated('status');

        if (! $salesOrder->canTransitionTo($targetStatus)) {
            return response()->json([
                'message' => 'Status transition is not allowed.',
                'errors' => [
                    'status' => ['Status transition is not allowed.'],
                ],
            ], 422);
        }

        $salesOrder->forceFill(['status' => $targetStatus])->save();

        return response()->json([
            'data' => [
                'id' => $salesOrder->id,
                'status' => $salesOrder->status,
                'available_status_transitions' => $salesOrder->availableTransitions(),
                'can_edit' => $salesOrder->isEditable(),
                'can_manage_lines' => $salesOrder->allowsLineMutations(),
            ],
        ]);
    }
}
