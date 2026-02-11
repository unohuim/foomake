<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderReceiptController extends Controller
{
    private const SCALE = 6;

    /**
     * Store a new purchase order receipt event.
     */
    public function store(
        Request $request,
        int $purchaseOrder,
        PurchaseOrderLifecycleService $lifecycleService
    ): JsonResponse {
        Gate::authorize('purchasing-purchase-orders-receive');

        $purchaseOrder = PurchaseOrder::query()->findOrFail($purchaseOrder);

        $hasLinesArray = is_array($request->input('lines'));

        $rules = [
            'received_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        if ($hasLinesArray) {
            $rules['lines'] = ['required', 'array', 'min:1'];
            $rules['lines.*.purchase_order_line_id'] = ['required', 'integer'];
            $rules['lines.*.received_quantity'] = ['required', 'numeric'];
        } else {
            $rules['purchase_order_line_id'] = ['required', 'integer'];
            $rules['received_quantity'] = ['required', 'numeric'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $purchaseOrder, $hasLinesArray, $lifecycleService) {
            $lineInputs = $hasLinesArray ? (array) $request->input('lines') : [[
                'purchase_order_line_id' => $request->input('purchase_order_line_id'),
                'received_quantity' => $request->input('received_quantity'),
            ]];

            if ($hasLinesArray && count($lineInputs) === 0) {
                $validator->errors()->add('lines', 'At least one line is required.');
                return;
            }

            $lineIds = collect($lineInputs)
                ->pluck('purchase_order_line_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $orderLines = $purchaseOrder->lines()
                ->whereIn('id', $lineIds)
                ->get()
                ->keyBy('id');

            $lineTotals = $lifecycleService->computeLineTotals($purchaseOrder);

            foreach ($lineInputs as $index => $lineInput) {
                $prefix = $hasLinesArray ? "lines.{$index}." : '';
                $lineId = (int) ($lineInput['purchase_order_line_id'] ?? 0);
                $quantity = (string) ($lineInput['received_quantity'] ?? '');

                if (! $lineId || ! $orderLines->has($lineId)) {
                    $validator->errors()->add("{$prefix}purchase_order_line_id", 'Line is invalid for this purchase order.');
                    continue;
                }

                if (bccomp($quantity, '0', self::SCALE) <= 0) {
                    $validator->errors()->add("{$prefix}received_quantity", 'Quantity must be greater than zero.');
                    continue;
                }

                $balance = $lineTotals[$lineId]['balance'] ?? '0.000000';

                if (bccomp($quantity, $balance, self::SCALE) === 1) {
                    $validator->errors()->add("{$prefix}received_quantity", 'Quantity exceeds remaining balance.');
                }
            }
        });

        $validated = $validator->validate();

        $lineInputs = $hasLinesArray ? (array) $request->input('lines') : [[
            'purchase_order_line_id' => $validated['purchase_order_line_id'] ?? null,
            'received_quantity' => $validated['received_quantity'] ?? null,
        ]];

        $lineIds = collect($lineInputs)
            ->pluck('purchase_order_line_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $orderLines = $purchaseOrder->lines()
            ->whereIn('id', $lineIds)
            ->get()
            ->keyBy('id');

        $lineItems = [];

        foreach ($lineInputs as $lineInput) {
            $lineId = (int) $lineInput['purchase_order_line_id'];
            $line = $orderLines->get($lineId);

            if (! $line) {
                continue;
            }

            $lineItems[] = [
                'line' => $line,
                'quantity' => (string) $lineInput['received_quantity'],
            ];
        }

        try {
            $receipt = $lifecycleService->createReceipt(
                $purchaseOrder,
                $request->user(),
                $lineItems,
                $validated['received_at'] ?? null,
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'status' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $receipt->id,
            ],
        ], 201);
    }
}
