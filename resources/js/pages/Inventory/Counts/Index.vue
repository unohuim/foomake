<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";

import ResourceCardGrid from "../../../components/ResourceCardGrid.vue";
import ResourceCreateDrawer from "../../../components/ResourceCreateDrawer.vue";
import ResourceIndex from "../../../components/ResourceIndex.vue";
import UiToast from "../../../components/UiToast.vue";
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

const counts = ref([]);
const search = ref("");
const sort = ref({
    column: "counted_at",
    direction: "desc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const createDrawerOpen = ref(false);
const createSubmitting = ref(false);
const createError = ref("");
const createErrors = ref(emptyErrors());
const form = reactive(emptyForm());

const labels = computed(() => props.crudConfig.labels ?? {});
const permissions = computed(() => props.crudConfig.permissions ?? {});
const users = computed(() => Array.isArray(props.payload.users) ? props.payload.users : []);

function emptyErrors() {
    return {
        name: [],
        counted_at: [],
        notes: [],
        assigned_to_user_id: [],
        general: [],
    };
}

function emptyForm() {
    return {
        name: "",
        counted_at: "",
        notes: "",
        assigned_to_user_id: "",
    };
}

function resetForm() {
    Object.assign(form, emptyForm());
    createError.value = "";
    createErrors.value = emptyErrors();
}

function normalizeErrors(errors) {
    if (!errors || typeof errors !== "object") {
        return emptyErrors();
    }

    return {
        ...emptyErrors(),
        ...errors,
        name: Array.isArray(errors.name) ? errors.name : [],
        counted_at: Array.isArray(errors.counted_at) ? errors.counted_at : [],
        notes: Array.isArray(errors.notes) ? errors.notes : [],
        assigned_to_user_id: Array.isArray(errors.assigned_to_user_id) ? errors.assigned_to_user_id : [],
        general: Array.isArray(errors.general) ? errors.general : [],
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
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": props.payload.csrfToken,
            ...(options.headers ?? {}),
        },
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

async function fetchCounts(nextSearch = search.value) {
    const listUrl = props.crudConfig.endpoints?.list;

    if (!listUrl) {
        listError.value = "Unable to load inventory counts.";
        return;
    }

    search.value = nextSearch;
    loading.value = true;
    listError.value = "";

    try {
        const url = new URL(listUrl, window.location.origin);
        url.searchParams.set("search", search.value);
        url.searchParams.set("sort", sort.value.column);
        url.searchParams.set("direction", sort.value.direction);

        const data = await jsonRequest(url.toString(), {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        });

        counts.value = Array.isArray(data.data) ? data.data : [];

        if (data.meta?.sort?.column && data.meta?.sort?.direction) {
            sort.value = {
                column: data.meta.sort.column,
                direction: data.meta.sort.direction,
            };
        }
    } catch {
        listError.value = "Unable to load inventory counts.";
    } finally {
        loading.value = false;
    }
}

function openCreateDrawer() {
    if (!permissions.value.showCreate) {
        return;
    }

    resetForm();
    createDrawerOpen.value = true;
}

function closeCreateDrawer() {
    if (createSubmitting.value) {
        return;
    }

    createDrawerOpen.value = false;
    resetForm();
}

function createPayload() {
    return {
        name: String(form.name ?? "").trim(),
        counted_at: form.counted_at,
        notes: form.notes,
        assigned_to_user_id: form.assigned_to_user_id === "" ? null : form.assigned_to_user_id,
    };
}

async function submitCreate() {
    createSubmitting.value = true;
    createError.value = "";
    createErrors.value = emptyErrors();

    try {
        await jsonRequest(props.crudConfig.endpoints?.create, {
            method: "POST",
            body: JSON.stringify(createPayload()),
        });

        await fetchCounts();
        createDrawerOpen.value = false;
        resetForm();
        showToast("Inventory count saved.");
    } catch (error) {
        createErrors.value = normalizeErrors(error.payload?.errors);
        createError.value = error.payload?.message ?? "Unable to save count.";
    } finally {
        createSubmitting.value = false;
    }
}

function inventoryCountStatusBadges(record) {
    const status = String(record?.status_label || "").trim();

    if (status === "") {
        return [];
    }

    const normalized = status.toUpperCase();
    const tones = {
        CANCELLED: "gray",
        COMPLETED: "green",
        COUNTED: "green",
        CREATED: "blue",
        DRAFT: "yellow",
        POSTED: "green",
        RECEIVED: "green",
        SCHEDULED: "blue",
    };

    return [{
        label: status,
        tone: tones[normalized] || "gray",
    }];
}

function inventoryCountSummary(record) {
    return record?.counter_name || record?.counter_email || "-";
}

function inventoryCountDetailRows(record) {
    return [
        {
            left: [
                { label: "Assigned", value: record?.counter_name || record?.counter_email || "-" },
                { label: "Items", value: String(record?.lines_count ?? 0) },
            ],
            right: [],
        },
        {
            left: [
                { label: "Posted", value: record?.posted_at || "-" },
            ],
            right: [],
        },
    ];
}

function handleOpenCreateEvent() {
    openCreateDrawer();
}

onMounted(() => {
    void fetchCounts();
    window.addEventListener("open-create-inventory-count", handleOpenCreateEvent);
});

onBeforeUnmount(() => {
    window.removeEventListener("open-create-inventory-count", handleOpenCreateEvent);

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }
});
</script>

<template>
    <Head title="Inventory Counts" />

    <AuthShell :shell="shell" title="Inventory Counts">
        <div class="flex h-full min-h-0 w-full">
            <div class="relative flex min-h-0 w-full flex-1">
                <UiToast
                    :visible="Boolean(toast)"
                    :type="toast?.tone || 'success'"
                    :message="toast?.message || ''"
                    @dismiss="dismissToast"
                />

                <ResourceIndex
                    v-model:search="search"
                    :labels="labels"
                    :permissions="permissions"
                    :records="counts"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchCounts"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title="(record) => record.name || '-'"
                            :title-aside="(record) => record.counted_at || '-'"
                            :title-badges="inventoryCountStatusBadges"
                            :subtitle="inventoryCountSummary"
                            :mobile-subtitle="inventoryCountSummary"
                            :detail-rows="inventoryCountDetailRows"
                            :href="(record) => record.show_url"
                        />
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="createDrawerOpen"
            :title="labels.createTitle || 'Create Inventory Count'"
            description="Schedule or start a new inventory count."
            submit-label="Save Count"
            :submitting="createSubmitting"
            :error="createError"
            panel-class="w-screen max-w-md"
            @close="closeCreateDrawer"
            @submit="submitCreate"
        >
            <template #header-icon>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7.5 3.75h9A2.25 2.25 0 0 1 18.75 6v12A2.25 2.25 0 0 1 16.5 20.25h-9A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
                </svg>
            </template>

            <div class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Name</span>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                        autocomplete="off"
                    >
                    <span v-if="createErrors.name[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.name[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Counted At</span>
                    <input
                        v-model="form.counted_at"
                        type="date"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                    >
                    <span v-if="createErrors.counted_at[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.counted_at[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Assigned To</span>
                    <select
                        v-model="form.assigned_to_user_id"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                    >
                        <option value="">Unassigned</option>
                        <option v-for="user in users" :key="user.id" :value="String(user.id)">
                            {{ user.name }}{{ user.email ? ` (${user.email})` : "" }}
                        </option>
                    </select>
                    <span v-if="createErrors.assigned_to_user_id[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.assigned_to_user_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Notes</span>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]"
                    />
                    <span v-if="createErrors.notes[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.notes[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>
    </AuthShell>
</template>
