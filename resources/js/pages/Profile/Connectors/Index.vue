<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

import GoogleSearchConsoleConnectorCard from "../../../components/connectors/GoogleSearchConsoleConnectorCard.vue";
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

const page = usePage();
const googleSearchConsoleError = computed(() => page.props.errors?.google_search_console || "");
</script>

<template>
    <Head title="Connectors" />

    <AuthShell :shell="shell" title="Connectors">
        <div class="px-0 pb-6 pt-2 md:px-4 md:pt-3 lg:px-8">
            <div class="mx-auto w-full max-w-3xl space-y-4 px-4 sm:px-5 md:px-0">
                <div v-if="googleSearchConsoleError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                    {{ googleSearchConsoleError }}
                </div>
                <WooCommerceConnectorCard
                    :connector="connectors.wooCommerce"
                    :plugin-connection="connectors.wordPressPlugin"
                    :plugin-download-url="connectors.pluginDownloadUrl"
                    :plugin-revoke-url="connectors.pluginRevokeUrl"
                    :store-url="connectors.storeUrl"
                    :disconnect-url="connectors.disconnectUrl"
                    :csrf-token="connectors.csrfToken"
                />
                <GoogleSearchConsoleConnectorCard
                    :connector="connectors.googleSearchConsole"
                    :connect-url="connectors.googleSearchConsoleConnectUrl"
                    :disconnect-url="connectors.googleSearchConsoleDisconnectUrl"
                    :performance-url="connectors.googleSearchConsolePerformanceUrl"
                    :report-url="connectors.googleSearchConsoleReportUrl"
                    :csrf-token="connectors.csrfToken"
                />
            </div>
        </div>
    </AuthShell>
</template>
