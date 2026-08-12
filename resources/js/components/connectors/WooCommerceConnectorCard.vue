<script setup>
import { computed, reactive, ref } from "vue";

import ConnectorStatusBadge from "./ConnectorStatusBadge.vue";

const props = defineProps({
    connector: {
        type: Object,
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

const statusLabel = computed(() => {
    if (isConnected.value && lastVerifiedAt.value) {
        return `Connected. Last verified at ${lastVerifiedAt.value}.`;
    }

    return isConnected.value ? "Connected." : "Disconnected.";
});

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
</script>

<template>
    <article class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">WooCommerce</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        Manage the tenant WooCommerce store used for product, customer, and order imports.
                    </p>
                </div>
                <ConnectorStatusBadge :connected="isConnected" />
            </div>
        </div>

        <div class="space-y-5 px-5 py-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Connection Status</p>
                    <p class="mt-1 text-sm text-slate-800">{{ statusLabel }}</p>
                    <p v-if="lastError" class="mt-1 text-xs text-red-600">{{ lastError }}</p>
                </div>

                <button
                    v-if="isConnected"
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="disconnecting"
                    @click="disconnect"
                >
                    {{ disconnecting ? "Disconnecting..." : "Disconnect" }}
                </button>
            </div>

            <section class="rounded-md border border-slate-200 bg-slate-50 p-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Connect or reconnect</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        Credentials are verified before saving and are never rendered back after storage.
                    </p>
                </div>

                <div class="mt-4 grid gap-3">
                    <label class="block text-xs font-semibold text-slate-700">
                        Store URL
                        <input
                            v-model="form.store_url"
                            type="url"
                            class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                        <span v-if="fieldError('store_url')" class="mt-1 block text-xs text-red-600">{{ fieldError("store_url") }}</span>
                    </label>

                    <label class="block text-xs font-semibold text-slate-700">
                        Consumer Key
                        <input
                            v-model="form.consumer_key"
                            type="password"
                            class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                        <span v-if="fieldError('consumer_key')" class="mt-1 block text-xs text-red-600">{{ fieldError("consumer_key") }}</span>
                    </label>

                    <label class="block text-xs font-semibold text-slate-700">
                        Consumer Secret
                        <input
                            v-model="form.consumer_secret"
                            type="password"
                            class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                        <span v-if="fieldError('consumer_secret')" class="mt-1 block text-xs text-red-600">{{ fieldError("consumer_secret") }}</span>
                    </label>
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md bg-[#172234] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#24334c] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="saving"
                        @click="save"
                    >
                        {{ saving ? "Saving..." : "Save WooCommerce Connection" }}
                    </button>
                    <span v-if="formMessage" class="text-xs text-red-600">{{ formMessage }}</span>
                </div>
            </section>
        </div>
    </article>
</template>
