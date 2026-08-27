<script setup>
import { Head } from "@inertiajs/vue3";

import WorkflowsPanel from "../../../components/admin/WorkflowsPanel.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

defineProps({
    shell: {
        type: Object,
        required: true,
    },
    payload: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Workflows" />

    <AuthShell :shell="shell" title="Workflows">
        <div class="flex h-full min-h-0 flex-col overflow-hidden px-0 md:px-4 lg:px-8">
            <div class="min-h-0 flex-1 overflow-y-auto py-4">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-5 md:px-0">
                    <WorkflowsPanel :payload="payload" />
                </div>
            </div>
        </div>
    </AuthShell>
</template>
