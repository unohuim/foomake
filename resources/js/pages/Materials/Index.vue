<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";

import ResourceCardGrid from "../../components/ResourceCardGrid.vue";
import ResourceCreateDrawer from "../../components/ResourceCreateDrawer.vue";
import ResourceIndex from "../../components/ResourceIndex.vue";
import UiToggle from "../../components/UiToggle.vue";
import UiToast from "../../components/UiToast.vue";
import AuthShell from "../../layouts/AuthShell.vue";

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

const materials = ref([]);
const search = ref("");
const sort = ref({
    column: "item",
    direction: "asc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);
const activeToggleSavingIds = ref([]);

const createDrawerOpen = ref(false);
const createSubmitting = ref(false);
const createError = ref("");
const createErrors = ref(emptyErrors());
const form = reactive(emptyForm());

const labels = computed(() => props.crudConfig.labels ?? {});
const permissions = computed(() => props.crudConfig.permissions ?? {});
const uoms = computed(() => (Array.isArray(props.payload.uoms) ? props.payload.uoms : []));
const tenantCurrency = computed(() => String(props.payload.tenantCurrency ?? "").toUpperCase());

function emptyErrors() {
    return {
        name: [],
        base_uom_id: [],
        default_price_amount: [],
        default_price_currency_code: [],
        starting_quantity: [],
    };
}

function emptyForm() {
    return {
        name: "",
        base_uom_id: "",
        is_active: true,
        is_stockable: false,
        is_purchasable: false,
        is_sellable: false,
        is_manufacturable: false,
        default_price_amount: "",
        starting_quantity: "",
    };
}

function resetForm() {
    Object.assign(form, emptyForm());
    createError.value = "";
    createErrors.value = emptyErrors();
}

function normalizeErrors(errors) {
    if (!errors || typeof errors !== "object") {
        return emptyErrors();
    }

    return {
        ...emptyErrors(),
        ...errors,
        name: Array.isArray(errors.name) ? errors.name : [],
        base_uom_id: Array.isArray(errors.base_uom_id) ? errors.base_uom_id : [],
        default_price_amount: Array.isArray(errors.default_price_amount) ? errors.default_price_amount : [],
        default_price_currency_code: Array.isArray(errors.default_price_currency_code)
            ? errors.default_price_currency_code
            : [],
        starting_quantity: Array.isArray(errors.starting_quantity) ? errors.starting_quantity : [],
    };
}

function canManageMaterials() {
    return Boolean(permissions.value.canManageMaterials);
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
        toastTimer.value = null;
    }, 1800);
}

function dismissToast() {
    toast.value = null;

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
        toastTimer.value = null;
    }
}

function buildItemEndpoint(template, itemId) {
    if (!template || itemId === null || itemId === undefined) {
        return "";
    }

    return template.replace("{id}", encodeURIComponent(String(itemId)));
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

async function refreshNavigationState() {
    router.reload({
        only: ["shell"],
        preserveScroll: true,
        preserveState: true,
    });
}

async function fetchMaterials(nextSearch = search.value) {
    const listUrl = props.crudConfig.endpoints?.list;

    if (!listUrl) {
        listError.value = "Unable to load materials.";
        return;
    }

    search.value = nextSearch;
    loading.value = true;
    listError.value = "";

    try {
        const url = new URL(listUrl, window.location.origin);
        url.searchParams.set("search", search.value);
        url.searchParams.set("sort", sort.value.column);
        url.searchParams.set("direction", sort.value.direction);

        const data = await jsonRequest(url.toString(), {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        });

        materials.value = Array.isArray(data.data) ? data.data : [];

        if (data.meta?.sort?.column && data.meta?.sort?.direction) {
            sort.value = {
                column: data.meta.sort.column,
                direction: data.meta.sort.direction,
            };
        }
    } catch {
        listError.value = "Unable to load materials.";
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    if (!permissions.value.showCreate) {
        return;
    }

    resetForm();
    createDrawerOpen.value = true;
}

function closeCreateDrawer() {
    if (createSubmitting.value) {
        return;
    }

    createDrawerOpen.value = false;
    resetForm();
}

function optionalString(value) {
    if (value === null || value === undefined || value === "") {
        return "";
    }

    return String(value);
}

function createPayload() {
    return {
        name: String(form.name ?? "").trim(),
        base_uom_id: form.base_uom_id,
        is_active: Boolean(form.is_active),
        is_stockable: Boolean(form.is_stockable),
        is_purchasable: Boolean(form.is_purchasable),
        is_sellable: Boolean(form.is_sellable),
        is_manufacturable: Boolean(form.is_manufacturable),
        default_price_amount: optionalString(form.default_price_amount),
        default_price_currency_code: tenantCurrency.value,
        starting_quantity: optionalString(form.starting_quantity),
    };
}

async function submitCreate() {
    createSubmitting.value = true;
    createError.value = "";
    createErrors.value = emptyErrors();

    try {
        await jsonRequest(props.payload.storeUrl, {
            method: "POST",
            body: JSON.stringify(createPayload()),
        });

        await fetchMaterials();
        await refreshNavigationState();
        createDrawerOpen.value = false;
        resetForm();
        showToast("Material created.");
    } catch (error) {
        createErrors.value = normalizeErrors(error.payload?.errors);
        createError.value = error.payload?.message ?? "Something went wrong. Please try again.";
    } finally {
        createSubmitting.value = false;
    }
}

async function toggleMaterialActive(record, checked) {
    if (!canManageMaterials() || !record?.id || activeToggleSavingIds.value.includes(record.id)) {
        return;
    }

    const endpoint = record.update_url || buildItemEndpoint(props.crudConfig.endpoints?.update, record.id);

    if (!endpoint) {
        showToast("Something went wrong. Please try again.", "error");
        return;
    }

    const previousValue = Boolean(record.is_active);
    record.is_active = checked;
    activeToggleSavingIds.value = [...activeToggleSavingIds.value, record.id];

    try {
        const data = await jsonRequest(endpoint, {
            method: "PATCH",
            body: JSON.stringify({
                name: record.item || "",
                base_uom_id: record.base_uom_id,
                is_active: checked,
                is_stockable: Boolean(record.is_stockable),
                is_purchasable: Boolean(record.is_purchasable),
                is_sellable: Boolean(record.is_sellable),
                is_manufacturable: Boolean(record.is_manufacturable),
                default_price_amount: record.default_price_amount || "",
                default_price_currency_code: record.default_price_currency_code || tenantCurrency.value,
            }),
        });
        const updated = data.data ?? {};

        record.is_active = Boolean(updated.is_active);
        await fetchMaterials();
        await refreshNavigationState();
        showToast(`${record.item || "Material"} ${record.is_active ? "Active" : "Inactive"}`);
    } catch {
        record.is_active = previousValue;
        showToast("Something went wrong. Please try again.", "error");
    } finally {
        activeToggleSavingIds.value = activeToggleSavingIds.value.filter((id) => id !== record.id);
    }
}

function formatMaterialCardQuantity(value) {
    const normalized = String(value || "0");
    const [whole, decimal] = normalized.split(".");
    const formattedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ",");

    return decimal === undefined ? formattedWhole : `${formattedWhole}.${decimal}`;
}

function materialCardUomLabel(record) {
    const name = String(record?.item_uom_name || "").trim();
    const symbol = String(record?.item_uom_symbol || "").trim();

    if (name !== "" && symbol !== "") {
        return `${name} (${symbol})`;
    }

    return name || symbol || "-";
}

function materialAvailabilityStats(record) {
    return [
        { label: "On hand", value: formatMaterialCardQuantity(record?.on_hand_display) },
        { label: "Net Qty", value: formatMaterialCardQuantity(record?.net_display) },
        { label: "SO Qty", value: formatMaterialCardQuantity(record?.sell_display) },
        { label: "PO Qty", value: formatMaterialCardQuantity(record?.buy_display) },
        { label: "MO Qty", value: formatMaterialCardQuantity(record?.make_display) },
    ];
}

function materialCardDetailRows(record) {
    const stats = materialAvailabilityStats(record);

    return [
        {
            left: stats.slice(0, 2),
            right: [],
        },
        {
            left: stats.slice(2),
            right: [],
        },
    ];
}

function materialFlagIcons(record) {
    return [
        { label: "Sellable", icon: "shopping-cart", active: Boolean(record?.is_sellable) },
        { label: "Purchasable", icon: "credit-card", active: Boolean(record?.is_purchasable) },
        { label: "Makeable", icon: "cog", active: Boolean(record?.is_manufacturable) },
        { label: "Stockable", icon: "rectangle-group", active: Boolean(record?.is_stockable) },
    ];
}

function handleMaterialsRefresh() {
    void fetchMaterials();
}

onMounted(() => {
    void fetchMaterials();
    window.addEventListener("materials-index-refresh", handleMaterialsRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("materials-index-refresh", handleMaterialsRefresh);

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }
});
</script>

<template>
    <Head title="Materials" />

    <AuthShell :shell="shell" title="Materials">
        <div class="flex h-full min-h-0 w-full">
            <div class="relative flex min-h-0 w-full flex-1">
                <UiToast
                    :visible="Boolean(toast)"
                    :type="toast?.tone || 'success'"
                    :message="toast?.message || ''"
                    @dismiss="dismissToast"
                />

                <ResourceIndex
                    v-model:search="search"
                    :labels="labels"
                    :permissions="permissions"
                    :records="materials"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchMaterials"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title="(record) => record.item || '-'"
                            :subtitle="materialCardUomLabel"
                            :mobile-subtitle="materialCardUomLabel"
                            :detail-rows="materialCardDetailRows"
                            :href="(record) => record.show_url"
                            :icon-badges="materialFlagIcons"
                        >
                            <template #mobile-aside="{ record }">
                                <UiToggle
                                    v-if="canManageMaterials()"
                                    name="is_active"
                                    :checked="Boolean(record.is_active)"
                                    :disabled="activeToggleSavingIds.includes(record.id)"
                                    :record="record"
                                    :aria-label="`Toggle ${record.item || 'material'} active state`"
                                    @change="toggleMaterialActive($event.record, $event.checked)"
                                />
                            </template>

                            <template #desktop-aside="{ record }">
                                <UiToggle
                                    v-if="canManageMaterials()"
                                    name="is_active"
                                    :checked="Boolean(record.is_active)"
                                    :disabled="activeToggleSavingIds.includes(record.id)"
                                    :record="record"
                                    :aria-label="`Toggle ${record.item || 'material'} active state`"
                                    @change="toggleMaterialActive($event.record, $event.checked)"
                                />
                            </template>
                        </ResourceCardGrid>
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="createDrawerOpen"
            :title="labels.createTitle || 'Create Material'"
            description="Add a new item to your inventory."
            submit-label="Create"
            :submitting="createSubmitting"
            :error="createError"
            panel-class="w-screen max-w-md"
            @close="closeCreateDrawer"
            @submit="submitCreate"
        >
            <template #header-icon>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 2.75 3.75 7.5m16.5 0v9L12 21.25m8.25-13.75L12 12.25m0 9V12.25m0 9-8.25-4.75v-9m0 0L12 12.25" />
                </svg>
            </template>

            <div class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Name</span>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="off"
                    >
                    <span v-if="createErrors.name[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.name[0] }}</span>
                </label>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                    <label class="block sm:col-span-3">
                        <span class="text-xs font-semibold text-gray-700">UoM</span>
                        <select
                            v-model="form.base_uom_id"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                            <option value="">Select a unit</option>
                            <option v-for="uom in uoms" :key="uom.id" :value="uom.id">
                                {{ uom.name }} ({{ uom.symbol }})
                            </option>
                        </select>
                        <span v-if="createErrors.base_uom_id[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.base_uom_id[0] }}</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold text-gray-700">Starting Qty</span>
                        <input
                            v-model="form.starting_quantity"
                            type="number"
                            min="0"
                            step="0.000001"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                        <span v-if="createErrors.starting_quantity[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.starting_quantity[0] }}</span>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Default Price</span>
                    <div class="mt-1 flex">
                        <input
                            v-model="form.default_price_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="block h-9 w-full rounded-l-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                        <span class="inline-flex h-9 items-center border border-l-0 border-gray-300 bg-gray-50 px-3 text-xs font-semibold text-gray-500">
                            {{ tenantCurrency || "USD" }}
                        </span>
                    </div>
                    <span v-if="createErrors.default_price_amount[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.default_price_amount[0] }}</span>
                </label>

                <div class="border-t border-gray-200 pt-4">
                    <p class="text-xs font-semibold text-gray-700">Type</p>
                    <div class="mt-3 space-y-3">
                        <label
                            v-for="option in [
                                ['is_stockable', 'Stockable', 'Keep track of stock levels of this item.'],
                                ['is_purchasable', 'Purchasable', 'Allow this item to be bought from suppliers.'],
                                ['is_sellable', 'Sellable', 'Allow this item to be sold to customers.'],
                                ['is_manufacturable', 'Manufacturable', 'Allow this item to be made from a recipe.'],
                            ]"
                            :key="option[0]"
                            class="flex items-start gap-2 text-sm text-gray-700"
                        >
                            <input
                                v-model="form[option[0]]"
                                type="checkbox"
                                class="mt-0.5 cursor-pointer rounded border-gray-300 text-[#6f895d] focus:ring-[#6f895d]"
                            >
                            <span class="block">
                                <span class="block text-xs font-semibold">{{ option[1] }}</span>
                                <span class="block text-xs text-gray-500">{{ option[2] }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </ResourceCreateDrawer>
    </AuthShell>
</template>
