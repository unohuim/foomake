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
        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->boolean('is_inventory_effect_stage')
                ->default(false)
                ->after('is_active');
        });

        $salesDomainId = DB::table('workflow_domains')
            ->where('key', 'sales')
            ->value('id');

        if ($salesDomainId !== null) {
            DB::table('workflow_stages')
                ->where('workflow_domain_id', $salesDomainId)
                ->where('key', 'packed')
                ->update([
                    'is_inventory_effect_stage' => true,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropColumn('is_inventory_effect_stage');
        });
    }
};
