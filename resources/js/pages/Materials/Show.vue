<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

import BaseDrawer from "../../components/BaseDrawer.vue";
import BaseDropdown from "../../components/BaseDropdown.vue";
import MaterialQuantityBar from "../../components/MaterialQuantityBar.vue";
import ResourceDetailHeaderBreadcrumb from "../../components/ResourceDetailHeaderBreadcrumb.vue";
import ResourceDetailSection from "../../components/ResourceDetailSection.vue";
import UiToast from "../../components/UiToast.vue";
import AuthShell from "../../layouts/AuthShell.vue";

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
    payload: {
        type: Object,
        default: null,
    },
});

const loading = ref(!props.payload);
const pageError = ref("");
const payload = ref(props.payload);
const toast = ref(null);
const toastTimer = ref(null);
const nameEditorOpen = ref(false);
const nameDraft = ref("");
const nameError = ref("");
const nameSaving = ref(false);
const typeSaving = reactive({
    is_sellable: false,
    is_purchasable: false,
    is_manufacturable: false,
    is_stockable: false,
});
const baseUomSaving = ref(false);

const poDrawerOpen = ref(false);
const poSubmitting = ref(false);
const poError = ref("");
const poForm = reactive({
    supplier_id: "",
    item_purchase_option_id: "",
    pack_count: "1",
    storeUrl: "",
});

const recipeDrawerOpen = ref(false);
const recipeSubmitting = ref(false);
const recipeError = ref("");
const recipeErrors = ref({});
const recipeForm = reactive({
    item_id: "",
    recipe_type: "manufacturing",
    name: "",
    output_quantity: "1.000000",
    is_active: true,
});

const makeOrderDrawerOpen = ref(false);
const makeOrderSubmitting = ref(false);
const makeOrderError = ref("");
const makeOrderErrors = ref({});
const makeOrderForm = reactive({
    recipe_id: "",
    runs: "",
    due_date: "",
});

const inventoryCountDrawerOpen = ref(false);
const inventoryCountSubmitting = ref(false);
const inventoryCountError = ref("");
const inventoryCountErrors = ref({});
const inventoryCountForm = reactive({
    name: "",
    counted_at: "",
    notes: "",
    assigned_to_user_id: "",
    counted_quantity: "",
});
const taskDrawerOpen = ref(false);
const taskSubmitting = ref(false);
const taskError = ref("");
const taskErrors = ref({});
const taskForm = reactive({
    title: "",
    assigned_to_user_id: "",
    due_date: "",
    description: "",
});
const createdTasks = ref([]);
const taskCompletingIds = ref([]);

const material = computed(() => payload.value?.item ?? {});
const pageTitle = computed(() => material.value?.name || props.title);
const breadcrumbItems = computed(() => [
    { label: "Materials", url: props.indexUrl, current: false },
    { label: pageTitle.value, url: null, current: true },
]);
const inventoryCards = computed(() => Array.isArray(payload.value?.inventoryStats?.cards) ? payload.value.inventoryStats.cards : []);
const typeToggles = [
    { field: "is_sellable", label: "Sellable", icon: "shopping-cart" },
    { field: "is_purchasable", label: "Purchasable", icon: "credit-card" },
    { field: "is_manufacturable", label: "Makeable", icon: "cog" },
    { field: "is_stockable", label: "Stockable", icon: "rectangle-group" },
];
const visibleSections = computed(() => [
    ["supplierPackages", payload.value?.sections?.supplierPackages],
    ["recipes", payload.value?.sections?.recipes],
    ["inventoryCounts", payload.value?.sections?.inventoryCounts],
    ["stockMoves", payload.value?.sections?.stockMoves],
    ["purchaseOrders", payload.value?.sections?.purchaseOrders],
    ["makeOrders", payload.value?.sections?.makeOrders],
].filter((entry) => entry[1]));
const purchaseOrderPackages = computed(() => payload.value?.purchaseOrderCreate?.packages ?? []);
const taskUsers = computed(() => payload.value?.taskCreate?.users ?? []);
const canChangeBaseUom = computed(() => Boolean(material.value?.can_manage));

function asString(value, fallback = "") {
    return value === null || value === undefined ? fallback : String(value);
}

function csrfToken() {
    return material.value?.csrf_token || payload.value?.csrfToken || "";
}

function headers() {
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
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
    }, 1800);
}

function dismissToast() {
    toast.value = null;

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
        toastTimer.value = null;
    }
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        ...options,
        headers: {
            ...headers(),
            ...(options.headers ?? {}),
        },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const requestError = new Error(data.message ?? "The request could not be completed.");
        requestError.payload = data;
        requestError.status = response.status;
        throw requestError;
    }

    return data;
}

async function loadPayload() {
    loading.value = true;
    pageError.value = "";

    try {
        const data = await jsonRequest(props.payloadUrl, {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        });
        payload.value = data.data ?? {};
        nameDraft.value = asString(payload.value?.item?.name);
    } catch {
        pageError.value = "Unable to load this material.";
    } finally {
        loading.value = false;
    }
}

function materialUpdatePayload(overrides = {}) {
    return {
        name: asString(material.value?.name),
        base_uom_id: material.value?.base_uom_id,
        is_sellable: Boolean(material.value?.is_sellable),
        is_purchasable: Boolean(material.value?.is_purchasable),
        is_manufacturable: Boolean(material.value?.is_manufacturable),
        is_stockable: Boolean(material.value?.is_stockable),
        ...overrides,
    };
}

function syncMaterialDetail(nextPayload) {
    payload.value = nextPayload;
    nameDraft.value = asString(nextPayload?.item?.name);
}

async function patchMaterial(overrides) {
    const data = await jsonRequest(material.value.update_url, {
        method: "PATCH",
        body: JSON.stringify(materialUpdatePayload(overrides)),
    });

    if (data.data?.material_detail) {
        syncMaterialDetail(data.data.material_detail);
    } else {
        await loadPayload();
    }

    return data;
}

function openNameEditor() {
    nameDraft.value = asString(material.value?.name);
    nameError.value = "";
    nameEditorOpen.value = true;
}

function materialTypeTitle(typeToggle) {
    if (material.value?.can_toggle_types) {
        return typeToggle.label;
    }

    return `${typeToggle.label} requires material management permission.`;
}

async function saveName() {
    const nextName = asString(nameDraft.value).trim();

    if (nextName === "") {
        nameError.value = "Material name is required.";
        return;
    }

    nameSaving.value = true;
    nameError.value = "";

    try {
        await patchMaterial({ name: nextName });
        nameEditorOpen.value = false;
        showToast("Material name updated.");
    } catch (error) {
        nameError.value = error.payload?.errors?.name?.[0] || error.payload?.message || "Unable to update material name.";
    } finally {
        nameSaving.value = false;
    }
}

async function toggleMaterialType(field) {
    if (!material.value?.can_toggle_types || typeSaving[field]) {
        return;
    }

    const previous = Boolean(material.value?.[field]);
    typeSaving[field] = true;
    payload.value.item[field] = !previous;

    try {
        await patchMaterial({ [field]: !previous });
        const label = typeToggles.find((toggle) => toggle.field === field)?.label || "Type";
        showToast(`${label} ${material.value?.[field] ? "enabled" : "disabled"}.`);
    } catch {
        payload.value.item[field] = previous;
        showToast("Unable to update material type.", "error");
    } finally {
        typeSaving[field] = false;
    }
}

function uomOptionLabel(option) {
    const symbol = asString(option?.symbol);

    return symbol === "" ? asString(option?.name, "Unnamed UoM") : `${option.name} (${symbol})`;
}

async function selectBaseUom(option) {
    if (!canChangeBaseUom.value || baseUomSaving.value || !option?.id || String(option.id) === String(material.value?.base_uom_id)) {
        return;
    }

    if (!window.confirm(`Change base unit of measure to ${uomOptionLabel(option)}?`)) {
        return;
    }

    baseUomSaving.value = true;

    try {
        await patchMaterial({ base_uom_id: option.id });
        showToast("Base unit of measure updated.");
    } catch (error) {
        showToast(error.payload?.errors?.base_uom_id?.[0] || error.payload?.message || "Unable to change base unit of measure.", "error");
    } finally {
        baseUomSaving.value = false;
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
            supplier_id: asString(record.supplier_id),
            pack_quantity: asString(record.pack_quantity),
            pack_uom_id: asString(record.pack_uom_id),
            supplier_sku: asString(record.supplier_sku),
            price_amount: asString(record.price_amount),
        },
        display: {
            primaryText: record.supplier_name || "Unknown supplier",
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

    if (record.status === "RECEIVED" || record.status === "COMPLETED") {
        return { text: record.status, tone: "success" };
    }

    return { text: record.status || "-", tone: record.status === "CREATED" ? "default" : "muted" };
}

function normalizePurchaseOrder(record) {
    const status = purchaseOrderStatusDisplay(record);

    return {
        ...record,
        display: {
            ...record.display,
            poNumberText: record.po_number ? `PO #${record.po_number}` : "Draft PO",
            orderDateText: record.order_date || record.display?.orderDateText || "",
            supplierText: record.supplier_name || record.display?.supplierText || "",
            statusText: status.text,
            statusTone: status.tone,
            showUrl: record.show_url || record.display?.showUrl || "",
        },
    };
}

function normalizeDisplayRecord(record) {
    return {
        ...record,
        display: {
            ...record.display,
            showUrl: record.show_url || record.display?.showUrl || "",
        },
    };
}

function buildSupplierPackagePayload(form) {
    return {
        supplier_id: asString(form.supplier_id),
        pack_quantity: asString(form.pack_quantity),
        pack_uom_id: asString(form.pack_uom_id),
        supplier_sku: asString(form.supplier_sku),
        price_amount: asString(form.price_amount),
    };
}

function resetTaskForm() {
    Object.assign(taskForm, {
        title: "",
        assigned_to_user_id: "",
        due_date: "",
        description: "",
    });
}

function normalizeRecordForSection(key) {
    if (key === "supplierPackages") {
        return normalizeSupplierPackage;
    }

    if (key === "purchaseOrders") {
        return normalizePurchaseOrder;
    }

    return normalizeDisplayRecord;
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
        const response = await jsonRequest(poForm.storeUrl, {
            method: "POST",
            body: JSON.stringify({
                supplier_id: poForm.supplier_id,
                item_purchase_option_id: poForm.item_purchase_option_id,
                pack_count: poForm.pack_count,
            }),
        });

        if (response.data?.show_url) {
            window.location.assign(response.data.show_url);
        }
    } catch (error) {
        poError.value = error.payload?.message ?? "Unable to create purchase order.";
    } finally {
        poSubmitting.value = false;
    }
}

function openRecipeDrawer(prefill = {}) {
    const prefillItemId = prefill.itemId || payload.value?.recipeCreate?.prefillItemId || material.value?.id || "";
    const item = (payload.value?.recipeCreate?.manufacturableItems ?? []).find((option) => String(option.id) === String(prefillItemId));

    Object.assign(recipeForm, {
        item_id: asString(prefillItemId),
        recipe_type: item?.allowed_recipe_types?.[0] || "manufacturing",
        name: item?.name || material.value?.name || "",
        output_quantity: "1.000000",
        is_active: true,
    });
    recipeErrors.value = {};
    recipeError.value = "";
    recipeDrawerOpen.value = true;
}

async function submitRecipe() {
    recipeSubmitting.value = true;
    recipeError.value = "";
    recipeErrors.value = {};

    try {
        await jsonRequest(payload.value.recipeCreate.storeUrl, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": payload.value.recipeCreate.csrfToken,
            },
            body: JSON.stringify(recipeForm),
        });
        recipeDrawerOpen.value = false;
        await loadPayload();
        showToast("Recipe created.");
    } catch (error) {
        recipeErrors.value = error.payload?.errors ?? {};
        recipeError.value = error.payload?.message ?? "Unable to create recipe.";
    } finally {
        recipeSubmitting.value = false;
    }
}

function openMakeOrderDrawer(record = null) {
    Object.assign(makeOrderForm, {
        recipe_id: asString(record?.recipe_id ?? record?.id ?? ""),
        runs: "",
        due_date: "",
    });
    makeOrderErrors.value = {};
    makeOrderError.value = "";
    makeOrderDrawerOpen.value = true;
}

async function submitMakeOrder() {
    makeOrderSubmitting.value = true;
    makeOrderError.value = "";
    makeOrderErrors.value = {};

    try {
        const response = await jsonRequest(payload.value.makeOrderCreate.storeUrl, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": payload.value.makeOrderCreate.csrfToken,
            },
            body: JSON.stringify(makeOrderForm),
        });

        if (response.data?.show_url) {
            window.location.assign(response.data.show_url);
            return;
        }

        makeOrderDrawerOpen.value = false;
        await loadPayload();
        showToast("Make order created.");
    } catch (error) {
        makeOrderErrors.value = error.payload?.errors ?? {};
        makeOrderError.value = error.payload?.message ?? "Unable to create make order.";
    } finally {
        makeOrderSubmitting.value = false;
    }
}

function openInventoryCountDrawer() {
    Object.assign(inventoryCountForm, {
        name: `Count ${material.value?.name || "Material"}`,
        counted_at: new Date().toISOString().slice(0, 10),
        notes: "",
        assigned_to_user_id: "",
        counted_quantity: "",
    });
    inventoryCountErrors.value = {};
    inventoryCountError.value = "";
    inventoryCountDrawerOpen.value = true;
}

async function submitInventoryCount() {
    inventoryCountSubmitting.value = true;
    inventoryCountError.value = "";
    inventoryCountErrors.value = {};

    try {
        const response = await jsonRequest(payload.value.inventoryCountCreate.storeUrl, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": payload.value.inventoryCountCreate.csrfToken,
            },
            body: JSON.stringify(inventoryCountForm),
        });

        inventoryCountDrawerOpen.value = false;
        await loadPayload();
        showToast(response.count?.name ? "Inventory count created." : "Count created.");
    } catch (error) {
        inventoryCountErrors.value = error.payload?.errors ?? {};
        inventoryCountError.value = error.payload?.message ?? "Unable to create inventory count.";
    } finally {
        inventoryCountSubmitting.value = false;
    }
}

function openTaskDrawer() {
    resetTaskForm();
    taskErrors.value = {};
    taskError.value = "";
    taskDrawerOpen.value = true;
}

function taskStatusClasses(task) {
    return task?.is_completed
        ? "bg-emerald-100 text-emerald-700"
        : "bg-amber-100 text-amber-700";
}

async function submitTask() {
    if (taskSubmitting.value) {
        return;
    }

    taskSubmitting.value = true;
    taskErrors.value = {};
    taskError.value = "";

    try {
        const response = await jsonRequest(payload.value?.taskCreate?.storeUrl ?? "/tasks", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": payload.value?.taskCreate?.csrfToken ?? csrfToken(),
            },
            body: JSON.stringify({
                title: taskForm.title,
                assigned_to_user_id: taskForm.assigned_to_user_id === "" ? null : Number(taskForm.assigned_to_user_id),
                due_date: taskForm.due_date || null,
                description: taskForm.description,
                workflow_domain_id: null,
                domain_record_id: null,
                workflow_stage_id: null,
            }),
        });

        createdTasks.value = [...createdTasks.value, response.data ?? {}].filter((task) => Boolean(task.id));
        taskDrawerOpen.value = false;
        showToast("Task created.");
    } catch (error) {
        taskErrors.value = error.payload?.errors ?? {};
        taskError.value = error.payload?.message ?? "Unable to create task.";
    } finally {
        taskSubmitting.value = false;
    }
}

async function completeCreatedTask(task) {
    if (!task?.id || !task?.complete_url || taskCompletingIds.value.includes(task.id)) {
        return;
    }

    taskCompletingIds.value = [...taskCompletingIds.value, task.id];

    try {
        const response = await jsonRequest(task.complete_url, {
            method: "PATCH",
            headers: {
                "X-CSRF-TOKEN": payload.value?.taskCreate?.csrfToken ?? csrfToken(),
            },
        });

        createdTasks.value = createdTasks.value.map((entry) => (
            entry.id === response.data?.id ? response.data : entry
        ));
        showToast("Task completed.");
    } catch {
        showToast("Unable to complete task.", "error");
    } finally {
        taskCompletingIds.value = taskCompletingIds.value.filter((taskId) => taskId !== task.id);
    }
}

async function handleSectionAction({ action, record, refresh }) {
    if (action.handlerKey === "purchase") {
        openPurchaseOrderDrawer(record);
        return;
    }

    if (action.handlerKey === "openRecipeCreate") {
        openRecipeDrawer(action.prefill ?? {});
        return;
    }

    if (action.handlerKey === "openInventoryCountCreate") {
        openInventoryCountDrawer();
        return;
    }

    if (action.handlerKey === "createMakeOrder") {
        openMakeOrderDrawer(record);
        return;
    }

    if (action.type === "archive" || action.type === "remove") {
        if (action.confirmMessage && !window.confirm(action.confirmMessage)) {
            return;
        }

        const endpoint = (record.purchase_url && action.handlerKey === "purchase")
            ? record.purchase_url
            : String(record.remove_url || record.archive_url || action.endpoint || "").replace("{id}", record.id);

        if (endpoint !== "") {
            await jsonRequest(endpoint, { method: action.method ?? "DELETE" });
            await refresh?.();
            showToast("Record removed.");
        }
    }
}

onMounted(() => {
    if (!payload.value) {
        void loadPayload();
        return;
    }

    nameDraft.value = asString(payload.value?.item?.name);
});
</script>

<template>
    <Head :title="pageTitle" />

    <AuthShell :shell="shell" :title="pageTitle" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <UiToast
                :visible="Boolean(toast)"
                :type="toast?.tone || 'success'"
                :message="toast?.message || ''"
                @dismiss="dismissToast"
            />

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="pageTitle"
                title-class="font-semibold text-base leading-tight text-gray-900 sm:text-2xl"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #header>
                    <div class="w-full bg-white px-4 py-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-2">
                                    <h1 class="truncate text-2xl font-semibold leading-tight text-slate-950">{{ pageTitle }}</h1>
                                    <button
                                        v-if="material.can_manage"
                                        type="button"
                                        class="inline-flex h-7 w-7 cursor-pointer items-center justify-center text-gray-500 transition hover:text-blue-600"
                                        aria-label="Edit material name"
                                        @click="openNameEditor"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="mt-1 text-xs font-medium text-slate-500">{{ material.base_uom_name || material.base_uom_symbol || "-" }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-for="toggle in typeToggles"
                                    :key="toggle.field"
                                    type="button"
                                    class="inline-flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full border bg-white transition disabled:cursor-not-allowed disabled:opacity-70 sm:h-8 sm:w-8 sm:border-2"
                                    :class="material[toggle.field] ? 'border-blue-600 text-blue-600 hover:border-blue-500 hover:text-blue-500' : 'border-gray-300 text-gray-300 hover:border-blue-600 hover:text-blue-600'"
                                    :aria-pressed="material[toggle.field] ? 'true' : 'false'"
                                    :aria-label="toggle.label"
                                    :aria-disabled="!material.can_toggle_types ? 'true' : 'false'"
                                    :title="materialTypeTitle(toggle)"
                                    :disabled="!material.can_toggle_types || typeSaving[toggle.field]"
                                    @click="toggleMaterialType(toggle.field)"
                                >
                                    <span class="sr-only">{{ toggle.label }}</span>

                                    <svg v-if="toggle.icon === 'shopping-cart'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                    </svg>

                                    <svg v-else-if="toggle.icon === 'credit-card'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                    </svg>

                                    <svg v-else-if="toggle.icon === 'cog'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
                                    </svg>

                                    <svg v-else-if="toggle.icon === 'rectangle-group'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                    </svg>
                                </button>

                                <BaseDropdown
                                    v-if="canChangeBaseUom"
                                    aria-label="Change base unit of measure"
                                    button-class="inline-flex h-8 cursor-pointer items-center justify-center rounded-md bg-white px-3 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    menu-class="w-56 rounded-md border border-gray-200 bg-white py-1 shadow-lg"
                                >
                                    <template #trigger>
                                        {{ material.base_uom_name || material.base_uom_symbol || "UoM" }}
                                    </template>
                                    <template #default="{ close }">
                                        <button
                                            v-for="option in material.uom_options ?? []"
                                            :key="option.id"
                                            type="button"
                                            class="block w-full cursor-pointer px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="baseUomSaving || String(option.id) === String(material.base_uom_id)"
                                            @click="close(); selectBaseUom(option)"
                                        >
                                            {{ uomOptionLabel(option) }}
                                        </button>
                                    </template>
                                </BaseDropdown>
                            </div>
                        </div>
                    </div>
                </template>
            </ResourceDetailHeaderBreadcrumb>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading" class="py-12">
                    <div class="mx-auto max-w-5xl px-4 text-sm text-gray-500">Loading material...</div>
                </div>

                <div v-else-if="pageError" class="py-12">
                    <div class="mx-auto max-w-5xl px-4 text-sm text-red-600">{{ pageError }}</div>
                </div>

                <div v-else class="py-12">
                    <div class="mx-auto max-w-5xl space-y-0 px-1 sm:space-y-6 sm:px-6 lg:px-8">
                        <MaterialQuantityBar :cards="inventoryCards" />

                        <ResourceDetailSection
                            v-for="[key, section] in visibleSections"
                            :key="key"
                            :section="section"
                            :csrf-token="section.csrfToken || csrfToken()"
                            :normalize-record="normalizeRecordForSection(key)"
                            :build-create-payload="key === 'supplierPackages' ? buildSupplierPackagePayload : undefined"
                            :build-update-payload="key === 'supplierPackages' ? buildSupplierPackagePayload : undefined"
                            @custom-action="handleSectionAction"
                            @created="showToast(`${section.title} created.`)"
                            @updated="showToast(`${section.title} updated.`)"
                            @removed="showToast(`${section.title} removed.`)"
                        />

                        <section class="-mx-1 border-y border-gray-100 bg-white shadow-sm sm:mx-0 sm:rounded-lg sm:border">
                            <div class="p-4 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Tasks</h3>
                                        <p class="mt-1 text-sm text-gray-600">Create assigned tasks without linking them to this material.</p>
                                    </div>
                                    <button type="button" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-10 sm:w-10" aria-label="Create task" @click="openTaskDrawer">
                                        <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    </button>
                                </div>

                                <div v-if="createdTasks.length === 0" class="mt-6 rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                                    Manual tasks created here are assigned to the selected user only.
                                </div>

                                <div v-else class="mt-6 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                                    <div v-for="task in createdTasks" :key="task.id" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                                            <p v-if="task.description" class="mt-1 text-sm text-gray-500">{{ task.description }}</p>
                                            <p v-if="task.assigned_to_user_name" class="mt-1 text-xs text-gray-500">Assigned To: {{ task.assigned_to_user_name }}</p>
                                            <p v-if="task.due_date" class="mt-1 text-xs text-gray-500">Due: {{ task.due_date }}</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="taskStatusClasses(task)">{{ task.status || "open" }}</span>
                                            <button v-if="task.can_complete && !task.is_completed" type="button" class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="taskCompletingIds.includes(task.id)" @click="completeCreatedTask(task)">
                                                Complete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="nameEditorOpen"
            class="fixed inset-0 z-[1300] flex items-start justify-center bg-black/30 px-4 pt-24"
            @click.self="nameEditorOpen = false"
        >
            <form class="w-full max-w-sm bg-white p-4 shadow-xl" @submit.prevent="saveName">
                <label class="block text-xs font-semibold text-gray-700">Material name</label>
                <input v-model="nameDraft" class="mt-1 block w-full border-gray-300 text-sm" type="text">
                <p v-if="nameError" class="mt-2 text-xs text-red-600">{{ nameError }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="cursor-pointer border border-gray-300 px-3 py-1.5 text-xs font-semibold" @click="nameEditorOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60" :disabled="nameSaving">Save</button>
                </div>
            </form>
        </div>

        <BaseDrawer :open="poDrawerOpen" labelled-by="material-purchase-order-title" panel-class="w-screen max-w-md" @close="poDrawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitPurchaseOrder">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="material-purchase-order-title" class="text-sm font-semibold">Create Purchase Order</h2>
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
                    <button type="button" class="cursor-pointer border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="poDrawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="poSubmitting">Save</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="recipeDrawerOpen" labelled-by="material-recipe-title" panel-class="w-screen max-w-md" @close="recipeDrawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitRecipe">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="material-recipe-title" class="text-sm font-semibold">Create Recipe</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="recipeError" class="text-xs text-red-600">{{ recipeError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Output Item
                        <select v-model="recipeForm.item_id" class="mt-1 block w-full border-slate-300 text-sm">
                            <option v-for="item in payload?.recipeCreate?.manufacturableItems ?? []" :key="item.id" :value="String(item.id)">{{ item.display_text || item.name }}</option>
                        </select>
                        <p v-if="recipeErrors.item_id?.[0]" class="mt-1 text-xs text-red-600">{{ recipeErrors.item_id[0] }}</p>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Type
                        <select v-model="recipeForm.recipe_type" class="mt-1 block w-full border-slate-300 text-sm">
                            <option value="manufacturing">Manufacturing</option>
                            <option value="fulfillment">Fulfillment</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Name
                        <input v-model="recipeForm.name" class="mt-1 block w-full border-slate-300 text-sm" type="text">
                        <p v-if="recipeErrors.name?.[0]" class="mt-1 text-xs text-red-600">{{ recipeErrors.name[0] }}</p>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Output Qty
                        <input v-model="recipeForm.output_quantity" class="mt-1 block w-full border-slate-300 text-sm" type="text">
                        <p v-if="recipeErrors.output_quantity?.[0]" class="mt-1 text-xs text-red-600">{{ recipeErrors.output_quantity[0] }}</p>
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-700">
                        <input v-model="recipeForm.is_active" type="checkbox"> Active
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="recipeDrawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="recipeSubmitting">Create</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="makeOrderDrawerOpen" labelled-by="material-make-order-title" panel-class="w-screen max-w-md" @close="makeOrderDrawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitMakeOrder">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="material-make-order-title" class="text-sm font-semibold">Create Make Order</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="makeOrderError" class="text-xs text-red-600">{{ makeOrderError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Recipe
                        <select v-model="makeOrderForm.recipe_id" class="mt-1 block w-full border-slate-300 text-sm">
                            <option value="">Select</option>
                            <option v-for="recipe in payload?.makeOrderCreate?.recipes ?? []" :key="recipe.id" :value="String(recipe.id)">{{ recipe.name }}</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Runs
                        <input v-model="makeOrderForm.runs" class="mt-1 block w-full border-slate-300 text-sm" type="text">
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Due date
                        <input v-model="makeOrderForm.due_date" class="mt-1 block w-full border-slate-300 text-sm" type="date">
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="makeOrderDrawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="makeOrderSubmitting">Create</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="inventoryCountDrawerOpen" labelled-by="material-inventory-count-title" panel-class="w-screen max-w-md" @close="inventoryCountDrawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitInventoryCount">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="material-inventory-count-title" class="text-sm font-semibold">Create Inventory Count</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="inventoryCountError" class="text-xs text-red-600">{{ inventoryCountError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Name
                        <input v-model="inventoryCountForm.name" class="mt-1 block w-full border-slate-300 text-sm" type="text">
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Counted at
                        <input v-model="inventoryCountForm.counted_at" class="mt-1 block w-full border-slate-300 text-sm" type="date">
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Assigned to
                        <select v-model="inventoryCountForm.assigned_to_user_id" class="mt-1 block w-full border-slate-300 text-sm">
                            <option value="">Select user</option>
                            <option v-for="user in payload?.inventoryCountCreate?.users ?? []" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Counted Qty
                        <input v-model="inventoryCountForm.counted_quantity" class="mt-1 block w-full border-slate-300 text-sm" type="text">
                    </label>
                    <label class="block text-xs font-medium text-slate-700">Notes
                        <textarea v-model="inventoryCountForm.notes" class="mt-1 block w-full border-slate-300 text-sm" rows="3" />
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="inventoryCountDrawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="inventoryCountSubmitting">Create</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="taskDrawerOpen" labelled-by="material-task-title" panel-class="w-screen max-w-sm" @close="taskDrawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitTask">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="material-task-title" class="text-sm font-semibold">Create Task</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="taskError" class="text-xs text-red-600">{{ taskError }}</p>
                    <label class="block text-xs font-medium text-slate-700">
                        Title
                        <input v-model="taskForm.title" class="mt-1 block w-full border-slate-300 text-sm" type="text" required maxlength="255">
                    </label>
                    <p v-if="taskErrors.title?.[0]" class="text-xs text-red-600">{{ taskErrors.title[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">
                        Assigned To
                        <select v-model="taskForm.assigned_to_user_id" class="mt-1 block w-full border-slate-300 text-sm" required>
                            <option value="">Select user</option>
                            <option v-for="user in taskUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                        </select>
                    </label>
                    <p v-if="taskErrors.assigned_to_user_id?.[0]" class="text-xs text-red-600">{{ taskErrors.assigned_to_user_id[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">
                        Due Date
                        <input v-model="taskForm.due_date" class="mt-1 block w-full border-slate-300 text-sm" type="date">
                    </label>
                    <label class="block text-xs font-medium text-slate-700">
                        Description
                        <textarea v-model="taskForm.description" class="mt-1 block w-full border-slate-300 text-sm" rows="4" />
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="taskDrawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="taskSubmitting">Create</button>
                </div>
            </form>
        </BaseDrawer>
    </AuthShell>
</template>
