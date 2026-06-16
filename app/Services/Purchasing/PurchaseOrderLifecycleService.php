<?php

namespace App\Services\Purchasing;

use App\Actions\Inventory\ReceivePurchaseOptionAction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\PurchaseOrderShortClosure;
use App\Models\PurchaseOrderShortClosureLine;
use App\Models\StockMove;
use App\Models\User;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderLifecycleService
{
    private const SCALE = 6;

    public function __construct(private readonly ReceivePurchaseOptionAction $receivePurchaseOptionAction)
    {
    }

    /**
     * @param array<int, array{line: PurchaseOrderLine, quantity: string}> $lineItems
     */
    public function createReceipt(
        PurchaseOrder $order,
        User $user,
        array $lineItems,
        ?string $receivedAt,
        ?string $reference,
        ?string $notes
    ): PurchaseOrderReceipt {
        return DB::transaction(function () use ($order, $user, $lineItems, $receivedAt, $reference, $notes) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->ensureReceivableStatus($lockedOrder);
            $this->ensureReceiptLineItemsAreReceivable($lockedOrder, $lineItems);

            $receipt = PurchaseOrderReceipt::query()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'purchase_order_id' => $lockedOrder->id,
                'received_at' => $receivedAt ? Carbon::parse($receivedAt) : Carbon::now(),
                'received_by_user_id' => $user->id,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            foreach ($lineItems as $item) {
                $line = $item['line'];
                $quantity = $this->normalizeQuantity($item['quantity']);
                $stockMoveQuantity = $this->calculateReceiptStockMoveQuantity($line, $quantity);

                $receiptLine = PurchaseOrderReceiptLine::query()->create([
                    'tenant_id' => $lockedOrder->tenant_id,
                    'purchase_order_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line->id,
                    'received_quantity' => $quantity,
                ]);

                if ($line->item?->is_stockable) {
                    $stockMove = StockMove::query()->create([
                        'tenant_id' => $lockedOrder->tenant_id,
                        'item_id' => $line->item_id,
                        'uom_id' => $line->item->base_uom_id,
                        'quantity' => $stockMoveQuantity,
                        'type' => 'receipt',
                        'status' => 'POSTED',
                        'source_type' => 'purchase_order_receipt_line',
                        'source_id' => $receiptLine->id,
                    ]);

                    $receiptLine->forceFill([
                        'stock_move_id' => $stockMove->id,
                    ])->save();
                }
            }

            $this->updateDerivedStatus($lockedOrder);

            return $receipt->fresh(['lines']);
        });
    }

    /**
     * @param array<int, array{line: PurchaseOrderLine, quantity: string}> $lineItems
     */
    public function createShortClosure(
        PurchaseOrder $order,
        User $user,
        array $lineItems,
        ?string $shortClosedAt,
        ?string $reference,
        ?string $notes
    ): PurchaseOrderShortClosure {
        return DB::transaction(function () use ($order, $user, $lineItems, $shortClosedAt, $reference, $notes) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->ensureReceivableStatus($lockedOrder);

            $shortClosure = PurchaseOrderShortClosure::query()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'purchase_order_id' => $lockedOrder->id,
                'short_closed_at' => $shortClosedAt ? Carbon::parse($shortClosedAt) : Carbon::now(),
                'short_closed_by_user_id' => $user->id,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            foreach ($lineItems as $item) {
                $line = $item['line'];
                $quantity = $this->normalizeQuantity($item['quantity']);

                PurchaseOrderShortClosureLine::query()->create([
                    'tenant_id' => $lockedOrder->tenant_id,
                    'purchase_order_short_closure_id' => $shortClosure->id,
                    'purchase_order_line_id' => $line->id,
                    'short_closed_quantity' => $quantity,
                ]);
            }

            $this->updateDerivedStatus($lockedOrder);

            return $shortClosure->fresh(['lines']);
        });
    }

    /**
     * @return array<int, array{pack_count: string, received_sum: string, short_closed_sum: string, balance: string}>
     */
    public function computeLineTotals(PurchaseOrder $order): array
    {
        $totals = [];
        $lines = $order->lines()->get(['id', 'pack_count']);

        foreach ($lines as $line) {
            $packCount = bcadd((string) $line->pack_count, '0', self::SCALE);
            $received = $this->sumReceiptQuantityForLine($line->id, $order->tenant_id);
            $shortClosed = $this->sumShortClosedQuantityForLine($line->id, $order->tenant_id);
            $balance = bcsub(bcsub($packCount, $received, self::SCALE), $shortClosed, self::SCALE);

            $totals[$line->id] = [
                'pack_count' => $packCount,
                'received_sum' => $received,
                'short_closed_sum' => $shortClosed,
                'balance' => $balance,
            ];
        }

        return $totals;
    }

    public function updateDerivedStatus(PurchaseOrder $order): void
    {
        $lineTotals = $this->computeLineTotals($order);
        $allBalancesZero = true;
        $totalReceived = '0.000000';

        foreach ($lineTotals as $totals) {
            if (bccomp($totals['balance'], '0', self::SCALE) !== 0) {
                $allBalancesZero = false;
            }

            $totalReceived = bcadd($totalReceived, $totals['received_sum'], self::SCALE);
        }

        $nextStatus = $order->status;

        if ($allBalancesZero) {
            $nextStatus = PurchaseOrder::STATUS_RECEIVED;
        } elseif (bccomp($totalReceived, '0', self::SCALE) === 1) {
            $nextStatus = PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
        }

        if ($nextStatus !== $order->status) {
            $order->forceFill(array_merge(
                ['status' => $nextStatus],
                $this->workflowFieldsForStatus($order, $nextStatus)
            ))->save();
        }
    }

    /**
     * Mirror legacy status changes into purchase-order workflow fields during migration.
     *
     * @return array<string, int|null>
     */
    private function workflowFieldsForStatus(PurchaseOrder $order, string $status): array
    {
        $domainId = WorkflowDomain::query()
            ->where('key', 'purchasing')
            ->value('id');

        if (! $domainId) {
            return [];
        }

        $stages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->where('workflow_domain_id', $domainId)
            ->whereIn('key', ['creating', 'receiving', 'completing'])
            ->get()
            ->keyBy('key');

        $activeStages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $order->tenant_id)
            ->where('workflow_domain_id', $domainId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $creatingStage = $stages->get('creating') ?? $activeStages->first();
        $inventoryEffectStage = $activeStages->firstWhere('is_inventory_effect_stage', true)
            ?? $stages->get('receiving')
            ?? $activeStages->get(1);
        $completingStage = $stages->get('completing') ?? $activeStages->last();

        return match ($status) {
            PurchaseOrder::STATUS_CREATED => [
                'last_completed_workflow_stage_id' => $creatingStage?->id,
                'current_workflow_stage_id' => $inventoryEffectStage?->id,
            ],
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [
                'last_completed_workflow_stage_id' => $creatingStage?->id,
                'current_workflow_stage_id' => $inventoryEffectStage?->id,
            ],
            PurchaseOrder::STATUS_RECEIVED => [
                'last_completed_workflow_stage_id' => $inventoryEffectStage?->id,
                'current_workflow_stage_id' => $completingStage?->id,
            ],
            PurchaseOrder::STATUS_COMPLETED => [
                'last_completed_workflow_stage_id' => $completingStage?->id,
                'current_workflow_stage_id' => null,
            ],
            default => [],
        };
    }

    private function ensureReceivableStatus(PurchaseOrder $order): void
    {
        $allowedStatuses = [
            PurchaseOrder::STATUS_CREATED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ];

        if (! in_array($order->status, $allowedStatuses, true) || $order->isCancelled()) {
            throw new DomainException('Purchase order is not in a receivable status.');
        }
    }

    /**
     * Validate receipt line quantities against the locked purchase order balance.
     *
     * @param array<int, array{line: PurchaseOrderLine, quantity: string}> $lineItems
     */
    private function ensureReceiptLineItemsAreReceivable(PurchaseOrder $order, array $lineItems): void
    {
        $lineTotals = $this->computeLineTotals($order);
        $requestedByLine = [];

        foreach ($lineItems as $item) {
            $line = $item['line'];
            $quantity = $this->normalizeQuantity($item['quantity']);

            if ((int) $line->purchase_order_id !== (int) $order->id || (int) $line->tenant_id !== (int) $order->tenant_id) {
                throw new DomainException('Line is invalid for this purchase order.');
            }

            if (bccomp($quantity, '0', self::SCALE) <= 0) {
                throw new DomainException('Quantity must be greater than zero.');
            }

            $requestedByLine[$line->id] = bcadd(
                $requestedByLine[$line->id] ?? '0.000000',
                $quantity,
                self::SCALE
            );
        }

        if ($requestedByLine === []) {
            throw new DomainException('At least one positive received quantity is required.');
        }

        foreach ($requestedByLine as $lineId => $quantity) {
            $balance = $lineTotals[$lineId]['balance'] ?? '0.000000';

            if (bccomp($quantity, $balance, self::SCALE) === 1) {
                throw new DomainException('Quantity exceeds remaining balance.');
            }
        }
    }

    private function sumReceiptQuantityForLine(int $lineId, int $tenantId): string
    {
        $total = '0.000000';

        $rows = DB::table('purchase_order_receipt_lines')
            ->where('tenant_id', $tenantId)
            ->where('purchase_order_line_id', $lineId)
            ->get(['received_quantity']);

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->received_quantity, self::SCALE);
        }

        return $total;
    }

    private function sumShortClosedQuantityForLine(int $lineId, int $tenantId): string
    {
        $total = '0.000000';

        $rows = DB::table('purchase_order_short_closure_lines')
            ->where('tenant_id', $tenantId)
            ->where('purchase_order_line_id', $lineId)
            ->get(['short_closed_quantity']);

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->short_closed_quantity, self::SCALE);
        }

        return $total;
    }

    private function normalizeQuantity(string $value): string
    {
        $normalized = bcadd($value, '0', self::SCALE);

        if (str_contains($normalized, '.')) {
            [$whole, $fraction] = explode('.', $normalized, 2);
            $fraction = str_pad(substr($fraction, 0, self::SCALE), self::SCALE, '0');
            return $whole . '.' . $fraction;
        }

        return $normalized . '.' . str_repeat('0', self::SCALE);
    }

    private function calculateReceiptStockMoveQuantity(PurchaseOrderLine $line, string $receivedQuantity): string
    {
        $purchaseOption = $line->purchaseOption;

        if ($purchaseOption === null) {
            throw new DomainException('Purchase order line is missing a purchase option pack quantity.');
        }

        return $this->receivePurchaseOptionAction->baseQuantityFor($purchaseOption, $receivedQuantity);
    }
}
