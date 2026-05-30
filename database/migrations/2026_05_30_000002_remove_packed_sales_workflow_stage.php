<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $salesDomainIds = DB::table('workflow_domains')
            ->where('key', 'sales')
            ->pluck('id');

        if ($salesDomainIds->isEmpty()) {
            return;
        }

        DB::table('workflow_stages')
            ->whereIn('workflow_domain_id', $salesDomainIds)
            ->where('key', 'packing')
            ->update(['is_inventory_effect_stage' => true]);

        DB::table('workflow_stages')
            ->whereIn('workflow_domain_id', $salesDomainIds)
            ->where('key', 'packed')
            ->where('name', 'Packed')
            ->where('status_complete_label', 'PACKED')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Removed duplicate seeded stages are not restored automatically.
    }
};
