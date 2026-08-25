<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

import ResourceCreateDrawer from "../../../components/ResourceCreateDrawer.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    payload: {
        type: Object,
        required: true,
    },
});

const globalConversions = ref([...(props.payload.globalConversions ?? [])]);
const tenantConversions = ref([...(props.payload.tenantConversions ?? [])]);
const itemSpecificConversions = ref([...(props.payload.itemSpecificConversions ?? [])]);
const items = ref([...(props.payload.items ?? [])]);
const uoms = ref([...(props.payload.uoms ?? [])]);
const uomOptions = ref([...(props.payload.uomOptions ?? [])]);

const toast = ref(null);
const toastTimer = ref(null);
const errorMessage = ref("");
const isSubmitting = ref(false);

const generalFormOpen = ref(false);
const generalIsEditing = ref(false);
const generalErrors = ref({});
const generalForm = reactive(emptyGeneralForm());

const itemFormOpen = ref(false);
const itemIsEditing = ref(false);
const itemErrors = ref({});
const itemForm = reactive(emptyItemForm());

const deleteOpen = ref(false);
const deleteTarget = ref(null);
const deleteType = ref(null);

const generalUomOptions = computed(() => mergeSelectedUomOptions(uomOptions.value, [
    generalForm.from_uom_id,
    generalForm.to_uom_id,
]));
const itemUomOptions = computed(() => mergeSelectedUomOptions(uomOptions.value, [
    itemForm.from_uom_id,
    itemForm.to_uom_id,
]));

function emptyGeneralForm() {
    return {
        id: null,
        from_uom_id: "",
        to_uom_id: "",
        multiplier: "",
    };
}

function emptyItemForm() {
    return {
        id: null,
        item_id: "",
        from_uom_id: "",
        to_uom_id: "",
        conversion_factor: "",
    };
}

function resetErrors() {
    generalErrors.value = {};
    itemErrors.value = {};
    errorMessage.value = "";
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

function findUom(id) {
    return uoms.value.find((uom) => String(uom.id) === String(id)) || null;
}

function findItem(id) {
    return items.value.find((item) => String(item.id) === String(id)) || null;
}

function mergeSelectedUomOptions(options, selectedIds) {
    const merged = [...options];

    selectedIds.forEach((selectedId) => {
        if (!selectedId) {
            return;
        }

        const exists = merged.some((uom) => String(uom.id) === String(selectedId));

        if (exists) {
            return;
        }

        const fallback = findUom(selectedId);

        if (fallback) {
            merged.push(fallback);
        }
    });

    return merged;
}

function hydrateGeneralConversion(row) {
    const fromUom = findUom(row.from_uom_id);
    const toUom = findUom(row.to_uom_id);

    return {
        ...row,
        from_symbol: row.from_symbol || fromUom?.symbol || "",
        to_symbol: row.to_symbol || toUom?.symbol || "",
        read_only: row.read_only ?? false,
        editable: row.editable ?? true,
    };
}

function hydrateItemConversion(row) {
    const item = findItem(row.item_id);
    const fromUom = findUom(row.from_uom_id);
    const toUom = findUom(row.to_uom_id);

    return {
        ...row,
        item_name: row.item_name || item?.name || "",
        from_symbol: row.from_symbol || fromUom?.symbol || "",
        to_symbol: row.to_symbol || toUom?.symbol || "",
    };
}

function upsertTenantConversion(row) {
    const hydrated = hydrateGeneralConversion(row);
    const index = tenantConversions.value.findIndex((conversion) => conversion.id === hydrated.id);

    if (index >= 0) {
        tenantConversions.value.splice(index, 1, hydrated);
    } else {
        tenantConversions.value.push(hydrated);
    }
}

function removeTenantConversion(id) {
    tenantConversions.value = tenantConversions.value.filter((conversion) => conversion.id !== id);
}

function upsertItemConversion(row) {
    const hydrated = hydrateItemConversion(row);
    const index = itemSpecificConversions.value.findIndex((conversion) => conversion.id === hydrated.id);

    if (index >= 0) {
        itemSpecificConversions.value.splice(index, 1, hydrated);
    } else {
        itemSpecificConversions.value.push(hydrated);
    }
}

function removeItemConversion(id) {
    itemSpecificConversions.value = itemSpecificConversions.value.filter((conversion) => conversion.id !== id);
}

function openGeneralCreate() {
    resetErrors();
    generalIsEditing.value = false;
    Object.assign(generalForm, emptyGeneralForm());
    generalFormOpen.value = true;
}

function openGeneralEdit(conversion) {
    resetErrors();
    generalIsEditing.value = true;
    Object.assign(generalForm, {
        id: conversion.id,
        from_uom_id: String(conversion.from_uom_id),
        to_uom_id: String(conversion.to_uom_id),
        multiplier: conversion.multiplier,
    });
    generalFormOpen.value = true;
}

function closeGeneralForm() {
    if (!isSubmitting.value) {
        generalFormOpen.value = false;
    }
}

function openItemCreate() {
    resetErrors();
    itemIsEditing.value = false;
    Object.assign(itemForm, emptyItemForm());
    itemFormOpen.value = true;
}

function openItemEdit(conversion) {
    resetErrors();
    itemIsEditing.value = true;
    Object.assign(itemForm, {
        id: conversion.id,
        item_id: String(conversion.item_id),
        from_uom_id: String(conversion.from_uom_id),
        to_uom_id: String(conversion.to_uom_id),
        conversion_factor: conversion.conversion_factor,
    });
    itemFormOpen.value = true;
}

function closeItemForm() {
    if (!isSubmitting.value) {
        itemFormOpen.value = false;
    }
}

function openDelete(conversion, type) {
    resetErrors();
    deleteTarget.value = conversion;
    deleteType.value = type;
    deleteOpen.value = true;
}

function closeDelete() {
    if (isSubmitting.value) {
        return;
    }

    deleteOpen.value = false;
    deleteTarget.value = null;
    deleteType.value = null;
}

async function submitGeneralForm() {
    resetErrors();
    isSubmitting.value = true;

    try {
        const url = generalIsEditing.value
            ? props.payload.updateUrlTemplate.replace("__ID__", generalForm.id)
            : props.payload.storeUrl;
        const data = await jsonRequest(url, {
            method: generalIsEditing.value ? "PATCH" : "POST",
            body: JSON.stringify({
                from_uom_id: generalForm.from_uom_id,
                to_uom_id: generalForm.to_uom_id,
                multiplier: generalForm.multiplier,
            }),
        });

        upsertTenantConversion(data);
        closeGeneralForm();
        showToast(generalIsEditing.value ? "Conversion updated." : "Conversion created.");
    } catch (error) {
        generalErrors.value = error.payload?.errors ?? {};
        errorMessage.value = error.status === 422 ? "" : (error.payload?.message ?? "Something went wrong. Please try again.");
    } finally {
        isSubmitting.value = false;
    }
}

async function submitItemForm() {
    resetErrors();
    isSubmitting.value = true;

    try {
        const url = itemIsEditing.value
            ? props.payload.itemUpdateUrlTemplate.replace("__ID__", itemForm.id)
            : props.payload.itemStoreUrl;
        const data = await jsonRequest(url, {
            method: itemIsEditing.value ? "PATCH" : "POST",
            body: JSON.stringify({
                item_id: itemForm.item_id,
                from_uom_id: itemForm.from_uom_id,
                to_uom_id: itemForm.to_uom_id,
                conversion_factor: itemForm.conversion_factor,
            }),
        });

        upsertItemConversion(data);
        closeItemForm();
        showToast(itemIsEditing.value ? "Item conversion updated." : "Item conversion created.");
    } catch (error) {
        itemErrors.value = error.payload?.errors ?? {};
        errorMessage.value = error.status === 422 ? "" : (error.payload?.message ?? "Something went wrong. Please try again.");
    } finally {
        isSubmitting.value = false;
    }
}

async function confirmDelete() {
    if (!deleteTarget.value || !deleteType.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = "";

    try {
        const url = deleteType.value === "general"
            ? props.payload.deleteUrlTemplate.replace("__ID__", deleteTarget.value.id)
            : props.payload.itemDeleteUrlTemplate.replace("__ID__", deleteTarget.value.id);

        await jsonRequest(url, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
            },
        });

        if (deleteType.value === "general") {
            removeTenantConversion(deleteTarget.value.id);
        } else {
            removeItemConversion(deleteTarget.value.id);
        }

        closeDelete();
        showToast("Conversion deleted.");
    } catch (error) {
        errorMessage.value = error.payload?.message ?? "Unable to delete the conversion.";
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head title="UoM Conversions" />

    <AuthShell :shell="shell" title="UoM Conversions">
        <div class="flex h-full min-h-0 w-full flex-col overflow-hidden bg-white">
            <div
                v-if="toast"
                class="fixed right-4 top-4 z-50 rounded-md px-4 py-3 text-sm shadow-lg"
                :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-slate-900 text-white'"
                role="status"
            >
                {{ toast.message }}
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="mx-auto w-full max-w-7xl space-y-8 px-4 py-4 sm:px-6 lg:px-8">
                    <div v-if="errorMessage" class="rounded-md bg-red-50 p-4">
                        <p class="text-sm text-red-700">{{ errorMessage }}</p>
                    </div>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">General Conversions</h3>
                                <p class="text-sm text-gray-500">Global conversions are read-only. Tenant conversions are editable.</p>
                            </div>
                            <button type="button" class="cursor-pointer inline-flex h-9 items-center justify-center rounded-md border border-transparent bg-[#6f895d] px-3 text-xs font-semibold text-white shadow-sm transition hover:brightness-105" @click="openGeneralCreate">
                                Create Conversion
                            </button>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">globalConversions</h4>
                            <div class="mt-3 overflow-x-auto border border-gray-100">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">From</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">To</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Multiplier</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">read_only</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-if="globalConversions.length === 0">
                                            <td colspan="5" class="px-4 py-4 text-sm text-gray-500">No global conversions available.</td>
                                        </tr>
                                        <tr v-for="conversion in globalConversions" :key="`global-${conversion.id}`">
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.from_symbol }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.to_symbol }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ conversion.multiplier_display }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">true</td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-400">Read-only</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">tenantConversions</h4>
                            <div class="mt-3 overflow-x-auto border border-gray-100">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">From</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">To</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Multiplier</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">editable</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-if="tenantConversions.length === 0">
                                            <td colspan="5" class="px-4 py-4 text-sm text-gray-500">No tenant conversions yet.</td>
                                        </tr>
                                        <tr v-for="conversion in tenantConversions" :key="`tenant-${conversion.id}`">
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.from_symbol }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.to_symbol }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ conversion.multiplier_display }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">true</td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                <button type="button" class="cursor-pointer text-blue-600 hover:text-blue-500" @click="openGeneralEdit(conversion)">Edit</button>
                                                <button type="button" class="ml-4 cursor-pointer text-red-600 hover:text-red-500" @click="openDelete(conversion, 'general')">Delete</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">itemSpecificConversions</h3>
                                <p class="text-sm text-gray-500">Item-specific conversions allow cross-category mappings and override tenant and global conversions.</p>
                            </div>
                            <button type="button" class="cursor-pointer inline-flex h-9 items-center justify-center rounded-md border border-transparent bg-[#6f895d] px-3 text-xs font-semibold text-white shadow-sm transition hover:brightness-105" @click="openItemCreate">
                                Create Item Conversion
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-gray-100">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Item</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">From</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">To</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Factor</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-if="itemSpecificConversions.length === 0">
                                        <td colspan="5" class="px-4 py-4 text-sm text-gray-500">No item-specific conversions yet.</td>
                                    </tr>
                                    <tr v-for="conversion in itemSpecificConversions" :key="`item-${conversion.id}`">
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.item_name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.from_symbol }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ conversion.to_symbol }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ conversion.conversion_factor_display }}</td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <button type="button" class="cursor-pointer text-blue-600 hover:text-blue-500" @click="openItemEdit(conversion)">Edit</button>
                                            <button type="button" class="ml-4 cursor-pointer text-red-600 hover:text-red-500" @click="openDelete(conversion, 'item')">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="generalFormOpen"
            :title="generalIsEditing ? 'Edit Conversion' : 'Create Conversion'"
            submit-label="Save"
            :submitting="isSubmitting"
            @close="closeGeneralForm"
            @submit="submitGeneralForm"
        >
            <div class="space-y-3">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">From UoM</span>
                    <select v-model="generalForm.from_uom_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option value="">Select a unit</option>
                        <option v-for="uom in generalUomOptions" :key="`general-from-${uom.id}`" :value="String(uom.id)">
                            {{ uom.symbol }} · {{ uom.name }}
                        </option>
                    </select>
                    <span v-if="generalErrors.from_uom_id" class="mt-1 block text-xs text-red-600">{{ generalErrors.from_uom_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">To UoM</span>
                    <select v-model="generalForm.to_uom_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option value="">Select a unit</option>
                        <option v-for="uom in generalUomOptions" :key="`general-to-${uom.id}`" :value="String(uom.id)">
                            {{ uom.symbol }} · {{ uom.name }}
                        </option>
                    </select>
                    <span v-if="generalErrors.to_uom_id" class="mt-1 block text-xs text-red-600">{{ generalErrors.to_uom_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Multiplier</span>
                    <input v-model="generalForm.multiplier" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="generalErrors.multiplier" class="mt-1 block text-xs text-red-600">{{ generalErrors.multiplier[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>

        <ResourceCreateDrawer
            :open="itemFormOpen"
            :title="itemIsEditing ? 'Edit Item Conversion' : 'Create Item Conversion'"
            submit-label="Save"
            :submitting="isSubmitting"
            @close="closeItemForm"
            @submit="submitItemForm"
        >
            <div class="space-y-3">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Item</span>
                    <select v-model="itemForm.item_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option value="">Select an item</option>
                        <option v-for="item in items" :key="`item-${item.id}`" :value="String(item.id)">
                            {{ item.name }}
                        </option>
                    </select>
                    <span v-if="itemErrors.item_id" class="mt-1 block text-xs text-red-600">{{ itemErrors.item_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">From UoM</span>
                    <select v-model="itemForm.from_uom_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option value="">Select a unit</option>
                        <option v-for="uom in itemUomOptions" :key="`item-from-${uom.id}`" :value="String(uom.id)">
                            {{ uom.symbol }} · {{ uom.name }}
                        </option>
                    </select>
                    <span v-if="itemErrors.from_uom_id" class="mt-1 block text-xs text-red-600">{{ itemErrors.from_uom_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">To UoM</span>
                    <select v-model="itemForm.to_uom_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option value="">Select a unit</option>
                        <option v-for="uom in itemUomOptions" :key="`item-to-${uom.id}`" :value="String(uom.id)">
                            {{ uom.symbol }} · {{ uom.name }}
                        </option>
                    </select>
                    <span v-if="itemErrors.to_uom_id" class="mt-1 block text-xs text-red-600">{{ itemErrors.to_uom_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Conversion Factor</span>
                    <input v-model="itemForm.conversion_factor" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="itemErrors.conversion_factor" class="mt-1 block text-xs text-red-600">{{ itemErrors.conversion_factor[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>

        <div
            v-if="deleteOpen"
            class="fixed inset-0 z-[130] flex items-center justify-center bg-black/40 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="uom-conversion-delete-title"
            @click.self="closeDelete"
        >
            <div class="w-full max-w-sm bg-white p-5 shadow-xl">
                <h2 id="uom-conversion-delete-title" class="text-base font-semibold text-gray-900">Delete Conversion</h2>
                <p class="mt-2 text-sm text-gray-600">Are you sure you want to delete this conversion?</p>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" class="cursor-pointer inline-flex h-9 items-center justify-center border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-50" @click="closeDelete">
                        Cancel
                    </button>
                    <button type="button" class="cursor-pointer inline-flex h-9 items-center justify-center bg-red-600 px-3 text-xs font-semibold text-white transition hover:bg-red-700" :disabled="isSubmitting" :class="isSubmitting ? 'cursor-not-allowed opacity-60' : ''" @click="confirmDelete">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </AuthShell>
</template>
