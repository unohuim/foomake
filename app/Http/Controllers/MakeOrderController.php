<?php

namespace App\Http\Controllers;

use App\Actions\Manufacturing\MoveMakeOrderWorkflowStageAction;
use App\Actions\Notes\BuildNotesFeedPayloadAction;
use App\Actions\Workflows\BuildWorkflowProgressStepsAction;
use App\Actions\Workflows\CanViewAssignedWorkflowResourceAction;
use App\Actions\Workflows\ResolveManufacturingWorkflowStageAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\Item;
use App\Models\MakeOrder;
use App\Models\MakeOrderLine;
use App\Models\Note;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionLine;
use App\Models\StockMove;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Support\QuantityFormatter;
use App\Support\Uom\UomConversionPathResolver;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Handle make-order reads, snapshots, and lifecycle actions.
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
            ->with(['item.baseUom', 'currentVersion'])
            ->get()
            ->filter(fn (Recipe $recipe): bool => $this->isEligibleManufacturingRecipe($recipe))
            ->values();

        $canExecute = $this->userCanOperateMakeOrderWorkflow($request->user());
        $crudConfig = $this->crudConfig($canExecute);
        $payload = [
            'recipes' => $recipes->map(fn (Recipe $recipe): array => $this->recipePayload($recipe))->all(),
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
     * Return shared CRUD rows for the make orders page.
     */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('inventory-make-orders-view');

        $crudConfig = $this->crudConfig($this->userCanOperateMakeOrderWorkflow($request->user()));
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'due_date');
        $direction = (string) ($validated['direction'] ?? 'asc');

        $rows = $this->makeOrdersListRows((int) $request->user()->tenant_id, $search, $sortColumn, $direction);

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
     * Display the make order detail page.
     */
    public function show(Request $request, MakeOrder $makeOrder): View
    {
        abort_unless((int) $makeOrder->tenant_id === (int) $request->user()->tenant_id, 404);
        abort_unless(
            Gate::allows('inventory-make-orders-view')
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $request->user(),
                    $makeOrder,
                    'manufacturing',
                    $makeOrder->made_by_user_id
                ),
            403
        );

        $this->ensureManufacturingWorkflowStagesExist($request);

        $makeOrder->load([
            'recipe.item.baseUom',
            'recipeVersion',
            'outputItem.baseUom',
            'lines.inputItem.baseUom',
            'workflowStage',
            'madeByUser',
            'taskedByUser',
        ]);
        $payload = [
            'makeOrder' => $this->makeOrderDetailPayload($makeOrder),
            'breadcrumbs' => [
                ['label' => 'Make Orders', 'url' => route('manufacturing.make-orders.index'), 'current' => false],
                ['label' => 'Make Order ' . $makeOrder->id, 'url' => null, 'current' => true],
            ],
            'workflowProgressSteps' => app(BuildWorkflowProgressStepsAction::class)->execute(
                (int) $request->user()->tenant_id,
                'manufacturing',
                $makeOrder->status !== MakeOrder::STATUS_MADE && $makeOrder->workflow_stage_id !== null
                    ? (int) $makeOrder->workflow_stage_id
                    : null,
                null,
                $makeOrder->workflow_stage_id === null ? null : (int) $makeOrder->workflow_stage_id,
                $makeOrder->status === MakeOrder::STATUS_MADE
            ),
            'workflow' => $this->makeOrderWorkflowPayload($makeOrder, $request->user()),
            'ingredients' => $this->makeOrderIngredientsPayload($makeOrder),
            'taskCreate' => [
                'users' => $this->manualTaskAssigneeOptions((int) $request->user()->tenant_id),
            ],
            'notesFeed' => app(BuildNotesFeedPayloadAction::class)->execute(
                $makeOrder,
                route('manufacturing.make-orders.notes.index', $makeOrder),
                route('manufacturing.make-orders.notes.store', $makeOrder)
            ),
            'csrf_token' => $request->session()->token(),
        ];

        return view('manufacturing.make-orders.show', [
            'makeOrder' => $makeOrder,
            'payload' => $payload,
        ]);
    }

    /**
     * Material detail helper list.
     */
    public function listForMaterial(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('inventory-make-orders-view');

        $perPage = $this->perPageFromRequest($request);

        $paginator = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('status', '!=', MakeOrder::STATUS_CANCELLED)
            ->with(['recipe', 'recipeVersion', 'outputItem.baseUom', 'workflowStage'])
            ->whereHas('recipe', function ($query) use ($item): void {
                $query->where('item_id', $item->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

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
     * Recipe detail helper list.
     */
    public function listForRecipe(Request $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-view');
        Gate::authorize('inventory-recipes-view');
        abort_unless((int) $recipe->tenant_id === (int) $request->user()->tenant_id, 404);

        $perPage = $this->perPageFromRequest($request);

        $paginator = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->where('status', '!=', MakeOrder::STATUS_CANCELLED)
            ->with(['recipe', 'recipeVersion', 'outputItem.baseUom', 'workflowStage'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

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
     * Store a new draft make order plus snapshotted lines.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $validated = $request->validate([
            'recipe_id' => [
                'required',
                'integer',
                Rule::exists('recipes', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'recipe_version_id' => [
                'nullable',
                'integer',
                Rule::exists('recipe_versions', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'runs' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        if (bccomp($validated['runs'], '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'runs' => ['Runs must be greater than zero.'],
            ], 'Runs must be greater than zero.');
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['item', 'item.baseUom', 'currentVersion.lines'])
            ->findOrFail($validated['recipe_id']);

        $version = $this->selectedCurrentPublishedVersion($recipe, $validated['recipe_version_id'] ?? null);

        if ($version instanceof JsonResponse) {
            return $version;
        }

        $makeOrder = DB::transaction(function () use ($request, $recipe, $version, $validated): MakeOrder {
            $runs = $this->canonicalQuantity($validated['runs']);

            $makeOrder = MakeOrder::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'recipe_id' => $recipe->id,
                'recipe_version_id' => $version->id,
                'output_item_id' => $recipe->item_id,
                'runs' => $runs,
                'expected_output_qty' => $this->expectedOutputQtyForVersion($runs, $version),
                'actual_output_qty' => null,
                'status' => MakeOrder::STATUS_DRAFT,
                'created_by_user_id' => $request->user()->id,
                'made_by_user_id' => $request->user()->id,
            ]);

            $this->snapshotVersionLines($makeOrder, $version, $runs);
            $this->createInitialNoteFromText($makeOrder, $request, $validated['notes'] ?? null);

            return $makeOrder->fresh(['recipe', 'recipeVersion', 'outputItem.baseUom', 'lines.inputItem.baseUom', 'workflowStage']);
        });

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrder),
        ], 201);
    }

    /**
     * Create a make order directly from a recipe entry point.
     */
    public function storeForRecipe(Request $request, Recipe $recipe): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);
        abort_unless((int) $recipe->tenant_id === (int) $request->user()->tenant_id, 404);

        $validated = $request->validate([
            'runs' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        $runs = (string) ($validated['runs'] ?? '1.000000');

        if (bccomp($runs, '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'runs' => ['Runs must be greater than zero.'],
            ], 'Runs must be greater than zero.');
        }

        $recipe->loadMissing(['item.baseUom', 'currentVersion.lines']);

        $version = $this->selectedCurrentPublishedVersion($recipe, null);

        if ($version instanceof JsonResponse) {
            return $version;
        }

        $makeOrder = DB::transaction(function () use ($request, $recipe, $version, $runs, $validated): MakeOrder {
            $canonicalRuns = $this->canonicalQuantity($runs);

            $makeOrder = MakeOrder::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'recipe_id' => $recipe->id,
                'recipe_version_id' => $version->id,
                'output_item_id' => $recipe->item_id,
                'runs' => $canonicalRuns,
                'expected_output_qty' => $this->expectedOutputQtyForVersion($canonicalRuns, $version),
                'actual_output_qty' => null,
                'status' => MakeOrder::STATUS_DRAFT,
                'created_by_user_id' => $request->user()->id,
                'made_by_user_id' => $request->user()->id,
            ]);

            $this->snapshotVersionLines($makeOrder, $version, $canonicalRuns);
            $this->createInitialNoteFromText($makeOrder, $request, $validated['notes'] ?? null);

            return $makeOrder->fresh(['recipe', 'recipeVersion', 'outputItem.baseUom', 'lines.inputItem.baseUom', 'workflowStage']);
        });

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrder),
        ], 201);
    }

    /**
     * Create the initial Notes feed entry from non-blank create-form notes.
     */
    private function createInitialNoteFromText(MakeOrder $makeOrder, Request $request, ?string $body): void
    {
        $body = trim((string) $body);

        if ($body === '') {
            return;
        }

        Note::query()->forceCreate([
            'tenant_id' => (int) $makeOrder->tenant_id,
            'noteable_type' => MakeOrder::class,
            'noteable_id' => (int) $makeOrder->id,
            'author_user_id' => (int) $request->user()->id,
            'body' => $body,
            'visibility' => 'internal',
            'is_pinned' => false,
        ]);
    }

    /**
     * Update an editable make order and refresh its snapshot lines when needed.
     */
    public function update(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $validated = $request->validate([
            'recipe_id' => [
                'required',
                'integer',
                Rule::exists('recipes', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'recipe_version_id' => [
                'nullable',
                'integer',
                Rule::exists('recipe_versions', 'id')->where('tenant_id', $request->user()->tenant_id),
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
            ->with(['recipe', 'recipeVersion', 'outputItem.baseUom', 'lines'])
            ->findOrFail($makeOrder);

        if (in_array($makeOrderModel->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'Only draft or scheduled make orders can be edited.',
            ], 422);
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['item', 'currentVersion.lines'])
            ->findOrFail($validated['recipe_id']);

        $version = $this->selectedCurrentPublishedVersion($recipe, $validated['recipe_version_id'] ?? null);

        if ($version instanceof JsonResponse) {
            return $version;
        }

        DB::transaction(function () use ($makeOrderModel, $recipe, $version, $validated): void {
            $dueDate = $validated['due_date'] ?? null;
            $runs = $this->canonicalQuantity($validated['runs']);

            $makeOrderModel->recipe_id = $recipe->id;
            $makeOrderModel->recipe_version_id = $version->id;
            $makeOrderModel->output_item_id = $recipe->item_id;
            $makeOrderModel->runs = $runs;
            $makeOrderModel->expected_output_qty = $this->recalculateExpectedOutputQty($version, $runs);
            $makeOrderModel->due_date = $dueDate ? Carbon::parse($dueDate)->startOfDay() : null;
            if ($makeOrderModel->workflow_stage_id === null) {
                $makeOrderModel->status = MakeOrder::STATUS_DRAFT;
            }
            $makeOrderModel->save();

            $makeOrderModel->lines()->delete();
            $this->snapshotVersionLines($makeOrderModel, $version, $runs);
        });

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel->fresh([
                'recipe',
                'recipeVersion',
                'outputItem.baseUom',
                'lines.inputItem.baseUom',
                'workflowStage',
            ])),
        ]);
    }

    /**
     * Schedule a draft make order.
     */
    public function schedule(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $validated = $request->validate([
            'due_date' => ['required', 'date'],
        ]);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['recipe', 'recipeVersion', 'outputItem.baseUom'])
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

        if (! $makeOrderModel->recipe?->is_active) {
            return $this->validationError([
                'recipe_id' => ['Recipe must be active to execute.'],
            ], 'Recipe must be active to execute.');
        }

        if (! $makeOrderModel->recipeVersion || ! $makeOrderModel->recipeVersion->isPublished()) {
            return $this->validationError([
                'recipe_version_id' => ['Recipe version must be published to execute.'],
            ], 'Recipe version must be published to execute.');
        }

        if ($makeOrderModel->recipeVersion->recipe_type !== Recipe::TYPE_MANUFACTURING) {
            return $this->validationError([
                'recipe_version_id' => ['Only manufacturing recipe versions can be used for make orders.'],
            ], 'Only manufacturing recipe versions can be used for make orders.');
        }

        $makeOrderModel->due_date = Carbon::parse($validated['due_date'])->startOfDay();
        $makeOrderModel->save();

        if ($makeOrderModel->workflow_stage_id === null) {
            try {
                $firstStage = app(ResolveManufacturingWorkflowStageAction::class)->firstActiveStage($makeOrderModel);

                if (! $firstStage) {
                    throw new DomainException('No active manufacturing workflow stage is configured.');
                }

                $makeOrderModel = app(MoveMakeOrderWorkflowStageAction::class)->execute(
                    $makeOrderModel,
                    (int) $firstStage->id,
                    (int) $request->user()->id
                );
            } catch (DomainException $exception) {
                return $this->validationError([
                    'workflow_stage_id' => [$exception->getMessage()],
                ], $exception->getMessage());
            }
        }

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel->fresh(['recipe', 'recipeVersion', 'outputItem.baseUom', 'workflowStage'])),
        ]);
    }

    /**
     * Move a Make Order to another configured manufacturing workflow stage.
     */
    public function updateWorkflowStage(
        Request $request,
        int $makeOrder,
        MoveMakeOrderWorkflowStageAction $moveWorkflowStageAction
    ): JsonResponse {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $validated = $request->validate([
            'workflow_stage_id' => [
                'required',
                'integer',
                Rule::exists('workflow_stages', 'id'),
            ],
        ]);

        $this->ensureManufacturingWorkflowStagesExist($request);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with([
                'recipe',
                'recipeVersion',
                'outputItem.baseUom',
                'lines.inputItem.baseUom',
                'workflowStage',
                'madeByUser',
                'taskedByUser',
            ])
            ->findOrFail($makeOrder);

        try {
            $makeOrderModel = $moveWorkflowStageAction->execute(
                $makeOrderModel,
                (int) $validated['workflow_stage_id'],
                (int) $request->user()->id
            );
        } catch (DomainException $exception) {
            return $this->validationError([
                'workflow_stage_id' => [$exception->getMessage()],
            ], $exception->getMessage());
        }

        $makeOrderModel->loadMissing([
            'recipe.item.baseUom',
            'recipeVersion',
            'outputItem.baseUom',
            'lines.inputItem.baseUom',
            'workflowStage',
            'madeByUser',
            'taskedByUser',
        ]);

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel),
            'workflow' => $this->makeOrderWorkflowPayload($makeOrderModel, $request->user()),
            'workflowProgressSteps' => $this->makeOrderWorkflowProgressSteps($makeOrderModel, $request->user()),
            'ingredients' => $this->makeOrderIngredientsPayload($makeOrderModel),
        ]);
    }

    /**
     * Update the workflow owner metadata for one Make Order.
     */
    public function updateAssignment(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with([
                'recipe',
                'recipeVersion',
                'outputItem.baseUom',
                'lines.inputItem.baseUom',
                'workflowStage',
                'madeByUser',
                'taskedByUser',
            ])
            ->findOrFail($makeOrder);

        $validated = $request->validate([
            'made_by_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        if (
            isset($validated['made_by_user_id'])
            && ! $this->userCanBeAssignedToWorkflow((int) $validated['made_by_user_id'], 'manufacturing')
        ) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'made_by_user_id' => ['The selected user cannot be assigned to Make Orders.'],
                ],
            ], 422);
        }

        $makeOrderModel->made_by_user_id = isset($validated['made_by_user_id'])
            ? (int) $validated['made_by_user_id']
            : null;
        $makeOrderModel->save();

        $makeOrderModel = $makeOrderModel->fresh([
            'recipe.item.baseUom',
            'recipeVersion',
            'outputItem.baseUom',
            'lines.inputItem.baseUom',
            'workflowStage',
            'madeByUser',
            'taskedByUser',
        ]);

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel),
            'workflow' => $this->makeOrderWorkflowPayload($makeOrderModel, $request->user()),
        ]);
    }

    /**
     * Update the due date metadata for one Make Order.
     */
    public function updateDueDate(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with([
                'recipe',
                'recipeVersion',
                'outputItem.baseUom',
                'lines.inputItem.baseUom',
                'workflowStage',
                'madeByUser',
                'taskedByUser',
            ])
            ->findOrFail($makeOrder);

        if (in_array($makeOrderModel->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'Only draft or scheduled make orders can be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'due_date' => ['nullable', 'date'],
        ]);

        $makeOrderModel->due_date = isset($validated['due_date']) && $validated['due_date'] !== null
            ? Carbon::parse($validated['due_date'])->startOfDay()
            : null;
        $makeOrderModel->save();

        $makeOrderModel = $makeOrderModel->fresh([
            'recipe.item.baseUom',
            'recipeVersion',
            'outputItem.baseUom',
            'lines.inputItem.baseUom',
            'workflowStage',
            'madeByUser',
            'taskedByUser',
        ]);

        return response()->json([
            'data' => $this->makeOrderPayload($makeOrderModel),
            'workflow' => $this->makeOrderWorkflowPayload($makeOrderModel, $request->user()),
        ]);
    }

    /**
     * Execute a make order from its snapshotted lines.
     */
    public function make(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $validated = $request->validate([
            'actual_output_qty' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'actual_output_quantity' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        $result = DB::transaction(function () use ($request, $makeOrder, $validated): array|JsonResponse {
            $makeOrderModel = MakeOrder::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->with(['recipe', 'recipeVersion', 'outputItem', 'lines.inputItem.baseUom', 'lines.uom'])
                ->lockForUpdate()
                ->findOrFail($makeOrder);

            if ($makeOrderModel->status === MakeOrder::STATUS_MADE) {
                return response()->json([
                    'message' => 'Make order is already made.',
                ], 422);
            }

            if ($makeOrderModel->status === MakeOrder::STATUS_CANCELLED) {
                return response()->json([
                    'message' => 'Cancelled make orders cannot be made.',
                ], 422);
            }

            $version = $makeOrderModel->recipeVersion;

            if (! $makeOrderModel->recipe?->is_active) {
                return $this->validationError([
                    'recipe_id' => ['Recipe must be active to execute.'],
                ], 'Recipe must be active to execute.');
            }

            if (! $version || ! $version->isPublished()) {
                return $this->validationError([
                    'recipe_version_id' => ['Recipe version must be published to execute.'],
                ], 'Recipe version must be published to execute.');
            }

            if ($version->recipe_type !== Recipe::TYPE_MANUFACTURING) {
                return $this->validationError([
                    'recipe_version_id' => ['Only manufacturing recipe versions can be used for make orders.'],
                ], 'Only manufacturing recipe versions can be used for make orders.');
            }

            if (bccomp((string) $version->output_quantity, '0.000000', self::SCALE) !== 1) {
                return $this->validationError([
                    'recipe_version_id' => ['Recipe version output quantity must be greater than zero.'],
                ], 'Recipe version output quantity must be greater than zero.');
            }

            if (bccomp($this->makeOrderRuns($makeOrderModel), '0.000000', self::SCALE) !== 1) {
                return $this->validationError([
                    'runs' => ['Runs must be greater than zero.'],
                ], 'Runs must be greater than zero.');
            }

            $actualOutputInput = $validated['actual_output_qty'] ?? $validated['actual_output_quantity'] ?? null;
            $actualOutputQty = $actualOutputInput !== null
                ? $this->canonicalQuantity((string) $actualOutputInput)
                : null;

            foreach ($makeOrderModel->lines as $line) {
                $inputItem = $line->inputItem;

                if (! $inputItem) {
                    return $this->validationError([
                        'recipe_version_id' => ['Make order line input item is missing.'],
                    ], 'Make order line input item is missing.');
                }

                if ($inputItem->is_stockable) {
                    $issueQuantity = $this->issueQuantityInBaseUom($makeOrderModel->tenant_id, $line, $inputItem);

                    if ($issueQuantity instanceof JsonResponse) {
                        return $issueQuantity;
                    }

                    StockMove::query()->create([
                        'tenant_id' => $makeOrderModel->tenant_id,
                        'item_id' => $inputItem->id,
                        'uom_id' => $inputItem->base_uom_id,
                        'quantity' => bcsub('0.000000', $issueQuantity, self::SCALE),
                        'type' => 'issue',
                        'source_id' => $makeOrderModel->id,
                        'source_type' => MakeOrder::class,
                        'status' => 'POSTED',
                    ]);
                }
            }

            $completedOutputQuantity = $actualOutputQty ?? $this->expectedOutputQty($makeOrderModel);

            if ($makeOrderModel->outputItem?->is_stockable) {
                StockMove::query()->create([
                    'tenant_id' => $makeOrderModel->tenant_id,
                    'item_id' => $makeOrderModel->output_item_id,
                    'uom_id' => $makeOrderModel->outputItem?->base_uom_id,
                    'quantity' => $completedOutputQuantity,
                    'type' => 'receipt',
                    'source_id' => $makeOrderModel->id,
                    'source_type' => MakeOrder::class,
                    'status' => 'POSTED',
                ]);
            }

            $nextWorkflowStage = app(ResolveManufacturingWorkflowStageAction::class)->nextActiveStage($makeOrderModel);

            if ($nextWorkflowStage !== null) {
                $makeOrderModel->workflow_stage_id = $nextWorkflowStage->id;
            }

            $makeOrderModel->status = MakeOrder::STATUS_MADE;
            $makeOrderModel->actual_output_qty = $actualOutputQty;
            $makeOrderModel->made_at = now();
            $makeOrderModel->save();

            return [
                    'data' => $this->makeOrderPayload($makeOrderModel->fresh([
                        'recipe',
                        'recipeVersion',
                        'outputItem.baseUom',
                        'lines.inputItem.baseUom',
                        'workflowStage',
                    ])),
                ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $freshMakeOrder = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['workflowStage', 'madeByUser', 'taskedByUser', 'lines.inputItem.baseUom'])
            ->findOrFail($makeOrder);

        $result['workflow'] = $this->makeOrderWorkflowPayload($freshMakeOrder, $request->user());
        $result['workflowProgressSteps'] = $this->makeOrderWorkflowProgressSteps($freshMakeOrder, $request->user());
        $result['ingredients'] = $this->makeOrderIngredientsPayload($freshMakeOrder);

        return response()->json($result);
    }

    /**
     * Autosave Make Order detail quantity fields from the detail page.
     */
    public function updateDetailsQuantities(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with([
                'recipe.item.baseUom',
                'recipeVersion',
                'outputItem.baseUom',
                'lines.inputItem.baseUom',
                'workflowStage',
                'madeByUser',
                'taskedByUser',
            ])
            ->findOrFail($makeOrder);

        if ($makeOrderModel->status === MakeOrder::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'Cancelled make orders cannot be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(['runs', 'actual_output_qty'])],
            'runs' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'actual_output_qty' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        $field = (string) $validated['field'];

        try {
            DB::transaction(function () use ($makeOrderModel, $validated, $field): void {
                if ($field === 'runs' && in_array($makeOrderModel->status, [MakeOrder::STATUS_MADE], true)) {
                    throw new DomainException('Made make orders cannot change runs or expected output.');
                }

                if ($field === 'runs') {
                    $runs = $this->canonicalQuantity((string) ($validated['runs'] ?? ''));

                    if (bccomp($runs, '0.000000', self::SCALE) !== 1) {
                        throw new DomainException('Runs must be greater than zero.');
                    }

                    $makeOrderModel->runs = $runs;
                    $makeOrderModel->expected_output_qty = $this->recalculateExpectedOutputQty(
                        $makeOrderModel->recipeVersion,
                        $runs
                    );
                    $makeOrderModel->save();
                    $makeOrderModel->lines()->delete();
                    if ($makeOrderModel->recipeVersion) {
                        $this->snapshotVersionLines($makeOrderModel, $makeOrderModel->recipeVersion, $runs);
                    }

                    return;
                }

                $actualOutputQty = $validated['actual_output_qty'] ?? null;
                $makeOrderModel->actual_output_qty = $actualOutputQty !== null && $actualOutputQty !== ''
                    ? $this->canonicalQuantity((string) $actualOutputQty)
                    : null;
                $makeOrderModel->save();

                if ($makeOrderModel->status === MakeOrder::STATUS_MADE && $makeOrderModel->outputItem?->is_stockable) {
                    $receiptQuantity = $makeOrderModel->actual_output_qty ?? $this->expectedOutputQty($makeOrderModel);

                    StockMove::query()
                        ->where('tenant_id', $makeOrderModel->tenant_id)
                        ->where('source_id', $makeOrderModel->id)
                        ->where('source_type', MakeOrder::class)
                        ->where('item_id', $makeOrderModel->output_item_id)
                        ->where('type', 'receipt')
                        ->update([
                            'quantity' => $receiptQuantity,
                        ]);
                }
            });
        } catch (DomainException $exception) {
            return $this->validationError([
                $field => [$exception->getMessage()],
            ], $exception->getMessage());
        }

        $makeOrderModel = $makeOrderModel->fresh([
            'recipe.item.baseUom',
            'recipeVersion',
            'outputItem.baseUom',
            'lines.inputItem.baseUom',
            'workflowStage',
            'madeByUser',
            'taskedByUser',
        ]);

        return response()->json([
            'data' => $this->makeOrderDetailPayload($makeOrderModel),
            'workflow' => $this->makeOrderWorkflowPayload($makeOrderModel, $request->user()),
            'workflowProgressSteps' => $this->makeOrderWorkflowProgressSteps($makeOrderModel, $request->user()),
        ]);
    }

    /**
     * Archive an eligible make order.
     */
    public function destroy(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['recipe', 'recipeVersion', 'outputItem.baseUom'])
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
            'removed_id' => $makeOrderModel->id,
            'data' => $this->makeOrderPayload($makeOrderModel->fresh(['recipe', 'recipeVersion', 'outputItem.baseUom', 'workflowStage'])),
            'message' => 'Archived.',
        ]);
    }

    /**
     * Add a manual or substitution line to a make order.
     */
    public function storeLine(Request $request, int $makeOrder): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = $this->editableMakeOrder($request, $makeOrder);

        if ($makeOrderModel instanceof JsonResponse) {
            return $makeOrderModel;
        }

        $validated = $request->validate([
            'item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'input_item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'planned_quantity' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'quantity' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'line_type' => ['nullable', 'string', Rule::in([
                MakeOrderLine::TYPE_MANUAL_ADJUSTMENT,
                MakeOrderLine::TYPE_SUBSTITUTION,
            ])],
        ]);

        $itemId = (int) ($validated['item_id'] ?? $validated['input_item_id'] ?? 0);
        $plannedQuantity = (string) ($validated['quantity'] ?? $validated['planned_quantity'] ?? '');

        if ($itemId <= 0) {
            return $this->validationError([
                'item_id' => ['Item is required.'],
            ], 'Item is required.');
        }

        if ($plannedQuantity === '') {
            return $this->validationError([
                'quantity' => ['Quantity is required.'],
            ], 'Quantity is required.');
        }

        if (bccomp($plannedQuantity, '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ], 'Quantity must be greater than zero.');
        }

        $item = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($itemId);

        $line = $makeOrderModel->lines()->create([
            'tenant_id' => $makeOrderModel->tenant_id,
            'source_recipe_version_line_id' => null,
            'input_item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'planned_quantity' => $plannedQuantity,
            'actual_quantity' => null,
            'line_type' => (string) ($validated['line_type'] ?? MakeOrderLine::TYPE_MANUAL_ADJUSTMENT),
        ]);

        return response()->json([
            'data' => $this->makeOrderLinePayload($line->fresh(['inputItem.baseUom'])),
        ], 201);
    }

    /**
     * Update an editable make-order line.
     */
    public function updateLine(Request $request, int $makeOrder, int $line): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = $this->editableMakeOrder($request, $makeOrder);

        if ($makeOrderModel instanceof JsonResponse) {
            return $makeOrderModel;
        }

        $lineModel = $makeOrderModel->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $validated = $request->validate([
            'planned_quantity' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'quantity' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        $plannedQuantity = (string) ($validated['quantity'] ?? $validated['planned_quantity'] ?? '');

        if ($plannedQuantity === '') {
            return $this->validationError([
                'quantity' => ['Quantity is required.'],
            ], 'Quantity is required.');
        }

        if (bccomp($plannedQuantity, '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ], 'Quantity must be greater than zero.');
        }

        $lineModel->planned_quantity = $plannedQuantity;
        $lineModel->save();

        return response()->json([
            'data' => $this->makeOrderLinePayload($lineModel->fresh(['inputItem.baseUom'])),
        ]);
    }

    /**
     * Remove an editable make-order line.
     */
    public function destroyLine(Request $request, int $makeOrder, int $line): JsonResponse
    {
        abort_unless($this->userCanOperateMakeOrderWorkflow($request->user()), 403);

        $makeOrderModel = $this->editableMakeOrder($request, $makeOrder);

        if ($makeOrderModel instanceof JsonResponse) {
            return $makeOrderModel;
        }

        $lineModel = $makeOrderModel->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $deletedLineId = (int) $lineModel->id;
        $lineModel->delete();

        $makeOrderModel->load(['lines.inputItem.baseUom']);

        return response()->json([
            'deleted_line_id' => $deletedLineId,
            'lines' => $makeOrderModel->lines
                ->sortBy('id')
                ->values()
                ->map(fn (MakeOrderLine $remainingLine): array => $this->makeOrderLinePayload($remainingLine))
                ->all(),
            'message' => 'Removed.',
        ]);
    }

    /**
     * Build one make-order payload row.
     *
     * @return array<string, mixed>
     */
    private function makeOrderPayload(MakeOrder $makeOrder): array
    {
        $expectedOutputQty = $this->expectedOutputQty($makeOrder);
        $runs = $this->makeOrderRuns($makeOrder);
        $runsText = $this->compactQuantityDisplay($runs);
        $recipeVersionOutputQty = $this->recipeVersionOutputQty($makeOrder);
        $totalOutputQuantityDisplay = QuantityFormatter::formatForUom(
            $expectedOutputQty,
            $makeOrder->outputItem?->baseUom,
            1
        );
        $workflowState = $this->makeOrderWorkflowState($makeOrder);
        $showUrl = route('manufacturing.make-orders.show', $makeOrder);

        return [
            'id' => $makeOrder->id,
            'recipe_id' => $makeOrder->recipe_id,
            'recipe_version_id' => $makeOrder->recipe_version_id,
            'recipe_version_number_display' => $makeOrder->recipeVersion?->versionNumberDisplay() ?? '—',
            'recipe_name' => $makeOrder->recipe?->name ?? '—',
            'output_item_id' => $makeOrder->output_item_id,
            'output_item_name' => $makeOrder->outputItem?->name ?? '—',
            'output_quantity' => $runs,
            'runs' => $runs,
            'runs_display' => $runsText,
            'expected_output_qty' => $expectedOutputQty,
            'expected_output_qty_display' => $totalOutputQuantityDisplay,
            'qty' => $expectedOutputQty,
            'qty_display' => $totalOutputQuantityDisplay,
            'total_output_quantity' => $expectedOutputQty,
            'total_output_quantity_display' => $totalOutputQuantityDisplay,
            'actual_output_qty' => $makeOrder->actual_output_qty !== null
                ? bcadd((string) $makeOrder->actual_output_qty, '0', self::SCALE)
                : null,
            'actual_output_quantity' => $makeOrder->actual_output_qty !== null
                ? bcadd((string) $makeOrder->actual_output_qty, '0', self::SCALE)
                : null,
            'status' => $makeOrder->status,
            'workflow_state' => $workflowState,
            'workflow_stage_id' => $makeOrder->workflow_stage_id,
            'workflow_stage_name' => $makeOrder->workflowStage?->name,
            'made_by_user_id' => $makeOrder->made_by_user_id,
            'due_date' => $makeOrder->due_date?->format('Y-m-d'),
            'scheduled_at' => $makeOrder->scheduled_at?->format('Y-m-d H:i'),
            'made_at' => $makeOrder->made_at?->format('Y-m-d H:i'),
            'show_url' => $showUrl,
            'recipe_version_output_qty' => $recipeVersionOutputQty,
            'output_uom_display_precision' => (int) ($makeOrder->outputItem?->baseUom?->display_precision ?? 6),
            'display' => [
                'recipeNameText' => $makeOrder->recipe?->name ?? 'Unnamed recipe',
                'runsText' => $runsText,
                'dueDateText' => $makeOrder->due_date?->format('Y-m-d') ?? 'No due date',
                'totalOutputQuantityText' => $totalOutputQuantityDisplay,
                'statusText' => $workflowState,
                'statusTone' => $workflowState === 'DRAFT' ? 'muted' : 'default',
                'versionBadgeText' => 'v' . ($makeOrder->recipeVersion?->versionNumberDisplay() ?? '—'),
                'versionBadgeTone' => 'subtle',
                'showUrl' => $showUrl,
            ],
        ];
    }

    /**
     * Build the make-order detail payload used by the page header.
     *
     * @return array<string, mixed>
     */
    private function makeOrderDetailPayload(MakeOrder $makeOrder): array
    {
        $expectedOutputQty = $this->expectedOutputQty($makeOrder);
        $actualOutputQtyText = $makeOrder->actual_output_qty !== null
            ? QuantityFormatter::formatForUom(
                bcadd((string) $makeOrder->actual_output_qty, '0', self::SCALE),
                $makeOrder->outputItem?->baseUom,
                1
            )
            : '';
        $expectedOutputQtyText = QuantityFormatter::formatForUom(
            $expectedOutputQty,
            $makeOrder->outputItem?->baseUom,
            1
        );

        return array_merge($this->makeOrderPayload($makeOrder), [
            'title' => 'Make Order ' . $makeOrder->id,
            'details_update_url' => route('manufacturing.make-orders.details.update', $makeOrder),
            'can_edit_quantities' => $this->userCanOperateMakeOrderWorkflow(request()->user()),
            'runs_text' => $this->compactQuantityDisplay($this->makeOrderRuns($makeOrder)),
            'expected_output_qty_text' => $expectedOutputQtyText,
            'actual_output_qty_text' => $actualOutputQtyText,
            'expected_output_quantity_text' => $expectedOutputQtyText,
            'actual_output_quantity_text' => $actualOutputQtyText,
            'produced_quantity_text' => $expectedOutputQtyText,
        ]);
    }

    /**
     * Build a selection payload for make-order creation.
     *
     * @return array<string, mixed>
     */
    private function recipePayload(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'recipe_type' => $recipe->currentVersion?->recipe_type ?? $recipe->recipe_type,
            'recipe_type_label' => Recipe::labelForRecipeType($recipe->currentVersion?->recipe_type ?? $recipe->recipe_type),
            'name' => $recipe->name,
            'item_id' => $recipe->item_id,
            'item_name' => $recipe->item?->name ?? '—',
            'output_quantity' => $recipe->currentOutputQuantity(),
            'output_quantity_display' => QuantityFormatter::formatForUom(
                $recipe->currentOutputQuantity(),
                $recipe->item?->baseUom,
                1
            ),
            'recipe_version_id' => $recipe->current_version_id,
        ];
    }

    /**
     * Build a payload for one make-order line mutation response.
     *
     * @return array<string, mixed>
     */
    private function makeOrderLinePayload(MakeOrderLine $line): array
    {
        $displayPrecision = (int) ($line->inputItem?->baseUom?->display_precision ?? 6);
        $onHand = $line->inputItem?->onHandQuantity() ?? '0.000000';

        return [
            'id' => $line->id,
            'item_id' => $line->input_item_id,
            'input_item_id' => $line->input_item_id,
            'item_name' => $line->inputItem?->name ?? '—',
            'input_item_name' => $line->inputItem?->name ?? '—',
            'uom' => $line->inputItem?->baseUom?->symbol ?? '—',
            'uom_name' => $line->inputItem?->baseUom?->name ?? '—',
            'uom_symbol' => $line->inputItem?->baseUom?->symbol ?? '—',
            'quantity' => (string) $line->planned_quantity,
            'quantity_input' => QuantityFormatter::formatForUom(
                (string) $line->planned_quantity,
                $line->inputItem?->baseUom,
                $displayPrecision
            ),
            'quantity_display' => QuantityFormatter::formatForUom(
                (string) $line->planned_quantity,
                $line->inputItem?->baseUom,
                $displayPrecision
            ),
            'on_hand' => $onHand,
            'on_hand_display' => QuantityFormatter::formatForUom(
                $onHand,
                $line->inputItem?->baseUom,
                $displayPrecision
            ),
            'line_type' => $line->line_type,
            'view_url' => route('materials.show', $line->inputItem),
            'remove_url' => route('manufacturing.make-orders.lines.destroy', [$line->make_order_id, $line->id]),
            'purchase_url' => $line->inputItem?->is_purchasable ? route('materials.show', $line->inputItem) : null,
            'make_url' => $this->makeOrderIngredientMakeUrl($line->inputItem),
        ];
    }

    /**
     * Build the make-order ingredients section payload.
     *
     * @return array<string, mixed>
     */
    private function makeOrderIngredientsPayload(MakeOrder $makeOrder): array
    {
        $canEdit = $this->userCanOperateMakeOrderWorkflow(request()->user())
            && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true);

        return [
            'item_options' => $this->ingredientItemsPayload((int) $makeOrder->tenant_id, (int) $makeOrder->output_item_id),
            'lines' => $makeOrder->lines
                ->sortBy('id')
                ->values()
                ->map(fn (MakeOrderLine $line): array => $this->makeOrderLinePayload($line))
                ->all(),
            'store_url' => route('manufacturing.make-orders.lines.store', $makeOrder),
            'update_url_template' => route('manufacturing.make-orders.lines.update', [$makeOrder, '__LINE__']),
            'remove_url_template' => route('manufacturing.make-orders.lines.destroy', [$makeOrder, '__LINE__']),
            'can_edit' => $canEdit,
        ];
    }

    /**
     * Build the Make Order workflow section payload.
     *
     * @return array<string, mixed>
     */
    private function makeOrderWorkflowPayload(MakeOrder $makeOrder, User $viewer): array
    {
        $resolver = app(ResolveManufacturingWorkflowStageAction::class);
        $currentStage = $resolver->currentStage($makeOrder);
        $availableStages = collect();

        if ($makeOrder->status !== MakeOrder::STATUS_MADE && $makeOrder->status !== MakeOrder::STATUS_CANCELLED) {
            $availableStages = $resolver->availableTransitions($makeOrder);
        }

        $nextStageAction = null;

        $canOperateWorkflow = $this->userCanOperateMakeOrderWorkflow($viewer);

        if (
            $canOperateWorkflow
            && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true)
        ) {
            if ($currentStage?->is_inventory_effect_stage) {
                $nextStageAction = [
                    'id' => $currentStage->id,
                    'label' => $currentStage->action_verb ?: $currentStage->name,
                    'type' => 'make',
                    'endpoint' => route('manufacturing.make-orders.make', $makeOrder),
                ];
            } else {
                $nextStage = $currentStage
                    ? $resolver->nextActiveStage($makeOrder)
                    : $resolver->firstActiveStage($makeOrder);

                if ($nextStage) {
                    $nextStageAction = [
                        'id' => $nextStage->id,
                        'label' => $nextStage->action_verb ?: $nextStage->name,
                        'type' => 'stage',
                        'endpoint' => route('manufacturing.make-orders.workflow-stage.update', $makeOrder),
                    ];
                }
            }
        }

        return [
            'default_open' => false,
            'transition_url' => route('manufacturing.make-orders.workflow-stage.update', $makeOrder),
            'can_move_stage' => $canOperateWorkflow
                && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true),
            'current_stage' => $currentStage ? [
                'id' => $currentStage->id,
                'workflow_domain_id' => $currentStage->workflow_domain_id,
                'key' => $currentStage->key,
                'name' => $currentStage->name,
                'description' => $currentStage->description,
            ] : null,
            'current_stage_label' => $this->makeOrderWorkflowState($makeOrder),
            'available_stages' => $availableStages
                ->map(fn (WorkflowStage $stage): array => [
                    'id' => $stage->id,
                    'key' => $stage->key,
                    'name' => $stage->name,
                    'description' => $stage->description,
                ])
                ->values()
                ->all(),
            'next_stage_action' => $nextStageAction,
            'due_date' => $makeOrder->due_date?->format('Y-m-d'),
            'due_date_update_url' => route('manufacturing.make-orders.due-date.update', $makeOrder),
            'can_edit_due_date' => $canOperateWorkflow
                && ! in_array($makeOrder->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true),
            'made_by_user_id' => $makeOrder->made_by_user_id,
            'owner_user_name' => $makeOrder->madeByUser?->name,
            'assignee_options' => $this->tenantAssigneeOptionsPayload($makeOrder->tenant_id),
            'assignment_update_url' => route('manufacturing.make-orders.assignment.update', $makeOrder),
            'can_edit_assignment' => $canOperateWorkflow,
            'tasked_by_user_id' => $makeOrder->tasked_by_user_id,
            'tasked_by_user_name' => $makeOrder->taskedByUser?->name,
            'current_stage_tasks' => $this->makeOrderWorkflowTasksPayload($makeOrder, $currentStage, $viewer),
        ];
    }

    /**
     * Build the Make Order workflow progress steps for runtime UI refreshes.
     *
     * @return array<int, array{label: string, status: string, url: null, current: bool}>
     */
    private function makeOrderWorkflowProgressSteps(MakeOrder $makeOrder, User $viewer): array
    {
        return app(BuildWorkflowProgressStepsAction::class)->execute(
            (int) $viewer->tenant_id,
            'manufacturing',
            $makeOrder->status !== MakeOrder::STATUS_MADE && $makeOrder->workflow_stage_id !== null
                ? (int) $makeOrder->workflow_stage_id
                : null,
            null,
            $makeOrder->workflow_stage_id === null ? null : (int) $makeOrder->workflow_stage_id,
            $makeOrder->status === MakeOrder::STATUS_MADE
        );
    }

    /**
     * Build tenant user options for manual task assignment.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function manualTaskAssigneeOptions(int $tenantId): array
    {
        return app(WorkflowAssignmentPermissions::class)
            ->eligibleUsersQuery($tenantId, 'manufacturing')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Validate that the selected version is the recipe current published version and is eligible.
     */
    private function selectedCurrentPublishedVersion(Recipe $recipe, ?int $versionId): RecipeVersion|JsonResponse
    {
        if (! $recipe->is_active) {
            return $this->validationError([
                'recipe_id' => ['Recipe must be active to execute.'],
            ], 'Recipe must be active to execute.');
        }

        $version = $recipe->currentPublishedVersion();

        if (! $version) {
            return $this->validationError([
                'recipe_version_id' => ['Recipe does not have a published current version.'],
            ], 'Recipe does not have a published current version.');
        }

        if ($versionId !== null && (int) $versionId !== (int) $version->id) {
            return $this->validationError([
                'recipe_version_id' => ['Make Orders must use recipes.current_version_id.'],
            ], 'Make Orders must use recipes.current_version_id.');
        }

        if ($version->recipe_type !== Recipe::TYPE_MANUFACTURING) {
            return $this->validationError([
                'recipe_version_id' => ['Only manufacturing recipe versions can be used for make orders.'],
            ], 'Only manufacturing recipe versions can be used for make orders.');
        }

        return $version->loadMissing('lines');
    }

    /**
     * Copy version lines into make-order snapshot lines.
     */
    private function snapshotVersionLines(MakeOrder $makeOrder, RecipeVersion $version, string $runs): void
    {
        $version->loadMissing('lines');

        foreach ($version->lines as $versionLine) {
            $makeOrder->lines()->create([
                'tenant_id' => $makeOrder->tenant_id,
                'source_recipe_version_line_id' => $versionLine->id,
                'input_item_id' => $versionLine->input_item_id,
                'uom_id' => $versionLine->uom_id,
                'planned_quantity' => bcmul((string) $versionLine->quantity, $runs, self::SCALE),
                'actual_quantity' => null,
                'line_type' => MakeOrderLine::TYPE_RECIPE,
            ]);
        }
    }

    /**
     * Calculate produced quantity from the snapshotted version output quantity.
     */
    private function totalOutputQuantity(MakeOrder $makeOrder): string
    {
        return $this->expectedOutputQty($makeOrder);
    }

    /**
     * Normalize a quantity string to canonical scale.
     */
    private function canonicalQuantity(string $quantity): string
    {
        return bcadd($quantity, '0', self::SCALE);
    }

    /**
     * Resolve canonical runs for one make order.
     */
    private function makeOrderRuns(MakeOrder $makeOrder): string
    {
        return $this->canonicalQuantity((string) ($makeOrder->runs ?? $makeOrder->output_quantity ?? '0.000000'));
    }

    /**
     * Resolve persisted expected output quantity with a safe fallback for older records.
     */
    private function expectedOutputQty(MakeOrder $makeOrder): string
    {
        if ($makeOrder->expected_output_qty !== null) {
            return $this->canonicalQuantity((string) $makeOrder->expected_output_qty);
        }

        $recipeOutputQuantity = (string) ($makeOrder->recipeVersion?->output_quantity ?? $makeOrder->recipe?->output_quantity ?? '0.000000');

        return bcmul($this->makeOrderRuns($makeOrder), $this->canonicalQuantity($recipeOutputQuantity), self::SCALE);
    }

    /**
     * Calculate expected output quantity for one recipe version and runs value.
     */
    private function expectedOutputQtyForVersion(string $runs, ?RecipeVersion $version): string
    {
        $recipeOutputQuantity = (string) ($version?->output_quantity ?? '0.000000');

        return bcmul($runs, $this->canonicalQuantity($recipeOutputQuantity), self::SCALE);
    }

    /**
     * Recalculate expected output from the current recipe-version per-run output quantity.
     */
    private function recalculateExpectedOutputQty(?RecipeVersion $version, string $newRuns): string
    {
        return $this->expectedOutputQtyForVersion($newRuns, $version);
    }

    /**
     * Resolve the per-run recipe output quantity for one make order.
     */
    private function recipeVersionOutputQty(MakeOrder $makeOrder): string
    {
        return $this->canonicalQuantity((string) ($makeOrder->recipeVersion?->output_quantity ?? $makeOrder->recipe?->output_quantity ?? '0.000000'));
    }

    /**
     * Render a compact quantity string without unnecessary trailing zero decimals.
     */
    private function compactQuantityDisplay(string $quantity): string
    {
        $canonical = $this->canonicalQuantity($quantity);

        if (! str_contains($canonical, '.')) {
            return $canonical;
        }

        $trimmed = rtrim(rtrim($canonical, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Return the shared CRUD config for the make orders page.
     *
     * @return array<string, mixed>
     */
    private function crudConfig(bool $canExecute): array
    {
        $actions = $canExecute
            ? [['id' => 'archive', 'label' => 'Archive', 'tone' => 'warning']]
            : [];

        return [
            'resource' => 'make-orders',
            'endpoints' => [
                'list' => route('manufacturing.make-orders.list'),
                'create' => route('manufacturing.make-orders.store'),
                'update' => url('/manufacturing/make-orders/{id}'),
                'delete' => url('/manufacturing/make-orders/{id}'),
            ],
            'detailUrlTemplate' => url('/manufacturing/make-orders/{id}'),
            'columns' => ['due_date', 'recipe_name', 'runs', 'output_item_name', 'qty', 'workflow_state'],
            'headers' => [
                'due_date' => 'Due Date',
                'recipe_name' => 'Recipe Name',
                'runs' => 'Runs',
                'output_item_name' => 'Output Item',
                'qty' => 'Qty',
                'workflow_state' => 'Workflow Stage',
            ],
            'sortable' => ['due_date', 'recipe_name', 'runs', 'output_item_name', 'qty', 'workflow_state'],
            'labels' => [
                'searchPlaceholder' => 'Search make orders',
                'createTitle' => 'Create Make Order',
                'createAriaLabel' => 'Create Make Order',
                'emptyState' => 'No make orders found.',
                'actionsAriaLabel' => 'Archive make order',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => $canExecute,
            ],
            'rowActions' => [
                'mode' => 'icon-button',
                'icon' => 'x-mark',
                'ariaLabel' => 'Archive make order',
            ],
            'rowDisplay' => [
                'columns' => [
                    'due_date' => ['kind' => 'text'],
                    'recipe_name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'runs' => ['kind' => 'text'],
                    'output_item_name' => ['kind' => 'text'],
                    'qty' => ['kind' => 'text'],
                    'workflow_state' => ['kind' => 'text'],
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
     * Build active list rows for the shared CRUD page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function makeOrdersListRows(int $tenantId, string $search, string $sortColumn, string $direction): array
    {
        $rows = MakeOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', MakeOrder::STATUS_CANCELLED)
            ->with(['recipe', 'recipeVersion', 'outputItem.baseUom', 'workflowStage'])
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
                    (string) ($row['workflow_state'] ?? ''),
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
     * Compare two make-order rows for list sorting.
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
     * Resolve an active manufacturing recipe prefill id.
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
            ->with('currentVersion')
            ->find((int) $recipeId);

        return $recipe && $this->isEligibleManufacturingRecipe($recipe) ? $recipe->id : null;
    }

    /**
     * Determine whether a recipe can seed make orders.
     */
    private function isEligibleManufacturingRecipe(Recipe $recipe): bool
    {
        return $recipe->currentVersion !== null
            && $recipe->currentVersion->isPublished()
            && $recipe->currentVersion->recipe_type === Recipe::TYPE_MANUFACTURING;
    }

    /**
     * Resolve an editable make order or return a lifecycle error.
     */
    private function editableMakeOrder(Request $request, int $makeOrder): MakeOrder|JsonResponse
    {
        $makeOrderModel = MakeOrder::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('lines')
            ->findOrFail($makeOrder);

        if (in_array($makeOrderModel->status, [MakeOrder::STATUS_MADE, MakeOrder::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'Only draft or scheduled make orders can be edited.',
            ], 422);
        }

        return $makeOrderModel;
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
     * Convert a make order line quantity into the item's current base UoM.
     */
    private function issueQuantityInBaseUom(int $tenantId, MakeOrderLine $line, Item $inputItem): string|JsonResponse
    {
        $baseUom = $inputItem->baseUom;

        if ($baseUom === null) {
            return $this->validationError([
                'recipe_version_id' => ['Make order line input item base UoM is required.'],
            ], 'Make order line input item base UoM is required.');
        }

        $lineUom = $line->uom ?? $baseUom;

        if ((int) $lineUom->id === (int) $baseUom->id) {
            return (string) $line->planned_quantity;
        }

        $quantity = app(UomConversionPathResolver::class)->convertQuantity(
            $tenantId,
            (int) $inputItem->id,
            $lineUom,
            $baseUom,
            (string) $line->planned_quantity,
            UomConversionPathResolver::PRECEDENCE_ITEM_FIRST
        );

        if ($quantity === null) {
            return $this->validationError([
                'recipe_version_id' => ['Make order line quantity cannot be converted to the input item base UoM.'],
            ], 'Make order line quantity cannot be converted to the input item base UoM.');
        }

        return $quantity;
    }

    /**
     * Build ingredient combobox options for one tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ingredientItemsPayload(int $tenantId, int $outputItemId): array
    {
        return Item::query()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $outputItemId)
            ->with(['baseUom:id,name,symbol,display_precision'])
            ->orderBy('name')
            ->get(['id', 'name', 'base_uom_id', 'is_purchasable', 'is_manufacturable'])
            ->map(function (Item $item): array {
                return [
                    'id' => $item->id,
                    'value' => (string) $item->id,
                    'label' => $item->name,
                    'uom_display' => $item->baseUom
                        ? $item->baseUom->name . ' (' . $item->baseUom->symbol . ')'
                        : '—',
                    'search_text' => strtolower($item->name . ' ' . ($item->baseUom?->symbol ?? '')),
                ];
            })
            ->all();
    }

    /**
     * Build tenant-scoped Make Order assignee options for the workflow section.
     *
     * @return array<int, array<string, string>>
     */
    private function tenantAssigneeOptionsPayload(int $tenantId): array
    {
        $assignedUsers = app(WorkflowAssignmentPermissions::class)
            ->ownerEligibleUsersQuery($tenantId, 'manufacturing')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->all();

        return [
            [
                'value' => '',
                'label' => 'Unassigned',
            ],
            ...$assignedUsers,
        ];
    }

    /**
     * Determine whether the selected user has minimum visibility for workflow assignment.
     */
    private function userCanBeAssignedToWorkflow(int $userId, string $domainKey): bool
    {
        $user = User::query()->find($userId);

        return $user !== null
            && app(WorkflowAssignmentPermissions::class)->userCanOwnWorkflowDomain($user, $domainKey);
    }

    /**
     * Determine whether the user can move Make Order workflow and edit operational sections.
     */
    private function userCanOperateMakeOrderWorkflow(?User $user): bool
    {
        return $user !== null
            && app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, 'manufacturing');
    }

    /**
     * Resolve a valid make flow URL for one make-order ingredient item.
     */
    private function makeOrderIngredientMakeUrl(?Item $item): ?string
    {
        if (! $item || ! $item->is_manufacturable) {
            return null;
        }

        $recipe = Recipe::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->with('currentVersion')
            ->get()
            ->first(fn (Recipe $recipe): bool => $this->isEligibleManufacturingRecipe($recipe));

        if (! $recipe) {
            return null;
        }

        return route('manufacturing.make-orders.index', ['recipe_id' => $recipe->id]);
    }

    /**
     * Resolve the visible workflow state label for one Make Order.
     */
    private function makeOrderWorkflowState(MakeOrder $makeOrder): string
    {
        if ($makeOrder->status === MakeOrder::STATUS_MADE) {
            return $makeOrder->workflowStage?->status_complete_label ?? MakeOrder::STATUS_MADE;
        }

        if ($makeOrder->workflow_stage_id === null) {
            return MakeOrder::STATUS_DRAFT;
        }

        return $makeOrder->workflowStage?->name ?? '—';
    }

    /**
     * Build task payload data for the current Make Order workflow stage.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function makeOrderWorkflowTasksPayload(
        MakeOrder $makeOrder,
        ?WorkflowStage $currentStage,
        User $viewer
    ): array {
        if (! $currentStage) {
            return [];
        }

        return Task::query()
            ->with(['assignedTo', 'completedBy'])
            ->where('tenant_id', $makeOrder->tenant_id)
            ->where('workflow_domain_id', $currentStage->workflow_domain_id)
            ->where('domain_record_id', $makeOrder->id)
            ->where(function ($query) use ($currentStage): void {
                $query->where('source', Task::SOURCE_MANUAL)
                    ->orWhere(function ($query) use ($currentStage): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $currentStage->id);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Task $task) use ($viewer): array {
                $canComplete = ! $task->isCompleted()
                    && (int) $task->assigned_to_user_id === (int) $viewer->id
                    && app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain($viewer, 'manufacturing');

                return [
                    'id' => $task->id,
                    'source' => $task->source,
                    'workflow_stage_id' => $task->workflow_stage_id,
                    'workflow_task_template_id' => $task->workflow_task_template_id,
                    'assigned_to_user_id' => $task->assigned_to_user_id,
                    'assigned_to_user_name' => $task->assignedTo?->name,
                    'title' => $task->title,
                    'description' => $task->description,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                    'sort_order' => $task->sort_order,
                    'status' => $task->status,
                    'is_completed' => $task->isCompleted(),
                    'can_complete' => $canComplete,
                    'completed_at' => $task->completed_at?->toISOString(),
                    'completed_by_user_id' => $task->completed_by_user_id,
                    'completed_by_user_name' => $task->completedBy?->name,
                    'complete_url' => route('tasks.complete', $task),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve the page size for list endpoints that support limited mobile payloads.
     */
    private function perPageFromRequest(Request $request, int $default = 5): int
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return (int) ($validated['per_page'] ?? $default);
    }

    /**
     * Seed default manufacturing workflow stages when none are configured for the tenant.
     */
    private function ensureManufacturingWorkflowStagesExist(Request $request): void
    {
        $manufacturingDomainId = WorkflowDomain::query()
            ->where('key', 'manufacturing')
            ->value('id');

        if ($manufacturingDomainId) {
            $hasConfiguredStages = WorkflowStage::withoutGlobalScopes()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('workflow_domain_id', $manufacturingDomainId)
                ->exists();

            if ($hasConfiguredStages) {
                return;
            }
        }

        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($request->user()->tenant);
    }
}
