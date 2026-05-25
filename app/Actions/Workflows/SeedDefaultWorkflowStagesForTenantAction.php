<?php

namespace App\Actions\Workflows;

use App\Models\Tenant;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the default workflow stages for a tenant.
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

        $domains = WorkflowDomain::query()
            ->whereIn('key', ['sales', 'purchasing', 'manufacturing', 'inventory'])
            ->get()
            ->keyBy('key');

        foreach ($this->defaultStages() as $domainKey => $stages) {
            $domainId = $domains->get($domainKey)?->id;

            if (! $domainId) {
                continue;
            }

            foreach ($stages as $stage) {
                WorkflowStage::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'workflow_domain_id' => $domainId,
                    'key' => $stage['key'],
                ], [
                    'name' => $stage['name'],
                    'description' => null,
                    'sort_order' => $stage['sort_order'],
                    'is_active' => true,
                    'is_inventory_effect_stage' => $stage['is_inventory_effect_stage'],
                ]);
            }

            app(EnforceWorkflowStageInventoryEffectInvariantAction::class)
                ->assertDomain($tenant->id, (int) $domainId);
        }
    }

    /**
     * Return the seeded default stages for each supported workflow domain.
     *
     * @return array<string, array<int, array<string, bool|int|string>>>
     */
    private function defaultStages(): array
    {
        return [
            'sales' => [
                ['key' => 'packing', 'name' => 'Packing', 'sort_order' => 10, 'is_inventory_effect_stage' => false],
                ['key' => 'packed', 'name' => 'Packed', 'sort_order' => 20, 'is_inventory_effect_stage' => true],
                ['key' => 'shipping', 'name' => 'Shipping', 'sort_order' => 30, 'is_inventory_effect_stage' => false],
            ],
            'purchasing' => [
                ['key' => 'receiving', 'name' => 'Receiving', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
                ['key' => 'completed', 'name' => 'Completed', 'sort_order' => 20, 'is_inventory_effect_stage' => false],
            ],
            'manufacturing' => [
                ['key' => 'production', 'name' => 'Production', 'sort_order' => 10, 'is_inventory_effect_stage' => true],
                ['key' => 'completed', 'name' => 'Completed', 'sort_order' => 20, 'is_inventory_effect_stage' => false],
            ],
            'inventory' => [
                ['key' => 'open', 'name' => 'Open', 'sort_order' => 10, 'is_inventory_effect_stage' => false],
                ['key' => 'completed', 'name' => 'Completed', 'sort_order' => 20, 'is_inventory_effect_stage' => true],
            ],
        ];
    }
}
