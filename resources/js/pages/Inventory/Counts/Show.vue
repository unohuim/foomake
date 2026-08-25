<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";

import BaseDrawer from "../../../components/BaseDrawer.vue";
import ResourceDetailHeaderBreadcrumb from "../../../components/ResourceDetailHeaderBreadcrumb.vue";
import UiToast from "../../../components/UiToast.vue";
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
    indexUrl: {
        type: String,
        required: true,
    },
    payload: {
        type: Object,
        required: true,
    },
});

const payload = ref({ ...props.payload });
const count = ref({ ...(props.payload.count ?? {}) });
const workflow = ref({ ...(props.payload.workflow ?? {}) });
const sections = ref({ ...(props.payload.sections ?? {}) });
const workflowProgressSteps = ref(normalizeWorkflowSteps(props.payload.workflowProgressSteps ?? [], count.value));
const countLines = ref([]);
const countLinesMeta = ref({});
const countLinesLoading = ref(false);
const countLinesError = ref("");
const addLineValue = ref("");
const addLineError = ref("");
const showRequiredCountedQuantityCue = ref(false);
const currentStageTasks = ref(sortTasks(count.value.current_stage_tasks ?? []));
const taskOpen = ref(false);
const taskSubmitting = ref(false);
const taskError = ref("");
const taskErrors = ref({});
const taskCompletingIds = ref([]);
const taskForm = reactive(emptyTaskForm());
const details = reactive({
    counted_at_iso: count.value.counted_at_iso ?? "",
    assigned_to_user_id: normalizeId(count.value.assigned_to_user_id),
    notes: count.value.notes ?? "",
});
const lastSavedDetails = reactive({ ...details });
const savingDetails = reactive({
    counted_at: false,
    assigned_to_user_id: false,
    notes: false,
});
const detailsAutosaveReady = ref(false);
const workflowMenuOpen = ref(false);
const workflowLoading = ref(false);
const workflowError = ref("");
const activeWorkflowStep = ref(0);
const toastVisible = ref(false);
const toastType = ref("success");
const toastMessage = ref("");
const toastTimer = ref(null);
const notes = ref([...(props.payload.notesFeed?.notes ?? [])]);
const notesFeed = computed(() => payload.value.notesFeed ?? {});
const noteBody = ref("");
const noteSaving = ref(false);
const noteError = ref("");
const noteSortDirection = ref("asc");

const csrfToken = computed(() => payload.value.csrfToken ?? "");
const pageTitle = computed(() => count.value.name || props.title);
const breadcrumbItems = computed(() => [
    {
        label: "Inventory Counts",
        url: props.indexUrl,
        current: false,
    },
    {
        label: pageTitle.value,
        url: null,
        current: true,
    },
]);
const statusLabel = computed(() => count.value.workflow_status_label
    || workflow.value.display_label
    || workflow.value.status_label
    || workflow.value.currentLabel
    || "Draft");
const workflowActions = computed(() => sortedWorkflowActions(workflow.value));
const workflowTriggerLabel = computed(() => workflow.value.display_label
    || workflow.value.status_label
    || workflow.value.currentLabel
    || statusLabel.value);
const countLinesSection = computed(() => sections.value.countLines ?? {});
const addLineOptions = computed(() => countLinesSection.value.addRow?.options ?? []);
const canAddLines = computed(() => Boolean(countLinesSection.value.addRow?.enabled));
const visibleNotes = computed(() => [...notes.value].sort((left, right) => {
    const multiplier = noteSortDirection.value === "desc" ? -1 : 1;
    const leftTime = Date.parse(left?.created_at || "") || 0;
    const rightTime = Date.parse(right?.created_at || "") || 0;

    return (leftTime - rightTime) * multiplier;
}));

watch(workflowProgressSteps, (steps) => {
    activeWorkflowStep.value = activeWorkflowStepIndex(steps);
}, { immediate: true });

watch(() => details.assigned_to_user_id, async (value) => {
    if (!detailsAutosaveReady.value || savingDetails.assigned_to_user_id) {
        return;
    }

    if (normalizeId(value) === lastSavedDetails.assigned_to_user_id) {
        return;
    }

    await saveDetails("assigned_to_user_id");
});

function normalizeId(value) {
    return value === null || value === undefined || value === "" ? "" : String(value);
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
        "X-CSRF-TOKEN": csrfToken.value,
    };
}

function showToast(message, type = "success") {
    toastType.value = type;
    toastMessage.value = message;
    toastVisible.value = true;

    if (toastTimer.value) {
        clearTimeout(toastTimer.value);
    }

    toastTimer.value = setTimeout(() => {
        toastVisible.value = false;
    }, 1500);
}

function normalizeLine(line) {
    return {
        ...line,
        counted_quantity_input: typeof line?.counted_quantity_input === "string"
            ? line.counted_quantity_input
            : (line?.counted_quantity ?? ""),
        _lastSavedCountedQuantityInput: typeof line?.counted_quantity_input === "string"
            ? line.counted_quantity_input
            : (line?.counted_quantity ?? ""),
        _countedQuantitySaved: false,
        _countedQuantitySaving: false,
    };
}

function sortTasks(tasks) {
    return Array.isArray(tasks)
        ? [...tasks].sort((left, right) => {
            const leftSortOrder = Number(left.sort_order ?? 0);
            const rightSortOrder = Number(right.sort_order ?? 0);

            if (leftSortOrder !== rightSortOrder) {
                return leftSortOrder - rightSortOrder;
            }

            return Number(left.id ?? 0) - Number(right.id ?? 0);
        })
        : [];
}

function sortedWorkflowActions(currentWorkflow) {
    const actions = Array.isArray(currentWorkflow?.actions) ? currentWorkflow.actions : [];

    return [...actions].sort((left, right) => {
        const leftIsCancel = String(left?.type || left?.id || "").toLowerCase() === "cancel";
        const rightIsCancel = String(right?.type || right?.id || "").toLowerCase() === "cancel";

        if (leftIsCancel === rightIsCancel) {
            return 0;
        }

        return leftIsCancel ? 1 : -1;
    });
}

function normalizeWorkflowSteps(rawSteps, currentCount = {}) {
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

    const hasActiveStep = steps.some((step) => step.current) || steps.some((step) => step.status === "completed");

    if (steps.length > 0 && !currentCount.is_draft_setup && !hasActiveStep) {
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

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        ...options,
        headers: {
            ...csrfHeaders(),
            ...(options.headers ?? {}),
        },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message ?? "The request could not be completed.");
        error.payload = data;
        throw error;
    }

    return data;
}

async function loadCountLines(page = 1) {
    const endpoint = countLinesSection.value.endpoints?.list;

    if (!endpoint) {
        return;
    }

    countLinesLoading.value = true;
    countLinesError.value = "";

    try {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(countLinesSection.value.pagination?.perPage ?? 10),
        });
        const data = await jsonRequest(`${endpoint}?${params.toString()}`, { method: "GET" });

        countLines.value = (data.data ?? []).map((line) => normalizeLine(line));
        countLinesMeta.value = data.meta ?? {};
    } catch (error) {
        countLinesError.value = error.payload?.message ?? "Unable to load materials.";
    } finally {
        countLinesLoading.value = false;
    }
}

async function addCountLine() {
    if (!addLineValue.value || !canAddLines.value) {
        return;
    }

    addLineError.value = "";

    try {
        const data = await jsonRequest(countLinesSection.value.endpoints?.create ?? "", {
            method: "POST",
            body: JSON.stringify({
                item_id: addLineValue.value,
            }),
        });

        countLines.value = [normalizeLine(data.line ?? {}), ...countLines.value].filter((line) => Boolean(line.id));
        countLinesMeta.value = {
            ...countLinesMeta.value,
            total: Number(countLinesMeta.value.total ?? 0) + 1,
        };
        if (data.section) {
            sections.value = {
                ...sections.value,
                countLines: data.section,
            };
        }
        addLineValue.value = "";
        showToast("Material added.");
    } catch (error) {
        addLineError.value = error.payload?.message ?? "Unable to add material line.";
    }
}

async function removeCountLine(line) {
    if (!line?.delete_url) {
        return;
    }

    try {
        const data = await jsonRequest(line.delete_url, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken.value,
            },
        });
        const deletedLineId = data.deleted_line_id ?? line.id;

        countLines.value = Array.isArray(data.lines)
            ? data.lines.map((entry) => normalizeLine(entry))
            : countLines.value.filter((entry) => Number(entry.id) !== Number(deletedLineId));
        countLinesMeta.value = {
            ...countLinesMeta.value,
            total: countLines.value.length,
        };
        if (data.section) {
            sections.value = {
                ...sections.value,
                countLines: data.section,
            };
        }
        showToast("Material removed.");
    } catch (error) {
        showToast(error.payload?.message ?? "Unable to remove material line.", "error");
    }
}

async function saveCountedQuantity(line) {
    if (!line?.update_url || !line.can_edit_counted_quantity || line._countedQuantitySaving) {
        return;
    }

    const nextValue = typeof line.counted_quantity_input === "string" ? line.counted_quantity_input : "";
    const previousValue = typeof line._lastSavedCountedQuantityInput === "string" ? line._lastSavedCountedQuantityInput : "";

    if (nextValue === previousValue) {
        return;
    }

    line._countedQuantitySaving = true;

    try {
        const data = await jsonRequest(line.update_url, {
            method: "PATCH",
            body: JSON.stringify({
                counted_quantity: nextValue,
            }),
        });
        const updatedLine = {
            ...normalizeLine(data.line ?? line),
            _countedQuantitySaved: true,
        };

        countLines.value = countLines.value.map((entry) => (
            Number(entry.id) === Number(updatedLine.id) ? updatedLine : entry
        ));
        setTimeout(() => {
            countLines.value = countLines.value.map((entry) => (
                Number(entry.id) === Number(updatedLine.id)
                    ? { ...entry, _countedQuantitySaved: false }
                    : entry
            ));
        }, 1000);
    } catch (error) {
        line.counted_quantity_input = previousValue;
        showToast(error.payload?.message ?? "Unable to update counted quantity.", "error");
    } finally {
        line._countedQuantitySaving = false;
    }
}

function countLinesHaveBlankQuantities() {
    return countLines.value.some((line) => (
        line.can_edit_counted_quantity
        && String(line.counted_quantity_input || "").trim() === ""
    ));
}

function hydrateCountResponse(data) {
    const responseCount = data.count ?? data;

    if (responseCount && Object.keys(responseCount).length > 0) {
        count.value = {
            ...count.value,
            ...responseCount,
        };
        currentStageTasks.value = sortTasks(responseCount.current_stage_tasks ?? currentStageTasks.value);
        details.counted_at_iso = responseCount.counted_at_iso ?? "";
        details.assigned_to_user_id = normalizeId(responseCount.assigned_to_user_id);
        details.notes = responseCount.notes ?? "";
        Object.assign(lastSavedDetails, { ...details });
    }

    if (data.workflow) {
        workflow.value = {
            ...workflow.value,
            ...data.workflow,
        };
    }

    if (Array.isArray(data.workflowProgressSteps)) {
        workflowProgressSteps.value = normalizeWorkflowSteps(data.workflowProgressSteps, count.value);
    }

    if (data.sections) {
        sections.value = {
            ...sections.value,
            ...data.sections,
        };
    }
}

function detailsPayload(field) {
    if (field === "notes") {
        return {
            notes: details.notes,
        };
    }

    if (field === "counted_at") {
        return {
            counted_at: details.counted_at_iso,
            notes: details.notes,
        };
    }

    return {
        counted_at: details.counted_at_iso,
        notes: details.notes,
        assigned_to_user_id: details.assigned_to_user_id === "" ? null : Number(details.assigned_to_user_id),
    };
}

async function saveDetails(field) {
    if (!count.value.update_url) {
        return;
    }

    if (field === "counted_at" && (!count.value.can_edit_counted_at || details.counted_at_iso === lastSavedDetails.counted_at_iso)) {
        return;
    }

    if (field === "assigned_to_user_id" && (!count.value.can_edit_assignment || details.assigned_to_user_id === lastSavedDetails.assigned_to_user_id)) {
        return;
    }

    if (field === "notes" && (!count.value.can_edit_notes || details.notes === lastSavedDetails.notes)) {
        return;
    }

    const previousDetails = { ...lastSavedDetails };
    savingDetails[field] = true;

    try {
        const data = await jsonRequest(count.value.update_url, {
            method: "PATCH",
            body: JSON.stringify(detailsPayload(field)),
        });

        hydrateCountResponse(data);
        showToast("Inventory count details updated.");
    } catch (error) {
        Object.assign(details, previousDetails);
        showToast(error.payload?.message ?? "Unable to update inventory count details.", "error");
    } finally {
        savingDetails[field] = false;
    }
}

async function performWorkflowAction(action) {
    if (!action || workflowLoading.value) {
        return;
    }

    if (action.requiresConfirmation && !window.confirm(action.description || "Continue?")) {
        return;
    }

    if (action.type === "advance" && count.value.workflow_stage_key === "counting" && countLinesHaveBlankQuantities()) {
        showRequiredCountedQuantityCue.value = true;
        showToast("Enter a counted quantity for every item before moving past Counting.", "error");
        return;
    }

    workflowMenuOpen.value = false;
    workflowLoading.value = true;
    workflowError.value = "";

    try {
        const data = await jsonRequest(action.endpoint, {
            method: action.method ?? "POST",
            headers: action.method === "DELETE"
                ? {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken.value,
                }
                : csrfHeaders(),
        });

        hydrateCountResponse(data);
        await loadCountLines(1);
        showRequiredCountedQuantityCue.value = false;
        showToast(action.type === "cancel" ? "Inventory count cancelled." : "Inventory count updated.");
    } catch (error) {
        workflowError.value = error.payload?.message ?? "Unable to update workflow.";
        showToast(workflowError.value, "error");
    } finally {
        workflowLoading.value = false;
    }
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

    try {
        const data = await jsonRequest(payload.value.taskCreate?.storeUrl ?? "/tasks", {
            method: "POST",
            body: JSON.stringify({
                title: taskForm.title,
                assigned_to_user_id: taskForm.assigned_to_user_id === "" ? null : Number(taskForm.assigned_to_user_id),
                due_date: taskForm.due_date || null,
                description: taskForm.description,
                workflow_domain_id: payload.value.taskCreate?.workflowDomainId ?? null,
                domain_record_id: count.value.id ?? null,
                workflow_stage_id: workflow.value.current_stage?.id ?? count.value.workflow_stage_id ?? null,
            }),
        });

        if (data.data?.id) {
            currentStageTasks.value = sortTasks([...currentStageTasks.value, data.data]);
        }

        taskOpen.value = false;
        showToast("Task created.");
    } catch (error) {
        taskErrors.value = error.payload?.errors ?? {};
        taskError.value = error.payload?.message ?? "Unable to create task.";
    } finally {
        taskSubmitting.value = false;
    }
}

async function completeTask(task) {
    if (!task?.id || !task?.complete_url || taskCompletingIds.value.includes(task.id)) {
        return;
    }

    taskCompletingIds.value = [...taskCompletingIds.value, task.id];

    try {
        const data = await jsonRequest(task.complete_url, {
            method: "PATCH",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken.value,
            },
        });

        if (data.data?.id) {
            currentStageTasks.value = sortTasks(currentStageTasks.value.map((entry) => (
                Number(entry.id) === Number(data.data.id) ? data.data : entry
            )));
        }
        showToast("Task completed.");
    } catch (error) {
        showToast(error.payload?.message ?? "Unable to complete task.", "error");
    } finally {
        taskCompletingIds.value = taskCompletingIds.value.filter((taskId) => Number(taskId) !== Number(task.id));
    }
}

async function submitNote() {
    if (!notesFeed.value.store_url || noteSaving.value) {
        return;
    }

    noteSaving.value = true;
    noteError.value = "";

    try {
        const data = await jsonRequest(notesFeed.value.store_url, {
            method: "POST",
            body: JSON.stringify({
                body: noteBody.value,
            }),
        });

        if (data.note?.id) {
            notes.value = [...notes.value, data.note];
        }

        noteBody.value = "";
    } catch (error) {
        const bodyErrors = error.payload?.errors?.body ?? [];
        noteError.value = bodyErrors[0] || error.payload?.message || "Unable to add note.";
    } finally {
        noteSaving.value = false;
    }
}

function toggleNoteSort() {
    noteSortDirection.value = noteSortDirection.value === "asc" ? "desc" : "asc";
}

function handleDocumentClick(event) {
    if (!event.target.closest("[data-workflow-action-button]")) {
        workflowMenuOpen.value = false;
    }
}

onMounted(async () => {
    document.addEventListener("click", handleDocumentClick);
    await loadCountLines();
    detailsAutosaveReady.value = true;
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <Head :title="pageTitle" />

    <AuthShell :shell="shell" :title="pageTitle" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <UiToast v-model:visible="toastVisible" :type="toastType" :message="toastMessage" />

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="pageTitle"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #titleSuffix>
                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        {{ statusLabel }}
                    </span>
                </template>
                <template #actions>
                    <div v-if="workflowActions.length > 0" class="relative" data-workflow-action-button>
                        <div v-if="workflowLoading" class="absolute inset-0 z-10 rounded-lg bg-white/60" />
                        <button type="button" class="inline-flex cursor-pointer items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="workflowLoading" @click.stop="workflowMenuOpen = !workflowMenuOpen">
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
                            <button v-for="action in workflowActions" :key="action.id || action.type || action.label" type="button" class="flex w-full cursor-pointer items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="workflowLoading" @click.stop="performWorkflowAction(action)">
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
                <div class="pt-0 pb-12 sm:pt-6">
                    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <nav v-if="workflowProgressSteps.length > 0" class="-mx-1 w-auto sm:mx-0 sm:w-full" aria-label="Progress">
                            <div class="flex overflow-hidden border-y border-gray-300 bg-white md:hidden" role="tablist" aria-label="Workflow stages">
                                <button v-for="(step, index) in workflowProgressSteps" :key="`${step.label}-${index}`" type="button" role="tab" class="relative min-h-16 cursor-pointer overflow-hidden bg-white py-2 pl-3 pr-6 transition-[flex-basis,flex-grow] duration-300 ease-out will-change-[flex-basis]" :class="activeWorkflowStep === index ? 'basis-0 grow' : 'basis-16 grow-0'" :aria-selected="activeWorkflowStep === index ? 'true' : 'false'" @click="activeWorkflowStep = index">
                                    <span class="flex h-full items-center" :class="activeWorkflowStep === index ? 'justify-start gap-3' : 'justify-center'">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2" :class="workflowCircleClass(step)">
                                            <svg v-if="step.status === 'completed'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" />
                                            </svg>
                                            <span v-else class="text-sm font-semibold">{{ step.number }}</span>
                                        </span>
                                        <span class="min-w-0 truncate text-left text-sm font-medium transition-[max-width,opacity,transform] duration-300 ease-out" :class="[workflowLabelClass(step), activeWorkflowStep === index ? 'max-w-48 translate-x-0 opacity-100' : 'max-w-0 -translate-x-1 opacity-0']">{{ step.label }}</span>
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

                        <section v-if="count.show_details_section" class="-mx-1 !-mt-px border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200">
                            <div class="bg-blue-50 px-3 py-4 sm:px-6 sm:py-5">
                                <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Details</h3>
                                <p class="mt-0.5 truncate text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Update count metadata separately from material lines and workflow tasks.</p>
                            </div>
                            <div class="border-t border-gray-100 bg-white px-3 py-3 sm:px-6 sm:py-5">
                                <div class="grid grid-cols-2 gap-2">
                                    <label v-if="count.can_view_counted_at" class="space-y-1">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Count Date</span>
                                        <input v-model="details.counted_at_iso" type="date" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100" :disabled="!count.can_edit_counted_at || savingDetails.counted_at" @change="saveDetails('counted_at')" />
                                    </label>
                                    <label v-if="count.can_view_assignment" class="space-y-1">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Assigned To</span>
                                        <select v-model="details.assigned_to_user_id" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100" :disabled="!count.can_edit_assignment || savingDetails.assigned_to_user_id">
                                            <option value="">Unassigned</option>
                                            <option v-for="option in count.assignee_options ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                        </section>

                        <section class="-mx-1 !-mt-px overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200">
                            <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">{{ countLinesSection.title || "Materials" }}</h3>
                                    <p class="mt-0.5 truncate text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">{{ countLinesSection.description || "Manage counted materials for this inventory count." }}</p>
                                </div>
                            </div>
                            <div class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5">
                                <div v-if="canAddLines" class="mb-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <select v-model="addLineValue" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                        <option value="">{{ countLinesSection.addRow?.placeholder || "Search materials" }}</option>
                                        <option v-for="option in addLineOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <button type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900" aria-label="Add material" @click="addCountLine">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    </button>
                                    <p v-if="addLineError" class="text-xs text-red-600 sm:col-span-2">{{ addLineError }}</p>
                                </div>
                                <p v-if="countLinesError" class="mb-3 text-sm text-red-600">{{ countLinesError }}</p>
                                <p v-if="countLinesLoading && countLines.length === 0" class="text-sm text-gray-500">Loading...</p>
                                <div v-else-if="countLines.length === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">{{ countLinesSection.emptyState || "No count lines added yet." }}</div>
                                <div v-else class="-mx-3 space-y-0 border-t border-gray-300 sm:mx-0 sm:space-y-3 sm:border-t-0">
                                    <article v-for="line in countLines" :key="line.id" class="relative border-b border-gray-300 bg-white px-3 py-3 sm:rounded-xl sm:border sm:border-gray-200 sm:px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-gray-900">{{ line.item_display }}</p>
                                            </div>
                                            <div v-if="line.can_edit_counted_quantity" class="w-28 shrink-0">
                                                <label class="sr-only" :for="`counted-quantity-${line.id}`">Counted quantity</label>
                                                <div class="flex items-center gap-1">
                                                    <input :id="`counted-quantity-${line.id}`" v-model="line.counted_quantity_input" type="text" class="block w-full rounded-lg border px-2 py-1.5 text-right text-sm font-semibold text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-100" :class="showRequiredCountedQuantityCue && String(line.counted_quantity_input || '').trim() === '' ? 'border-red-400' : 'border-gray-300'" :disabled="line._countedQuantitySaving" @change="saveCountedQuantity(line)" @blur="saveCountedQuantity(line)" />
                                                    <svg v-if="line._countedQuantitySaved" class="h-4 w-4 shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div v-else-if="line.shows_counted_quantity" class="shrink-0 text-right text-sm font-semibold text-gray-900">
                                                <span class="text-gray-500">QTY: </span>{{ line.counted_quantity_display || "-" }}
                                            </div>
                                            <button v-if="(countLinesSection.actions ?? []).length > 0" type="button" class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-red-600 transition hover:bg-red-50" aria-label="Remove material line" @click="removeCountLine(line)">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </section>

                        <section class="-mx-1 !-mt-px border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200">
                            <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Tasks</h3>
                                    <p class="mt-0.5 truncate text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Complete required workflow tasks before moving the inventory count forward.</p>
                                </div>
                                <button type="button" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8" aria-label="Create task" @click="openTaskCreate">
                                    <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                            <div class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5">
                                <div v-if="currentStageTasks.length === 0" class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">No tasks for the current stage.</div>
                                <div v-else class="space-y-0 sm:space-y-3">
                                    <article v-for="task in currentStageTasks" :key="task.id" class="-mx-3 rounded-none border-y border-gray-200 bg-gray-50 px-3 py-2 sm:mx-0 sm:rounded-xl sm:border sm:border-gray-100 sm:p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="min-w-0 flex-1 space-y-1.5">
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <p class="truncate text-sm font-semibold text-gray-900">{{ task.title || "Task" }}</p>
                                                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="task.is_completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ task.status || "open" }}</span>
                                                </div>
                                                <div class="flex min-w-0 items-center gap-3 text-xs text-gray-600 sm:text-sm">
                                                    <p v-if="task.assigned_to_display" class="truncate text-gray-700">{{ task.assigned_to_display }}</p>
                                                    <p v-if="task.due_date" class="shrink-0 text-gray-600">{{ task.due_date }}</p>
                                                </div>
                                            </div>
                                            <button v-if="task.can_complete && !task.is_completed && task.complete_url" type="button" class="inline-flex cursor-pointer items-center rounded-md border border-slate-300 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 sm:px-3 sm:py-1.5 sm:text-xs sm:tracking-widest" :disabled="taskCompletingIds.includes(task.id)" @click="completeTask(task)">Complete</button>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </section>

                        <section class="-mx-1 !-mt-px border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:rounded-2xl sm:border-gray-200">
                            <div class="bg-blue-50 px-3 py-4 sm:px-6 sm:py-5">
                                <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Notes</h3>
                                <p class="mt-0.5 truncate text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Timeline of internal comments for this resource.</p>
                            </div>
                            <div class="border-t border-gray-100 bg-white px-3 py-3 sm:px-6 sm:py-5">
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
                                <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">{{ notesFeed.empty_state || "No notes yet." }}</div>
                                <form class="mt-6 space-y-3" @submit.prevent="submitNote">
                                    <textarea v-model="noteBody" rows="3" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100" placeholder="Add an internal comment" :disabled="noteSaving" />
                                    <p v-if="noteError" class="text-sm text-red-600">{{ noteError }}</p>
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center text-gray-600 transition hover:text-gray-900" aria-label="Toggle comment sort order" @click="toggleNoteSort">
                                            <svg v-if="noteSortDirection === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                            </svg>
                                            <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                            </svg>
                                        </button>
                                        <button type="submit" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="noteSaving">Comment</button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <BaseDrawer :open="taskOpen" labelled-by="inventory-count-task-title" @close="taskOpen = false">
                <form class="flex h-full flex-col bg-white" @submit.prevent="submitTask">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 id="inventory-count-task-title" class="text-sm font-semibold">Create Task</h2>
                    </div>
                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                        <label class="block text-sm font-medium text-gray-700">
                            Title
                            <input v-model="taskForm.title" type="text" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <span v-if="taskErrors.title?.[0]" class="mt-1 block text-sm text-red-600">{{ taskErrors.title[0] }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Assigned To
                            <select v-model="taskForm.assigned_to_user_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select user</option>
                                <option v-for="user in payload.taskCreate?.users ?? []" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                            </select>
                            <span v-if="taskErrors.assigned_to_user_id?.[0]" class="mt-1 block text-sm text-red-600">{{ taskErrors.assigned_to_user_id[0] }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Due Date
                            <input v-model="taskForm.due_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @click="$event.target.showPicker?.()" />
                            <span v-if="taskErrors.due_date?.[0]" class="mt-1 block text-sm text-red-600">{{ taskErrors.due_date[0] }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Description
                            <textarea v-model="taskForm.description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <span v-if="taskErrors.description?.[0]" class="mt-1 block text-sm text-red-600">{{ taskErrors.description[0] }}</span>
                        </label>
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
