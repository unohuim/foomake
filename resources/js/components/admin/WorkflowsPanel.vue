<script setup>
import { computed, reactive, ref } from "vue";

import UiToast from "../UiToast.vue";

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
});

const domains = ref([...(props.payload.domains || [])]);
const stages = ref([...(props.payload.stages || [])]);
const taskTemplates = ref([...(props.payload.taskTemplates || [])]);
const users = ref([...(props.payload.users || [])]);
const stagesOpen = ref(true);
const tasksOpen = ref(true);
const showInactive = ref(Boolean(props.payload.showInactive));
const stageFormMode = ref("create");
const editingStageId = ref(null);
const taskTemplateFormMode = ref("create");
const editingTaskTemplateId = ref(null);
const toast = reactive({
    visible: false,
    message: "",
    type: "success",
});
const stageForm = reactive(emptyStageForm());
const taskTemplateForm = reactive(emptyTaskTemplateForm());

const filteredStages = computed(() => stages.value.filter((stage) => showInactive.value || stage.is_active));
const filteredTaskTemplates = computed(() => taskTemplates.value.filter((taskTemplate) => showInactive.value || taskTemplate.is_active));
const stageOptionsForTaskForm = computed(() => {
    if (!taskTemplateForm.workflow_domain_id) {
        return [];
    }

    return stages.value.filter((stage) => (
        String(stage.workflow_domain_id) === String(taskTemplateForm.workflow_domain_id)
        && (showInactive.value || stage.is_active)
    ));
});
const eligibleUsersForTaskForm = computed(() => {
    if (!taskTemplateForm.workflow_domain_id) {
        return [];
    }

    return users.value.filter((user) => (
        Array.isArray(user.eligible_workflow_domain_ids)
        && user.eligible_workflow_domain_ids.map(String).includes(String(taskTemplateForm.workflow_domain_id))
    ));
});
const editingCoreStage = computed(() => {
    if (stageFormMode.value !== "edit" || !editingStageId.value) {
        return false;
    }

    return Boolean(stages.value.find((stage) => stage.id === editingStageId.value)?.is_core);
});
const statusOptionsForStageForm = computed(() => (
    props.payload.statusOptionsByDomainId?.[String(stageForm.workflow_domain_id)] || []
));

function emptyStageForm() {
    return {
        workflow_domain_id: "",
        name: "",
        action_verb: "",
        status_complete_label: "",
        completion_mode: "manual",
        description: "",
        sort_order: "",
        is_active: true,
        is_inventory_effect_stage: false,
    };
}

function emptyTaskTemplateForm() {
    return {
        workflow_domain_id: "",
        workflow_stage_id: "",
        title: "",
        description: "",
        sort_order: 10,
        default_assignee_user_id: "",
        is_active: true,
    };
}

function showToast(type, message) {
    toast.type = type;
    toast.message = message;
    toast.visible = true;
}

function resetStageForm() {
    stageFormMode.value = "create";
    editingStageId.value = null;
    Object.assign(stageForm, emptyStageForm());
}

function openStageEdit(stage) {
    stageFormMode.value = "edit";
    editingStageId.value = stage.id;
    Object.assign(stageForm, {
        workflow_domain_id: String(stage.workflow_domain_id || ""),
        name: stage.name || "",
        action_verb: stage.action_verb || "",
        status_complete_label: stage.status_complete_label || "",
        completion_mode: stage.completion_mode || "manual",
        description: stage.description || "",
        sort_order: Number(stage.sort_order || 10),
        is_active: Boolean(stage.is_active),
        is_inventory_effect_stage: Boolean(stage.is_inventory_effect_stage),
    });
}

function handleStageDomainChanged() {
    if (!statusOptionsForStageForm.value.includes(stageForm.status_complete_label)) {
        stageForm.status_complete_label = "";
    }
}

function stagePayload(stage) {
    return {
        workflow_domain_id: stage.workflow_domain_id === "" ? null : Number(stage.workflow_domain_id),
        name: stage.name,
        action_verb: stage.action_verb,
        status_complete_label: stage.status_complete_label,
        completion_mode: stage.completion_mode,
        description: stage.description,
        sort_order: Number(stage.sort_order || 0),
        is_active: Boolean(stage.is_active),
        is_inventory_effect_stage: Boolean(stage.is_inventory_effect_stage),
    };
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": props.payload.csrfToken,
            ...(options.headers || {}),
        },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || "The request could not be completed.");
        error.payload = data;
        throw error;
    }

    return data;
}

function sortStages() {
    stages.value = [...stages.value].sort((left, right) => {
        if (Number(left.workflow_domain_id) !== Number(right.workflow_domain_id)) {
            return Number(left.workflow_domain_id) - Number(right.workflow_domain_id);
        }

        if (Number(left.sort_order) !== Number(right.sort_order)) {
            return Number(left.sort_order) - Number(right.sort_order);
        }

        return Number(left.id) - Number(right.id);
    });
}

function upsertStage(stage) {
    const index = stages.value.findIndex((entry) => entry.id === stage.id);

    if (index === -1) {
        stages.value.push(stage);
    } else {
        stages.value.splice(index, 1, stage);
    }

    sortStages();
}

async function submitStageForm() {
    const isCreate = stageFormMode.value === "create";
    const url = isCreate ? props.payload.stageStoreUrl : `${props.payload.stageUpdateUrlBase}/${editingStageId.value}`;

    try {
        const data = await jsonRequest(url, {
            method: isCreate ? "POST" : "PATCH",
            body: JSON.stringify(stagePayload(stageForm)),
        });

        upsertStage(data.data || {});
        showToast("success", isCreate ? "Workflow stage created." : "Workflow stage updated.");
        resetStageForm();
    } catch (error) {
        showToast("error", error.message || "Unable to save workflow stage.");
    }
}

async function toggleStage(stage) {
    try {
        const data = await jsonRequest(`${props.payload.stageUpdateUrlBase}/${stage.id}`, {
            method: "PATCH",
            body: JSON.stringify(stagePayload({
                ...stage,
                is_active: !stage.is_active,
            })),
        });

        upsertStage(data.data || {});
        showToast("success", "Workflow stage updated.");
    } catch (error) {
        showToast("error", error.message || "Unable to update workflow stage.");
    }
}

async function deleteStage(stage) {
    if (!stage || stage.is_core || !props.payload.stageDeleteUrlBase) {
        return;
    }

    try {
        await jsonRequest(`${props.payload.stageDeleteUrlBase}/${stage.id}`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
            },
        });
        stages.value = stages.value.filter((entry) => entry.id !== stage.id);
        showToast("success", "Workflow stage deleted.");
    } catch (error) {
        showToast("error", error.message || "Unable to delete workflow stage.");
    }
}

function resetTaskTemplateForm() {
    taskTemplateFormMode.value = "create";
    editingTaskTemplateId.value = null;
    Object.assign(taskTemplateForm, emptyTaskTemplateForm());
}

function openTaskTemplateEdit(taskTemplate) {
    taskTemplateFormMode.value = "edit";
    editingTaskTemplateId.value = taskTemplate.id;
    Object.assign(taskTemplateForm, {
        workflow_domain_id: String(taskTemplate.workflow_domain_id || ""),
        workflow_stage_id: String(taskTemplate.workflow_stage_id || ""),
        title: taskTemplate.title || "",
        description: taskTemplate.description || "",
        sort_order: Number(taskTemplate.sort_order || 10),
        default_assignee_user_id: taskTemplate.default_assignee_user_id ? String(taskTemplate.default_assignee_user_id) : "",
        is_active: Boolean(taskTemplate.is_active),
    });
}

function taskTemplatePayload(taskTemplate) {
    return {
        workflow_domain_id: taskTemplate.workflow_domain_id === "" ? null : Number(taskTemplate.workflow_domain_id),
        workflow_stage_id: taskTemplate.workflow_stage_id === "" ? null : Number(taskTemplate.workflow_stage_id),
        title: taskTemplate.title,
        description: taskTemplate.description,
        sort_order: Number(taskTemplate.sort_order || 0),
        default_assignee_user_id: taskTemplate.default_assignee_user_id === "" ? null : Number(taskTemplate.default_assignee_user_id),
        is_active: Boolean(taskTemplate.is_active),
    };
}

function upsertTaskTemplate(taskTemplate) {
    const index = taskTemplates.value.findIndex((entry) => entry.id === taskTemplate.id);

    if (index === -1) {
        taskTemplates.value.push(taskTemplate);
        return;
    }

    taskTemplates.value.splice(index, 1, taskTemplate);
}

async function submitTaskTemplateForm() {
    const isCreate = taskTemplateFormMode.value === "create";
    const url = isCreate ? props.payload.taskTemplateStoreUrl : `${props.payload.taskTemplateUpdateUrlBase}/${editingTaskTemplateId.value}`;

    try {
        const data = await jsonRequest(url, {
            method: isCreate ? "POST" : "PATCH",
            body: JSON.stringify(taskTemplatePayload(taskTemplateForm)),
        });

        upsertTaskTemplate(data.data || {});
        showToast("success", isCreate ? "Workflow task template created." : "Workflow task template updated.");
        resetTaskTemplateForm();
    } catch (error) {
        showToast("error", error.message || "Unable to save workflow task template.");
    }
}

async function toggleTaskTemplate(taskTemplate) {
    try {
        const data = await jsonRequest(`${props.payload.taskTemplateUpdateUrlBase}/${taskTemplate.id}`, {
            method: "PATCH",
            body: JSON.stringify(taskTemplatePayload({
                ...taskTemplate,
                is_active: !taskTemplate.is_active,
            })),
        });

        upsertTaskTemplate(data.data || {});
        showToast("success", "Workflow task template updated.");
    } catch (error) {
        showToast("error", error.message || "Unable to update workflow task template.");
    }
}

async function reorderTaskTemplates() {
    const stageId = taskTemplateForm.workflow_stage_id || filteredTaskTemplates.value[0]?.workflow_stage_id;

    if (!stageId) {
        return;
    }

    const orderedIds = filteredTaskTemplates.value
        .filter((taskTemplate) => String(taskTemplate.workflow_stage_id) === String(stageId) && taskTemplate.is_active)
        .sort((left, right) => Number(left.sort_order) - Number(right.sort_order))
        .map((taskTemplate) => taskTemplate.id);

    if (orderedIds.length === 0) {
        return;
    }

    try {
        await jsonRequest(props.payload.taskTemplateReorderUrl, {
            method: "POST",
            body: JSON.stringify({
                workflow_stage_id: stageId,
                ordered_ids: orderedIds,
            }),
        });
        showToast("success", "Workflow task templates reordered.");
    } catch (error) {
        showToast("error", error.message || "Unable to reorder workflow task templates.");
    }
}
</script>

<template>
    <UiToast v-model:visible="toast.visible" :message="toast.message" :type="toast.type" />

    <div class="space-y-6">
                <div class="border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Workflow configuration</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage tenant-scoped operational stages and task templates.</p>
                        </div>

                        <label class="inline-flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-700">
                            <input
                                v-model="showInactive"
                                type="checkbox"
                                class="cursor-pointer rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500"
                            >
                            <span>Show inactive</span>
                        </label>
                    </div>
                </div>

                <section class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between px-6 py-5 text-left"
                        @click="stagesOpen = !stagesOpen"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Stages</h3>
                            <p class="mt-1 text-sm text-slate-600">Create, edit, deactivate, and reactivate operational workflow stages.</p>
                        </div>
                        <span class="text-sm font-semibold text-slate-500">{{ stagesOpen ? "Hide" : "Show" }}</span>
                    </button>

                    <div v-show="stagesOpen" class="border-t border-slate-200 px-6 py-6">
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Configured stages</h4>
                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50"
                                        @click="resetStageForm"
                                    >
                                        Create stage
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="stage in filteredStages" :key="stage.id" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <p class="text-sm font-semibold text-slate-900">{{ stage.name }}</p>
                                                    <span
                                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                                        :class="stage.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                                    >
                                                        {{ stage.is_active ? "Active" : "Inactive" }}
                                                    </span>
                                                    <span
                                                        v-if="stage.is_inventory_effect_stage"
                                                        class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-sky-700"
                                                    >
                                                        Inventory Effect
                                                    </span>
                                                    <span
                                                        v-if="stage.is_core"
                                                        class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700"
                                                    >
                                                        Core
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-500">{{ stage.workflow_domain_key }}</p>
                                                <p class="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                                    <span>Action:</span>
                                                    <span> {{ stage.action_verb }}</span>
                                                    <span> / Complete:</span>
                                                    <span> {{ stage.status_complete_label }}</span>
                                                </p>
                                                <p class="mt-2 text-sm text-slate-600">{{ stage.description || "No description." }}</p>
                                            </div>

                                            <div class="flex flex-wrap gap-3 text-sm">
                                                <button type="button" class="cursor-pointer text-sky-700 hover:text-sky-600" @click="openStageEdit(stage)">Edit</button>
                                                <button
                                                    type="button"
                                                    class="cursor-pointer text-slate-700 hover:text-slate-600 disabled:cursor-not-allowed disabled:text-slate-400"
                                                    :disabled="stage.is_core"
                                                    @click="toggleStage(stage)"
                                                >
                                                    {{ stage.is_active ? "Deactivate" : "Reactivate" }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="cursor-pointer text-rose-700 hover:text-rose-600 disabled:cursor-not-allowed disabled:text-slate-400"
                                                    :disabled="stage.is_core"
                                                    @click="deleteStage(stage)"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5" @submit.prevent="submitStageForm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="text-base font-semibold text-slate-900">{{ stageFormMode === "create" ? "Create stage" : "Edit stage" }}</h4>
                                        <p class="mt-1 text-sm text-slate-600">Domain-backed operational stages only.</p>
                                    </div>
                                    <button type="button" class="cursor-pointer text-sm text-slate-500 hover:text-slate-700" @click="resetStageForm">Reset</button>
                                </div>

                                <div class="mt-5 space-y-4">
                                    <label class="block text-sm font-medium text-slate-700">
                                        Domain
                                        <select v-model="stageForm.workflow_domain_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage" @change="handleStageDomainChanged">
                                            <option value="">Select domain</option>
                                            <option v-for="domain in domains" :key="domain.id" :value="String(domain.id)">{{ domain.name }}</option>
                                        </select>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Name
                                        <input v-model="stageForm.name" type="text" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage">
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Action verb
                                        <input v-model="stageForm.action_verb" type="text" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage">
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Status complete label
                                        <select v-model="stageForm.status_complete_label" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage">
                                            <option value="">Select status</option>
                                            <option v-for="status in statusOptionsForStageForm" :key="status" :value="status">{{ status }}</option>
                                        </select>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Completion mode
                                        <select v-model="stageForm.completion_mode" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage">
                                            <option value="manual">Manual</option>
                                            <option value="automatic">Automatic</option>
                                        </select>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Description
                                        <textarea v-model="stageForm.description" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" rows="3"></textarea>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Sort order
                                        <input v-model="stageForm.sort_order" type="number" step="1" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage">
                                    </label>

                                    <label v-if="stageFormMode === 'edit'" class="inline-flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-700">
                                        <input v-model="stageForm.is_active" type="checkbox" class="cursor-pointer rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-500" :disabled="editingCoreStage">
                                        <span>Active</span>
                                    </label>

                                    <label class="inline-flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-700">
                                        <input v-model="stageForm.is_inventory_effect_stage" type="checkbox" class="cursor-pointer rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                                        <span>Inventory effect stage</span>
                                    </label>
                                </div>

                                <div class="mt-5 flex items-center justify-end">
                                    <button type="submit" class="cursor-pointer rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-slate-800">
                                        Save stage
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center justify-between px-6 py-5 text-left"
                        @click="tasksOpen = !tasksOpen"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Tasks</h3>
                            <p class="mt-1 text-sm text-slate-600">Configure stage-scoped task templates and default assignees.</p>
                        </div>
                        <span class="text-sm font-semibold text-slate-500">{{ tasksOpen ? "Hide" : "Show" }}</span>
                    </button>

                    <div v-show="tasksOpen" class="border-t border-slate-200 px-6 py-6">
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Task templates</h4>
                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50"
                                        @click="resetTaskTemplateForm"
                                    >
                                        Create task
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="taskTemplate in filteredTaskTemplates" :key="taskTemplate.id" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <p class="text-sm font-semibold text-slate-900">{{ taskTemplate.title }}</p>
                                                    <span
                                                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                                                        :class="taskTemplate.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                                    >
                                                        {{ taskTemplate.is_active ? "Active" : "Inactive" }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-500">
                                                    <span>{{ taskTemplate.workflow_domain_key }}</span>
                                                    <span> / </span>
                                                    <span>{{ taskTemplate.workflow_stage_key }}</span>
                                                </p>
                                                <p class="mt-2 text-sm text-slate-600">{{ taskTemplate.description || "No description." }}</p>
                                                <p class="mt-2 text-xs text-slate-500">
                                                    {{ taskTemplate.default_assignee_name ? `Default assignee: ${taskTemplate.default_assignee_name}` : "Default assignee: first tenant user" }}
                                                </p>
                                            </div>

                                            <div class="flex flex-wrap gap-3 text-sm">
                                                <button type="button" class="cursor-pointer text-sky-700 hover:text-sky-600" @click="openTaskTemplateEdit(taskTemplate)">Edit</button>
                                                <button type="button" class="cursor-pointer text-slate-700 hover:text-slate-600" @click="toggleTaskTemplate(taskTemplate)">
                                                    {{ taskTemplate.is_active ? "Deactivate" : "Reactivate" }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5" @submit.prevent="submitTaskTemplateForm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="text-base font-semibold text-slate-900">{{ taskTemplateFormMode === "create" ? "Create task template" : "Edit task template" }}</h4>
                                        <p class="mt-1 text-sm text-slate-600">Generated tasks always resolve to a user.</p>
                                    </div>
                                    <button type="button" class="cursor-pointer text-sm text-slate-500 hover:text-slate-700" @click="resetTaskTemplateForm">Reset</button>
                                </div>

                                <div class="mt-5 space-y-4">
                                    <label class="block text-sm font-medium text-slate-700">
                                        Domain
                                        <select v-model="taskTemplateForm.workflow_domain_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" @change="taskTemplateForm.default_assignee_user_id = ''">
                                            <option value="">Select domain</option>
                                            <option v-for="domain in domains" :key="domain.id" :value="String(domain.id)">{{ domain.name }}</option>
                                        </select>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Stage
                                        <select v-model="taskTemplateForm.workflow_stage_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                            <option value="">Select stage</option>
                                            <option v-for="stage in stageOptionsForTaskForm" :key="stage.id" :value="String(stage.id)">{{ stage.name }}</option>
                                        </select>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Title
                                        <input v-model="taskTemplateForm.title" type="text" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Description
                                        <textarea v-model="taskTemplateForm.description" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" rows="3"></textarea>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Default Assignee
                                        <select v-model="taskTemplateForm.default_assignee_user_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                            <option value="">First eligible user</option>
                                            <option v-for="user in eligibleUsersForTaskForm" :key="user.id" :value="String(user.id)">{{ `${user.name} (${user.email})` }}</option>
                                        </select>
                                    </label>

                                    <label class="block text-sm font-medium text-slate-700">
                                        Sort order
                                        <input v-model="taskTemplateForm.sort_order" type="number" step="1" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                    </label>

                                    <label v-if="taskTemplateFormMode === 'edit'" class="inline-flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-700">
                                        <input v-model="taskTemplateForm.is_active" type="checkbox" class="cursor-pointer rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                                        <span>Active</span>
                                    </label>
                                </div>

                                <div class="mt-5 flex items-center justify-between">
                                    <button type="button" class="cursor-pointer rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-100" @click="reorderTaskTemplates">
                                        Reorder tasks
                                    </button>

                                    <button type="submit" class="cursor-pointer rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-slate-800">
                                        Save task
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
    </div>
</template>
