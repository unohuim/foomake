<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\UpdateSalesOrderStatusRequest;
use App\Models\SalesOrder;
use App\Services\Workflows\SalesOrderWorkflow;
use DomainException;
use Illuminate\Http\JsonResponse;

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
        SalesOrderWorkflow $workflow
    ): JsonResponse {
        $targetStatus = (string) $request->validated('status');

        try {
            /** @var SalesOrder $salesOrder */
            $salesOrder = $workflow->transition($salesOrder, $targetStatus);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'status' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json($workflow->responsePayload($salesOrder));
    }
}
