<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\Uom;
use App\Support\QuantityFormatter;
use App\Support\Uom\UomConversionPathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaterialSupplierPackageController extends Controller
{
    public function __construct(
        private readonly UomConversionPathResolver $conversionPathResolver
    ) {
    }

    public function index(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-suppliers-view');

        $paginator = ItemPurchaseOption::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('item_id', $item->id)
            ->whereNotNull('supplier_id')
            ->with(['supplier', 'item', 'packUom', 'currentPrice'])
            ->whereHas('supplier')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->paginate(10);

        $data = $paginator->getCollection()
            ->map(fn (ItemPurchaseOption $option): array => $this->rowPayload($request, $option))
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

    public function store(Request $request, Item $item): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-suppliers-manage');

        $validated = $request->validate($this->rules($request));

        if (! $this->supplierPackageConversionExists($request, $item, (int) $validated['pack_uom_id'])) {
            return $this->missingConversionResponse($request, $item, (int) $validated['pack_uom_id']);
        }

        $tenantCurrency = $this->tenantCurrency($request);
        $option = null;
        $priceCents = $this->normalizeAmountToCents((string) $validated['price_amount']);

        DB::transaction(function () use ($request, $validated, $item, $priceCents, $tenantCurrency, &$option): void {
            $option = ItemPurchaseOption::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'supplier_id' => (int) $validated['supplier_id'],
                'item_id' => $item->id,
                'pack_quantity' => $validated['pack_quantity'],
                'pack_uom_id' => (int) $validated['pack_uom_id'],
                'supplier_sku' => $validated['supplier_sku'] ?? null,
                'is_active' => true,
            ]);

            $this->storeCurrentPrice($request, $option, $priceCents, $tenantCurrency);
        });

        $option->load(['supplier', 'item', 'packUom', 'currentPrice']);

        return response()->json([
            'data' => $this->rowPayload($request, $option),
        ], 201);
    }

    public function update(Request $request, Item $item, ItemPurchaseOption $option): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-suppliers-manage');

        $this->abortIfOptionDoesNotBelongToItem($request, $item, $option);

        $validated = $request->validate($this->rules($request));

        if (! $this->supplierPackageConversionExists($request, $item, (int) $validated['pack_uom_id'])) {
            return $this->missingConversionResponse($request, $item, (int) $validated['pack_uom_id']);
        }

        $tenantCurrency = $this->tenantCurrency($request);
        $priceCents = $this->normalizeAmountToCents((string) $validated['price_amount']);

        DB::transaction(function () use ($request, $validated, $option, $priceCents, $tenantCurrency): void {
            $option->update([
                'supplier_id' => (int) $validated['supplier_id'],
                'pack_quantity' => $validated['pack_quantity'],
                'pack_uom_id' => (int) $validated['pack_uom_id'],
                'supplier_sku' => $validated['supplier_sku'] ?? null,
            ]);

            $this->storeCurrentPrice($request, $option, $priceCents, $tenantCurrency);
        });

        $option->load(['supplier', 'item', 'packUom', 'currentPrice']);

        return response()->json([
            'data' => $this->rowPayload($request, $option),
        ]);
    }

    public function destroy(Request $request, Item $item, ItemPurchaseOption $option): JsonResponse
    {
        Gate::authorize('inventory-materials-view');
        Gate::authorize('purchasing-suppliers-manage');

        $this->abortIfOptionDoesNotBelongToItem($request, $item, $option);

        if ($this->hasHistory($request, $option)) {
            $option->forceFill(['is_active' => false])->save();

            return response()->json([
                'result' => 'archived',
                'message' => 'Archived.',
            ]);
        }

        $option->delete();

        return response()->json([
            'result' => 'removed',
            'message' => 'Removed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId),
            ],
            'pack_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d{1,12}(?:\.\d{1,6})?$/',
            ],
            'pack_uom_id' => [
                'required',
                'integer',
                Rule::exists('uoms', 'id')->where('tenant_id', $tenantId),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'price_amount' => [
                'required',
                'regex:/^\d+(?:\.\d{1,2})?$/',
            ],
        ];
    }

    private function abortIfOptionDoesNotBelongToItem(Request $request, Item $item, ItemPurchaseOption $option): void
    {
        if (
            $option->tenant_id !== $request->user()->tenant_id
            || $option->item_id !== $item->id
            || $option->supplier_id === null
        ) {
            abort(404);
        }

        $supplier = Supplier::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->find($option->supplier_id);

        $uom = Uom::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->find($option->pack_uom_id);

        if (! $supplier || ! $uom) {
            abort(404);
        }
    }

    private function hasHistory(Request $request, ItemPurchaseOption $option): bool
    {
        return PurchaseOrderLine::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('item_purchase_option_id', $option->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(Request $request, ItemPurchaseOption $option): array
    {
        $currentPrice = $option->currentPrice;
        $packQuantity = bcadd((string) $option->pack_quantity, '0', 6);
        $packPrecision = (int) ($option->packUom?->display_precision ?? 1);
        $hasHistory = $this->hasHistory($request, $option);
        $canManage = Gate::allows('purchasing-suppliers-manage');
        $canCreatePurchaseOrders = Gate::allows('purchasing-purchase-orders-create');
        $availableActions = ['view'];

        if ($canCreatePurchaseOrders && (bool) $option->is_active) {
            $availableActions[] = 'purchase';
        }

        if ($canManage) {
            $availableActions[] = 'edit';

            if ((bool) $option->is_active) {
                $availableActions[] = $hasHistory ? 'archive' : 'remove';
            }
        }

        return [
            'id' => $option->id,
            'item_purchase_option_id' => $option->id,
            'supplier_id' => $option->supplier_id,
            'supplier_name' => $option->supplier?->company_name,
            'item_id' => $option->item_id,
            'item_name' => $option->item?->name,
            'show_url' => $option->supplier_id ? route('purchasing.suppliers.show', $option->supplier_id) : null,
            'pack_quantity' => $packQuantity,
            'pack_quantity_display' => QuantityFormatter::format($packQuantity, $packPrecision),
            'pack_uom_id' => $option->pack_uom_id,
            'pack_uom_symbol' => $option->packUom?->symbol,
            'pack_uom_name' => $option->packUom?->name,
            'label' => trim(sprintf(
                '%s (%s %s)',
                (string) $option->supplier?->company_name,
                QuantityFormatter::format($packQuantity, $packPrecision),
                (string) ($option->packUom?->symbol ?: $option->packUom?->name)
            )),
            'supplier_sku' => $option->supplier_sku,
            'current_price_display' => $currentPrice
                ? $this->formatMoney($currentPrice->price_currency_code, $currentPrice->converted_price_cents)
                : null,
            'current_price_cents' => $currentPrice?->converted_price_cents,
            'current_price_currency_code' => $currentPrice?->price_currency_code,
            'price_amount' => $this->formatCentsToAmount($currentPrice?->converted_price_cents),
            'is_active' => (bool) $option->is_active,
            'state' => $option->is_active ? 'active' : 'archived',
            'available_actions' => $availableActions,
            'conversion_create_url' => route('manufacturing.uom-conversions.items.store'),
        ];
    }

    private function storeCurrentPrice(
        Request $request,
        ItemPurchaseOption $option,
        int $priceCents,
        string $tenantCurrency
    ): void {
        $effectiveAt = now();

        ItemPurchaseOptionPrice::query()
            ->where('item_purchase_option_id', $option->id)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->update([
                'ended_at' => $effectiveAt,
            ]);

        ItemPurchaseOptionPrice::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'item_purchase_option_id' => $option->id,
            'price_cents' => $priceCents,
            'price_currency_code' => $tenantCurrency,
            'converted_price_cents' => $priceCents,
            'fx_rate' => '1.000000',
            'fx_rate_as_of' => Carbon::today()->toDateString(),
            'effective_at' => $effectiveAt,
            'ended_at' => null,
        ]);
    }

    private function formatMoney(?string $currencyCode, ?int $cents): ?string
    {
        if (! $currencyCode || $cents === null) {
            return null;
        }

        return sprintf('%s %s', strtoupper($currencyCode), number_format($cents / 100, 2, '.', ''));
    }

    private function formatCentsToAmount(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function normalizeAmountToCents(string $amount): int
    {
        if (! str_contains($amount, '.')) {
            return ((int) $amount) * 100;
        }

        [$whole, $decimal] = explode('.', $amount, 2);
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return (((int) $whole) * 100) + ((int) $decimal);
    }

    private function tenantCurrency(Request $request): string
    {
        return strtoupper((string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD')));
    }

    /**
     * Determine whether the package UoM can convert into the item base UoM.
     */
    private function supplierPackageConversionExists(Request $request, Item $item, int $packUomId): bool
    {
        $tenantId = (int) $request->user()->tenant_id;

        $packUom = Uom::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($packUomId);

        $baseUom = Uom::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($item->base_uom_id);

        return $this->conversionPathResolver->canResolve(
            $tenantId,
            (int) $item->id,
            $packUom,
            $baseUom,
            UomConversionPathResolver::PRECEDENCE_GENERAL_FIRST
        );
    }

    /**
     * Return a JSON/AJAX-compatible missing conversion response.
     */
    private function missingConversionResponse(Request $request, Item $item, int $packUomId): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $item->loadMissing('baseUom');
        $packUom = Uom::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($packUomId);
        $baseUom = $item->baseUom;

        return response()->json([
            'message' => 'Create a unit conversion before using this package UoM.',
            'errors' => [
                'pack_uom_id' => ['Create a unit conversion before using this package UoM.'],
            ],
            'meta' => [
                'requires_conversion' => true,
                'conversion_create_url' => route('manufacturing.uom-conversions.items.store'),
                'suggested_direction' => 'package_to_base',
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                ],
                'from_uom' => [
                    'id' => $packUom->id,
                    'name' => $packUom->name,
                    'symbol' => $packUom->symbol,
                ],
                'to_uom' => [
                    'id' => $baseUom?->id,
                    'name' => $baseUom?->name,
                    'symbol' => $baseUom?->symbol,
                ],
            ],
        ], 422);
    }
}
