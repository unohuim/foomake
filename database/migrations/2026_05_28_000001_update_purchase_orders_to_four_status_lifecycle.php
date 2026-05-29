<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->after('cancelled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('back_ordered_at')->nullable()->after('cancelled_by_user_id');
            $table->foreignId('back_ordered_by_user_id')
                ->nullable()
                ->after('back_ordered_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->unsignedInteger('line_tax_rate_bps')->default(0)->after('line_subtotal_cents');
        });

        DB::table('purchase_orders')
            ->whereIn('status', ['OPEN', 'PARTIALLY-RECEIVED', 'BACK-ORDERED'])
            ->update(['status' => PurchaseOrder::STATUS_SENT]);

        DB::table('purchase_orders')
            ->where('status', 'SHORT-CLOSED')
            ->update(['status' => PurchaseOrder::STATUS_RECEIVED]);

        DB::table('purchase_orders')
            ->where('status', 'CANCELLED')
            ->update([
                'status' => PurchaseOrder::STATUS_SENT,
                'cancelled_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('line_tax_rate_bps');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('back_ordered_by_user_id');
            $table->dropColumn('back_ordered_at');
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn('cancelled_at');
        });
    }
};

