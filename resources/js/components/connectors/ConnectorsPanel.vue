<script setup>
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

import GoogleSearchConsoleConnectorCard from "./GoogleSearchConsoleConnectorCard.vue";
import WooCommerceConnectorCard from "./WooCommerceConnectorCard.vue";

const props = defineProps({
    connectors: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const activeConnector = ref(initialConnector());
const googleSearchConsoleError = computed(() => page.props.errors?.google_search_console || "");
const connectorTabs = computed(() => [
    {
        key: "woo",
        label: "Woo",
    },
    {
        key: "google",
        label: "Google",
    },
]);

function initialConnector() {
    const query = String(page.url || "").split("?", 2)[1] || "";
    const connector = new URLSearchParams(query).get("connector");

    return connector === "google" ? "google" : "woo";
}
</script>

<template>
    <div class="mt-3 flex h-full min-h-0 flex-col">
        <div v-if="googleSearchConsoleError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
            {{ googleSearchConsoleError }}
        </div>

        <nav class="shrink-0 overflow-x-auto" aria-label="Connectors">
            <div class="flex min-w-max">
                <button
                    v-for="(tab, index) in connectorTabs"
                    :key="tab.key"
                    type="button"
                    class="relative flex cursor-pointer items-center border border-b-0 border-slate-200 px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-[#111b31] shadow-sm transition hover:text-[#1849ff]"
                    :class="[
                        tab.key === activeConnector ? 'bg-white' : 'bg-gray-100',
                        index === 0 ? 'rounded-tl-lg' : '',
                        index === connectorTabs.length - 1 ? 'rounded-tr-lg' : '',
                        connectorTabs.length > 1 && index === 0 ? 'border-r-0' : '',
                    ]"
                    @click="activeConnector = tab.key"
                >
                    <span>{{ tab.label }}</span>
                </button>
            </div>
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto pb-4">
            <WooCommerceConnectorCard
                v-if="activeConnector === 'woo'"
                :connector="connectors.wooCommerce"
                :plugin-connection="connectors.wordPressPlugin"
                :plugin-download-url="connectors.pluginDownloadUrl"
                :plugin-revoke-url="connectors.pluginRevokeUrl"
                :store-url="connectors.storeUrl"
                :disconnect-url="connectors.disconnectUrl"
                :csrf-token="connectors.csrfToken"
                square-top
            />
            <GoogleSearchConsoleConnectorCard
                v-if="activeConnector === 'google'"
                :connector="connectors.googleSearchConsole"
                :connect-url="connectors.googleSearchConsoleConnectUrl"
                :disconnect-url="connectors.googleSearchConsoleDisconnectUrl"
                :performance-url="connectors.googleSearchConsolePerformanceUrl"
                :refresh-url="connectors.googleSearchConsoleRefreshUrl"
                :report-url="connectors.googleSearchConsoleReportUrl"
                :csrf-token="connectors.csrfToken"
                square-top
            />
        </div>
    </div>
</template>
