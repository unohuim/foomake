<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\ExecuteRecipeAction;
use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\Recipe;
use App\Support\QuantityFormatter;
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
    private const SCALE = 6;

    /**
     * Display the make orders index.
     */
    public function index(Request $request): View
    {
        Gate::authorize('inventory-make-orders-view');

        $recipes = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->where('recipe_type', Recipe::TYPE_MANUFACTURING)
            ->with('item.baseUom')
            ->orderBy('id')
            ->get();

        $canExecute = Gate::allows('inventory-make-orders-execute');
        $crudConfig = $this->crudConfig($canExecute);
        $payload = [
            'recipes' => $recipes->map(function (Recipe $recipe) {
                return $this->recipePayload($recipe);
            })->all(),
            'storeUrl' => route('manufacturing.make-orders.store'),
            'csrfToken' => $request->session()->token(),
            'canExecute' => $canExecute,
            'prefillRecipeId' => $this->prefillRecipeId($request),
        ];

        return view('manufacturing.make-orders.index', [
            'crudConfig' => $crudConfig,
            'payload' => $payload,
        ]);
    }

    /**
     * Return the make orders list read model for the shared CRUD page module.
     */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('inventory-make-orders-view');

        $crudConfig = $this->crudConfig(Gate::allows('inventory-make-orders-execute'));
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'due_date');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $rows = $this->makeOrdersListRows(
            (int) $request->user()->tenant_id,
            $search,
            $sortColumn,
            $direction
        );

        return response()->json([
            'data' => $rows,
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => count($rows),
            ],
        ]);
    }

    /**
     * Display a minimal read-only make order detail page.
     */
    public function show(Request $request, MakeOrder $makeOrder): View
    {
        Gate::authorize('inventory-make-orders-view');
        abort_unless((int) $makeOrder->tenant_id === (int) $request->user()->tenant_id, 404);

        $makeOrder->load(['recipe.item.baseUom', 'outputItem.baseUom']);

        $totalOutputQuantity = $this->totalOutputQuantity($makeOrder);

        return view('manufacturing.make-orders.show', [
            'makeOrder' => $makeOrder,
            'totalOutputQuantity' => $totalOutputQuantity,
            'totalOutputQuantityDisplay' => QuantityFormatter::formatForUom(
                $totalOutputQuantity,
                $makeOrder->outputItem?->baseUom,
                1
            ),
            'runsDisplay' => QuantityFormatter::format((string) $makeOrder->output_quantity, 0),
        ]);
    }

    /**
     * Return a paginated material-scoped make order list for the material detail page.
     */
    public function listForMaterial(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('inventory-make-orders-view');

        $paginator = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('status', '!=', MakeOrder::STATUS_CANCELLED)
            ->with(['recipe', 'outputItem.baseUom'])
            ->whereHas('recipe', function ($query) use ($item): void {
                $query->where('item_id', $item->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        $data = $paginator->getCollection()
            ->map(fn (MakeOrder $makeOrder): array => $this->makeOrderPayload($makeOrder))
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
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
            'runs' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        if (bccomp($validated['runs'], '0.000000', 6) !== 1) {
            return $this->validationError([
                'runs' => ['Runs must be greater than zero.'],
            ], 'Runs must be greater than zero.');
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('item')
            ->findOrFail($validated['recipe_id']);

        if ($recipeTypeError = $this->manufacturingRecipeValidationError($recipe)) {
            return $recipeTypeError;
        }

        if (!$recipe->is_active) {
            return $this->validationError([
                'recipe_id' => ['Recipe must be active to execute.'],
            ], 'Recipe must be active to execute.');
        }

        $makeOrder = MakeOrder::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'recipe_id' => $recipe->id,
            'output_item_id' => $recipe->item_id,
            'output_quantity' => $validated['runs'],
            'status' => MakeOrder::STATUS_DRAFT,
            'created_by_user_id' => $request->user()->id,
        ]);

        $makeOrder->load(['recipe', 'outputItem.baseUom']);

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrder),
        ], 201);
    }

    /**
     * Update an existing editable make order.
     */
    public function update(Request $request, int $makeOrder): JsonResponse
    {
        Gate::authorize('inventory-make-orders-execute');

        $validated = $request->validate([
            'recipe_id' => [
                'required',
                'integer',
                Rule::exists('recipes', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'runs' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (bccomp($validated['runs'], '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'runs' => ['Runs must be greater than zero.'],
            ], 'Runs must be greater than zero.');
        }

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['recipe', 'outputItem.baseUom'])
            ->findOrFail($makeOrder);

        if ($makeOrderModel->status === MakeOrder::STATUS_MADE || $makeOrderModel->status === MakeOrder::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'Only draft or scheduled make orders can be edited.',
            ], 422);
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('item')
            ->findOrFail($validated['recipe_id']);

        if ($recipeTypeError = $this->manufacturingRecipeValidationError($recipe)) {
            return $recipeTypeError;
        }

        if (! $recipe->is_active) {
            return $this->validationError([
                'recipe_id' => ['Recipe must be active to execute.'],
            ], 'Recipe must be active to execute.');
        }

        $dueDate = $validated['due_date'] ?? null;

        $makeOrderModel->recipe_id = $recipe->id;
        $makeOrderModel->output_item_id = $recipe->item_id;
        $makeOrderModel->output_quantity = $validated['runs'];
        $makeOrderModel->due_date = $dueDate ? Carbon::parse($dueDate)->startOfDay() : null;
        $makeOrderModel->status = $dueDate ? MakeOrder::STATUS_SCHEDULED : MakeOrder::STATUS_DRAFT;
        $makeOrderModel->scheduled_at = $dueDate ? ($makeOrderModel->scheduled_at ?? now()) : null;
        $makeOrderModel->save();

        $makeOrderModel->load(['recipe', 'outputItem.baseUom']);

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel),
        ]);
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
            ->with(['recipe', 'outputItem.baseUom'])
            ->findOrFail($makeOrder);

        if ($makeOrderModel->status === MakeOrder::STATUS_MADE) {
            return response()->json([
                'message' => 'Make order is already made.',
            ], 422);
        }

        if ($makeOrderModel->status === MakeOrder::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'Cancelled make orders cannot be scheduled.',
            ], 422);
        }

        if ($recipeTypeError = $this->manufacturingRecipeValidationError($makeOrderModel->recipe)) {
            return $recipeTypeError;
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

                if ($makeOrderModel->status === MakeOrder::STATUS_CANCELLED) {
                    return [
                        'error' => response()->json([
                            'message' => 'Cancelled make orders cannot be made.',
                        ], 422),
                    ];
                }

                $recipe = Recipe::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->with('item')
                    ->findOrFail($makeOrderModel->recipe_id);

                if ($recipeTypeError = $this->manufacturingRecipeValidationError($recipe)) {
                    return [
                        'error' => $recipeTypeError,
                    ];
                }

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

                $makeOrderModel->load(['recipe', 'outputItem.baseUom']);

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
     * Archive an eligible make order by transitioning it to cancelled.
     */
    public function destroy(Request $request, int $makeOrder): JsonResponse
    {
        Gate::authorize('inventory-make-orders-execute');

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['recipe', 'outputItem.baseUom'])
            ->findOrFail($makeOrder);

        if ($makeOrderModel->status === MakeOrder::STATUS_MADE) {
            return response()->json([
                'message' => 'Made make orders cannot be archived.',
            ], 422);
        }

        if ($makeOrderModel->status === MakeOrder::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'Make order is already archived.',
            ], 422);
        }

        $makeOrderModel->status = MakeOrder::STATUS_CANCELLED;
        $makeOrderModel->save();

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel->fresh(['recipe', 'outputItem.baseUom'])),
            'message' => 'Archived.',
        ]);
    }

    /**
     * Build JSON payload for a make order list entry.
     */
    private function makeOrderPayload(MakeOrder $makeOrder): array
    {
        $totalOutputQuantity = $this->totalOutputQuantity($makeOrder);

        return [
            'id' => $makeOrder->id,
            'recipe_id' => $makeOrder->recipe_id,
            'recipe_name' => $makeOrder->recipe?->name ?? '—',
            'output_item_id' => $makeOrder->output_item_id,
            'output_item_name' => $makeOrder->outputItem?->name ?? '—',
            'runs' => (string) $makeOrder->output_quantity,
            'runs_display' => QuantityFormatter::format(
                (string) $makeOrder->output_quantity,
                0
            ),
            'qty' => $totalOutputQuantity,
            'qty_display' => QuantityFormatter::formatForUom(
                $totalOutputQuantity,
                $makeOrder->outputItem?->baseUom,
                1
            ),
            'total_output_quantity' => $totalOutputQuantity,
            'total_output_quantity_display' => QuantityFormatter::formatForUom(
                $totalOutputQuantity,
                $makeOrder->outputItem?->baseUom,
                1
            ),
            'status' => $makeOrder->status,
            'due_date' => $makeOrder->due_date?->format('Y-m-d'),
            'scheduled_at' => $makeOrder->scheduled_at?->format('Y-m-d H:i'),
            'made_at' => $makeOrder->made_at?->format('Y-m-d H:i'),
            'created_by_user_id' => $makeOrder->created_by_user_id,
            'made_by_user_id' => $makeOrder->made_by_user_id,
            'show_url' => route('manufacturing.make-orders.show', $makeOrder),
        ];
    }

    /**
     * Build JSON payload for a recipe selector entry.
     */
    private function recipePayload(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'recipe_type' => $recipe->recipe_type,
            'recipe_type_label' => $recipe->recipeTypeLabel(),
            'name' => $recipe->name,
            'item_id' => $recipe->item_id,
            'item_name' => $recipe->item?->name ?? '—',
            'output_quantity' => (string) $recipe->output_quantity,
            'output_quantity_display' => QuantityFormatter::formatForUom(
                (string) $recipe->output_quantity,
                $recipe->item?->baseUom,
                1
            ),
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
     * Validate that a recipe can be used for make orders.
     */
    private function manufacturingRecipeValidationError(?Recipe $recipe): ?JsonResponse
    {
        if (!$recipe) {
            return $this->validationError([
                'recipe_id' => ['Recipe does not exist.'],
            ], 'Recipe does not exist.');
        }

        if (!$recipe->isManufacturingType()) {
            return $this->validationError([
                'recipe_id' => ['Only manufacturing recipes can be used for make orders.'],
            ], 'Only manufacturing recipes can be used for make orders.');
        }

        return null;
    }

    /**
     * Calculate total produced quantity as runs multiplied by recipe output quantity.
     */
    private function totalOutputQuantity(MakeOrder $makeOrder): string
    {
        $recipeOutputQuantity = (string) ($makeOrder->recipe?->output_quantity ?? '0.000000');

        return bcmul((string) $makeOrder->output_quantity, $recipeOutputQuantity, 6);
    }

    /**
     * Return the shared CRUD config for the make orders page module.
     *
     * @return array<string, mixed>
     */
    private function crudConfig(bool $canExecute): array
    {
        $actions = [
            [
                'id' => 'view',
                'label' => 'View',
                'tone' => 'default',
            ],
        ];

        if ($canExecute) {
            $actions[] = [
                'id' => 'edit',
                'label' => 'Edit',
                'tone' => 'default',
            ];
            $actions[] = [
                'id' => 'archive',
                'label' => 'Archive',
                'tone' => 'warning',
            ];
        }

        return [
            'resource' => 'make-orders',
            'endpoints' => [
                'list' => route('manufacturing.make-orders.list'),
                'create' => route('manufacturing.make-orders.store'),
                'update' => url('/manufacturing/make-orders/{id}'),
                'delete' => url('/manufacturing/make-orders/{id}'),
            ],
            'detailUrlTemplate' => url('/manufacturing/make-orders/{id}'),
            'columns' => ['due_date', 'recipe_name', 'runs', 'output_item_name', 'qty', 'status'],
            'headers' => [
                'due_date' => 'Due Date',
                'recipe_name' => 'Recipe Name',
                'runs' => 'Runs',
                'output_item_name' => 'Output Item',
                'qty' => 'Qty',
                'status' => 'Status',
            ],
            'sortable' => ['due_date', 'recipe_name', 'runs', 'output_item_name', 'qty', 'status'],
            'labels' => [
                'searchPlaceholder' => 'Search make orders',
                'createTitle' => 'Create Make Order',
                'createAriaLabel' => 'Create Make Order',
                'emptyState' => 'No make orders found.',
                'actionsAriaLabel' => 'Make order actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => $canExecute,
            ],
            'rowDisplay' => [
                'columns' => [
                    'due_date' => ['kind' => 'text'],
                    'recipe_name' => ['kind' => 'text'],
                    'runs' => ['kind' => 'text'],
                    'output_item_name' => ['kind' => 'text'],
                    'qty' => ['kind' => 'text'],
                    'status' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.recipe_name || '—'",
                'subtitleExpression' => "record.output_item_name || '—'",
                'bodyExpression' => 'makeOrderMobileSummary(record)',
            ],
            'actions' => $actions,
        ];
    }

    /**
     * Build active list rows for the make orders shared CRUD page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function makeOrdersListRows(int $tenantId, string $search, string $sortColumn, string $direction): array
    {
        $rows = MakeOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', MakeOrder::STATUS_CANCELLED)
            ->with(['recipe', 'outputItem.baseUom'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MakeOrder $makeOrder): array => $this->makeOrderPayload($makeOrder))
            ->values()
            ->all();

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                $haystacks = [
                    (string) ($row['due_date'] ?? ''),
                    (string) ($row['recipe_name'] ?? ''),
                    (string) ($row['runs'] ?? ''),
                    (string) ($row['output_item_name'] ?? ''),
                    (string) ($row['qty'] ?? ''),
                    (string) ($row['status'] ?? ''),
                ];

                foreach ($haystacks as $haystack) {
                    if (str_contains(mb_strtolower($haystack), $needle)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        usort($rows, function (array $left, array $right) use ($sortColumn, $direction): int {
            $comparison = $this->compareListRows($left, $right, $sortColumn);

            return $direction === 'desc' ? $comparison * -1 : $comparison;
        });

        return $rows;
    }

    /**
     * Compare two make order list rows for sort operations.
     */
    private function compareListRows(array $left, array $right, string $sortColumn): int
    {
        if (in_array($sortColumn, ['runs', 'qty'], true)) {
            return bccomp(
                (string) ($left[$sortColumn] ?? '0.000000'),
                (string) ($right[$sortColumn] ?? '0.000000'),
                self::SCALE
            );
        }

        return strcmp(
            mb_strtolower((string) ($left[$sortColumn] ?? '')),
            mb_strtolower((string) ($right[$sortColumn] ?? ''))
        );
    }

    /**
     * Resolve an active manufacturing recipe prefill id for the create form.
     */
    private function prefillRecipeId(Request $request): ?int
    {
        $recipeId = $request->query('recipe_id');

        if (! is_numeric($recipeId)) {
            return null;
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('is_active', true)
            ->where('recipe_type', Recipe::TYPE_MANUFACTURING)
            ->find((int) $recipeId);

        return $recipe?->id;
    }

    /**
     * Map execution domain exceptions to validation-style responses.
     */
    private function handleExecuteException(InvalidArgumentException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        $field = 'recipe_id';

        if (str_contains($message, 'Runs') || str_contains($message, 'output quantity')) {
            $field = 'runs';
        }

        return $this->validationError([
            $field => [$message],
        ], $message);
    }
}
