<script setup>
import { Head } from "@inertiajs/vue3";

import WooCommerceConnectorCard from "../../../components/connectors/WooCommerceConnectorCard.vue";
import WordPressPluginConnectionCard from "../../../components/connectors/WordPressPluginConnectionCard.vue";
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
        <div class="px-0 pb-10 pt-4 md:px-4 md:pt-5 lg:px-8">
            <div class="mx-auto w-full max-w-5xl space-y-5 px-4 sm:px-6 md:px-0">
                <WordPressPluginConnectionCard
                    :connection="connectors.wordPressPlugin"
                    :download-url="connectors.pluginDownloadUrl"
                    :revoke-url="connectors.pluginRevokeUrl"
                    :csrf-token="connectors.csrfToken"
                />

                <WooCommerceConnectorCard
                    :connector="connectors.wooCommerce"
                    :store-url="connectors.storeUrl"
                    :disconnect-url="connectors.disconnectUrl"
                    :csrf-token="connectors.csrfToken"
                />
            </div>
        </div>
    </AuthShell>
</template>
