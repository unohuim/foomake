<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\StoreItemPurchaseOptionPriceRequest;
use App\Models\ItemPurchaseOption;
use App\Models\ItemPurchaseOptionPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Manage pricing for supplier purchase options.
 */
class ItemPurchaseOptionPriceController extends Controller
{
    /**
     * Store a new price for a purchase option.
     */
    public function store(StoreItemPurchaseOptionPriceRequest $request, ItemPurchaseOption $option): JsonResponse
    {
        Gate::authorize('purchasing-suppliers-manage');
        $this->abortIfWrongTenant($request, $option);

        $tenantCurrency = strtoupper(
            $request->user()?->tenant?->currency_code
            ?? config('app.currency_code', 'USD')
        );

        $payload = $request->validated();
        $priceCents = $payload['price_cents'];
        $currencyCode = (string) $payload['price_currency_code'];
        $fxRate = (string) $payload['fx_rate'];
        $fxRateAsOf = Carbon::parse($payload['fx_rate_as_of'])->toDateString();
        $effectiveAt = now();
        $convertedPriceCents = $this->calculateConvertedPrice(
            $priceCents,
            $fxRate,
            $currencyCode,
            $tenantCurrency
        );

        $price = null;

        DB::transaction(function () use (
            $option,
            $request,
            $currencyCode,
            $fxRate,
            $fxRateAsOf,
            $priceCents,
            $convertedPriceCents,
            $effectiveAt,
            &$price
        ) {
            $current = ItemPurchaseOptionPrice::query()
                ->where('item_purchase_option_id', $option->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current) {
                $current->update([
                    'ended_at' => $effectiveAt,
                ]);
            }

            $price = ItemPurchaseOptionPrice::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'item_purchase_option_id' => $option->id,
                'price_cents' => $priceCents,
                'price_currency_code' => $currencyCode,
                'converted_price_cents' => $convertedPriceCents,
                'fx_rate' => $fxRate,
                'fx_rate_as_of' => $fxRateAsOf,
                'effective_at' => $effectiveAt,
                'ended_at' => null,
            ]);
        });

        return response()->json([
            'data' => $this->formatPriceResponse($price),
        ], 201);
    }

    /**
     * Calculate converted cents with half-up rounding.
     */
    private function calculateConvertedPrice(int $priceCents, string $fxRate, string $currencyCode, string $tenantCurrency): int
    {
        if ($currencyCode === $tenantCurrency) {
            return $priceCents;
        }

        $value = bcmul((string) $priceCents, $fxRate, 8);
        $integerPart = (int) bcdiv($value, '1', 0);
        $remainder = bcsub($value, (string) $integerPart, 8);

        if (bccomp($remainder, '0.5', 8) >= 0) {
            $integerPart++;
        }

        return $integerPart;
    }

    /**
     * Prepare the JSON payload for created prices.
     */
    private function formatPriceResponse(ItemPurchaseOptionPrice $price): array
    {
        return [
            'id' => $price->id,
            'item_purchase_option_id' => $price->item_purchase_option_id,
            'price_cents' => $price->price_cents,
            'price_currency_code' => $price->price_currency_code,
            'converted_price_cents' => $price->converted_price_cents,
            'fx_rate' => (string) $price->fx_rate,
            'fx_rate_as_of' => $price->fx_rate_as_of->toDateString(),
            'effective_at' => $price->effective_at->toDateTimeString(),
            'ended_at' => $price->ended_at?->toDateTimeString(),
        ];
    }

    /**
     * Abort when the option does not belong to the authenticated tenant.
     */
    private function abortIfWrongTenant(Request $request, ItemPurchaseOption $option): void
    {
        if ($request->user()?->tenant_id !== $option->tenant_id) {
            abort(404);
        }
    }
}
