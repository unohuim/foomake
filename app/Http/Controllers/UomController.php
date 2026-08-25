<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use App\Models\UomCategory;
use App\Support\Inertia\AuthShellPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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
    public function index(Request $request, AuthShellPayloadBuilder $authShellPayloadBuilder): InertiaResponse
    {
        Gate::authorize('inventory-materials-manage');

        $categories = UomCategory::query()
            ->with(['uoms' => function ($query) {
                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Manufacturing/Uoms/Index', [
            'shell' => $authShellPayloadBuilder->build($request),
            'crudConfig' => $this->uomsCrudConfig(),
            'payload' => [
                'categories' => $categories->map(fn (UomCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'uoms' => $category->uoms->map(fn (Uom $uom): array => $this->uomPayload($uom))->values()->all(),
                ])->values()->all(),
                'storeUrl' => route('manufacturing.uoms.store', absolute: false),
                'updateUrlTemplate' => route('manufacturing.uoms.update', ['uom' => '__ID__'], false),
                'deleteUrlTemplate' => route('manufacturing.uoms.destroy', ['uom' => '__ID__'], false),
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * Build the Units of Measure resource index configuration.
     *
     * @return array<string, mixed>
     */
    private function uomsCrudConfig(): array
    {
        return [
            'resource' => 'uoms',
            'labels' => [
                'title' => 'Units of Measure',
                'description' => 'Maintain the units used in manufacturing and inventory.',
                'searchPlaceholder' => 'Search units of measure',
                'createTitle' => 'Create Unit',
                'createAriaLabel' => 'Create unit of measure',
                'emptyState' => 'No units of measure yet.',
                'actionsAriaLabel' => 'Unit of measure actions',
            ],
            'permissions' => [
                'showCreate' => true,
                'showImport' => false,
                'showExport' => false,
            ],
            'actions' => [
                [
                    'id' => 'edit',
                    'label' => 'Edit',
                ],
                [
                    'id' => 'delete',
                    'label' => 'Delete',
                    'tone' => 'danger',
                ],
            ],
        ];
    }

    /**
     * Build a Units of Measure row payload.
     *
     * @return array<string, mixed>
     */
    private function uomPayload(Uom $uom): array
    {
        return [
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
            'display_precision' => $uom->display_precision,
        ];
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
            'display_precision' => ['sometimes', 'integer', 'between:0,6'],
        ]);

        $uom = Uom::create([
            'tenant_id' => $tenantId,
            'uom_category_id' => $validated['uom_category_id'],
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'display_precision' => $validated['display_precision'] ?? 1,
        ]);

        return response()->json([
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
            'display_precision' => $uom->display_precision,
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
            'display_precision' => ['sometimes', 'integer', 'between:0,6'],
        ]);

        $uom->update([
            'uom_category_id' => $validated['uom_category_id'],
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'display_precision' => $validated['display_precision'] ?? $uom->display_precision,
        ]);

        return response()->json([
            'id' => $uom->id,
            'uom_category_id' => $uom->uom_category_id,
            'name' => $uom->name,
            'symbol' => $uom->symbol,
            'display_precision' => $uom->display_precision,
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
