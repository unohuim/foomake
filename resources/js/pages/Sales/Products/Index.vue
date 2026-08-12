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

const requiredProductCsvHeaders = [
    "name",
    "base_uom_id",
    "is_active",
    "is_purchasable",
    "is_manufacturable",
    "default_price_amount",
    "default_price_currency_code",
    "external_source",
    "external_id",
];

const products = ref([]);
const search = ref("");
const sort = reactive({
    column: "name",
    direction: "desc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formMode = ref("create");
const editingProductId = ref(null);
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
const createFulfillmentRecipes = ref(true);
const importAllAsManufacturable = ref(false);
const importAllAsPurchasable = ref(false);
const bulkBaseUomId = ref("");

const labels = computed(() => props.crudConfig.labels ?? {});
const importLabels = computed(() => props.importConfig.labels ?? {});
const importMessages = computed(() => props.importConfig.messages ?? {});
const importSources = computed(() => props.importConfig.sources ?? []);
const uoms = computed(() => props.importConfig.uoms ?? props.payload.uoms ?? []);
const canManageConnections = computed(() => Boolean(props.importConfig.permissions?.canManageConnections));
const connectorsPageUrl = computed(() => props.importConfig.connectorsPageUrl ?? "");
const tenantCurrency = computed(() => props.payload.tenantCurrency ?? "USD");
const canManageProducts = computed(() => Boolean(props.payload.canManageProducts));

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
        row.sku,
        row.external_id,
        row.external_source,
        row.price,
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
        base_uom_id: "",
        is_purchasable: false,
        is_manufacturable: false,
        default_price_amount: "",
    };
}

function resetForm(values = emptyForm()) {
    Object.assign(form, emptyForm(), values);
    formErrors.value = {};
    formError.value = "";
}

function productBaseUomLabel(product) {
    const baseUom = product?.base_uom || {};

    if (baseUom.name && baseUom.symbol) {
        return `${baseUom.name} (${baseUom.symbol})`;
    }

    return baseUom.name || baseUom.symbol || "-";
}

function formattedProductPrice(product) {
    if (product?.price && product?.currency) {
        return `${product.currency} ${product.price}`;
    }

    if (product?.price) {
        return product.price;
    }

    return "-";
}

function productTitleBadges(product) {
    const badges = [];

    if (product?.is_manufacturable) {
        badges.push({ label: "Manufacturable", tone: "blue" });
    }

    if (product?.is_purchasable) {
        badges.push({ label: "Purchasable", tone: "green" });
    }

    return badges;
}

function productCardRows(product) {
    return [
        {
            left: [{ label: "Base UoM", value: productBaseUomLabel(product) }],
            right: [{ label: "Price", value: formattedProductPrice(product) }],
        },
    ];
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

async function fetchProducts(nextSearch = search.value) {
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

        products.value = response.data ?? [];

        if (response.meta?.sort) {
            sort.column = response.meta.sort.column ?? sort.column;
            sort.direction = response.meta.sort.direction ?? sort.direction;
        }
    } catch (error) {
        listError.value = error.payload?.message ?? "Unable to load products.";
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    formMode.value = "create";
    editingProductId.value = null;
    resetForm();
    formDrawerOpen.value = true;
}

function openEditDrawer(product) {
    formMode.value = "edit";
    editingProductId.value = product.id;
    resetForm({
        name: product.name ?? "",
        base_uom_id: product.base_uom?.id ? String(product.base_uom.id) : "",
        is_purchasable: Boolean(product.is_purchasable),
        is_manufacturable: Boolean(product.is_manufacturable),
        default_price_amount: product.price ?? "",
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
            ? `${props.payload.updateUrlBase}/${editingProductId.value}`
            : props.crudConfig.endpoints.create;
        const response = await jsonRequest(url, {
            method: isEdit ? "PATCH" : "POST",
            body: JSON.stringify({ ...form }),
        });

        await fetchProducts();
        refreshShellNavigation();
        formDrawerOpen.value = false;
        showToast(response.message ?? (isEdit ? "Product updated." : "Product created."));
    } catch (error) {
        formErrors.value = error.payload?.errors ?? {};
        formError.value = error.payload?.message ?? "Unable to save product.";
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
    showDuplicateRows.value = false;
    importErrors.value = {};
    importConnectRequired.value = false;
    createFulfillmentRecipes.value = true;
    importAllAsManufacturable.value = false;
    importAllAsPurchasable.value = false;
    bulkBaseUomId.value = "";
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
        const rows = parseProductCsv(text);

        if (rows.length === 0) {
            importErrors.value = { file: [importMessages.value.emptyFileRows ?? "The selected CSV file does not contain any product rows."] };
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

function parseProductCsv(text) {
    const csvRows = parseCsvRows(text).filter((row) => row.some((value) => String(value ?? "").trim() !== ""));
    const headers = (csvRows.shift() ?? []).map((header) => String(header ?? "").trim());
    const missingHeaders = requiredProductCsvHeaders.filter((header) => !headers.includes(header));

    if (missingHeaders.length > 0) {
        throw Object.assign(new Error(importMessages.value.missingFileHeaders ?? "The selected CSV file is missing one or more required product headers."), {
            payload: {
                errors: {
                    file: [importMessages.value.missingFileHeaders ?? "The selected CSV file is missing one or more required product headers."],
                },
            },
        });
    }

    return csvRows.map((row, rowIndex) => {
        const record = Object.fromEntries(headers.map((header, index) => [header, row[index] ?? ""]));
        const productName = String(record.name ?? "").trim();

        return {
            external_id: String(record.external_id ?? "").trim() || `file-product-${rowIndex + 1}-${slugify(productName || "product")}`,
            external_source: String(record.external_source ?? "").trim(),
            name: productName,
            base_uom_id: String(record.base_uom_id ?? "").trim() || null,
            is_active: csvBoolean(record.is_active, true),
            is_purchasable: csvBoolean(record.is_purchasable, false),
            is_manufacturable: csvBoolean(record.is_manufacturable, false),
            default_price_cents: amountToCents(record.default_price_amount),
            default_price_currency_code: String(record.default_price_currency_code ?? "").trim(),
            is_duplicate: false,
            selected: true,
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

function amountToCents(value) {
    const normalized = String(value ?? "").trim();

    if (normalized === "") {
        return null;
    }

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        throw Object.assign(new Error("The selected CSV file contains an invalid product price."), {
            payload: {
                errors: {
                    file: ["Product prices must be valid currency amounts with up to two decimals."],
                },
            },
        });
    }

    const [whole, decimal = ""] = normalized.split(".");
    const cents = `${decimal}00`.slice(0, 2);

    return (Number.parseInt(whole, 10) * 100) + Number.parseInt(cents, 10);
}

function slugify(value) {
    return String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        || "product";
}

function normalizePreviewRows(rows, source) {
    return rows.map((row, index) => ({
        ...row,
        id: row.id ?? `${source}-${row.external_id ?? index}`,
        external_source: row.external_source ?? "",
        is_duplicate: Boolean(row.is_duplicate),
        selected: row.selected !== false,
        name: row.name || "",
        sku: row.sku || "",
        subtitle: row.sku || row.external_id || "",
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
            external_source: row.external_source || selectedImportSource.value,
            name: row.name,
            base_uom_id: row.base_uom_id || bulkBaseUomId.value || null,
            is_active: row.is_active !== false,
            is_purchasable: row.is_purchasable,
            is_manufacturable: row.is_manufacturable,
            default_price_cents: row.default_price_cents ?? null,
            default_price_currency_code: row.default_price_currency_code ?? null,
            image_url: row.image_url ?? null,
        }));

    if (rows.length === 0) {
        importErrors.value = { import: ["Select at least one product to import."] };
        return;
    }

    importSubmitting.value = true;
    importErrors.value = {};

    try {
        const response = await jsonRequest(props.importConfig.endpoints.store, {
            method: "POST",
            body: JSON.stringify({
                source: selectedImportSource.value,
                is_local_file_import: selectedImportSource.value === "file-upload",
                bulk_base_uom_id: bulkBaseUomId.value || null,
                create_fulfillment_recipes: createFulfillmentRecipes.value,
                import_all_as_manufacturable: importAllAsManufacturable.value,
                import_all_as_purchasable: importAllAsPurchasable.value,
                rows,
            }),
        });

        await fetchProducts();
        refreshShellNavigation();
        importDrawerOpen.value = false;
        showToast(`${response.data?.imported_count ?? rows.length} products imported.`);
    } catch (error) {
        importErrors.value = error.payload?.errors ?? { import: [error.payload?.message ?? "Unable to import products."] };
    } finally {
        importSubmitting.value = false;
    }
}

onMounted(() => {
    fetchProducts();
});
</script>

<template>
    <Head title="Products" />

    <AuthShell :shell="shell" title="Products">
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
                    :records="products"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchProducts"
                    @create="openCreateDrawer"
                    @export="openExportDrawer"
                    @import="openImportDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title-badges="productTitleBadges"
                            :detail-rows="productCardRows"
                            :subtitle="productBaseUomLabel"
                            :mobile-subtitle="formattedProductPrice"
                            :actions="canManageProducts ? [{ id: 'edit', label: 'Edit', tone: 'default' }] : []"
                            @action="({ record }) => openEditDrawer(record)"
                        />
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            :title="formMode === 'edit' ? 'Edit Product' : labels.createTitle"
            :description="formMode === 'edit' ? 'Update sellable product details without leaving the page.' : 'Create a new sellable product item for this tenant.'"
            :submit-label="formMode === 'edit' ? 'Save Changes' : 'Add Product'"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-md"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <div class="mt-6 space-y-5">
                <div>
                    <label for="product-name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input
                        id="product-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    />
                    <p v-if="formErrors.name?.[0]" class="mt-1 text-sm text-red-600">{{ formErrors.name[0] }}</p>
                </div>

                <div>
                    <label for="product-base-uom" class="block text-sm font-medium text-gray-700">Base Unit of Measure</label>
                    <select
                        id="product-base-uom"
                        v-model="form.base_uom_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    >
                        <option value="">Select a unit</option>
                        <option v-for="uom in uoms" :key="uom.id" :value="String(uom.id)">
                            {{ uom.name }} ({{ uom.symbol }})
                        </option>
                    </select>
                    <p v-if="formErrors.base_uom_id?.[0]" class="mt-1 text-sm text-red-600">{{ formErrors.base_uom_id[0] }}</p>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700">Planning price</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="product-default-price-amount" class="block text-xs font-medium text-gray-600">Amount</label>
                            <input
                                id="product-default-price-amount"
                                v-model="form.default_price_amount"
                                type="text"
                                inputmode="decimal"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <p v-if="formErrors.default_price_amount?.[0]" class="mt-1 text-sm text-red-600">{{ formErrors.default_price_amount[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Currency</label>
                            <input
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm sm:text-sm"
                                :value="tenantCurrency"
                                disabled
                            />
                            <p v-if="formErrors.default_price_currency_code?.[0]" class="mt-1 text-sm text-red-600">{{ formErrors.default_price_currency_code[0] }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-700">Flags</p>
                    <div class="mt-3 space-y-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked disabled>
                            Sellable
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_purchasable" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Purchasable
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_manufacturable" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Manufacturable
                        </label>
                    </div>
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
            :open="importDrawerOpen"
            :labels="importLabels"
            :sources="importSources"
            :preview-rows="visiblePreviewRows"
            :loading-preview="loadingPreview"
            :submitting="importSubmitting"
            :errors="importErrors"
            :show-bulk-options="selectedImportSource !== '' && !showConnectionRequired"
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
                <div class="space-y-3">
                    <label v-if="selectedImportSource === 'file-upload'" class="block text-sm font-medium text-gray-900">
                        Product CSV
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            class="mt-2 block w-full cursor-pointer rounded-md border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:cursor-pointer file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                            @change="handleLocalFileChange"
                        >
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="createFulfillmentRecipes" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Create fulfillment recipes
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="importAllAsManufacturable" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Import all selected as manufacturable
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="importAllAsPurchasable" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Import all selected as purchasable
                    </label>
                    <label class="block text-sm font-medium text-gray-700">
                        Bulk base UoM
                        <select v-model="bulkBaseUomId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select a unit</option>
                            <option v-for="uom in uoms" :key="uom.id" :value="String(uom.id)">
                                {{ uom.name }} ({{ uom.symbol }})
                            </option>
                        </select>
                    </label>
                </div>
            </template>

            <template #preview-rows="{ rows }">
                <article
                    v-for="row in rows"
                    :key="row.id ?? row.external_id ?? row.name"
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
                                {{ row.name || "-" }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-gray-600">{{ formattedProductPrice(row) }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ row.sku || row.external_id || "-" }}</p>
                        </div>
                    </div>
                </article>
            </template>
        </ResourceImportDrawer>
    </AuthShell>
</template>
