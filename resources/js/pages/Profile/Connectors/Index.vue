<script setup>
import { Head } from "@inertiajs/vue3";

import WooCommerceConnectorCard from "../../../components/connectors/WooCommerceConnectorCard.vue";
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
        <div class="px-0 pb-6 pt-2 md:px-4 md:pt-3 lg:px-8">
            <div class="mx-auto w-full max-w-3xl px-4 sm:px-5 md:px-0">
                <WooCommerceConnectorCard
                    :connector="connectors.wooCommerce"
                    :plugin-connection="connectors.wordPressPlugin"
                    :plugin-download-url="connectors.pluginDownloadUrl"
                    :plugin-revoke-url="connectors.pluginRevokeUrl"
                    :store-url="connectors.storeUrl"
                    :disconnect-url="connectors.disconnectUrl"
                    :csrf-token="connectors.csrfToken"
                />
            </div>
        </div>
    </AuthShell>
</template>
