<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SCALE = 6;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('make_orders', function (Blueprint $table): void {
            $table->decimal('runs', 18, 6)
                ->nullable()
                ->after('output_item_id');
            $table->decimal('expected_output_qty', 18, 6)
                ->nullable()
                ->after('runs');
            $table->decimal('actual_output_qty', 18, 6)
                ->nullable()
                ->after('expected_output_qty');
        });

        $rows = DB::table('make_orders')
            ->leftJoin('recipe_versions', 'recipe_versions.id', '=', 'make_orders.recipe_version_id')
            ->leftJoin('recipes', 'recipes.id', '=', 'make_orders.recipe_id')
            ->select([
                'make_orders.id',
                'make_orders.output_quantity',
                'make_orders.actual_output_quantity',
                'recipe_versions.output_quantity as recipe_version_output_quantity',
                'recipes.output_quantity as recipe_output_quantity',
            ])
            ->orderBy('make_orders.id')
            ->get();

        foreach ($rows as $row) {
            $runs = bcadd((string) ($row->output_quantity ?? '0.000000'), '0', self::SCALE);
            $recipeOutputQuantity = (string) ($row->recipe_version_output_quantity ?? $row->recipe_output_quantity ?? '0.000000');
            $expectedOutputQty = bcmul($runs, bcadd($recipeOutputQuantity, '0', self::SCALE), self::SCALE);
            $actualOutputQty = $row->actual_output_quantity !== null
                ? bcadd((string) $row->actual_output_quantity, '0', self::SCALE)
                : null;

            DB::table('make_orders')
                ->where('id', $row->id)
                ->update([
                    'runs' => $runs,
                    'expected_output_qty' => $expectedOutputQty,
                    'actual_output_qty' => $actualOutputQty,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('make_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'runs',
                'expected_output_qty',
                'actual_output_qty',
            ]);
        });
    }
};
