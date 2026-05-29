<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('purchase_orders')
            ->whereNotNull('cancelled_at')
            ->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        $this->addPurchaseOrderStatusCheck();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropPurchaseOrderStatusCheck();
    }

    /**
     * Add a database-level check constraint for purchase order statuses.
     */
    private function addPurchaseOrderStatusCheck(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            "ALTER TABLE purchase_orders " .
            "ADD CONSTRAINT purchase_orders_status_allowed " .
            "CHECK (status IN ('DRAFT', 'SENT', 'RECEIVED', 'COMPLETED', 'CANCELLED'))"
        );
    }

    /**
     * Drop the database-level check constraint for purchase order statuses.
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
};
