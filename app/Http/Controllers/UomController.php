<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use App\Models\UomCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Class UomController
 *
 * Handles Units of Measure CRUD operations.
 */
class UomController extends Controller
{
    /**
     * Display the Units of Measure index.
     */
    public function index(): View
    {
        Gate::authorize('inventory-materials-manage');

        $categories = UomCategory::query()
            ->with(['uoms' => function ($query) {
                $query->orderBy('name')
                    ->select(['id', 'uom_category_id', 'name', 'symbol']);
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('manufacturing.uoms.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a new Unit of Measure.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $validated = $request->validate([
            'uom_category_id' => ['required', 'integer', 'exists:uom_categories,id'],
            'name' => ['required', 'string', 'max:255', 'unique:uoms,name'],
            'symbol' => ['required', 'string', 'max:255', 'unique:uoms,symbol'],
        ]);

        $uom = Uom::create($validated);

        return response()->json([
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
        ], 201);
    }

    /**
     * Update the specified Unit of Measure.
     */
    public function update(Request $request, Uom $uom): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $validated = $request->validate([
            'uom_category_id' => ['required', 'integer', 'exists:uom_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uoms', 'name')->ignore($uom->id),
            ],
            'symbol' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uoms', 'symbol')->ignore($uom->id),
            ],
        ]);

        $uom->update($validated);

        return response()->json([
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
        ]);
    }

    /**
     * Remove the specified Unit of Measure.
     */
    public function destroy(Uom $uom): Response|JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        try {
            $uom->delete();
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to delete the unit of measure.',
            ], 409);
        }

        return response()->noContent();
    }
}
