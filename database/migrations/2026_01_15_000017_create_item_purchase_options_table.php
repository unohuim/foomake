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
        Schema::create('item_purchase_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->decimal('pack_quantity', 18, 6);
            $table->foreignId('pack_uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'item_id']);
            $table->index(['tenant_id', 'supplier_sku']);
        });

        $this->addPackQuantityCheck();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropPackQuantityCheck();

        Schema::dropIfExists('item_purchase_options');
    }

    /**
     * Add a database-level check constraint for pack_quantity > 0.
     */
    private function addPackQuantityCheck(): void
    {
        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE item_purchase_options ' .
            'ADD CONSTRAINT item_purchase_options_pack_quantity_positive ' .
            'CHECK (pack_quantity > 0)'
        );
    }

    /**
     * Drop the database-level check constraint for pack_quantity > 0.
     */
    private function dropPackQuantityCheck(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE item_purchase_options ' .
                'DROP CHECK item_purchase_options_pack_quantity_positive'
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE item_purchase_options ' .
                'DROP CONSTRAINT item_purchase_options_pack_quantity_positive'
            );
        }
    }
};
