<script setup>
import { Head } from "@inertiajs/vue3";

import MarketingSearchConsolePanel from "../../components/MarketingSearchConsolePanel.vue";
import AuthShell from "../../layouts/AuthShell.vue";

defineProps({
    shell: {
        type: Object,
        required: true,
    },
    searchConsole: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Marketing" />

    <AuthShell :shell="shell" title="Marketing">
        <div class="flex h-[calc(100vh-4rem)] flex-col px-0 pt-3 md:px-4 lg:px-8">
            <div class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 px-4 sm:px-5 md:px-0">
                <MarketingSearchConsolePanel :search-console="searchConsole" />
            </div>
        </div>
    </AuthShell>
</template>
