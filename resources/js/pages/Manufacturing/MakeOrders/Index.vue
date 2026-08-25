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

const makeOrders = ref([]);
const search = ref("");
const sort = ref({
    column: "due_date",
    direction: "asc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);
const drawerOpen = ref(false);
const drawerSubmitting = ref(false);
const drawerRecordId = ref(null);
const drawerError = ref("");
const drawerErrors = ref(emptyErrors());
const form = reactive(emptyForm());

const labels = computed(() => props.crudConfig.labels ?? {});
const permissions = computed(() => props.crudConfig.permissions ?? {});
const recipes = computed(() => Array.isArray(props.payload.recipes) ? props.payload.recipes : []);
const canExecute = computed(() => Boolean(props.payload.canExecute));
const isEditMode = computed(() => drawerRecordId.value !== null);

function emptyForm() {
    return {
        recipe_id: "",
        runs: "",
        due_date: "",
    };
}

function emptyErrors() {
    return {
        recipe_id: [],
        runs: [],
        due_date: [],
    };
}

function normalizeErrors(errors) {
    if (!errors || typeof errors !== "object") {
        return emptyErrors();
    }

    return {
        ...emptyErrors(),
        ...errors,
        recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
        runs: Array.isArray(errors.runs) ? errors.runs : [],
        due_date: Array.isArray(errors.due_date) ? errors.due_date : [],
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

async function fetchMakeOrders(nextSearch = search.value) {
    const listUrl = props.crudConfig.endpoints?.list;

    if (!listUrl) {
        listError.value = "Unable to load make orders.";
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

        const data = await jsonRequest(url.toString(), { method: "GET" });

        makeOrders.value = Array.isArray(data.data) ? data.data : [];
        if (data.meta?.sort?.column && data.meta?.sort?.direction) {
            sort.value = {
                column: data.meta.sort.column,
                direction: data.meta.sort.direction,
            };
        }
    } catch {
        listError.value = "Unable to load make orders.";
    } finally {
        loading.value = false;
    }
}

function openCreate(prefill = {}) {
    if (!canExecute.value) {
        return;
    }

    drawerRecordId.value = null;
    drawerErrors.value = emptyErrors();
    drawerError.value = "";
    Object.assign(form, {
        recipe_id: prefill.recipe_id ? String(prefill.recipe_id) : "",
        runs: "",
        due_date: "",
    });
    drawerOpen.value = true;
}

function openEdit(record) {
    if (!canExecute.value) {
        return;
    }

    drawerRecordId.value = record.id;
    drawerErrors.value = emptyErrors();
    drawerError.value = "";
    Object.assign(form, {
        recipe_id: record.recipe_id ? String(record.recipe_id) : "",
        runs: record.runs || "",
        due_date: record.due_date || "",
    });
    drawerOpen.value = true;
}

function closeDrawer() {
    if (drawerSubmitting.value) {
        return;
    }

    drawerOpen.value = false;
    drawerRecordId.value = null;
    drawerErrors.value = emptyErrors();
    drawerError.value = "";
    Object.assign(form, emptyForm());
}

async function submitForm() {
    if (!canExecute.value) {
        drawerError.value = "You do not have permission to create make orders.";
        return;
    }

    drawerSubmitting.value = true;
    drawerErrors.value = emptyErrors();
    drawerError.value = "";

    const payload = {
        recipe_id: form.recipe_id ? Number(form.recipe_id) : "",
        runs: form.runs,
    };

    if (isEditMode.value) {
        payload.due_date = form.due_date || null;
    }

    const endpoint = isEditMode.value
        ? String(props.crudConfig.endpoints?.update ?? "").replace("{id}", encodeURIComponent(String(drawerRecordId.value)))
        : props.payload.storeUrl;

    try {
        await jsonRequest(endpoint, {
            method: isEditMode.value ? "PATCH" : "POST",
            body: JSON.stringify(payload),
        });
        await fetchMakeOrders();
        closeDrawer();
        showToast(isEditMode.value ? "Make order updated." : "Make order created.");
    } catch (error) {
        drawerErrors.value = normalizeErrors(error.payload?.errors);
        drawerError.value = error.payload?.message ?? "Validation failed.";
    } finally {
        drawerSubmitting.value = false;
    }
}

async function archiveMakeOrder(record) {
    if (!canExecute.value) {
        showToast("You do not have permission to archive make orders.", "error");
        return;
    }

    const endpoint = String(props.crudConfig.endpoints?.delete ?? "").replace("{id}", encodeURIComponent(String(record?.id ?? "")));

    try {
        const data = await jsonRequest(endpoint, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": props.payload.csrfToken,
            },
        });
        const removedId = data?.removed_id ?? record?.id;

        makeOrders.value = makeOrders.value.filter((entry) => Number(entry.id) !== Number(removedId));
    } catch (error) {
        showToast(error.payload?.message ?? "Unable to archive make order.", "error");
    }
}

function makeOrderTitleBadges(record) {
    const status = String(record?.status_label || record?.workflow_state || "").trim();

    if (status === "") {
        return [];
    }

    const normalized = status.toUpperCase();
    const tones = {
        CANCELLED: "gray",
        COMPLETED: "green",
        CREATED: "blue",
        DRAFT: "yellow",
        "IN PROGRESS": "blue",
        MADE: "green",
        SCHEDULED: "blue",
    };

    return [{
        label: status,
        tone: tones[normalized] || "gray",
    }];
}

function makeOrderQuantityWithUom(value, record) {
    const quantity = String(value || "-").trim();
    const symbol = String(record?.output_uom_symbol || "").trim();

    if (quantity === "-" || symbol === "") {
        return quantity;
    }

    return `${quantity} ${symbol}`;
}

function makeOrderCardRows(record) {
    return [
        {
            left: [
                { label: "Recipe", value: record?.recipe_name || "-" },
                { label: "Runs", value: record?.runs_display || record?.runs || "-" },
            ],
            right: [],
        },
        {
            left: [
                { label: "Expected", value: makeOrderQuantityWithUom(record?.expected_output_qty_display || record?.qty_display, record) },
            ],
            right: [
                { label: "Actual", value: makeOrderQuantityWithUom(record?.actual_output_qty_display || record?.actual_output_quantity_display, record) },
            ],
        },
    ];
}

function makeOrderSummary(record) {
    const parts = [];

    if (record?.due_date) {
        parts.push(`Due ${record.due_date}`);
    }

    if (record?.runs_display || record?.runs) {
        parts.push(`Runs ${record.runs_display || record.runs}`);
    }

    if (record?.qty_display || record?.qty) {
        parts.push(`Qty ${record.qty_display || record.qty}`);
    }

    if (record?.workflow_state) {
        parts.push(record.workflow_state);
    }

    return parts.join(" · ");
}

onMounted(() => {
    void fetchMakeOrders();

    if (props.payload.prefillRecipeId) {
        openCreate({ recipe_id: props.payload.prefillRecipeId });
    }
});

onBeforeUnmount(() => {
    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }
});
</script>

<template>
    <Head title="Make Orders" />

    <AuthShell :shell="shell" title="Make Orders">
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
                    :records="makeOrders"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchMakeOrders"
                    @create="openCreate"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :title="(record) => record.output_item_name || '-'"
                            :title-aside="(record) => record.due_date || 'No due date'"
                            :title-badges="makeOrderTitleBadges"
                            :subtitle="() => ''"
                            :mobile-subtitle="makeOrderSummary"
                            :detail-rows="makeOrderCardRows"
                            :href="(record) => record.show_url"
                        >
                            <template #desktop-aside="{ record }">
                                <div class="flex shrink-0 items-start gap-2">
                                    <p class="shrink-0 text-xs font-medium text-gray-500">
                                        {{ record.due_date || "No due date" }}
                                    </p>
                                    <button
                                        v-if="canExecute"
                                        type="button"
                                        class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-red-600 transition hover:bg-red-50"
                                        aria-label="Archive make order"
                                        @click.stop="archiveMakeOrder(record)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <template #mobile-aside="{ record }">
                                <div class="flex shrink-0 items-start gap-2">
                                    <p class="shrink-0 self-start text-xs font-medium text-gray-500">
                                        {{ record.due_date || "No due date" }}
                                    </p>
                                    <button
                                        v-if="canExecute"
                                        type="button"
                                        class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-red-600 transition hover:bg-red-50"
                                        aria-label="Archive make order"
                                        @click.stop="archiveMakeOrder(record)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </ResourceCardGrid>
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer
            :open="drawerOpen"
            :title="isEditMode ? 'Edit Make Order' : 'Create Make Order'"
            description="Create a draft make order from an active recipe."
            :submit-label="isEditMode ? 'Save' : 'Create'"
            :submitting="drawerSubmitting"
            :error="drawerError"
            panel-class="w-screen max-w-md"
            @close="closeDrawer"
            @submit="submitForm"
        >
            <template #header-icon>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
                </svg>
            </template>

            <div class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Recipe</span>
                    <select v-model="form.recipe_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option value="">Select recipe</option>
                        <option v-for="recipe in recipes" :key="recipe.id" :value="String(recipe.id)">
                            {{ recipe.name }} - {{ recipe.item_name }} ({{ recipe.output_quantity_display }})
                        </option>
                    </select>
                    <span v-if="drawerErrors.recipe_id[0]" class="mt-1 block text-xs text-red-600">{{ drawerErrors.recipe_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Runs</span>
                    <input v-model="form.runs" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="drawerErrors.runs[0]" class="mt-1 block text-xs text-red-600">{{ drawerErrors.runs[0] }}</span>
                </label>

                <label v-if="isEditMode" class="block">
                    <span class="text-xs font-semibold text-gray-700">Due Date</span>
                    <input v-model="form.due_date" type="date" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="drawerErrors.due_date[0]" class="mt-1 block text-xs text-red-600">{{ drawerErrors.due_date[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>
    </AuthShell>
</template>
