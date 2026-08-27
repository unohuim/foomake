<script setup>
import { computed, ref } from "vue";

import ConnectorStatusBadge from "./ConnectorStatusBadge.vue";

const props = defineProps({
    connector: {
        type: Object,
        required: true,
    },
    connectUrl: {
        type: String,
        required: true,
    },
    disconnectUrl: {
        type: String,
        required: true,
    },
    performanceUrl: {
        type: String,
        required: true,
    },
    refreshUrl: {
        type: String,
        required: true,
    },
    reportUrl: {
        type: String,
        required: true,
    },
    csrfToken: {
        type: String,
        required: true,
    },
    squareTop: {
        type: Boolean,
        default: false,
    },
});

const connected = ref(Boolean(props.connector.connected));
const siteUrl = ref(props.connector.site_url || "");
const lastVerifiedAt = ref(props.connector.last_verified_at || "");
const lastError = ref(props.connector.last_error || "");
const disconnecting = ref(false);
const refreshing = ref(false);
const loadingPerformance = ref(false);
const message = ref("");
const rows = ref([]);

const statusText = computed(() => {
    if (connected.value && lastVerifiedAt.value) {
        return `Connected. Last checked ${lastVerifiedAt.value}.`;
    }

    return connected.value ? "Connected." : "Not connected.";
});

const applyConnection = (connection) => {
    connected.value = Boolean(connection?.connected);
    siteUrl.value = connection?.site_url || "";
    lastVerifiedAt.value = connection?.last_verified_at || "";
    lastError.value = connection?.last_error || "";
};

const refresh = async () => {
    message.value = "";
    refreshing.value = true;

    try {
        const response = await fetch(props.refreshUrl, {
            method: "PATCH",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": props.csrfToken,
            },
        });
        const data = await response.json();

        if (!response.ok) {
            message.value = data.message || "Unable to refresh Search Console.";
            return;
        }

        applyConnection(data.data);
    } catch (error) {
        message.value = "Unable to refresh Search Console.";
    } finally {
        refreshing.value = false;
    }
};

const disconnect = async () => {
    message.value = "";
    disconnecting.value = true;

    try {
        const response = await fetch(props.disconnectUrl, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": props.csrfToken,
            },
        });

        if (!response.ok) {
            message.value = "Unable to disconnect Search Console.";
            return;
        }

        const data = await response.json();
        applyConnection(data.data);
        rows.value = [];
    } catch (error) {
        message.value = "Unable to disconnect Search Console.";
    } finally {
        disconnecting.value = false;
    }
};

const loadPerformance = async () => {
    message.value = "";
    loadingPerformance.value = true;

    try {
        const response = await fetch(`${props.performanceUrl}?days=28`, {
            headers: {
                Accept: "application/json",
            },
        });

        const data = await response.json();

        if (!response.ok) {
            message.value = data.message || "Unable to load Search Console performance.";
            return;
        }

        siteUrl.value = data.data?.site_url || siteUrl.value;
        rows.value = data.data?.rows || [];
    } catch (error) {
        message.value = "Unable to load Search Console performance.";
    } finally {
        loadingPerformance.value = false;
    }
};
</script>

<template>
    <article
        class="overflow-hidden border border-slate-200 bg-white shadow-sm"
        :class="squareTop ? 'rounded-b-lg' : 'rounded-lg'"
    >
        <div class="grid gap-3 border-b border-slate-200 px-4 py-3 sm:px-5 md:grid-cols-[auto,1fr,auto] md:items-center">
            <div class="flex h-11 w-11 items-center justify-center rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="rounded bg-[#4285f4] px-1.5 py-0.5 text-xs font-black leading-none text-white shadow-sm">
                    GSC
                </div>
            </div>

            <div>
                <h2 class="text-base font-semibold text-[#111b31]">Google Search Console</h2>
                <p class="mt-0.5 max-w-lg text-xs leading-4 text-slate-600">
                    Connect readonly Search Console access for automated SEO performance retrieval.
                </p>
            </div>

            <div class="md:text-right">
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Connection Status</p>
                <div class="inline-flex items-center gap-2">
                    <button
                        v-if="connected"
                        type="button"
                        class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded-md border border-slate-300 text-slate-600 transition hover:bg-slate-50 hover:text-[#111b31] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="refreshing"
                        title="Refresh Search Console connection"
                        @click="refresh"
                    >
                        <span class="sr-only">Refresh Search Console connection</span>
                        <svg
                            aria-hidden="true"
                            class="h-3.5 w-3.5"
                            :class="{ 'animate-spin': refreshing }"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M21 12a9 9 0 0 0-15.5-6.3L3 8" />
                            <path d="M3 3v5h5" />
                            <path d="M3 12a9 9 0 0 0 15.5 6.3L21 16" />
                            <path d="M16 16h5v5" />
                        </svg>
                    </button>
                    <ConnectorStatusBadge :connected="connected" />
                </div>
                <p v-if="lastError" class="mt-2 text-xs text-red-600">{{ lastError }}</p>
            </div>
        </div>

        <div class="space-y-3 px-4 py-4 sm:px-5">
            <div class="grid gap-3 md:grid-cols-[1fr,auto] md:items-center">
                <div>
                    <p class="text-xs font-semibold text-[#111b31]">{{ siteUrl || "No Search Console property selected yet." }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ statusText }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="!connected"
                        :href="connectUrl"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md bg-[#4285f4] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#2f6fd8]"
                    >
                        Connect Google
                    </a>
                    <button
                        v-if="connected"
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loadingPerformance"
                        @click="loadPerformance"
                    >
                        {{ loadingPerformance ? "Loading..." : "Preview 28 Days" }}
                    </button>
                    <a
                        v-if="connected"
                        :href="reportUrl"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md bg-[#111b31] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1d2a47]"
                    >
                        Download Report
                    </a>
                    <button
                        v-if="connected"
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="disconnecting"
                        @click="disconnect"
                    >
                        {{ disconnecting ? "Disconnecting..." : "Disconnect" }}
                    </button>
                </div>
            </div>

            <p v-if="message" class="text-xs text-red-600">{{ message }}</p>

            <div v-if="rows.length > 0" class="overflow-hidden rounded-md border border-slate-200">
                <div class="grid grid-cols-[1fr,70px,90px] bg-slate-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <span>Query</span>
                    <span class="text-right">Clicks</span>
                    <span class="text-right">Impressions</span>
                </div>
                <div v-for="row in rows" :key="row.query" class="grid grid-cols-[1fr,70px,90px] border-t border-slate-100 px-3 py-2 text-xs text-slate-700">
                    <span class="truncate">{{ row.query || "-" }}</span>
                    <span class="text-right">{{ row.clicks }}</span>
                    <span class="text-right">{{ row.impressions }}</span>
                </div>
            </div>
        </div>
    </article>
</template>
