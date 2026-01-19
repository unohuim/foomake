<?php

namespace App\Http\Controllers;

use App\Models\UomCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Class UomCategoryController
 *
 * Handles UoM Category CRUD operations.
 */
class UomCategoryController extends Controller
{
    /**
     * Display the UoM Categories index.
     */
    public function index(): View
    {
        Gate::authorize('inventory-materials-manage');

        $categories = UomCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('materials.uom-categories.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a new UoM Category.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:uom_categories,name'],
        ]);

        $category = UomCategory::create($validated);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
        ], 201);
    }

    /**
     * Update the specified UoM Category.
     */
    public function update(Request $request, UomCategory $uomCategory): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uom_categories', 'name')->ignore($uomCategory->id),
            ],
        ]);

        $uomCategory->update($validated);

        return response()->json([
            'id' => $uomCategory->id,
            'name' => $uomCategory->name,
        ]);
    }

    /**
     * Remove the specified UoM Category.
     */
    public function destroy(UomCategory $uomCategory): Response
    {
        Gate::authorize('inventory-materials-manage');

        $uomCategory->delete();

        return response()->noContent();
    }
}
