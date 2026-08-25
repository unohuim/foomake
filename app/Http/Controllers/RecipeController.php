<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionCheckout;
use App\Models\RecipeVersionLine;
use App\Support\Inertia\AuthShellPayloadBuilder;
use App\Support\QuantityFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

/**
 * Handle recipe parent, version, checkout, and ingredient flows.
 */
class RecipeController extends Controller
{
    private const SCALE = 6;

    /**
     * Display the recipes index.
     */
    public function index(Request $request, AuthShellPayloadBuilder $authShellPayloadBuilder): InertiaResponse
    {
        Gate::authorize('inventory-recipes-view');

        $canManage = Gate::allows('inventory-make-orders-manage');
        $canExecute = Gate::allows('inventory-make-orders-execute');

        $crudConfig = $this->crudConfig($canManage, $canExecute);

        return Inertia::render('Manufacturing/Recipes/Index', [
            'shell' => $authShellPayloadBuilder->build($request),
            'crudConfig' => $crudConfig,
            'payload' => [
                'manufacturable_items' => $this->manufacturableItemsPayload((int) $request->user()->tenant_id),
                'store_url' => route('manufacturing.recipes.store'),
                'version_store_url_base' => url('/manufacturing/recipes'),
                'csrf_token' => $request->session()->token(),
                'csrfToken' => $request->session()->token(),
                'can_manage' => $canManage,
                'prefill_create' => $this->prefillCreatePayload($request),
                'initial_rows' => $this->recipeListRows((int) $request->user()->tenant_id, '', 'updated_at', 'desc', $canManage, $canExecute),
            ],
        ]);
    }

    /**
     * Return shared CRUD rows for the recipes index.
     */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('inventory-recipes-view');

        $canManage = Gate::allows('inventory-make-orders-manage');
        $canExecute = Gate::allows('inventory-make-orders-execute');
        $crudConfig = $this->crudConfig($canManage, $canExecute);
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'updated_at');
        $direction = (string) ($validated['direction'] ?? 'desc');

        return response()->json([
            'data' => $this->recipeListRows((int) $request->user()->tenant_id, $search, $sortColumn, $direction, $canManage, $canExecute),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
            ],
        ]);
    }

    /**
     * Return the recipe versions list for the versions accordion.
     */
    public function listVersions(Request $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-recipes-view');

        $user = $request->user();
        $canManage = Gate::allows('inventory-make-orders-manage');
        $canExecute = Gate::allows('inventory-make-orders-execute');
        $recipe->loadMissing('item.baseUom', 'versions');

        $includeArchived = $request->boolean('include_archived');

        $versions = $recipe->versions()
            ->with(['recipe.item.baseUom', 'checkouts' => function ($query): void {
                $query->whereNull('checked_in_at');
            }])
            ->withCount('lines')
            ->get()
            ->filter(fn (RecipeVersion $version): bool => $includeArchived
                || RecipeVersion::normalizeStatus($version->status) !== RecipeVersion::STATUS_ARCHIVED)
            ->sort(fn (RecipeVersion $left, RecipeVersion $right): int => $this->compareVersionRows($recipe, $user->id, $left, $right))
            ->values();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = $this->perPageFromRequest($request);
        $slice = $versions->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator($slice, $versions->count(), $perPage, $page);

        return response()->json([
            'data' => $slice->map(
                fn (RecipeVersion $version): array => $this->recipeVersionListRow(
                    $recipe,
                    $version,
                    $user->id,
                    $canManage,
                    $canExecute
                )
            )->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Display a recipe detail page.
     */
    public function show(Request $request, Recipe $recipe): View
    {
        Gate::authorize('inventory-recipes-view');

        $user = $request->user();
        $canManage = Gate::allows('inventory-make-orders-manage');
        $canExecute = Gate::allows('inventory-make-orders-execute');
        $canViewMakeOrders = Gate::allows('inventory-make-orders-view');
        $recipe->load(['item.baseUom', 'currentVersion.lines.inputItem.baseUom']);

        $displayVersion = $recipe->displayVersionForUser((int) $user->id);
        $displayVersion?->loadMissing('lines.inputItem.baseUom');

        $payload = [
            'recipe' => $this->recipeDetailPayload($recipe, $displayVersion, (int) $user->id, $canManage, $canExecute),
            'manufacturable_items' => $this->manufacturableItemsPayload((int) $user->tenant_id),
            'index_url' => route('manufacturing.recipes.index'),
            'csrf_token' => $request->session()->token(),
            'can_manage' => $canManage,
            'recipe_type_options' => $this->recipeTypeOptions(),
            'sections' => $this->detailSectionsPayload($recipe, $canManage, $canExecute, $canViewMakeOrders),
            'ingredients' => $this->ingredientsPayload($recipe, $displayVersion, (int) $user->id),
        ];

        return view('manufacturing.recipes.show', [
            'recipe' => $recipe,
            'payload' => $payload,
        ]);
    }

    /**
     * Material detail helper list.
     */
    public function listForMaterial(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('inventory-recipes-view');
        $canManage = Gate::allows('inventory-make-orders-manage');
        $canExecute = Gate::allows('inventory-make-orders-execute');

        $perPage = $this->perPageFromRequest($request);

        $paginator = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['item.baseUom', 'currentVersion'])
            ->where('item_id', $item->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Recipe $recipe): array => $this->recipePayload($recipe, $canManage, $canExecute))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Store a parent recipe and its initial draft version.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'recipe_type' => ['required', 'string', Rule::in(Recipe::allowedRecipeTypes())],
            'name' => ['required', 'string', 'max:255'],
            'output_quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $tenantId = (int) $request->user()->tenant_id;
        $outputItem = Item::query()->where('tenant_id', $tenantId)->findOrFail($validated['item_id']);

        if ($eligibilityError = Recipe::recipeTypeEligibilityError($outputItem, $validated['recipe_type'])) {
            return $this->validationError(['recipe_type' => [$eligibilityError]], $eligibilityError);
        }

        try {
            $recipe = DB::transaction(function () use ($request, $validated, $tenantId): Recipe {
                $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;
                $isDefault = $request->boolean('is_default');

                if ($isDefault) {
                    Recipe::query()
                        ->where('tenant_id', $tenantId)
                        ->where('item_id', $validated['item_id'])
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $recipe = Recipe::query()->create([
                    'tenant_id' => $tenantId,
                    'item_id' => $validated['item_id'],
                    'recipe_type' => $validated['recipe_type'],
                    'name' => $validated['name'],
                    'output_quantity' => Recipe::normalizeOutputQuantityForType($validated['recipe_type'], $validated['output_quantity']),
                    'is_active' => $isActive,
                    'is_default' => $isDefault,
                ]);

                $recipe->versions()->create([
                    'tenant_id' => $tenantId,
                    'version_number' => 100,
                    'name' => null,
                    'output_quantity' => Recipe::normalizeOutputQuantityForType($validated['recipe_type'], $validated['output_quantity']),
                    'recipe_type' => $validated['recipe_type'],
                    'status' => RecipeVersion::STATUS_DRAFT,
                    'effective_from' => null,
                    'effective_until' => null,
                    'approved_at' => null,
                    'approved_by_user_id' => null,
                    'notes' => null,
                ]);

                return $recipe;
            });
        } catch (InvalidArgumentException $exception) {
            return $this->handleRecipeException($exception);
        }

        $recipe->load(['item.baseUom', 'currentVersion']);

        return response()->json([
            'data' => $this->recipePayload(
                $recipe,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
        ], 201);
    }

    /**
     * Update parent-level recipe metadata only.
     */
    public function update(Request $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($recipe, $request, $validated): void {
            $isDefault = $request->has('is_default') ? $request->boolean('is_default') : $recipe->is_default;

            if ($isDefault) {
                Recipe::query()
                    ->where('tenant_id', $recipe->tenant_id)
                    ->where('item_id', $recipe->item_id)
                    ->where('is_default', true)
                    ->where('id', '!=', $recipe->id)
                    ->update(['is_default' => false]);
            }

            $recipe->name = $validated['name'];
            $recipe->is_active = $request->boolean('is_active');
            $recipe->is_default = $isDefault;
            $recipe->save();
        });

        $recipe->load(['item.baseUom', 'currentVersion']);

        return response()->json([
            'data' => $this->recipePayload(
                $recipe,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
        ]);
    }

    /**
     * Archive a recipe parent and its current published version.
     */
    public function destroy(Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        DB::transaction(function () use ($recipe): void {
            $currentVersion = $recipe->currentPublishedVersion();
            $recipe->is_active = false;
            $recipe->current_version_id = null;
            $recipe->save();

            if ($currentVersion) {
                $currentVersion->status = RecipeVersion::STATUS_ARCHIVED;
                $currentVersion->effective_until = now();
                $currentVersion->save();
            }

            $recipe->versionCheckouts()->whereNull('checked_in_at')->update(['checked_in_at' => now()]);
        });

        return response()->json(['deleted' => true]);
    }

    /**
     * Create a new draft execution version and auto-check it out to the creator.
     */
    public function storeVersion(Request $request, Recipe $recipe): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        try {
            $validated = $this->validateVersionPayload($request, $recipe);
        } catch (InvalidArgumentException $exception) {
            return $this->handleRecipeException($exception);
        }

        $userId = (int) $request->user()->id;

        $version = DB::transaction(function () use ($recipe, $validated, $userId): RecipeVersion {
            $version = $this->createRecipeVersionRecord($recipe, $validated);
            $this->openCheckout($recipe, $version, $userId);

            return $version->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom']);
        });

        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);

        return response()->json([
            'data' => $this->recipeVersionPayload($version),
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $version,
                $userId,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $version, $userId),
        ], 201);
    }

    /**
     * Update a checked-out draft version.
     */
    public function updateVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        if (! $this->userOwnsOpenCheckout($recipe, $versionModel, (int) $request->user()->id)) {
            abort(403);
        }

        if (! $versionModel->isEditableLifecycle()) {
            return $this->validationError([
                'recipe_version_id' => ['Only checked out draft versions can be edited.'],
            ], 'Only checked out draft versions can be edited.');
        }

        $validated = $request->validate([
            'recipe_type' => ['required', 'string', Rule::in(Recipe::allowedRecipeTypes())],
            'output_quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        DB::transaction(function () use ($recipe, $versionModel, $validated): void {
            $versionModel->recipe_type = $validated['recipe_type'];
            $versionModel->output_quantity = Recipe::normalizeOutputQuantityForType(
                $validated['recipe_type'],
                $validated['output_quantity']
            );
            $versionModel->save();

            if ((int) $recipe->current_version_id === (int) $versionModel->id) {
                $this->syncRecipeMirror($recipe, $versionModel);
            }
        });

        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);

        return response()->json([
            'data' => $this->recipeVersionPayload($versionModel->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom'])),
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $versionModel,
                (int) $request->user()->id,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $versionModel, (int) $request->user()->id),
        ]);
    }

    /**
     * Checkout a draft version for editing.
     */
    public function checkoutVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        $normalizedStatus = RecipeVersion::normalizeStatus($versionModel->status);

        if ($normalizedStatus === RecipeVersion::STATUS_ARCHIVED) {
            return $this->validationError([
                'recipe_version_id' => ['Archived versions cannot be checked out.'],
            ], 'Archived versions cannot be checked out.');
        }

        if ($normalizedStatus !== RecipeVersion::STATUS_DRAFT) {
            return $this->validationError([
                'recipe_version_id' => ['Only draft versions can be checked out.'],
            ], 'Only draft versions can be checked out.');
        }

        $userId = (int) $request->user()->id;

        try {
            $checkedOutVersion = DB::transaction(function () use ($recipe, $versionModel, $userId): RecipeVersion {
                if ($this->hasOpenCheckoutForVersionUser($versionModel, $userId)) {
                    throw new InvalidArgumentException('This version is already checked out by you.');
                }

                $this->openCheckout($recipe, $versionModel, $userId);

                return $versionModel->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom']);
            });
        } catch (InvalidArgumentException $exception) {
            return $this->validationError([
                'recipe_version_id' => [$exception->getMessage()],
            ], $exception->getMessage());
        }

        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);

        return response()->json([
            'data' => $this->recipeVersionPayload($checkedOutVersion),
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $checkedOutVersion,
                $userId,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $checkedOutVersion, $userId),
        ], 201);
    }

    /**
     * Check in one checked-out version for the current user.
     */
    public function checkInVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        $checkout = $this->openCheckoutRecordForVersionUser($versionModel, (int) $request->user()->id);

        if (! $checkout) {
            abort(403);
        }

        $checkout->checked_in_at = now();
        $checkout->save();

        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);
        $displayVersion = $recipe->displayVersionForUser((int) $request->user()->id);

        return response()->json([
            'checked_in' => true,
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $displayVersion,
                (int) $request->user()->id,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $displayVersion, (int) $request->user()->id),
        ]);
    }

    /**
     * Publish a draft and make it current.
     */
    public function publishVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        if (RecipeVersion::normalizeStatus($versionModel->status) !== RecipeVersion::STATUS_DRAFT) {
            return $this->validationError([
                'recipe_version_id' => ['Only draft versions can be published.'],
            ], 'Only draft versions can be published.');
        }

        if (! $this->recipeVersionHasIngredients($versionModel)) {
            return $this->validationError([
                'recipe_version_id' => ['Add at least one ingredient before publishing this version.'],
            ], 'Add at least one ingredient before publishing this version.');
        }

        DB::transaction(function () use ($recipe, $versionModel, $request): void {
            $previousPublishedVersionIds = $recipe->versions()
                ->where('tenant_id', $recipe->tenant_id)
                ->whereKeyNot($versionModel->id)
                ->whereIn('status', [
                    RecipeVersion::STATUS_PUBLISHED,
                    RecipeVersion::STATUS_APPROVED_LEGACY,
                ])
                ->pluck('id');

            $versionModel->version_number = $this->nextPublishVersionNumber($recipe, $versionModel);

            if ($previousPublishedVersionIds->isNotEmpty()) {
                $recipe->versions()
                    ->whereIn('id', $previousPublishedVersionIds)
                    ->update([
                        'status' => RecipeVersion::STATUS_ARCHIVED,
                        'effective_until' => now(),
                    ]);
            }

            $versionModel->status = RecipeVersion::STATUS_PUBLISHED;
            $versionModel->effective_from = now();
            $versionModel->effective_until = null;
            $versionModel->approved_at = now();
            $versionModel->approved_by_user_id = $request->user()->id;
            $versionModel->save();

            $recipe->current_version_id = $versionModel->id;
            $recipe->save();

            $this->closeOpenCheckoutsForRecipeUser($recipe, (int) $request->user()->id);
            $this->syncRecipeMirror($recipe, $versionModel);
        });

        $canManage = Gate::allows('inventory-make-orders-manage');
        $canExecute = Gate::allows('inventory-make-orders-execute');
        $canViewMakeOrders = Gate::allows('inventory-make-orders-view');
        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);
        $displayVersion = $recipe->displayVersionForUser((int) $request->user()->id);

        return response()->json([
            'data' => $this->recipeVersionPayload($versionModel->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom'])),
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $displayVersion,
                (int) $request->user()->id,
                $canManage,
                $canExecute
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $displayVersion, (int) $request->user()->id),
            'sections' => $this->detailSectionsPayload($recipe, $canManage, $canExecute, $canViewMakeOrders),
            'ui' => [
                'recently_published_version_id' => $versionModel->id,
                'recently_published_highlight_ms' => 1000,
            ],
        ]);
    }

    /**
     * Archive a draft or non-current published version.
     */
    public function archiveVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        if ((int) $recipe->current_version_id === (int) $versionModel->id) {
            return $this->validationError([
                'recipe_version_id' => ['Current versions cannot be archived directly. Publish another draft first.'],
            ], 'Current versions cannot be archived directly. Publish another draft first.');
        }

        DB::transaction(function () use ($versionModel): void {
            $versionModel->status = RecipeVersion::STATUS_ARCHIVED;
            $versionModel->effective_until = now();
            $versionModel->save();
            $versionModel->checkouts()->whereNull('checked_in_at')->update(['checked_in_at' => now()]);
        });

        return response()->json([
            'data' => $this->recipeVersionPayload($versionModel->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom'])),
        ]);
    }

    /**
     * Duplicate a version into a new editable draft.
     */
    public function duplicateVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $sourceVersion = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        $draftVersion = DB::transaction(function () use ($recipe, $sourceVersion, $request): RecipeVersion {
            $draftVersion = $this->cloneVersionAsDraft($recipe, $sourceVersion);
            $this->closeOpenCheckout($draftVersion, (int) $request->user()->id);
            $this->openCheckout($recipe, $draftVersion, (int) $request->user()->id);

            return $draftVersion->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom']);
        });

        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);

        return response()->json([
            'data' => $this->recipeVersionPayload($draftVersion),
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $draftVersion,
                (int) $request->user()->id,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $draftVersion, (int) $request->user()->id),
        ], 201);
    }

    /**
     * Delete a draft version.
     */
    public function destroyVersion(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->firstOrFail();

        if (RecipeVersion::normalizeStatus($versionModel->status) !== RecipeVersion::STATUS_DRAFT) {
            return $this->validationError([
                'recipe_version_id' => ['Only draft versions can be deleted.'],
            ], 'Only draft versions can be deleted.');
        }

        if ((int) $recipe->current_version_id === (int) $versionModel->id) {
            return $this->validationError([
                'recipe_version_id' => ['Current versions cannot be deleted.'],
            ], 'Current versions cannot be deleted.');
        }

        DB::transaction(function () use ($versionModel): void {
            $versionModel->checkouts()->delete();
            $versionModel->lines()->delete();
            $versionModel->delete();
        });

        return response()->json([
            'deleted' => true,
        ]);
    }

    /**
     * Create a version-owned ingredient line.
     */
    public function storeIngredient(Request $request, Recipe $recipe, int $version): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $this->editableCheckedOutVersion($request, $recipe, $version);

        if ($versionModel instanceof JsonResponse) {
            return $versionModel;
        }

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        if ((int) $validated['item_id'] === (int) $recipe->item_id) {
            return $this->validationError([
                'item_id' => ['Ingredient item cannot reference the output item.'],
            ]);
        }

        $item = Item::query()->where('tenant_id', $request->user()->tenant_id)->findOrFail($validated['item_id']);

        $line = $versionModel->lines()->create([
            'tenant_id' => $request->user()->tenant_id,
            'input_item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'quantity' => '1.000000',
            'sort_order' => ((int) $versionModel->lines()->max('sort_order')) + 1,
        ]);

        return response()->json([
            'data' => $this->ingredientLinePayload(
                $line->fresh(['inputItem.baseUom']),
                (int) $recipe->id,
                (int) $versionModel->id
            ),
        ], 201);
    }

    /**
     * Update a version-owned ingredient quantity.
     */
    public function updateIngredient(Request $request, Recipe $recipe, int $version, int $line): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $this->editableCheckedOutVersion($request, $recipe, $version);

        if ($versionModel instanceof JsonResponse) {
            return $versionModel;
        }

        $validated = $request->validate([
            'quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        $normalizedQuantity = $this->normalizeQuantityString($validated['quantity']);

        if (bccomp($normalizedQuantity, '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $lineModel = $versionModel->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $lineModel->quantity = $normalizedQuantity;
        $lineModel->save();

        return response()->json([
            'data' => $this->ingredientLinePayload(
                $lineModel->fresh(['inputItem.baseUom']),
                (int) $recipe->id,
                (int) $versionModel->id
            ),
            'meta' => [
                'saved' => true,
            ],
        ]);
    }

    /**
     * Delete a version-owned ingredient line.
     */
    public function destroyIngredient(Request $request, Recipe $recipe, int $version, int $line): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $versionModel = $this->editableCheckedOutVersion($request, $recipe, $version);

        if ($versionModel instanceof JsonResponse) {
            return $versionModel;
        }

        $lineModel = $versionModel->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $lineModel->delete();

        $recipe->refresh()->load(['item.baseUom', 'currentVersion']);
        $displayVersion = $recipe->displayVersionForUser((int) $request->user()->id);

        return response()->json([
            'deleted_line_id' => (int) $lineModel->id,
            'recipe' => $this->recipeDetailPayload(
                $recipe,
                $displayVersion,
                (int) $request->user()->id,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute')
            ),
            'ingredients' => $this->ingredientsPayload($recipe, $displayVersion, (int) $request->user()->id),
            'sections' => $this->detailSectionsPayload(
                $recipe,
                Gate::allows('inventory-make-orders-manage'),
                Gate::allows('inventory-make-orders-execute'),
                Gate::allows('inventory-make-orders-view')
            ),
            'message' => 'Removed.',
        ]);
    }

    /**
     * Legacy line create alias.
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
            'quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        if ((int) $validated['item_id'] === (int) $recipe->item_id) {
            return $this->validationError([
                'item_id' => ['Ingredient item cannot reference the output item.'],
            ]);
        }

        if (bccomp($validated['quantity'], '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $version = $this->resolveOrCreateEditableDraftForUser($recipe, (int) $request->user()->id);
        $item = Item::query()->where('tenant_id', $request->user()->tenant_id)->findOrFail($validated['item_id']);

        $line = DB::transaction(function () use ($recipe, $version, $item, $validated): RecipeLine {
            $line = RecipeLine::query()->create([
                'tenant_id' => $recipe->tenant_id,
                'recipe_id' => $recipe->id,
                'item_id' => $item->id,
                'quantity' => $validated['quantity'],
            ]);

            $this->syncVersionLinesFromLegacyRecipeLines($recipe, $version);

            return $line->fresh(['item.baseUom']);
        });

        return response()->json([
            'data' => $this->legacyRecipeLinePayload($line),
        ], 201);
    }

    /**
     * Legacy line update alias.
     */
    public function updateLine(Request $request, Recipe $recipe, int $line): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);

        if ((int) $validated['item_id'] === (int) $recipe->item_id) {
            return $this->validationError([
                'item_id' => ['Ingredient item cannot reference the output item.'],
            ]);
        }

        if (bccomp($validated['quantity'], '0.000000', self::SCALE) !== 1) {
            return $this->validationError([
                'quantity' => ['Quantity must be greater than zero.'],
            ]);
        }

        $displayVersion = $this->resolveOrCreateEditableDraftForUser($recipe, (int) $request->user()->id);

        $lineModel = RecipeLine::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->whereKey($line)
            ->firstOrFail();

        DB::transaction(function () use ($recipe, $displayVersion, $lineModel, $validated): void {
            $lineModel->item_id = (int) $validated['item_id'];
            $lineModel->quantity = $validated['quantity'];
            $lineModel->save();

            $this->syncVersionLinesFromLegacyRecipeLines($recipe, $displayVersion);
        });

        return response()->json([
            'data' => $this->legacyRecipeLinePayload($lineModel->fresh(['item.baseUom'])),
        ]);
    }

    /**
     * Legacy line delete alias.
     */
    public function destroyLine(Request $request, Recipe $recipe, int $line): JsonResponse
    {
        Gate::authorize('inventory-make-orders-manage');

        $displayVersion = $this->resolveOrCreateEditableDraftForUser($recipe, (int) $request->user()->id);

        $lineModel = RecipeLine::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->whereKey($line)
            ->firstOrFail();

        DB::transaction(function () use ($recipe, $displayVersion, $lineModel): void {
            $lineModel->delete();
            $this->syncVersionLinesFromLegacyRecipeLines($recipe, $displayVersion);
        });

        return response()->json(['deleted' => true]);
    }

    /**
     * Build the recipes index CRUD config.
     *
     * @return array<string, mixed>
     */
    private function crudConfig(bool $canManage, bool $canExecute): array
    {
        return [
            'resource' => 'recipes',
            'endpoints' => [
                'list' => route('manufacturing.recipes.list'),
                'create' => route('manufacturing.recipes.store'),
                'update' => url('/manufacturing/recipes/{id}'),
                'versionStore' => url('/manufacturing/recipes/{id}/versions'),
                'delete' => url('/manufacturing/recipes/{id}'),
            ],
            'detailUrlTemplate' => url('/manufacturing/recipes/{id}'),
            'columns' => [
                'name',
                'output_item_name',
                'current_version_number',
                'output_quantity',
                'updated_at',
            ],
            'headers' => [
                'name' => 'Recipe Name',
                'output_item_name' => 'Output Item',
                'current_version_number' => 'Current Version',
                'output_quantity' => 'Output Qty',
                'updated_at' => 'Updated',
            ],
            'sortable' => [
                'name',
                'output_item_name',
                'current_version_number',
                'output_quantity',
                'updated_at',
            ],
            'labels' => [
                'searchPlaceholder' => 'Search recipes',
                'createTitle' => 'Create Recipe',
                'createAriaLabel' => 'Create Recipe',
                'emptyState' => 'No recipes found.',
                'actionsAriaLabel' => 'Recipe actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => $canManage,
            ],
            'rowDisplay' => [
                'columns' => [
                    'name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'output_item_name' => ['kind' => 'text'],
                    'current_version_number' => ['kind' => 'text'],
                    'output_quantity' => ['kind' => 'text'],
                    'updated_at' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.name || '—'",
                'titleBadgesExpression' => 'recipeTitleBadges(record)',
                'subtitleExpression' => "record.output_item_name || '—'",
                'bodyExpression' => 'recipeMobileSummary(record)',
            ],
            'desktopCard' => [
                'titleExpression' => "record.name || '—'",
                'titleBadgesExpression' => 'recipeTitleBadges(record)',
                'subtitleExpression' => "record.output_item_name || '—'",
                'detailRowsExpression' => 'recipeCardRows(record)',
                'bodyExpression' => '',
                'showBody' => false,
                'compact' => true,
                'urlExpression' => 'record.show_url',
            ],
            'actions' => [
                ['id' => 'make', 'label' => 'Make Order', 'tone' => 'default'],
                ['id' => 'edit', 'label' => 'Edit', 'tone' => 'default'],
                ['id' => 'archive', 'label' => 'Archive', 'tone' => 'warning'],
            ],
        ];
    }

    /**
     * Build the versions accordion config.
     *
     * @return array<string, mixed>
     */
    private function versionsSectionConfig(Recipe $recipe, bool $canManage): array
    {
        return [
            'resource' => 'recipe-versions',
            'title' => 'Versions',
            'description' => 'Version status is lifecycle. Currentness is controlled only by recipes.current_version_id.',
            'emptyState' => 'No recipe versions exist yet.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => false,
            'permissions' => [
                'canCreate' => $canManage,
            ],
            'toolbarToggles' => [
                [
                    'key' => 'include_archived',
                    'label' => 'View Archived',
                    'checked' => false,
                ],
            ],
            'createAction' => [
                'type' => 'custom',
                'handlerKey' => 'openVersionCreate',
            ],
            'endpoints' => [
                'list' => route('manufacturing.recipes.versions.index', $recipe),
                'update' => url("/manufacturing/recipes/{$recipe->id}/versions/{id}"),
                'remove' => url("/manufacturing/recipes/{$recipe->id}/versions/{id}"),
            ],
            'fields' => [
                [
                    'name' => 'recipe_type',
                    'label' => 'Type',
                    'type' => 'select',
                    'required' => true,
                    'rowGroup' => 'type-output',
                    'options' => $this->recipeTypeOptions(),
                ],
                [
                    'name' => 'output_quantity',
                    'label' => 'Output Qty',
                    'type' => 'text',
                    'required' => true,
                    'rowGroup' => 'type-output',
                ],
            ],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.versionText',
                    'fallback' => 'Version',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Type',
                        'field' => 'display.typeText',
                        'fallback' => '—',
                    ],
                    [
                        'label' => 'Context',
                        'field' => 'display.contextText',
                        'fallback' => '—',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [
                    [
                        'label' => 'Output',
                        'field' => 'display.outputQuantityText',
                        'fallback' => '—',
                        'strong' => true,
                    ],
                    [
                        'label' => 'Updated',
                        'field' => 'display.updatedAtText',
                        'fallback' => '—',
                        'strong' => false,
                    ],
                ],
            ],
            'actions' => $this->recipeVersionSectionActions(),
        ];
    }

    /**
     * Build the ingredients section shell config.
     *
     * @return array<string, mixed>
     */
    private function ingredientsSectionConfig(): array
    {
        return [
            'resource' => 'recipe-ingredients',
            'title' => 'Ingredients',
            'description' => 'Ingredients are version-owned and follow the current display version context.',
            'defaultOpen' => true,
        ];
    }

    /**
     * Build the recipe detail make-orders section config.
     *
     * @return array<string, mixed>
     */
    private function makeOrdersSectionConfig(Recipe $recipe, bool $canExecute): array
    {
        $hasCurrentPublishedVersion = $recipe->currentPublishedVersion() !== null;

        return [
            'resource' => 'recipe-make-orders',
            'title' => 'Make Orders',
            'description' => 'Make orders created from this recipe always use recipes.current_version_id.',
            'emptyState' => 'No make orders exist for this recipe yet.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => false,
            'mobilePageSize' => 3,
            'permissions' => [
                'canCreate' => $canExecute && $hasCurrentPublishedVersion,
            ],
            'createAction' => [
                'type' => 'custom',
                'handlerKey' => 'createMakeOrder',
                'title' => 'Create Make Order',
                'description' => 'Recipe: ' . $recipe->name . '. Make orders created from this recipe always use recipes.current_version_id.',
            ],
            'endpoints' => [
                'list' => route('manufacturing.recipes.make-orders.index', $recipe),
                'create' => route('manufacturing.recipes.make-orders.store', $recipe),
            ],
            'fields' => [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.recipeNameText',
                    'urlField' => 'display.showUrl',
                    'fallback' => 'Unnamed recipe',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Runs',
                        'field' => 'display.runsText',
                        'fallback' => '—',
                    ],
                    [
                        'label' => 'Due',
                        'field' => 'display.dueDateText',
                        'fallback' => 'No due date',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'fallback' => '',
                    ],
                    [
                        'field' => 'display.versionBadgeText',
                        'toneField' => 'display.versionBadgeTone',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [
                    [
                        'label' => 'Qty',
                        'field' => 'display.totalOutputQuantityText',
                        'fallback' => '—',
                        'strong' => true,
                    ],
                ],
            ],
            'actions' => [
                ['id' => 'view', 'label' => 'View', 'type' => 'view', 'tone' => 'default', 'urlField' => 'display.showUrl'],
            ],
        ];
    }

    /**
     * Build the recipe detail shared sections payload.
     *
     * @return array<string, mixed>
     */
    private function detailSectionsPayload(
        Recipe $recipe,
        bool $canManage,
        bool $canExecute,
        bool $canViewMakeOrders
    ): array {
        return [
            'ingredients' => $this->ingredientsSectionConfig(),
            'makeOrders' => $canViewMakeOrders ? $this->makeOrdersSectionConfig($recipe, $canExecute) : null,
            'versions' => $this->versionsSectionConfig($recipe, $canManage),
        ];
    }

    /**
     * Resolve the page size for reusable detail-section list endpoints.
     */
    private function perPageFromRequest(Request $request): int
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return (int) ($validated['per_page'] ?? 5);
    }

    /**
     * Build one recipe list row.
     *
     * @return array<string, mixed>
     */
    private function recipePayload(Recipe $recipe, bool $canManage = false, bool $canExecute = false): array
    {
        $currentVersion = $recipe->currentPublishedVersion();
        $displayVersion = $currentVersion ?? $this->latestDisplayVersion($recipe);
        $outputQuantity = $displayVersion
            ? (string) $displayVersion->output_quantity
            : (string) $recipe->output_quantity;
        $versionStatus = $displayVersion?->status ?? ($recipe->is_active ? '—' : RecipeVersion::STATUS_ARCHIVED);
        $recipeType = $displayVersion?->recipe_type ?? $recipe->recipe_type;
        $displayVersionNumber = $displayVersion?->versionNumberDisplay() ?? '—';
        $canMake = $canExecute
            && $currentVersion !== null
            && $currentVersion->recipe_type === Recipe::TYPE_MANUFACTURING;
        $availableActions = ['view'];
        if ($canMake) {
            $availableActions[] = 'make';
        }

        if ($canManage) {
            $availableActions[] = 'edit';
            $availableActions[] = 'archive';
        }

        $payload = [
            'id' => $recipe->id,
            'item_id' => $recipe->item_id,
            'name' => $recipe->name,
            'output_item_name' => $recipe->item?->name ?? '—',
            'output_quantity' => $outputQuantity,
            'output_quantity_display' => $outputQuantity !== ''
                ? QuantityFormatter::formatForUom($outputQuantity, $recipe->item?->baseUom, 2)
                : '—',
            'recipe_type' => $recipeType,
            'recipe_type_label' => $recipeType ? Recipe::labelForRecipeType($recipeType) : '—',
            'display_version_number_display' => $displayVersionNumber,
            'current_version_id' => $currentVersion?->id,
            'current_version_number' => $currentVersion?->version_number,
            'current_version_number_display' => $currentVersion?->versionNumberDisplay() ?? '—',
            'version_status' => RecipeVersion::normalizeStatus($versionStatus),
            'is_active' => (bool) $recipe->is_active,
            'is_default' => (bool) $recipe->is_default,
            'is_default_label' => $recipe->is_default ? 'Yes' : 'No',
            'updated_at' => $recipe->updated_at?->format('Y-m-d H:i') ?? '—',
            'show_url' => route('manufacturing.recipes.show', $recipe),
            'available_actions' => array_values(array_unique($availableActions)),
        ];

        if ($canMake) {
            $payload['make_url'] = route('manufacturing.recipes.make-orders.store', $recipe);
        }

        return $payload;
    }

    /**
     * Build the recipe detail payload for the active display context.
     *
     * @return array<string, mixed>
     */
    private function recipeDetailPayload(
        Recipe $recipe,
        ?RecipeVersion $displayVersion,
        int $userId,
        bool $canManage = false,
        bool $canExecute = false
    ): array
    {
        $resolvedDisplayVersion = $displayVersion ?? $this->resolveRecipeDetailDisplayVersion($recipe, $userId);
        $displayVersionNumber = $resolvedDisplayVersion?->versionNumberDisplay() ?? '—';
        $displayOutputQuantity = $resolvedDisplayVersion ? (string) $resolvedDisplayVersion->output_quantity : null;
        $currentVersion = $recipe->currentPublishedVersion();

        return array_merge($this->recipePayload($recipe, $canManage, $canExecute), [
            'item_uom' => $recipe->item?->baseUom
                ? $recipe->item->baseUom->name . ' (' . $recipe->item->baseUom->symbol . ')'
                : '—',
            'display_version_id' => $resolvedDisplayVersion?->id,
            'display_version_number' => $displayVersionNumber,
            'display_output_quantity' => $displayOutputQuantity,
            'display_output_quantity_text' => $displayOutputQuantity !== null
                ? QuantityFormatter::formatForUom($displayOutputQuantity, $recipe->item?->baseUom, 2)
                : '—',
            'display_recipe_type' => $resolvedDisplayVersion?->recipe_type,
            'display_recipe_type_label' => $resolvedDisplayVersion ? Recipe::labelForRecipeType($resolvedDisplayVersion->recipe_type) : '—',
            'display_recipe_type_icon' => $this->recipeTypeIcon($resolvedDisplayVersion?->recipe_type),
            'active_version' => $this->activeRecipeVersionSummary($recipe, $resolvedDisplayVersion, $userId, $canManage, $canExecute),
            'current_published_version_id' => $currentVersion?->id,
            'current_published_version_number' => $currentVersion?->versionNumberDisplay() ?? '—',
            'update_url' => route('manufacturing.recipes.update', $recipe),
            'delete_url' => route('manufacturing.recipes.destroy', $recipe),
            'version_store_url' => route('manufacturing.recipes.versions.store', $recipe),
        ]);
    }

    /**
     * Build one version row for the versions accordion.
     *
     * @return array<string, mixed>
     */
    private function recipeVersionListRow(
        Recipe $recipe,
        RecipeVersion $version,
        int $userId,
        bool $canManage,
        bool $canExecute
    ): array
    {
        $normalizedStatus = RecipeVersion::normalizeStatus($version->status);
        $isCurrent = (int) $recipe->current_version_id === (int) $version->id;
        $isCheckedOutByUser = $this->userOwnsOpenCheckout($recipe, $version, $userId);
        $isCheckedOutByAnotherUser = $this->versionCheckedOutByAnotherUser($version, $userId);

        $availableActions = [];

        if ($normalizedStatus === RecipeVersion::STATUS_PUBLISHED) {
            if ($isCurrent && $version->recipe_type === Recipe::TYPE_MANUFACTURING) {
                $availableActions[] = 'make';
            }

            if ($canManage) {
                $availableActions[] = 'duplicate';
                $availableActions[] = 'archive';
            }
        } elseif ($normalizedStatus === RecipeVersion::STATUS_DRAFT) {
            if ($canManage) {
                if ($isCheckedOutByUser) {
                    $availableActions[] = 'check_in';
                } else {
                    $availableActions[] = 'checkout';
                }

                if ($this->recipeVersionHasIngredients($version)) {
                    $availableActions[] = 'publish';
                }
                $availableActions[] = 'duplicate';
                $availableActions[] = 'delete';
            }
        } elseif ($normalizedStatus === RecipeVersion::STATUS_ARCHIVED && $canManage) {
            $availableActions[] = 'view';
            $availableActions[] = 'duplicate';
        }

        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'version_number_display' => $version->versionNumberDisplay(),
            'name' => $version->name,
            'recipe_type' => $version->recipe_type,
            'is_current' => $isCurrent,
            'is_checked_out_by_user' => $isCheckedOutByUser,
            'status' => $normalizedStatus,
            'output_quantity' => (string) $version->output_quantity,
            'updated_at' => $version->updated_at?->format('Y-m-d H:i') ?? '—',
            'availableActions' => array_values(array_unique($availableActions)),
            'checkout_url' => route('manufacturing.recipes.versions.checkout', [$recipe, $version->id]),
            'check_in_url' => route('manufacturing.recipes.versions.check-in', [$recipe, $version->id]),
            'publish_url' => route('manufacturing.recipes.versions.publish', [$recipe, $version->id]),
            'duplicate_url' => route('manufacturing.recipes.versions.duplicate', [$recipe, $version->id]),
            'archive_url' => route('manufacturing.recipes.versions.archive', [$recipe, $version->id]),
            'view_url' => route('manufacturing.recipes.show', $recipe),
            'make_url' => $isCurrent && $normalizedStatus === RecipeVersion::STATUS_PUBLISHED && $version->recipe_type === Recipe::TYPE_MANUFACTURING
                ? route('manufacturing.recipes.make-orders.store', $recipe)
                : null,
            'display' => [
                'versionText' => $version->versionNumberDisplay(),
                'typeText' => Recipe::labelForRecipeType($version->recipe_type),
                'typeLabel' => Recipe::labelForRecipeType($version->recipe_type),
                'typeIcon' => $this->recipeTypeIcon($version->recipe_type),
                'contextText' => $isCurrent
                    ? 'Current'
                    : ($isCheckedOutByUser ? 'Checked out by you' : ($isCheckedOutByAnotherUser ? 'Checked out' : 'Read only')),
                'statusText' => $this->versionStatusLabel($normalizedStatus),
                'statusTone' => $this->versionStatusTone($normalizedStatus),
                'outputQuantityText' => QuantityFormatter::formatForUom((string) $version->output_quantity, $recipe->item?->baseUom, 2),
                'updatedAtText' => $version->updated_at?->format('Y-m-d H:i') ?? '—',
            ],
            'formValues' => [
                'recipe_type' => $version->recipe_type,
                'output_quantity' => (string) $version->output_quantity,
            ],
        ];
    }

    /**
     * Build a version API payload.
     *
     * @return array<string, mixed>
     */
    private function recipeVersionPayload(RecipeVersion $version): array
    {
        return [
            'id' => $version->id,
            'recipe_id' => $version->recipe_id,
            'version_number' => (int) $version->version_number,
            'version_number_display' => $version->versionNumberDisplay(),
            'status' => RecipeVersion::normalizeStatus($version->status),
            'recipe_type' => $version->recipe_type,
            'output_quantity' => (string) $version->output_quantity,
            'lines' => $version->lines
                ->map(fn (RecipeVersionLine $line): array => $this->ingredientLinePayload($line, (int) $version->recipe_id, (int) $version->id))
                ->all(),
        ];
    }

    /**
     * Resolve the newest version to display when no current published version exists yet.
     */
    private function latestDisplayVersion(Recipe $recipe): ?RecipeVersion
    {
        if ($recipe->relationLoaded('versions')) {
            /** @var \Illuminate\Support\Collection<int, RecipeVersion> $versions */
            $versions = $recipe->getRelation('versions');

            return $versions
                ->sortBy([
                    fn (RecipeVersion $version): int => (int) $version->version_number,
                    fn (RecipeVersion $version): int => (int) $version->id,
                ])
                ->reverse()
                ->first();
        }

        return $recipe->versions()
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Build JSON payload for ingredient editing.
     *
     * @return array<string, mixed>
     */
    private function ingredientsPayload(Recipe $recipe, ?RecipeVersion $displayVersion, int $userId): array
    {
        return [
            'display_version_id' => $displayVersion?->id,
            'display_version_number' => $displayVersion?->versionNumberDisplay() ?? '—',
            'can_edit' => $displayVersion !== null
                && RecipeVersion::normalizeStatus($displayVersion->status) === RecipeVersion::STATUS_DRAFT
                && $this->userOwnsOpenCheckout($recipe, $displayVersion, $userId),
            'item_options' => $this->ingredientItemsPayload((int) $recipe->tenant_id, (int) $recipe->item_id),
            'lines' => $displayVersion?->lines
                ? $displayVersion->lines
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn (RecipeVersionLine $line): array => $this->ingredientLinePayload($line, (int) $recipe->id, (int) $displayVersion->id))
                    ->all()
                : [],
            'store_url' => $displayVersion ? route('manufacturing.recipes.ingredients.store', [$recipe, $displayVersion->id]) : null,
            'update_url_template' => $displayVersion
                ? URL::route('manufacturing.recipes.ingredients.update', [$recipe, $displayVersion->id, '__LINE__'])
                : null,
        ];
    }

    /**
     * Build one ingredient line payload.
     *
     * @return array<string, mixed>
     */
    private function ingredientLinePayload(RecipeVersionLine $line, int $recipeId, int $versionId): array
    {
        return [
            'id' => $line->id,
            'item_id' => $line->input_item_id,
            'item_name' => $line->inputItem?->name ?? '—',
            'uom' => $line->inputItem?->baseUom?->symbol ?? '—',
            'quantity' => (string) $line->quantity,
            'quantity_input' => QuantityFormatter::formatForUom((string) $line->quantity, $line->inputItem?->baseUom, 6),
            'quantity_display' => QuantityFormatter::formatForUom((string) $line->quantity, $line->inputItem?->baseUom, 6),
            'uom_display_precision' => (int) ($line->inputItem?->baseUom?->display_precision ?? 6),
            'sort_order' => (int) $line->sort_order,
            'remove_url' => route('manufacturing.recipes.ingredients.destroy', [$recipeId, $versionId, $line->id]),
        ];
    }

    /**
     * Build a compatibility payload for legacy recipe lines endpoints.
     *
     * @return array<string, mixed>
     */
    private function legacyRecipeLinePayload(RecipeLine $line): array
    {
        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_name' => $line->item?->name ?? '—',
            'item_uom' => $line->item?->baseUom?->symbol ?? '—',
            'quantity' => (string) $line->quantity,
            'quantity_display' => QuantityFormatter::formatForUom((string) $line->quantity, $line->item?->baseUom, 6),
        ];
    }

    /**
     * Build the output item payload list for comboboxes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function manufacturableItemsPayload(int $tenantId): array
    {
        return Item::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->where('is_manufacturable', true)
                    ->orWhere('is_sellable', true);
            })
            ->withCount('recipes')
            ->with(['baseUom:id,name,symbol,display_precision'])
            ->orderBy('name')
            ->get(['id', 'name', 'base_uom_id', 'is_manufacturable', 'is_sellable'])
            ->map(fn (Item $item): array => $this->manufacturableItemPayload($item))
            ->all();
    }

    /**
     * Build ingredient combobox options.
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
            ->get(['id', 'name', 'base_uom_id'])
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
     * Build JSON payload for one output item option.
     *
     * @return array<string, mixed>
     */
    private function manufacturableItemPayload(Item $item): array
    {
        $uomDisplay = $item->baseUom
            ? $item->baseUom->name . ' (' . $item->baseUom->symbol . ')'
            : '—';

        return [
            'id' => $item->id,
            'name' => $item->name,
            'uom_display' => $uomDisplay,
            'display_text' => $item->name . ' ' . $uomDisplay,
            'search_text' => strtolower($item->name . ' ' . $uomDisplay),
            'has_recipe' => $item->recipes_count > 0,
            'is_manufacturable' => (bool) $item->is_manufacturable,
            'is_sellable' => (bool) $item->is_sellable,
            'uom_display_precision' => (int) ($item->baseUom?->display_precision ?? 6),
            'allowed_recipe_types' => $this->allowedRecipeTypesForItem($item),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedRecipeTypesForItem(Item $item): array
    {
        $allowedRecipeTypes = [];

        if ($item->is_manufacturable) {
            $allowedRecipeTypes[] = Recipe::TYPE_MANUFACTURING;
        }

        if ($item->is_sellable) {
            $allowedRecipeTypes[] = Recipe::TYPE_FULFILLMENT;
        }

        return $allowedRecipeTypes;
    }

    /**
     * Validate draft version payload.
     *
     * @return array<string, mixed>
     */
    private function validateVersionPayload(Request $request, Recipe $recipe): array
    {
        $validated = $request->validate([
            'recipe_type' => ['required', 'string', Rule::in(Recipe::allowedRecipeTypes())],
            'output_quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            'status' => ['nullable', 'string', Rule::in([
                RecipeVersion::STATUS_DRAFT,
                RecipeVersion::STATUS_PUBLISHED,
                RecipeVersion::STATUS_ARCHIVED,
                RecipeVersion::STATUS_APPROVED_LEGACY,
            ])],
            'source_version_id' => [
                'nullable',
                'integer',
                Rule::exists('recipe_versions', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        $outputItem = $recipe->item()->first();

        if ($outputItem && ($eligibilityError = Recipe::recipeTypeEligibilityError($outputItem, $validated['recipe_type']))) {
            throw new InvalidArgumentException($eligibilityError);
        }

        return $validated;
    }

    /**
     * Create a new draft version record by cloning a source version when available.
     */
    private function createRecipeVersionRecord(Recipe $recipe, array $validated): RecipeVersion
    {
        $sourceVersion = isset($validated['source_version_id'])
            ? $recipe->versions()->whereKey($validated['source_version_id'])->with('lines')->first()
            : $recipe->displayVersionForUser(0)?->loadMissing('lines');

        $version = $recipe->versions()->create([
            'tenant_id' => $recipe->tenant_id,
            'version_number' => $recipe->nextVersionNumber(),
            'name' => null,
            'output_quantity' => Recipe::normalizeOutputQuantityForType($validated['recipe_type'], $validated['output_quantity']),
            'recipe_type' => $validated['recipe_type'],
            'status' => RecipeVersion::STATUS_DRAFT,
            'effective_from' => null,
            'effective_until' => null,
            'approved_at' => null,
            'approved_by_user_id' => null,
            'notes' => null,
        ]);

        if ($sourceVersion) {
            foreach ($sourceVersion->lines as $line) {
                $version->lines()->create([
                    'tenant_id' => $version->tenant_id,
                    'input_item_id' => $line->input_item_id,
                    'uom_id' => $line->uom_id,
                    'quantity' => (string) $line->quantity,
                    'sort_order' => (int) $line->sort_order,
                ]);
            }
        }

        return $version;
    }

    /**
     * Clone one source version into a draft.
     */
    private function cloneVersionAsDraft(Recipe $recipe, RecipeVersion $sourceVersion): RecipeVersion
    {
        $sourceVersion->loadMissing('lines');

        $draftVersion = $recipe->versions()->create([
            'tenant_id' => $recipe->tenant_id,
            'version_number' => $recipe->nextVersionNumber(),
            'name' => null,
            'output_quantity' => (string) $sourceVersion->output_quantity,
            'recipe_type' => $sourceVersion->recipe_type,
            'status' => RecipeVersion::STATUS_DRAFT,
            'effective_from' => null,
            'effective_until' => null,
            'approved_at' => null,
            'approved_by_user_id' => null,
            'notes' => null,
        ]);

        foreach ($sourceVersion->lines as $line) {
            $draftVersion->lines()->create([
                'tenant_id' => $draftVersion->tenant_id,
                'input_item_id' => $line->input_item_id,
                'uom_id' => $line->uom_id,
                'quantity' => (string) $line->quantity,
                'sort_order' => (int) $line->sort_order,
            ]);
        }

        return $draftVersion;
    }

    /**
     * Create an open checkout record for one user and version.
     */
    private function openCheckout(Recipe $recipe, RecipeVersion $version, int $userId): RecipeVersionCheckout
    {
        return RecipeVersionCheckout::query()->create([
            'tenant_id' => $recipe->tenant_id,
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $version->id,
            'user_id' => $userId,
            'checked_out_at' => now(),
            'checked_in_at' => null,
        ]);
    }

    /**
     * Close any open checkout for the user and version.
     */
    private function closeOpenCheckout(RecipeVersion $version, int $userId): void
    {
        $version->checkouts()
            ->where('user_id', $userId)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => now()]);
    }

    /**
     * Close any open checkouts for the user across the same recipe.
     */
    private function closeOpenCheckoutsForRecipeUser(Recipe $recipe, int $userId): void
    {
        RecipeVersionCheckout::query()
            ->where('tenant_id', $recipe->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->where('user_id', $userId)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => now()]);
    }

    /**
     * Promote a published version to the next highest visible version number when needed.
     */
    private function nextPublishVersionNumber(Recipe $recipe, RecipeVersion $version): int
    {
        $currentVersionNumber = RecipeVersion::normalizeVersionNumberForOrdering((int) $version->version_number);
        $highestOtherVersionNumber = RecipeVersion::normalizeVersionNumberForOrdering((int) ($recipe->versions()
            ->where('tenant_id', $recipe->tenant_id)
            ->whereKeyNot($version->id)
            ->max('version_number') ?? 0));

        if ($currentVersionNumber > $highestOtherVersionNumber) {
            return (int) $version->version_number;
        }

        return $recipe->nextVersionNumber();
    }

    /**
     * Determine whether the user owns an open checkout for the version.
     */
    private function userOwnsOpenCheckout(Recipe $recipe, RecipeVersion $version, int $userId): bool
    {
        return RecipeVersionCheckout::query()
            ->where('tenant_id', $recipe->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->where('recipe_version_id', $version->id)
            ->where('user_id', $userId)
            ->whereNull('checked_in_at')
            ->exists();
    }

    /**
     * Determine whether a version is checked out by another user.
     */
    private function versionCheckedOutByAnotherUser(RecipeVersion $version, int $userId): bool
    {
        return RecipeVersionCheckout::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('recipe_version_id', $version->id)
            ->where('user_id', '!=', $userId)
            ->whereNull('checked_in_at')
            ->exists();
    }

    /**
     * Determine whether the same user already has this version checked out.
     */
    private function hasOpenCheckoutForVersionUser(RecipeVersion $version, int $userId): bool
    {
        return RecipeVersionCheckout::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('recipe_version_id', $version->id)
            ->where('user_id', $userId)
            ->whereNull('checked_in_at')
            ->exists();
    }

    /**
     * Resolve the open checkout record for one version and user.
     */
    private function openCheckoutRecordForVersionUser(RecipeVersion $version, int $userId): ?RecipeVersionCheckout
    {
        return RecipeVersionCheckout::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('recipe_version_id', $version->id)
            ->where('user_id', $userId)
            ->whereNull('checked_in_at')
            ->latest('checked_out_at')
            ->latest('id')
            ->first();
    }

    /**
     * Resolve an editable checked out version or return a response.
     */
    private function editableCheckedOutVersion(Request $request, Recipe $recipe, int $version): RecipeVersion|JsonResponse
    {
        $versionModel = $recipe->versions()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($version)
            ->with('lines.inputItem.baseUom')
            ->firstOrFail();

        if (! $this->userOwnsOpenCheckout($recipe, $versionModel, (int) $request->user()->id)) {
            abort(403);
        }

        if (RecipeVersion::normalizeStatus($versionModel->status) !== RecipeVersion::STATUS_DRAFT) {
            return $this->validationError([
                'recipe_version_id' => ['Only checked out draft versions can be edited.'],
            ], 'Only checked out draft versions can be edited.');
        }

        return $versionModel;
    }

    /**
     * Resolve or create an editable draft for legacy line endpoints.
     */
    private function resolveOrCreateEditableDraftForUser(Recipe $recipe, int $userId): RecipeVersion
    {
        $displayVersion = $recipe->displayVersionForUser($userId);

        if ($displayVersion && $this->userOwnsOpenCheckout($recipe, $displayVersion, $userId) && $displayVersion->isEditableLifecycle()) {
            return $displayVersion;
        }

        $sourceVersion = $recipe->currentPublishedVersion() ?? $recipe->versions()
            ->where('tenant_id', $recipe->tenant_id)
            ->where('status', '!=', RecipeVersion::STATUS_ARCHIVED)
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();

        if ($sourceVersion) {
            return DB::transaction(function () use ($recipe, $sourceVersion, $userId): RecipeVersion {
                $draftVersion = $this->cloneVersionAsDraft($recipe, $sourceVersion);
                $this->openCheckout($recipe, $draftVersion, $userId);

                return $draftVersion;
            });
        }

        return DB::transaction(function () use ($recipe, $userId): RecipeVersion {
            $draftVersion = $recipe->versions()->create([
                'tenant_id' => $recipe->tenant_id,
                'version_number' => $recipe->nextVersionNumber(),
                'name' => null,
                'output_quantity' => Recipe::normalizeOutputQuantityForType(
                    $recipe->recipe_type,
                    (string) $recipe->output_quantity
                ),
                'recipe_type' => $recipe->recipe_type,
                'status' => RecipeVersion::STATUS_DRAFT,
                'effective_from' => null,
                'effective_until' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'notes' => null,
            ]);

            $this->syncVersionLinesFromLegacyRecipeLines($recipe, $draftVersion);
            $this->openCheckout($recipe, $draftVersion, $userId);

            return $draftVersion->fresh(['lines.inputItem.baseUom', 'recipe.item.baseUom']);
        });
    }

    /**
     * Mirror the current published version onto legacy parent fields and lines.
     */
    private function syncRecipeMirror(Recipe $recipe, RecipeVersion $version): void
    {
        if (! $version->isPublished()) {
            return;
        }

        $recipe->recipe_type = $version->recipe_type;
        $recipe->output_quantity = (string) $version->output_quantity;
        $recipe->save();

        RecipeLine::query()
            ->where('tenant_id', $recipe->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->delete();

        $version->loadMissing('lines');

        foreach ($version->lines as $line) {
            RecipeLine::query()->create([
                'tenant_id' => $recipe->tenant_id,
                'recipe_id' => $recipe->id,
                'item_id' => $line->input_item_id,
                'quantity' => (string) $line->quantity,
            ]);
        }
    }

    /**
     * Keep legacy recipe_lines synchronized for transitional API compatibility.
     */
    private function syncVersionLinesFromLegacyRecipeLines(Recipe $recipe, RecipeVersion $version): void
    {
        RecipeVersionLine::query()
            ->where('tenant_id', $version->tenant_id)
            ->where('recipe_version_id', $version->id)
            ->delete();

        $legacyLines = RecipeLine::query()
            ->where('tenant_id', $recipe->tenant_id)
            ->where('recipe_id', $recipe->id)
            ->with('item')
            ->orderBy('id')
            ->get();

        foreach ($legacyLines as $index => $legacyLine) {
            RecipeVersionLine::query()->create([
                'tenant_id' => $version->tenant_id,
                'recipe_version_id' => $version->id,
                'input_item_id' => $legacyLine->item_id,
                'uom_id' => $legacyLine->item?->base_uom_id,
                'quantity' => (string) $legacyLine->quantity,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Compare versions using the requested versions ordering contract.
     */
    private function compareVersionRows(Recipe $recipe, int $userId, RecipeVersion $left, RecipeVersion $right): int
    {
        $leftRank = $this->versionOrderingRank($recipe, $userId, $left);
        $rightRank = $this->versionOrderingRank($recipe, $userId, $right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcmp(
            sprintf('%020d', RecipeVersion::normalizeVersionNumberForOrdering((int) $right->version_number)),
            sprintf('%020d', RecipeVersion::normalizeVersionNumberForOrdering((int) $left->version_number))
        );
    }

    /**
     * Resolve a sortable rank for the versions list.
     */
    private function versionOrderingRank(Recipe $recipe, int $userId, RecipeVersion $version): int
    {
        $normalizedStatus = RecipeVersion::normalizeStatus($version->status);

        if ((int) $recipe->current_version_id === (int) $version->id) {
            return 0;
        }

        if ($this->userOwnsOpenCheckout($recipe, $version, $userId)) {
            return 1;
        }

        if ($normalizedStatus === RecipeVersion::STATUS_ARCHIVED) {
            return 3;
        }

        return 2;
    }

    /**
     * Resolve the UI icon token for a recipe type.
     */
    private function recipeTypeIcon(?string $recipeType): ?string
    {
        if ($recipeType === null) {
            return null;
        }

        return $recipeType === Recipe::TYPE_FULFILLMENT ? 'shopping-cart' : 'cog';
    }

    /**
     * Normalize a quantity string to canonical scale 6 without float math.
     */
    private function normalizeQuantityString(string $quantity): string
    {
        return bcadd(trim($quantity), '0', self::SCALE);
    }

    /**
     * Resolve the visual tone for a lifecycle status.
     */
    private function versionStatusTone(string $status): string
    {
        return match ($status) {
            RecipeVersion::STATUS_PUBLISHED => 'success',
            RecipeVersion::STATUS_ARCHIVED => 'muted',
            default => 'default',
        };
    }

    /**
     * Resolve the display label for a recipe version lifecycle status.
     */
    private function versionStatusLabel(string $status): string
    {
        return match ($status) {
            RecipeVersion::STATUS_PUBLISHED => 'Published',
            RecipeVersion::STATUS_ARCHIVED => 'Archived',
            default => 'Draft',
        };
    }

    /**
     * Resolve the active recipe version for the detail header identity.
     */
    private function resolveRecipeDetailDisplayVersion(Recipe $recipe, int $userId): ?RecipeVersion
    {
        return $recipe->displayVersionForUser($userId) ?? $this->latestDisplayVersion($recipe);
    }

    /**
     * Build the active recipe version summary used by the detail header.
     *
     * @return array<string, mixed>|null
     */
    private function activeRecipeVersionSummary(
        Recipe $recipe,
        ?RecipeVersion $activeVersion,
        int $userId,
        bool $canManage,
        bool $canExecute
    ): ?array
    {
        if (! $activeVersion) {
            return null;
        }

        $row = $this->recipeVersionListRow($recipe, $activeVersion, $userId, $canManage, $canExecute);
        $normalizedStatus = RecipeVersion::normalizeStatus((string) $row['status']);
        $currentLabel = $this->activeRecipeVersionHeaderLabel($row, $normalizedStatus);

        return array_merge($row, [
            'status_label' => $currentLabel,
            'actions' => $this->recipeVersionHeaderMenuOptions(
                is_array($row['availableActions'] ?? null) ? $row['availableActions'] : [],
                $normalizedStatus,
                $row
            ),
            'header_menu' => [
                'currentLabel' => $currentLabel,
                'options' => $this->recipeVersionHeaderMenuOptions(
                    is_array($row['availableActions'] ?? null) ? $row['availableActions'] : [],
                    $normalizedStatus,
                    $row
                ),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recipeVersionSectionActions(): array
    {
        return [
            ['id' => 'view', 'label' => 'View', 'description' => 'Open this archived version read-only.', 'type' => 'custom', 'tone' => 'default', 'handlerKey' => 'viewVersion'],
            ['id' => 'make', 'label' => 'Make Order', 'description' => 'Create a make order from this published version.', 'type' => 'custom', 'tone' => 'default', 'handlerKey' => 'makeVersion'],
            ['id' => 'check_in', 'label' => 'Check In', 'description' => 'Finish editing this version.', 'type' => 'custom', 'tone' => 'default', 'handlerKey' => 'checkInVersion'],
            ['id' => 'checkout', 'label' => 'Check Out', 'description' => 'Open this version for editing.', 'type' => 'custom', 'tone' => 'default', 'handlerKey' => 'checkoutVersion'],
            ['id' => 'publish', 'label' => 'Publish', 'description' => 'Make this version current for new make orders.', 'type' => 'custom', 'tone' => 'default', 'handlerKey' => 'publishVersion'],
            ['id' => 'duplicate', 'label' => 'Duplicate', 'description' => 'Copy this version into a new draft.', 'type' => 'custom', 'tone' => 'default', 'handlerKey' => 'duplicateVersion'],
            ['id' => 'delete', 'label' => 'Delete', 'description' => 'Permanently remove this draft version.', 'type' => 'remove', 'tone' => 'warning', 'endpointKey' => 'remove', 'method' => 'DELETE'],
            ['id' => 'archive', 'label' => 'Archive', 'description' => 'Retire this published version from active use.', 'type' => 'custom', 'tone' => 'warning', 'handlerKey' => 'archiveVersion'],
        ];
    }

    /**
     * @param  array<int, string>  $availableActions
     * @return array<int, array<string, mixed>>
     */
    private function recipeVersionHeaderMenuOptions(array $availableActions, string $normalizedStatus, array $row): array
    {
        $catalog = collect($this->recipeVersionSectionActions());

        return collect($availableActions)
            ->map(function (string $actionId) use ($catalog, $normalizedStatus, $row): ?array {
                $action = $catalog->firstWhere('id', $actionId);

                if (! is_array($action)) {
                    return null;
                }

                return array_filter([
                    'id' => (string) ($action['id'] ?? $actionId),
                    'type' => (string) ($action['id'] ?? $actionId),
                    'label' => (string) $action['label'],
                    'description' => $this->recipeVersionHeaderMenuDescription(
                        $actionId,
                        (string) ($action['description'] ?? ''),
                        $normalizedStatus
                    ),
                    'tone' => (string) ($action['tone'] ?? 'default'),
                    'handlerKey' => $this->recipeVersionActionHandlerKey($actionId, $action),
                    'endpoint' => $this->recipeVersionActionEndpoint($actionId, $row),
                    'method' => $this->recipeVersionActionMethod($actionId, $action),
                    'requiresConfirmation' => (bool) ($action['requiresConfirmation'] ?? false),
                ], static fn ($value): bool => $value !== null && $value !== '');
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve the client-side handler key for a recipe version action.
     *
     * @param  array<string, mixed>  $action
     */
    private function recipeVersionActionHandlerKey(string $actionId, array $action): ?string
    {
        return (string) ($action['handlerKey'] ?? '') !== ''
            ? (string) $action['handlerKey']
            : null;
    }

    /**
     * Resolve the endpoint used by a recipe version action.
     *
     * @param  array<string, mixed>  $row
     */
    private function recipeVersionActionEndpoint(string $actionId, array $row): ?string
    {
        return match ($actionId) {
            'view' => null,
            'make' => (string) ($row['make_url'] ?? '') ?: null,
            'check_in' => (string) ($row['check_in_url'] ?? '') ?: null,
            'checkout' => (string) ($row['checkout_url'] ?? '') ?: null,
            'publish' => (string) ($row['publish_url'] ?? '') ?: null,
            'duplicate' => (string) ($row['duplicate_url'] ?? '') ?: null,
            'delete' => isset($row['recipe_id'], $row['id'])
                ? route('manufacturing.recipes.versions.destroy', [(int) $row['recipe_id'], (int) $row['id']])
                : null,
            'archive' => (string) ($row['archive_url'] ?? '') ?: null,
            default => null,
        };
    }

    /**
     * Resolve the HTTP method for a recipe version action.
     *
     * @param  array<string, mixed>  $action
     */
    private function recipeVersionActionMethod(string $actionId, array $action): string
    {
        return (string) ($action['method'] ?? match ($actionId) {
            'publish', 'archive' => 'PATCH',
            'view' => 'GET',
            default => 'POST',
        });
    }

    private function recipeVersionHeaderMenuDescription(
        string $actionId,
        string $defaultDescription,
        string $normalizedStatus
    ): string {
        if ($actionId === 'duplicate' && $normalizedStatus === RecipeVersion::STATUS_ARCHIVED) {
            return 'Copy this archived version into a new draft.';
        }

        return $defaultDescription;
    }

    /**
     * Determine whether a version has any ingredient lines.
     */
    private function recipeVersionHasIngredients(RecipeVersion $version): bool
    {
        if ($version->relationLoaded('lines')) {
            return $version->lines->isNotEmpty();
        }

        if (array_key_exists('lines_count', $version->getAttributes())) {
            return (int) $version->getAttribute('lines_count') > 0;
        }

        return $version->lines()->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function activeRecipeVersionHeaderLabel(array $row, string $normalizedStatus): string
    {
        if (($row['is_checked_out_by_user'] ?? false) === true) {
            return 'Checked-Out';
        }

        return $this->versionStatusLabel($normalizedStatus);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function recipeTypeOptions(): array
    {
        return collect(Recipe::recipeTypeLabels())
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<string, bool|int|null>
     */
    private function prefillCreatePayload(Request $request): array
    {
        $open = $request->boolean('open_create');
        $itemId = $request->query('item_id');

        if (! $open || ! is_numeric($itemId)) {
            return [
                'open' => false,
                'item_id' => null,
            ];
        }

        $item = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where(function ($query): void {
                $query->where('is_manufacturable', true)
                    ->orWhere('is_sellable', true);
            })
            ->find((int) $itemId);

        return [
            'open' => $item !== null,
            'item_id' => $item?->id,
        ];
    }

    /**
     * Build list rows for the shared CRUD page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recipeListRows(
        int $tenantId,
        string $search,
        string $sortColumn,
        string $direction,
        bool $canManage = false,
        bool $canExecute = false
    ): array
    {
        $rows = Recipe::query()
            ->where('tenant_id', $tenantId)
            ->with(['item.baseUom', 'currentVersion'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Recipe $recipe) use ($canManage, $canExecute): array {
                $row = $this->recipePayload($recipe, $canManage, $canExecute);

                $row['available_actions'] = array_values(array_filter(
                    $row['available_actions'] ?? [],
                    static fn (string $actionId): bool => $actionId !== 'view'
                ));

                return $row;
            })
            ->values()
            ->all();

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                $haystacks = [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['output_item_name'] ?? ''),
                    (string) ($row['current_version_number_display'] ?? ''),
                    (string) ($row['output_quantity'] ?? ''),
                    (string) ($row['updated_at'] ?? ''),
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
     * Compare two recipe rows for list sorting.
     */
    private function compareListRows(array $left, array $right, string $sortColumn): int
    {
        if ($sortColumn === 'output_quantity') {
            return bccomp(
                (string) ($left['output_quantity'] ?? '0.000000'),
                (string) ($right['output_quantity'] ?? '0.000000'),
                self::SCALE
            );
        }

        if ($sortColumn === 'current_version_number') {
            return RecipeVersion::normalizeVersionNumberForOrdering((int) ($left['current_version_number'] ?? 0))
                <=> RecipeVersion::normalizeVersionNumberForOrdering((int) ($right['current_version_number'] ?? 0));
        }

        return strcmp(
            mb_strtolower((string) ($left[$sortColumn] ?? '')),
            mb_strtolower((string) ($right[$sortColumn] ?? ''))
        );
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
     * @return never
     */
    private function throwValidationException(string $field, string $message): never
    {
        throw new InvalidArgumentException($field . '::' . $message);
    }

    /**
     * Handle known recipe validation exceptions.
     */
    private function handleRecipeException(InvalidArgumentException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        if (str_contains($message, '::')) {
            [$field, $error] = explode('::', $message, 2);

            return $this->validationError([$field => [$error]], $error);
        }

        if ($message === 'This version is already checked out by you.') {
            return $this->validationError([
                'recipe_version_id' => [$message],
            ], $message);
        }

        return $this->validationError([
            'recipe_type' => [$message],
        ], $message);
    }
}
