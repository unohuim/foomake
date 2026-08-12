<script setup>
import { ref } from "vue";

import ConnectorStatusBadge from "./ConnectorStatusBadge.vue";
import WordPressPluginDownload from "./WordPressPluginDownload.vue";

const props = defineProps({
    connection: {
        type: Object,
        required: true,
    },
    downloadUrl: {
        type: String,
        required: true,
    },
    revokeUrl: {
        type: String,
        required: true,
    },
    csrfToken: {
        type: String,
        required: true,
    },
});

const connected = ref(Boolean(props.connection.connected));
const status = ref(props.connection.status || "revoked");
const siteUrl = ref(props.connection.site_url || "");
const siteName = ref(props.connection.site_name || "");
const lastSeenAt = ref(props.connection.last_seen_at || "");
const connectedAt = ref(props.connection.connected_at || "");
const revokeMessage = ref("");
const revoking = ref(false);

const revoke = async () => {
    revokeMessage.value = "";
    revoking.value = true;

    try {
        const response = await fetch(props.revokeUrl, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": props.csrfToken,
            },
        });

        if (!response.ok) {
            revokeMessage.value = "Unable to revoke the WordPress plugin connection.";
            return;
        }

        const data = await response.json();
        connected.value = Boolean(data.data?.connected);
        status.value = data.data?.status || "revoked";
        siteUrl.value = data.data?.site_url || siteUrl.value;
        siteName.value = data.data?.site_name || siteName.value;
        lastSeenAt.value = data.data?.last_seen_at || lastSeenAt.value;
        connectedAt.value = data.data?.connected_at || connectedAt.value;
    } catch (error) {
        revokeMessage.value = "Unable to revoke the WordPress plugin connection.";
    } finally {
        revoking.value = false;
    }
};
</script>

<template>
    <article class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">WordPress Plugin</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        Install and pair the FooMake plugin with this tenant account.
                    </p>
                </div>
                <ConnectorStatusBadge :connected="connected" />
            </div>
        </div>

        <div class="space-y-5 px-5 py-5">
            <WordPressPluginDownload :download-url="downloadUrl" />

            <section class="rounded-md border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pairing Status</p>
                        <p class="mt-1 text-sm text-slate-800">
                            {{ connected ? "Plugin paired with FooMake." : "No active plugin pairing." }}
                        </p>
                        <dl v-if="siteUrl" class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                            <div>
                                <dt class="font-semibold text-slate-500">Site</dt>
                                <dd class="truncate">{{ siteName || siteUrl }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-500">Last Seen</dt>
                                <dd>{{ lastSeenAt || "Not checked yet" }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="font-semibold text-slate-500">URL</dt>
                                <dd class="truncate">{{ siteUrl }}</dd>
                            </div>
                        </dl>
                    </div>

                    <button
                        v-if="connected"
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="revoking"
                        @click="revoke"
                    >
                        {{ revoking ? "Revoking..." : "Revoke" }}
                    </button>
                </div>

                <p v-if="revokeMessage" class="mt-3 text-xs text-red-600">{{ revokeMessage }}</p>
            </section>
        </div>
    </article>
</template>
