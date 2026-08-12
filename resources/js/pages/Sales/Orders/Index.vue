<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

import ResourceCardGrid from "../../../components/ResourceCardGrid.vue";
import ResourceCreateDrawer from "../../../components/ResourceCreateDrawer.vue";
import ResourceExportDrawer from "../../../components/ResourceExportDrawer.vue";
import ResourceImportDrawer from "../../../components/ResourceImportDrawer.vue";
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
    importConfig: {
        type: Object,
        required: true,
    },
    payload: {
        type: Object,
        required: true,
    },
});

const orderCsvHeaders = [
    "external_source",
    "order_external_id",
    "order_date",
    "customer_name",
    "contact_name",
    "city",
    "status",
    "external_status",
    "line_external_id",
    "product_external_id",
    "product_name",
    "quantity",
    "unit_price",
];

const orders = ref([...props.payload.orders]);
const search = ref("");
const sort = reactive({
    column: "date",
    direction: "desc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formMode = ref("create");
const editingOrderId = ref(null);
const formSubmitting = ref(false);
const formError = ref("");
const formErrors = ref(emptyErrors());
const form = reactive(emptyForm());

const exportDrawerOpen = ref(false);
const exportScope = ref("current");
const exportSubmitting = ref(false);
const exportError = ref("");

const importDrawerOpen = ref(false);
const selectedImportSource = ref("");
const previewRows = ref([]);
const previewSearch = ref("");
const showDuplicateRows = ref(true);
const loadingPreview = ref(false);
const importSubmitting = ref(false);
const importErrors = ref({});
const importConnectRequired = ref(false);

const labels = computed(() => props.crudConfig.labels ?? {});
const importLabels = computed(() => props.importConfig.labels ?? {});
const importMessages = computed(() => props.importConfig.messages ?? {});
const importSources = computed(() => props.importConfig.sources ?? []);
const canManageConnections = computed(() => Boolean(props.importConfig.permissions?.canManageConnections));
const connectorsPageUrl = computed(() => props.importConfig.connectorsPageUrl ?? "");
const customers = computed(() => props.payload.customers ?? []);

const duplicateRowCount = computed(() => previewRows.value.filter((row) => Boolean(row.is_duplicate)).length);
const selectedSource = computed(() => importSources.value.find((source) => source.value === selectedImportSource.value) ?? null);
const showConnectionRequired = computed(() => selectedImportSource.value !== ""
    && selectedImportSource.value !== "file-upload"
    && (selectedSource.value?.connected === false || selectedSource.value?.enabled === false || importConnectRequired.value));

const visiblePreviewRows = computed(() => previewRows.value.filter((row) => {
    if (!showDuplicateRows.value && row.is_duplicate) {
        return false;
    }

    const needle = previewSearch.value.trim().toLowerCase();

    if (needle === "") {
        return true;
    }

    const customer = row.customer && typeof row.customer === "object" ? row.customer : {};

    return [
        row.date,
        row.external_id,
        row.external_source,
        row.external_status,
        row.status,
        row.contact_name,
        customer.name,
        customer.email,
        customer.city,
    ].some((value) => String(value ?? "").toLowerCase().includes(needle));
}));

function emptyForm() {
    return {
        customer_id: "",
        contact_id: "",
        order_date: "",
    };
}

function emptyErrors() {
    return {
        customer_id: [],
        contact_id: [],
        order_date: [],
    };
}

function resetForm(values = emptyForm()) {
    Object.assign(form, emptyForm(), values);
    formErrors.value = emptyErrors();
    formError.value = "";
}

function orderTitle(order) {
    return order?.id ? `SO-${order.id}` : "SO";
}

function truncatedCustomerName(name) {
    const value = typeof name === "string" ? name : "";

    if (value.length <= 32) {
        return value || "-";
    }

    return `${value.slice(0, 29)}...`;
}

function orderStatusBadges(order) {
    const status = String(order?.status_label || order?.status || "").trim();

    if (status === "") {
        return [];
    }

    return [{
        label: status,
        tone: order?.status === "COMPLETED" ? "green" : order?.status === "CANCELLED" ? "red" : "blue",
    }];
}

function orderCardRows(order) {
    return [
        {
            left: [{ label: "Customer", value: truncatedCustomerName(order?.customer_name || "") }],
            right: [{ label: "Total", value: order?.order_total_amount ? `$${order.order_total_amount}` : "-" }],
        },
        {
            left: [{ label: "Contact", value: order?.contact_name || "-" }],
            right: [{ label: "Lines", value: Number.isFinite(Number(order?.line_count)) ? String(order.line_count) : "-" }],
        },
        {
            left: [{ label: "City", value: order?.city || "-" }],
            right: [{ label: "Status", value: String(order?.status_label || order?.status || "-") }],
        },
    ];
}

function selectedCustomer() {
    const customerId = Number(form.customer_id);

    if (!customerId) {
        return null;
    }

    return customers.value.find((customer) => customer.id === customerId) ?? null;
}

function selectedCustomerContacts() {
    return selectedCustomer()?.contacts ?? [];
}

function defaultContactIdForCustomer(customerId) {
    const customer = customers.value.find((entry) => entry.id === Number(customerId));

    if (!customer?.primary_contact_id) {
        return "";
    }

    return String(customer.primary_contact_id);
}

function handleCustomerChange() {
    form.contact_id = defaultContactIdForCustomer(form.customer_id);
}

function orderToForm(order) {
    return {
        customer_id: order?.customer_id ? String(order.customer_id) : "",
        contact_id: order?.contact_id ? String(order.contact_id) : "",
        order_date: order?.date || "",
    };
}

function normalizeErrors(errors) {
    if (!errors || typeof errors !== "object") {
        return emptyErrors();
    }

    return {
        ...emptyErrors(),
        customer_id: Array.isArray(errors.customer_id) ? errors.customer_id : [],
        contact_id: Array.isArray(errors.contact_id) ? errors.contact_id : [],
        order_date: Array.isArray(errors.order_date) ? errors.order_date : [],
    };
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
        toastTimer.value = null;
    }, 1500);
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
        listError.value = error.payload?.message ?? "Unable to load orders.";
        showToast(listError.value, "error");
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    formMode.value = "create";
    editingOrderId.value = null;
    resetForm();
    formDrawerOpen.value = true;
}

function openEditDrawer(order) {
    if (!order?.can_edit) {
        return;
    }

    formMode.value = "edit";
    editingOrderId.value = order.id;
    resetForm(orderToForm(order));
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
    formErrors.value = emptyErrors();
    formError.value = "";

    try {
        const isCreate = formMode.value === "create";
        const url = isCreate ? props.crudConfig.endpoints.create : `${props.payload.updateUrlBase}/${editingOrderId.value}`;
        const response = await jsonRequest(url, {
            method: isCreate ? "POST" : "PATCH",
            body: JSON.stringify({
                customer_id: form.customer_id === "" ? null : Number(form.customer_id),
                contact_id: form.contact_id === "" ? null : Number(form.contact_id),
                order_date: form.order_date || null,
            }),
        });

        await fetchOrders();
        refreshShellNavigation();
        formDrawerOpen.value = false;
        showToast(response.message ?? (isCreate ? "Sales order created." : "Sales order updated."));
    } catch (error) {
        formErrors.value = normalizeErrors(error.payload?.errors);
        formError.value = error.payload?.message ?? "Something went wrong. Please try again.";
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

function openExportDrawer() {
    exportError.value = "";
    exportScope.value = "current";
    exportDrawerOpen.value = true;
}

function submitExport(scope) {
    exportSubmitting.value = true;
    exportError.value = "";

    const params = new URLSearchParams({ scope });

    if (scope !== "all") {
        params.set("search", search.value);
        params.set("sort", sort.column);
        params.set("direction", sort.direction);
    }

    window.location.assign(`${props.crudConfig.endpoints.export}?${params.toString()}`);
    window.setTimeout(() => {
        exportSubmitting.value = false;
        exportDrawerOpen.value = false;
    }, 500);
}

function openImportDrawer() {
    importDrawerOpen.value = true;
    selectedImportSource.value = "";
    previewRows.value = [];
    previewSearch.value = "";
    showDuplicateRows.value = true;
    importErrors.value = {};
    importConnectRequired.value = false;
}

function closeImportDrawer() {
    if (loadingPreview.value || importSubmitting.value) {
        return;
    }

    importDrawerOpen.value = false;
}

async function handleImportSourceChange(source) {
    selectedImportSource.value = source;
    previewRows.value = [];
    importErrors.value = {};
    importConnectRequired.value = false;

    if (source === "" || source === "file-upload" || showConnectionRequired.value) {
        return;
    }

    await loadExternalPreview(source);
}

async function loadExternalPreview(source) {
    loadingPreview.value = true;

    try {
        const response = await jsonRequest(props.importConfig.endpoints.preview, {
            method: "POST",
            body: JSON.stringify({ source }),
        });
        previewRows.value = normalizePreviewRows(response.data?.rows ?? [], source);
    } catch (error) {
        importErrors.value = error.payload?.errors ?? {};
        importConnectRequired.value = Boolean(error.payload?.meta?.connect_required);
    } finally {
        loadingPreview.value = false;
    }
}

async function handleLocalFileChange(event) {
    const file = event.target.files?.[0];
    importErrors.value = {};

    if (!file) {
        importErrors.value = { file: ["Please choose a CSV file."] };
        return;
    }

    loadingPreview.value = true;

    try {
        const text = await file.text();
        const rows = parseOrderCsv(text);

        if (rows.length === 0) {
            importErrors.value = { file: [importMessages.value.emptyFileRows ?? "The selected CSV file does not contain any order rows."] };
            return;
        }

        const response = await jsonRequest(props.importConfig.endpoints.preview, {
            method: "POST",
            body: JSON.stringify({
                source: "file-upload",
                rows,
            }),
        });

        previewRows.value = normalizePreviewRows(response.data?.rows ?? [], "file-upload");
    } catch (error) {
        importErrors.value = error.payload?.errors ?? { file: [error.message ?? "The selected CSV file could not be previewed."] };
    } finally {
        event.target.value = "";
        loadingPreview.value = false;
    }
}

function parseOrderCsv(text) {
    const csvRows = parseCsvRows(text).filter((row) => row.some((value) => String(value ?? "").trim() !== ""));
    const headers = (csvRows.shift() ?? []).map((header) => String(header ?? "").trim());
    const missingHeaders = orderCsvHeaders.filter((header) => !headers.includes(header));

    if (missingHeaders.length > 0) {
        throw fileImportError(importMessages.value.missingFileHeaders ?? "The selected CSV file is missing one or more required order headers.");
    }

    const parsedRows = csvRows.map((row, index) => headers.reduce((carry, header, rowIndex) => ({
        ...carry,
        [header]: row[rowIndex] ?? "",
    }), { __row_index: index }));
    const normalizedSources = parsedRows
        .map((record) => String(record.external_source ?? "").trim().toLowerCase())
        .filter((value) => value !== "");

    if (normalizedSources.length !== parsedRows.length) {
        throw fileImportError("Every imported order row must include external_source.");
    }

    if (new Set(normalizedSources).size > 1) {
        throw fileImportError("Every row in one import file must use the same external_source.");
    }

    const groupedRecords = new Map();

    for (const record of parsedRows) {
        const externalSource = String(record.external_source ?? "").trim();
        const orderExternalId = String(record.order_external_id ?? "").trim();

        if (orderExternalId === "") {
            throw fileImportError("Every imported order row must include order_external_id.");
        }

        const groupKey = `${externalSource.toLowerCase()}|${orderExternalId}`;

        if (!groupedRecords.has(groupKey)) {
            groupedRecords.set(groupKey, {
                external_id: orderExternalId,
                external_source: externalSource,
                external_status: String(record.external_status || record.status || "").trim(),
                status: String(record.status || "").trim(),
                date: String(record.order_date || "").trim(),
                contact_name: String(record.contact_name || "").trim(),
                customer: {
                    external_id: "",
                    name: String(record.customer_name || "").trim(),
                    email: "",
                    phone: "",
                    address_line_1: "",
                    address_line_2: "",
                    city: String(record.city || "").trim(),
                    region: "",
                    postal_code: "",
                    country_code: "",
                },
                lines: [],
                is_duplicate: false,
                selected: true,
            });
        }

        groupedRecords.get(groupKey).lines.push({
            external_id: String(record.line_external_id || "").trim(),
            product_external_id: String(record.product_external_id || "").trim(),
            name: String(record.product_name || "").trim(),
            quantity: String(record.quantity || "0.000000").trim(),
            unit_price: String(record.unit_price || "0").trim(),
            currency_code: "",
        });
    }

    return Array.from(groupedRecords.values());
}

function fileImportError(message) {
    return Object.assign(new Error(message), {
        payload: {
            errors: {
                file: [message],
            },
        },
    });
}

function parseCsvRows(text) {
    const rows = [];
    let currentRow = [];
    let currentValue = "";
    let inQuotes = false;

    for (let index = 0; index < text.length; index += 1) {
        const character = text[index];
        const nextCharacter = text[index + 1] || "";

        if (character === "\"") {
            if (inQuotes && nextCharacter === "\"") {
                currentValue += "\"";
                index += 1;
            } else {
                inQuotes = !inQuotes;
            }

            continue;
        }

        if (character === "," && !inQuotes) {
            currentRow.push(currentValue);
            currentValue = "";
            continue;
        }

        if ((character === "\n" || character === "\r") && !inQuotes) {
            if (character === "\r" && nextCharacter === "\n") {
                index += 1;
            }

            currentRow.push(currentValue);
            rows.push(currentRow);
            currentRow = [];
            currentValue = "";
            continue;
        }

        currentValue += character;
    }

    if (currentValue !== "" || currentRow.length > 0) {
        currentRow.push(currentValue);
        rows.push(currentRow);
    }

    return rows;
}

function normalizePreviewRows(rows, source) {
    return rows.map((row, index) => ({
        ...row,
        id: row.id ?? `${source}-${row.external_id ?? index}`,
        external_source: row.external_source ?? "",
        external_status: row.external_status ?? "",
        status: row.status ?? "",
        date: row.date ?? "",
        contact_name: row.contact_name ?? "",
        customer: row.customer && typeof row.customer === "object" && !Array.isArray(row.customer)
            ? {
                external_id: row.customer.external_id || "",
                name: row.customer.name || "",
                email: row.customer.email || "",
                phone: row.customer.phone || "",
                address_line_1: row.customer.address_line_1 || "",
                address_line_2: row.customer.address_line_2 || "",
                city: row.customer.city || "",
                region: row.customer.region || "",
                postal_code: row.customer.postal_code || "",
                country_code: row.customer.country_code || "",
            }
            : {
                external_id: "",
                name: "",
                email: "",
                phone: "",
                address_line_1: "",
                address_line_2: "",
                city: "",
                region: "",
                postal_code: "",
                country_code: "",
            },
        lines: Array.isArray(row.lines)
            ? row.lines.map((line) => ({
                external_id: line.external_id || "",
                product_external_id: line.product_external_id || "",
                name: line.name || "",
                quantity: line.quantity || "0.000000",
                unit_price: line.unit_price || "",
                unit_price_cents: line.unit_price_cents ?? null,
                currency_code: line.currency_code || "",
            }))
            : [],
        is_duplicate: Boolean(row.is_duplicate),
        selected: row.selected !== false,
    }));
}

function truncatedPreviewCustomerName(row) {
    const customer = row?.customer && typeof row.customer === "object" ? row.customer : {};

    return truncatedCustomerName(customer.name || "");
}

function compactPreviewMeta(row) {
    const customer = row?.customer && typeof row.customer === "object" ? row.customer : {};
    const date = typeof row?.date === "string" && row.date.trim() !== "" ? row.date.trim() : "-";
    const city = typeof customer.city === "string" && customer.city.trim() !== "" ? customer.city.trim() : "-";

    return `${date} - ${city}`;
}

function togglePreviewRowSelection({ row, event }) {
    const nextChecked = event.target.checked;
    previewRows.value = previewRows.value.map((previewRow) => (
        previewRow.id === row.id
            ? { ...previewRow, selected: nextChecked }
            : previewRow
    ));
}

function toggleVisiblePreviewSelection(event) {
    const nextChecked = event.target.checked;
    const visibleIds = new Set(visiblePreviewRows.value.map((row) => row.id));

    previewRows.value = previewRows.value.map((row) => {
        if (!visibleIds.has(row.id) || row.is_duplicate) {
            return row;
        }

        return { ...row, selected: nextChecked };
    });
}

async function submitImport() {
    const rows = previewRows.value
        .filter((row) => row.selected)
        .map((row) => ({
            external_id: row.external_id,
            external_source: row.external_source || selectedImportSource.value,
            external_status: row.external_status || "",
            status: row.status || "",
            date: row.date || null,
            contact_name: row.contact_name || null,
            customer: {
                external_id: row.customer?.external_id || "",
                name: row.customer?.name || "",
                email: row.customer?.email || null,
                phone: row.customer?.phone || null,
                address_line_1: row.customer?.address_line_1 || null,
                address_line_2: row.customer?.address_line_2 || null,
                city: row.customer?.city || null,
                region: row.customer?.region || null,
                postal_code: row.customer?.postal_code || null,
                country_code: row.customer?.country_code || null,
            },
            lines: Array.isArray(row.lines)
                ? row.lines.map((line) => ({
                    external_id: line.external_id,
                    product_external_id: line.product_external_id || "",
                    name: line.name || "",
                    quantity: line.quantity || "0.000000",
                    unit_price: line.unit_price || null,
                    unit_price_cents: line.unit_price_cents ?? null,
                    currency_code: line.currency_code || null,
                }))
                : [],
        }));

    if (rows.length === 0) {
        importErrors.value = { import: [importMessages.value.emptySelection ?? "Select at least one order to import."] };
        return;
    }

    importSubmitting.value = true;
    importErrors.value = {};

    try {
        const response = await jsonRequest(props.importConfig.endpoints.store, {
            method: "POST",
            body: JSON.stringify({
                source: selectedImportSource.value === "file-upload" ? "file-upload" : selectedImportSource.value,
                rows,
            }),
        });

        await fetchOrders();
        refreshShellNavigation();
        importDrawerOpen.value = false;
        showToast(response.message ?? "Orders imported.");
    } catch (error) {
        importErrors.value = error.payload?.errors ?? { import: [error.payload?.message ?? "Unable to import orders."] };
    } finally {
        importSubmitting.value = false;
    }
}

onMounted(() => {
    fetchOrders();
});
</script>

<template>
    <Head title="Sales Orders" />

    <AuthShell :shell="shell" title="Sales Orders">
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
                    @export="openExportDrawer"
                    @import="openImportDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title="orderTitle"
                            :title-aside="(order) => order.date || 'No order date'"
                            :title-badges="orderStatusBadges"
                            :detail-rows="orderCardRows"
                            :subtitle="(order) => order.customer_name || 'Customer not set'"
                            :mobile-subtitle="(order) => order.customer_name || 'Customer not set'"
                            :href="(order) => order.show_url"
                        />
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            :title="formMode === 'create' ? 'Create sales order' : 'Edit sales order'"
            description="Manage order header details without leaving the list page."
            :submit-label="formMode === 'create' ? 'Create Order' : 'Save Order'"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-md"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <div class="space-y-4">
                <div>
                    <label for="sales-order-customer" class="block text-sm font-medium text-gray-700">
                        Customer
                    </label>
                    <select
                        id="sales-order-customer"
                        v-model="form.customer_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        @change="handleCustomerChange"
                    >
                        <option value="">Select customer</option>
                        <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">
                            {{ customer.name }}
                        </option>
                    </select>
                    <p v-if="formErrors.customer_id[0]" class="mt-1 text-sm text-red-600">{{ formErrors.customer_id[0] }}</p>
                </div>

                <div>
                    <label for="sales-order-contact" class="block text-sm font-medium text-gray-700">
                        Contact
                    </label>
                    <select
                        id="sales-order-contact"
                        v-model="form.contact_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">No contact</option>
                        <option v-for="contact in selectedCustomerContacts()" :key="contact.id" :value="String(contact.id)">
                            {{ contact.full_name }}
                        </option>
                    </select>
                    <p v-if="formErrors.contact_id[0]" class="mt-1 text-sm text-red-600">{{ formErrors.contact_id[0] }}</p>
                </div>

                <div>
                    <label for="sales-order-date" class="block text-sm font-medium text-gray-700">
                        Order date
                    </label>
                    <input
                        id="sales-order-date"
                        v-model="form.order_date"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        @click="$event.target.showPicker?.()"
                    >
                    <p v-if="formErrors.order_date[0]" class="mt-1 text-sm text-red-600">{{ formErrors.order_date[0] }}</p>
                </div>
            </div>
        </ResourceCreateDrawer>

        <ResourceExportDrawer
            v-model:scope="exportScope"
            :open="exportDrawerOpen"
            :labels="labels"
            :submitting="exportSubmitting"
            :error="exportError"
            @close="exportDrawerOpen = false"
            @submit="submitExport"
        />

        <ResourceImportDrawer
            v-model:selected-source="selectedImportSource"
            v-model:preview-search="previewSearch"
            v-model:show-duplicate-rows="showDuplicateRows"
            :open="importDrawerOpen"
            :labels="importLabels"
            :sources="importSources"
            :preview-rows="visiblePreviewRows"
            :loading-preview="loadingPreview"
            :submitting="importSubmitting"
            :errors="importErrors"
            :duplicate-row-count="duplicateRowCount"
            :show-bulk-options="selectedImportSource === 'file-upload'"
            @close="closeImportDrawer"
            @source-change="handleImportSourceChange"
            @file-change="handleLocalFileChange"
            @toggle-row-selection="togglePreviewRowSelection"
            @toggle-visible-selection="toggleVisiblePreviewSelection"
            @submit="submitImport"
        >
            <template #connection-required>
                <div v-if="showConnectionRequired" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <h3 class="font-semibold text-amber-950">
                        Connection required
                    </h3>
                    <p class="mt-1">
                        WooCommerce status:
                        <span class="font-medium">{{ selectedSource?.status_label ?? "Not connected" }}</span>.
                    </p>
                    <p class="mt-2">
                        {{ canManageConnections ? "Manage store credentials from Profile > Connectors before loading a preview." : "Ask an admin to connect WooCommerce from Profile > Connectors before loading a preview." }}
                    </p>
                    <a
                        v-if="canManageConnections && connectorsPageUrl"
                        :href="connectorsPageUrl"
                        class="cursor-pointer mt-4 inline-flex rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500"
                    >
                        Open Connectors
                    </a>
                </div>
            </template>

            <template #bulk-options>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <label class="block font-medium text-gray-900">
                        Sales Orders CSV
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            class="mt-2 block w-full cursor-pointer rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:cursor-pointer file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                            @change="handleLocalFileChange"
                        >
                    </label>
                    <p class="mt-2 text-xs text-gray-500">
                        Required headers: {{ orderCsvHeaders.join(", ") }}
                    </p>
                </div>
            </template>

            <template #preview-rows="{ rows }">
                <article
                    v-for="row in rows"
                    :key="row.id ?? row.external_id"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
                >
                    <div class="flex min-h-10 items-center gap-3">
                        <input
                            type="checkbox"
                            class="shrink-0 rounded border-gray-300 text-[#6f895d] shadow-sm focus:ring-[#6f895d]"
                            :checked="Boolean(row.selected)"
                            :disabled="row.is_duplicate"
                            @change="togglePreviewRowSelection({ row, event: $event })"
                        >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">
                                {{ truncatedPreviewCustomerName(row) }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-gray-600">{{ compactPreviewMeta(row) }}</p>
                        </div>
                        <span
                            v-if="row.is_duplicate || row.external_status"
                            class="shrink-0 rounded-full px-2 py-0.5 text-[0.65rem] font-semibold uppercase"
                            :class="row.is_duplicate ? 'bg-yellow-50 text-yellow-800' : 'bg-blue-50 text-blue-700'"
                        >
                            {{ row.is_duplicate ? "Duplicate" : row.external_status }}
                        </span>
                    </div>
                </article>
            </template>
        </ResourceImportDrawer>
    </AuthShell>
</template>
