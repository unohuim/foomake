<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\StoreSupplierPurchaseOptionRequest;
use App\Models\ItemPurchaseOption;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Handle CRUD for supplier purchase options.
 */
class SupplierPurchaseOptionController extends Controller
{
    /**
     * Store a new purchase option for the supplier.
     */
    public function store(StoreSupplierPurchaseOptionRequest $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');
        $this->abortIfWrongTenant($request, $supplier);

        $validated = $request->validated();
        $option = ItemPurchaseOption::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'supplier_id' => $supplier->id,
            'item_id' => $validated['item_id'],
            'pack_quantity' => $validated['pack_quantity'],
            'pack_uom_id' => $validated['pack_uom_id'],
            'supplier_sku' => $validated['supplier_sku'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $option->id,
                'item_id' => $option->item_id,
                'supplier_id' => $option->supplier_id,
                'pack_quantity' => bcadd((string) $option->pack_quantity, '0', 6),
                'pack_uom_id' => $option->pack_uom_id,
                'supplier_sku' => $option->supplier_sku,
            ],
        ], 201);
    }

    /**
     * Delete a supplier purchase option.
     */
    public function destroy(Request $request, Supplier $supplier, ItemPurchaseOption $option): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');
        $this->abortIfWrongTenant($request, $supplier);

        if ($option->supplier_id !== $supplier->id) {
            abort(404);
        }

        $option->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Abort with 404 when tenant mismatch.
     */
    private function abortIfWrongTenant(Request $request, Supplier $supplier): void
    {
        if ($request->user()?->tenant_id !== $supplier->tenant_id) {
            abort(404);
        }
    }
}
