<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeLine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Handle recipe views and AJAX CRUD.
 */
class RecipeController extends Controller
{
    /**
     * Display the recipes index.
     */
    public function index(Request $request): View
    {
        Gate::authorize('inventory-recipes-view');

        $recipes = Recipe::query()
            ->with('item')
            ->withCount('lines')
            ->orderByDesc('updated_at')
            ->get();

        $manufacturableItems = Item::query()
            ->where('is_manufacturable', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $payload = [
            'recipes' => $recipes->map(function (Recipe $recipe) {
                return $this->recipePayload($recipe);
            })->all(),
            'manufacturable_items' => $manufacturableItems->map(function (Item $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                ];
            })->all(),
            'store_url' => route('manufacturing.recipes.store'),
            'update_url_base' => url('/manufacturing/recipes'),
            'delete_url_base' => url('/manufacturing/recipes'),
            'show_url_base' => url('/manufacturing/recipes'),
            'csrf_token' => $request->session()->token(),
            'can_manage' => Gate::allows('inventory-make-orders-manage'),
        ];

        return view('manufacturing.recipes.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Display a recipe detail page.
     */
    public function show(Request $request, Recipe $recipe): View
    {
        Gate::authorize('inventory-recipes-view');

        $recipe->load([
            'item.baseUom',
            'lines' => function ($query) {
                $query->with('item.baseUom')->orderBy('id');
            },
        ]);

        $manufacturableItems = Item::query()
            ->where('is_manufacturable', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $items = Item::query()
            ->with('baseUom')
            ->orderBy('name')
            ->get(['id', 'name', 'base_uom_id']);

        $payload = [
            'recipe' => array_merge($this->recipePayload($recipe), [
                'item_uom' => $recipe->item?->baseUom
                    ? $recipe->item->baseUom->name . ' (' . $recipe->item->baseUom->symbol . ')'
                    : '—',
                'update_url' => route('manufacturing.recipes.update', $recipe),
                'delete_url' => route('manufacturing.recipes.destroy', $recipe),
                'has_lines' => $recipe->lines->isNotEmpty(),
            ]),
            'manufacturable_items' => $manufacturableItems->map(function (Item $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                ];
            })->all(),
            'items' => $items->map(function (Item $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'uom_display' => $item->baseUom
                        ? $item->baseUom->name . ' (' . $item->baseUom->symbol . ')'
                        : '—',
                ];
            })->all(),
            'lines' => $recipe->lines->map(function (RecipeLine $line) {
                return $this->linePayload($line);
            })->all(),
            'line_store_url' => route('manufacturing.recipes.lines.store', $recipe),
            'index_url' => route('manufacturing.recipes.index'),
            'csrf_token' => $request->session()->token(),
            'can_manage' => Gate::allows('inventory-make-orders-manage'),
        ];

        return view('manufacturing.recipes.show', [
            'recipe' => $recipe,
            'payload' => $payload,
        ]);
    }

    /**
     * Store a newly created recipe.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_manufacturable', true),
            ],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;
        $isDefault = $request->boolean('is_default');

        try {
            $recipe = DB::transaction(function () use ($tenantId, $validated, $isActive, $isDefault) {
                if ($isDefault) {
                    Recipe::query()
                        ->where('tenant_id', $tenantId)
                        ->where('item_id', $validated['item_id'])
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                return Recipe::query()->create([
                    'tenant_id' => $tenantId,
                    'item_id' => $validated['item_id'],
                    'is_active' => $isActive,
                    'is_default' => $isDefault,
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            return $this->handleRecipeException($exception);
        }

        $recipe->load('item');
        $recipe->loadCount('lines');

        return response()->json([
            'data' => $this->recipePayload($recipe),
        ], 201);
    }

    /**
     * Update an existing recipe.
     */
    public function update(Request $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_manufacturable', true),
            ],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $hasLines = $recipe->lines()->exists();
        $itemId = (int) $validated['item_id'];

        if ($hasLines && $itemId !== $recipe->item_id) {
            return $this->validationError([
                'item_id' => ['Output item cannot be changed once recipe has lines.'],
            ]);
        }

        $tenantId = $request->user()->tenant_id;
        $isDefault = $request->has('is_default') ? $request->boolean('is_default') : $recipe->is_default;

        try {
            DB::transaction(function () use ($recipe, $tenantId, $itemId, $request, $isDefault) {
                if ($isDefault) {
                    Recipe::query()
                        ->where('tenant_id', $tenantId)
                        ->where('item_id', $itemId)
                        ->where('is_default', true)
                        ->where('id', '!=', $recipe->id)
                        ->update(['is_default' => false]);
                }

                $recipe->item_id = $itemId;
                $recipe->is_active = $request->boolean('is_active');
                $recipe->is_default = $isDefault;
                $recipe->save();
            });
        } catch (InvalidArgumentException $exception) {
            return $this->handleRecipeException($exception);
        }

        $recipe->load('item');
        $recipe->loadCount('lines');

        return response()->json([
            'data' => $this->recipePayload($recipe),
        ]);
    }

    /**
     * Delete a recipe.
     */
    public function destroy(Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $recipe->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    /**
     * Store a new recipe line.
     */
    public function storeLine(Request $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
        ]);

        if ((int) $validated['item_id'] === $recipe->item_id) {
            return $this->validationError([
                'item_id' => ['Line item cannot reference the output item.'],
            ]);
        }

        if (bccomp($validated['quantity'], '0', 6) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $line = RecipeLine::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'recipe_id' => $recipe->id,
            'item_id' => $validated['item_id'],
            'quantity' => $validated['quantity'],
        ]);

        $line->load('item.baseUom');

        return response()->json([
            'data' => $this->linePayload($line),
        ], 201);
    }

    /**
     * Update an existing recipe line.
     */
    public function updateLine(Request $request, Recipe $recipe, int $line): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $lineModel = $recipe->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
        ]);

        if ((int) $validated['item_id'] === $recipe->item_id) {
            return $this->validationError([
                'item_id' => ['Line item cannot reference the output item.'],
            ]);
        }

        if (bccomp($validated['quantity'], '0', 6) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $lineModel->item_id = $validated['item_id'];
        $lineModel->quantity = $validated['quantity'];
        $lineModel->save();

        $lineModel->load('item.baseUom');

        return response()->json([
            'data' => $this->linePayload($lineModel),
        ]);
    }

    /**
     * Delete a recipe line.
     */
    public function destroyLine(Request $request, Recipe $recipe, int $line): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $lineModel = $recipe->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $lineModel->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    /**
     * Build JSON payload for a recipe list entry.
     */
    private function recipePayload(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'item_id' => $recipe->item_id,
            'item_name' => $recipe->item?->name ?? '—',
            'is_active' => $recipe->is_active,
            'is_default' => $recipe->is_default,
            'updated_at' => $recipe->updated_at?->format('Y-m-d H:i') ?? '—',
            'lines_count' => $recipe->lines_count ?? 0,
            'show_url' => route('manufacturing.recipes.show', $recipe),
        ];
    }

    /**
     * Build JSON payload for a recipe line.
     */
    private function linePayload(RecipeLine $line): array
    {
        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item?->name ?? '—',
            'item_uom' => $line->item?->baseUom
                ? $line->item->baseUom->name . ' (' . $line->item->baseUom->symbol . ')'
                : '—',
            'quantity' => $line->quantity,
            'quantity_display' => number_format((float) $line->quantity, 2, '.', ''),
            'update_url' => route('manufacturing.recipes.lines.update', [
                'recipe' => $line->recipe_id,
                'line' => $line->id,
            ]),
            'delete_url' => route('manufacturing.recipes.lines.destroy', [
                'recipe' => $line->recipe_id,
                'line' => $line->id,
            ]),
        ];
    }

    /**
     * Return a standardized validation error response.
     */
    private function validationError(array $errors, string $message = 'Validation failed.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Map recipe domain exceptions to validation-style responses.
     */
    private function handleRecipeException(InvalidArgumentException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        $field = 'item_id';

        if (str_contains($message, 'active recipe')) {
            $field = 'is_active';
        }

        return $this->validationError([
            $field => [$message],
        ], $message);
    }
}
