<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\SupplierUpdateRequest;
use App\Models\Item;
use App\Models\ItemPurchaseOption;
use App\Models\Supplier;
use App\Models\Uom;
use App\Services\Purchasing\SupplierDeleteGuard;
use App\Support\QuantityFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Handle supplier index and creation.
 */
class SupplierController extends Controller
{
    /**
     * Display the suppliers index.
     */
    public function index(Request $request): View
    {
        Gate::authorize('purchasing-suppliers-view');

        $suppliers = Supplier::query()
            ->orderBy('company_name')
            ->get();

        $tenantCurrency = $request->user()?->tenant?->currency_code;
        $defaultCurrency = $tenantCurrency ?: (string) config('app.currency_code', 'USD');

        return view('purchasing.suppliers.index', [
            'suppliers' => $suppliers,
            'defaultCurrency' => $defaultCurrency,
        ]);
    }

    /**
     * Display a supplier detail page with pricing.
     */
    public function show(Request $request, Supplier $supplier): View
    {
        Gate::authorize('purchasing-suppliers-view');
        $this->abortIfWrongTenant($request, $supplier);

        $tenantCurrency = strtoupper((string) ($request->user()?->tenant?->currency_code ?: config('app.currency_code', 'USD')));
        $packages = $supplier->purchaseOptions()
            ->with(['item', 'packUom', 'currentPrice'])
            ->orderBy('id')
            ->get()
            ->map(function (ItemPurchaseOption $option) {
                $currentPrice = $option->currentPrice;
                $packQuantity = bcadd((string) $option->pack_quantity, '0', 6);
                $packPrecision = (int) ($option->packUom?->display_precision ?? 1);

                return [
                    'id' => $option->id,
                    'item_id' => $option->item_id,
                    'item_name' => $option->item?->name,
                    'pack_quantity' => $packQuantity,
                    'pack_quantity_display' => QuantityFormatter::format($packQuantity, $packPrecision),
                    'pack_uom_id' => $option->pack_uom_id,
                    'pack_uom_symbol' => $option->packUom?->symbol,
                    'pack_uom_name' => $option->packUom?->name,
                    'supplier_sku' => $option->supplier_sku,
                    'current_price_display' => $currentPrice
                        ? $this->formatMoney($currentPrice->price_currency_code, $currentPrice->converted_price_cents)
                        : null,
                    'current_price_currency_code' => $currentPrice?->price_currency_code,
                    'current_price_cents' => $currentPrice?->converted_price_cents,
                ];
            })
            ->values()
            ->toArray();

        $payload = [
            'supplier' => [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'currency_code' => $supplier->currency_code,
            ],
            'packages' => $packages,
            'canManage' => Gate::allows('purchasing-suppliers-manage'),
            'packageStoreUrl' => route('purchasing.suppliers.purchase-options.store', $supplier),
            'priceStoreUrlBase' => url('/purchasing/purchase-options'),
            'csrfToken' => csrf_token(),
            'tenantCurrencyCode' => $tenantCurrency,
            'supplierCurrencyCode' => $supplier->currency_code ? strtoupper($supplier->currency_code) : null,
            'purchasableItems' => Item::query()
                ->where('tenant_id', $supplier->tenant_id)
                ->where('is_purchasable', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'uoms' => Uom::query()
                ->where('tenant_id', $supplier->tenant_id)
                ->orderBy('symbol')
                ->get(['id', 'symbol', 'name'])
                ->toArray(),
        ];

        return view('purchasing.suppliers.show', [
            'supplier' => $supplier,
            'payload' => $payload,
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

        return sprintf('%s %s', $currencyCode, number_format($cents / 100, 2, '.', ''));
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ]);

        $tenantCurrency = $request->user()?->tenant?->currency_code;
        $defaultCurrency = $tenantCurrency ?: (string) config('app.currency_code', 'USD');
        $currencyCode = $validated['currency_code'] ?? null;

        if ($currencyCode === null || $currencyCode === '') {
            $currencyCode = $defaultCurrency;
        } else {
            $currencyCode = strtoupper($currencyCode);
        }

        $supplier = Supplier::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'company_name' => $validated['company_name'],
            'url' => $validated['url'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'currency_code' => $currencyCode,
        ]);

        return response()->json([
            'data' => [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'url' => $supplier->url,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'currency_code' => $supplier->currency_code,
                'show_url' => route('purchasing.suppliers.show', $supplier),
            ],
        ], 201);
    }

    /**
     * Update an existing supplier.
     */
    public function update(SupplierUpdateRequest $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $this->abortIfWrongTenant($request, $supplier);

        $validated = $request->validated();

        $updateData = [
            'company_name' => $validated['company_name'],
        ];

        $payload = $request->all();

        foreach (['url', 'phone', 'email', 'currency_code'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $value = $validated[$field] ?? null;

            if ($field === 'currency_code' && $value !== null && $value !== '') {
                $value = strtoupper($value);
            }

            $updateData[$field] = $value;
        }

        $supplier->update($updateData);

        return response()->json([
            'data' => [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'url' => $supplier->url,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'currency_code' => $supplier->currency_code,
            ],
        ]);
    }

    /**
     * Delete a supplier.
     */
    public function destroy(Request $request, Supplier $supplier, SupplierDeleteGuard $deleteGuard): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');

        $this->abortIfWrongTenant($request, $supplier);

        if ($deleteGuard->isLinkedToMaterials($supplier)) {
            return response()->json([
                'message' => 'Supplier cannot be deleted because it is linked to materials.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Deleted.',
        ]);
    }

    /**
     * Abort with 404 if the supplier does not belong to the authenticated tenant.
     */
    private function abortIfWrongTenant(Request $request, Supplier $supplier): void
    {
        if ($request->user() && $supplier->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }
}
