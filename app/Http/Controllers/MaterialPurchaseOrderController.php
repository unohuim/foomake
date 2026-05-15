<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaterialPurchaseOrderController extends Controller
{
    /**
     * Display a paginated list of purchase orders that include the current material.
     */
    public function index(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-purchase-orders-create');

        $paginator = PurchaseOrder::query()
            ->with('supplier')
            ->whereHas('lines', function ($query) use ($item): void {
                $query->where('item_id', $item->id);
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10);

        $data = $paginator->getCollection()
            ->map(fn (PurchaseOrder $purchaseOrder): array => $this->rowPayload($purchaseOrder))
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
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
            'status' => $purchaseOrder->status,
            'show_url' => route('purchasing.orders.show', $purchaseOrder),
            'available_actions' => ['view'],
        ];
    }
}
