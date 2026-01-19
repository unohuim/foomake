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

        $item->load('baseUom');

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom' => [
                    'id' => $item->baseUom->id,
                    'name' => $item->baseUom->name,
                    'symbol' => $item->baseUom->symbol,
                ],
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
            ],
        ], 201);
    }
}
