<?php

namespace Database\Seeders;

use App\Actions\Workflows\EnsureWorkflowDomainsSeededAction;
use Illuminate\Database\Seeder;

class WorkflowDomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();
    }
}
