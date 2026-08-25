<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

import ResourceCardGrid from "../../../components/ResourceCardGrid.vue";
import ResourceCreateDrawer from "../../../components/ResourceCreateDrawer.vue";
import ResourceIndex from "../../../components/ResourceIndex.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    crudConfig: {
        type: Object,
        required: true,
    },
    payload: {
        type: Object,
        required: true,
    },
});

const orders = ref([...props.payload.orders]);
const search = ref("");
const sort = reactive({
    column: "created_at",
    direction: "desc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formSubmitting = ref(false);
const formError = ref("");

const labels = computed(() => props.crudConfig.labels ?? {});

function orderTitle(order) {
    return order?.order || "Draft PO";
}

function orderStatusBadges(order) {
    const status = String(order?.status || "").trim();

    if (status === "") {
        return [];
    }

    const tones = {
        CANCELLED: "gray",
        COMPLETED: "green",
        CREATED: "blue",
        DRAFT: "yellow",
        PARTIALLY_RECEIVED: "blue",
        RECEIVED: "green",
    };

    return [{
        label: status,
        tone: tones[status] || "gray",
    }];
}

function orderCardRows(order) {
    return [
        {
            left: [{ label: "Supplier", value: order?.supplier_name || "Supplier not set" }],
            right: [{ label: "Total", value: order?.po_grand_total_display || formatMoney(order?.po_grand_total_cents) }],
        },
        {
            left: [{ label: "Status", value: order?.status || "-" }],
            right: [{ label: "Lines", value: String(order?.lines_count ?? 0) }],
        },
    ];
}

function formatMoney(cents) {
    if (cents === null || cents === undefined) {
        return `${props.payload.tenantCurrency ?? "USD"} 0.00`;
    }

    return `${props.payload.tenantCurrency ?? "USD"} ${(Number(cents) / 100).toFixed(2)}`;
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
        toastTimer.value = null;
    }, 3500);
}

async function jsonRequest(url, options = {}) {
    const headers = {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": props.payload.csrfToken,
        ...(options.headers ?? {}),
    };

    const response = await fetch(url, {
        credentials: "same-origin",
        ...options,
        headers,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message ?? "The request could not be completed.");
        error.payload = data;
        error.status = response.status;
        throw error;
    }

    return data;
}

async function fetchOrders(nextSearch = search.value) {
    loading.value = true;
    listError.value = "";
    search.value = nextSearch;

    try {
        const params = new URLSearchParams({
            search: search.value,
            sort: sort.column,
            direction: sort.direction,
        });
        const response = await jsonRequest(`${props.crudConfig.endpoints.list}?${params.toString()}`, {
            method: "GET",
        });

        orders.value = response.data ?? [];

        if (response.meta?.sort) {
            sort.column = response.meta.sort.column ?? sort.column;
            sort.direction = response.meta.sort.direction ?? sort.direction;
        }
    } catch (error) {
        listError.value = error.payload?.message ?? "Unable to load purchase orders.";
        showToast(listError.value, "error");
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    formError.value = "";
    formDrawerOpen.value = true;
}

function closeFormDrawer() {
    if (formSubmitting.value) {
        return;
    }

    formDrawerOpen.value = false;
}

async function submitForm() {
    if (formSubmitting.value) {
        return;
    }

    formSubmitting.value = true;
    formError.value = "";

    try {
        const response = await jsonRequest(props.crudConfig.endpoints.create, {
            method: "POST",
            body: JSON.stringify({}),
        });

        await fetchOrders();
        refreshShellNavigation();
        formDrawerOpen.value = false;
        showToast(response.message ?? "Purchase order created.");
    } catch (error) {
        formError.value = error.payload?.message ?? "Unable to create purchase order.";
        showToast(formError.value, "error");
    } finally {
        formSubmitting.value = false;
    }
}

function refreshShellNavigation() {
    router.reload({
        only: ["shell"],
        preserveScroll: true,
        preserveState: true,
    });
}

onMounted(() => {
    fetchOrders();
});
</script>

<template>
    <Head title="Purchase Orders" />

    <AuthShell :shell="shell" title="Purchase Orders">
        <div class="flex h-full min-h-0 w-full">
            <div class="relative flex min-h-0 w-full flex-1">
                <div
                    v-if="toast"
                    class="fixed right-4 top-4 z-50 rounded-md px-4 py-3 text-sm shadow-lg"
                    :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-slate-900 text-white'"
                    role="status"
                >
                    {{ toast.message }}
                </div>

                <ResourceIndex
                    v-model:search="search"
                    :labels="labels"
                    :permissions="crudConfig.permissions"
                    :records="orders"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchOrders"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title="orderTitle"
                            :title-aside="(order) => order.order_date || 'No order date'"
                            :title-badges="orderStatusBadges"
                            :detail-rows="orderCardRows"
                            :subtitle="(order) => order.supplier_name || 'Supplier not set'"
                            :mobile-subtitle="(order) => order.supplier_name || 'Supplier not set'"
                            :href="(order) => order.show_url"
                        />
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            title="Create purchase order"
            description="Create a draft purchase order, then add supplier and line details from the order page."
            submit-label="Create Order"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-sm"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <p class="text-sm leading-6 text-gray-600">
                A draft order will be created without supplier or line details.
            </p>
        </ResourceCreateDrawer>
    </AuthShell>
</template>
