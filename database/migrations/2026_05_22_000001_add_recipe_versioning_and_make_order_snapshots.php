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
        Schema::create('recipe_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('name')->nullable();
            $table->decimal('output_quantity', 18, 6);
            $table->string('recipe_type')->default('manufacturing');
            $table->string('status')->default('PUBLISHED');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'recipe_id', 'version_number']);
            $table->index(['tenant_id', 'recipe_id', 'status']);
        });

        Schema::table('recipes', function (Blueprint $table): void {
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('item_id')
                ->constrained('recipe_versions')
                ->nullOnDelete();
        });

        Schema::create('recipe_version_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->cascadeOnDelete();
            $table->foreignId('input_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['recipe_version_id', 'sort_order']);
        });

        Schema::create('recipe_version_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_out_at');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'recipe_id', 'user_id', 'checked_in_at'],
                'rvco_recipe_user_open_idx'
            );
            $table->index(
                ['tenant_id', 'recipe_version_id', 'user_id', 'checked_in_at'],
                'rvco_open_user_version_idx'
            );
        });

        Schema::table('make_orders', function (Blueprint $table): void {
            $table->foreignId('recipe_version_id')
                ->nullable()
                ->after('recipe_id')
                ->constrained('recipe_versions')
                ->nullOnDelete();
        });

        Schema::create('make_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('make_order_id')->constrained('make_orders')->cascadeOnDelete();
            $table->foreignId('source_recipe_version_line_id')
                ->nullable()
                ->constrained('recipe_version_lines')
                ->nullOnDelete();
            $table->foreignId('input_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->decimal('planned_quantity', 18, 6);
            $table->decimal('actual_quantity', 18, 6)->nullable();
            $table->string('line_type')->default('recipe');
            $table->timestamps();

            $table->index(['make_order_id', 'line_type']);
        });

        $this->backfillRecipeVersions();
        $this->backfillMakeOrderSnapshots();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('make_order_lines');

        Schema::table('make_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipe_version_id');
        });

        Schema::dropIfExists('recipe_version_checkouts');
        Schema::dropIfExists('recipe_version_lines');

        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('recipe_versions');
    }

    /**
     * Backfill one published version per existing recipe and mirror the current lines.
     */
    private function backfillRecipeVersions(): void
    {
        $recipes = DB::table('recipes')
            ->orderBy('id')
            ->get();

        foreach ($recipes as $recipe) {
            $versionId = DB::table('recipe_versions')->insertGetId([
                'tenant_id' => $recipe->tenant_id,
                'recipe_id' => $recipe->id,
                'version_number' => 100,
                'name' => null,
                'output_quantity' => $recipe->output_quantity ?? '0.000000',
                'recipe_type' => $recipe->recipe_type ?? 'manufacturing',
                'status' => 'PUBLISHED',
                'effective_from' => $recipe->created_at,
                'approved_at' => $recipe->created_at,
                'approved_by_user_id' => null,
                'notes' => null,
                'created_at' => $recipe->created_at,
                'updated_at' => $recipe->updated_at,
            ]);

            DB::table('recipes')
                ->where('id', $recipe->id)
                ->update(['current_version_id' => $versionId]);

            $lines = DB::table('recipe_lines')
                ->where('recipe_id', $recipe->id)
                ->orderBy('id')
                ->get();

            foreach ($lines as $index => $line) {
                $uomId = DB::table('items')->where('id', $line->item_id)->value('base_uom_id');

                DB::table('recipe_version_lines')->insert([
                    'tenant_id' => $line->tenant_id,
                    'recipe_version_id' => $versionId,
                    'input_item_id' => $line->item_id,
                    'uom_id' => $uomId,
                    'quantity' => $line->quantity,
                    'sort_order' => $index + 1,
                    'created_at' => $line->created_at,
                    'updated_at' => $line->updated_at,
                ]);
            }
        }
    }

    /**
     * Backfill make-order snapshot versions and lines from current published recipe state.
     */
    private function backfillMakeOrderSnapshots(): void
    {
        $makeOrders = DB::table('make_orders')
            ->orderBy('id')
            ->get();

        foreach ($makeOrders as $makeOrder) {
            $recipe = DB::table('recipes')->where('id', $makeOrder->recipe_id)->first();

            if (! $recipe || $recipe->current_version_id === null) {
                continue;
            }

            DB::table('make_orders')
                ->where('id', $makeOrder->id)
                ->update(['recipe_version_id' => $recipe->current_version_id]);

            $versionLines = DB::table('recipe_version_lines')
                ->where('recipe_version_id', $recipe->current_version_id)
                ->orderBy('sort_order')
                ->get();

            foreach ($versionLines as $versionLine) {
                DB::table('make_order_lines')->insert([
                    'tenant_id' => $makeOrder->tenant_id,
                    'make_order_id' => $makeOrder->id,
                    'source_recipe_version_line_id' => $versionLine->id,
                    'input_item_id' => $versionLine->input_item_id,
                    'uom_id' => $versionLine->uom_id,
                    'planned_quantity' => bcmul(
                        (string) $versionLine->quantity,
                        (string) $makeOrder->output_quantity,
                        self::SCALE
                    ),
                    'actual_quantity' => null,
                    'line_type' => 'recipe',
                    'created_at' => $makeOrder->created_at,
                    'updated_at' => $makeOrder->updated_at,
                ]);
            }
        }
    }
};
