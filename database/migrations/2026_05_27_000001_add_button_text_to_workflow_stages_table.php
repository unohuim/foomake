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
            $table->string('button_text')->nullable()->after('name');
        });

        DB::table('workflow_stages')
            ->whereNull('button_text')
            ->update(['button_text' => DB::raw('name')]);

        $inventoryDomainId = DB::table('workflow_domains')
            ->where('key', 'inventory')
            ->value('id');

        if ($inventoryDomainId !== null) {
            $openStages = DB::table('workflow_stages')
                ->select(['id', 'tenant_id'])
                ->where('workflow_domain_id', $inventoryDomainId)
                ->where('key', 'open')
                ->get();

            foreach ($openStages as $openStage) {
                $scheduledExists = DB::table('workflow_stages')
                    ->where('tenant_id', $openStage->tenant_id)
                    ->where('workflow_domain_id', $inventoryDomainId)
                    ->where('key', 'scheduled')
                    ->exists();

                if ($scheduledExists) {
                    continue;
                }

                DB::table('workflow_stages')
                    ->where('id', $openStage->id)
                    ->update([
                        'key' => 'scheduled',
                        'name' => 'SCHEDULED',
                        'button_text' => 'SCHEDULE',
                    ]);
            }

            DB::table('workflow_stages')
                ->where('workflow_domain_id', $inventoryDomainId)
                ->where('key', 'completed')
                ->update([
                    'name' => 'COMPLETED',
                    'button_text' => 'COMPLETE',
                ]);
        }

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->string('button_text')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $inventoryDomainId = DB::table('workflow_domains')
            ->where('key', 'inventory')
            ->value('id');

        if ($inventoryDomainId !== null) {
            $scheduledStages = DB::table('workflow_stages')
                ->select(['id', 'tenant_id'])
                ->where('workflow_domain_id', $inventoryDomainId)
                ->where('key', 'scheduled')
                ->where('name', 'SCHEDULED')
                ->where('button_text', 'SCHEDULE')
                ->get();

            foreach ($scheduledStages as $scheduledStage) {
                $openExists = DB::table('workflow_stages')
                    ->where('tenant_id', $scheduledStage->tenant_id)
                    ->where('workflow_domain_id', $inventoryDomainId)
                    ->where('key', 'open')
                    ->exists();

                if ($openExists) {
                    continue;
                }

                DB::table('workflow_stages')
                    ->where('id', $scheduledStage->id)
                    ->update([
                        'key' => 'open',
                        'name' => 'Open',
                    ]);
            }

            DB::table('workflow_stages')
                ->where('workflow_domain_id', $inventoryDomainId)
                ->where('key', 'completed')
                ->where('name', 'COMPLETED')
                ->where('button_text', 'COMPLETE')
                ->update([
                    'name' => 'Completed',
                ]);
        }

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropColumn('button_text');
        });
    }
};
