<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CompleteSalesOrderAction;
use App\Http\Requests\Sales\UpdateSalesOrderStatusRequest;
use App\Models\SalesOrder;
use DomainException;
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
    public function update(
        UpdateSalesOrderStatusRequest $request,
        SalesOrder $salesOrder,
        CompleteSalesOrderAction $completeSalesOrderAction
    ): JsonResponse {
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

        try {
            if ($targetStatus === SalesOrder::STATUS_COMPLETED) {
                $salesOrder = $completeSalesOrderAction->execute($salesOrder);
            } else {
                $salesOrder->forceFill(['status' => $targetStatus])->save();
                $salesOrder->refresh();
            }
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'status' => [$exception->getMessage()],
                ],
            ], 422);
        }

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
