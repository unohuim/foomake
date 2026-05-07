<?php

namespace App\Actions\Workflows;

use App\Models\Tenant;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the default sales workflow stages for a tenant.
 */
class SeedDefaultWorkflowStagesForTenantAction
{
    /**
     * Seed the default tenant stages when workflow tables exist.
     */
    public function execute(Tenant $tenant): void
    {
        if (! Schema::hasTable('workflow_domains') || ! Schema::hasTable('workflow_stages')) {
            return;
        }

        app(EnsureWorkflowDomainsSeededAction::class)->execute();

        $salesDomainId = WorkflowDomain::query()
            ->where('key', 'sales')
            ->value('id');

        if (! $salesDomainId) {
            return;
        }

        foreach ([
            ['key' => 'packing', 'name' => 'Packing', 'sort_order' => 10],
            ['key' => 'packed', 'name' => 'Packed', 'sort_order' => 20],
            ['key' => 'shipping', 'name' => 'Shipping', 'sort_order' => 30],
        ] as $stage) {
            WorkflowStage::withoutGlobalScopes()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'workflow_domain_id' => $salesDomainId,
                'key' => $stage['key'],
            ], [
                'name' => $stage['name'],
                'description' => null,
                'sort_order' => $stage['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}
