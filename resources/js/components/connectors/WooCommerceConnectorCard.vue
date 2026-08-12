<script setup>
import { computed, reactive, ref } from "vue";

import ConnectorStatusBadge from "./ConnectorStatusBadge.vue";
import WordPressPluginDownload from "./WordPressPluginDownload.vue";

const props = defineProps({
    connector: {
        type: Object,
        required: true,
    },
    pluginConnection: {
        type: Object,
        required: true,
    },
    pluginDownloadUrl: {
        type: String,
        required: true,
    },
    pluginRevokeUrl: {
        type: String,
        required: true,
    },
    storeUrl: {
        type: String,
        required: true,
    },
    disconnectUrl: {
        type: String,
        required: true,
    },
    csrfToken: {
        type: String,
        required: true,
    },
});

const form = reactive({
    store_url: "",
    consumer_key: "",
    consumer_secret: "",
});

const isConnected = ref(Boolean(props.connector.connected));
const status = ref(props.connector.status || "disconnected");
const lastVerifiedAt = ref(props.connector.last_verified_at || "");
const lastError = ref(props.connector.last_error || "");
const errors = ref({});
const formMessage = ref("");
const saving = ref(false);
const disconnecting = ref(false);
const pluginConnected = ref(Boolean(props.pluginConnection.connected));
const pluginSiteUrl = ref(props.pluginConnection.site_url || "");
const pluginSiteName = ref(props.pluginConnection.site_name || "");
const pluginLastSeenAt = ref(props.pluginConnection.last_seen_at || "");
const pluginMessage = ref("");
const pluginRevoking = ref(false);

const statusLabel = computed(() => {
    if (isConnected.value && lastVerifiedAt.value) {
        return `Connected. Last verified at ${lastVerifiedAt.value}.`;
    }

    return isConnected.value ? "API keys connected." : "API keys disconnected.";
});

const overallConnected = computed(() => isConnected.value || pluginConnected.value);

const fieldError = (field) => {
    const messages = errors.value[field];

    return Array.isArray(messages) && messages.length > 0 ? messages[0] : "";
};

const resetForm = () => {
    form.store_url = "";
    form.consumer_key = "";
    form.consumer_secret = "";
};

const applyConnection = (connection) => {
    isConnected.value = Boolean(connection?.connected);
    status.value = connection?.status || "connected";
    lastVerifiedAt.value = connection?.last_verified_at || "";
    lastError.value = connection?.last_error || "";
};

const save = async () => {
    errors.value = {};
    formMessage.value = "";
    saving.value = true;

    try {
        const response = await fetch(props.storeUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": props.csrfToken,
            },
            body: JSON.stringify(form),
        });

        if (response.status === 422) {
            const data = await response.json();
            errors.value = data.errors || {};
            formMessage.value = data.message || "Unable to save the WooCommerce connection.";
            return;
        }

        if (!response.ok) {
            formMessage.value = "Unable to save the WooCommerce connection.";
            return;
        }

        const data = await response.json();
        applyConnection(data.data);
        resetForm();
    } catch (error) {
        formMessage.value = "Unable to save the WooCommerce connection.";
    } finally {
        saving.value = false;
    }
};

const disconnect = async () => {
    errors.value = {};
    formMessage.value = "";
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
            formMessage.value = "Unable to disconnect the WooCommerce connection.";
            return;
        }

        const data = await response.json();
        applyConnection(data.data);
        resetForm();
    } catch (error) {
        formMessage.value = "Unable to disconnect the WooCommerce connection.";
    } finally {
        disconnecting.value = false;
    }
};

const revokePlugin = async () => {
    pluginMessage.value = "";
    pluginRevoking.value = true;

    try {
        const response = await fetch(props.pluginRevokeUrl, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": props.csrfToken,
            },
        });

        if (!response.ok) {
            pluginMessage.value = "Unable to revoke the WordPress plugin connection.";
            return;
        }

        const data = await response.json();
        pluginConnected.value = Boolean(data.data?.connected);
        pluginSiteUrl.value = data.data?.site_url || pluginSiteUrl.value;
        pluginSiteName.value = data.data?.site_name || pluginSiteName.value;
        pluginLastSeenAt.value = data.data?.last_seen_at || pluginLastSeenAt.value;
    } catch (error) {
        pluginMessage.value = "Unable to revoke the WordPress plugin connection.";
    } finally {
        pluginRevoking.value = false;
    }
};
</script>

<template>
    <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-3 border-b border-slate-200 px-4 py-3 sm:px-5 md:grid-cols-[auto,1fr,auto] md:items-center">
            <div class="flex h-11 w-11 items-center justify-center rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="rounded bg-[#9651c7] px-1.5 py-0.5 text-xs font-black leading-none text-white shadow-sm">
                    WOO
                </div>
            </div>

            <div>
                <h2 class="text-base font-semibold text-[#111b31]">WooCommerce</h2>
                <p class="mt-0.5 max-w-lg text-xs leading-4 text-slate-600">
                    Manage the tenant WooCommerce store used for product, customer, and order imports.
                </p>
            </div>

            <div class="md:text-right">
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-500">Connection Status</p>
                <ConnectorStatusBadge :connected="overallConnected" />
                <p v-if="lastError" class="mt-2 text-xs text-red-600">{{ lastError }}</p>
                <button
                    v-if="isConnected"
                    type="button"
                    class="mt-2 inline-flex cursor-pointer items-center justify-center rounded-md border border-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="disconnecting"
                    @click="disconnect"
                >
                    {{ disconnecting ? "Disconnecting..." : "Disconnect API Keys" }}
                </button>
            </div>
        </div>

        <div class="space-y-4 px-4 py-4 sm:px-5">
            <div class="rounded-lg border border-[#d8ead2] bg-[#f7fbf5] p-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#5f8b50] text-xs font-semibold text-white shadow-sm">1</span>
                        <h3 class="text-sm font-semibold text-[#111b31]">Install WordPress Plugin</h3>
                        <span class="inline-flex items-center rounded-full border border-[#cfe4c8] bg-white px-2 py-0.5 text-[10px] font-semibold text-[#4f7e46]">Recommended</span>
                    </div>
                    <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-4 text-slate-600">
                        Download the FooMake connector plugin for your WooCommerce / WordPress site and connect from within WordPress.
                        </p>
                        <WordPressPluginDownload v-if="!pluginConnected" class="shrink-0 sm:w-56" :download-url="pluginDownloadUrl" />
                    </div>

                    <div v-if="pluginConnected" class="mt-2 rounded-md border border-[#d8ead2] bg-white p-2.5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-[#244d24]">Plugin paired with FooMake.</p>
                                <p class="mt-1 truncate text-xs text-slate-600">
                                    {{ pluginSiteName || pluginSiteUrl || "WordPress site connected" }}
                                </p>
                                <p v-if="pluginLastSeenAt" class="mt-1 text-xs text-slate-500">Last seen {{ pluginLastSeenAt }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center justify-center rounded-md border border-red-200 px-2.5 py-1.5 text-[11px] font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="pluginRevoking"
                                @click="revokePlugin"
                            >
                                {{ pluginRevoking ? "Revoking..." : "Revoke" }}
                            </button>
                        </div>
                        <p v-if="pluginMessage" class="mt-3 text-xs text-red-600">{{ pluginMessage }}</p>
                    </div>

                    <p class="mt-1.5 text-[11px] text-slate-500">For self-hosted WordPress sites with WooCommerce.</p>
                </div>
            </div>

            <div class="relative border-t border-slate-200">
                <span class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">or</span>
            </div>

            <div class="rounded-lg border border-blue-100 bg-blue-50/40 p-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#1f74d0] text-xs font-semibold text-white shadow-sm">2</span>
                        <h3 class="text-sm font-semibold text-[#111b31]">Connect with API Keys</h3>
                    </div>
                    <p class="mt-1.5 text-xs leading-4 text-slate-600">
                        Alternatively, generate WooCommerce API credentials and enter them manually here.
                    </p>

                    <div class="mt-2 grid gap-2.5 md:grid-cols-3">
                        <label class="block text-xs font-semibold text-slate-700">
                            Store URL
                            <input
                                v-model="form.store_url"
                                type="url"
                                placeholder="https://your-store.com"
                                class="mt-1 block h-8 w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-[#1f74d0] focus:ring-[#1f74d0]"
                            >
                            <span v-if="fieldError('store_url')" class="mt-1 block text-xs text-red-600">{{ fieldError("store_url") }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-slate-700">
                            Consumer Key
                            <input
                                v-model="form.consumer_key"
                                type="password"
                                placeholder="ck_........................"
                                class="mt-1 block h-8 w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-[#1f74d0] focus:ring-[#1f74d0]"
                            >
                            <span v-if="fieldError('consumer_key')" class="mt-1 block text-xs text-red-600">{{ fieldError("consumer_key") }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-slate-700">
                            Consumer Secret
                            <input
                                v-model="form.consumer_secret"
                                type="password"
                                placeholder="cs_........................"
                                class="mt-1 block h-8 w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-[#1f74d0] focus:ring-[#1f74d0]"
                            >
                            <span v-if="fieldError('consumer_secret')" class="mt-1 block text-xs text-red-600">{{ fieldError("consumer_secret") }}</span>
                        </label>
                    </div>

                    <div class="mt-2 grid gap-2 md:grid-cols-[1fr,260px] md:items-center">
                        <div class="rounded-md border border-blue-100 bg-white/70 px-2.5 py-2">
                            <div class="flex gap-2">
                                <span class="text-[#1f74d0]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-5" />
                                    </svg>
                                </span>
                                <p class="text-[11px] leading-4 text-[#1d4f8f]">
                                    Credentials are verified before saving and are never shown again after storage.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-[#1f74d0] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1764b6] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="saving"
                            @click="save"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M8 11V8a4 4 0 1 1 8 0v3h1a1 1 0 0 1 1 1v8H6v-8a1 1 0 0 1 1-1h1Zm2 0h4V8a2 2 0 1 0-4 0v3Z" />
                            </svg>
                            {{ saving ? "Saving..." : "Save WooCommerce Connection" }}
                        </button>
                    </div>
                    <div>
                        <span v-if="formMessage" class="mt-2 block text-xs text-red-600">{{ formMessage }}</span>
                        <p class="mt-2 text-xs text-slate-500">{{ statusLabel }}</p>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>
