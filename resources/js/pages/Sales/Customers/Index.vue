<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

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
        customer_type: "retail",
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

function customerTypeLabel(customer) {
    return customer.customer_type_label
        ?? optionEntries(props.payload.customerTypes).find((typeOption) => typeOption.value === customer.customer_type)?.label
        ?? "Customer";
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

function toggleSort(column) {
    if (sort.column === column) {
        sort.direction = sort.direction === "asc" ? "desc" : "asc";
    } else {
        sort.column = column;
        sort.direction = "asc";
    }

    fetchCustomers();
}

function openCreateDrawer() {
    formMode.value = "create";
    editingCustomerId.value = null;
    resetForm();
    formDrawerOpen.value = true;
}

function openEditDrawer(customer) {
    formMode.value = "edit";
    editingCustomerId.value = customer.id;
    resetForm({
        name: customer.name ?? "",
        customer_type: customer.customer_type ?? "retail",
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
                        <div class="border-b border-gray-100 bg-gray-50 px-4 py-2">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-gray-600">
                                <span class="mr-1 text-gray-500">Sort</span>
                                <button
                                    v-for="column in crudConfig.sortable"
                                    :key="column"
                                    type="button"
                                    class="cursor-pointer rounded-md px-2.5 py-1 transition hover:bg-white hover:text-gray-900"
                                    :class="sort.column === column ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200' : ''"
                                    @click="toggleSort(column)"
                                >
                                    {{ crudConfig.headers[column] ?? column }}
                                    <span v-if="sort.column === column" aria-hidden="true">
                                        {{ sort.direction === "asc" ? "ASC" : "DESC" }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="divide-y divide-gray-100">
                            <article
                                v-for="customer in records"
                                :key="customer.id"
                                class="px-4 py-4 transition hover:bg-gray-50 sm:px-6"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a
                                                :href="customer.show_url"
                                                class="cursor-pointer truncate text-sm font-semibold text-gray-950 hover:text-blue-700"
                                            >
                                                {{ customer.name || "Unnamed customer" }}
                                            </a>
                                            <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                {{ customerTypeLabel(customer) }}
                                            </span>
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                {{ customerStatusLabel(customer) }}
                                            </span>
                                        </div>

                                        <div class="mt-2 grid gap-2 text-sm text-gray-600 md:grid-cols-3">
                                            <p class="truncate">
                                                {{ customer.email || "No email" }}
                                            </p>
                                            <p class="truncate md:col-span-2">
                                                {{ customer.address_summary || "No address" }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <button
                                            type="button"
                                            class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                                            @click="openEditDrawer(customer)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="cursor-pointer rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-amber-700 transition hover:bg-amber-50"
                                            @click="archiveCustomer(customer)"
                                        >
                                            Archive
                                        </button>
                                    </div>
                                </div>
                            </article>
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
            panel-class="w-screen max-w-2xl"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 sm:col-span-2">
                        Name
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            required
                        >
                        <span v-if="formErrors.name?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.name[0] }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700">
                        Customer Type
                        <select
                            v-model="form.customer_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option
                                v-for="typeOption in optionEntries(payload.customerTypes)"
                                :key="typeOption.value"
                                :value="typeOption.value"
                            >
                                {{ typeOption.label }}
                            </option>
                        </select>
                        <span v-if="formErrors.customer_type?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.customer_type[0] }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700">
                        Currency
                        <input
                            v-model="form.currency_code"
                            type="text"
                            maxlength="3"
                            class="mt-1 block w-full uppercase rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <span v-if="formErrors.currency_code?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.currency_code[0] }}</span>
                    </label>

                    <label v-if="formMode === 'edit'" class="block text-sm font-medium text-gray-700 sm:col-span-2">
                        Status
                        <select
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option
                                v-for="statusOption in optionEntries(payload.statuses)"
                                :key="statusOption.value"
                                :value="statusOption.value"
                            >
                                {{ statusOption.label }}
                            </option>
                        </select>
                        <span v-if="formErrors.status?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.status[0] }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 sm:col-span-2">
                        Notes
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <span v-if="formErrors.notes?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.notes[0] }}</span>
                    </label>
                </div>

                <section class="space-y-4 border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Address
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 sm:col-span-2">
                            Address Line 1
                            <input v-model="form.address_line_1" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span v-if="formErrors.address_line_1?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.address_line_1[0] }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 sm:col-span-2">
                            Address Line 2
                            <input v-model="form.address_line_2" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span v-if="formErrors.address_line_2?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.address_line_2[0] }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700">
                            City
                            <input v-model="form.city" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span v-if="formErrors.city?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.city[0] }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700">
                            Region
                            <input v-model="form.region" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span v-if="formErrors.region?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.region[0] }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700">
                            Postal Code
                            <input v-model="form.postal_code" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span v-if="formErrors.postal_code?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.postal_code[0] }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700">
                            Country Code
                            <input v-model="form.country_code" type="text" maxlength="2" class="mt-1 block w-full uppercase rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span v-if="formErrors.country_code?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.country_code[0] }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 sm:col-span-2">
                            Formatted Address
                            <textarea v-model="form.formatted_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <span v-if="formErrors.formatted_address?.[0]" class="mt-1 block text-sm text-red-600">{{ formErrors.formatted_address[0] }}</span>
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
