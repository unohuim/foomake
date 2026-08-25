<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

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

const categories = ref(normalizeCategories(props.payload.categories ?? []));
const search = ref("");
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formMode = ref("create");
const editingUomId = ref(null);
const formSubmitting = ref(false);
const formError = ref("");
const formErrors = ref({});
const labels = computed(() => props.crudConfig.labels ?? {});
const categoryOptions = computed(() => categories.value.map((category) => ({
    id: String(category.id),
    name: category.name,
})));
const form = reactive(emptyForm());

const deleteUom = ref(null);
const deleteSubmitting = ref(false);
const deleteError = ref("");

const uoms = computed(() => categories.value.flatMap((category) => (
    (category.uoms ?? []).map((uom) => ({
        ...uom,
        category_name: category.name,
    }))
)));
const filteredUoms = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === "") {
        return uoms.value;
    }

    return uoms.value.filter((uom) => [
        uom.name,
        uom.symbol,
        uom.category_name,
        String(uom.display_precision ?? ""),
    ].some((value) => String(value ?? "").toLowerCase().includes(term)));
});

function normalizeCategories(sourceCategories) {
    return sourceCategories.map((category) => ({
        id: category.id,
        name: category.name,
        uoms: [...(category.uoms ?? [])]
            .map(normalizeUom)
            .sort((left, right) => String(left.name ?? "").localeCompare(String(right.name ?? ""))),
    }));
}

function normalizeUom(uom) {
    return {
        ...uom,
        id: uom.id,
        uom_category_id: uom.uom_category_id,
        name: uom.name ?? "",
        symbol: uom.symbol ?? "",
        display_precision: Number(uom.display_precision ?? 1),
    };
}

function emptyForm() {
    return {
        name: "",
        symbol: "",
        uom_category_id: categoryOptions.value[0]?.id ?? "",
        display_precision: 1,
    };
}

function resetForm(values = emptyForm()) {
    Object.assign(form, emptyForm(), values);
    formErrors.value = {};
    formError.value = "";
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

function removeUom(id) {
    const normalizedId = String(id);

    categories.value = categories.value.map((category) => ({
        ...category,
        uoms: (category.uoms ?? []).filter((uom) => String(uom.id) !== normalizedId),
    }));
}

function upsertUom(uomPayload) {
    const uom = normalizeUom(uomPayload);
    const categoryId = String(uom.uom_category_id);

    removeUom(uom.id);

    categories.value = categories.value.map((category) => {
        if (String(category.id) !== categoryId) {
            return category;
        }

        return {
            ...category,
            uoms: [...(category.uoms ?? []), uom].sort((left, right) => (
                String(left.name ?? "").localeCompare(String(right.name ?? ""))
            )),
        };
    });
}

function openCreateDrawer() {
    formMode.value = "create";
    editingUomId.value = null;
    resetForm();
    formDrawerOpen.value = true;
}

function openEditDrawer(uom) {
    formMode.value = "edit";
    editingUomId.value = uom.id;
    resetForm({
        name: uom.name ?? "",
        symbol: uom.symbol ?? "",
        uom_category_id: String(uom.uom_category_id ?? ""),
        display_precision: Number(uom.display_precision ?? 1),
    });
    formDrawerOpen.value = true;
}

function closeFormDrawer() {
    if (formSubmitting.value) {
        return;
    }

    formDrawerOpen.value = false;
}

function formPayload() {
    return {
        name: String(form.name ?? "").trim(),
        symbol: String(form.symbol ?? "").trim(),
        uom_category_id: form.uom_category_id,
        display_precision: Number(form.display_precision ?? 1),
    };
}

async function submitForm() {
    formSubmitting.value = true;
    formError.value = "";
    formErrors.value = {};
    listError.value = "";

    try {
        const isEdit = formMode.value === "edit";
        const url = isEdit
            ? props.payload.updateUrlTemplate.replace("__ID__", editingUomId.value)
            : props.payload.storeUrl;
        const uom = await jsonRequest(url, {
            method: isEdit ? "PATCH" : "POST",
            body: JSON.stringify(formPayload()),
        });

        upsertUom(uom);
        formDrawerOpen.value = false;
        showToast(isEdit ? "Unit updated." : "Unit created.");
    } catch (error) {
        formErrors.value = error.payload?.errors ?? {};
        formError.value = error.payload?.message ?? "Unable to save unit.";
    } finally {
        formSubmitting.value = false;
    }
}

function handleAction({ action, record }) {
    if (action.id === "edit") {
        openEditDrawer(record);
        return;
    }

    if (action.id === "delete") {
        deleteUom.value = record;
        deleteError.value = "";
    }
}

function closeDeleteModal() {
    if (deleteSubmitting.value) {
        return;
    }

    deleteUom.value = null;
    deleteError.value = "";
}

async function confirmDelete() {
    if (!deleteUom.value) {
        return;
    }

    deleteSubmitting.value = true;
    deleteError.value = "";
    listError.value = "";

    try {
        await jsonRequest(props.payload.deleteUrlTemplate.replace("__ID__", deleteUom.value.id), {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
            },
        });

        removeUom(deleteUom.value.id);
        showToast("Unit deleted.");
        deleteUom.value = null;
    } catch (error) {
        deleteError.value = error.payload?.message ?? "Unable to delete unit.";
    } finally {
        deleteSubmitting.value = false;
    }
}

function uomTitleBadges(uom) {
    return [
        {
            label: uom.symbol || "-",
            tone: "gray",
        },
    ];
}

function uomRows(uom) {
    return [
        {
            left: [{ label: "Category", value: uom.category_name || "-" }],
            right: [],
        },
        {
            left: [{ label: "Precision", value: String(uom.display_precision ?? 1) }],
            right: [],
        },
    ];
}
</script>

<template>
    <Head title="Units of Measure" />

    <AuthShell :shell="shell" title="Units of Measure">
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
                    :records="filteredUoms"
                    :error="listError"
                    bounded-height-class="h-full"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :title="(uom) => uom.name || '-'"
                            :title-badges="uomTitleBadges"
                            :subtitle="(uom) => uom.category_name || '-'"
                            :mobile-subtitle="(uom) => `${uom.symbol || '-'} · ${uom.category_name || '-'}`"
                            :detail-rows="uomRows"
                            :href="() => null"
                            :actions="crudConfig.actions ?? []"
                            @action="handleAction"
                        />
                    </template>

                    <template #empty>
                        <div class="p-4 sm:p-6">
                            <div class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                                {{ categoryOptions.length === 0 ? "Create a UoM category before adding units." : labels.emptyState }}
                            </div>
                        </div>
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            :title="formMode === 'edit' ? 'Edit Unit of Measure' : labels.createTitle"
            description="Provide a name, symbol, category, and display precision."
            :submit-label="formMode === 'edit' ? 'Save Unit' : 'Create Unit'"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-md"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <template #header-icon>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L12 6.75l5.571 3m0 0L21.75 12l-4.179 2.25m0-4.5v4.5m0 0L12 17.25l-5.571-3m11.142 0v4.5L12 21.75l-5.571-3v-4.5" />
                </svg>
            </template>

            <div class="space-y-3">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Name</span>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="off"
                    >
                    <span v-if="formErrors.name" class="mt-1 block text-xs text-red-600">{{ formErrors.name[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Symbol</span>
                    <input
                        v-model="form.symbol"
                        type="text"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="off"
                    >
                    <span v-if="formErrors.symbol" class="mt-1 block text-xs text-red-600">{{ formErrors.symbol[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Category</span>
                    <select
                        v-model="form.uom_category_id"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                    >
                        <option value="">Select a category</option>
                        <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <span v-if="formErrors.uom_category_id" class="mt-1 block text-xs text-red-600">{{ formErrors.uom_category_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Display Precision</span>
                    <input
                        v-model="form.display_precision"
                        type="number"
                        min="0"
                        max="6"
                        step="1"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                    >
                    <span v-if="formErrors.display_precision" class="mt-1 block text-xs text-red-600">{{ formErrors.display_precision[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>

        <div
            v-if="deleteUom"
            class="fixed inset-0 z-[130] flex items-center justify-center bg-black/40 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="uom-delete-title"
            @click.self="closeDeleteModal"
        >
            <div class="w-full max-w-sm bg-white p-5 shadow-xl">
                <h2 id="uom-delete-title" class="text-base font-semibold text-gray-900">Delete unit?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleteUom.name }} will be removed. This action cannot be undone.
                </p>

                <p v-if="deleteError" class="mt-3 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ deleteError }}
                </p>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="cursor-pointer inline-flex h-9 items-center justify-center border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer inline-flex h-9 items-center justify-center bg-red-600 px-3 text-xs font-semibold text-white transition hover:bg-red-700"
                        :disabled="deleteSubmitting"
                        :class="deleteSubmitting ? 'cursor-not-allowed opacity-60' : ''"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </AuthShell>
</template>
