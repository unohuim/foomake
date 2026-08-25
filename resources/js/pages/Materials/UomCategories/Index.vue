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

const categories = ref([...(props.payload.categories ?? [])]);
const search = ref("");
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formMode = ref("create");
const editingCategoryId = ref(null);
const formSubmitting = ref(false);
const formError = ref("");
const formErrors = ref({});
const form = reactive(emptyForm());

const deleteCategory = ref(null);
const deleteSubmitting = ref(false);
const deleteError = ref("");

const labels = computed(() => props.crudConfig.labels ?? {});
const filteredCategories = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === "") {
        return categories.value;
    }

    return categories.value.filter((category) => String(category.name ?? "").toLowerCase().includes(term));
});

function emptyForm() {
    return {
        name: "",
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

function sortCategories() {
    categories.value = [...categories.value].sort((left, right) => (
        String(left.name ?? "").localeCompare(String(right.name ?? ""))
    ));
}

function upsertCategory(category) {
    const index = categories.value.findIndex((entry) => entry.id === category.id);

    if (index >= 0) {
        categories.value.splice(index, 1, category);
    } else {
        categories.value.push(category);
    }

    sortCategories();
}

function openCreateDrawer() {
    formMode.value = "create";
    editingCategoryId.value = null;
    resetForm();
    formDrawerOpen.value = true;
}

function openEditDrawer(category) {
    formMode.value = "edit";
    editingCategoryId.value = category.id;
    resetForm({
        name: category.name ?? "",
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
            ? props.payload.updateUrlTemplate.replace("__ID__", editingCategoryId.value)
            : props.payload.storeUrl;
        const category = await jsonRequest(url, {
            method: isEdit ? "PATCH" : "POST",
            body: JSON.stringify(formPayload()),
        });

        upsertCategory(category);
        formDrawerOpen.value = false;
        showToast(isEdit ? "UoM category updated." : "UoM category created.");
    } catch (error) {
        formErrors.value = error.payload?.errors ?? {};
        formError.value = error.payload?.message ?? "Unable to save UoM category.";
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
        deleteCategory.value = record;
        deleteError.value = "";
    }
}

function closeDeleteModal() {
    if (deleteSubmitting.value) {
        return;
    }

    deleteCategory.value = null;
    deleteError.value = "";
}

async function confirmDelete() {
    if (!deleteCategory.value) {
        return;
    }

    deleteSubmitting.value = true;
    deleteError.value = "";
    listError.value = "";

    try {
        await jsonRequest(props.payload.deleteUrlTemplate.replace("__ID__", deleteCategory.value.id), {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
            },
        });

        categories.value = categories.value.filter((category) => category.id !== deleteCategory.value.id);
        showToast("UoM category deleted.");
        deleteCategory.value = null;
    } catch (error) {
        deleteError.value = error.payload?.message ?? "Unable to delete UoM category.";
    } finally {
        deleteSubmitting.value = false;
    }
}

function categoryRows(category) {
    return [
        {
            left: [{ label: "Name", value: category.name || "-" }],
            right: [],
        },
    ];
}
</script>

<template>
    <Head title="UoM Categories" />

    <AuthShell :shell="shell" title="UoM Categories">
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
                    :records="filteredCategories"
                    :error="listError"
                    bounded-height-class="h-full"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :title="(category) => category.name || '-'"
                            :subtitle="() => 'Unit category'"
                            :mobile-subtitle="() => 'Unit category'"
                            :detail-rows="categoryRows"
                            :href="() => null"
                            :actions="crudConfig.actions ?? []"
                            @action="handleAction"
                        />
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            :title="formMode === 'edit' ? 'Edit UoM Category' : labels.createTitle"
            description="Provide a clear category name."
            :submit-label="formMode === 'edit' ? 'Save Category' : 'Create Category'"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-md"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <template #header-icon>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.169.659 1.591l9.182 9.182a2.25 2.25 0 0 0 3.182 0l4.318-4.318a2.25 2.25 0 0 0 0-3.182L11.159 3.659A2.25 2.25 0 0 0 9.568 3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
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
            </div>
        </ResourceCreateDrawer>

        <div
            v-if="deleteCategory"
            class="fixed inset-0 z-[130] flex items-center justify-center bg-black/40 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="uom-category-delete-title"
            @click.self="closeDeleteModal"
        >
            <div class="w-full max-w-sm bg-white p-5 shadow-xl">
                <h2 id="uom-category-delete-title" class="text-base font-semibold text-gray-900">Delete UoM category?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleteCategory.name }} will be removed. This action cannot be undone.
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
