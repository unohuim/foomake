<?php

namespace App\Http\Controllers;

use App\Actions\Sales\BuildSalesOrderIssuePlanAction;
use App\Actions\Sales\CancelPackedSalesOrderAction;
use App\Actions\Sales\MoveSalesOrderToPackingAction;
use App\Actions\Sales\PackSalesOrderAction;
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
        BuildSalesOrderIssuePlanAction $buildPlanAction,
        MoveSalesOrderToPackingAction $moveToPackingAction,
        PackSalesOrderAction $packSalesOrderAction,
        CancelPackedSalesOrderAction $cancelPackedSalesOrderAction
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
            $salesOrder = match ($targetStatus) {
                SalesOrder::STATUS_PACKING => $moveToPackingAction->execute($salesOrder, $buildPlanAction),
                SalesOrder::STATUS_PACKED => $packSalesOrderAction->execute($salesOrder, $buildPlanAction),
                SalesOrder::STATUS_CANCELLED => $salesOrder->status === SalesOrder::STATUS_PACKED
                    ? $cancelPackedSalesOrderAction->execute($salesOrder)
                    : $this->transitionWithoutInventory($salesOrder, $targetStatus),
                default => $this->transitionWithoutInventory($salesOrder, $targetStatus),
            };
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

    /**
     * Persist a non-inventory lifecycle transition.
     */
    private function transitionWithoutInventory(SalesOrder $salesOrder, string $targetStatus): SalesOrder
    {
        $salesOrder->forceFill(['status' => $targetStatus])->save();

        return $salesOrder->fresh();
    }
}
