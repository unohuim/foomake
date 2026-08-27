<script setup>
import { Head } from "@inertiajs/vue3";

import ConnectorsPanel from "../../../components/connectors/ConnectorsPanel.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

defineProps({
    shell: {
        type: Object,
        required: true,
    },
    connectors: {
        type: Object,
        required: true,
    },
});

</script>

<template>
    <Head title="Connectors" />

    <AuthShell :shell="shell" title="Connectors">
        <ConnectorsPanel :connectors="connectors" />
    </AuthShell>
</template>
