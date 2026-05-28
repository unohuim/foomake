<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\StoreSupplierPurchaseOptionRequest;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Support\QuantityFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Handle CRUD for supplier purchase options.
 */
class SupplierPurchaseOptionController extends Controller
{
    /**
     * Return supplier-scoped purchase options for the reusable detail section.
     */
    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-view');
        $this->abortIfWrongTenant($request, $supplier);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $options = ItemPurchaseOption::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('supplier_id', $supplier->id)
            ->with(['item', 'packUom', 'currentPrice', 'supplier'])
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $options
                ->getCollection()
                ->map(fn (ItemPurchaseOption $option): array => $this->rowPayload($request, $option))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $options->currentPage(),
                'last_page' => $options->lastPage(),
                'per_page' => $options->perPage(),
                'total' => $options->total(),
            ],
        ]);
    }

    /**
     * Store a new purchase option for the supplier.
     */
    public function store(StoreSupplierPurchaseOptionRequest $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');
        $this->abortIfWrongTenant($request, $supplier);

        $validated = $request->validated();
        $tenantCurrency = $this->tenantCurrency($request);
        $priceCents = isset($validated['price_amount']) && $validated['price_amount'] !== ''
            ? $this->normalizeAmountToCents((string) $validated['price_amount'])
            : null;

        $option = DB::transaction(function () use ($request, $supplier, $validated, $priceCents, $tenantCurrency) {
            $option = ItemPurchaseOption::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'supplier_id' => $supplier->id,
                'item_id' => $validated['item_id'],
                'pack_quantity' => $validated['pack_quantity'],
                'pack_uom_id' => $validated['pack_uom_id'],
                'supplier_sku' => $validated['supplier_sku'] ?? null,
            ]);

            if ($priceCents !== null) {
                $this->storeCurrentPrice($request, $option, $priceCents, $tenantCurrency);
            }

            return $option;
        });

        $option->load(['item', 'packUom', 'currentPrice', 'supplier']);

        return response()->json([
            'data' => $this->rowPayload($request, $option),
        ], 201);
    }

    /**
     * Update a supplier purchase option.
     */
    public function update(
        StoreSupplierPurchaseOptionRequest $request,
        Supplier $supplier,
        ItemPurchaseOption $option
    ): JsonResponse {
        Gate::authorize('purchasing-suppliers-manage');
        $this->abortIfWrongTenant($request, $supplier);
        $this->abortIfOptionUnavailable($request, $supplier, $option);

        $validated = $request->validated();
        $tenantCurrency = $this->tenantCurrency($request);
        $priceCents = isset($validated['price_amount']) && $validated['price_amount'] !== ''
            ? $this->normalizeAmountToCents((string) $validated['price_amount'])
            : null;

        DB::transaction(function () use ($request, $option, $validated, $priceCents, $tenantCurrency): void {
            $option->update([
                'item_id' => $validated['item_id'],
                'pack_quantity' => $validated['pack_quantity'],
                'pack_uom_id' => $validated['pack_uom_id'],
                'supplier_sku' => $validated['supplier_sku'] ?? null,
            ]);

            if ($priceCents !== null) {
                $this->storeCurrentPrice($request, $option, $priceCents, $tenantCurrency);
            }
        });

        $option->refresh()->load(['item', 'packUom', 'currentPrice', 'supplier']);

        return response()->json([
            'data' => $this->rowPayload($request, $option),
        ]);
    }

    /**
     * Delete or archive a supplier purchase option.
     */
    public function destroy(Request $request, Supplier $supplier, ItemPurchaseOption $option): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');
        $this->abortIfWrongTenant($request, $supplier);
        $this->abortIfOptionUnavailable($request, $supplier, $option);

        if ($this->hasHistory($request, $option)) {
            $option->forceFill(['is_active' => false])->save();

            return response()->json([
                'message' => 'Archived.',
            ]);
        }

        $option->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Abort with 404 when tenant mismatch.
     */
    private function abortIfWrongTenant(Request $request, Supplier $supplier): void
    {
        if ($request->user()?->tenant_id !== $supplier->tenant_id) {
            abort(404);
        }
    }

    /**
     * Abort with 404 when the option is outside the supplier context.
     */
    private function abortIfOptionUnavailable(Request $request, Supplier $supplier, ItemPurchaseOption $option): void
    {
        if ($option->tenant_id !== $request->user()?->tenant_id || $option->supplier_id !== $supplier->id) {
            abort(404);
        }
    }

    /**
     * Determine whether a purchase option has dependent purchase order history.
     */
    private function hasHistory(Request $request, ItemPurchaseOption $option): bool
    {
        return PurchaseOrderLine::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('item_purchase_option_id', $option->id)
            ->exists();
    }

    /**
     * Build the reusable detail section row payload.
     *
     * @return array<string, mixed>
     */
    private function rowPayload(Request $request, ItemPurchaseOption $option): array
    {
        $currentPrice = $option->currentPrice;
        $packQuantity = bcadd((string) $option->pack_quantity, '0', 6);
        $packPrecision = (int) ($option->packUom?->display_precision ?? 1);
        $canManage = Gate::allows('purchasing-suppliers-manage');
        $canCreatePurchaseOrders = Gate::allows('purchasing-purchase-orders-create');
        $availableActions = [];

        if ($canManage) {
            $availableActions[] = 'edit';
        }

        if ($canCreatePurchaseOrders && (bool) $option->is_active) {
            $availableActions[] = 'purchase';
        }

        if ($canManage && (bool) $option->is_active) {
            $availableActions[] = 'delete';
        }

        return [
            'id' => $option->id,
            'item_purchase_option_id' => $option->id,
            'supplier_id' => $option->supplier_id,
            'supplier_name' => $option->supplier?->company_name,
            'item_id' => $option->item_id,
            'item_name' => $option->item?->name,
            'show_url' => $option->item_id ? route('materials.show', $option->item_id) : null,
            'purchase_url' => $option->item_id ? route('materials.purchase-orders.store', $option->item_id) : null,
            'pack_quantity' => $packQuantity,
            'pack_quantity_display' => QuantityFormatter::format($packQuantity, $packPrecision),
            'pack_uom_id' => $option->pack_uom_id,
            'pack_uom_symbol' => $option->packUom?->symbol,
            'pack_uom_name' => $option->packUom?->name,
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
        ];
    }

    /**
     * Store the current tenant-currency price for a purchase option.
     */
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

    /**
     * Format money for display.
     */
    private function formatMoney(?string $currencyCode, ?int $cents): ?string
    {
        if (! $currencyCode || $cents === null) {
            return null;
        }

        return sprintf('%s %s', strtoupper($currencyCode), number_format($cents / 100, 2, '.', ''));
    }

    /**
     * Format cents as a decimal form amount.
     */
    private function formatCentsToAmount(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /**
     * Normalize a decimal currency amount into cents.
     */
    private function normalizeAmountToCents(string $amount): int
    {
        if (! str_contains($amount, '.')) {
            return ((int) $amount) * 100;
        }

        [$whole, $decimal] = explode('.', $amount, 2);
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return (((int) $whole) * 100) + ((int) $decimal);
    }

    /**
     * Return the current tenant currency.
     */
    private function tenantCurrency(Request $request): string
    {
        return strtoupper((string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD')));
    }
}
