<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

import BaseDrawer from "../../../components/BaseDrawer.vue";
import ResourceDetailHeaderBreadcrumb from "../../../components/ResourceDetailHeaderBreadcrumb.vue";
import ResourceDetailSection from "../../../components/ResourceDetailSection.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    payloadUrl: {
        type: String,
        required: true,
    },
    indexUrl: {
        type: String,
        required: true,
    },
});

const loading = ref(true);
const pageError = ref("");
const payload = ref(null);
const toast = ref(null);
const toastTimer = ref(null);
const details = reactive(emptyDetails());
const lastSavedDetails = reactive(emptyDetails());
const detailsSaving = ref(false);
const detailsError = ref("");
const savedField = ref("");

const poDrawerOpen = ref(false);
const poSubmitting = ref(false);
const poError = ref("");
const poForm = reactive({
    supplier_id: "",
    item_purchase_option_id: "",
    pack_count: "1",
    storeUrl: "",
});

const supplier = computed(() => payload.value?.supplier ?? {});
const pageTitle = computed(() => {
    const name = String(supplier.value?.company_name ?? "").trim();

    return name === "" ? props.title : `Supplier: ${name}`;
});
const breadcrumbItems = computed(() => [
    {
        label: "Suppliers",
        url: props.indexUrl,
        current: false,
    },
    {
        label: supplier.value?.company_name ?? "",
        url: null,
        current: true,
    },
]);
const supplierInitials = computed(() => {
    const words = String(supplier.value?.company_name ?? "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    return words.length === 0 ? "SU" : words.slice(0, 2).map((word) => word[0]).join("").toUpperCase();
});
const contactLine = computed(() => {
    const parts = [supplier.value?.email, supplier.value?.phone]
        .filter((part) => String(part ?? "").trim() !== "");

    return parts.length === 0 ? "No contact on file" : parts.join(" | ");
});
const supplierPackagesSection = computed(() => payload.value?.sections?.supplierPackages ?? null);
const purchaseOrdersSection = computed(() => payload.value?.sections?.purchaseOrders ?? null);
const purchaseOrderPackages = computed(() => payload.value?.purchaseOrderCreate?.packages ?? []);

function emptyDetails() {
    return {
        company_name: "",
        url: "",
        phone: "",
        email: "",
        currency_code: "",
    };
}

function asString(value) {
    return value === null || value === undefined ? "" : String(value);
}

function syncDetails() {
    const values = {
        company_name: asString(supplier.value.company_name),
        url: asString(supplier.value.url),
        phone: asString(supplier.value.phone),
        email: asString(supplier.value.email),
        currency_code: asString(supplier.value.currency_code),
    };

    Object.assign(details, values);
    Object.assign(lastSavedDetails, values);
}

function csrfHeaders() {
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": payload.value?.csrfToken ?? "",
    };
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
    }, 1800);
}

async function loadPayload() {
    loading.value = true;
    pageError.value = "";

    const response = await fetch(props.payloadUrl, {
        headers: { Accept: "application/json" },
    });

    if (!response.ok) {
        pageError.value = "Unable to load this supplier.";
        loading.value = false;
        return;
    }

    const data = await response.json();
    payload.value = data.data ?? {};
    syncDetails();
    loading.value = false;
}

function detailsPayload() {
    const currencyCode = asString(details.currency_code).trim();

    return {
        company_name: asString(details.company_name).trim(),
        url: asString(details.url).trim() || null,
        phone: asString(details.phone).trim() || null,
        email: asString(details.email).trim() || null,
        currency_code: currencyCode === "" ? null : currencyCode.toUpperCase(),
    };
}

async function saveDetails(field) {
    if (!supplier.value?.can_manage || detailsSaving.value) {
        return;
    }

    const next = detailsPayload();
    const previous = {
        company_name: lastSavedDetails.company_name,
        url: lastSavedDetails.url || null,
        phone: lastSavedDetails.phone || null,
        email: lastSavedDetails.email || null,
        currency_code: lastSavedDetails.currency_code || null,
    };

    if (JSON.stringify(next) === JSON.stringify(previous)) {
        return;
    }

    detailsSaving.value = true;
    detailsError.value = "";

    try {
        const response = await fetch(supplier.value.update_url, {
            method: "PATCH",
            headers: csrfHeaders(),
            body: JSON.stringify(next),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            detailsError.value = data.message ?? "Unable to update supplier details.";
            syncDetails();
            return;
        }

        payload.value.supplier = {
            ...supplier.value,
            ...data.data,
        };
        syncDetails();
        savedField.value = field;
        window.setTimeout(() => {
            if (savedField.value === field) {
                savedField.value = "";
            }
        }, 1000);
    } catch (error) {
        detailsError.value = "Unable to update supplier details.";
        syncDetails();
    } finally {
        detailsSaving.value = false;
    }
}

function packageDisplayText(record) {
    const quantity = record.pack_quantity_display || record.pack_quantity || "-";
    const symbol = record.pack_uom_symbol || "";

    return symbol === "" ? quantity : `${quantity} ${symbol}`;
}

function supplierPackageStateDisplay(record) {
    if (record.is_active === false || record.state === "archived") {
        return { text: "Archived", tone: "muted" };
    }

    return { text: "Active", tone: "success" };
}

function normalizeSupplierPackage(record) {
    const stateDisplay = supplierPackageStateDisplay(record);

    return {
        ...record,
        formValues: {
            item_id: asString(record.item_id),
            pack_quantity: asString(record.pack_quantity),
            pack_uom_id: asString(record.pack_uom_id),
            supplier_sku: asString(record.supplier_sku),
            price_amount: asString(record.price_amount),
        },
        display: {
            primaryText: record.item_name || "Unknown material",
            packageText: packageDisplayText(record),
            skuText: record.supplier_sku || "",
            statusText: stateDisplay.text,
            statusTone: stateDisplay.tone,
            priceText: record.current_price_display || "No price",
            showUrl: record.show_url || "",
        },
    };
}

function purchaseOrderStatusDisplay(record) {
    if (record.is_cancelled) {
        return { text: "Cancelled", tone: "muted" };
    }

    if (record.is_back_ordered) {
        return { text: "Back Ordered", tone: "muted" };
    }

    if (record.status === "CREATED") {
        return { text: record.status, tone: "default" };
    }

    if (record.status === "RECEIVED" || record.status === "COMPLETED") {
        return { text: record.status, tone: "success" };
    }

    return { text: record.status || "-", tone: "muted" };
}

function formatMoney(currencyCode, cents) {
    return `${currencyCode || "USD"} ${(Number(cents || 0) / 100).toFixed(2)}`;
}

function normalizePurchaseOrder(record) {
    const status = purchaseOrderStatusDisplay(record);

    return {
        ...record,
        display: {
            poNumberText: record.po_number ? `PO #${record.po_number}` : "Draft PO",
            orderDateText: record.order_date || "No order date",
            supplierText: record.supplier_name || "Supplier not set",
            totalText: formatMoney(payload.value?.tenantCurrencyCode || "USD", record.po_grand_total_cents),
            statusText: status.text,
            statusTone: status.tone,
            showUrl: record.show_url || "",
        },
    };
}

function buildSupplierPackagePayload(form) {
    return {
        item_id: asString(form.item_id),
        pack_quantity: asString(form.pack_quantity),
        pack_uom_id: asString(form.pack_uom_id),
        supplier_sku: asString(form.supplier_sku),
        price_amount: asString(form.price_amount),
    };
}

function openPurchaseOrderDrawer(record) {
    poForm.supplier_id = asString(record.supplier_id);
    poForm.item_purchase_option_id = asString(record.item_purchase_option_id ?? record.id);
    poForm.pack_count = "1";
    poForm.storeUrl = asString(record.purchase_url || payload.value?.purchaseOrderCreate?.storeUrl);
    poError.value = "";
    poDrawerOpen.value = true;
}

async function submitPurchaseOrder() {
    poSubmitting.value = true;
    poError.value = "";

    try {
        const response = await fetch(poForm.storeUrl, {
            method: "POST",
            headers: csrfHeaders(),
            body: JSON.stringify({
                supplier_id: poForm.supplier_id,
                item_purchase_option_id: poForm.item_purchase_option_id,
                pack_count: poForm.pack_count,
            }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            poError.value = data.message ?? "Unable to create purchase order.";
            return;
        }

        if (data.data?.show_url) {
            window.location.assign(data.data.show_url);
        }
    } finally {
        poSubmitting.value = false;
    }
}

async function createSupplierPurchaseOrder(action) {
    const response = await fetch(action.url, {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify({
            supplier_id: action.prefill?.supplier_id ?? supplier.value?.id ?? null,
        }),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        showToast(data.message ?? "Unable to create purchase order.", "error");
        return;
    }

    if (data.data?.show_url) {
        window.location.assign(data.data.show_url);
    }
}

async function handleSectionAction({ action, record }) {
    if (action.handlerKey === "purchase") {
        openPurchaseOrderDrawer(record);
        return;
    }

    if (action.handlerKey === "createSupplierPurchaseOrder") {
        await createSupplierPurchaseOrder(action);
    }
}

onMounted(loadPayload);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthShell :shell="shell" :title="pageTitle" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <div v-if="toast" class="fixed right-4 top-4 z-[1200] rounded-md px-4 py-2 text-sm font-medium shadow-lg" :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
                {{ toast.message }}
            </div>

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="pageTitle"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #header>
                    <div class="w-full bg-white px-4 py-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-lg font-semibold text-emerald-800 shadow-inner">
                                {{ supplierInitials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-2xl font-semibold leading-tight text-slate-950">{{ supplier.company_name }}</h2>
                                <p class="mt-1 truncate text-xs font-medium text-slate-500">{{ contactLine }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span class="inline-flex items-center rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600">Currency: {{ supplier.currency_code || "Tenant default" }}</span>
                                    <a v-if="supplier.url" :href="supplier.url" class="inline-flex cursor-pointer items-center rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:text-slate-900">Website</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </ResourceDetailHeaderBreadcrumb>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading" class="py-12">
                    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <div class="border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm text-gray-500">Loading supplier...</div>
                        </div>
                    </div>
                </div>

                <div v-else-if="pageError" class="py-12">
                    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <div class="border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm text-red-600">{{ pageError }}</div>
                        </div>
                    </div>
                </div>

                <div v-else class="py-12">
                    <div class="mx-auto max-w-5xl space-y-0 px-1 sm:space-y-6 sm:px-6 lg:px-8">
                        <section class="-mx-1 overflow-visible border border-gray-500 bg-white shadow-sm sm:mx-0 sm:rounded-2xl sm:border-gray-200">
                            <div class="bg-blue-50 px-3 py-4 sm:px-6 sm:py-5">
                                <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Details</h3>
                                <p class="mt-0.5 truncate text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Update supplier contact and purchasing defaults.</p>
                            </div>
                            <div class="border-t border-gray-100 bg-white px-3 py-4 sm:px-6 sm:py-5">
                                <div class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                                    <label v-for="field in ['company_name', 'url', 'phone', 'email', 'currency_code']" :key="field" class="space-y-1">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">
                                            {{ field === "company_name" ? "Supplier name" : field.replace("_", " ") }}
                                        </span>
                                        <input
                                            v-model="details[field]"
                                            :type="field === 'email' ? 'email' : field === 'url' ? 'url' : 'text'"
                                            :maxlength="field === 'currency_code' ? 3 : null"
                                            class="block w-full max-w-sm rounded-xl border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100"
                                            :class="savedField === field ? 'border-2 border-lime-400' : 'border-gray-300'"
                                            :disabled="!supplier.can_manage || detailsSaving"
                                            @change="saveDetails(field)"
                                            @blur="saveDetails(field)"
                                        >
                                    </label>
                                </div>
                                <p v-if="detailsError" class="mt-3 text-xs text-red-600">{{ detailsError }}</p>
                            </div>
                        </section>

                        <ResourceDetailSection
                            v-if="supplierPackagesSection"
                            :section="supplierPackagesSection"
                            :csrf-token="payload.csrfToken"
                            :normalize-record="normalizeSupplierPackage"
                            :build-create-payload="buildSupplierPackagePayload"
                            :build-update-payload="buildSupplierPackagePayload"
                            @custom-action="handleSectionAction"
                            @created="showToast('Supplier package created.')"
                            @updated="showToast('Supplier package updated.')"
                            @removed="showToast('Supplier package removed.')"
                        />

                        <ResourceDetailSection
                            v-if="purchaseOrdersSection"
                            :section="purchaseOrdersSection"
                            :csrf-token="payload.csrfToken"
                            :normalize-record="normalizePurchaseOrder"
                            @created="showToast('Purchase order created.')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <BaseDrawer :open="poDrawerOpen" labelled-by="supplier-purchase-order-title" panel-class="w-screen max-w-md" @close="poDrawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitPurchaseOrder">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="supplier-purchase-order-title" class="text-sm font-semibold">Create Purchase Order</h2>
                    <p class="mt-1 text-xs text-blue-100">Create a draft purchase order with one line.</p>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="poError" class="text-xs text-red-600">{{ poError }}</p>
                    <label class="block text-xs font-medium text-slate-700">
                        Package
                        <select v-model="poForm.item_purchase_option_id" class="mt-1 block w-full border-slate-300 text-sm">
                            <option value="">Select</option>
                            <option v-for="option in purchaseOrderPackages" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">
                        Qty
                        <input v-model="poForm.pack_count" class="mt-1 block w-24 border-slate-300 text-sm" type="text">
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="poDrawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="poSubmitting">Save</button>
                </div>
            </form>
        </BaseDrawer>
    </AuthShell>
</template>
