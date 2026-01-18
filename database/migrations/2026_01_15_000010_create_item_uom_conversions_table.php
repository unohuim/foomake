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
        Schema::create('item_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('from_uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->foreignId('to_uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->decimal('conversion_factor', 12, 6);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'item_id', 'from_uom_id', 'to_uom_id'],
                'item_uom_conversions_unique'
            );
        });

        $this->addConversionFactorCheck();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropConversionFactorCheck();

        Schema::dropIfExists('item_uom_conversions');
    }

    /**
     * Add a database-level check constraint for conversion_factor > 0.
     */
    private function addConversionFactorCheck(): void
    {
        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE item_uom_conversions ' .
            'ADD CONSTRAINT item_uom_conversions_conversion_factor_positive ' .
            'CHECK (conversion_factor > 0)'
        );
    }

    /**
     * Drop the database-level check constraint for conversion_factor > 0.
     */
    private function dropConversionFactorCheck(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE item_uom_conversions ' .
                'DROP CHECK item_uom_conversions_conversion_factor_positive'
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE item_uom_conversions ' .
                'DROP CONSTRAINT item_uom_conversions_conversion_factor_positive'
            );
        }
    }
};
