<script setup>
import { computed, onMounted, reactive, ref, useSlots } from "vue";

import BaseDropdown from "./BaseDropdown.vue";
import BaseDrawer from "./BaseDrawer.vue";
import UiToggle from "./UiToggle.vue";

const props = defineProps({
    section: {
        type: Object,
        required: true,
    },
    csrfToken: {
        type: String,
        required: true,
    },
    normalizeRecord: {
        type: Function,
        default: (record) => record,
    },
    buildCreatePayload: {
        type: Function,
        default: (form) => ({ ...form }),
    },
    buildUpdatePayload: {
        type: Function,
        default: (form) => ({ ...form }),
    },
});

const emit = defineEmits(["custom-action", "created", "updated", "removed"]);

const slots = useSlots();
const isOpen = ref(props.section.defaultOpen !== false);
const loading = ref(false);
const error = ref("");
const records = ref([]);
const meta = ref({});
const drawerOpen = ref(false);
const drawerMode = ref("create");
const editingRecordId = ref(null);
const form = reactive({});
const formErrors = ref({});
const formError = ref("");
const submitting = ref(false);
const toggleValues = reactive({});

const fields = computed(() => props.section.fields ?? []);
const createAction = computed(() => props.section.createAction ?? {});
const canCreate = computed(() => Boolean(props.section.permissions?.canCreate));
const visibleActions = computed(() => props.section.actions ?? []);
const hasStaticContent = computed(() => Boolean(slots.default));
const toolbarToggles = computed(() => props.section.toolbarToggles ?? []);

toolbarToggles.value.forEach((toggle) => {
    toggleValues[toggle.key] = Boolean(toggle.checked);
});

function valueAt(record, path, fallback = "") {
    const value = String(path ?? "")
        .split(".")
        .filter(Boolean)
        .reduce((carry, key) => (carry && Object.prototype.hasOwnProperty.call(carry, key) ? carry[key] : undefined), record);

    return value === null || value === undefined || value === "" ? fallback : value;
}

function resetForm(values = {}) {
    fields.value.forEach((field) => {
        form[field.name] = values[field.name] ?? createAction.value.prefill?.[field.name] ?? "";
    });
    formErrors.value = {};
    formError.value = "";
}

function rowPrimary(record) {
    const primary = props.section.rowLayout?.primaryText ?? {};

    return valueAt(record, primary.field, primary.fallback ?? "-");
}

function rowUrl(record) {
    const primary = props.section.rowLayout?.primaryText ?? {};

    return valueAt(record, primary.urlField, valueAt(record, "display.showUrl", ""));
}

function secondaryRows(record) {
    return (props.section.rowLayout?.secondaryFields ?? []).map((field) => ({
        label: field.label ?? "",
        value: valueAt(record, field.field, field.fallback ?? "-"),
    }));
}

function badges(record) {
    return (props.section.rowLayout?.badges ?? [])
        .map((badge) => ({
            text: valueAt(record, badge.field, badge.fallback ?? ""),
            tone: valueAt(record, badge.toneField, "muted"),
        }))
        .filter((badge) => badge.text !== "");
}

function rightMeta(record) {
    return (props.section.rowLayout?.rightMeta ?? []).map((metaItem) => ({
        label: metaItem.label ?? "",
        value: valueAt(record, metaItem.field, metaItem.fallback ?? "-"),
        strong: Boolean(metaItem.strong),
    }));
}

function badgeClasses(badge) {
    if (badge.tone === "success") {
        return "bg-emerald-100 text-emerald-700";
    }

    if (badge.tone === "default") {
        return "bg-blue-100 text-blue-700";
    }

    return "bg-gray-100 text-gray-700";
}

function allowedActions(record) {
    const allowed = Array.isArray(record.available_actions)
        ? record.available_actions
        : (Array.isArray(record.availableActions) ? record.availableActions : []);

    if (!Array.isArray(record.available_actions) && !Array.isArray(record.availableActions)) {
        return visibleActions.value;
    }

    return visibleActions.value.filter((action) => allowed.includes(action.id));
}

function actionClasses(action) {
    if (action.tone === "warning" || action.tone === "danger") {
        return "text-red-600 hover:bg-red-50";
    }

    return "text-gray-700 hover:bg-gray-50";
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        ...options,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": props.csrfToken,
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

async function load(page = 1) {
    if (hasStaticContent.value || !props.section.endpoints?.list) {
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(props.section.pagination?.perPage ?? 10),
        });

        toolbarToggles.value.forEach((toggle) => {
            if (toggleValues[toggle.key]) {
                params.set(toggle.key, "1");
            }
        });

        const response = await jsonRequest(`${props.section.endpoints.list}?${params.toString()}`, {
            method: "GET",
        });

        records.value = (response.data ?? []).map((record) => props.normalizeRecord(record));
        meta.value = response.meta ?? {};
    } catch (requestError) {
        error.value = requestError.payload?.message ?? "Unable to load section.";
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    if (createAction.value.type === "custom") {
        emit("custom-action", { action: createAction.value, record: null, refresh: load });
        return;
    }

    drawerMode.value = "create";
    editingRecordId.value = null;
    resetForm();
    drawerOpen.value = true;
}

function openEdit(record) {
    drawerMode.value = "edit";
    editingRecordId.value = record.id;
    resetForm(record.formValues ?? record);
    drawerOpen.value = true;
}

function endpointFor(action, record) {
    const key = action.endpointKey ?? (action.type === "remove" ? "remove" : "update");
    const template = props.section.endpoints?.[key] ?? "";

    return String(template).replace("{id}", record.id);
}

async function submitForm() {
    submitting.value = true;
    formErrors.value = {};
    formError.value = "";

    try {
        const isEdit = drawerMode.value === "edit";
        const url = isEdit
            ? String(props.section.endpoints.update ?? "").replace("{id}", editingRecordId.value)
            : props.section.endpoints.create;
        const payload = isEdit ? props.buildUpdatePayload(form) : props.buildCreatePayload(form);
        const response = await jsonRequest(url, {
            method: isEdit ? "PATCH" : "POST",
            body: JSON.stringify(payload),
        });

        await load(meta.value.current_page ?? 1);
        drawerOpen.value = false;
        emit(isEdit ? "updated" : "created", response.data ?? {});
    } catch (requestError) {
        formErrors.value = requestError.payload?.errors ?? {};
        formError.value = requestError.payload?.message ?? "Unable to save.";
    } finally {
        submitting.value = false;
    }
}

async function handleAction(action, record) {
    if (action.type === "view") {
        const url = valueAt(record, action.urlField, record.show_url ?? "");

        if (url !== "") {
            window.location.assign(url);
        }
        return;
    }

    if (action.type === "edit") {
        openEdit(record);
        return;
    }

    if (action.type === "remove" || action.type === "archive") {
        if (action.confirmMessage && !window.confirm(action.confirmMessage)) {
            return;
        }

        await jsonRequest(endpointFor(action, record), {
            method: action.method ?? "DELETE",
        });
        await load(meta.value.current_page ?? 1);
        emit("removed", record);
        return;
    }

    emit("custom-action", { action, record, refresh: load });
}

function handleToggleChange(detail) {
    toggleValues[detail.name] = Boolean(detail.checked);
    load(1);
}

function pageCount() {
    return Number(meta.value.last_page ?? 1);
}

function currentPage() {
    return Number(meta.value.current_page ?? 1);
}

onMounted(() => {
    if (isOpen.value && !hasStaticContent.value) {
        load();
    }
});
</script>

<template>
    <section class="-mx-1 !-mt-px overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200">
        <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">{{ section.title }}</h3>
                <p class="mt-0.5 truncate text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">{{ section.description }}</p>
            </div>
            <slot name="actions" />
            <div v-if="toolbarToggles.length > 0" class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                <label v-for="toggle in toolbarToggles" :key="toggle.key" class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-gray-600">
                    <UiToggle
                        :checked="Boolean(toggleValues[toggle.key])"
                        :name="toggle.key"
                        :aria-label="toggle.label"
                        @change="handleToggleChange"
                    />
                    <span>{{ toggle.label }}</span>
                </label>
            </div>
            <button v-if="canCreate" type="button" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8" aria-label="Create" @click.stop.prevent="openCreate">
                <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </button>
            <button type="button" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8" :aria-expanded="isOpen ? 'true' : 'false'" aria-label="Toggle section" @click="isOpen = !isOpen; if (isOpen && records.length === 0 && !hasStaticContent) load();">
                <svg class="h-3.5 w-3.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        </div>

        <div class="grid transition-[grid-template-rows] duration-[400ms] ease-in-out" :class="isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
            <div class="min-h-0 overflow-hidden">
                <div class="border-t border-gray-100 bg-white px-3 py-2 transition-opacity duration-[400ms] ease-in-out sm:px-6 sm:py-5" :class="isOpen ? 'opacity-100' : 'opacity-0'">
                    <slot v-if="hasStaticContent" />

                    <p v-else-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>
                    <p v-else-if="loading && records.length === 0" class="text-sm text-gray-500">Loading...</p>
                    <div v-else-if="records.length === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                        {{ section.emptyState }}
                    </div>

                    <div v-else class="-mx-3 space-y-0 border-t border-gray-300 sm:mx-0 sm:space-y-3 sm:border-t-0">
                        <article v-for="record in records" :key="record.id" class="relative border-b border-gray-300 bg-white px-3 py-3 sm:rounded-xl sm:border sm:border-gray-200 sm:px-4">
                            <div class="flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <a v-if="rowUrl(record)" :href="rowUrl(record)" class="truncate text-sm font-semibold text-gray-900 hover:text-blue-700">{{ rowPrimary(record) }}</a>
                                        <p v-else class="truncate text-sm font-semibold text-gray-900">{{ rowPrimary(record) }}</p>
                                        <span v-for="badge in badges(record)" :key="`${record.id}-${badge.text}`" class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[0.65rem] font-medium leading-none sm:px-2.5 sm:py-1 sm:text-xs" :class="badgeClasses(badge)">
                                            {{ badge.text }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                                        <p v-for="row in secondaryRows(record)" :key="`${record.id}-${row.label}`">
                                            <span class="text-gray-500">{{ row.label }}: </span>
                                            <span class="text-gray-700">{{ row.value }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <div class="text-right">
                                        <p v-for="metaItem in rightMeta(record)" :key="`${record.id}-${metaItem.label}`" class="text-sm" :class="metaItem.strong ? 'font-semibold text-gray-900' : 'text-gray-600'">
                                            <span class="text-gray-500">{{ metaItem.label }}: </span>{{ metaItem.value }}
                                        </p>
                                    </div>

                                    <BaseDropdown v-if="allowedActions(record).length > 0" aria-label="Row actions">
                                        <template #trigger>
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                            </svg>
                                        </template>
                                        <template #default="{ close }">
                                            <button v-for="action in allowedActions(record)" :key="`${record.id}-${action.id}`" type="button" class="flex w-full cursor-pointer items-center px-4 py-2 text-sm" :class="actionClasses(action)" role="menuitem" @click="close(); handleAction(action, record)">
                                                {{ action.label }}
                                            </button>
                                        </template>
                                    </BaseDropdown>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-if="pageCount() > 1" class="mt-4 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <button type="button" class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="currentPage() <= 1 || loading" @click="load(currentPage() - 1)">Previous</button>
                        <p class="text-xs text-gray-500">Page {{ currentPage() }} of {{ pageCount() }}</p>
                        <button type="button" class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="currentPage() >= pageCount() || loading" @click="load(currentPage() + 1)">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <BaseDrawer :open="drawerOpen" labelled-by="detail-section-drawer-title" panel-class="w-screen max-w-md" @close="drawerOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitForm">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="detail-section-drawer-title" class="text-sm font-semibold">{{ drawerMode === "edit" ? "Edit" : createAction.title }}</h2>
                    <p v-if="createAction.description" class="mt-1 text-xs text-blue-100">{{ createAction.description }}</p>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
                    <label v-for="field in fields" :key="field.name" class="block text-xs font-medium text-slate-700">
                        {{ field.label }}
                        <select v-if="field.type === 'select' || field.type === 'combobox'" v-model="form[field.name]" class="mt-1 block w-full border-slate-300 text-sm">
                            <option value="">Select</option>
                            <option v-for="option in field.options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <input v-else v-model="form[field.name]" class="mt-1 block w-full border-slate-300 text-sm" :class="field.name === 'price_amount' ? 'text-right' : ''" type="text">
                        <p v-if="formErrors[field.name]?.[0]" class="mt-1 text-xs text-red-600">{{ formErrors[field.name][0] }}</p>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="drawerOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="submitting">{{ drawerMode === "edit" ? "Save" : createAction.submitLabel ?? "Create" }}</button>
                </div>
            </form>
        </BaseDrawer>
    </section>
</template>
