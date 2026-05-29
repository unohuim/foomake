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

            $this->migrateLegacyDefaultStageKeys($tenant, (int) $domainId, $domainKey);

            foreach ($stages as $stage) {
                WorkflowStage::withoutGlobalScopes()->updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'workflow_domain_id' => $domainId,
                    'key' => $stage['key'],
                ], $this->stagePayload($stage));
            }

            app(EnforceWorkflowStageInventoryEffectInvariantAction::class)
                ->assertDomain($tenant->id, (int) $domainId);
        }
    }

    /**
     * Build a stage payload compatible with the current migrated schema.
     *
     * @param array<string, bool|int|string> $stage
     * @return array<string, bool|int|string|null>
     */
    private function stagePayload(array $stage): array
    {
        $payload = [
            'name' => $stage['name'],
            'description' => null,
            'sort_order' => $stage['sort_order'],
            'is_active' => $stage['is_active'] ?? true,
            'is_inventory_effect_stage' => $stage['is_inventory_effect_stage'],
        ];

        if (Schema::hasColumn('workflow_stages', 'action_verb')) {
            $payload['action_verb'] = $stage['action_verb'];
        }

        if (Schema::hasColumn('workflow_stages', 'status_complete_label')) {
            $payload['status_complete_label'] = $stage['status_complete_label'];
        }

        if (Schema::hasColumn('workflow_stages', 'completion_mode')) {
            $payload['completion_mode'] = $stage['completion_mode'];
        }

        return $payload;
    }

    /**
     * Move old seeded defaults to the new present-tense stage model.
     */
    private function migrateLegacyDefaultStageKeys(Tenant $tenant, int $domainId, string $domainKey): void
    {
        foreach ($this->legacyStageKeyMap($domainKey) as $fromKey => $toKey) {
            $legacyStage = WorkflowStage::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('workflow_domain_id', $domainId)
                ->where('key', $fromKey)
                ->first();

            if (! $legacyStage) {
                continue;
            }

            if ($toKey === null) {
                $legacyStage->forceFill([
                    'is_active' => false,
                    'is_inventory_effect_stage' => false,
                ])->save();
                continue;
            }

            $targetExists = WorkflowStage::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('workflow_domain_id', $domainId)
                ->where('key', $toKey)
                ->whereKeyNot($legacyStage->id)
                ->exists();

            if ($targetExists) {
                $legacyStage->forceFill([
                    'is_active' => false,
                    'is_inventory_effect_stage' => false,
                ])->save();
                continue;
            }

            $legacyStage->forceFill([
                'key' => $toKey,
            ])->save();
        }
    }

    /**
     * Return legacy seeded keys that need to migrate to the current defaults.
     *
     * @return array<string, string|null>
     */
    private function legacyStageKeyMap(string $domainKey): array
    {
        return match ($domainKey) {
            'sales' => [
            ],
            'purchasing' => [
                'completed' => 'completing',
            ],
            'manufacturing' => [
            ],
            'inventory' => [
                'scheduled' => 'creating',
                'completed' => 'completing',
            ],
            default => [],
        };
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
                [
                    'key' => 'creating',
                    'name' => 'Creating',
                    'action_verb' => 'CREATE',
                    'status_complete_label' => 'CREATED',
                    'completion_mode' => 'manual',
                    'sort_order' => 10,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'packing',
                    'name' => 'Packing',
                    'action_verb' => 'PACK',
                    'status_complete_label' => 'PACKED',
                    'completion_mode' => 'manual',
                    'sort_order' => 20,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'shipping',
                    'name' => 'Shipping',
                    'action_verb' => 'SHIP',
                    'status_complete_label' => 'SHIPPED',
                    'completion_mode' => 'manual',
                    'sort_order' => 30,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'packed',
                    'name' => 'Packed',
                    'action_verb' => 'PACKED',
                    'status_complete_label' => 'PACKED',
                    'completion_mode' => 'manual',
                    'sort_order' => 25,
                    'is_inventory_effect_stage' => true,
                ],
                [
                    'key' => 'invoicing',
                    'name' => 'Invoicing',
                    'action_verb' => 'INVOICE',
                    'status_complete_label' => 'INVOICED',
                    'completion_mode' => 'manual',
                    'sort_order' => 40,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'completing',
                    'name' => 'Completing',
                    'action_verb' => 'COMPLETE',
                    'status_complete_label' => 'COMPLETED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 50,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'cancelling',
                    'name' => 'Cancelling',
                    'action_verb' => 'CANCEL',
                    'status_complete_label' => 'CANCELLED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 60,
                    'is_active' => false,
                    'is_inventory_effect_stage' => false,
                ],
            ],
            'purchasing' => [
                [
                    'key' => 'creating',
                    'name' => 'Creating',
                    'action_verb' => 'CREATE',
                    'status_complete_label' => 'CREATED',
                    'completion_mode' => 'manual',
                    'sort_order' => 10,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'receiving',
                    'name' => 'Receiving',
                    'action_verb' => 'RECEIVE',
                    'status_complete_label' => 'RECEIVED',
                    'completion_mode' => 'manual',
                    'sort_order' => 20,
                    'is_inventory_effect_stage' => true,
                ],
                [
                    'key' => 'completing',
                    'name' => 'Completing',
                    'action_verb' => 'COMPLETE',
                    'status_complete_label' => 'COMPLETED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 30,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'cancelling',
                    'name' => 'Cancelling',
                    'action_verb' => 'CANCEL',
                    'status_complete_label' => 'CANCELLED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 40,
                    'is_active' => false,
                    'is_inventory_effect_stage' => false,
                ],
            ],
            'manufacturing' => [
                [
                    'key' => 'creating',
                    'name' => 'Creating',
                    'action_verb' => 'SCHEDULE',
                    'status_complete_label' => 'SCHEDULED',
                    'completion_mode' => 'manual',
                    'sort_order' => 10,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'making',
                    'name' => 'Making',
                    'action_verb' => 'MAKE',
                    'status_complete_label' => 'IN PROGRESS',
                    'completion_mode' => 'manual',
                    'sort_order' => 20,
                    'is_inventory_effect_stage' => true,
                ],
                [
                    'key' => 'completing',
                    'name' => 'Completing',
                    'action_verb' => 'COMPLETE',
                    'status_complete_label' => 'COMPLETED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 30,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'cancelling',
                    'name' => 'Cancelling',
                    'action_verb' => 'CANCEL',
                    'status_complete_label' => 'CANCELLED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 40,
                    'is_active' => false,
                    'is_inventory_effect_stage' => false,
                ],
            ],
            'inventory' => [
                [
                    'key' => 'creating',
                    'name' => 'Creating',
                    'action_verb' => 'SCHEDULE',
                    'status_complete_label' => 'SCHEDULED',
                    'completion_mode' => 'manual',
                    'sort_order' => 10,
                    'is_inventory_effect_stage' => false,
                ],
                [
                    'key' => 'completing',
                    'name' => 'Completing',
                    'action_verb' => 'COMPLETE',
                    'status_complete_label' => 'COMPLETED',
                    'completion_mode' => 'manual',
                    'sort_order' => 20,
                    'is_inventory_effect_stage' => true,
                ],
                [
                    'key' => 'cancelling',
                    'name' => 'Cancelling',
                    'action_verb' => 'CANCEL',
                    'status_complete_label' => 'CANCELLED',
                    'completion_mode' => 'automatic',
                    'sort_order' => 30,
                    'is_active' => false,
                    'is_inventory_effect_stage' => false,
                ],
            ],
        ];
    }
}
