<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\ExecuteRecipeAction;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Handle make order execution flows.
 */
class MakeOrderController extends Controller
{
    /**
     * Display the make orders execution page.
     */
    public function index(Request $request): View
    {
        Gate::authorize('inventory-make-orders-view');

        $recipes = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->with('item')
            ->orderBy('id')
            ->get();

        $payload = [
            'recipes' => $recipes->map(function (Recipe $recipe) {
                return $this->recipePayload($recipe);
            })->all(),
            'execute_url' => route('manufacturing.make-orders.execute'),
            'csrf_token' => $request->session()->token(),
            'can_execute' => Gate::allows('inventory-make-orders-execute'),
        ];

        return view('manufacturing.make-orders.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Execute a recipe and create ledger stock moves.
     */
    public function execute(Request $request, ExecuteRecipeAction $action): JsonResponse
    {
        Gate::authorize('inventory-make-orders-execute');

        $validated = $request->validate([
            'recipe_id' => [
                'required',
                'integer',
                Rule::exists('recipes', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'output_quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
        ]);

        if (bccomp($validated['output_quantity'], '0', 6) !== 1) {
            return $this->validationError([
                'output_quantity' => ['Output quantity must be greater than zero.'],
            ], 'Output quantity must be greater than zero.');
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('item')
            ->findOrFail($validated['recipe_id']);

        if (!$recipe->is_active) {
            return $this->validationError([
                'recipe_id' => ['Recipe must be active to execute.'],
            ], 'Recipe must be active to execute.');
        }

        try {
            $moves = $action->execute($recipe, $validated['output_quantity']);
        } catch (InvalidArgumentException $exception) {
            return $this->handleExecuteException($exception);
        }

        $issueCount = collect($moves)
            ->where('type', 'issue')
            ->count();

        $receiptCount = collect($moves)
            ->where('type', 'receipt')
            ->count();

        return response()->json([
            'success' => true,
            'toast' => [
                'message' => 'Make order executed.',
                'type' => 'success',
            ],
            'summary' => [
                'recipe_id' => $recipe->id,
                'output_item_id' => $recipe->item_id,
                'output_item_name' => $recipe->item?->name ?? '—',
                'output_quantity' => $validated['output_quantity'],
                'issue_count' => $issueCount,
                'receipt_count' => $receiptCount,
                'move_count' => count($moves),
            ],
        ]);
    }

    /**
     * Build JSON payload for a recipe selector entry.
     */
    private function recipePayload(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'item_id' => $recipe->item_id,
            'item_name' => $recipe->item?->name ?? '—',
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
     * Map execution domain exceptions to validation-style responses.
     */
    private function handleExecuteException(InvalidArgumentException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        $field = 'recipe_id';

        if (str_contains($message, 'quantity')) {
            $field = 'output_quantity';
        }

        return $this->validationError([
            $field => [$message],
        ], $message);
    }
}
