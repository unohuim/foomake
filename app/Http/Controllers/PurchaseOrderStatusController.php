<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PurchaseOrderStatusController extends Controller
{
    /**
     * Update a purchase order status manually.
     */
    public function update(Request $request, int $purchaseOrder): JsonResponse
    {
        Gate::authorize('purchasing-purchase-orders-receive');

        $purchaseOrder = PurchaseOrder::query()->findOrFail($purchaseOrder);

        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                'string',
                Rule::in([
                    PurchaseOrder::STATUS_OPEN,
                    PurchaseOrder::STATUS_BACK_ORDERED,
                    PurchaseOrder::STATUS_CANCELLED,
                ]),
            ],
        ]);

        $validated = $validator->validate();
        $targetStatus = $validated['status'];

        $currentStatus = (string) $purchaseOrder->status;

        $allowed = false;

        if ($targetStatus === PurchaseOrder::STATUS_OPEN
            && in_array($currentStatus, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_BACK_ORDERED], true)) {
            $allowed = true;
        }

        if ($targetStatus === PurchaseOrder::STATUS_BACK_ORDERED
            && $currentStatus === PurchaseOrder::STATUS_OPEN) {
            $allowed = true;
        }

        if ($targetStatus === PurchaseOrder::STATUS_CANCELLED
            && $currentStatus === PurchaseOrder::STATUS_OPEN) {
            $receiptsExist = DB::table('purchase_order_receipts')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->exists();

            if (! $receiptsExist) {
                $allowed = true;
            }
        }

        if (! $allowed) {
            return response()->json([
                'message' => 'Status transition is not allowed.',
                'errors' => [
                    'status' => ['Status transition is not allowed.'],
                ],
            ], 422);
        }

        $purchaseOrder->forceFill(['status' => $targetStatus])->save();

        return response()->json([
            'data' => [
                'status' => $purchaseOrder->status,
            ],
        ]);
    }
}
