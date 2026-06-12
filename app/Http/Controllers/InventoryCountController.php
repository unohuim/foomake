<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AdvanceInventoryCountWorkflowStageAction;
use App\Actions\Notes\BuildNotesFeedPayloadAction;
use App\Actions\Workflows\BuildWorkflowProgressStepsAction;
use App\Actions\Workflows\CanViewAssignedWorkflowResourceAction;
use App\Actions\Workflows\ResolveInventoryWorkflowStageAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Support\QuantityFormatter;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InventoryCountController extends Controller
{
    /**
     * Display a listing of inventory counts.
     */
    public function index(Request $request): View
    {
        $this->authorizeInventoryCountsIndex();

        $users = app(WorkflowAssignmentPermissions::class)
            ->eligibleUsersQuery((int) $request->user()->tenant_id, 'inventory')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('inventory.counts.index', [
            'crudConfig' => $this->countsCrudConfig(),
            'payload' => [
                'csrfToken' => csrf_token(),
                'users' => $users
                    ->map(fn (User $user): array => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ])
                    ->values()
                    ->all(),
            ],
            'users' => $users,
        ]);
    }

    /**
     * Return the inventory counts list read model for the shared CRUD page module.
     */
    public function list(Request $request): JsonResponse
    {
        $this->authorizeInventoryCountsIndex();

        $crudConfig = $this->countsCrudConfig();
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $allowedSortColumns = $crudConfig['sortable'];
        $requestedSortColumn = (string) ($validated['sort'] ?? 'counted_at');
        $sortColumn = in_array($requestedSortColumn, $allowedSortColumns, true) ? $requestedSortColumn : 'counted_at';
        $direction = (string) ($validated['direction'] ?? 'desc');
        $counts = $this->countsQuery($request, $search, $sortColumn, $direction)->get();

        return response()->json([
            'data' => $counts
                ->map(fn (InventoryCount $count): array => $this->countListData($count))
                ->values()
                ->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $counts->count(),
            ],
        ]);
    }

    /**
     * Show a specific inventory count.
     */
    public function show(Request $request, int $inventoryCount): View
    {
        $count = $this->findInventoryCount($request, $inventoryCount);
        $this->authorizeInventoryCountView($request, $count);

        $count->load(['workflowStage', 'lines.item.baseUom', 'lines.uom']);
        $count->loadCount('lines');

        $items = $this->userCanMutateInventoryCountLines($request->user(), $count)
            ? $this->countLineSelectableItems($request, $count)
            : collect();

        $resolver = app(ResolveInventoryWorkflowStageAction::class);
        $this->ensureInventoryWorkflowStagesExist(
            $request,
            $resolver,
            app(SeedDefaultWorkflowStagesForTenantAction::class)
        );
        $previousStage = $this->previousWorkflowActionStage($count, $resolver);
        $nextStage = $this->nextWorkflowActionStage($count, $resolver);
        $canSubmitWorkflow = $this->userCanSubmitInventoryCountWorkflow($request->user(), $count);
        $canOperateWorkflow = $this->userCanOperateInventoryWorkflow($request->user());

        return view('inventory.counts.show', [
            'inventoryCount' => $count,
            'items' => $items,
            'notesFeed' => app(BuildNotesFeedPayloadAction::class)->execute(
                $count,
                route('inventory.counts.notes.index', $count),
                route('inventory.counts.notes.store', $count)
            ),
            'previousWorkflowActionLabel' => $canOperateWorkflow ? $this->workflowActionButtonText($previousStage) : null,
            'previousWorkflowActionEvent' => $canOperateWorkflow ? $this->previousWorkflowActionEvent($count, $previousStage) : null,
            'nextWorkflowActionLabel' => $this->canShowNextWorkflowAction($count, $canSubmitWorkflow, $canOperateWorkflow)
                ? $this->workflowActionButtonText($nextStage, $count)
                : null,
            'nextWorkflowActionEvent' => $this->canShowNextWorkflowAction($count, $canSubmitWorkflow, $canOperateWorkflow)
                ? $this->nextWorkflowActionEvent($count, $nextStage)
                : null,
            'payload' => [
                'count' => $this->countPayload($count, Gate::allows('inventory-adjustments-execute')),
                'workflow' => $this->inventoryWorkflowPayload($count, $canSubmitWorkflow, $canOperateWorkflow),
                'workflowProgressSteps' => app(BuildWorkflowProgressStepsAction::class)->execute(
                    (int) $request->user()->tenant_id,
                    'inventory',
                    $count->posted_at === null && $count->workflow_stage_id !== null
                        ? (int) $count->workflow_stage_id
                        : null,
                    null,
                    $count->workflow_stage_id === null ? null : (int) $count->workflow_stage_id,
                    $count->posted_at !== null
                ),
                'sections' => [
                    'countLines' => $this->countLinesSectionConfig($request, $count, $items),
                    'tasks' => $this->tasksSectionConfig($count),
                ],
                'taskCreate' => [
                    'users' => $this->manualTaskAssigneeOptions((int) $request->user()->tenant_id),
                    'workflowDomainId' => $this->workflowDomainId('inventory'),
                ],
            ],
        ]);
    }

    /**
     * Build tenant user options for manual task assignment.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function manualTaskAssigneeOptions(int $tenantId): array
    {
        return app(WorkflowAssignmentPermissions::class)
            ->eligibleUsersQuery($tenantId, 'inventory')
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
     * Store a new inventory count draft.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'counted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        if (
            isset($validated['assigned_to_user_id'])
            && ! $this->userCanBeAssignedToInventoryWorkflow((int) $validated['assigned_to_user_id'])
        ) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'assigned_to_user_id' => ['The selected user cannot be assigned to Inventory Counts.'],
                ],
            ], 422);
        }

        $count = DB::transaction(function () use ($request, $validated): InventoryCount {
            $count = InventoryCount::query()->forceCreate([
                'tenant_id' => $request->user()->tenant_id,
                'created_by_user_id' => $request->user()->id,
                'tasked_by_user_id' => $request->user()->id,
                'assigned_to_user_id' => isset($validated['assigned_to_user_id'])
                    ? (int) $validated['assigned_to_user_id']
                    : null,
                'name' => $validated['name'],
                'counted_at' => Carbon::parse($validated['counted_at']),
                'workflow_stage_id' => null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->createInitialNoteFromCountNotes($count, $request);

            return $count;
        });

        $count->load(['workflowStage', 'assignedToUser'])->loadCount('lines');

        return response()->json([
            'count' => $this->countPayload($count),
        ], 201);
    }

    /**
     * Create the initial Notes feed entry from non-blank create-form notes.
     */
    private function createInitialNoteFromCountNotes(InventoryCount $count, Request $request): void
    {
        $body = trim((string) ($count->notes ?? ''));

        if ($body === '') {
            return;
        }

        Note::query()->forceCreate([
            'tenant_id' => (int) $count->tenant_id,
            'noteable_type' => InventoryCount::class,
            'noteable_id' => (int) $count->id,
            'author_user_id' => (int) $request->user()->id,
            'body' => $body,
            'visibility' => 'internal',
            'is_pinned' => false,
        ]);
    }

    /**
     * Update an inventory count draft.
     */
    public function update(Request $request, int $inventoryCount): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);
        $this->authorizeInventoryCountView($request, $count);

        if ($response = $this->ensureEditableDetails($count)) {
            return $response;
        }

        $canEditMetadata = Gate::allows('inventory-adjustments-view');

        if (
            ! $canEditMetadata
            && ($request->has('counted_at') || $request->has('assigned_to_user_id'))
        ) {
            abort(403);
        }

        $validated = $request->validate($canEditMetadata
            ? [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'counted_at' => ['required', 'date'],
                'notes' => ['nullable', 'string'],
                'assigned_to_user_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id),
                ],
            ]
            : [
                'notes' => ['nullable', 'string'],
            ]);

        if (
            array_key_exists('assigned_to_user_id', $validated)
            && $validated['assigned_to_user_id'] !== null
            && ! $this->userCanBeAssignedToInventoryWorkflow((int) $validated['assigned_to_user_id'])
        ) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'assigned_to_user_id' => ['The selected user cannot be assigned to Inventory Counts.'],
                ],
            ], 422);
        }

        $count->notes = $validated['notes'] ?? null;

        if ($canEditMetadata && array_key_exists('name', $validated)) {
            $count->name = $validated['name'];
        }

        if ($canEditMetadata) {
            $count->counted_at = Carbon::parse($validated['counted_at']);
        }

        if ($canEditMetadata && array_key_exists('assigned_to_user_id', $validated)) {
            $count->assigned_to_user_id = $validated['assigned_to_user_id'] === null
                ? null
                : (int) $validated['assigned_to_user_id'];
            $count->tasked_by_user_id = $request->user()->id;
        }

        $count->save();
        $this->syncOpenCurrentStageTaskAssignment($count);

        $count->load(['workflowStage', 'assignedToUser'])->loadCount('lines');

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Cancel an inventory count before it is posted.
     */
    public function destroy(Request $request, int $inventoryCount): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        $count = $this->findInventoryCount($request, $inventoryCount);

        if (
            ! Schema::hasColumn('inventory_counts', 'workflow_cancelled_at')
            || ! Schema::hasColumn('inventory_counts', 'workflow_cancelled_by_user_id')
        ) {
            return response()->json([
                'message' => 'Inventory count cancellation is not installed on this database yet.',
            ], 422);
        }

        if ($count->posted_at !== null) {
            return response()->json([
                'message' => 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        if ($count->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => 'Inventory count is already cancelled.',
            ], 422);
        }

        $count->forceFill([
            'workflow_cancelled_at' => now(),
            'workflow_cancelled_by_user_id' => $request->user()?->id,
        ])->save();

        return response()->json([
            'cancelled' => true,
            'count' => $this->countPayload($count->fresh()),
            'workflow' => $this->inventoryWorkflowPayload(
                $count->fresh(),
                $this->userCanSubmitInventoryCountWorkflow($request->user(), $count->fresh()),
                $this->userCanOperateInventoryWorkflow($request->user())
            ),
        ]);
    }

    /**
     * Submit an inventory count into the configured inventory workflow.
     */
    public function submit(
        Request $request,
        int $inventoryCount,
        AdvanceInventoryCountWorkflowStageAction $action,
        ResolveInventoryWorkflowStageAction $resolver,
        SeedDefaultWorkflowStagesForTenantAction $seedDefaultStagesAction
    ): JsonResponse {
        $count = $this->findInventoryCount($request, $inventoryCount);
        if ($count->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => 'Inventory count is cancelled and cannot be modified.',
            ], 422);
        }
        abort_unless($this->userCanSubmitInventoryCountWorkflow($request->user(), $count), 403);
        $this->ensureInventoryWorkflowStagesExist($request, $resolver, $seedDefaultStagesAction);

        try {
            $count = $action->submit($count, (int) $request->user()->id);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Advance an inventory count through the configured inventory workflow.
     */
    public function advance(
        Request $request,
        int $inventoryCount,
        AdvanceInventoryCountWorkflowStageAction $action,
        ResolveInventoryWorkflowStageAction $resolver,
        SeedDefaultWorkflowStagesForTenantAction $seedDefaultStagesAction
    ): JsonResponse {
        abort_unless($this->userCanOperateInventoryWorkflow($request->user()), 403);

        $count = $this->findInventoryCount($request, $inventoryCount);
        if ($count->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => 'Inventory count is cancelled and cannot be modified.',
            ], 422);
        }
        $this->ensureInventoryWorkflowStagesExist($request, $resolver, $seedDefaultStagesAction);

        try {
            $count = $action->advance($count, (int) $request->user()->id);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Post an inventory count.
     */
    public function post(
        Request $request,
        int $inventoryCount,
        AdvanceInventoryCountWorkflowStageAction $action,
        ResolveInventoryWorkflowStageAction $resolver,
        SeedDefaultWorkflowStagesForTenantAction $seedDefaultStagesAction
    ): JsonResponse {
        abort_unless($this->userCanOperateInventoryWorkflow($request->user()), 403);

        $count = $this->findInventoryCount($request, $inventoryCount);
        if ($count->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => 'Inventory count is cancelled and cannot be modified.',
            ], 422);
        }
        $this->ensureInventoryWorkflowStagesExist($request, $resolver, $seedDefaultStagesAction);

        try {
            $count = $action->postCompatible($count, (int) $request->user()->id);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Move an inventory count back to the previous configured workflow stage.
     */
    public function previous(
        Request $request,
        int $inventoryCount,
        AdvanceInventoryCountWorkflowStageAction $action,
        ResolveInventoryWorkflowStageAction $resolver,
        SeedDefaultWorkflowStagesForTenantAction $seedDefaultStagesAction
    ): JsonResponse {
        abort_unless($this->userCanOperateInventoryWorkflow($request->user()), 403);

        $count = $this->findInventoryCount($request, $inventoryCount);
        if ($count->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => 'Inventory count is cancelled and cannot be modified.',
            ], 422);
        }
        $this->ensureInventoryWorkflowStagesExist($request, $resolver, $seedDefaultStagesAction);

        try {
            $count = $action->previous($count, (int) $request->user()->id);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'count' => $this->countPayload($count),
        ]);
    }

    /**
     * Return the inventory count lines list read model for the reusable detail CRUD section.
     */
    public function listLines(Request $request, int $inventoryCount): JsonResponse
    {
        $count = $this->findInventoryCount($request, $inventoryCount);
        $this->authorizeInventoryCountView($request, $count);
        $canEditCountedQuantity = $this->userCanMutateInventoryCountLines($request->user(), $count)
            && $this->ensureWorkflowStageCountedQuantityEditable($count) === null;
        $showsCountedQuantity = $count->workflow_stage_id !== null;
        $perPage = $this->perPageFromRequest($request);
        $paginator = $count->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['item.baseUom', 'uom'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (InventoryCountLine $line): array => $this->linePayload($line, $canEditCountedQuantity, $showsCountedQuantity))
                ->values()
                ->all(),
            'meta' => $this->sectionMeta($paginator),
        ]);
    }

    /**
     * Return the current-stage inventory count tasks list for the reusable detail section.
     */
    public function listTasks(Request $request, int $inventoryCount): JsonResponse
    {
        $count = $this->findInventoryCount($request, $inventoryCount);
        $this->authorizeInventoryCountView($request, $count);
        $count->load('workflowStage');

        $perPage = $this->perPageFromRequest($request);
        $workflowDomainId = $count->workflowStage?->workflow_domain_id
            ?? $this->workflowDomainId('inventory');

        if ($workflowDomainId === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        $paginator = Task::withoutGlobalScopes()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('workflow_domain_id', $workflowDomainId)
            ->where('domain_record_id', $count->id)
            ->where(function ($query) use ($count): void {
                $query->where('source', Task::SOURCE_MANUAL);

                if ($count->workflow_stage_id !== null) {
                    $query->orWhere(function ($query) use ($count): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $count->workflow_stage_id);
                    });
                }
            })
            ->with(['assignedTo', 'completedBy'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Task $task): array => $this->taskPayload($task, $request->user()))
                ->values()
                ->all(),
            'meta' => $this->sectionMeta($paginator),
        ]);
    }

    /**
     * Store a new inventory count line.
     */
    public function storeLine(Request $request, int $inventoryCount): JsonResponse
    {
        $count = $this->findInventoryCount($request, $inventoryCount);
        abort_unless($this->userCanMutateInventoryCountLines($request->user(), $count), 403);

        if ($response = $this->ensureEditableDraft($count)) {
            return $response;
        }

        $request->merge([
            'counted_quantity' => $this->normalizeOptionalQuantity($request->input('counted_quantity')),
        ]);

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
                Rule::unique('inventory_count_lines', 'item_id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('inventory_count_id', $count->id),
            ],
            'counted_quantity' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($validated['item_id'])
            ->firstOrFail();

        $line = InventoryCountLine::query()->forceCreate([
            'tenant_id' => $request->user()->tenant_id,
            'inventory_count_id' => $count->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'counted_quantity' => $validated['counted_quantity'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $line->load(['item.baseUom', 'uom']);

        return response()->json([
            'line' => $this->linePayload($line, false, false),
            'section' => $this->countLinesSectionConfig(
                $request,
                $count->fresh(['lines']),
                $this->countLineSelectableItems($request, $count->fresh(['lines']))
            ),
        ], 201);
    }

    /**
     * Update an inventory count line.
     */
    public function updateLine(Request $request, int $inventoryCount, int $line): JsonResponse
    {
        $count = $this->findInventoryCount($request, $inventoryCount);
        abort_unless($this->userCanMutateInventoryCountLines($request->user(), $count), 403);
        $lineModel = $count->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        if (! $request->has('item_id') && $request->exists('counted_quantity')) {
            if ($response = $this->ensureWorkflowStageCountedQuantityEditable($count)) {
                return $response;
            }

            $request->merge([
                'counted_quantity' => $this->normalizeOptionalQuantity($request->input('counted_quantity')),
            ]);

            $validated = $request->validate([
                'counted_quantity' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            ]);

            $lineModel->counted_quantity = $validated['counted_quantity'] ?? null;
            $lineModel->save();
            $lineModel->load(['item.baseUom', 'uom']);

            return response()->json([
                'line' => $this->linePayload($lineModel, true, true),
            ]);
        }

        if ($response = $this->ensureEditableLines($count)) {
            return $response;
        }

        $request->merge([
            'counted_quantity' => $this->normalizeOptionalQuantity($request->input('counted_quantity')),
        ]);

        $validated = $request->validate([
            'item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('tenant_id', $request->user()->tenant_id),
                Rule::unique('inventory_count_lines', 'item_id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('inventory_count_id', $count->id)
                    ->ignore($lineModel->id),
            ],
            'counted_quantity' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($validated['item_id'])
            ->firstOrFail();

        $lineModel->item_id = $item->id;
        $lineModel->uom_id = $item->base_uom_id;
        $lineModel->counted_quantity = $validated['counted_quantity'] ?? null;
        $lineModel->notes = $validated['notes'] ?? null;
        $lineModel->save();

        $lineModel->load(['item.baseUom', 'uom']);

        $canEditCountedQuantity = $this->ensureWorkflowStageCountedQuantityEditable($count) === null;
        $showsCountedQuantity = $count->workflow_stage_id !== null;

        return response()->json([
            'line' => $this->linePayload($lineModel, $canEditCountedQuantity, $showsCountedQuantity),
        ]);
    }

    /**
     * Delete an inventory count line.
     */
    public function destroyLine(Request $request, int $inventoryCount, int $line): JsonResponse
    {
        $count = $this->findInventoryCount($request, $inventoryCount);
        abort_unless($this->userCanMutateInventoryCountLines($request->user(), $count), 403);

        if ($response = $this->ensureRemovableLines($count)) {
            return $response;
        }

        $lineModel = $count->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($line)
            ->firstOrFail();

        $deletedLineId = $lineModel->id;
        $lineModel->delete();

        $remainingLines = $count->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['item.baseUom', 'uom'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (InventoryCountLine $remainingLine): array => $this->linePayload($remainingLine, false, false))
            ->values()
            ->all();

        return response()->json([
            'deleted' => true,
            'deleted_line_id' => $deletedLineId,
            'lines' => $remainingLines,
            'section' => $this->countLinesSectionConfig(
                $request,
                $count->fresh(['lines']),
                $this->countLineSelectableItems($request, $count->fresh(['lines']))
            ),
        ]);
    }

    /**
     * Find a tenant-scoped inventory count or fail.
     */
    private function findInventoryCount(Request $request, int $inventoryCount): InventoryCount
    {
        return InventoryCount::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($inventoryCount);
    }

    /**
     * Authorize read access for an inventory count detail resource.
     */
    private function authorizeInventoryCountView(Request $request, InventoryCount $inventoryCount): void
    {
        if (Gate::allows('inventory-adjustments-view')) {
            return;
        }

        abort_unless(
            app(CanViewAssignedWorkflowResourceAction::class)->execute(
                $request->user(),
                $inventoryCount,
                'inventory',
                $inventoryCount->assigned_to_user_id
            ),
            403
        );
    }

    /**
     * Authorize inventory count index access.
     */
    private function authorizeInventoryCountsIndex(): void
    {
        abort_unless(
            Gate::allows('inventory-adjustments-view') || Gate::allows('inventory-adjustments-execute'),
            403
        );
    }

    /**
     * Determine whether the user may submit a draft Inventory Count into workflow.
     */
    private function userCanSubmitInventoryCountWorkflow(User $user, InventoryCount $inventoryCount): bool
    {
        return Gate::forUser($user)->allows('inventory-adjustments-execute')
            && (
                $this->userCanOperateInventoryWorkflow($user)
                || (int) $inventoryCount->created_by_user_id === (int) $user->id
                || (int) $inventoryCount->tasked_by_user_id === (int) $user->id
                || app(CanViewAssignedWorkflowResourceAction::class)->execute(
                    $user,
                    $inventoryCount,
                    'inventory',
                    $inventoryCount->assigned_to_user_id
                )
            );
    }

    /**
     * Determine whether the user may complete, reverse, or post Inventory Count workflow stages.
     */
    private function userCanOperateInventoryWorkflow(User $user): bool
    {
        return app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($user, 'inventory');
    }

    /**
     * Determine whether the visible next action should be rendered.
     */
    private function canShowNextWorkflowAction(
        InventoryCount $inventoryCount,
        bool $canSubmitWorkflow,
        bool $canOperateWorkflow
    ): bool {
        if ($inventoryCount->posted_at !== null || $inventoryCount->workflow_cancelled_at !== null) {
            return false;
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return $canSubmitWorkflow;
        }

        return $canOperateWorkflow;
    }

    /**
     * Determine whether the user may mutate Inventory Count material lines.
     */
    private function userCanMutateInventoryCountLines(User $user, InventoryCount $inventoryCount): bool
    {
        return $this->userCanSubmitInventoryCountWorkflow($user, $inventoryCount);
    }

    /**
     * Ensure the inventory count is still in draft setup and editable.
     */
    private function ensureEditableDraft(InventoryCount $inventoryCount): ?JsonResponse
    {
        if ($inventoryCount->posted_at !== null || $inventoryCount->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => $inventoryCount->workflow_cancelled_at !== null
                    ? 'Inventory count is cancelled and cannot be modified.'
                    : 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        if ($inventoryCount->workflow_stage_id !== null) {
            return response()->json([
                'message' => 'Inventory count has been submitted and cannot be modified.',
            ], 422);
        }

        return null;
    }

    /**
     * Ensure inventory count detail metadata can still be updated.
     */
    private function ensureEditableDetails(InventoryCount $inventoryCount): ?JsonResponse
    {
        if (
            $inventoryCount->posted_at !== null
            || $inventoryCount->workflow_cancelled_at !== null
            || $inventoryCount->workflowStage?->is_inventory_effect_stage
        ) {
            return response()->json([
                'message' => $inventoryCount->workflow_cancelled_at !== null
                    ? 'Inventory count is cancelled and cannot be modified.'
                    : 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        return null;
    }

    /**
     * Ensure the inventory count still allows count-line mutations.
     */
    private function ensureEditableLines(InventoryCount $inventoryCount): ?JsonResponse
    {
        if ($inventoryCount->posted_at !== null || $inventoryCount->workflow_cancelled_at !== null) {
            return response()->json([
                'message' => $inventoryCount->workflow_cancelled_at !== null
                    ? 'Inventory count is cancelled and cannot be modified.'
                    : 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        if ($inventoryCount->workflowStage?->is_inventory_effect_stage) {
            return response()->json([
                'message' => 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        return null;
    }

    /**
     * Ensure inventory count lines can still be removed from this count.
     */
    private function ensureRemovableLines(InventoryCount $inventoryCount): ?JsonResponse
    {
        if (
            $inventoryCount->posted_at !== null
            || $inventoryCount->workflow_cancelled_at !== null
            || $inventoryCount->workflowStage?->is_inventory_effect_stage
        ) {
            return response()->json([
                'message' => $inventoryCount->workflow_cancelled_at !== null
                    ? 'Inventory count is cancelled and materials can no longer be removed.'
                    : 'Inventory count is posted and materials can no longer be removed.',
            ], 422);
        }

        if ($inventoryCount->workflow_stage_id !== null) {
            return response()->json([
                'message' => 'Inventory count has been submitted and materials can no longer be removed.',
            ], 422);
        }

        return null;
    }

    /**
     * Ensure counted quantities can still be updated inline for this count.
     */
    private function ensureWorkflowStageCountedQuantityEditable(InventoryCount $inventoryCount): ?JsonResponse
    {
        if (
            $inventoryCount->posted_at !== null
            || $inventoryCount->workflow_cancelled_at !== null
            || $inventoryCount->workflowStage?->is_inventory_effect_stage
        ) {
            return response()->json([
                'message' => $inventoryCount->workflow_cancelled_at !== null
                    ? 'Inventory count is cancelled and cannot be modified.'
                    : 'Inventory count is posted and cannot be modified.',
            ], 422);
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return response()->json([
                'message' => 'Inventory count is still in draft and counted quantity can be updated after submission.',
            ], 422);
        }

        return null;
    }

    /**
     * Seed inventory workflow stages only when the tenant has not configured any yet.
     */
    private function ensureInventoryWorkflowStagesExist(
        Request $request,
        ResolveInventoryWorkflowStageAction $resolver,
        SeedDefaultWorkflowStagesForTenantAction $seedDefaultStagesAction
    ): void {
        $inventoryDomainId = $resolver->inventoryDomainId();

        $hasStages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', (int) $request->user()->tenant_id)
            ->where('workflow_domain_id', $inventoryDomainId)
            ->exists();

        if (! $hasStages) {
            $seedDefaultStagesAction->execute($request->user()->tenant()->firstOrFail());
        }
    }

    /**
     * Build JSON payload for inventory counts.
     */
    private function countPayload(InventoryCount $inventoryCount, bool $canExecute = true): array
    {
        $inventoryCount->loadMissing(['workflowStage', 'assignedToUser']);
        $inventoryCount->loadCount('lines');
        $showDetailsSection = Gate::allows('inventory-adjustments-view');
        $canEditNotes = $canExecute && $this->ensureEditableDetails($inventoryCount) === null;
        $canEditMetadata = $canEditNotes && $showDetailsSection;
        $canViewMetadata = $showDetailsSection;
        $isCancelled = $inventoryCount->workflow_cancelled_at !== null;

        return [
            'id' => $inventoryCount->id,
            'name' => $inventoryCount->name,
            'counted_at' => $inventoryCount->counted_at->format('F j, Y'),
            'counted_at_iso' => $inventoryCount->counted_at->format('Y-m-d'),
            'notes' => $inventoryCount->notes ?? '',
            'can_edit_details' => $canEditMetadata,
            'can_edit_notes' => $canEditNotes,
            'can_view_counted_at' => $canViewMetadata,
            'can_view_assignment' => $canViewMetadata,
            'can_edit_counted_at' => $canEditMetadata,
            'can_edit_assignment' => $canEditMetadata,
            'show_details_section' => $showDetailsSection,
            'assignee_options' => $canViewMetadata
                ? $this->tenantAssigneeOptionsPayload((int) $inventoryCount->tenant_id, $inventoryCount->assignedToUser)
                : [],
            'status' => $inventoryCount->status,
            'lifecycle_status_label' => $isCancelled
                ? 'Cancelled'
                : ($inventoryCount->status === 'posted' ? 'Posted' : 'Draft'),
            'created_by_user_id' => $inventoryCount->created_by_user_id,
            'tasked_by_user_id' => $inventoryCount->tasked_by_user_id,
            'assigned_to_user_id' => $inventoryCount->assigned_to_user_id,
            'assigned_to_user_name' => $inventoryCount->assignedToUser?->name,
            'workflow_stage_id' => $inventoryCount->workflow_stage_id,
            'workflow_stage_key' => $inventoryCount->workflowStage?->key,
            'workflow_stage_name' => $inventoryCount->workflowStage?->name,
            'display_label' => $this->workflowStatusLabel($inventoryCount),
            'status_label' => $this->workflowStatusLabel($inventoryCount),
            'currentLabel' => $this->workflowStatusLabel($inventoryCount),
            'workflow_status_label' => $this->workflowStatusLabel($inventoryCount),
            'is_draft_setup' => $this->isDraftSetup($inventoryCount),
            'is_cancelled' => $isCancelled,
            'is_submitted' => $inventoryCount->workflow_stage_id !== null && $inventoryCount->posted_at === null,
            'posted_at_display' => $isCancelled
                ? 'Cancelled'
                : $inventoryCount->posted_at?->format('F j, Y'),
            'posted_at_iso' => $inventoryCount->posted_at?->format('Y-m-d'),
            'lines_count' => $inventoryCount->lines_count,
            'current_stage_tasks' => $this->currentStageTasksData($inventoryCount),
            'show_url' => route('inventory.counts.show', $inventoryCount),
            'index_url' => route('inventory.counts.index'),
            'update_url' => route('inventory.counts.update', $inventoryCount),
            'delete_url' => route('inventory.counts.destroy', $inventoryCount),
            'previous_url' => route('inventory.counts.previous', $inventoryCount),
            'submit_url' => route('inventory.counts.submit', $inventoryCount),
            'advance_url' => route('inventory.counts.advance', $inventoryCount),
            'post_url' => route('inventory.counts.post', $inventoryCount),
        ];
    }

    /**
     * Build tenant-scoped assignee options for detail assignment controls.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function tenantAssigneeOptionsPayload(int $tenantId, ?User $selectedUser = null): array
    {
        $eligibleUsers = app(WorkflowAssignmentPermissions::class)
            ->eligibleUsersQuery($tenantId, 'inventory')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if (
            $selectedUser !== null
            && (int) $selectedUser->tenant_id === $tenantId
            && ! $eligibleUsers->contains(fn (User $user): bool => (int) $user->id === (int) $selectedUser->id)
        ) {
            $eligibleUsers->push($selectedUser);
        }

        return $eligibleUsers
            ->sortBy([
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Determine whether the selected user has minimum visibility for Inventory Count assignment.
     */
    private function userCanBeAssignedToInventoryWorkflow(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null
            && app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain($user, 'inventory');
    }

    /**
     * Keep open generated tasks aligned with the current Inventory Count assignee.
     */
    private function syncOpenCurrentStageTaskAssignment(InventoryCount $inventoryCount): void
    {
        if ($inventoryCount->workflow_stage_id === null || $inventoryCount->assigned_to_user_id === null) {
            return;
        }

        $inventoryCount->loadMissing('workflowStage');

        Task::withoutGlobalScopes()
            ->where('tenant_id', $inventoryCount->tenant_id)
            ->where('workflow_domain_id', $inventoryCount->workflowStage?->workflow_domain_id)
            ->where('domain_record_id', $inventoryCount->id)
            ->where('workflow_stage_id', $inventoryCount->workflow_stage_id)
            ->where('status', Task::STATUS_OPEN)
            ->update([
                'assigned_to_user_id' => $inventoryCount->assigned_to_user_id,
            ]);
    }

    /**
     * Build the filtered and sorted inventory counts query shared by the index page.
     */
    private function countsQuery(Request $request, string $search, string $sortColumn, string $direction)
    {
        $query = InventoryCount::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['workflowStage', 'assignedToUser'])
            ->withCount('lines');

        if (! Gate::allows('inventory-adjustments-view')) {
            $inventoryDomainId = WorkflowDomain::query()
                ->where('key', 'inventory')
                ->value('id');

            $query->where(function ($assignedQuery) use ($request, $inventoryDomainId): void {
                $assignedQuery->where('assigned_to_user_id', $request->user()->id);

                if ($inventoryDomainId !== null) {
                    $assignedQuery->orWhereIn('id', function ($taskQuery) use ($request, $inventoryDomainId): void {
                        $taskQuery->select('domain_record_id')
                            ->from('tasks')
                            ->where('tenant_id', $request->user()->tenant_id)
                            ->where('workflow_domain_id', $inventoryDomainId)
                            ->where('assigned_to_user_id', $request->user()->id);
                    });
                }
            });
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%');

                if (ctype_digit($search)) {
                    $builder->orWhereKey((int) $search);
                }
            });
        }

        return match ($sortColumn) {
            'name' => $query
                ->orderBy('name', $direction)
                ->orderByDesc('counted_at'),
            'status' => $query
                ->orderByRaw('CASE WHEN posted_at IS NULL THEN 0 ELSE 1 END ' . $direction)
                ->orderByDesc('counted_at'),
            'lines_count' => $query
                ->orderBy('lines_count', $direction)
                ->orderByDesc('counted_at'),
            'posted_at' => $query
                ->orderByRaw('posted_at IS NULL')
                ->orderBy('posted_at', $direction)
                ->orderByDesc('counted_at'),
            default => $query
                ->orderBy('counted_at', $direction)
                ->orderByDesc('id'),
        };
    }

    /**
     * Build the JSON list row for the shared inventory counts CRUD renderer.
     *
     * @return array<string, mixed>
     */
    private function countListData(InventoryCount $inventoryCount): array
    {
        return [
            'id' => $inventoryCount->id,
            'name' => $inventoryCount->name,
            'counted_at' => $inventoryCount->counted_at->format('F j, Y'),
            'counted_at_iso' => $inventoryCount->counted_at->format('Y-m-d'),
            'notes' => $inventoryCount->notes ?? '',
            'status' => $inventoryCount->status,
            'status_label' => $this->workflowStatusLabel($inventoryCount),
            'lifecycle_status' => $inventoryCount->status,
            'assigned_to_user_id' => $inventoryCount->assigned_to_user_id,
            'counter_name' => $inventoryCount->assignedToUser?->name,
            'counter_email' => $inventoryCount->assignedToUser?->email,
            'posted_at' => $inventoryCount->posted_at?->format('F j, Y') ?? '—',
            'posted_at_iso' => $inventoryCount->posted_at?->format('Y-m-d'),
            'lines_count' => $inventoryCount->lines_count ?? 0,
            'show_url' => route('inventory.counts.show', $inventoryCount),
            'update_url' => route('inventory.counts.update', $inventoryCount),
            'delete_url' => route('inventory.counts.destroy', $inventoryCount),
            'submit_url' => route('inventory.counts.submit', $inventoryCount),
            'advance_url' => route('inventory.counts.advance', $inventoryCount),
            'post_url' => route('inventory.counts.post', $inventoryCount),
        ];
    }

    /**
     * Return the shared CRUD config for the inventory counts index page module.
     *
     * @return array<string, mixed>
     */
    private function countsCrudConfig(): array
    {
        $canManageCounts = Gate::allows('inventory-adjustments-view')
            && Gate::allows('inventory-adjustments-execute');

        return [
            'resource' => 'inventory-counts',
            'endpoints' => [
                'list' => route('inventory.counts.list'),
                'create' => route('inventory.counts.store'),
                'update' => url('/inventory/counts/{id}'),
                'delete' => url('/inventory/counts/{id}'),
            ],
            'detailUrlTemplate' => url('/inventory/counts/{id}'),
            'columns' => ['name', 'counted_at', 'status', 'counter', 'lines_count', 'posted_at'],
            'headers' => [
                'name' => 'Name',
                'counted_at' => 'Counted At',
                'status' => 'Status',
                'counter' => 'Assigned',
                'lines_count' => 'Items',
                'posted_at' => 'Posted At',
            ],
            'sortable' => ['name', 'counted_at', 'status', 'lines_count', 'posted_at'],
            'labels' => [
                'searchPlaceholder' => 'Search inventory counts',
                'createTitle' => 'Create Inventory Count',
                'createAriaLabel' => 'Create Inventory Count',
                'emptyState' => 'No inventory counts found.',
                'actionsAriaLabel' => 'Inventory count actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => $canManageCounts,
            ],
            'rowDisplay' => [
                'columns' => [
                    'name' => [
                        'kind' => 'linked-text',
                        'urlExpression' => 'record.show_url',
                    ],
                    'counted_at' => [
                        'kind' => 'text',
                    ],
                    'status' => ['kind' => 'text'],
                    'counter' => ['kind' => 'text'],
                    'lines_count' => ['kind' => 'text'],
                    'posted_at' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.name || '—'",
                'titleAsideExpression' => "record.counted_at || '—'",
                'titleAsidePlacement' => 'top-right',
                'titleBadgesExpression' => 'inventoryCountStatusBadges(record)',
                'subtitleExpression' => "record.counter_name || record.counter_email || '—'",
                'bodyExpression' => '',
                'urlExpression' => 'record.show_url',
            ],
            'desktopCard' => [
                'titleExpression' => "record.name || '—'",
                'titleAsideExpression' => "record.counted_at || '—'",
                'subtitleExpression' => "record.counter_name || record.counter_email || '—'",
                'bodyExpression' => '',
                'badgesExpression' => 'inventoryCountStatusBadges(record)',
                'statsExpression' => 'inventoryCountCardStats(record)',
                'urlExpression' => 'record.show_url',
            ],
            'actions' => [],
        ];
    }

    /**
     * Build JSON payload for inventory count lines.
     */
    private function linePayload(
        InventoryCountLine $line,
        bool $canEditCountedQuantity = false,
        bool $showsCountedQuantity = false
    ): array
    {
        $lineUom = $line->snapshotUom() ?? $line->uom ?? $line->item?->baseUom;

        return [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_display' => $line->item->name . ' (' . $lineUom?->symbol . ')',
            'counted_quantity' => $line->counted_quantity,
            'counted_quantity_display' => $line->counted_quantity === null
                ? '—'
                : QuantityFormatter::formatForUom($line->counted_quantity, $lineUom),
            'counted_quantity_input' => $line->counted_quantity === null
                ? ''
                : QuantityFormatter::formatForUom($line->counted_quantity, $lineUom),
            'uom_display_precision' => (int) ($lineUom?->display_precision ?? QuantityFormatter::MAX_PRECISION),
            'can_edit_counted_quantity' => $canEditCountedQuantity,
            'shows_counted_quantity' => $showsCountedQuantity,
            'notes' => $line->notes ?? '',
            'notes_display' => $line->notes ?: '',
            'update_url' => route('inventory.counts.lines.update', [
                'inventoryCount' => $line->inventory_count_id,
                'line' => $line->id,
            ]),
            'delete_url' => route('inventory.counts.lines.destroy', [
                'inventoryCount' => $line->inventory_count_id,
                'line' => $line->id,
            ]),
        ];
    }

    /**
     * Build the reusable CRUD section config for the count lines section.
     *
     * @param \Illuminate\Support\Collection<int, Item> $items
     * @return array<string, mixed>
     */
    private function countLinesSectionConfig(Request $request, InventoryCount $inventoryCount, $items): array
    {
        $canManage = $this->userCanMutateInventoryCountLines($request->user(), $inventoryCount);
        $canRemoveLines = $canManage
            && $inventoryCount->workflow_cancelled_at === null
            && $inventoryCount->posted_at === null
            && $inventoryCount->workflow_stage_id === null;
        $canAddLines = $canManage
            && $inventoryCount->workflow_cancelled_at === null
            && $inventoryCount->posted_at === null
            && $inventoryCount->workflow_stage_id === null;
        $showsCountedQuantity = $inventoryCount->workflow_stage_id !== null;

        return [
            'resource' => 'inventory-count-lines',
            'title' => 'Materials',
            'description' => 'Manage counted materials for this inventory count.',
            'emptyState' => 'No count lines added yet.',
            'recordClass' => 'rounded-xl sm:rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 sm:px-4 sm:py-3.5',
            'rowClass' => 'flex flex-row items-center justify-between gap-3',
            'rightMetaClass' => 'flex shrink-0 items-end justify-center text-right',
            'csrfToken' => csrf_token(),
            'defaultOpen' => true,
            'showRowActionsMenu' => false,
            'permissions' => [
                'canCreate' => false,
            ],
            'addRow' => [
                'enabled' => $canAddLines,
                'type' => 'combobox-add',
                'fieldName' => 'item_id',
                'placeholder' => 'Search materials',
                'noResultsText' => 'No materials found.',
                'options' => $items->map(fn (Item $item): array => [
                    'value' => (string) $item->id,
                    'label' => $item->name . ' (' . $item->baseUom?->symbol . ')',
                    'description' => $item->baseUom?->name ?? '',
                ])->values()->all(),
                'action' => [
                    'handlerKey' => 'addSelectedCountLine',
                    'ariaLabel' => 'Add material',
                ],
            ],
            'endpoints' => [
                'list' => route('inventory.counts.lines.index', $inventoryCount),
                'create' => route('inventory.counts.lines.store', $inventoryCount),
                'update' => url('/inventory/counts/' . $inventoryCount->id . '/lines/{id}'),
                'remove' => url('/inventory/counts/' . $inventoryCount->id . '/lines/{id}'),
            ],
            'fields' => [
                [
                    'name' => 'item_id',
                    'label' => 'Material',
                    'type' => 'select',
                    'required' => true,
                    'options' => $items->map(fn (Item $item): array => [
                        'value' => (string) $item->id,
                        'label' => $item->name . ' (' . $item->baseUom?->symbol . ')',
                    ])->values()->all(),
                ],
                [
                    'name' => 'counted_quantity',
                    'label' => 'Counted Quantity',
                    'type' => 'text',
                    'required' => false,
                ],
                [
                    'name' => 'notes',
                    'label' => 'Notes',
                    'type' => 'text',
                    'required' => false,
                ],
            ],
            'actions' => $canRemoveLines ? [
                [
                    'id' => 'remove',
                    'label' => 'Remove',
                    'ariaLabel' => 'Remove material line',
                    'type' => 'custom',
                    'tone' => 'warning',
                    'handlerKey' => 'removeCountLine',
                    'icon' => 'x-mark',
                ],
            ] : [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'item_display',
                ],
                'secondaryFields' => [],
                'badges' => [],
                'rightMeta' => $showsCountedQuantity ? [
                    [
                        'label' => 'QTY',
                        'field' => 'counted_quantity_input',
                        'strong' => true,
                        'compactOnMobile' => true,
                    ],
                ] : [],
            ],
        ];
    }

    /**
     * Build the selectable item list for draft inventory count Materials comboboxes.
     *
     * @return \Illuminate\Support\Collection<int, Item>
     */
    private function countLineSelectableItems(Request $request, InventoryCount $inventoryCount)
    {
        $existingItemIds = $inventoryCount->lines()
            ->where('tenant_id', $request->user()->tenant_id)
            ->pluck('item_id');

        return Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when(
                $existingItemIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $existingItemIds->all())
            )
            ->with('baseUom')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Build the reusable detail section config for current-stage tasks.
     *
     * @return array<string, mixed>
     */
    private function tasksSectionConfig(InventoryCount $inventoryCount): array
    {
        return [
            'resource' => 'inventory-count-tasks',
            'title' => 'Tasks',
            'description' => 'Complete required workflow tasks before moving the inventory count forward.',
            'emptyState' => 'No tasks for the current stage.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => true,
            'initialRecords' => $this->currentStageTasksData($inventoryCount),
            'permissions' => [
                'canCreate' => false,
            ],
            'showRowActionsMenu' => false,
            'endpoints' => [
                'list' => route('inventory.counts.tasks.index', $inventoryCount),
                'create' => '',
                'update' => '',
                'remove' => '',
            ],
            'fields' => [],
            'actions' => [
                [
                    'id' => 'complete',
                    'label' => 'Complete',
                    'type' => 'custom',
                    'tone' => 'default',
                    'handlerKey' => 'completeTask',
                ],
            ],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'title',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Assigned By',
                        'field' => 'assigned_by_user_name',
                        'fallback' => '—',
                    ],
                    [
                        'label' => 'Assigned To',
                        'field' => 'assigned_to_display',
                        'fallback' => '',
                    ],
                    [
                        'label' => 'Completed By',
                        'field' => 'completed_by_display',
                        'fallback' => '',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'status',
                        'toneField' => 'status_tone',
                    ],
                ],
                'rightMeta' => [],
            ],
        ];
    }

    /**
     * Build section pagination meta for the shared detail CRUD component.
     *
     * @return array<string, int>
     */
    private function sectionMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
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
     * Determine whether a count is still in draft setup outside workflow stages.
     */
    private function isDraftSetup(InventoryCount $inventoryCount): bool
    {
        return $inventoryCount->workflow_stage_id === null && $inventoryCount->posted_at === null;
    }

    /**
     * Return the workflow-facing status label for index/detail presentation.
     */
    private function workflowStatusLabel(InventoryCount $inventoryCount): string
    {
        if ($inventoryCount->workflow_cancelled_at !== null) {
            return 'CANCELLED';
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return $inventoryCount->posted_at !== null ? 'COMPLETED' : 'Draft';
        }

        $resolver = app(ResolveInventoryWorkflowStageAction::class);
        $currentStage = $resolver->currentStage($inventoryCount);

        if (! $currentStage) {
            return 'Unknown';
        }

        if ($inventoryCount->posted_at !== null) {
            return $currentStage->status_complete_label ?: $currentStage->name;
        }

        $previousStage = $resolver->previousActiveStage($inventoryCount);

        if ($previousStage) {
            return $previousStage->status_complete_label ?: $previousStage->name;
        }

        return $currentStage->status_complete_label ?: $currentStage->name ?: 'Draft';
    }

    /**
     * Build the shared workflow action-button payload for an inventory count.
     *
     * @return array<string, mixed>
     */
    private function inventoryWorkflowPayload(
        InventoryCount $inventoryCount,
        bool $canSubmitWorkflow,
        bool $canOperateWorkflow
    ): array {
        $resolver = app(ResolveInventoryWorkflowStageAction::class);
        $currentStage = $resolver->currentStage($inventoryCount);
        $nextStage = $this->nextWorkflowActionStage($inventoryCount, $resolver);
        $actions = [];

        if ($inventoryCount->workflow_cancelled_at === null) {
            if ($inventoryCount->posted_at === null) {
                $actions[] = [
                    'id' => 'cancel',
                    'type' => 'cancel',
                    'label' => 'Cancel',
                    'description' => 'Cancel this inventory count before it is posted.',
                    'endpoint' => route('inventory.counts.destroy', $inventoryCount),
                    'method' => 'DELETE',
                    'requiresConfirmation' => true,
                ];
            }

            if ($this->canShowNextWorkflowAction($inventoryCount, $canSubmitWorkflow, $canOperateWorkflow) && $nextStage !== null) {
                $isInitialWorkflowAction = $inventoryCount->workflow_stage_id === null;

                $actions[] = [
                    'id' => 'next',
                    'type' => $inventoryCount->workflow_stage_id === null ? 'submit' : 'advance',
                    'label' => $isInitialWorkflowAction
                        ? 'Submit'
                        : $this->workflowActionButtonText($nextStage, $inventoryCount),
                    'description' => $this->workflowActionDescription(
                        $nextStage,
                        $isInitialWorkflowAction
                            ? 'Submit this inventory count into workflow.'
                            : 'Advance this inventory count to the next workflow stage.'
                    ),
                    'endpoint' => $inventoryCount->workflow_stage_id === null
                        ? route('inventory.counts.submit', $inventoryCount)
                        : route('inventory.counts.advance', $inventoryCount),
                    'method' => 'POST',
                ];
            }
        }

        $currentLabel = $this->workflowStatusLabel($inventoryCount);

        return [
            'status' => $inventoryCount->status,
            'status_label' => $currentLabel,
            'display_label' => $currentLabel,
            'currentLabel' => $currentLabel,
            'current_stage_label' => $currentLabel,
            'current_stage' => $currentStage ? [
                'id' => (int) $currentStage->id,
                'workflow_domain_id' => (int) $currentStage->workflow_domain_id,
                'key' => $currentStage->key,
                'name' => $currentStage->name,
                'action_verb' => $currentStage->action_verb,
                'status_complete_label' => $currentStage->status_complete_label,
                'description' => $currentStage->description,
            ] : null,
            'actions' => $actions,
            'header_menu' => [
                'currentLabel' => $currentLabel,
                'options' => $actions,
            ],
            'previous_url' => route('inventory.counts.previous', $inventoryCount),
            'submit_url' => route('inventory.counts.submit', $inventoryCount),
            'advance_url' => route('inventory.counts.advance', $inventoryCount),
            'post_url' => route('inventory.counts.post', $inventoryCount),
        ];
    }

    /**
     * Resolve the next workflow stage the visible action should enter.
     */
    private function nextWorkflowActionStage(
        InventoryCount $inventoryCount,
        ResolveInventoryWorkflowStageAction $resolver
    ): ?WorkflowStage {
        if ($inventoryCount->posted_at !== null) {
            return null;
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return $resolver->firstActiveStage($inventoryCount);
        }

        $currentStage = $resolver->currentStage($inventoryCount);

        if ($currentStage?->is_inventory_effect_stage) {
            return $currentStage;
        }

        return $resolver->nextActiveStage($inventoryCount);
    }

    /**
     * Resolve the previous workflow stage the visible action may move back to.
     */
    private function previousWorkflowActionStage(
        InventoryCount $inventoryCount,
        ResolveInventoryWorkflowStageAction $resolver
    ): ?WorkflowStage {
        if ($inventoryCount->posted_at !== null || $inventoryCount->workflow_stage_id === null) {
            return null;
        }

        return $resolver->previousActiveStage($inventoryCount);
    }

    /**
     * Resolve the visible next workflow button event name.
     */
    private function nextWorkflowActionEvent(InventoryCount $inventoryCount, ?WorkflowStage $targetStage): ?string
    {
        if ($targetStage === null || $inventoryCount->posted_at !== null) {
            return null;
        }

        return $inventoryCount->workflow_stage_id === null
            ? 'inventory-count-submit'
            : 'inventory-count-advance';
    }

    /**
     * Resolve the visible previous workflow button event name.
     */
    private function previousWorkflowActionEvent(InventoryCount $inventoryCount, ?WorkflowStage $targetStage): ?string
    {
        if ($targetStage === null || $inventoryCount->posted_at !== null || $inventoryCount->workflow_stage_id === null) {
            return null;
        }

        return 'inventory-count-previous';
    }

    /**
     * Resolve the visible action-button text for a workflow stage target.
     */
    private function workflowActionButtonText(
        ?WorkflowStage $workflowStage,
        ?InventoryCount $inventoryCount = null
    ): ?string
    {
        if (! $workflowStage) {
            return null;
        }

        if ($inventoryCount !== null && $inventoryCount->workflow_stage_id === null) {
            return 'Submit';
        }

        if (
            $inventoryCount !== null
            && $inventoryCount->posted_at === null
            && $inventoryCount->workflow_stage_id !== null
            && (int) $inventoryCount->workflow_stage_id === (int) $workflowStage->id
            && $workflowStage->is_inventory_effect_stage
        ) {
            return 'Complete';
        }

        return $workflowStage->action_verb
            ?: $workflowStage->name;
    }

    /**
     * Resolve helper copy for an inventory workflow action.
     */
    private function workflowActionDescription(WorkflowStage $workflowStage, string $fallback): string
    {
        return filled($workflowStage->description)
            ? (string) $workflowStage->description
            : $fallback;
    }

    /**
     * Normalize optional counted quantity inputs so blank setup values become null.
     */
    private function normalizeOptionalQuantity(mixed $quantity): mixed
    {
        if ($quantity === null) {
            return null;
        }

        if (! is_string($quantity)) {
            return $quantity;
        }

        $trimmed = trim($quantity);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Build current-stage task payloads for the detail page.
     *
     * @return array<int, array<string, int|string|bool|null>>
     */
    private function currentStageTasksData(InventoryCount $inventoryCount): array
    {
        $inventoryCount->loadMissing('workflowStage');
        $workflowDomainId = $inventoryCount->workflowStage?->workflow_domain_id
            ?? $this->workflowDomainId('inventory');

        if ($workflowDomainId === null) {
            return [];
        }

        return Task::withoutGlobalScopes()
            ->where('tenant_id', $inventoryCount->tenant_id)
            ->where('workflow_domain_id', $workflowDomainId)
            ->where('domain_record_id', $inventoryCount->id)
            ->where(function ($query) use ($inventoryCount): void {
                $query->where('source', Task::SOURCE_MANUAL);

                if ($inventoryCount->workflow_stage_id !== null) {
                    $query->orWhere(function ($query) use ($inventoryCount): void {
                        $query->where('source', Task::SOURCE_GENERATED)
                            ->where('workflow_stage_id', $inventoryCount->workflow_stage_id);
                    });
                }
            })
            ->with(['assignedTo', 'completedBy'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskPayload($task, auth()->user()))
            ->values()
            ->all();
    }

    /**
     * Resolve a workflow domain id by key.
     */
    private function workflowDomainId(string $key): ?int
    {
        $id = WorkflowDomain::query()
            ->where('key', $key)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Build the shared task payload contract used by the detail page and task list endpoint.
     *
     * @return array<string, int|string|bool|null|array<int, string>>
     */
    private function taskPayload(Task $task, ?User $viewer): array
    {
        $isCompleted = $task->isCompleted() || $task->completed_at !== null;
        $inventoryCount = InventoryCount::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $task->tenant_id)
            ->find($task->domain_record_id);
        $viewerUserId = $viewer?->id;
        $canCompleteWorkflowTask = $viewer !== null
            && app(WorkflowAssignmentPermissions::class)->userCanBeAssignedToDomain($viewer, 'inventory');
        $canComplete = ! $isCompleted
            && $viewerUserId !== null
            && $canCompleteWorkflowTask
            && $inventoryCount?->workflow_cancelled_at === null
            && (
                (int) $task->assigned_to_user_id === (int) $viewerUserId
                || (int) $inventoryCount?->assigned_to_user_id === (int) $viewerUserId
            );

        return [
            'id' => $task->id,
            'source' => $task->source,
            'workflow_stage_id' => $task->workflow_stage_id,
            'workflow_task_template_id' => $task->workflow_task_template_id,
            'assigned_to_user_id' => $task->assigned_to_user_id,
            'assigned_to_user_name' => $task->assignedTo?->name,
            'assigned_by_user_name' => $inventoryCount?->createdByUser?->name
                ?? $inventoryCount?->taskedByUser?->name,
            'assigned_to_display' => $isCompleted ? '' : ($task->assignedTo?->name ?? '—'),
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'sort_order' => $task->sort_order,
            'status' => $task->status,
            'status_tone' => $isCompleted ? 'success' : 'muted',
            'is_completed' => $isCompleted,
            'can_complete' => $canComplete,
            'completed_at' => $task->completed_at?->toISOString(),
            'completed_by_user_id' => $task->completed_by_user_id,
            'completed_by_user_name' => $task->completedBy?->name,
            'completed_by_display' => $isCompleted ? ($task->completedBy?->name ?? '—') : '',
            'complete_url' => route('tasks.complete', $task),
            'available_actions' => $canComplete ? ['complete'] : [],
            'availableActions' => $canComplete ? ['complete'] : [],
        ];
    }
}
