<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\ExecuteRecipeAction;
use App\Models\MakeOrder;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Handle make order views and lifecycle actions.
 */
class MakeOrderController extends Controller
{
    /**
     * Display the make orders index.
     */
    public function index(Request $request): View
    {
        Gate::authorize('inventory-make-orders-view');

        $makeOrders = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['recipe', 'outputItem'])
            ->orderByDesc('created_at')
            ->get();

        $recipes = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->with('item')
            ->orderBy('id')
            ->get();

        $payload = [
            'make_orders' => $makeOrders->map(function (MakeOrder $makeOrder) {
                return $this->makeOrderPayload($makeOrder);
            })->all(),
            'recipes' => $recipes->map(function (Recipe $recipe) {
                return $this->recipePayload($recipe);
            })->all(),
            'store_url' => route('manufacturing.make-orders.store'),
            'schedule_url_base' => url('/manufacturing/make-orders'),
            'make_url_base' => url('/manufacturing/make-orders'),
            'csrf_token' => $request->session()->token(),
            'can_execute' => Gate::allows('inventory-make-orders-execute'),
        ];

        return view('manufacturing.make-orders.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Store a new draft make order.
     */
    public function store(Request $request): JsonResponse
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

        $makeOrder = MakeOrder::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $recipe->item_id,
            'output_quantity' => $validated['output_quantity'],
            'status' => MakeOrder::STATUS_DRAFT,
            'created_by_user_id' => $request->user()->id,
        ]);

        $makeOrder->load(['recipe', 'outputItem']);

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrder),
        ], 201);
    }

    /**
     * Schedule a draft make order.
     */
    public function schedule(Request $request, int $makeOrder): JsonResponse
    {
        Gate::authorize('inventory-make-orders-execute');

        $validated = $request->validate([
            'due_date' => ['required', 'date'],
        ]);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['recipe', 'outputItem'])
            ->findOrFail($makeOrder);

        if ($makeOrderModel->status === MakeOrder::STATUS_MADE) {
            return response()->json([
                'message' => 'Make order is already made.',
            ], 422);
        }

        if (!$makeOrderModel->recipe || !$makeOrderModel->recipe->is_active) {
            return $this->validationError([
                'recipe_id' => ['Recipe must be active to execute.'],
            ], 'Recipe must be active to execute.');
        }

        $makeOrderModel->due_date = Carbon::parse($validated['due_date'])->startOfDay();
        $makeOrderModel->scheduled_at = now();
        $makeOrderModel->status = MakeOrder::STATUS_SCHEDULED;
        $makeOrderModel->save();

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel),
        ]);
    }

    /**
     * Execute a make order and post stock moves.
     */
    public function make(Request $request, int $makeOrder, ExecuteRecipeAction $action): JsonResponse
    {
        Gate::authorize('inventory-make-orders-execute');

        try {
            $result = DB::transaction(function () use ($request, $makeOrder, $action) {
                $makeOrderModel = MakeOrder::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->lockForUpdate()
                    ->findOrFail($makeOrder);

                if ($makeOrderModel->status === MakeOrder::STATUS_MADE) {
                    return [
                        'error' => response()->json([
                            'message' => 'Make order is already made.',
                        ], 422),
                    ];
                }

                $recipe = Recipe::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->with('item')
                    ->findOrFail($makeOrderModel->recipe_id);

                if (!$recipe->is_active) {
                    return [
                        'error' => $this->validationError([
                            'recipe_id' => ['Recipe must be active to execute.'],
                        ], 'Recipe must be active to execute.'),
                    ];
                }

                $moves = $action->execute($recipe, (string) $makeOrderModel->output_quantity);

                $makeOrderModel->status = MakeOrder::STATUS_MADE;
                $makeOrderModel->made_at = now();
                $makeOrderModel->made_by_user_id = $request->user()->id;
                $makeOrderModel->save();

                $makeOrderModel->load(['recipe', 'outputItem']);

                return [
                    'make_order' => $makeOrderModel,
                    'moves' => $moves,
                ];
            });
        } catch (InvalidArgumentException $exception) {
            return $this->handleExecuteException($exception);
        }

        if (isset($result['error'])) {
            return $result['error'];
        }

        return response()->json([
            'data' => $this->makeOrderPayload($result['make_order']),
        ]);
    }

    /**
     * Build JSON payload for a make order list entry.
     */
    private function makeOrderPayload(MakeOrder $makeOrder): array
    {
        return [
            'id' => $makeOrder->id,
            'recipe_id' => $makeOrder->recipe_id,
            'output_item_id' => $makeOrder->output_item_id,
            'output_item_name' => $makeOrder->outputItem?->name ?? '—',
            'output_quantity' => (string) $makeOrder->output_quantity,
            'status' => $makeOrder->status,
            'due_date' => $makeOrder->due_date?->format('Y-m-d'),
            'scheduled_at' => $makeOrder->scheduled_at?->format('Y-m-d H:i'),
            'made_at' => $makeOrder->made_at?->format('Y-m-d H:i'),
            'created_by_user_id' => $makeOrder->created_by_user_id,
            'made_by_user_id' => $makeOrder->made_by_user_id,
        ];
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
