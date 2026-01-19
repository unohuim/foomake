<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    /**
     * Store a newly created material (item).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        if (!Uom::query()->exists()) {
            return response()->json([
                'message' => 'No units of measure exist.',
                'errors' => [
                    'base_uom_id' => ['No units of measure exist.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
        ]);

        $item = Item::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'base_uom_id' => $validated['base_uom_id'],
            'is_purchasable' => $request->boolean('is_purchasable'),
            'is_sellable' => $request->boolean('is_sellable'),
            'is_manufacturable' => $request->boolean('is_manufacturable'),
        ]);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
            ],
        ], 201);
    }

    /**
     * Update an existing material (item).
     *
     * @param Request $request
     * @param Item $item
     * @return JsonResponse
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
        ]);

        $hasStockMoves = $item->stockMoves()->exists();
        $baseUomId = (int) $validated['base_uom_id'];

        if ($hasStockMoves && $baseUomId !== $item->base_uom_id) {
            return response()->json([
                'message' => 'Base unit of measure is locked.',
                'errors' => [
                    'base_uom_id' => ['Base unit of measure cannot be changed once stock moves exist.'],
                ],
            ], 422);
        }

        $updateData = [
            'name' => $validated['name'],
            'base_uom_id' => $baseUomId,
        ];

        $flagFields = ['is_purchasable', 'is_sellable', 'is_manufacturable'];

        foreach ($flagFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->boolean($field);
            }
        }

        $item->update($updateData);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
                'has_stock_moves' => $hasStockMoves,
            ],
        ]);
    }

    /**
     * Delete a material (item).
     *
     * @param Item $item
     * @return JsonResponse
     */
    public function destroy(Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        if ($item->stockMoves()->exists()) {
            return response()->json([
                'message' => 'Material cannot be deleted because stock moves exist.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }
}
