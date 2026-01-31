<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemController extends Controller
{
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
                    'default_price_amount' => $this->formatCentsToAmount($item->default_price_cents),
                    'default_price_currency_code' => $item->default_price_currency_code,
                ],
            ]);
        }

        return view('materials.show', [
            'item' => $item,
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
            'default_price_amount' => ['nullable', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'default_price_currency_code' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        $defaultPriceData = $this->resolveDefaultPriceData($request, null);

        $item = Item::query()->create(array_merge([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'base_uom_id' => $validated['base_uom_id'],
            'is_purchasable' => $request->boolean('is_purchasable'),
            'is_sellable' => $request->boolean('is_sellable'),
            'is_manufacturable' => $request->boolean('is_manufacturable'),
        ], $defaultPriceData));

        return response()->json([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'is_purchasable' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_manufacturable' => ['nullable', 'boolean'],
            'default_price_amount' => ['nullable', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'default_price_currency_code' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        $hasStockMoves = $item->stockMoves()->exists();
        $baseUomId = (int) $validated['base_uom_id'];

        if ($hasStockMoves && $baseUomId !== $item->base_uom_id) {
            return response()->json([
                'message' => 'Base unit of measure is locked.',
                'errors' => [
                    'base_uom_id' => ['Base unit of measure cannot be changed once stock moves exist.'],
                ],
            ], 422);
        }

        $updateData = [
            'name' => $validated['name'],
            'base_uom_id' => $baseUomId,
        ];

        $flagFields = ['is_purchasable', 'is_sellable', 'is_manufacturable'];

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
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
                'has_stock_moves' => $hasStockMoves,
                'default_price_amount' => $this->formatCentsToAmount($item->default_price_cents),
                'default_price_currency_code' => $item->default_price_currency_code,
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
