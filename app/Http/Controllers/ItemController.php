<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AdvanceInventoryCountWorkflowStageAction;
use App\Actions\Inventory\BuildMaterialInventoryStatsAction;
use App\Actions\Inventory\CanConvertInventoryBalancesToUomAction;
use App\Actions\Workflows\ResolveInventoryWorkflowStageAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Uom;
use App\Models\User;
use App\Support\QuantityFormatter;
use App\Support\Purchasing\SupplierPackageFormConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    private const QUANTITY_SCALE = 6;

    /**
     * Display a material (item) detail page.
     *
     * @param Request $request
     * @param Item $item
     * @return View|JsonResponse
     */
    public function show(Request $request, Item $item): View|JsonResponse
    {
        Gate::authorize('inventory-materials-view');

        $item->load('baseUom');

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'base_uom_id' => $item->base_uom_id,
                    'is_stockable' => $item->is_stockable,
                    'default_price_amount' => $this->formatCentsToAmount($item->default_price_cents),
                    'default_price_currency_code' => $item->default_price_currency_code,
                ],
            ]);
        }

        $payload = $this->materialDetailPayload($request, $item);

        return view('materials.show', [
            'item' => $item,
            'payload' => $payload,
        ]);
    }

    /**
     * Store a newly created material (item).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        if (!Uom::query()->exists()) {
            return response()->json([
                'message' => 'No units of measure exist.',
                'errors' => [
                    'base_uom_id' => ['No units of measure exist.'],
                ],
            ], 422);
        }

        $this->normalizeDefaultPriceInputs($request);
        $this->normalizeStartingQuantityInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => [
                'required',
                'integer',
                Rule::exists('uoms', 'id')->where(function ($query) use ($request): void {
                    $query->where('tenant_id', $request->user()->tenant_id)
                        ->orWhereNull('tenant_id');
                }),
            ],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
            'is_stockable' => ['nullable', 'boolean'],
            'default_price_amount' => ['nullable', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'default_price_currency_code' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
            'starting_quantity' => ['nullable', 'string', 'regex:/^\\d+(?:\\.\\d{1,6})?$/'],
        ]);

        $defaultPriceData = $this->resolveDefaultPriceData($request, null);
        $startingQuantity = $this->normalizeStartingQuantity($validated['starting_quantity'] ?? null);

        if ($startingQuantity !== null && ! $request->boolean('is_stockable')) {
            return response()->json([
                'message' => 'Starting quantity is only allowed for stockable materials.',
                'errors' => [
                    'starting_quantity' => ['Starting quantity is only allowed for stockable materials.'],
                ],
            ], 422);
        }

        $item = DB::transaction(function () use ($request, $validated, $defaultPriceData, $startingQuantity): Item {
            $item = Item::query()->create(array_merge([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $validated['name'],
                'base_uom_id' => $validated['base_uom_id'],
                'is_purchasable' => $request->boolean('is_purchasable'),
                'is_sellable' => $request->boolean('is_sellable'),
                'is_manufacturable' => $request->boolean('is_manufacturable'),
                'is_stockable' => $request->boolean('is_stockable'),
            ], $defaultPriceData));

            if (
                $item->is_stockable
                && $startingQuantity !== null
                && bccomp($startingQuantity, '0.000000', self::QUANTITY_SCALE) === 1
            ) {
                $this->createCompletedStartingInventoryCount($request, $item, $startingQuantity);
            }

            return $item;
        });

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
                'is_active' => $item->is_active,
                'is_stockable' => $item->is_stockable,
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
                'default_price_amount' => $this->formatCentsToAmount($item->default_price_cents),
                'default_price_currency_code' => $item->default_price_currency_code,
            ],
        ], 201);
    }

    /**
     * Update an existing material (item).
     *
     * @param Request $request
     * @param Item $item
     * @return JsonResponse
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $this->normalizeDefaultPriceInputs($request);
        $this->normalizeStartingQuantityInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => [
                'required',
                'integer',
                Rule::exists('uoms', 'id')->where(function ($query) use ($request): void {
                    $query->where('tenant_id', $request->user()->tenant_id)
                        ->orWhereNull('tenant_id');
                }),
            ],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
            'is_stockable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'default_price_amount' => ['nullable', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'default_price_currency_code' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        $baseUomId = (int) $validated['base_uom_id'];

        if ($baseUomId !== (int) $item->base_uom_id && ! $this->canChangeBaseUom($item, $baseUomId)) {
            return response()->json([
                'message' => 'Base unit of measure cannot be changed.',
                'errors' => [
                    'base_uom_id' => ['Existing inventory cannot be converted to the selected base unit of measure.'],
                ],
            ], 422);
        }

        $updateData = [
            'name' => $validated['name'],
            'base_uom_id' => $baseUomId,
        ];

        $flagFields = ['is_active', 'is_stockable', 'is_purchasable', 'is_sellable', 'is_manufacturable'];

        foreach ($flagFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->boolean($field);
            }
        }

        $defaultPriceData = $this->resolveDefaultPriceData($request, $item);
        $updateData = array_merge($updateData, $defaultPriceData);

        $item->update($updateData);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
                'is_active' => $item->is_active,
                'is_stockable' => $item->is_stockable,
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
                'has_stock_moves' => $item->stockMoves()->exists(),
                'default_price_amount' => $this->formatCentsToAmount($item->default_price_cents),
                'default_price_currency_code' => $item->default_price_currency_code,
                'material_detail' => $this->materialDetailPayload($request, $item->fresh('baseUom')),
            ],
        ]);
    }

    /**
     * Delete a material (item).
     *
     * @param Item $item
     * @return JsonResponse
     */
    public function destroy(Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        if ($item->stockMoves()->exists()) {
            return response()->json([
                'message' => 'Material cannot be deleted because stock moves exist.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Determine whether existing inventory can be represented in a new base UoM.
     */
    private function canChangeBaseUom(Item $item, int $baseUomId): bool
    {
        $targetUom = Uom::query()->whereKey($baseUomId)->first();

        if ($targetUom === null) {
            return false;
        }

        return app(CanConvertInventoryBalancesToUomAction::class)->execute($item, $targetUom);
    }

    /**
     * Build the Material detail payload used by the initial page and live updates.
     *
     * @return array<string, mixed>
     */
    private function materialDetailPayload(Request $request, Item $item): array
    {
        $canViewPurchasing = Gate::allows('purchasing-suppliers-view');
        $canManagePurchasing = Gate::allows('purchasing-suppliers-manage');
        $canViewPurchaseOrders = Gate::allows('purchasing-purchase-orders-create');
        $canViewRecipes = Gate::allows('inventory-recipes-view');
        $canViewMakeOrders = Gate::allows('inventory-make-orders-view');
        $canManageRecipes = Gate::allows('inventory-make-orders-manage');
        $canExecuteMakeOrders = Gate::allows('inventory-make-orders-execute');
        $canViewInventoryCounts = Gate::allows('inventory-adjustments-view');
        $canCreateInventoryCounts = Gate::allows('inventory-adjustments-execute');
        $canCreatePurchaseOrdersFromPackages = $canViewPurchasing
            && Gate::allows('purchasing-purchase-orders-create');

        return [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
                'base_uom_name' => $item->baseUom?->name,
                'base_uom_symbol' => $item->baseUom?->symbol,
                'uom_options' => $this->materialUomOptions($request, $item),
                'is_stockable' => (bool) $item->is_stockable,
                'is_purchasable' => (bool) $item->is_purchasable,
                'is_sellable' => (bool) $item->is_sellable,
                'is_manufacturable' => (bool) $item->is_manufacturable,
                'can_manage' => Gate::allows('inventory-materials-manage'),
                'can_toggle_types' => Gate::allows('inventory-materials-manage'),
                'update_url' => route('materials.update', $item),
                'csrf_token' => $request->session()->token(),
            ],
            'inventoryStats' => $item->is_stockable
                ? app(BuildMaterialInventoryStatsAction::class)->execute($item)
                : null,
            'tenantCurrency' => strtoupper($this->resolveTenantCurrency($request)),
            'navigationStateUrl' => route('navigation.state'),
            'taskCreate' => [
                'users' => $this->manualTaskAssigneeOptions((int) $request->user()->tenant_id),
            ],
            'canViewPurchasing' => $canViewPurchasing,
            'purchaseOrderCreate' => $canCreatePurchaseOrdersFromPackages && $item->is_purchasable
                ? $this->purchaseOrderCreateConfig($request, $item)
                : null,
            'recipeCreate' => $canViewRecipes && $item->is_manufacturable
                ? $this->recipeCreateConfig($request, $item, $canManageRecipes)
                : null,
            'inventoryCountCreate' => $item->is_stockable && $canViewInventoryCounts && $canCreateInventoryCounts
                ? $this->inventoryCountCreateConfig($request, $item)
                : null,
            'makeOrderCreate' => $canViewMakeOrders && $item->is_manufacturable
                ? $this->makeOrderCreateConfig($request, $item, $canExecuteMakeOrders)
                : null,
            'sections' => [
                'supplierPackages' => $canViewPurchasing && $item->is_purchasable
                    ? $this->supplierPackagesSectionConfig($request, $item, $canManagePurchasing)
                    : null,
                'recipes' => $canViewRecipes && $item->is_manufacturable
                    ? $this->recipesSectionConfig($item, $canManageRecipes, $canExecuteMakeOrders)
                    : null,
                'inventoryCounts' => $item->is_stockable && $canViewInventoryCounts
                    ? $this->inventoryCountsSectionConfig($request, $item)
                    : null,
                'purchaseOrders' => $canViewPurchaseOrders && $item->is_purchasable
                    ? $this->purchaseOrdersSectionConfig($item)
                    : null,
                'makeOrders' => $canViewMakeOrders && $item->is_manufacturable
                    ? $this->makeOrdersSectionConfig($item)
                    : null,
            ],
        ];
    }

    /**
     * Build tenant user options for manual task assignment.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function manualTaskAssigneeOptions(int $tenantId): array
    {
        return User::query()
            ->where('tenant_id', $tenantId)
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
     * Build tenant UoM options for the material base UoM dropdown.
     *
     * @return array<int, array{id: int, name: string, symbol: string}>
     */
    private function materialUomOptions(Request $request, Item $item): array
    {
        return Uom::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'symbol'])
            ->map(fn (Uom $uom): array => [
                'id' => (int) $uom->id,
                'name' => (string) $uom->name,
                'symbol' => (string) $uom->symbol,
            ])
            ->all();
    }

    /**
     * List inventory count rows scoped to one stockable material.
     */
    public function listInventoryCounts(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('inventory-adjustments-view');

        abort_unless($item->is_stockable, 404);

        $perPage = $this->perPageFromRequest($request);

        $paginator = InventoryCountLine::query()
            ->where('inventory_count_lines.tenant_id', $request->user()->tenant_id)
            ->where('inventory_count_lines.item_id', $item->id)
            ->with(['inventoryCount.assignedToUser', 'inventoryCount.workflowStage', 'item.baseUom', 'uom'])
            ->join('inventory_counts', 'inventory_counts.id', '=', 'inventory_count_lines.inventory_count_id')
            ->orderByDesc('inventory_counts.counted_at')
            ->orderByDesc('inventory_count_lines.id')
            ->select('inventory_count_lines.*')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (InventoryCountLine $line): array => $this->materialInventoryCountRowPayload($line))
                ->values()
                ->all(),
            'meta' => $this->sectionMeta($paginator),
        ]);
    }

    /**
     * Create an inventory count prefilled for the current stockable material.
     */
    public function storeInventoryCount(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-adjustments-execute');

        abort_unless($item->is_stockable, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'counted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'counted_quantity' => ['nullable', 'string', 'regex:/^\\d+(?:\\.\\d{1,6})?$/'],
        ]);

        $count = DB::transaction(function () use ($request, $item, $validated): InventoryCount {
            $count = InventoryCount::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'created_by_user_id' => $request->user()->id,
                'tasked_by_user_id' => $request->user()->id,
                'assigned_to_user_id' => isset($validated['assigned_to_user_id'])
                    ? (int) $validated['assigned_to_user_id']
                    : null,
                'name' => $validated['name'],
                'counted_at' => Carbon::parse((string) $validated['counted_at']),
                'workflow_stage_id' => null,
                'notes' => $validated['notes'] ?? null,
            ]);

            InventoryCountLine::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'inventory_count_id' => $count->id,
                'item_id' => $item->id,
                'uom_id' => $item->base_uom_id,
                'counted_quantity' => $validated['counted_quantity'] ?? null,
                'notes' => null,
            ]);

            return $count->fresh(['assignedToUser']);
        });

        return response()->json([
            'count' => [
                'id' => $count->id,
                'name' => $count->name,
                'show_url' => route('inventory.counts.show', $count),
            ],
        ], 201);
    }

    /**
     * Normalize and resolve default price data based on request input.
     *
     * @param Request $request
     * @param Item|null $item
     * @return array<string, int|string|null>
     */
    private function resolveDefaultPriceData(Request $request, ?Item $item): array
    {
        $payload = $request->all();
        $amountKeyExists = array_key_exists('default_price_amount', $payload);
        $currencyKeyExists = array_key_exists('default_price_currency_code', $payload);

        if (! $amountKeyExists && ! $currencyKeyExists) {
            return $item ? [] : [
                'default_price_cents' => null,
                'default_price_currency_code' => null,
            ];
        }

        $amountValue = $payload['default_price_amount'] ?? null;
        $currencyValue = $payload['default_price_currency_code'] ?? null;

        if ($amountValue === null || $amountValue === '') {
            return [
                'default_price_cents' => null,
                'default_price_currency_code' => null,
            ];
        }

        $normalizedCents = $this->normalizeAmountToCents((string) $amountValue);
        $currencyCode = null;

        if ($currencyValue !== null && $currencyValue !== '') {
            $currencyCode = strtoupper((string) $currencyValue);
        } else {
            $currencyCode = strtoupper($this->resolveTenantCurrency($request));
        }

        return [
            'default_price_cents' => $normalizedCents,
            'default_price_currency_code' => $currencyCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPackagesSectionConfig(Request $request, Item $item, bool $canManagePurchasing): array
    {
        $formConfig = new SupplierPackageFormConfig();

        return [
            'resource' => 'supplier-packages',
            'title' => 'Supplier Packages',
            'description' => 'Linked purchasing options for this material.',
            'emptyState' => 'No supplier packages have been added for this material.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => true,
            'permissions' => [
                'canCreate' => $canManagePurchasing,
            ],
            'showRowActionsMenu' => false,
            'inlineActionsOnMobile' => true,
            'recordClass' => 'rounded-lg border border-gray-200 bg-gray-50 px-3 py-1 sm:px-4 sm:py-1',
            'createAction' => $formConfig->createAction(),
            'endpoints' => [
                'list' => route('materials.supplier-packages.index', $item),
                'create' => route('materials.supplier-packages.store', $item),
                'update' => url("/materials/{$item->id}/supplier-packages/{id}"),
                'remove' => url("/materials/{$item->id}/supplier-packages/{id}"),
                'conversionCreate' => route('manufacturing.uom-conversions.items.store'),
            ],
            'fields' => $formConfig->fieldsForMaterial($request, $canManagePurchasing),
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.primaryText',
                    'fallback' => 'Unknown supplier',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Package',
                        'field' => 'display.packageText',
                        'hideLabelOnMobile' => true,
                        'compactOnMobile' => true,
                        'fallback' => '—',
                    ],
                    [
                        'label' => 'SKU',
                        'field' => 'display.skuText',
                        'hideLabelOnMobile' => true,
                        'compactOnMobile' => true,
                        'fallback' => '',
                    ],
                    [
                        'label' => 'Price',
                        'field' => 'price_amount',
                        'suffixField' => 'current_price_currency_code',
                        'hideLabelOnMobile' => true,
                        'compactOnMobile' => true,
                        'mobilePlacement' => 'primary-end',
                        'fallback' => 'No price',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'fallback' => '',
                    ],
                    [
                        'field' => 'display.versionText',
                        'toneField' => 'display.versionTone',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [],
            ],
            'actions' => [
                [
                    'id' => 'purchase',
                    'label' => 'Purchase',
                    'ariaLabel' => 'Purchase supplier package',
                    'tooltip' => 'Purchase Order',
                    'type' => 'custom',
                    'tone' => 'default',
                    'icon' => 'credit-card',
                    'handlerKey' => 'purchase',
                ],
                [
                    'id' => 'archive',
                    'label' => 'Archive',
                    'ariaLabel' => 'Archive supplier package',
                    'tooltip' => 'Archive',
                    'confirmMessage' => 'Are you sure you want to archive this supplier package?',
                    'type' => 'archive',
                    'tone' => 'warning',
                    'icon' => 'x-mark',
                    'endpointKey' => 'remove',
                    'method' => 'DELETE',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrdersSectionConfig(Item $item): array
    {
        return [
            'resource' => 'material-purchase-orders',
            'title' => 'Purchase Orders',
            'description' => 'Purchase orders that include this material.',
            'emptyState' => 'No purchase orders include this material yet.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => false,
            'permissions' => [
                'canCreate' => false,
            ],
            'showRowActionsMenu' => false,
            'recordClass' => 'rounded-lg border border-gray-200 bg-gray-50 px-3 py-1 sm:px-4 sm:py-1',
            'rowClass' => 'flex flex-row items-start justify-between gap-4',
            'rightMetaClass' => 'flex min-h-[3.25rem] min-w-[5rem] flex-col items-end justify-between gap-4 self-stretch text-right',
            'mobileRowUrlField' => 'display.showUrl',
            'secondaryFieldsClass' => 'mt-px flex flex-wrap items-center gap-x-3 gap-y-1 sm:mt-1.5',
            'endpoints' => [
                'list' => route('materials.purchase-orders.index', $item),
            ],
            'fields' => [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.poNumberText',
                    'urlField' => 'display.showUrl',
                    'linkClass' => 'truncate text-sm font-semibold text-gray-900 transition hover:text-gray-700',
                    'fallback' => 'Draft PO',
                ],
                'secondaryFields' => [
                    [
                        'label' => '',
                        'field' => 'display.supplierText',
                        'fallback' => 'Supplier not set',
                        'textClass' => 'text-xs leading-none text-gray-600',
                    ],
                    [
                        'label' => '',
                        'field' => 'display.materialQuantityCostText',
                        'fallback' => '',
                        'textClass' => 'mt-0.5 text-xs leading-none text-gray-600 sm:whitespace-nowrap',
                        'fullWidth' => true,
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'textClass' => 'text-[0.55rem] uppercase tracking-wide',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [
                    [
                        'label' => '',
                        'field' => 'display.orderDateText',
                        'fallback' => '',
                        'textClass' => 'text-xs font-medium text-gray-500',
                    ],
                    [
                        'label' => '',
                        'field' => 'display.materialLineTotalAmountText',
                        'suffixField' => 'display.materialLineTotalCurrencyText',
                        'fallback' => '—',
                        'strong' => true,
                        'textClass' => 'text-sm font-semibold text-gray-900',
                        'suffixClass' => 'ml-1 text-[0.65rem] font-medium text-gray-500',
                    ],
                ],
            ],
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderCreateConfig(Request $request, Item $item): array
    {
        $options = \App\Models\ItemPurchaseOption::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->whereNotNull('supplier_id')
            ->with(['supplier', 'packUom', 'currentPrice'])
            ->whereHas('supplier')
            ->orderBy('id')
            ->get();

        $suppliers = $options
            ->map(fn (\App\Models\ItemPurchaseOption $option): ?array => $option->supplier
                ? [
                    'id' => $option->supplier->id,
                    'name' => $option->supplier->company_name,
                ]
                : null)
            ->filter()
            ->unique('id')
            ->values()
            ->all();

        $packages = $options
            ->map(function (\App\Models\ItemPurchaseOption $option) use ($item): array {
                $quantity = bcadd((string) $option->pack_quantity, '0', 6);
                $symbol = $option->packUom?->symbol;
                $label = $symbol
                    ? sprintf('%s (%s %s)', $option->supplier?->company_name, $quantity, $symbol)
                    : sprintf('%s (%s)', $option->supplier?->company_name, $quantity);

                return [
                    'id' => $option->id,
                    'supplier_id' => $option->supplier_id,
                    'supplier_name' => $option->supplier?->company_name,
                    'item_id' => $option->item_id,
                    'item_name' => $item->name,
                    'label' => $label,
                    'current_price_cents' => $option->currentPrice?->converted_price_cents ?? 0,
                ];
            })
            ->values()
            ->all();

        return [
            'storeUrl' => route('materials.purchase-orders.store', $item),
            'csrfToken' => csrf_token(),
            'suppliers' => $suppliers,
            'packages' => $packages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipesSectionConfig(Item $item, bool $canManageRecipes, bool $canExecuteMakeOrders): array
    {
        $actions = [
            [
                'id' => 'view',
                'label' => 'View',
                'type' => 'view',
                'tone' => 'default',
                'urlField' => 'display.showUrl',
            ],
        ];

        if ($canExecuteMakeOrders) {
            $actions[] = [
                'id' => 'make',
                'label' => 'Make',
                'type' => 'custom',
                'tone' => 'default',
                'handlerKey' => 'createMakeOrder',
            ];
        }

        return [
            'resource' => 'material-recipes',
            'title' => 'Recipes',
            'description' => 'Recipes that produce this material.',
            'emptyState' => 'No recipes produce this material yet.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => true,
            'permissions' => [
                'canCreate' => $canManageRecipes,
            ],
            'createAction' => [
                'type' => 'custom',
                'handlerKey' => 'openRecipeCreate',
                'prefill' => [
                    'itemId' => $item->id,
                ],
            ],
            'endpoints' => [
                'list' => route('materials.recipes.index', $item),
            ],
            'fields' => [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.nameText',
                    'fallback' => 'Unnamed recipe',
                ],
                'secondaryFields' => [
                    [
                        'label' => 'Type',
                        'field' => 'display.recipeTypeText',
                        'fallback' => '—',
                    ],
                    [
                        'label' => 'Updated',
                        'field' => 'display.updatedAtText',
                        'fallback' => '—',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'fallback' => '',
                    ],
                    [
                        'field' => 'display.versionText',
                        'toneField' => 'display.versionTone',
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
                ],
            ],
            'actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryCountsSectionConfig(Request $request, Item $item): array
    {
        $canCreate = Gate::allows('inventory-adjustments-execute');

        return [
            'resource' => 'material-inventory-counts',
            'title' => 'Inventory Counts',
            'description' => 'Inventory count history for this stockable material.',
            'emptyState' => 'No inventory counts include this material yet.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => false,
            'permissions' => [
                'canCreate' => $canCreate,
            ],
            'showRowActionsMenu' => false,
            'inlineActionsOnMobile' => true,
            'recordClass' => 'rounded-lg border border-gray-200 bg-gray-50 px-3 py-1 sm:px-4 sm:py-1',
            'rowClass' => 'flex flex-row items-start justify-between gap-4',
            'rightMetaClass' => 'flex min-h-[3.25rem] min-w-[5rem] flex-col items-end justify-between gap-4 self-stretch text-right',
            'mobileRowUrlField' => 'display.showUrl',
            'secondaryFieldsClass' => 'mt-px flex flex-wrap items-center gap-x-3 gap-y-1',
            'createAction' => [
                'type' => 'custom',
                'handlerKey' => 'openInventoryCountCreate',
                'title' => 'Create Inventory Count',
                'description' => 'Create a count prefilled for this material.',
                'submitLabel' => 'Create Count',
            ],
            'endpoints' => [
                'list' => route('materials.inventory-counts.index', $item),
                'create' => route('materials.inventory-counts.store', $item),
                'update' => '',
                'remove' => '',
            ],
            'fields' => [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.nameText',
                    'urlField' => 'display.showUrl',
                    'linkClass' => 'truncate text-sm font-semibold text-gray-900 transition hover:text-gray-700',
                    'fallback' => '—',
                ],
                'secondaryFields' => [
                    [
                        'label' => '',
                        'field' => 'display.assignedToText',
                        'fallback' => '',
                        'textClass' => 'text-xs leading-none text-gray-600',
                        'textValueClass' => 'text-xs leading-none text-gray-600',
                    ],
                ],
                'badges' => [
                    [
                        'field' => 'display.statusText',
                        'toneField' => 'display.statusTone',
                        'textClass' => 'text-[0.55rem] uppercase tracking-wide',
                        'fallback' => '',
                    ],
                ],
                'rightMeta' => [
                    [
                        'label' => '',
                        'field' => 'display.countedAtText',
                        'fallback' => '',
                        'textClass' => 'text-xs font-medium text-gray-500',
                    ],
                    [
                        'label' => '',
                        'field' => 'display.countedQuantityText',
                        'suffixField' => 'display.uomSymbolText',
                        'fallback' => '',
                        'strong' => true,
                        'textClass' => 'text-xs',
                        'suffixClass' => 'text-[0.65rem]',
                    ],
                ],
            ],
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryCountCreateConfig(Request $request, Item $item): array
    {
        return [
            'canCreate' => true,
            'storeUrl' => route('materials.inventory-counts.store', $item),
            'csrfToken' => csrf_token(),
            'users' => User::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'scopedItem' => [
                'id' => $item->id,
                'name' => $item->name,
                'uomSymbol' => $item->baseUom?->symbol,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeOrdersSectionConfig(Item $item): array
    {
        return [
            'resource' => 'material-make-orders',
            'title' => 'Make Orders',
            'description' => 'Make orders for recipes that produce this material.',
            'emptyState' => 'No make orders produce this material yet.',
            'csrfToken' => csrf_token(),
            'defaultOpen' => false,
            'permissions' => [
                'canCreate' => false,
            ],
            'endpoints' => [
                'list' => route('materials.make-orders.index', $item),
            ],
            'fields' => [],
            'rowLayout' => [
                'primaryText' => [
                    'field' => 'display.recipeNameText',
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
                ],
                'rightMeta' => [
                    [
                        'label' => 'Output',
                        'field' => 'display.totalOutputQuantityText',
                        'fallback' => '—',
                        'strong' => true,
                    ],
                ],
            ],
            'actions' => [
                [
                    'id' => 'view',
                    'label' => 'View',
                    'type' => 'view',
                    'tone' => 'default',
                    'urlField' => 'display.showUrl',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function materialInventoryCountRowPayload(InventoryCountLine $line): array
    {
        $count = $line->inventoryCount;
        $lineUom = $line->snapshotUom() ?? $line->uom ?? $line->item?->baseUom;

        return [
            'id' => $line->id,
            'name' => $count?->name ?? '—',
            'counted_at' => $count?->counted_at?->format('F j, Y') ?? '—',
            'assigned_to_user_name' => $count?->assignedToUser?->name,
            'uom_name' => $lineUom?->name,
            'uom_symbol' => $lineUom?->symbol,
            'status_label' => $this->materialInventoryCountStatusLabel($count),
            'status_tone' => $this->materialInventoryCountStatusTone($count),
            'counted_quantity' => $line->counted_quantity,
            'counted_quantity_display' => $line->counted_quantity === null
                ? null
                : $this->formatGroupedQuantity(
                    QuantityFormatter::formatForUom($line->counted_quantity, $lineUom, 2)
                ),
            'show_url' => $count ? route('inventory.counts.show', $count) : '',
            'available_actions' => [],
        ];
    }

    /**
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
     * Add thousands separators to an already formatted decimal quantity string.
     */
    private function formatGroupedQuantity(string $quantity): string
    {
        $trimmed = trim($quantity);

        if ($trimmed === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $trimmed)) {
            return $quantity;
        }

        $isNegative = str_starts_with($trimmed, '-');
        $absolute = $isNegative ? substr($trimmed, 1) : $trimmed;
        [$wholePart, $fractionPart] = array_pad(explode('.', $absolute, 2), 2, '');
        $groupedWhole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $wholePart) ?? $wholePart;
        $formatted = $fractionPart === '' ? $groupedWhole : $groupedWhole . '.' . $fractionPart;

        return $isNegative ? '-' . $formatted : $formatted;
    }

    /**
     * Return the workflow-facing status label for a material-scoped inventory count row.
     */
    private function materialInventoryCountStatusLabel(?InventoryCount $inventoryCount): string
    {
        if ($inventoryCount === null) {
            return '';
        }

        if ($inventoryCount->workflow_stage_id === null) {
            return $inventoryCount->posted_at !== null ? 'COMPLETED' : 'Draft';
        }

        if ($inventoryCount->posted_at !== null) {
            return $inventoryCount->workflowStage?->status_complete_label
                ?: $inventoryCount->workflowStage?->name
                ?: 'Unknown';
        }

        $previousStage = app(ResolveInventoryWorkflowStageAction::class)->previousActiveStage($inventoryCount);

        return $previousStage?->status_complete_label
            ?: $previousStage?->name
            ?: 'Draft';
    }

    /**
     * Return the display tone for a material-scoped inventory count status badge.
     */
    private function materialInventoryCountStatusTone(?InventoryCount $inventoryCount): string
    {
        $statusLabel = $this->materialInventoryCountStatusLabel($inventoryCount);

        if (strtoupper($statusLabel) === 'SCHEDULED') {
            return 'info';
        }

        if ($inventoryCount === null || $inventoryCount->posted_at === null) {
            return 'muted';
        }

        return 'success';
    }

    /**
     * @return array<string, mixed>
     */
    private function recipeCreateConfig(Request $request, Item $item, bool $canManageRecipes): array
    {
        $manufacturableItems = Item::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where(function ($query) {
                $query->where('is_manufacturable', true)
                    ->orWhere('is_sellable', true);
            })
            ->withCount('recipes')
            ->with(['baseUom:id,name,symbol,display_precision'])
            ->orderBy('name')
            ->get(['id', 'name', 'base_uom_id', 'is_manufacturable', 'is_sellable']);

        return [
            'storeUrl' => route('manufacturing.recipes.store'),
            'csrfToken' => $request->session()->token(),
            'canManage' => $canManageRecipes,
            'prefillItemId' => $item->id,
            'manufacturableItems' => $manufacturableItems->map(function (Item $manufacturableItem) {
                return $this->recipeCreateItemPayload($manufacturableItem);
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeOrderCreateConfig(Request $request, Item $item, bool $canExecuteMakeOrders): array
    {
        $recipes = Recipe::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->where('recipe_type', Recipe::TYPE_MANUFACTURING)
            ->with('item.baseUom')
            ->orderBy('name')
            ->get();

        return [
            'storeUrl' => route('manufacturing.make-orders.store'),
            'csrfToken' => $request->session()->token(),
            'canExecute' => $canExecuteMakeOrders,
            'recipes' => $recipes->map(function (Recipe $recipe) {
                return [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'item_id' => $recipe->item_id,
                    'item_name' => $recipe->item?->name ?? '—',
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipeCreateItemPayload(Item $item): array
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
     * Normalize a numeric amount string to integer cents without float casting.
     *
     * @param string $amount
     * @return int
     */
    private function normalizeAmountToCents(string $amount): int
    {
        if (str_contains($amount, '.')) {
            [$whole, $decimal] = explode('.', $amount, 2);
            $decimalLength = strlen($decimal);

            if ($decimalLength === 0) {
                $decimal = '00';
            } elseif ($decimalLength === 1) {
                $decimal = $decimal . '0';
            }

            $wholeValue = (int) $whole;
            $decimalValue = (int) substr($decimal, 0, 2);

            return ($wholeValue * 100) + $decimalValue;
        }

        return ((int) $amount) * 100;
    }

    /**
     * Resolve the tenant currency for defaulting.
     *
     * @param Request $request
     * @return string
     */
    private function resolveTenantCurrency(Request $request): string
    {
        $tenantCurrency = $request->user()?->tenant?->currency_code;

        return $tenantCurrency ?: (string) config('app.currency_code', 'USD');
    }

    /**
     * Normalize a starting quantity input to canonical scale-safe text.
     */
    private function normalizeStartingQuantity(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return bcadd($normalized, '0', self::QUANTITY_SCALE);
    }

    /**
     * Normalize empty string inputs for default price fields to null.
     *
     * @param Request $request
     * @return void
     */
    private function normalizeDefaultPriceInputs(Request $request): void
    {
        if ($request->has('default_price_amount') && $request->input('default_price_amount') === '') {
            $request->merge(['default_price_amount' => null]);
        }

        if ($request->has('default_price_currency_code') && $request->input('default_price_currency_code') === '') {
            $request->merge(['default_price_currency_code' => null]);
        }
    }

    /**
     * Normalize empty string starting quantity input to null.
     */
    private function normalizeStartingQuantityInput(Request $request): void
    {
        if ($request->has('starting_quantity') && $request->input('starting_quantity') === '') {
            $request->merge(['starting_quantity' => null]);
        }
    }

    /**
     * Create and complete the initial inventory count used for a stockable opening balance.
     */
    private function createCompletedStartingInventoryCount(Request $request, Item $item, string $startingQuantity): void
    {
        $inventoryCount = InventoryCount::query()->create([
            'tenant_id' => $item->tenant_id,
            'created_by_user_id' => $request->user()->id,
            'tasked_by_user_id' => $request->user()->id,
            'assigned_to_user_id' => $request->user()->id,
            'name' => 'Initial count for ' . $item->name,
            'counted_at' => now(),
            'workflow_stage_id' => null,
            'notes' => 'Initial Stock',
        ]);

        InventoryCountLine::query()->create([
            'tenant_id' => $item->tenant_id,
            'inventory_count_id' => $inventoryCount->id,
            'item_id' => $item->id,
            'uom_id' => $item->base_uom_id,
            'counted_quantity' => $startingQuantity,
            'notes' => 'Initial Stock',
        ]);

        $this->ensureInventoryWorkflowStagesExist($request);
        app(AdvanceInventoryCountWorkflowStageAction::class)->postCompatible(
            $inventoryCount,
            (int) $request->user()->id
        );
    }

    /**
     * Seed default inventory workflow stages only when the tenant has not configured them yet.
     */
    private function ensureInventoryWorkflowStagesExist(Request $request): void
    {
        $resolver = app(ResolveInventoryWorkflowStageAction::class);
        $seedDefaultStagesAction = app(SeedDefaultWorkflowStagesForTenantAction::class);
        $inventoryDomainId = $resolver->inventoryDomainId();

        $hasStages = \App\Models\WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', (int) $request->user()->tenant_id)
            ->where('workflow_domain_id', $inventoryDomainId)
            ->exists();

        if (! $hasStages) {
            $seedDefaultStagesAction->execute($request->user()->tenant()->firstOrFail());
        }
    }

    /**
     * Format integer cents to a two-decimal string amount.
     *
     * @param int|null $cents
     * @return string|null
     */
    private function formatCentsToAmount(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $whole = intdiv($cents, 100);
        $decimal = $cents % 100;

        return sprintf('%d.%02d', $whole, $decimal);
    }
}
