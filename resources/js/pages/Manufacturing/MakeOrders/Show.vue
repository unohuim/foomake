<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";

import BaseDrawer from "../../../components/BaseDrawer.vue";
import ResourceDetailHeaderBreadcrumb from "../../../components/ResourceDetailHeaderBreadcrumb.vue";
import ResourceDetailSection from "../../../components/ResourceDetailSection.vue";
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

const SCALE = 6;
const SCALE_FACTOR = 10n ** 6n;

const payload = ref({ ...props.payload });
const makeOrder = ref({ ...(props.payload.makeOrder ?? {}) });
const workflow = ref(normalizeWorkflow(props.payload.workflow ?? {}));
const workflowProgressSteps = ref(normalizeWorkflowSteps(props.payload.workflowProgressSteps ?? [], makeOrder.value));
const ingredients = ref(normalizeIngredients(props.payload.ingredients ?? {}));
const selectedIngredientItemId = ref("");
const ingredientsSaving = ref(false);
const ingredientSavedState = ref({});
const workflowMenuOpen = ref(false);
const workflowTransitionSaving = ref(false);
const workflowError = ref("");
const workflowDueDateSaving = ref(false);
const workflowAssignmentSaving = ref(false);
const lastSavedWorkflowDueDate = ref(workflow.value.due_date || "");
const lastSavedWorkflowOwnerId = ref(normalizedId(workflow.value.made_by_user_id));
const workflowAutosaveReady = ref(false);
const makeOrderDetailSaving = ref(false);
const activeWorkflowStep = ref(0);
const toastVisible = ref(false);
const toastType = ref("success");
const toastMessage = ref("");
const toastTimer = ref(null);
const taskOpen = ref(false);
const taskSubmitting = ref(false);
const taskError = ref("");
const taskErrors = ref({});
const taskCompletingIds = ref([]);
const taskForm = reactive(emptyTaskForm());
const notes = ref([...(props.payload.notesFeed?.notes ?? [])]);
const noteBody = ref("");
const noteSaving = ref(false);
const noteError = ref("");
const noteSortDirection = ref("asc");

const csrfToken = computed(() => payload.value.csrfToken ?? payload.value.csrf_token ?? "");
const pageTitle = computed(() => makeOrder.value.output_item_name || props.title);
const subtitle = computed(() => `MO-${makeOrder.value.id || ""}`);
const statusLabel = computed(() => workflow.value.display_label || workflow.value.status_label || makeOrder.value.workflow_state || "DRAFT");
const workflowActions = computed(() => sortedWorkflowActions(workflow.value));
const workflowTriggerLabel = computed(() => workflow.value.display_label
    || workflow.value.status_label
    || workflow.value.currentLabel
    || workflow.value.current_stage_label
    || statusLabel.value);
const workflowTerminalLabel = computed(() => {
    const label = String(makeOrder.value.status || workflow.value.status || "").toUpperCase();

    return ["MADE", "CANCELLED"].includes(label) ? statusLabel.value : "";
});
const breadcrumbItems = computed(() => [
    {
        label: "Make Orders",
        url: props.indexUrl,
        current: false,
    },
    {
        label: props.title,
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

watch(workflowProgressSteps, (steps) => {
    activeWorkflowStep.value = activeWorkflowStepIndex(steps);
}, { immediate: true });

watch(() => workflow.value.made_by_user_id, async (value) => {
    if (!workflowAutosaveReady.value || workflowAssignmentSaving.value) {
        return;
    }

    if (normalizedId(value) === lastSavedWorkflowOwnerId.value) {
        return;
    }

    await saveWorkflowAssignment();
});

function emptyTaskForm() {
    return {
        title: "",
        assigned_to_user_id: "",
        due_date: "",
        description: "",
    };
}

function normalizeWorkflow(value) {
    return {
        default_open: Boolean(value.default_open),
        transition_url: value.transition_url || "",
        can_move_stage: Boolean(value.can_move_stage),
        due_date_update_url: value.due_date_update_url || "",
        can_edit_due_date: Boolean(value.can_edit_due_date),
        assignment_update_url: value.assignment_update_url || "",
        can_edit_assignment: Boolean(value.can_edit_assignment),
        current_stage: value.current_stage ?? null,
        current_stage_label: value.current_stage_label || "",
        actions: Array.isArray(value.actions) ? value.actions : [],
        next_stage_action: value.next_stage_action ?? null,
        available_stages: Array.isArray(value.available_stages) ? value.available_stages : [],
        assignee_options: Array.isArray(value.assignee_options) ? value.assignee_options : [],
        due_date: value.due_date || "",
        made_by_user_id: value.made_by_user_id ?? "",
        owner_user_name: value.owner_user_name || "",
        tasked_by_user_id: value.tasked_by_user_id ?? null,
        tasked_by_user_name: value.tasked_by_user_name || "",
        current_stage_tasks: Array.isArray(value.current_stage_tasks) ? value.current_stage_tasks : [],
        status: value.status || "",
        status_label: value.status_label || "",
        display_label: value.display_label || "",
        currentLabel: value.currentLabel || "",
        header_menu: value.header_menu ?? {},
    };
}

function normalizeIngredients(value) {
    return {
        can_edit: Boolean(value.can_edit),
        item_options: Array.isArray(value.item_options) ? value.item_options : [],
        lines: Array.isArray(value.lines) ? value.lines : [],
        store_url: value.store_url || "",
        update_url_template: value.update_url_template || "",
        remove_url_template: value.remove_url_template || "",
    };
}

function csrfHeaders() {
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken.value,
    };
}

function normalizedId(value) {
    return value === "" || value === null || value === undefined ? "" : String(value);
}

function normalizePrecision(value, fallback = SCALE) {
    const precision = Number.parseInt(String(value ?? fallback), 10);

    return Number.isNaN(precision) ? fallback : Math.max(0, Math.min(SCALE, precision));
}

function canonicalizeScaleSix(value) {
    const normalized = String(value ?? "").trim();

    if (!/^\d+(?:\.\d+)?$/.test(normalized)) {
        return null;
    }

    const [wholePart, decimalPart = ""] = normalized.split(".", 2);

    return `${wholePart}.${decimalPart.padEnd(SCALE, "0").slice(0, SCALE)}`;
}

function scaledIntegerFromCanonical(value) {
    const canonical = canonicalizeScaleSix(value);

    return canonical === null ? null : BigInt(canonical.replace(".", ""));
}

function multiplyCanonicalQuantities(left, right) {
    const leftScaled = scaledIntegerFromCanonical(left);
    const rightScaled = scaledIntegerFromCanonical(right);

    if (leftScaled === null || rightScaled === null) {
        return null;
    }

    const product = (leftScaled * rightScaled) / SCALE_FACTOR;
    const absolute = product.toString().padStart(SCALE + 1, "0");
    const splitAt = absolute.length - SCALE;

    return `${absolute.slice(0, splitAt)}.${absolute.slice(splitAt)}`;
}

function formatQuantityForPrecision(value, precision) {
    const scaled = scaledIntegerFromCanonical(value);

    if (scaled === null) {
        return "";
    }

    const normalizedPrecision = normalizePrecision(precision, SCALE);
    const factor = 10n ** BigInt(SCALE - normalizedPrecision);
    let rounded = scaled / factor;

    if ((scaled % factor) * 2n >= factor) {
        rounded += 1n;
    }

    if (normalizedPrecision === 0) {
        return rounded.toString();
    }

    const absolute = rounded.toString().padStart(normalizedPrecision + 1, "0");
    const splitAt = absolute.length - normalizedPrecision;

    return `${absolute.slice(0, splitAt)}.${absolute.slice(splitAt)}`;
}

function compactQuantity(value) {
    return String(value ?? "").replace(/(\.\d*?[1-9])0+$/u, "$1").replace(/\.0+$/u, "");
}

function normalizeWorkflowSteps(rawSteps, currentMakeOrder = {}) {
    let steps = Array.isArray(rawSteps) ? rawSteps : [];
    const isDraft = String(currentMakeOrder.status || "").toUpperCase() === "DRAFT";

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

    if (steps.length > 0 && !isDraft && !steps.some((step) => step.current) && !steps.some((step) => step.status === "completed")) {
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

    return index < 0 ? Math.max(0, steps.length - 1) : index;
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

function hydrateMakeOrder(data) {
    if (!data || typeof data !== "object") {
        return;
    }

    makeOrder.value = {
        ...makeOrder.value,
        ...data,
    };
}

function hydrateWorkflow(data) {
    if (!data || typeof data !== "object") {
        return;
    }

    workflow.value = normalizeWorkflow({
        ...workflow.value,
        ...data,
    });
    lastSavedWorkflowDueDate.value = workflow.value.due_date || "";
    lastSavedWorkflowOwnerId.value = normalizedId(workflow.value.made_by_user_id);
}

function hydrateIngredients(data) {
    if (!data || typeof data !== "object") {
        return;
    }

    ingredients.value = normalizeIngredients({
        ...ingredients.value,
        ...data,
    });
}

function hydrateMutationResponse(data) {
    hydrateMakeOrder(data.data);
    hydrateWorkflow(data.workflow);
    hydrateIngredients(data.ingredients);

    if (Array.isArray(data.workflowProgressSteps)) {
        workflowProgressSteps.value = normalizeWorkflowSteps(data.workflowProgressSteps, data.data ?? makeOrder.value);
    }
}

function validationMessageFromResponse(data, fallbackMessage) {
    const errors = data && typeof data.errors === "object" && data.errors !== null ? data.errors : {};
    const preferredFieldOrder = ["runs", "expected_output_qty", "actual_output_qty", "made_by_user_id", "due_date"];

    for (const field of preferredFieldOrder) {
        if (Array.isArray(errors[field]) && errors[field].length > 0) {
            return errors[field][0];
        }
    }

    return data?.message || fallbackMessage;
}

function firstWorkflowEntryValidationMessage() {
    if (workflow.value.current_stage?.id) {
        return "";
    }

    if (!canonicalizeScaleSix(makeOrder.value.runs_text)) {
        return "Runs qty needs to be entered.";
    }

    if (!canonicalizeScaleSix(makeOrder.value.expected_output_qty_text)) {
        return "Set an expected output quantity before moving this make order into workflow.";
    }

    if (!workflow.value.made_by_user_id) {
        return "Assign this make order before moving it into workflow.";
    }

    if (!workflow.value.due_date) {
        return "Set a due date before moving this make order into workflow.";
    }

    return "";
}

function recalculateExpectedOutputQtyFromRuns() {
    const canonicalRuns = canonicalizeScaleSix(makeOrder.value.runs_text);
    const perRunOutputQty = canonicalizeScaleSix(makeOrder.value.recipe_version_output_qty);

    if (canonicalRuns === null || perRunOutputQty === null) {
        makeOrder.value.expected_output_qty_text = "";
        return;
    }

    const expectedOutputQty = multiplyCanonicalQuantities(canonicalRuns, perRunOutputQty);

    if (expectedOutputQty === null) {
        makeOrder.value.expected_output_qty_text = "";
        return;
    }

    makeOrder.value.expected_output_qty_text = formatQuantityForPrecision(
        expectedOutputQty,
        makeOrder.value.output_uom_display_precision
    );
    recalculateIngredientQuantitiesFromRuns(canonicalRuns);
}

function recalculateIngredientQuantitiesFromRuns(canonicalRuns) {
    ingredients.value.lines = ingredients.value.lines.map((line) => {
        const recipeQuantity = canonicalizeScaleSix(line.recipe_quantity);

        if (line.line_type !== "recipe" || recipeQuantity === null) {
            return line;
        }

        const nextQuantity = multiplyCanonicalQuantities(recipeQuantity, canonicalRuns);

        if (nextQuantity === null) {
            return line;
        }

        const nextDisplayQuantity = formatQuantityForPrecision(nextQuantity, line.quantity_display_precision);

        return {
            ...line,
            quantity: nextQuantity,
            quantity_input: nextDisplayQuantity,
            quantity_display: nextDisplayQuantity,
        };
    });
}

async function saveMakeOrderDetailQuantity(field) {
    if (!makeOrder.value.details_update_url || makeOrderDetailSaving.value) {
        return;
    }

    makeOrderDetailSaving.value = true;

    const body = { field };

    if (field === "runs") {
        body.runs = makeOrder.value.runs_text;
    }

    if (field === "actual_output_qty") {
        body.actual_output_qty = makeOrder.value.actual_output_qty_text === "" ? null : makeOrder.value.actual_output_qty_text;
    }

    try {
        const response = await fetch(makeOrder.value.details_update_url, {
            method: "PATCH",
            headers: csrfHeaders(),
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            showToast(validationMessageFromResponse(data, "Unable to save make order details."), "error");
            return;
        }

        hydrateMutationResponse(data);
        showToast("Make order details updated.");
    } catch (error) {
        showToast("Unable to save make order details.", "error");
    } finally {
        makeOrderDetailSaving.value = false;
    }
}

async function saveWorkflowDueDate() {
    if (!workflowAutosaveReady.value || !workflow.value.can_edit_due_date || !workflow.value.due_date_update_url) {
        return;
    }

    const nextDueDate = workflow.value.due_date || "";

    if (nextDueDate === lastSavedWorkflowDueDate.value || workflowDueDateSaving.value) {
        return;
    }

    const previousDueDate = lastSavedWorkflowDueDate.value;
    workflowDueDateSaving.value = true;

    try {
        const response = await fetch(workflow.value.due_date_update_url, {
            method: "PATCH",
            headers: csrfHeaders(),
            body: JSON.stringify({
                due_date: nextDueDate === "" ? null : nextDueDate,
            }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            workflow.value.due_date = previousDueDate;
            showToast("Unable to save due date.", "error");
            return;
        }

        hydrateMakeOrder(data.data);
        hydrateWorkflow(data.workflow);
        showToast("Due date updated.");
    } catch (error) {
        workflow.value.due_date = previousDueDate;
        showToast("Unable to save due date.", "error");
    } finally {
        workflowDueDateSaving.value = false;
    }
}

async function saveWorkflowAssignment() {
    if (!workflow.value.can_edit_assignment || !workflow.value.assignment_update_url) {
        return;
    }

    const previousOwnerId = lastSavedWorkflowOwnerId.value;
    workflowAssignmentSaving.value = true;

    try {
        const response = await fetch(workflow.value.assignment_update_url, {
            method: "PATCH",
            headers: csrfHeaders(),
            body: JSON.stringify({
                made_by_user_id: workflow.value.made_by_user_id === "" ? null : Number(workflow.value.made_by_user_id),
            }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            workflow.value.made_by_user_id = previousOwnerId;
            showToast("Unable to save make order owner.", "error");
            return;
        }

        hydrateMakeOrder(data.data);
        hydrateWorkflow(data.workflow);
        showToast("Make order owner updated.");
    } catch (error) {
        workflow.value.made_by_user_id = previousOwnerId;
        showToast("Unable to save make order owner.", "error");
    } finally {
        workflowAssignmentSaving.value = false;
    }
}

async function performWorkflowAction(action) {
    const actionRecord = action?.action && typeof action.action === "object" ? action.action : action;

    if (!actionRecord?.id || workflowTransitionSaving.value) {
        return;
    }

    if (actionRecord.requiresConfirmation && !window.confirm(actionRecord.description || "Continue?")) {
        return;
    }

    workflowMenuOpen.value = false;
    workflowTransitionSaving.value = true;
    workflowError.value = "";

    try {
        if (String(actionRecord.method || "").toUpperCase() === "DELETE" || actionRecord.type === "cancel") {
            await submitWorkflowAction(actionRecord.endpoint, "DELETE");
        } else if (actionRecord.type === "make") {
            await submitWorkflowAction(actionRecord.endpoint, "POST", {
                actual_output_qty: makeOrder.value.actual_output_qty_text || null,
            });
        } else {
            await submitWorkflowAction(actionRecord.endpoint || workflow.value.transition_url, "PATCH", {
                workflow_stage_id: Number(actionRecord.id),
            });
        }
    } finally {
        workflowTransitionSaving.value = false;
    }
}

async function submitWorkflowAction(endpoint, method, body = null) {
    if (!endpoint) {
        showToast("Unable to update workflow.", "error");
        return;
    }

    const response = await fetch(endpoint, {
        method,
        headers: method === "DELETE"
            ? { Accept: "application/json", "X-CSRF-TOKEN": csrfToken.value }
            : csrfHeaders(),
        body: body === null ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = validationMessageFromResponse(
            data,
            "A valid due date is required before moving this make order into workflow."
        );
        workflowError.value = message;
        showToast(message, "error");
        return;
    }

    hydrateMutationResponse(data);
    showToast(data.message || (method === "POST" ? "Make order completed." : "Workflow stage updated."));
}

async function addIngredient() {
    if (!ingredients.value.can_edit || !ingredients.value.store_url || !selectedIngredientItemId.value) {
        return;
    }

    ingredientsSaving.value = true;

    try {
        const response = await fetch(ingredients.value.store_url, {
            method: "POST",
            headers: csrfHeaders(),
            body: JSON.stringify({
                item_id: Number(selectedIngredientItemId.value),
                quantity: "1.000000",
            }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            showToast("Unable to add ingredient.", "error");
            return;
        }

        ingredients.value.lines = [...ingredients.value.lines, data.data];
        selectedIngredientItemId.value = "";
        showToast("Ingredient added.");
    } catch (error) {
        showToast("Unable to add ingredient.", "error");
    } finally {
        ingredientsSaving.value = false;
    }
}

async function saveIngredientQuantity(line) {
    if (!ingredients.value.can_edit || !ingredients.value.update_url_template || !line?.id) {
        return;
    }

    ingredientSavedState.value = {
        ...ingredientSavedState.value,
        [line.id]: "saving",
    };

    try {
        const response = await fetch(
            ingredients.value.update_url_template.replace("__LINE__", encodeURIComponent(String(line.id))),
            {
                method: "PATCH",
                headers: csrfHeaders(),
                body: JSON.stringify({
                    quantity: line.quantity_input,
                }),
            }
        );
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            ingredientSavedState.value = { ...ingredientSavedState.value, [line.id]: "error" };
            showToast("Unable to save ingredient quantity.", "error");
            return;
        }

        ingredients.value.lines = ingredients.value.lines.map((entry) => (entry.id === line.id ? data.data : entry));
        ingredientSavedState.value = { ...ingredientSavedState.value, [line.id]: "saved" };
        showToast("Ingredient saved.");

        setTimeout(() => {
            if (ingredientSavedState.value[line.id] === "saved") {
                ingredientSavedState.value = { ...ingredientSavedState.value, [line.id]: "" };
            }
        }, 1000);
    } catch (error) {
        ingredientSavedState.value = { ...ingredientSavedState.value, [line.id]: "error" };
        showToast("Unable to save ingredient quantity.", "error");
    }
}

async function removeIngredient(line) {
    if (!ingredients.value.can_edit || !line?.remove_url) {
        return;
    }

    const response = await fetch(line.remove_url, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken.value,
        },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        showToast("Unable to remove ingredient.", "error");
        return;
    }

    const deletedLineId = data?.deleted_line_id ?? line.id;
    const nextLines = Array.isArray(data?.lines) ? data.lines : [];

    ingredients.value.lines = nextLines.length > 0
        ? nextLines
        : ingredients.value.lines.filter((entry) => entry.id !== deletedLineId);
    showToast("Ingredient removed.");
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
            workflow_domain_id: workflow.value.current_stage?.workflow_domain_id ?? null,
            domain_record_id: makeOrder.value.id ?? null,
            workflow_stage_id: workflow.value.current_stage?.id ?? null,
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

    workflow.value.current_stage_tasks = [...workflow.value.current_stage_tasks, data.data ?? {}].filter((task) => Boolean(task.id));
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
            "X-CSRF-TOKEN": csrfToken.value,
        },
    });

    if (response.ok) {
        const data = await response.json();
        workflow.value.current_stage_tasks = workflow.value.current_stage_tasks.map((entry) => (
            entry.id === data.data?.id ? data.data : entry
        ));
        showToast("Task completed.");
    } else {
        showToast("Unable to complete task.", "error");
    }

    taskCompletingIds.value = taskCompletingIds.value.filter((taskId) => taskId !== task.id);
}

async function submitNote() {
    if (!payload.value.notesFeed?.store_url || noteSaving.value) {
        return;
    }

    noteSaving.value = true;
    noteError.value = "";

    const response = await fetch(payload.value.notesFeed.store_url, {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify({
            body: noteBody.value,
        }),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const bodyErrors = Array.isArray(data.errors?.body) ? data.errors.body : [];
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
    window.setTimeout(() => {
        workflowAutosaveReady.value = true;
    }, 0);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);

    if (toastTimer.value) {
        clearTimeout(toastTimer.value);
    }
});
</script>

<template>
    <Head :title="title" />

    <AuthShell :shell="shell" :title="title" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <UiToast :visible="toastVisible" :type="toastType" :message="toastMessage" />

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="pageTitle"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #titleSuffix>
                    <span class="text-sm font-medium text-gray-500">{{ workflow.due_date || "No due date" }}</span>
                </template>
                <template #actions>
                    <div class="relative" data-workflow-action-button>
                        <button v-if="workflowTerminalLabel" type="button" class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm" disabled>
                            <span class="inline-flex items-center px-3 py-2 text-sm font-semibold uppercase text-slate-700">{{ workflowTriggerLabel }}</span>
                        </button>
                        <button
                            v-else-if="workflowActions.length > 0"
                            type="button"
                            class="inline-flex cursor-pointer items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="workflowTransitionSaving"
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

                        <div v-if="workflowMenuOpen" class="absolute right-0 z-[1300] mt-2 w-72 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5" role="menu">
                            <button
                                v-for="action in workflowActions"
                                :key="action.id || action.type || action.label"
                                type="button"
                                class="flex w-full cursor-pointer items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="workflowTransitionSaving"
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

                <template #header>
                    <div class="w-full bg-white px-4 pb-4 pt-4 sm:px-6 sm:pb-6 sm:pt-6 lg:px-8">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ subtitle }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-6 gap-y-2">
                                    <h1 class="truncate text-xl font-semibold leading-tight text-gray-800">{{ pageTitle }}</h1>
                                    <span class="text-sm font-medium text-gray-500">{{ workflow.due_date || "No due date" }}</span>
                                </div>
                                <div class="mt-2 space-y-1 text-sm text-gray-600">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[0.65rem] font-medium uppercase tracking-wide text-gray-500">
                                        <span>Recipe</span>
                                        <span class="text-[0.85rem] font-semibold text-gray-700">{{ makeOrder.recipe_name || "-" }}</span>
                                        <span class="text-gray-400">{{ makeOrder.display?.versionBadgeText || `v${makeOrder.recipe_version_number_display || "-"}` }}</span>
                                        <span>Runs</span>
                                        <span class="text-sm font-semibold text-gray-700">{{ makeOrder.runs_text || "-" }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-[0.65rem] font-medium uppercase tracking-wide text-gray-500">
                                        <span>Expected</span>
                                        <span class="font-medium text-gray-700">{{ compactQuantity(makeOrder.expected_output_qty_text) }} {{ makeOrder.output_uom_symbol }}</span>
                                        <span class="px-2">Actual</span>
                                        <span class="font-medium text-gray-700">{{ compactQuantity(makeOrder.actual_output_qty_text) }} {{ makeOrder.output_uom_symbol }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="relative shrink-0" data-workflow-action-button>
                                <button v-if="workflowTerminalLabel" type="button" class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm" disabled>
                                    <span class="inline-flex items-center px-3 py-2 text-sm font-semibold uppercase text-slate-700">{{ workflowTriggerLabel }}</span>
                                </button>
                                <button
                                    v-else-if="workflowActions.length > 0"
                                    type="button"
                                    class="inline-flex cursor-pointer items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="workflowTransitionSaving"
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

                                <div v-if="workflowMenuOpen" class="absolute right-0 z-[1300] mt-2 w-72 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5" role="menu">
                                    <button
                                        v-for="action in workflowActions"
                                        :key="action.id || action.type || action.label"
                                        type="button"
                                        class="flex w-full cursor-pointer items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="workflowTransitionSaving"
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
                        </div>
                    </div>
                </template>
            </ResourceDetailHeaderBreadcrumb>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="pb-12 sm:pt-6">
                    <div class="mx-auto max-w-5xl space-y-0 px-1 pb-8 pt-0 sm:space-y-6 sm:px-6 sm:pb-12 lg:px-8">
                        <nav v-if="workflowProgressSteps.length > 0" class="-mx-1 w-auto sm:mx-0 sm:w-full" aria-label="Progress">
                            <div class="flex overflow-hidden border-y border-gray-300 bg-white md:hidden" role="tablist" aria-label="Workflow stages">
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

                        <ResourceDetailSection :section="{ title: 'Details', description: 'Runs, expected output, actual output, due date, and assignment live here. Workflow movement stays in the header action.', defaultOpen: workflow.default_open }" :csrf-token="csrfToken">
                            <div class="space-y-3">
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Runs</p>
                                        <input v-model="makeOrder.runs_text" type="text" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" @input="recalculateExpectedOutputQtyFromRuns" @change="saveMakeOrderDetailQuantity('runs')" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Expected Output</p>
                                        <input v-model="makeOrder.expected_output_qty_text" type="text" readonly class="block w-full rounded-xl border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-900 shadow-sm" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Actual Output</p>
                                        <input v-model="makeOrder.actual_output_qty_text" type="text" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" @change="saveMakeOrderDetailQuantity('actual_output_qty')" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Due Date</p>
                                        <input v-model="workflow.due_date" type="date" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100" :disabled="!workflow.can_edit_due_date || workflowDueDateSaving" @click="$event.target.showPicker?.()" @change="saveWorkflowDueDate" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Assigned To</p>
                                        <select v-model="workflow.made_by_user_id" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-gray-100" :disabled="!workflow.can_edit_assignment || workflowAssignmentSaving">
                                            <option v-for="option in workflow.assignee_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </ResourceDetailSection>

                        <ResourceDetailSection :section="{ title: 'Tasks', description: 'Complete current stage tasks separately from workflow metadata editing.', defaultOpen: false }" :csrf-token="csrfToken">
                            <template #actions>
                                <button type="button" class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-10 sm:w-10" aria-label="Create task" @click="openTaskCreate">
                                    <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </template>
                            <div class="rounded-xl border border-gray-200 bg-white">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Current Stage Tasks</h4>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    <div v-if="workflow.current_stage_tasks.length === 0" class="px-4 py-4 text-sm text-gray-500">No tasks for the current workflow stage.</div>
                                    <div v-for="task in workflow.current_stage_tasks" :key="task.id" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                                            <p v-if="task.description" class="mt-1 text-sm text-gray-500">{{ task.description }}</p>
                                            <p v-if="task.assigned_to_user_name" class="mt-1 text-xs text-gray-500">Assigned To: {{ task.assigned_to_user_name }}</p>
                                            <p v-if="task.due_date" class="mt-1 text-xs text-gray-500">Due: {{ task.due_date }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="task.is_completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ task.is_completed ? "Completed" : "Open" }}</span>
                                            <button v-if="task.can_complete" type="button" class="inline-flex h-9 cursor-pointer items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="taskCompletingIds.includes(task.id)" @click="completeTask(task)">Complete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ResourceDetailSection>

                        <ResourceDetailSection :section="{ title: 'Ingredients', description: 'Make Order ingredient lines are editable snapshot rows and do not mutate the source recipe version.', defaultOpen: true }" :csrf-token="csrfToken">
                            <div class="space-y-4">
                                <div v-if="ingredients.can_edit" class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <select v-model="selectedIngredientItemId" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Select ingredient</option>
                                        <option v-for="item in ingredients.item_options" :key="item.id" :value="String(item.id)">{{ item.label }} · {{ item.uom_display }}</option>
                                    </select>
                                    <button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="ingredientsSaving || !selectedIngredientItemId" @click="addIngredient">Add</button>
                                </div>

                                <div class="rounded-xl border border-gray-200 bg-white">
                                    <div v-if="ingredients.lines.length === 0" class="px-4 py-4 text-sm text-gray-500">No ingredients yet.</div>
                                    <div v-for="line in ingredients.lines" :key="line.id" class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <a :href="line.view_url" class="cursor-pointer text-sm font-medium text-gray-900 hover:text-blue-700">{{ line.item_name || line.input_item_name }}</a>
                                            <p class="mt-1 text-xs text-gray-500">{{ line.uom_name || line.uom_symbol }} · On hand {{ line.on_hand_display }}</p>
                                        </div>
                                        <div class="flex items-center justify-end gap-2">
                                            <input v-model="line.quantity_input" type="text" class="block w-24 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!ingredients.can_edit" @change="saveIngredientQuantity(line)" />
                                            <span v-if="ingredientSavedState[line.id] === 'saved'" class="text-xs font-medium text-emerald-600">Saved</span>
                                            <button v-if="ingredients.can_edit" type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-300 text-slate-500 transition hover:bg-red-50 hover:text-red-600" aria-label="Remove ingredient" @click="removeIngredient(line)">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ResourceDetailSection>

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
                                        {{ payload.notesFeed?.empty_state || "No notes yet." }}
                                    </div>

                                    <form class="mt-6 space-y-3" @submit.prevent="submitNote">
                                        <textarea v-model="noteBody" rows="3" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="Add an internal comment" :disabled="noteSaving" />
                                        <p v-if="noteError" class="mt-2 text-sm text-red-600">{{ noteError }}</p>
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
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <BaseDrawer :open="taskOpen" labelled-by="make-order-task-title" @close="taskOpen = false">
                <form class="flex h-full flex-col" @submit.prevent="submitTask">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 id="make-order-task-title" class="text-sm font-semibold">Create Task</h2>
                    </div>
                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                        <div>
                            <label for="make-order-task-form-title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="make-order-task-form-title" v-model="taskForm.title" type="text" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <p v-if="taskErrors.title?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.title[0] }}</p>
                        </div>
                        <div>
                            <label for="make-order-task-assigned-to-user-id" class="block text-sm font-medium text-gray-700">Assigned To</label>
                            <select id="make-order-task-assigned-to-user-id" v-model="taskForm.assigned_to_user_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select user</option>
                                <option v-for="user in payload.taskCreate?.users ?? []" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                            </select>
                            <p v-if="taskErrors.assigned_to_user_id?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.assigned_to_user_id[0] }}</p>
                        </div>
                        <div>
                            <label for="make-order-task-due-date" class="block text-sm font-medium text-gray-700">Due Date</label>
                            <input id="make-order-task-due-date" v-model="taskForm.due_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @click="$event.target.showPicker?.()" />
                            <p v-if="taskErrors.due_date?.length" class="mt-1 text-sm text-red-600">{{ taskErrors.due_date[0] }}</p>
                        </div>
                        <div>
                            <label for="make-order-task-description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="make-order-task-description" v-model="taskForm.description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
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
