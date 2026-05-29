<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
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
                ])),
            ],
        ]);
    }

    /**
     * Cancel the purchase order workflow.
     */
    public function cancel(
        Request $request,
        PurchaseOrder $purchaseOrder,
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
                ])),
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
    private function purchaseOrderPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'status' => $purchaseOrder->workflowStatus(),
            'is_cancelled' => $purchaseOrder->workflow_cancelled_at !== null,
            'is_editable' => $purchaseOrder->isWorkflowEditable(),
            'current_workflow_stage_id' => $purchaseOrder->current_workflow_stage_id,
            'last_completed_workflow_stage_id' => $purchaseOrder->last_completed_workflow_stage_id,
        ];
    }
}
