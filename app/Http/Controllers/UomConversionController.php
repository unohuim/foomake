<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Uom;
use App\Models\UomConversion;
use App\Support\QuantityFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UomConversionController extends Controller
{
    private const QUANTITY_SCALE = 6;

    /**
     * Display the conversions page.
     */
    public function index(Request $request): View
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = (int) $request->user()->tenant_id;

        $payload = [
            'globalConversions' => $this->generalConversionsPayload(null),
            'tenantConversions' => $this->generalConversionsPayload($tenantId),
            'itemSpecificConversions' => $this->itemConversionsPayload($tenantId),
            'items' => Item::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Item $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])
                ->values()
                ->all(),
            'uoms' => Uom::query()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($tenantId): void {
                    $query->whereNull('tenant_id')
                        ->orWhere('tenant_id', $tenantId);
                })
                ->orderBy('symbol')
                ->get(['id', 'tenant_id', 'uom_category_id', 'name', 'symbol'])
                ->map(fn (Uom $uom): array => [
                    'id' => $uom->id,
                    'tenant_id' => $uom->tenant_id,
                    'uom_category_id' => $uom->uom_category_id,
                    'name' => $uom->name,
                    'symbol' => $uom->symbol,
                ])
                ->values()
                ->all(),
            'uomOptions' => $this->uomOptionsPayload($tenantId),
            'storeUrl' => route('manufacturing.uom-conversions.store'),
            'updateUrlTemplate' => route('manufacturing.uom-conversions.update', ['conversion' => '__ID__']),
            'deleteUrlTemplate' => route('manufacturing.uom-conversions.destroy', ['conversion' => '__ID__']),
            'itemStoreUrl' => route('manufacturing.uom-conversions.items.store'),
            'itemUpdateUrlTemplate' => route('manufacturing.uom-conversions.items.update', ['itemConversion' => '__ID__']),
            'itemDeleteUrlTemplate' => route('manufacturing.uom-conversions.items.destroy', ['itemConversion' => '__ID__']),
            'resolveUrl' => route('manufacturing.uom-conversions.resolve'),
            'csrfToken' => csrf_token(),
        ];

        return view('manufacturing.uom-conversions.index', [
            'payload' => $payload,
        ]);
    }

    /**
     * Store a tenant-managed general conversion.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = (int) $request->user()->tenant_id;
        $validated = $this->validateGeneralConversion($request, $tenantId);

        $conversion = UomConversion::query()->create([
            'tenant_id' => $tenantId,
            'from_uom_id' => (int) $validated['from_uom_id'],
            'to_uom_id' => (int) $validated['to_uom_id'],
            'multiplier' => $validated['multiplier'],
        ]);

        return response()->json($this->generalConversionRow($conversion, true), 201);
    }

    /**
     * Update a tenant-managed general conversion.
     *
     * @throws ValidationException
     */
    public function update(Request $request, int $conversion): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = (int) $request->user()->tenant_id;
        $model = UomConversion::query()->findOrFail($conversion);

        if ($model->isGlobal()) {
            abort(403);
        }

        if ((int) $model->tenant_id !== $tenantId) {
            abort(404);
        }

        $validated = $this->validateGeneralConversion($request, $tenantId, $model->id);

        $model->update([
            'from_uom_id' => (int) $validated['from_uom_id'],
            'to_uom_id' => (int) $validated['to_uom_id'],
            'multiplier' => $validated['multiplier'],
        ]);

        return response()->json($this->generalConversionRow($model->fresh(), true));
    }

    /**
     * Delete a tenant-managed general conversion.
     */
    public function destroy(Request $request, int $conversion): JsonResponse
    {
        Gate::authorize('inventory-materials-manage');

        $tenantId = (int) $request->user()->tenant_id;
        $model = UomConversion::query()->findOrFail($conversion);

        if ($model->isGlobal()) {
            abort(403);
        }

        if ((int) $model->tenant_id !== $tenantId) {
            abort(404);
        }

        $model->delete();

        return response()->json(null, 204);
    }

    /**
     * Store an item-specific conversion.
     *
     * @throws ValidationException
     */
    public function storeItem(Request $request): JsonResponse
    {
        $tenantId = null;
        $user = $request->user();

        if ($user !== null) {
            Gate::forUser($user)->authorize('inventory-materials-manage');
            $tenantId = (int) $user->tenant_id;
        }

        $validated = $this->validateItemConversion($request, null, $tenantId);

        $conversion = ItemUomConversion::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId ?? (int) $validated['tenant_id'],
            'item_id' => (int) $validated['item_id'],
            'from_uom_id' => (int) $validated['from_uom_id'],
            'to_uom_id' => (int) $validated['to_uom_id'],
            'conversion_factor' => $validated['conversion_factor'],
        ]);

        return response()->json($this->itemConversionRow($conversion), 201);
    }

    /**
     * Update an item-specific conversion.
     *
     * @throws ValidationException
     */
    public function updateItem(Request $request, int $itemConversion): JsonResponse
    {
        $model = ItemUomConversion::query()->withoutGlobalScopes()->findOrFail($itemConversion);
        $tenantId = (int) $model->tenant_id;
        $user = $request->user();

        if ($user !== null) {
            Gate::forUser($user)->authorize('inventory-materials-manage');

            if ((int) $user->tenant_id !== $tenantId) {
                abort(404);
            }
        }

        $validated = $this->validateItemConversion($request, $model->id, $tenantId);

        $model->update([
            'item_id' => (int) $validated['item_id'],
            'from_uom_id' => (int) $validated['from_uom_id'],
            'to_uom_id' => (int) $validated['to_uom_id'],
            'conversion_factor' => $validated['conversion_factor'],
        ]);

        return response()->json($this->itemConversionRow($model->fresh()));
    }

    /**
     * Delete an item-specific conversion.
     */
    public function destroyItem(Request $request, int $itemConversion): JsonResponse
    {
        $model = ItemUomConversion::query()->withoutGlobalScopes()->findOrFail($itemConversion);
        $user = $request->user();

        if ($user !== null) {
            Gate::forUser($user)->authorize('inventory-materials-manage');

            if ((int) $model->tenant_id !== (int) $user->tenant_id) {
                abort(404);
            }
        }

        $model->delete();

        return response()->json(null, 204);
    }

    /**
     * Resolve a conversion using item-specific, tenant, then global precedence.
     *
     * @throws ValidationException
     */
    public function resolve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_id' => ['nullable', 'integer'],
            'from_uom_id' => ['required', 'integer'],
            'to_uom_id' => ['required', 'integer'],
            'quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'],
        ]);

        $validated = $validator->validate();

        $item = null;
        $contextTenantId = null;

        if (isset($validated['item_id'])) {
            $item = Item::query()->withoutGlobalScopes()->findOrFail((int) $validated['item_id']);
            $contextTenantId = (int) $item->tenant_id;

            $itemConversion = ItemUomConversion::query()->withoutGlobalScopes()
                ->where('tenant_id', $contextTenantId)
                ->where('item_id', $item->id)
                ->where('from_uom_id', (int) $validated['from_uom_id'])
                ->where('to_uom_id', (int) $validated['to_uom_id'])
                ->first();

            if ($itemConversion) {
                return response()->json([
                    'data' => [
                        'source' => 'item-specific',
                        'multiplier' => bcadd((string) $itemConversion->conversion_factor, '0', self::QUANTITY_SCALE),
                        'converted_quantity' => bcmul(
                            $validated['quantity'],
                            (string) $itemConversion->conversion_factor,
                            self::QUANTITY_SCALE
                        ),
                    ],
                ]);
            }
        }

        if ($contextTenantId === null) {
            $fromUom = Uom::query()->withoutGlobalScopes()->findOrFail((int) $validated['from_uom_id']);
            $toUom = Uom::query()->withoutGlobalScopes()->findOrFail((int) $validated['to_uom_id']);

            if ($fromUom->tenant_id !== null && (int) $fromUom->tenant_id === (int) $toUom->tenant_id) {
                $contextTenantId = (int) $fromUom->tenant_id;
            }
        }

        if ($contextTenantId !== null) {
            $tenantConversion = UomConversion::query()
                ->where('tenant_id', $contextTenantId)
                ->where('from_uom_id', (int) $validated['from_uom_id'])
                ->where('to_uom_id', (int) $validated['to_uom_id'])
                ->first();

            if ($tenantConversion) {
                return response()->json([
                    'data' => [
                        'source' => 'tenant',
                        'multiplier' => bcadd((string) $tenantConversion->multiplier, '0', 8),
                        'converted_quantity' => bcmul(
                            $validated['quantity'],
                            (string) $tenantConversion->multiplier,
                            self::QUANTITY_SCALE
                        ),
                    ],
                ]);
            }
        }

        $globalConversion = UomConversion::query()
            ->whereNull('tenant_id')
            ->where('from_uom_id', (int) $validated['from_uom_id'])
            ->where('to_uom_id', (int) $validated['to_uom_id'])
            ->first();

        if ($globalConversion) {
            return response()->json([
                'data' => [
                    'source' => 'global',
                    'multiplier' => bcadd((string) $globalConversion->multiplier, '0', 8),
                    'converted_quantity' => bcmul(
                        $validated['quantity'],
                        (string) $globalConversion->multiplier,
                        self::QUANTITY_SCALE
                    ),
                ],
            ]);
        }

        return response()->json([
            'message' => 'Conversion not found.',
        ], 404);
    }

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function validateGeneralConversion(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => ['sometimes', 'required', 'integer'],
            'from_uom_id' => ['required', 'integer'],
            'to_uom_id' => ['required', 'integer'],
            'multiplier' => ['required', 'regex:/^\d+(\.\d{1,8})?$/'],
        ]);

        $validator->after(function ($validator) use ($request, $tenantId, $ignoreId): void {
            if ($request->exists('tenant_id') && (int) $request->input('tenant_id') !== $tenantId) {
                $validator->errors()->add('tenant_id', 'The tenant id field is invalid.');
            }

            $fromUom = Uom::query()->withoutGlobalScopes()->find($request->input('from_uom_id'));
            $toUom = Uom::query()->withoutGlobalScopes()->find($request->input('to_uom_id'));

            if (! $fromUom) {
                $validator->errors()->add('from_uom_id', 'The selected from uom id is invalid.');
            }

            if (! $toUom) {
                $validator->errors()->add('to_uom_id', 'The selected to uom id is invalid.');
            }

            if ($fromUom && $toUom && (int) $fromUom->uom_category_id !== (int) $toUom->uom_category_id) {
                $validator->errors()->add('to_uom_id', 'General conversions must stay within the same category.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $duplicateQuery = UomConversion::query()
                ->where('from_uom_id', (int) $request->input('from_uom_id'))
                ->where('to_uom_id', (int) $request->input('to_uom_id'))
                ->where(function ($query) use ($tenantId): void {
                    $query->whereNull('tenant_id')
                        ->orWhere('tenant_id', $tenantId);
                });

            if ($ignoreId !== null) {
                $duplicateQuery->where('id', '!=', $ignoreId);
            }

            if ($duplicateQuery->exists()) {
                $validator->errors()->add('from_uom_id', 'The conversion already exists.');
            }
        });

        return $validator->validate();
    }

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function validateItemConversion(Request $request, ?int $ignoreId = null, ?int $fallbackTenantId = null): array
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => [$fallbackTenantId === null ? 'required' : 'sometimes', 'integer'],
            'item_id' => ['required', 'integer'],
            'from_uom_id' => ['required', 'integer'],
            'to_uom_id' => ['required', 'integer'],
            'conversion_factor' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'],
        ]);

        $validator->after(function ($validator) use ($request, $ignoreId, $fallbackTenantId): void {
            $tenantId = $fallbackTenantId ?? (int) $request->input('tenant_id');
            $item = Item::query()->withoutGlobalScopes()->find($request->input('item_id'));

            if (! $item || (int) $item->tenant_id !== $tenantId) {
                $validator->errors()->add('item_id', 'The selected item id is invalid.');
                return;
            }

            $fromUom = Uom::query()->withoutGlobalScopes()->find($request->input('from_uom_id'));
            $toUom = Uom::query()->withoutGlobalScopes()->find($request->input('to_uom_id'));

            if (! $fromUom) {
                $validator->errors()->add('from_uom_id', 'The selected from uom id is invalid.');
            }

            if (! $toUom) {
                $validator->errors()->add('to_uom_id', 'The selected to uom id is invalid.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $duplicateQuery = ItemUomConversion::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('item_id', (int) $request->input('item_id'))
                ->where('from_uom_id', (int) $request->input('from_uom_id'))
                ->where('to_uom_id', (int) $request->input('to_uom_id'));

            if ($ignoreId !== null) {
                $duplicateQuery->where('id', '!=', $ignoreId);
            }

            if ($duplicateQuery->exists()) {
                $validator->errors()->add('from_uom_id', 'The item-specific conversion already exists.');
            }
        });

        $validated = $validator->validate();

        if ($fallbackTenantId !== null) {
            $validated['tenant_id'] = $fallbackTenantId;
        }

        return $validated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generalConversionsPayload(?int $tenantId): array
    {
        $query = DB::table('uom_conversions')
            ->join('uoms as from_uoms', 'from_uoms.id', '=', 'uom_conversions.from_uom_id')
            ->join('uoms as to_uoms', 'to_uoms.id', '=', 'uom_conversions.to_uom_id')
            ->select([
                'uom_conversions.id',
                'uom_conversions.tenant_id',
                'uom_conversions.from_uom_id',
                'uom_conversions.to_uom_id',
                'uom_conversions.multiplier',
                'from_uoms.symbol as from_symbol',
                'to_uoms.symbol as to_symbol',
                'to_uoms.display_precision as to_display_precision',
            ])
            ->orderBy('from_uoms.symbol')
            ->orderBy('to_uoms.symbol');

        if ($tenantId === null) {
            $query->whereNull('uom_conversions.tenant_id');
        } else {
            $query->where('uom_conversions.tenant_id', $tenantId);
        }

        return $query->get()
            ->map(function ($row): array {
                return [
                    'id' => $row->id,
                    'tenant_id' => $row->tenant_id,
                    'from_uom_id' => $row->from_uom_id,
                    'to_uom_id' => $row->to_uom_id,
                    'from_symbol' => $row->from_symbol,
                    'to_symbol' => $row->to_symbol,
                    'multiplier' => (string) $row->multiplier,
                    'multiplier_display' => QuantityFormatter::format(
                        (string) $row->multiplier,
                        (int) $row->to_display_precision
                    ),
                    'read_only' => $row->tenant_id === null,
                    'editable' => $row->tenant_id !== null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function uomOptionsPayload(int $tenantId): array
    {
        return Uom::query()
            ->withoutGlobalScopes()
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->get(['id', 'tenant_id', 'uom_category_id', 'name', 'symbol'])
            ->sortBy(function (Uom $uom) use ($tenantId): string {
                $priority = (int) ((int) $uom->tenant_id !== $tenantId);

                return implode('|', [
                    (string) $priority,
                    strtolower((string) $uom->symbol),
                    strtolower((string) $uom->name),
                    (string) $uom->id,
                ]);
            })
            ->unique(function (Uom $uom): string {
                return implode('|', [
                    strtolower(trim((string) $uom->symbol)),
                    strtolower(trim((string) $uom->name)),
                ]);
            })
            ->values()
            ->map(fn (Uom $uom): array => [
                'id' => $uom->id,
                'tenant_id' => $uom->tenant_id,
                'uom_category_id' => $uom->uom_category_id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemConversionsPayload(int $tenantId): array
    {
        return DB::table('item_uom_conversions')
            ->join('items', 'items.id', '=', 'item_uom_conversions.item_id')
            ->join('uoms as from_uoms', 'from_uoms.id', '=', 'item_uom_conversions.from_uom_id')
            ->join('uoms as to_uoms', 'to_uoms.id', '=', 'item_uom_conversions.to_uom_id')
            ->where('item_uom_conversions.tenant_id', $tenantId)
            ->select([
                'item_uom_conversions.id',
                'item_uom_conversions.tenant_id',
                'item_uom_conversions.item_id',
                'item_uom_conversions.from_uom_id',
                'item_uom_conversions.to_uom_id',
                'item_uom_conversions.conversion_factor',
                'items.name as item_name',
                'from_uoms.symbol as from_symbol',
                'to_uoms.symbol as to_symbol',
                'to_uoms.display_precision as to_display_precision',
            ])
            ->orderBy('items.name')
            ->orderBy('from_uoms.symbol')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'from_uom_id' => $row->from_uom_id,
                'to_uom_id' => $row->to_uom_id,
                'from_symbol' => $row->from_symbol,
                'to_symbol' => $row->to_symbol,
                'conversion_factor' => (string) $row->conversion_factor,
                'conversion_factor_display' => QuantityFormatter::format(
                    (string) $row->conversion_factor,
                    (int) $row->to_display_precision
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function generalConversionRow(UomConversion $conversion, bool $editable): array
    {
        $toUom = $conversion->toUom()->withoutGlobalScopes()->first();

        return [
            'id' => $conversion->id,
            'tenant_id' => $conversion->tenant_id,
            'from_uom_id' => $conversion->from_uom_id,
            'to_uom_id' => $conversion->to_uom_id,
            'multiplier' => (string) $conversion->multiplier,
            'multiplier_display' => QuantityFormatter::formatForUom(
                (string) $conversion->multiplier,
                $toUom,
                6
            ),
            'read_only' => ! $editable,
            'editable' => $editable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemConversionRow(ItemUomConversion $conversion): array
    {
        $toUom = $conversion->toUom()->withoutGlobalScopes()->first();

        return [
            'id' => $conversion->id,
            'tenant_id' => $conversion->tenant_id,
            'item_id' => $conversion->item_id,
            'from_uom_id' => $conversion->from_uom_id,
            'to_uom_id' => $conversion->to_uom_id,
            'conversion_factor' => (string) $conversion->conversion_factor,
            'conversion_factor_display' => QuantityFormatter::formatForUom(
                (string) $conversion->conversion_factor,
                $toUom,
                6
            ),
        ];
    }
}
