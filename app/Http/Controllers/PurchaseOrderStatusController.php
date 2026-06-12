<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\BuildWorkflowProgressStepsAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Services\Purchasing\PurchaseOrderLifecycleService;
use App\Services\Workflows\WorkflowTransitionService;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PurchaseOrderStatusController extends Controller
{
    /**
     * Update a purchase order status manually.
     */
    public function update(Request $request, int $purchaseOrder): JsonResponse
    {
        abort_unless(
            app(WorkflowAssignmentPermissions::class)->userCanOperateWorkflowDomain($request->user(), 'purchasing'),
            403
        );

        $purchaseOrder = PurchaseOrder::query()->findOrFail($purchaseOrder);

        $validator = Validator::make($request->all(), [
            'status' => [
                'nullable',
                'required_without:action',
                'string',
                Rule::in(PurchaseOrder::statuses()),
            ],
            'action' => [
                'nullable',
                'required_without:status',
                'string',
                Rule::in([
                    PurchaseOrder::ACTION_CANCEL,
                    PurchaseOrder::ACTION_BACK_ORDER,
                ]),
            ],
        ]);

        $validated = $validator->validate();

        if (isset($validated['action'])) {
            return $this->applyAction($request, $purchaseOrder, $validated['action']);
        }

        return $this->applyStatus($request, $purchaseOrder, $validated['status']);
    }

    /**
     * Apply a strict persisted lifecycle transition.
     */
    private function applyStatus(Request $request, PurchaseOrder $purchaseOrder, string $targetStatus): JsonResponse
    {
        $currentStatus = (string) $purchaseOrder->status;
        $allowed = match (true) {
            $targetStatus === PurchaseOrder::STATUS_CREATED
                && $currentStatus === PurchaseOrder::STATUS_DRAFT => true,
            $targetStatus === PurchaseOrder::STATUS_RECEIVED
                && in_array($currentStatus, [
                    PurchaseOrder::STATUS_CREATED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ], true)
                && $this->allLineBalancesClosed($purchaseOrder) => true,
            $targetStatus === PurchaseOrder::STATUS_COMPLETED
                && $currentStatus === PurchaseOrder::STATUS_RECEIVED => true,
            default => false,
        };

        if ($purchaseOrder->isCancelled()) {
            $allowed = false;
        }

        if (! $allowed) {
            return response()->json([
                'message' => 'Status transition is not allowed.',
                'errors' => [
                    'status' => ['Status transition is not allowed.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($purchaseOrder, $targetStatus): void {
            $purchaseOrder->forceFill(['status' => $targetStatus]);

            if (Schema::hasColumn('purchase_orders', 'current_workflow_stage_id')) {
                $workflowFields = $this->workflowFieldsForStatus($purchaseOrder, $targetStatus);

                if ($workflowFields !== []) {
                    $purchaseOrder->forceFill($workflowFields);
                }
            }

            $purchaseOrder->save();
        });

        $purchaseOrder->refresh();

        return response()->json([
            'data' => $this->statusPayload($purchaseOrder, $request),
        ]);
    }

    /**
     * Apply a lifecycle action.
     */
    private function applyAction(Request $request, PurchaseOrder $purchaseOrder, string $action): JsonResponse
    {
        $allowed = match ($action) {
            PurchaseOrder::ACTION_CANCEL => ! $purchaseOrder->isTerminal()
                && ! $this->hasReceipts($purchaseOrder),
            PurchaseOrder::ACTION_BACK_ORDER => $purchaseOrder->isReceivingStage(),
            default => false,
        };

        if (! $allowed) {
            return response()->json([
                'message' => 'Action is not allowed.',
                'errors' => [
                    'action' => ['Action is not allowed.'],
                ],
            ], 422);
        }

        if ($action === PurchaseOrder::ACTION_CANCEL) {
            $purchaseOrder->forceFill([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'workflow_cancelled_at' => Carbon::now(),
                'cancelled_at' => Carbon::now(),
                'cancelled_by_user_id' => $request->user()?->id,
            ])->save();
        }

        if ($action === PurchaseOrder::ACTION_BACK_ORDER) {
            $purchaseOrder->forceFill(array_merge([
                'back_ordered_at' => Carbon::now(),
                'back_ordered_by_user_id' => $request->user()?->id,
            ], $this->workflowFieldsForStatus($purchaseOrder, PurchaseOrder::STATUS_CREATED)))->save();
        }

        $purchaseOrder->refresh();

        return response()->json([
            'data' => $this->statusPayload($purchaseOrder, $request),
        ]);
    }

    /**
     * Build temporary workflow mirror fields for legacy status endpoint transitions.
     *
     * @return array<string, int|null>
     */
    private function workflowFieldsForStatus(PurchaseOrder $purchaseOrder, string $targetStatus): array
    {
        $tenant = Tenant::query()->find($purchaseOrder->tenant_id);

        if ($tenant) {
            app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($tenant);
        }

        $stages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('workflow_domain_id', $this->purchasingWorkflowDomainId() ?: 0)
            ->whereIn('key', ['creating', 'receiving', 'completing'])
            ->get()
            ->keyBy('key');

        $activeStages = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('workflow_domain_id', $this->purchasingWorkflowDomainId() ?: 0)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        return match ($targetStatus) {
            PurchaseOrder::STATUS_CREATED => [
                'last_completed_workflow_stage_id' => $stages->get('creating')?->id,
                'current_workflow_stage_id' => $stages->get('receiving')?->id
                    ?? $activeStages->first()?->id,
            ],
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [
                'last_completed_workflow_stage_id' => $stages->get('creating')?->id,
                'current_workflow_stage_id' => $stages->get('receiving')?->id
                    ?? $activeStages->first()?->id,
            ],
            PurchaseOrder::STATUS_RECEIVED => [
                'last_completed_workflow_stage_id' => $stages->get('receiving')?->id,
                'current_workflow_stage_id' => $stages->get('completing')?->id
                    ?? $activeStages->get(1)?->id,
            ],
            PurchaseOrder::STATUS_COMPLETED => [
                'last_completed_workflow_stage_id' => $stages->get('completing')?->id,
                'current_workflow_stage_id' => null,
            ],
            default => [],
        };
    }

    /**
     * Resolve the purchasing workflow domain id.
     */
    private function purchasingWorkflowDomainId(): ?int
    {
        $domainId = WorkflowDomain::query()
            ->where('key', 'purchasing')
            ->value('id');

        return $domainId ? (int) $domainId : null;
    }

    /**
     * Build the status response payload used by the PO detail header.
     *
     * @return array<string, bool|int|string|array<int|string, mixed>|null>
     */
    private function statusPayload(PurchaseOrder $purchaseOrder, Request $request): array
    {
        $workflowPayload = app(WorkflowTransitionService::class)->purchaseOrderWorkflowPayload(
            $purchaseOrder,
            $request->user()
        );

        $payload = [
            'status' => $purchaseOrder->workflowStatus(),
            'persisted_status' => $purchaseOrder->status,
            'is_cancelled' => $purchaseOrder->workflow_cancelled_at !== null,
            'is_editable' => $purchaseOrder->isWorkflowEditable(),
            'is_back_ordered' => $purchaseOrder->back_ordered_at !== null,
            'has_receipts' => $this->hasReceipts($purchaseOrder),
            'can_receive' => $this->canReceive($purchaseOrder),
            'workflow' => $workflowPayload,
            'workflowProgressSteps' => app(BuildWorkflowProgressStepsAction::class)->execute(
                (int) $request->user()->tenant_id,
                'purchasing',
                isset($workflowPayload['currentStage']['id']) ? (int) $workflowPayload['currentStage']['id'] : null,
                null,
                $purchaseOrder->last_completed_workflow_stage_id === null
                    ? null
                    : (int) $purchaseOrder->last_completed_workflow_stage_id,
                ! isset($workflowPayload['currentStage']['id'])
                    && $purchaseOrder->last_completed_workflow_stage_id !== null
                    && $purchaseOrder->workflowStatus() === PurchaseOrder::STATUS_COMPLETED
            ),
        ];

        if (Schema::hasColumn('purchase_orders', 'current_workflow_stage_id')) {
            $payload['current_workflow_stage_id'] = $purchaseOrder->getAttribute('current_workflow_stage_id');
            $payload['last_completed_workflow_stage_id'] = $purchaseOrder->getAttribute('last_completed_workflow_stage_id');
        }

        return $payload;
    }

    /**
     * Determine whether a purchase order already has receipts.
     */
    private function hasReceipts(PurchaseOrder $purchaseOrder): bool
    {
        return DB::table('purchase_order_receipts')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->exists();
    }

    /**
     * Determine whether the updated purchase order can immediately open the receive slide-over.
     */
    private function canReceive(PurchaseOrder $purchaseOrder): bool
    {
        if (! $purchaseOrder->isReceivingStage()) {
            return false;
        }

        $lineTotals = app(PurchaseOrderLifecycleService::class)->computeLineTotals(
            $purchaseOrder->fresh(['lines']) ?? $purchaseOrder
        );

        return collect($lineTotals)->contains(fn (array $totals): bool => bccomp(
            $totals['balance'],
            '0',
            6
        ) === 1);
    }

    /**
     * Determine whether every purchase order line has no remaining balance.
     */
    private function allLineBalancesClosed(PurchaseOrder $purchaseOrder): bool
    {
        $lineCount = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->count();

        if ($lineCount === 0) {
            return false;
        }

        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->get(['id', 'pack_count']);

        foreach ($lines as $line) {
            $received = (string) DB::table('purchase_order_receipt_lines')
                ->where('purchase_order_line_id', $line->id)
                ->sum('received_quantity');
            $shortClosed = (string) DB::table('purchase_order_short_closure_lines')
                ->where('purchase_order_line_id', $line->id)
                ->sum('short_closed_quantity');
            $closed = bcadd($received, $shortClosed, 6);

            if (bccomp($closed, (string) $line->pack_count, 6) < 0) {
                return false;
            }
        }

        return true;
    }
}
