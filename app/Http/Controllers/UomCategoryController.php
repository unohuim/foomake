<?php

namespace App\Http\Controllers;

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
 * Class UomCategoryController
 *
 * Handles UoM Category CRUD operations.
 */
class UomCategoryController extends Controller
{
    /**
     * Display the UoM Categories index.
     */
    public function index(Request $request, AuthShellPayloadBuilder $authShellPayloadBuilder): InertiaResponse
    {
        Gate::authorize('inventory-materials-manage');

        $categories = UomCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Materials/UomCategories/Index', [
            'shell' => $authShellPayloadBuilder->build($request),
            'crudConfig' => $this->uomCategoriesCrudConfig(),
            'payload' => [
                'categories' => $categories->map(fn (UomCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values()->all(),
                'storeUrl' => route('materials.uom-categories.store', absolute: false),
                'updateUrlTemplate' => route(
                    'materials.uom-categories.update',
                    ['uomCategory' => '__ID__'],
                    false
                ),
                'deleteUrlTemplate' => route(
                    'materials.uom-categories.destroy',
                    ['uomCategory' => '__ID__'],
                    false
                ),
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * Build the UoM categories resource index configuration.
     *
     * @return array<string, mixed>
     */
    private function uomCategoriesCrudConfig(): array
    {
        return [
            'resource' => 'uom-categories',
            'labels' => [
                'title' => 'UoM Categories',
                'description' => 'Define categories that group related units of measure.',
                'searchPlaceholder' => 'Search UoM categories',
                'createTitle' => 'Create UoM Category',
                'createAriaLabel' => 'Create UoM category',
                'emptyState' => 'No UoM categories yet.',
                'actionsAriaLabel' => 'UoM category actions',
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
     * Store a new UoM Category.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uom_categories', 'name')->where('tenant_id', $tenantId),
            ],
        ]);

        $category = UomCategory::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
        ]);

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

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uom_categories', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($uomCategory->id),
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
