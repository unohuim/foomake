<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropPurchaseOrderStatusCheck();
        $this->addPurchaseOrderStatusCheck([
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_CREATED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_COMPLETED,
            PurchaseOrder::STATUS_CANCELLED,
        ]);
        $this->updatePurchasingCreatingStatusLabel(PurchaseOrder::STATUS_CREATED);
        $this->backfillPartiallyReceivedWorkflowState();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('purchase_orders')
            ->where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
            ->update(['status' => PurchaseOrder::STATUS_CREATED]);

        $this->dropPurchaseOrderStatusCheck();
        $this->addPurchaseOrderStatusCheck([
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_CREATED,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_COMPLETED,
            PurchaseOrder::STATUS_CANCELLED,
        ]);
        $this->updatePurchasingCreatingStatusLabel('CREATED');
    }

    /**
     * Add the purchase order status check for drivers that enforce named checks.
     *
     * @param array<int, string> $statuses
     */
    private function addPurchaseOrderStatusCheck(array $statuses): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $quotedStatuses = collect($statuses)
            ->map(fn (string $status): string => "'" . str_replace("'", "''", $status) . "'")
            ->implode(', ');

        DB::statement(
            "ALTER TABLE purchase_orders " .
            "ADD CONSTRAINT purchase_orders_status_allowed " .
            "CHECK (status IN ({$quotedStatuses}))"
        );
    }

    /**
     * Drop the purchase order status check for drivers that enforce named checks.
     */
    private function dropPurchaseOrderStatusCheck(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE purchase_orders ' .
                'DROP CHECK purchase_orders_status_allowed'
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE purchase_orders ' .
                'DROP CONSTRAINT purchase_orders_status_allowed'
            );
        }
    }

    /**
     * Keep seeded purchasing workflow status labels aligned with persisted PO statuses.
     */
    private function updatePurchasingCreatingStatusLabel(string $statusLabel): void
    {
        if (
            ! Schema::hasTable('workflow_domains')
            || ! Schema::hasTable('workflow_stages')
            || ! Schema::hasColumn('workflow_stages', 'status_complete_label')
        ) {
            return;
        }

        $domainId = DB::table('workflow_domains')
            ->where('key', 'purchasing')
            ->value('id');

        if (! $domainId) {
            return;
        }

        DB::table('workflow_stages')
            ->where('workflow_domain_id', $domainId)
            ->where('key', 'creating')
            ->update(['status_complete_label' => $statusLabel]);
    }

    /**
     * Mirror any already-partial POs into the receiving workflow stage.
     */
    private function backfillPartiallyReceivedWorkflowState(): void
    {
        if (
            ! Schema::hasTable('workflow_domains')
            || ! Schema::hasTable('workflow_stages')
            || ! Schema::hasColumn('purchase_orders', 'current_workflow_stage_id')
            || ! Schema::hasColumn('purchase_orders', 'last_completed_workflow_stage_id')
        ) {
            return;
        }

        $domainId = DB::table('workflow_domains')
            ->where('key', 'purchasing')
            ->value('id');

        if (! $domainId) {
            return;
        }

        $stages = DB::table('workflow_stages')
            ->where('workflow_domain_id', $domainId)
            ->whereIn('key', ['creating', 'receiving'])
            ->pluck('id', 'key');

        DB::table('purchase_orders')
            ->where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
            ->update([
                'last_completed_workflow_stage_id' => $stages['creating'] ?? null,
                'current_workflow_stage_id' => $stages['receiving'] ?? null,
            ]);
    }
};
