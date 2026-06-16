<?php

namespace App\Contracts\Workflows;

/**
 * Interface for domain records that participate in configured workflows.
 */
interface Workflowable
{
    /**
     * Return the workflow domain key for this record.
     */
    public function workflowDomainKey(): string;

    /**
     * Return the record id used by generated workflow tasks.
     */
    public function workflowRecordId(): int;

    /**
     * Return the tenant id that owns this workflow record.
     */
    public function workflowTenantId(): int;

    /**
     * Return the persisted workflow status value.
     */
    public function workflowStatus(): string;

    /**
     * Set the persisted workflow status value.
     */
    public function setWorkflowStatus(string $status): void;
}
