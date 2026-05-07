<?php

namespace Database\Seeders;

use App\Actions\Workflows\EnsureWorkflowDomainsSeededAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class WorkflowStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();

        $action = app(SeedDefaultWorkflowStagesForTenantAction::class);

        Tenant::query()->each(function (Tenant $tenant) use ($action): void {
            $action->execute($tenant);
        });
    }
}
