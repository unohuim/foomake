<?php

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
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->cascadeOnDelete();
            if (DB::getDriverName() === 'sqlite') {
                $table->text('quantity');
            } else {
                $table->decimal('quantity', 18, 6);
            }
            $table->timestamps();

            $table->unique(['tenant_id', 'item_id', 'uom_id'], 'inventory_balances_unique');
            $table->index(['tenant_id', 'item_id']);
        });

        $this->backfillBalances();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }

    /**
     * Backfill balances from posted stock moves.
     */
    private function backfillBalances(): void
    {
        DB::table('stock_moves')
            ->select('tenant_id', 'item_id', 'uom_id')
            ->selectRaw('SUM(quantity) as quantity')
            ->where(function ($query): void {
                $query->where('status', 'POSTED')
                    ->orWhereNull('status');
            })
            ->groupBy('tenant_id', 'item_id', 'uom_id')
            ->orderBy('tenant_id')
            ->orderBy('item_id')
            ->orderBy('uom_id')
            ->chunk(500, function ($balances): void {
                $now = now();

                foreach ($balances as $balance) {
                    DB::table('inventory_balances')->insert([
                        'tenant_id' => $balance->tenant_id,
                        'item_id' => $balance->item_id,
                        'uom_id' => $balance->uom_id,
                        'quantity' => bcadd((string) $balance->quantity, '0', 6),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
