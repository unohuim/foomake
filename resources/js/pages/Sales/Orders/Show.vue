<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from "vue";

import BaseDrawer from "../../../components/BaseDrawer.vue";
import ResourceDetailHeaderBreadcrumb from "../../../components/ResourceDetailHeaderBreadcrumb.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

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
});

const loading = ref(true);
const pageError = ref("");
const payload = ref(null);
const toast = ref(null);
const toastTimer = ref(null);

const taskOpen = ref(false);
const taskSubmitting = ref(false);
const taskError = ref("");
const taskErrors = ref({});
const taskForm = reactive(emptyTaskForm());
const taskCompletingIds = ref([]);

const lineForm = reactive(emptyLineForm());
const lineErrors = ref(emptyLineErrors());
const lineGeneralError = ref("");
const lineEditQuantities = reactive({});
const lineEditErrorsByLine = reactive({});

const workflowMenuOpen = ref(false);
const workflowLoading = ref(false);
const workflowError = ref("");
const activeWorkflowStep = ref(0);
const noteBody = ref("");
const noteSaving = ref(false);
const noteError = ref("");
const noteSortDirection = ref("asc");

const order = computed(() => payload.value?.order ?? {});
const workflow = computed(() => payload.value?.workflow ?? {});
const workflowProgressSteps = computed(() => normalizeWorkflowSteps(payload.value?.workflowProgressSteps ?? []));
const sellableItems = computed(() => payload.value?.sellableItems ?? []);
const taskUsers = computed(() => payload.value?.taskCreate?.users ?? []);
const notesFeed = computed(() => payload.value?.notesFeed ?? {});
const notes = ref([]);
const canManageOrderLines = computed(() => Boolean(order.value?.can_manage_lines));
const statusLabel = computed(() => order.value?.display_label || order.value?.currentLabel || "DRAFT");
const workflowActions = computed(() => sortedWorkflowActions(workflow.value));
const workflowTerminalLabel = computed(() => {
    const label = String(workflow.value?.status || workflow.value?.status_label || workflow.value?.display_label || "").toUpperCase();

    if (label === "CANCELLED") {
        return "Cancelled";
    }

    if (label === "COMPLETED") {
        return "COMPLETED";
    }

    return "";
});
const workflowTriggerLabel = computed(() => workflowTerminalLabel.value
    || workflow.value?.display_label
    || workflow.value?.status_label
    || workflow.value?.currentLabel
    || workflow.value?.current_stage_label
    || workflow.value?.current_stage?.status_complete_label
    || workflow.value?.current_stage?.action_verb
    || workflow.value?.status
    || workflow.value?.header_menu?.currentLabel
    || "");
const breadcrumbItems = computed(() => [
    {
        label: "Sales Orders",
        url: props.indexUrl,
        current: false,
    },
    {
        label: order.value?.id ? `ID #${order.value.id}` : "",
        url: null,
        current: true,
    },
]);

const visibleNotes = computed(() => [...notes.value].sort((left, right) => {
    const multiplier = noteSortDirection.value === "desc" ? -1 : 1;
    const leftTime = Date.parse(left?.created_at || "") || 0;
    const rightTime = Date.parse(right?.created_at || "") || 0;

    return (leftTime - rightTime) * multiplier;
}));

const sellableItemsForOrder = computed(() => {
    const currencyCode = String(order.value?.currency_code || "").trim().toUpperCase();

    if (currencyCode === "") {
        return sellableItems.value;
    }

    return sellableItems.value.filter((item) => (
        String(item?.default_price_currency_code || "").trim().toUpperCase() === currencyCode
    ));
});

watch(workflowProgressSteps, (steps) => {
    activeWorkflowStep.value = activeWorkflowStepIndex(steps);
});

function emptyLineForm() {
    return {
        item_id: "",
        quantity: "1.000000",
    };
}

function emptyLineErrors() {
    return {
        item_id: [],
        quantity: [],
    };
}

function emptyTaskForm() {
    return {
        title: "",
        assigned_to_user_id: "",
        due_date: "",
        description: "",
    };
}

function csrfHeaders() {
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": payload.value?.csrfToken ?? "",
    };
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        clearTimeout(toastTimer.value);
    }

    toastTimer.value = setTimeout(() => {
        toast.value = null;
    }, 1500);
}

async function loadPayload() {
    loading.value = true;
    pageError.value = "";

    const response = await fetch(props.payloadUrl, {
        headers: {
            Accept: "application/json",
        },
    });

    if (!response.ok) {
        pageError.value = "Unable to load sales order.";
        loading.value = false;
        return;
    }

    const data = await response.json();
    payload.value = data.data ?? {};
    notes.value = Array.isArray(payload.value?.notesFeed?.notes) ? [...payload.value.notesFeed.notes] : [];
    syncLineState();
    loading.value = false;
}

function syncLineState() {
    (order.value?.lines || []).forEach((line) => {
        lineEditQuantities[line.id] = line.quantity;

        if (!lineEditErrorsByLine[line.id]) {
            lineEditErrorsByLine[line.id] = emptyLineErrors();
        }
    });
}

function normalizeLineErrors(errors) {
    if (!errors || typeof errors !== "object") {
        return emptyLineErrors();
    }

    return {
        ...emptyLineErrors(),
        item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
        quantity: Array.isArray(errors.quantity) ? errors.quantity : [],
    };
}

function sortedWorkflowActions(currentWorkflow) {
    const normalize = (actions) => {
        if (!Array.isArray(actions) || actions.length === 0) {
            return [];
        }

        return [...actions].sort((left, right) => {
            const leftIsCancel = String(left?.type || left?.id || "").toLowerCase() === "cancel";
            const rightIsCancel = String(right?.type || right?.id || "").toLowerCase() === "cancel";

            if (leftIsCancel === rightIsCancel) {
                return 0;
            }

            return leftIsCancel ? 1 : -1;
        });
    };

    if (Array.isArray(currentWorkflow?.actions) && currentWorkflow.actions.length > 0) {
        return normalize(currentWorkflow.actions);
    }

    if (currentWorkflow?.next_stage_action && typeof currentWorkflow.next_stage_action === "object") {
        return [currentWorkflow.next_stage_action];
    }

    if (Array.isArray(currentWorkflow?.header_menu?.options) && currentWorkflow.header_menu.options.length > 0) {
        return normalize(currentWorkflow.header_menu.options);
    }

    return [];
}

function normalizeWorkflowSteps(rawSteps) {
    let steps = Array.isArray(rawSteps) ? rawSteps : [];

    steps = steps
        .map((step, index) => ({
            label: String(step?.label || ""),
            status: ["completed", "current", "upcoming"].includes(step?.status) ? step.status : "upcoming",
            current: Boolean(step?.current || step?.status === "current"),
            number: String(index + 1).padStart(2, "0"),
        }))
        .filter((step) => step.label !== "" && step.label !== "DRAFT")
        .map((step, index) => ({
            ...step,
            number: String(index + 1).padStart(2, "0"),
        }));

    if (steps.length > 0 && !steps.some((step) => step.current) && !steps.some((step) => step.status === "completed")) {
        steps = steps.map((step, index) => ({
            ...step,
            status: index === 0 ? "current" : step.status,
            current: index === 0,
        }));
    }

    return steps;
}

function activeWorkflowStepIndex(steps) {
    let index = steps.findIndex((step) => step.current || step.status === "current");

    if (index < 0) {
        index = steps.findIndex((step) => step.status === "upcoming");
    }

    if (index < 0) {
        index = Math.max(0, steps.length - 1);
    }

    return index;
}

function workflowCircleClass(step) {
    if (step.status === "completed") {
        return "border-indigo-600 bg-indigo-600 text-white";
    }

    if (step.status === "current" || step.current) {
        return "border-indigo-600 bg-white text-indigo-600";
    }

    return "border-gray-300 bg-white text-gray-500";
}

function workflowLabelClass(step) {
    return step.status === "current" || step.current ? "text-indigo-600" : "text-gray-900";
}

function applyOrderLifecycleUpdate(data) {
    payload.value.order = {
        ...order.value,
        status: data.status,
        can_edit: data.can_edit,
        can_manage_lines: data.can_manage_lines,
        available_status_transitions: data.available_status_transitions || [],
        current_stage_tasks: data.current_stage_tasks || [],
        display_label: data.workflow?.display_label || data.display_label || order.value.display_label,
        status_label: data.workflow?.status_label || data.status_label || order.value.status_label,
        currentLabel: data.workflow?.currentLabel || data.currentLabel || order.value.currentLabel,
    };

    if (data.workflow) {
        payload.value.workflow = data.workflow;
    }

    if (Array.isArray(data.workflowProgressSteps)) {
        payload.value.workflowProgressSteps = data.workflowProgressSteps;
    }
}

async function performWorkflowAction(action) {
    if (!action || workflowLoading.value) {
        return;
    }

    const actionToSubmit = action.action && typeof action.action === "object" ? action.action : action;

    if (actionToSubmit.requiresConfirmation && !window.confirm(actionToSubmit.description || "Continue?")) {
        return;
    }

    workflowMenuOpen.value = false;
    workflowLoading.value = true;
    workflowError.value = "";

    const endpoint = String(actionToSubmit.endpoint || "").trim();
    const status = String(actionToSubmit.type || actionToSubmit.status || "").trim();

    if (!endpoint && !order.value?.status_update_url) {
        workflowLoading.value = false;
        return;
    }

    try {
        const response = await fetch(endpoint || order.value.status_update_url, {
            method: endpoint ? actionToSubmit.method || "PATCH" : "PATCH",
            headers: csrfHeaders(),
            body: JSON.stringify(endpoint ? { status } : { status }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            workflowError.value = data.message || "Unable to update workflow.";
            showToast(workflowError.value, "error");
            return;
        }

        applyOrderLifecycleUpdate({
            ...(data.data || {}),
            workflow: data.workflow || data.data?.workflow || null,
            workflowProgressSteps: data.data?.workflowProgressSteps || null,
        });
        showToast("Status updated.");
    } catch (error) {
        workflowError.value = "Unable to update workflow.";
        showToast(workflowError.value, "error");
    } finally {
        workflowLoading.value = false;
    }
}

function formatLineMoney(amount, currencyCode) {
    return `${currencyCode} ${amount}`;
}

async function submitLine() {
    if (!canManageOrderLines.value) {
        return;
    }

    lineErrors.value = emptyLineErrors();
    lineGeneralError.value = "";

    const response = await fetch(`${payload.value.lineStoreUrlBase}/${order.value.id}/lines`, {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify({
            item_id: lineForm.item_id === "" ? null : Number(lineForm.item_id),
            quantity: lineForm.quantity,
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (response.status === 422) {
        lineErrors.value = normalizeLineErrors(data.errors);
        lineGeneralError.value = data.message || "Validation failed.";
        return;
    }

    if (!response.ok) {
        lineGeneralError.value = "Unable to add line.";
        showToast(lineGeneralError.value, "error");
        return;
    }

    payload.value.order = data.data.order;
    Object.assign(lineForm, emptyLineForm());
    lineErrors.value = emptyLineErrors();
    lineGeneralError.value = "";
    syncLineState();
    showToast("Line added.");
}

async function saveLineQuantity(line) {
    if (!canManageOrderLines.value) {
        return;
    }

    lineEditErrorsByLine[line.id] = emptyLineErrors();

    const response = await fetch(`${payload.value.lineStoreUrlBase}/${order.value.id}/lines/${line.id}`, {
        method: "PATCH",
        headers: csrfHeaders(),
        body: JSON.stringify({
            quantity: lineEditQuantities[line.id] || line.quantity,
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (response.status === 422) {
        lineEditErrorsByLine[line.id] = normalizeLineErrors(data.errors);
        showToast(data.message || "Unable to update line quantity.", "error");
        return;
    }

    if (!response.ok) {
        showToast("Unable to update line quantity.", "error");
        return;
    }

    payload.value.order = data.data.order;
    syncLineState();
    showToast("Line quantity updated.");
}

async function deleteLine(line) {
    if (!canManageOrderLines.value) {
        return;
    }

    const response = await fetch(`${payload.value.lineStoreUrlBase}/${order.value.id}/lines/${line.id}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": payload.value?.csrfToken ?? "",
        },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        showToast(data.message || "Unable to remove line.", "error");
        return;
    }

    payload.value.order = data.data.order;
    syncLineState();
    showToast("Line removed.");
}

function openTaskCreate() {
    Object.assign(taskForm, emptyTaskForm());
    taskErrors.value = {};
    taskError.value = "";
    taskOpen.value = true;
}

async function submitTask() {
    if (taskSubmitting.value) {
        return;
    }

    taskSubmitting.value = true;
    taskErrors.value = {};
    taskError.value = "";

    const response = await fetch(payload.value?.taskCreate?.storeUrl ?? "/tasks", {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify({
            title: taskForm.title,
            assigned_to_user_id: taskForm.assigned_to_user_id === "" ? null : Number(taskForm.assigned_to_user_id),
            due_date: taskForm.due_date || null,
            description: taskForm.description,
            workflow_domain_id: order.value?.current_stage?.workflow_domain_id ?? null,
            domain_record_id: order.value?.id ?? null,
            workflow_stage_id: order.value?.current_stage?.id ?? null,
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (response.status === 422) {
        taskErrors.value = data.errors ?? {};
        taskError.value = data.message ?? "Unable to create task.";
        taskSubmitting.value = false;
        return;
    }

    if (!response.ok) {
        taskError.value = "Unable to create task.";
        taskSubmitting.value = false;
        return;
    }

    payload.value.order = {
        ...order.value,
        current_stage_tasks: [...(order.value.current_stage_tasks || []), data.data ?? {}].filter((task) => Boolean(task.id)),
    };
    taskOpen.value = false;
    taskSubmitting.value = false;
    showToast("Task created.");
}

async function completeTask(task) {
    if (!task?.id || !task?.complete_url || taskCompletingIds.value.includes(task.id)) {
        return;
    }

    taskCompletingIds.value = [...taskCompletingIds.value, task.id];

    const response = await fetch(task.complete_url, {
        method: "PATCH",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": payload.value?.csrfToken ?? "",
        },
    });

    if (response.ok) {
        const data = await response.json();
        payload.value.order = {
            ...order.value,
            current_stage_tasks: (order.value.current_stage_tasks || []).map((entry) => (
                entry.id === data.data?.id ? data.data : entry
            )),
        };
        showToast("Task completed.");
    } else {
        showToast("Unable to complete task.", "error");
    }

    taskCompletingIds.value = taskCompletingIds.value.filter((taskId) => taskId !== task.id);
}

async function submitNote() {
    if (!notesFeed.value?.store_url || noteSaving.value) {
        return;
    }

    noteSaving.value = true;
    noteError.value = "";

    const response = await fetch(notesFeed.value.store_url, {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify({
            body: noteBody.value,
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = data.errors ?? {};
        const bodyErrors = Array.isArray(errors.body) ? errors.body : [];
        noteError.value = bodyErrors[0] || data.message || "Unable to add note.";
        noteSaving.value = false;
        return;
    }

    if (data.note?.id) {
        notes.value = [...notes.value, data.note];
    }

    noteBody.value = "";
    noteSaving.value = false;
}

function toggleNoteSort() {
    noteSortDirection.value = noteSortDirection.value === "asc" ? "desc" : "asc";
}

function handleDocumentClick(event) {
    if (!event.target.closest("[data-workflow-action-button]")) {
        workflowMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
    loadPayload();
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <Head :title="title" />

    <AuthShell :shell="shell" :title="title" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <div v-if="toast" class="fixed right-4 top-4 z-[1200] rounded-md px-4 py-2 text-sm font-medium shadow-lg" :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
                {{ toast.message }}
            </div>

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="title"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #titleSuffix>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                        {{ statusLabel }}
                    </span>
                </template>
                <template #actions>
                    <div class="relative" data-workflow-action-button>
                        <div v-if="workflowLoading" class="absolute inset-0 z-10 rounded-lg bg-white/60" />
                        <button v-if="workflowTerminalLabel" type="button" class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60" disabled>
                            <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-700">
                                <span class="text-sm font-semibold uppercase text-slate-700">{{ workflowTriggerLabel }}</span>
                            </span>
                        </button>
                        <button
                            v-else-if="workflowActions.length > 0"
                            type="button"
                            class="inline-flex cursor-pointer items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="workflowLoading"
                            @click.stop="workflowMenuOpen = !workflowMenuOpen"
                        >
                            <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700">
                                <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm font-semibold uppercase text-slate-700">{{ workflowTriggerLabel }}</span>
                            </span>
                            <span class="inline-flex items-center border-l border-slate-300 px-2.5 text-slate-500">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>

                        <div v-if="workflowMenuOpen" class="absolute right-0 z-[1100] mt-2 w-72 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5" role="menu">
                            <button
                                v-for="action in workflowActions"
                                :key="action.id || action.type || action.handlerKey || action.label"
                                type="button"
                                class="flex w-full cursor-pointer items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="workflowLoading"
                                @click.stop="performWorkflowAction(action)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-slate-900">{{ action.label }}</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ action.description }}</span>
                                </span>
                            </button>
                            <p v-if="workflowError" class="px-3 pb-2 pt-1 text-xs text-red-600">{{ workflowError }}</p>
                        </div>
                    </div>
                </template>
            </ResourceDetailHeaderBreadcrumb>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading" class="py-12">
                    <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                        <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm text-gray-500">Loading sales order...</div>
                        </div>
                    </div>
                </div>

                <div v-else-if="pageError" class="py-12">
                    <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                        <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm text-red-600">{{ pageError }}</div>
                        </div>
                    </div>
                </div>

                <div v-else class="pt-0 pb-12 sm:pt-6">
                    <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                        <nav v-if="workflowProgressSteps.length > 0" class="-mx-1 w-auto sm:mx-0 sm:w-full" aria-label="Progress">
                            <div class="flex overflow-hidden border-y border-gray-300 bg-white md:hidden" role="tablist" aria-label="Workflow stages" data-workflow-progress-mobile-tabs>
                                <button
                                    v-for="(step, index) in workflowProgressSteps"
                                    :key="`${step.label}-${index}`"
                                    type="button"
                                    role="tab"
                                    class="relative min-h-16 cursor-pointer overflow-hidden bg-white py-2 pl-3 pr-6 transition-[flex-basis,flex-grow] duration-300 ease-out will-change-[flex-basis]"
                                    :class="activeWorkflowStep === index ? 'basis-0 grow' : 'basis-16 grow-0'"
                                    :aria-selected="activeWorkflowStep === index ? 'true' : 'false'"
                                    @click="activeWorkflowStep = index"
                                >
                                    <span class="flex h-full items-center" :class="activeWorkflowStep === index ? 'justify-start gap-3' : 'justify-center'">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2" :class="workflowCircleClass(step)">
                                            <svg v-if="step.status === 'completed'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" />
                                            </svg>
                                            <span v-else class="text-sm font-semibold">{{ step.number }}</span>
                                        </span>
                                        <span class="min-w-0 truncate text-left text-sm font-medium transition-[max-width,opacity,transform] duration-300 ease-out" :class="[workflowLabelClass(step), activeWorkflowStep === index ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0']">
                                            {{ step.label }}
                                        </span>
                                    </span>
                                    <span v-if="index < workflowProgressSteps.length - 1" aria-hidden="true" class="pointer-events-none absolute right-0 top-0 h-full w-5 md:hidden">
                                        <svg viewBox="0 0 22 80" fill="none" preserveAspectRatio="none" class="size-full text-gray-300">
                                            <path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </button>
                            </div>

                            <ol role="list" class="hidden divide-y divide-gray-300 rounded-md border border-gray-300 bg-white md:flex md:divide-y-0">
                                <li v-for="(step, index) in workflowProgressSteps" :key="`${step.label}-${index}`" class="relative md:flex md:flex-1">
                                    <span class="group flex w-full items-center" :aria-current="step.status === 'current' ? 'step' : null">
                                        <span class="flex items-center px-4 py-3 text-sm font-medium sm:px-6">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2" :class="workflowCircleClass(step)">
                                                <svg v-if="step.status === 'completed'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" />
                                                </svg>
                                                <span v-else class="text-sm font-semibold">{{ step.number }}</span>
                                            </span>
                                            <span class="ml-4 text-sm font-medium" :class="step.status === 'completed' ? 'text-gray-900' : workflowLabelClass(step)">{{ step.label }}</span>
                                        </span>
                                    </span>
                                    <div v-if="index < workflowProgressSteps.length - 1" aria-hidden="true" class="absolute right-0 top-0 hidden h-full w-5 md:block">
                                        <svg viewBox="0 0 22 80" fill="none" preserveAspectRatio="none" class="size-full text-gray-300">
                                            <path d="M0 -2L20 40L0 82" stroke="currentcolor" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                </li>
                            </ol>
                        </nav>

                        <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Status</p>
                                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ statusLabel }}</p>
                                    </div>

                                </div>

                                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                    <div><p class="text-sm text-gray-500">Order ID</p><p class="mt-1 text-base text-gray-900">{{ order.id }}</p></div>
                                    <div><p class="text-sm text-gray-500">Order date</p><p class="mt-1 text-base text-gray-900">{{ order.date || "-" }}</p></div>
                                    <div><p class="text-sm text-gray-500">Customer</p><p class="mt-1 text-base text-gray-900">{{ order.customer_name || "-" }}</p></div>
                                    <div><p class="text-sm text-gray-500">Contact</p><p class="mt-1 text-base text-gray-900">{{ order.contact_name || "-" }}</p></div>
                                    <div><p class="text-sm text-gray-500">City</p><p class="mt-1 text-base text-gray-900">{{ order.city || "-" }}</p></div>
                                    <div><p class="text-sm text-gray-500">External source</p><p class="mt-1 text-base text-gray-900">{{ order.external_source || "-" }}</p></div>
                                    <div><p class="text-sm text-gray-500">External ID</p><p class="mt-1 text-base text-gray-900">{{ order.external_id || "-" }}</p></div>
                                    <div><p class="text-sm text-gray-500">External status</p><p class="mt-1 text-base text-gray-900">{{ order.external_status || "-" }}</p></div>
                                </div>

                                <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Checklist</p>
                                        <button type="button" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-9 sm:w-9" aria-label="Create task" @click="openTaskCreate">
                                            <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        <div v-if="(order.current_stage_tasks || []).length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">No tasks for the current workflow stage.</div>
                                        <div v-for="task in order.current_stage_tasks || []" :key="task.id" class="rounded-lg border border-gray-200 bg-white p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="task.is_completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ task.status }}</span>
                                                    </div>
                                                    <p v-if="task.description" class="mt-1 text-xs text-gray-500">{{ task.description }}</p>
                                                    <p class="mt-1 text-xs text-gray-500">{{ task.assigned_to_user_name ? `Assigned to ${task.assigned_to_user_name}` : "Assigned user unavailable" }}</p>
                                                    <p v-if="task.due_date" class="mt-1 text-xs text-gray-500">Due {{ task.due_date }}</p>
                                                </div>
                                                <button v-if="task.can_complete" type="button" class="inline-flex cursor-pointer items-center rounded-md border border-emerald-300 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="taskCompletingIds.includes(task.id)" @click="completeTask(task)">
                                                    Complete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Order lines</h3>
                                        <p class="mt-1 text-sm text-gray-600">Manage order quantities and sellable items from the detail view.</p>
                                    </div>
                                    <div class="text-right text-sm text-gray-500">
                                        <p>{{ order.line_count || 0 }} line(s)</p>
                                        <p class="mt-1">Currency: {{ order.currency_code || "-" }}</p>
                                    </div>
                                </div>

                                <div v-if="(order.lines || []).length > 0" class="mt-6 space-y-4">
                                    <div v-for="line in order.lines" :key="line.id" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium text-gray-900">{{ line.item_name }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ formatLineMoney(line.unit_price_amount, line.unit_price_currency_code) }}</p>
                                                <p class="mt-1 text-xs text-gray-500">Total: {{ formatLineMoney(line.line_total_amount, line.unit_price_currency_code) }}</p>
                                            </div>
                                            <button v-if="canManageOrderLines" type="button" class="cursor-pointer text-red-600 hover:text-red-500" @click="deleteLine(line)">Remove</button>
                                        </div>

                                        <div v-if="canManageOrderLines" class="mt-3 flex items-start gap-2">
                                            <div class="flex-1">
                                                <input v-model="lineEditQuantities[line.id]" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                                                <p class="mt-1 text-xs text-red-600">{{ (lineEditErrorsByLine[line.id] || {}).quantity?.[0] }}</p>
                                            </div>
                                            <button type="button" class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" @click="saveLineQuantity(line)">
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mt-6 rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                                    <p>No lines yet.</p>
                                </div>

                                <div v-if="sellableItemsForOrder.length > 0 && canManageOrderLines" class="mt-6 rounded-lg border border-gray-200 p-4">
                                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_auto]">
                                        <div>
                                            <select v-model="lineForm.item_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select item</option>
                                                <option v-for="item in sellableItemsForOrder" :key="item.id" :value="String(item.id)">{{ item.name }} ({{ item.default_price_currency_code }})</option>
                                            </select>
                                            <p class="mt-1 text-xs text-red-600">{{ lineErrors.item_id[0] }}</p>
                                        </div>
                                        <div>
                                            <input v-model="lineForm.quantity" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="1.000000" />
                                            <p class="mt-1 text-xs text-red-600">{{ lineErrors.quantity[0] }}</p>
                                        </div>
                                        <button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500" @click="submitLine">
                                            Add Line
                                        </button>
                                    </div>
                                    <p v-if="lineGeneralError" class="mt-2 text-xs text-red-600">{{ lineGeneralError }}</p>
                                </div>

                                <div v-if="sellableItems.length > 0 && sellableItemsForOrder.length === 0 && canManageOrderLines" class="mt-6 rounded-lg border border-dashed border-gray-300 p-4">
                                    <p class="text-xs text-gray-500">No sellable items are priced in this order currency.</p>
                                </div>

                                <div v-if="!canManageOrderLines" class="mt-6 rounded-lg border border-dashed border-gray-300 p-4">
                                    <p class="text-xs text-gray-500">Line editing is unavailable once an order is completed or cancelled.</p>
                                </div>
                            </div>
                        </section>

                        <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="border-b border-gray-100 pb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Notes</h3>
                                    <p class="mt-1 text-sm text-gray-600">Timeline of internal comments for this resource.</p>
                                </div>

                                <div class="mt-6">
                                    <ol v-if="visibleNotes.length > 0" class="relative space-y-5 border-l border-gray-200 pl-5" role="list">
                                        <li v-for="note in visibleNotes" :key="note.id" class="relative">
                                            <span class="absolute -left-[1.65rem] top-4 flex h-3 w-3 rounded-full bg-blue-600 ring-4 ring-white" />
                                            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                                    <p class="text-sm font-semibold text-gray-900">{{ note.author_name || "Unknown user" }}</p>
                                                    <time class="text-xs font-medium text-gray-500" :datetime="note.created_at || ''">{{ note.created_at_display || "" }}</time>
                                                </div>
                                                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">{{ note.body || "" }}</p>
                                            </article>
                                        </li>
                                    </ol>

                                    <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                                        {{ notesFeed.empty_state || "No notes yet." }}
                                    </div>

                                    <form class="mt-6 space-y-3" @submit.prevent="submitNote">
                                        <div>
                                            <label class="sr-only" for="sales-order-notes-feed-body">Comment</label>
                                            <textarea id="sales-order-notes-feed-body" v-model="noteBody" rows="3" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100" placeholder="Add an internal comment" :disabled="noteSaving" />
                                            <p v-if="noteError" class="mt-2 text-sm text-red-600">{{ noteError }}</p>
                                        </div>

                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center text-gray-600 transition hover:text-gray-900 focus:outline-none focus-visible:text-gray-900" aria-label="Toggle comment sort order" @click="toggleNoteSort">
                                                <svg v-if="noteSortDirection === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                                </svg>
                                                <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                                </svg>
                                            </button>
                                            <button type="submit" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" :disabled="noteSaving">
                                                Comment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <BaseDrawer :open="taskOpen" labelled-by="sales-order-task-title" @close="taskOpen = false">
                <form class="flex h-full flex-col" @submit.prevent="submitTask">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 id="sales-order-task-title" class="text-sm font-semibold">Create Task</h2>
                    </div>
                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                        <div>
                            <label for="sales-order-task-form-title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="sales-order-task-form-title" v-model="taskForm.title" type="text" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <p v-if="taskErrors.title?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.title[0] }}</p>
                        </div>
                        <div>
                            <label for="sales-order-task-assigned-to-user-id" class="block text-sm font-medium text-gray-700">Assigned To</label>
                            <select id="sales-order-task-assigned-to-user-id" v-model="taskForm.assigned_to_user_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select user</option>
                                <option v-for="user in taskUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                            </select>
                            <p v-if="taskErrors.assigned_to_user_id?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.assigned_to_user_id[0] }}</p>
                        </div>
                        <div>
                            <label for="sales-order-task-due-date" class="block text-sm font-medium text-gray-700">Due Date</label>
                            <input id="sales-order-task-due-date" v-model="taskForm.due_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @click="$event.target.showPicker?.()" />
                            <p v-if="taskErrors.due_date?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.due_date[0] }}</p>
                        </div>
                        <div>
                            <label for="sales-order-task-description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="sales-order-task-description" v-model="taskForm.description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <p v-if="taskErrors.description?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.description[0] }}</p>
                        </div>
                        <p v-if="taskError" class="text-sm text-red-600">{{ taskError }}</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                        <button type="button" class="cursor-pointer rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="taskOpen = false">Cancel</button>
                        <button type="submit" class="cursor-pointer rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="taskSubmitting">Create Task</button>
                    </div>
                </form>
            </BaseDrawer>
        </div>
    </AuthShell>
</template>
