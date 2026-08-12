<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

import BaseDropdown from "../../../components/BaseDropdown.vue";
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

const requiredCustomerCsvHeaders = [
    "external_id",
    "external_source",
    "name",
    "email",
    "phone",
    "is_active",
    "address_line_1",
    "address_line_2",
    "city",
    "region",
    "postal_code",
    "country_code",
];

const customers = ref([...props.payload.customers]);
const search = ref("");
const sort = reactive({
    column: "name",
    direction: "asc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formMode = ref("create");
const editingCustomerId = ref(null);
const formSubmitting = ref(false);
const formError = ref("");
const formErrors = ref({});
const form = reactive(emptyForm());
const customerDetailsOpen = ref(true);
const customerAddressOpen = ref(false);

const exportDrawerOpen = ref(false);
const exportScope = ref("current");
const exportSubmitting = ref(false);
const exportError = ref("");

const importDrawerOpen = ref(false);
const selectedImportSource = ref("");
const previewRows = ref([]);
const previewSearch = ref("");
const showDuplicateRows = ref(false);
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

const duplicateRowCount = computed(() => previewRows.value.filter((row) => Boolean(row.is_duplicate)).length);

const visiblePreviewRows = computed(() => previewRows.value.filter((row) => {
    if (!showDuplicateRows.value && row.is_duplicate) {
        return false;
    }

    const needle = previewSearch.value.trim().toLowerCase();

    if (needle === "") {
        return true;
    }

    return [
        row.name,
        row.email,
        row.phone,
        row.external_id,
        row.external_source,
        row.address_line_1,
        row.address_line_2,
        row.city,
        row.region,
        row.postal_code,
        row.country_code,
        row.status_label,
    ].some((value) => String(value ?? "").toLowerCase().includes(needle));
}));

const selectedSource = computed(() => importSources.value.find((source) => source.value === selectedImportSource.value) ?? null);

const showConnectionRequired = computed(() => selectedImportSource.value !== ""
    && selectedImportSource.value !== "file-upload"
    && (selectedSource.value?.connected === false || selectedSource.value?.enabled === false || importConnectRequired.value));

function emptyForm() {
    return {
        name: "",
        customer_type: "business",
        currency_code: "USD",
        status: "active",
        notes: "",
        address_line_1: "",
        address_line_2: "",
        city: "",
        region: "",
        postal_code: "",
        country_code: "",
        formatted_address: "",
    };
}

function resetForm(values = emptyForm()) {
    Object.assign(form, emptyForm(), values);
    formErrors.value = {};
    formError.value = "";
}

function optionEntries(options) {
    if (Array.isArray(options)) {
        return options.map((option) => ({
            value: option.value ?? option.key ?? option,
            label: option.label ?? option.name ?? option,
        }));
    }

    return Object.entries(options ?? {}).map(([value, label]) => ({ value, label }));
}

function customerStatusLabel(customer) {
    return optionEntries(props.payload.statuses).find((statusOption) => statusOption.value === customer.status)?.label
        ?? customer.status
        ?? "Active";
}

function customerTitleBadges(customer) {
    const badges = [];
    const type = String(customer?.customer_type_label || "").trim();
    const status = String(customer?.status || "").trim();

    if (type !== "") {
        badges.push({
            label: type,
            tone: type.toLowerCase() === "consumer" ? "blue" : "gray",
        });
    }

    if (status !== "" && status.toLowerCase() !== "active") {
        badges.push({
            label: customerStatusLabel(customer),
            tone: status.toLowerCase() === "archived" ? "gray" : "yellow",
        });
    }

    return badges;
}

function customerCardRows(customer) {
    return [
        {
            left: [
                { label: "Primary email", value: customer?.email || "-" },
            ],
            right: [],
        },
        {
            left: [
                { label: "Address", value: customer?.address_summary || "-" },
            ],
            right: [],
        },
    ];
}

function badgeToneClasses(badge) {
    if (badge.tone === "blue") {
        return "bg-blue-50 text-blue-700";
    }

    if (badge.tone === "yellow") {
        return "bg-yellow-50 text-yellow-800";
    }

    return "bg-gray-100 text-gray-700";
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

async function fetchCustomers(nextSearch = search.value) {
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
            headers: {
                "Content-Type": "application/json",
            },
        });

        customers.value = response.data ?? [];

        if (response.meta?.sort) {
            sort.column = response.meta.sort.column ?? sort.column;
            sort.direction = response.meta.sort.direction ?? sort.direction;
        }
    } catch (error) {
        listError.value = error.payload?.message ?? "Unable to load customers.";
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    formMode.value = "create";
    editingCustomerId.value = null;
    resetForm();
    customerDetailsOpen.value = true;
    customerAddressOpen.value = false;
    formDrawerOpen.value = true;
}

function openEditDrawer(customer) {
    formMode.value = "edit";
    editingCustomerId.value = customer.id;
    customerDetailsOpen.value = true;
    customerAddressOpen.value = false;
    resetForm({
        name: customer.name ?? "",
        customer_type: customer.customer_type ?? "business",
        currency_code: customer.currency_code ?? "USD",
        status: customer.status ?? "active",
        notes: customer.notes ?? "",
        address_line_1: customer.address_line_1 ?? "",
        address_line_2: customer.address_line_2 ?? "",
        city: customer.city ?? "",
        region: customer.region ?? "",
        postal_code: customer.postal_code ?? "",
        country_code: customer.country_code ?? "",
        formatted_address: customer.formatted_address ?? "",
    });
    formDrawerOpen.value = true;
}

function closeFormDrawer() {
    if (formSubmitting.value) {
        return;
    }

    formDrawerOpen.value = false;
}

async function submitForm() {
    formSubmitting.value = true;
    formError.value = "";
    formErrors.value = {};

    try {
        const isEdit = formMode.value === "edit";
        const url = isEdit
            ? `${props.payload.updateUrlBase}/${editingCustomerId.value}`
            : props.payload.storeUrl;
        const response = await jsonRequest(url, {
            method: isEdit ? "PATCH" : "POST",
            body: JSON.stringify({ ...form }),
        });

        await fetchCustomers();
        refreshShellNavigation();
        formDrawerOpen.value = false;
        showToast(response.message ?? (isEdit ? "Customer updated." : "Customer created."));
    } catch (error) {
        formErrors.value = error.payload?.errors ?? {};
        formError.value = error.payload?.message ?? "Unable to save customer.";
    } finally {
        formSubmitting.value = false;
    }
}

async function archiveCustomer(customer) {
    if (!window.confirm(`Archive ${customer.name}?`)) {
        return;
    }

    loading.value = true;

    try {
        const response = await jsonRequest(`${props.payload.updateUrlBase}/${customer.id}`, {
            method: "DELETE",
        });
        await fetchCustomers();
        refreshShellNavigation();
        showToast(response.message ?? "Customer archived.");
    } catch (error) {
        showToast(error.payload?.message ?? "Unable to archive customer.", "error");
    } finally {
        loading.value = false;
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
    showDuplicateRows.value = false;
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
        const rows = parseCustomerCsv(text);

        if (rows.length === 0) {
            importErrors.value = { file: [importMessages.value.emptyFileRows ?? "The selected CSV file does not contain any customer rows."] };
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

function parseCustomerCsv(text) {
    const csvRows = parseCsvRows(text).filter((row) => row.some((value) => String(value ?? "").trim() !== ""));
    const headers = (csvRows.shift() ?? []).map((header) => String(header ?? "").trim());
    const missingHeaders = requiredCustomerCsvHeaders.filter((header) => !headers.includes(header));

    if (missingHeaders.length > 0) {
        throw Object.assign(new Error(importMessages.value.missingFileHeaders ?? "The selected CSV file is missing one or more required customer headers."), {
            payload: {
                errors: {
                    file: [importMessages.value.missingFileHeaders ?? "The selected CSV file is missing one or more required customer headers."],
                },
            },
        });
    }

    return csvRows.map((row, rowIndex) => {
        const record = Object.fromEntries(headers.map((header, index) => [header, row[index] ?? ""]));
        const customerName = String(record.name ?? "").trim();
        const externalId = String(record.external_id ?? "").trim()
            || `file-customer-${rowIndex + 1}-${slugify(customerName || "customer")}`;

        return {
            external_id: externalId,
            external_source: String(record.external_source ?? "").trim(),
            name: customerName,
            email: String(record.email ?? "").trim() || null,
            phone: String(record.phone ?? "").trim() || null,
            is_active: csvBoolean(record.is_active, true),
            is_duplicate: false,
            selected: true,
            address_line_1: String(record.address_line_1 ?? "").trim() || null,
            address_line_2: String(record.address_line_2 ?? "").trim() || null,
            city: String(record.city ?? "").trim() || null,
            region: String(record.region ?? "").trim() || null,
            postal_code: String(record.postal_code ?? "").trim() || null,
            country_code: String(record.country_code ?? "").trim() || null,
        };
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

function csvBoolean(value, fallback) {
    const normalized = String(value ?? "").trim().toLowerCase();

    if (normalized === "1" || normalized === "true" || normalized === "yes") {
        return true;
    }

    if (normalized === "0" || normalized === "false" || normalized === "no") {
        return false;
    }

    return fallback;
}

function slugify(value) {
    return String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        || "customer";
}

function normalizePreviewRows(rows, source) {
    return rows.map((row, index) => ({
        ...row,
        id: row.id ?? `${source}-${row.external_id ?? index}`,
        external_source: row.external_source ?? "",
        is_active: csvBoolean(row.is_active, false),
        is_duplicate: Boolean(row.is_duplicate),
        selected: row.selected !== false,
        email: row.email || "",
        phone: row.phone || "",
        address_line_1: row.address_line_1 || "",
        address_line_2: row.address_line_2 || "",
        city: row.city || "",
        region: row.region || "",
        postal_code: row.postal_code || "",
        country_code: row.country_code || "",
        subtitle: row.email || row.city || row.external_id || "",
    }));
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
            name: row.name,
            email: row.email || null,
            phone: row.phone || null,
            is_active: row.is_active,
            address_line_1: row.address_line_1 || null,
            address_line_2: row.address_line_2 || null,
            city: row.city || null,
            region: row.region || null,
            postal_code: row.postal_code || null,
            country_code: row.country_code || null,
        }));

    if (rows.length === 0) {
        importErrors.value = { import: [importMessages.value.emptySelection ?? "Select at least one customer to import."] };
        return;
    }

    importSubmitting.value = true;
    importErrors.value = {};

    try {
        const response = await jsonRequest(props.importConfig.endpoints.store, {
            method: "POST",
            body: JSON.stringify({
                source: selectedImportSource.value,
                rows,
            }),
        });

        await fetchCustomers();
        refreshShellNavigation();
        importDrawerOpen.value = false;
        showToast(`${response.data?.imported_count ?? rows.length} customers imported.`);
    } catch (error) {
        importErrors.value = error.payload?.errors ?? { import: [error.payload?.message ?? "Unable to import customers."] };
    } finally {
        importSubmitting.value = false;
    }
}
</script>

<template>
    <Head title="Customers" />

    <AuthShell :shell="shell" title="Customers">
        <div class="px-4 pb-10 pt-5 sm:px-6 lg:px-8">
            <div class="relative mx-auto max-w-7xl">
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
                    :records="customers"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-[calc(100vh-10rem)]"
                    @search="fetchCustomers"
                    @create="openCreateDrawer"
                    @export="openExportDrawer"
                    @import="openImportDrawer"
                >
                    <template #default="{ records }">
                        <div>
                            <div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>
                                <div class="min-h-0 flex-1 overflow-y-auto p-0" data-crud-records-scroll>
                                    <div class="border-t border-gray-300">
                                        <div
                                            v-for="customer in records"
                                            :key="`mobile-card-${customer.id}`"
                                            class="relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 overflow-visible border-b border-gray-300 bg-white px-4 py-2"
                                            data-crud-card
                                        >
                                            <a class="min-w-0 cursor-pointer" :href="customer.show_url">
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <div class="flex min-w-0 flex-1 items-center gap-2">
                                                        <p class="truncate text-sm font-semibold text-gray-900">
                                                            {{ customer.name || "-" }}
                                                        </p>
                                                        <span
                                                            v-for="badge in customerTitleBadges(customer)"
                                                            :key="`mobile-card-${customer.id}-title-badge-${badge.label}`"
                                                            class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide"
                                                            :class="badgeToneClasses(badge)"
                                                            data-crud-mobile-title-badge
                                                        >
                                                            {{ badge.label }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <p class="mt-0.5 truncate text-xs text-gray-600">
                                                    {{ customer.email || "-" }}
                                                </p>

                                                <div class="mt-2 space-y-1.5" data-crud-card-detail-rows>
                                                    <div
                                                        v-for="(row, rowIndex) in customerCardRows(customer)"
                                                        :key="`mobile-card-${customer.id}-detail-row-${rowIndex}`"
                                                        class="flex min-w-0 items-baseline justify-between gap-3 text-xs"
                                                    >
                                                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                                            <span
                                                                v-for="item in row.left"
                                                                :key="`mobile-card-${customer.id}-detail-left-${rowIndex}-${item.label}`"
                                                                class="inline-flex min-w-0 items-baseline gap-1"
                                                            >
                                                                <span class="shrink-0 text-[0.65rem] font-medium text-gray-400">{{ item.label }}</span>
                                                                <span class="min-w-0 truncate text-xs font-medium text-gray-700">{{ item.value }}</span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>

                                            <div class="relative z-10 flex shrink-0 items-center gap-2">
                                                <BaseDropdown :aria-label="labels.actionsAriaLabel">
                                                    <template #trigger>
                                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                        </svg>
                                                    </template>

                                                    <template #default="{ close }">
                                                        <button type="button" class="cursor-pointer flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem" @click="close(); openEditDrawer(customer)">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="cursor-pointer flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50" role="menuitem" @click="close(); archiveCustomer(customer)">
                                                            Archive
                                                        </button>
                                                    </template>
                                                </BaseDropdown>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden h-full min-h-0 md:block">
                                <div class="flex h-full min-h-0 flex-col">
                                    <div class="min-h-0 flex-1 overflow-y-auto p-6" data-crud-records-scroll>
                                        <div
                                            class="customer-card-grid grid gap-4"
                                            data-crud-card-grid
                                            :class="loading ? 'opacity-80' : 'opacity-100'"
                                        >
                                            <article
                                                v-for="customer in records"
                                                :key="`desktop-card-${customer.id}`"
                                                class="group flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md"
                                                data-crud-card
                                            >
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex min-w-0 items-center gap-2">
                                                            <a
                                                                class="cursor-pointer truncate text-base font-semibold text-gray-900"
                                                                :href="customer.show_url"
                                                            >
                                                                {{ customer.name || "-" }}
                                                            </a>
                                                            <span
                                                                v-for="badge in customerTitleBadges(customer)"
                                                                :key="`desktop-card-${customer.id}-title-badge-${badge.label}`"
                                                                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide"
                                                                :class="badgeToneClasses(badge)"
                                                                data-crud-desktop-title-badge
                                                            >
                                                                {{ badge.label }}
                                                            </span>
                                                        </div>
                                                        <p class="mt-0.5 truncate text-sm text-gray-500">
                                                            {{ customer.customer_type_label || "Customer" }}
                                                        </p>
                                                    </div>

                                                    <div class="relative flex shrink-0 items-start gap-2">
                                                        <BaseDropdown :aria-label="labels.actionsAriaLabel">
                                                            <template #trigger>
                                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                                </svg>
                                                            </template>

                                                            <template #default="{ close }">
                                                                <button type="button" class="cursor-pointer flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem" @click="close(); openEditDrawer(customer)">
                                                                    Edit
                                                                </button>
                                                                <button type="button" class="cursor-pointer flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50" role="menuitem" @click="close(); archiveCustomer(customer)">
                                                                    Archive
                                                                </button>
                                                            </template>
                                                        </BaseDropdown>
                                                    </div>
                                                </div>

                                                <div class="mt-2 space-y-1.5" data-crud-card-detail-rows>
                                                    <div
                                                        v-for="(row, rowIndex) in customerCardRows(customer)"
                                                        :key="`desktop-card-${customer.id}-detail-row-${rowIndex}`"
                                                        class="flex min-w-0 items-baseline justify-between gap-3 text-xs"
                                                    >
                                                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                                            <span
                                                                v-for="item in row.left"
                                                                :key="`desktop-card-${customer.id}-detail-left-${rowIndex}-${item.label}`"
                                                                class="inline-flex min-w-0 items-baseline gap-1"
                                                            >
                                                                <span class="shrink-0 text-[0.65rem] font-medium text-gray-400">{{ item.label }}</span>
                                                                <span class="min-w-0 truncate text-xs font-medium text-gray-700">{{ item.value }}</span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            :title="formMode === 'edit' ? 'Edit Customer' : labels.createTitle"
            :submit-label="formMode === 'edit' ? 'Save Customer' : 'Create Customer'"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-md"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <div class="space-y-3">
                <section class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    <button
                        type="button"
                        class="cursor-pointer flex w-full items-center justify-between gap-4 text-left"
                        :class="customerDetailsOpen ? 'mb-3' : ''"
                        :aria-expanded="customerDetailsOpen"
                        @click="customerDetailsOpen = !customerDetailsOpen"
                    >
                        <h3 class="text-xs font-bold uppercase tracking-wide text-[#001f3f]">
                            Customer Details
                        </h3>
                        <svg
                            class="h-4 w-4 text-[#001f3f] transition-transform"
                            :class="customerDetailsOpen ? '' : 'rotate-180'"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 15 7-7 7 7" />
                        </svg>
                    </button>

                    <div v-show="customerDetailsOpen" class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs font-semibold text-[#001f3f] sm:col-span-2">
                        Name <span class="text-red-500">*</span>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                            placeholder="Full name"
                            required
                        >
                        <span v-if="formErrors.name?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.name[0] }}</span>
                    </label>

                    <label class="block text-xs font-semibold text-[#001f3f]">
                        Customer Type
                        <select
                            v-model="form.customer_type"
                            class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                            <option
                                v-for="typeOption in optionEntries(payload.customerTypes)"
                                :key="typeOption.value"
                                :value="typeOption.value"
                            >
                                {{ typeOption.label }}
                            </option>
                        </select>
                        <span v-if="formErrors.customer_type?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.customer_type[0] }}</span>
                    </label>

                    <label class="block text-xs font-semibold text-[#001f3f]">
                        Currency
                        <input
                            v-model="form.currency_code"
                            type="text"
                            maxlength="3"
                            class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs uppercase shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                        <span v-if="formErrors.currency_code?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.currency_code[0] }}</span>
                    </label>

                    <label v-if="formMode === 'edit'" class="block text-xs font-semibold text-[#001f3f] sm:col-span-2">
                        Status
                        <select
                            v-model="form.status"
                            class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        >
                            <option
                                v-for="statusOption in optionEntries(payload.statuses)"
                                :key="statusOption.value"
                                :value="statusOption.value"
                            >
                                {{ statusOption.label }}
                            </option>
                        </select>
                        <span v-if="formErrors.status?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.status[0] }}</span>
                    </label>

                    <label class="block text-xs font-semibold text-[#001f3f] sm:col-span-2">
                        Notes
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="mt-1.5 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                            placeholder="Add a note..."
                        />
                        <span v-if="formErrors.notes?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.notes[0] }}</span>
                    </label>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                    <button
                        type="button"
                        class="cursor-pointer flex w-full items-center justify-between gap-4 text-left"
                        :class="customerAddressOpen ? 'mb-3 border-b border-gray-200 pb-3' : ''"
                        :aria-expanded="customerAddressOpen"
                        @click="customerAddressOpen = !customerAddressOpen"
                    >
                        <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-[#6f895d]">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.33 6-11a6 6 0 1 0-12 0c0 5.67 6 11 6 11Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5h.008v.008H12V10.5Z" />
                            </svg>
                            Address
                        </h3>
                        <svg
                            class="h-4 w-4 text-[#001f3f] transition-transform"
                            :class="customerAddressOpen ? '' : 'rotate-180'"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.8"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 15 7-7 7 7" />
                        </svg>
                    </button>

                    <div v-show="customerAddressOpen" class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-[#001f3f] sm:col-span-2">
                            Address Line 1
                            <input v-model="form.address_line_1" type="text" class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" placeholder="Street address, P.O. box, company name, etc.">
                            <span v-if="formErrors.address_line_1?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.address_line_1[0] }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-[#001f3f] sm:col-span-2">
                            Address Line 2 <span class="font-normal text-gray-500">(optional)</span>
                            <input v-model="form.address_line_2" type="text" class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" placeholder="Apartment, suite, unit, building, floor, etc.">
                            <span v-if="formErrors.address_line_2?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.address_line_2[0] }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-[#001f3f]">
                            City
                            <input v-model="form.city" type="text" class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" placeholder="City">
                            <span v-if="formErrors.city?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.city[0] }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-[#001f3f]">
                            Region
                            <input v-model="form.region" type="text" class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" placeholder="State, province, or region">
                            <span v-if="formErrors.region?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.region[0] }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-[#001f3f]">
                            Postal Code
                            <input v-model="form.postal_code" type="text" class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" placeholder="Postal code">
                            <span v-if="formErrors.postal_code?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.postal_code[0] }}</span>
                        </label>

                        <label class="block text-xs font-semibold text-[#001f3f]">
                            Country Code
                            <input v-model="form.country_code" type="text" maxlength="2" class="mt-1.5 block h-8 w-full rounded-md border-gray-300 text-xs uppercase shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" placeholder="US">
                            <span v-if="formErrors.country_code?.[0]" class="mt-1 block text-xs text-red-600">{{ formErrors.country_code[0] }}</span>
                        </label>

                    </div>
                </section>
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
                <div v-if="selectedImportSource === 'file-upload'" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <label class="block font-medium text-gray-900">
                        Customer CSV
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            class="mt-2 block w-full cursor-pointer rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:cursor-pointer file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                            @change="handleLocalFileChange"
                        >
                    </label>
                    <p class="mt-2 text-xs text-gray-500">
                        Required headers: {{ requiredCustomerCsvHeaders.join(", ") }}
                    </p>
                </div>
                <div v-else class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                    {{ importLabels.noBulkOptions ?? "No additional import options are available for this resource." }}
                </div>
            </template>
        </ResourceImportDrawer>
    </AuthShell>
</template>

<style scoped>
.customer-card-grid {
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 18rem), 1fr));
}
</style>
