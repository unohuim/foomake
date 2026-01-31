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
 * Handles UoM CRUD operations.
 */
class UomController extends Controller
{
    /**
     * Display the UoM index.
     */
    public function index(): View
    {
        Gate::authorize('inventory-materials-manage');

        $categories = UomCategory::query()
            ->with(['uoms' => function ($query) {
                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('manufacturing.uoms.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a new UoM.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'uom_category_id' => [
                'required',
                'integer',
                Rule::exists('uom_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uoms', 'symbol')->where('tenant_id', $tenantId),
            ],
        ]);

        $uom = Uom::create([
            'tenant_id' => $tenantId,
            'uom_category_id' => $validated['uom_category_id'],
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
        ]);

        return response()->json([
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
        ], 201);
    }

    /**
     * Update the specified UoM.
     */
    public function update(Request $request, Uom $uom): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'uom_category_id' => [
                'required',
                'integer',
                Rule::exists('uom_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uoms', 'symbol')
                    ->where('tenant_id', $tenantId)
                    ->ignore($uom->id),
            ],
        ]);

        $uom->update([
            'uom_category_id' => $validated['uom_category_id'],
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
        ]);

        return response()->json([
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
        ]);
    }

    /**
     * Remove the specified UoM.
     */
    public function destroy(Uom $uom): Response|JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $uom->delete();

        return response()->noContent();
    }
}
