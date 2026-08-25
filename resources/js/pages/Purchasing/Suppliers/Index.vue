<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

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

const suppliers = ref([...props.payload.suppliers]);
const search = ref("");
const sort = reactive({
    column: "company_name",
    direction: "asc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const formDrawerOpen = ref(false);
const formMode = ref("create");
const editingSupplierId = ref(null);
const formSubmitting = ref(false);
const formError = ref("");
const formErrors = ref({});
const form = reactive(emptyForm());

const archiveSupplier = ref(null);
const archiveSubmitting = ref(false);
const archiveError = ref("");

const labels = computed(() => props.crudConfig.labels ?? {});

function emptyForm() {
    return {
        company_name: "",
        url: "",
        phone: "",
        email: "",
        currency_code: props.payload.defaultCurrency ?? "",
    };
}

function resetForm(values = emptyForm()) {
    Object.assign(form, emptyForm(), values);
    formErrors.value = {};
    formError.value = "";
}

function nullableString(value) {
    const normalized = String(value ?? "").trim();

    return normalized === "" ? null : normalized;
}

function supplierTitleBadges(supplier) {
    const currency = String(supplier?.currency_code ?? "").trim();

    return currency === "" ? [] : [
        {
            label: currency,
            tone: "gray",
        },
    ];
}

function supplierCardRows(supplier) {
    return [
        {
            left: [{ label: "Email", value: supplier?.email || "-" }],
            right: [{ label: "Phone", value: supplier?.phone || "-" }],
        },
        {
            left: [{ label: "Website", value: supplier?.url || "-" }],
            right: [],
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

async function fetchSuppliers(nextSearch = search.value) {
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

        suppliers.value = response.data ?? [];

        if (response.meta?.sort) {
            sort.column = response.meta.sort.column ?? sort.column;
            sort.direction = response.meta.sort.direction ?? sort.direction;
        }
    } catch (error) {
        listError.value = error.payload?.message ?? "Unable to load suppliers.";
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    formMode.value = "create";
    editingSupplierId.value = null;
    resetForm();
    formDrawerOpen.value = true;
}

function openEditDrawer(supplier) {
    formMode.value = "edit";
    editingSupplierId.value = supplier.id;
    resetForm({
        company_name: supplier.company_name ?? "",
        url: supplier.url ?? "",
        phone: supplier.phone ?? "",
        email: supplier.email ?? "",
        currency_code: supplier.currency_code ?? props.payload.defaultCurrency ?? "",
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
    const currencyCode = nullableString(form.currency_code);

    return {
        company_name: String(form.company_name ?? "").trim(),
        url: nullableString(form.url),
        phone: nullableString(form.phone),
        email: nullableString(form.email),
        currency_code: currencyCode === null ? null : currencyCode.toUpperCase(),
    };
}

async function submitForm() {
    formSubmitting.value = true;
    formError.value = "";
    formErrors.value = {};

    try {
        const isEdit = formMode.value === "edit";
        const url = isEdit
            ? `${props.payload.updateUrlBase}/${editingSupplierId.value}`
            : props.crudConfig.endpoints.create;
        const response = await jsonRequest(url, {
            method: isEdit ? "PATCH" : "POST",
            body: JSON.stringify(formPayload()),
        });

        await fetchSuppliers();
        refreshShellNavigation();
        formDrawerOpen.value = false;
        showToast(response.message ?? (isEdit ? "Supplier updated." : "Supplier created."));
    } catch (error) {
        formErrors.value = error.payload?.errors ?? {};
        formError.value = error.payload?.message ?? "Unable to save supplier.";
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

function handleAction({ action, record }) {
    if (action.id === "edit") {
        openEditDrawer(record);
        return;
    }

    if (action.id === "archive") {
        archiveSupplier.value = record;
        archiveError.value = "";
    }
}

function closeArchiveModal() {
    if (archiveSubmitting.value) {
        return;
    }

    archiveSupplier.value = null;
    archiveError.value = "";
}

async function confirmArchive() {
    if (!archiveSupplier.value) {
        return;
    }

    archiveSubmitting.value = true;
    archiveError.value = "";

    try {
        await jsonRequest(`${props.payload.updateUrlBase}/${archiveSupplier.value.id}`, {
            method: "DELETE",
        });
        await fetchSuppliers();
        refreshShellNavigation();
        showToast("Supplier archived.");
        archiveSupplier.value = null;
    } catch (error) {
        archiveError.value = error.payload?.message ?? "Unable to archive supplier.";
    } finally {
        archiveSubmitting.value = false;
    }
}

onMounted(() => {
    fetchSuppliers();
});
</script>

<template>
    <Head title="Suppliers" />

    <AuthShell :shell="shell" title="Suppliers">
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
                    :records="suppliers"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchSuppliers"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title="(supplier) => supplier.company_name || '-'"
                            :title-badges="supplierTitleBadges"
                            :subtitle="(supplier) => supplier.email || '-'"
                            :mobile-subtitle="(supplier) => supplier.email || '-'"
                            :detail-rows="supplierCardRows"
                            :href="(supplier) => supplier.show_url"
                            :actions="crudConfig.actions ?? []"
                            @action="handleAction"
                        />
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="formDrawerOpen"
            :title="formMode === 'edit' ? 'Edit Supplier' : labels.createTitle"
            :description="formMode === 'edit' ? 'Update supplier contact and currency details.' : 'Add supplier contact and currency details.'"
            :submit-label="formMode === 'edit' ? 'Save Supplier' : 'Create Supplier'"
            :submitting="formSubmitting"
            :error="formError"
            panel-class="w-screen max-w-md"
            @close="closeFormDrawer"
            @submit="submitForm"
        >
            <div class="space-y-3">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Company name</span>
                    <input
                        v-model="form.company_name"
                        type="text"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="organization"
                    >
                    <span v-if="formErrors.company_name" class="mt-1 block text-xs text-red-600">{{ formErrors.company_name[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Website</span>
                    <input
                        v-model="form.url"
                        type="text"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="url"
                    >
                    <span v-if="formErrors.url" class="mt-1 block text-xs text-red-600">{{ formErrors.url[0] }}</span>
                </label>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-gray-700">Phone</span>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                            autocomplete="tel"
                        >
                        <span v-if="formErrors.phone" class="mt-1 block text-xs text-red-600">{{ formErrors.phone[0] }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold text-gray-700">Currency</span>
                        <input
                            v-model="form.currency_code"
                            type="text"
                            maxlength="3"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm uppercase shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                            autocomplete="off"
                        >
                        <span v-if="formErrors.currency_code" class="mt-1 block text-xs text-red-600">{{ formErrors.currency_code[0] }}</span>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Email</span>
                    <input
                        v-model="form.email"
                        type="email"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="email"
                    >
                    <span v-if="formErrors.email" class="mt-1 block text-xs text-red-600">{{ formErrors.email[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>

        <div
            v-if="archiveSupplier"
            class="fixed inset-0 z-[130] flex items-center justify-center bg-black/40 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="supplier-archive-title"
            @click.self="closeArchiveModal"
        >
            <div class="w-full max-w-sm bg-white p-5 shadow-xl">
                <h2 id="supplier-archive-title" class="text-base font-semibold text-gray-900">Archive supplier?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ archiveSupplier.company_name }} will be removed from the supplier index.
                </p>

                <p v-if="archiveError" class="mt-3 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ archiveError }}
                </p>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="cursor-pointer inline-flex h-9 items-center justify-center border border-gray-200 bg-white px-3 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                        @click="closeArchiveModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer inline-flex h-9 items-center justify-center bg-red-600 px-3 text-xs font-semibold text-white transition hover:bg-red-700"
                        :disabled="archiveSubmitting"
                        :class="archiveSubmitting ? 'cursor-not-allowed opacity-60' : ''"
                        @click="confirmArchive"
                    >
                        Archive
                    </button>
                </div>
            </div>
        </div>

    </AuthShell>
</template>
