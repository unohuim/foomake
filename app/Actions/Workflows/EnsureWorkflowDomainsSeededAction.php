<?php

namespace App\Actions\Workflows;

use App\Models\WorkflowDomain;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure fixed workflow domains exist before tenant-stage seeding or runtime lookups.
 */
class EnsureWorkflowDomainsSeededAction
{
    /**
     * Seed the fixed workflow domains when the backing table exists.
     */
    public function execute(): void
    {
        if (! Schema::hasTable('workflow_domains')) {
            return;
        }

        foreach ([
            ['key' => 'sales', 'name' => 'Sales', 'sort_order' => 10],
            ['key' => 'purchasing', 'name' => 'Purchasing', 'sort_order' => 20],
            ['key' => 'manufacturing', 'name' => 'Manufacturing', 'sort_order' => 30],
        ] as $domain) {
            WorkflowDomain::query()->updateOrCreate([
                'key' => $domain['key'],
            ], $domain);
        }
    }
}
