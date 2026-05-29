<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Handle supplier-scoped purchase order read models.
 */
class SupplierPurchaseOrderController extends Controller
{
    /**
     * Display a paginated list of purchase orders for the current supplier.
     */
    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-view');

        if ($request->user()?->tenant_id !== $supplier->tenant_id) {
            abort(404);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginator = PurchaseOrder::query()
            ->with('supplier')
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator
                ->getCollection()
                ->map(fn (PurchaseOrder $purchaseOrder): array => $this->rowPayload($purchaseOrder))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Build a reusable detail-section row payload.
     *
     * @return array<string, mixed>
     */
    private function rowPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
            'supplier_name' => $purchaseOrder->supplier?->company_name,
            'po_number' => $purchaseOrder->po_number ?? null,
            'po_grand_total_cents' => $purchaseOrder->po_grand_total_cents,
            'status' => $purchaseOrder->workflowStatus(),
            'is_cancelled' => $purchaseOrder->isCancelled(),
            'is_back_ordered' => $purchaseOrder->back_ordered_at !== null,
            'show_url' => route('purchasing.orders.show', $purchaseOrder),
            'available_actions' => Gate::allows('purchasing-purchase-orders-create') ? ['view'] : [],
        ];
    }
}
