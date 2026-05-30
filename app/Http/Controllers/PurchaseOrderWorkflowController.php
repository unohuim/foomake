<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use App\Services\Workflows\WorkflowTransitionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handle purchase order workflow AJAX actions.
 */
class PurchaseOrderWorkflowController extends Controller
{
    /**
     * Complete the current purchase order workflow stage.
     */
    public function complete(
        Request $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService,
        WorkflowTransitionService $workflowTransitionService
    ): JsonResponse {
        try {
            $workflow = $workflowTransitionService->completePurchaseOrderStage(
                $purchaseOrder,
                $request->user()
            );
        } catch (DomainException $exception) {
            return $this->workflowError($exception->getMessage());
        }

        return response()->json([
            'data' => [
                'workflow' => $workflow,
                'purchase_order' => $this->purchaseOrderPayload($purchaseOrder->fresh([
                    'currentWorkflowStage',
                    'lastCompletedWorkflowStage',
                    'lines',
                ]), $lifecycleService),
            ],
        ]);
    }

    /**
     * Cancel the purchase order workflow.
     */
    public function cancel(
        Request $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService,
        WorkflowTransitionService $workflowTransitionService
    ): JsonResponse {
        try {
            $workflow = $workflowTransitionService->cancelPurchaseOrder(
                $purchaseOrder,
                $request->user()
            );
        } catch (DomainException $exception) {
            return $this->workflowError($exception->getMessage());
        }

        return response()->json([
            'data' => [
                'workflow' => $workflow,
                'purchase_order' => $this->purchaseOrderPayload($purchaseOrder->fresh([
                    'currentWorkflowStage',
                    'lastCompletedWorkflowStage',
                    'lines',
                ]), $lifecycleService),
            ],
        ]);
    }

    /**
     * Build a workflow validation error response.
     */
    private function workflowError(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => [
                'workflow' => [$message],
            ],
        ], 422);
    }

    /**
     * Build the purchase order state returned to the detail page.
     *
     * @return array<string, bool|int|string|null>
     */
    private function purchaseOrderPayload(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService
    ): array {
        $lineTotals = $lifecycleService->computeLineTotals($purchaseOrder);

        return [
            'id' => $purchaseOrder->id,
            'status' => $purchaseOrder->workflowStatus(),
            'persisted_status' => $purchaseOrder->status,
            'is_cancelled' => $purchaseOrder->workflow_cancelled_at !== null,
            'is_editable' => $purchaseOrder->isWorkflowEditable(),
            'is_back_ordered' => $purchaseOrder->back_ordered_at !== null,
            'has_receipts' => $purchaseOrder->receipts()->exists(),
            'can_receive' => $purchaseOrder->isReceivingStage()
                && collect($lineTotals)->contains(fn (array $totals): bool => bccomp(
                    $totals['balance'],
                    '0',
                    6
                ) === 1),
            'current_workflow_stage_id' => $purchaseOrder->current_workflow_stage_id,
            'last_completed_workflow_stage_id' => $purchaseOrder->last_completed_workflow_stage_id,
        ];
    }
}
