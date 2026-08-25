<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";

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
    payload: {
        type: Object,
        required: true,
    },
    indexUrl: {
        type: String,
        required: true,
    },
});

const purchaseOrder = ref({ ...(props.payload.purchaseOrder ?? {}) });
const workflow = ref({ ...(props.payload.workflow ?? {}) });
const workflowProgressSteps = ref(normalizeWorkflowSteps(props.payload.workflowProgressSteps ?? []));
const lines = ref([...(props.payload.lines ?? [])]);
const receipts = ref([...(props.payload.receipts ?? [])]);
const shortClosures = ref([...(props.payload.shortClosures ?? [])]);
const notes = ref([...(props.payload.notesFeed?.notes ?? [])]);
const suppliers = computed(() => props.payload.suppliers ?? []);
const purchaseOptions = computed(() => props.payload.purchaseOptions ?? []);
const taskUsers = computed(() => props.payload.taskCreate?.users ?? []);
const notesFeed = computed(() => props.payload.notesFeed ?? {});
const csrfToken = computed(() => props.payload.csrfToken ?? "");
const tenantCurrency = computed(() => props.payload.tenantCurrency ?? "USD");
const isEditable = computed(() => Boolean(purchaseOrder.value?.is_editable));
const canReceive = ref(Boolean(props.payload.canReceive));

const activeWorkflowStep = ref(0);
const workflowMenuOpen = ref(false);
const workflowLoading = ref(false);
const workflowError = ref("");
const toast = ref(null);
const toastTimer = ref(null);
const headerError = ref("");
const headerErrors = ref(emptyHeaderErrors());
const savedFields = reactive({});
const savedFieldTimers = {};
const taskOpen = ref(false);
const taskSubmitting = ref(false);
const taskError = ref("");
const taskErrors = ref({});
const taskCompletingIds = ref([]);
const taskForm = reactive(emptyTaskForm());
const lineError = ref("");
const lineErrors = ref(emptyLineErrors());
const isLineSubmitting = ref(false);
const isDeleteLineSubmitting = ref(false);
const lineForm = reactive(emptyLineForm());
const noteBody = ref("");
const noteSaving = ref(false);
const noteError = ref("");
const noteSortDirection = ref("asc");
const receiveOpen = ref(false);
const receiveSubmitting = ref(false);
const receiveError = ref("");
const receiveErrors = ref(emptyReceiveErrors());
const receiveLineErrors = ref({});
const receiveForm = reactive(emptyReceiveForm());
const shortCloseOpen = ref(false);
const shortCloseSubmitting = ref(false);
const shortCloseError = ref("");
const shortCloseErrors = ref(emptyShortCloseErrors());
const shortCloseLineLabel = ref("");
const shortCloseForm = reactive(emptyShortCloseForm());
const detailsDefaultOpen = !purchaseOrder.value?.supplier_id
    || !purchaseOrder.value?.order_date
    || !purchaseOrder.value?.po_number;
const sectionOpen = reactive({
    details: detailsDefaultOpen,
    items: true,
    totals: true,
    notes: true,
    tasks: false,
    receipts: false,
    shortClosures: false,
});

const form = reactive({
    supplier_id: normalizeId(purchaseOrder.value?.supplier_id),
    assigned_to_user_id: normalizeId(purchaseOrder.value?.assigned_to_user_id),
    order_date: purchaseOrder.value?.order_date ?? "",
    shipping_amount: purchaseOrder.value?.shipping_amount ?? "",
    po_number: purchaseOrder.value?.po_number ?? "",
    notes: purchaseOrder.value?.notes ?? "",
});

const breadcrumbItems = computed(() => [
    {
        label: "Purchase Orders",
        url: props.indexUrl,
        current: false,
    },
    {
        label: currentTitle.value,
        url: null,
        current: true,
    },
]);
const currentTitle = computed(() => purchaseOrder.value?.po_number || `PO #${purchaseOrder.value?.id ?? ""}`);
const workflowActions = computed(() => sortedWorkflowActions(workflow.value));
const workflowTriggerLabel = computed(() => workflow.value?.display_label
    || workflow.value?.status_label
    || workflow.value?.currentLabel
    || workflow.value?.currentStage?.actionVerb
    || purchaseOrder.value?.status
    || "");
const canReceiveOrder = computed(() => {
    const status = purchaseOrder.value?.persisted_status || purchaseOrder.value?.status || "";
    const hasReceivableLine = lines.value.some((line) => normalizeDecimal(line.remaining_balance) !== "0.000000");
    const hasReceiveAction = workflowActions.value.some((action) => (action.action || action.type) === "receive");

    return hasReceiveAction
        && ["CREATED", "PARTIALLY_RECEIVED"].includes(status)
        && hasReceivableLine
        && !purchaseOrder.value?.is_cancelled;
});
const supplierPackageOptions = computed(() => {
    const supplierId = Number(form.supplier_id);

    if (!supplierId) {
        return [];
    }

    return purchaseOptions.value.filter((option) => Number(option.supplier_id) === supplierId);
});
const visibleNotes = computed(() => [...notes.value].sort((left, right) => {
    const multiplier = noteSortDirection.value === "desc" ? -1 : 1;
    const leftTime = Date.parse(left?.created_at || "") || 0;
    const rightTime = Date.parse(right?.created_at || "") || 0;

    return (leftTime - rightTime) * multiplier;
}));

watch(workflowProgressSteps, (steps) => {
    activeWorkflowStep.value = activeWorkflowStepIndex(steps);
}, { immediate: true });

function emptyHeaderErrors() {
    return {
        supplier_id: [],
        assigned_to_user_id: [],
        order_date: [],
        shipping_amount: [],
        po_number: [],
        notes: [],
    };
}

function emptyLineErrors() {
    return {
        item_purchase_option_id: [],
        pack_count: [],
        unit_price_cents: [],
        tax_percent: [],
        supplier_id: [],
    };
}

function emptyLineForm() {
    return {
        item_purchase_option_id: "",
        pack_count: 1,
        unit_price_cents: "",
        tax_percent: "0",
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

function emptyReceiveErrors() {
    return {
        received_at: [],
        reference: [],
        notes: [],
        lines: [],
    };
}

function emptyReceiveForm() {
    return {
        received_at: "",
        reference: "",
        notes: "",
        lines: [],
    };
}

function emptyShortCloseErrors() {
    return {
        short_closed_at: [],
        reference: [],
        notes: [],
        short_closed_quantity: [],
    };
}

function emptyShortCloseForm() {
    return {
        short_closed_at: "",
        reference: "",
        notes: "",
        purchase_order_line_id: null,
        short_closed_quantity: "",
    };
}

function normalizeId(value) {
    return value === null || value === undefined ? "" : String(value);
}

function normalizeNullable(value) {
    return value === "" || value === null || value === undefined ? null : value;
}

function normalizeNullableInt(value) {
    return value === "" || value === null || value === undefined ? null : Number(value);
}

function normalizeDecimal(value) {
    const raw = value === null || value === undefined ? "" : String(value).trim();

    if (raw === "") {
        return "0.000000";
    }

    const parts = raw.split(".");
    const whole = parts[0] === "" ? "0" : parts[0];
    const fraction = (parts[1] || "").padEnd(6, "0").slice(0, 6);

    return `${whole}.${fraction}`;
}

function normalizeErrors(errors, emptyFactory) {
    const defaults = emptyFactory();

    if (!errors || typeof errors !== "object") {
        return defaults;
    }

    return Object.keys(defaults).reduce((carry, key) => ({
        ...carry,
        [key]: Array.isArray(errors[key]) ? errors[key] : [],
    }), defaults);
}

function csrfHeaders() {
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken.value,
    };
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
    }, 1500);
}

function formatMoney(cents) {
    return `${tenantCurrency.value} ${((Number(cents) || 0) / 100).toFixed(2)}`;
}

function formatQuantity(value) {
    const raw = value === null || value === undefined ? "" : String(value);

    return raw === "" ? "0" : raw;
}

function formatWholeQuantity(value) {
    const normalized = normalizeDecimal(value);
    const [whole, fraction = ""] = normalized.split(".");

    if (fraction !== "" && !/^0+$/.test(fraction)) {
        return normalized;
    }

    return whole.replace(/^(-?)0+(?=\d)/, "$1");
}

function lineLabel(line) {
    if (!line?.pack_quantity) {
        return "Pack";
    }

    const quantity = line.pack_quantity_display || formatQuantity(line.pack_quantity);
    const uom = line.pack_uom_symbol || line.pack_uom_name || "pack";

    return `${quantity} ${uom} pack`;
}

function receiptLineSummary(receipt) {
    return `${receipt.lines_count ?? 0} lines, ${formatWholeQuantity(receipt.total_packs ?? "0.000000")} total packs`;
}

function shortCloseLineSummary(shortClose) {
    return `${shortClose.lines_count ?? 0} lines, ${formatQuantity(shortClose.total_packs ?? "0.000000")} total packs`;
}

function sortedWorkflowActions(currentWorkflow) {
    const actions = Array.isArray(currentWorkflow?.actions) ? currentWorkflow.actions : [];

    return [...actions].sort((left, right) => {
        const leftCancel = String(left?.type || left?.id || "").toLowerCase() === "cancel";
        const rightCancel = String(right?.type || right?.id || "").toLowerCase() === "cancel";

        if (leftCancel === rightCancel) {
            return 0;
        }

        return leftCancel ? 1 : -1;
    });
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

function syncFormFromOrder(updated, preserveFields = []) {
    const next = {
        supplier_id: normalizeId(updated.supplier_id),
        assigned_to_user_id: normalizeId(updated.assigned_to_user_id),
        order_date: updated.order_date ?? "",
        shipping_amount: updated.shipping_amount ?? "",
        po_number: updated.po_number ?? "",
        notes: updated.notes ?? "",
    };

    preserveFields.forEach((field) => {
        next[field] = form[field];
    });

    Object.assign(form, next);
}

function applyPurchaseOrderUpdate(updated, nextLines = null, preserveFields = []) {
    purchaseOrder.value = {
        ...purchaseOrder.value,
        ...updated,
    };
    syncFormFromOrder(purchaseOrder.value, preserveFields);

    if (Array.isArray(nextLines)) {
        lines.value = nextLines;
    }
}

function markSaved(field) {
    savedFields[field] = true;

    if (savedFieldTimers[field]) {
        window.clearTimeout(savedFieldTimers[field]);
    }

    savedFieldTimers[field] = window.setTimeout(() => {
        savedFields[field] = false;
    }, 1000);
}

function fieldPayload(field) {
    if (field === "supplier_id") {
        return { supplier_id: normalizeNullableInt(form.supplier_id) };
    }

    if (field === "assigned_to_user_id") {
        return { assigned_to_user_id: normalizeNullableInt(form.assigned_to_user_id) };
    }

    if (field === "order_date") {
        return { order_date: normalizeNullable(form.order_date) };
    }

    if (field === "shipping_amount") {
        return { shipping_amount: normalizeNullable(form.shipping_amount) };
    }

    if (field === "po_number") {
        return { po_number: normalizeNullable(form.po_number) };
    }

    if (field === "notes") {
        return { notes: normalizeNullable(form.notes) };
    }

    return {};
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
        error.status = response.status;
        throw error;
    }

    return data;
}

async function autosaveField(field) {
    if (!isEditable.value || !props.payload.updateUrl) {
        return;
    }

    headerErrors.value = emptyHeaderErrors();
    headerError.value = "";

    try {
        const data = await jsonRequest(props.payload.updateUrl, {
            method: "PATCH",
            body: JSON.stringify(fieldPayload(field)),
        });
        const updated = data.data?.purchase_order || data.data || {};
        const preserveFields = field === "shipping_amount" ? ["shipping_amount"] : [];

        applyPurchaseOrderUpdate(updated, data.data?.lines, preserveFields);

        if (field === "supplier_id") {
            Object.assign(lineForm, emptyLineForm());
        }

        markSaved(field);
    } catch (error) {
        headerErrors.value = normalizeErrors(error.payload?.errors, emptyHeaderErrors);
        headerError.value = error.payload?.message ?? "Unable to save field.";
    }
}

function handleOptionChange() {
    const optionId = Number(lineForm.item_purchase_option_id);
    const option = supplierPackageOptions.value.find((entry) => Number(entry.id) === optionId);

    if (!option) {
        return;
    }

    lineForm.unit_price_cents = option.current_price_cents ?? "";
    lineForm.pack_count = lineForm.pack_count || 1;
    lineForm.tax_percent = lineForm.tax_percent || "0";
}

async function submitLine() {
    if (!isEditable.value || isLineSubmitting.value || !props.payload.lineStoreUrl) {
        return;
    }

    isLineSubmitting.value = true;
    lineErrors.value = emptyLineErrors();
    lineError.value = "";
    handleOptionChange();

    try {
        const data = await jsonRequest(props.payload.lineStoreUrl, {
            method: "POST",
            body: JSON.stringify({
                item_purchase_option_id: normalizeNullableInt(lineForm.item_purchase_option_id),
                pack_count: normalizeNullableInt(lineForm.pack_count),
                unit_price_cents: normalizeNullableInt(lineForm.unit_price_cents),
                tax_percent: normalizeNullable(lineForm.tax_percent),
            }),
        });

        if (data.data?.line) {
            lines.value = [...lines.value, data.data.line];
        }

        if (data.data?.purchase_order) {
            applyPurchaseOrderUpdate(data.data.purchase_order);
        }

        Object.assign(lineForm, emptyLineForm());
        showToast("Line added.");
    } catch (error) {
        lineErrors.value = normalizeErrors(error.payload?.errors, emptyLineErrors);
        lineError.value = error.payload?.message ?? "Unable to add line.";
    } finally {
        isLineSubmitting.value = false;
    }
}

async function autosaveLineField(line, field) {
    if (!isEditable.value || !line?.id || !props.payload.lineUpdateUrlBase) {
        return;
    }

    try {
        const data = await jsonRequest(`${props.payload.lineUpdateUrlBase}/${line.id}`, {
            method: "PATCH",
            body: JSON.stringify({
                pack_count: normalizeNullableInt(line.pack_count),
                unit_price_cents: normalizeNullableInt(line.unit_price_cents),
                tax_percent: normalizeNullable(line.tax_percent),
            }),
        });

        if (data.data?.line) {
            lines.value = lines.value.map((entry) => (entry.id === data.data.line.id ? data.data.line : entry));
        }

        if (data.data?.purchase_order) {
            applyPurchaseOrderUpdate(data.data.purchase_order);
        }

        showToast(`${field.replace("_", " ")} updated.`);
    } catch (error) {
        showToast(error.payload?.message ?? `Unable to update ${field}.`, "error");
    }
}

async function deleteLine(line) {
    if (!isEditable.value || !line?.id || !props.payload.lineDeleteUrlBase || isDeleteLineSubmitting.value) {
        return;
    }

    isDeleteLineSubmitting.value = true;

    try {
        const data = await jsonRequest(`${props.payload.lineDeleteUrlBase}/${line.id}`, {
            method: "DELETE",
        });

        lines.value = Array.isArray(data.data?.lines)
            ? data.data.lines
            : lines.value.filter((entry) => entry.id !== line.id);

        if (data.data?.purchase_order) {
            applyPurchaseOrderUpdate(data.data.purchase_order);
        }

        showToast("Line removed.");
    } catch (error) {
        showToast(error.payload?.message ?? "Unable to delete line.", "error");
    } finally {
        isDeleteLineSubmitting.value = false;
    }
}

async function performWorkflowAction(action) {
    if (!action || workflowLoading.value) {
        return;
    }

    const actionType = action.action || action.type;

    if (actionType === "receive") {
        openReceive();
        return;
    }

    if (actionType === "short_close") {
        const line = lines.value.find((entry) => canShortCloseLine(entry));

        if (line) {
            openShortCloseLine(line);
        }

        return;
    }

    workflowMenuOpen.value = false;
    workflowLoading.value = true;
    workflowError.value = "";

    try {
        const data = await jsonRequest(action.endpoint || props.payload.statusUpdateUrl, {
            method: action.method || "PATCH",
            body: JSON.stringify({ status: action.status || actionType }),
        });
        applyWorkflowResponse(data.data || data);
        showToast("Status updated.");
    } catch (error) {
        workflowError.value = error.payload?.message ?? "Unable to update workflow.";
        showToast(workflowError.value, "error");
    } finally {
        workflowLoading.value = false;
    }
}

function applyWorkflowResponse(data) {
    if (data.purchase_order) {
        purchaseOrder.value = {
            ...purchaseOrder.value,
            ...data.purchase_order,
            status: data.purchase_order.status || purchaseOrder.value.status,
        };
        syncFormFromOrder(purchaseOrder.value);
    }

    if (data.workflow) {
        workflow.value = data.workflow;
    }

    if (Array.isArray(data.workflowProgressSteps)) {
        workflowProgressSteps.value = normalizeWorkflowSteps(data.workflowProgressSteps);
    }

    if (Array.isArray(data.lines)) {
        lines.value = data.lines;
    }

    if (Object.prototype.hasOwnProperty.call(data, "can_receive")) {
        canReceive.value = Boolean(data.can_receive);
    }
}

function canShortCloseLine(line) {
    return canReceiveOrder.value && normalizeDecimal(line.remaining_balance) !== "0.000000";
}

function openReceive() {
    if (!canReceive.value || !canReceiveOrder.value) {
        return;
    }

    const receivableLines = lines.value
        .filter((line) => normalizeDecimal(line.remaining_balance) !== "0.000000")
        .map((line) => ({
            id: line.id,
            item_name: line.item_name,
            remaining_balance: normalizeDecimal(line.remaining_balance),
            remaining_balance_display: line.remaining_balance_display,
            unit_context: lineLabel(line),
            received_quantity: formatWholeQuantity(line.remaining_balance),
        }));

    Object.assign(receiveForm, emptyReceiveForm(), { lines: receivableLines });
    receiveErrors.value = emptyReceiveErrors();
    receiveLineErrors.value = {};
    receiveError.value = "";
    receiveOpen.value = receivableLines.length > 0;
}

async function submitReceive() {
    receiveSubmitting.value = true;
    receiveErrors.value = emptyReceiveErrors();
    receiveLineErrors.value = {};
    receiveError.value = "";

    try {
        const data = await jsonRequest(props.payload.receiptStoreUrl, {
            method: "POST",
            body: JSON.stringify({
                received_at: receiveForm.received_at || null,
                reference: receiveForm.reference || null,
                notes: receiveForm.notes || null,
                lines: receiveForm.lines.map((line) => ({
                    purchase_order_line_id: line.id,
                    received_quantity: normalizeDecimal(line.received_quantity),
                })),
            }),
        });

        applyWorkflowResponse(data.data || {});

        if (Array.isArray(data.data?.receipts)) {
            receipts.value = data.data.receipts;
        }

        receiveOpen.value = false;
        showToast("Receipt recorded.");
    } catch (error) {
        receiveErrors.value = normalizeErrors(error.payload?.errors, emptyReceiveErrors);
        receiveError.value = error.payload?.message ?? "Unable to receive order.";
    } finally {
        receiveSubmitting.value = false;
    }
}

function openShortCloseLine(line) {
    if (!canShortCloseLine(line)) {
        return;
    }

    Object.assign(shortCloseForm, emptyShortCloseForm(), {
        purchase_order_line_id: line.id,
        short_closed_quantity: normalizeDecimal(line.remaining_balance),
    });
    shortCloseLineLabel.value = line.item_name || "Line";
    shortCloseErrors.value = emptyShortCloseErrors();
    shortCloseError.value = "";
    shortCloseOpen.value = true;
}

async function submitShortClose() {
    shortCloseSubmitting.value = true;
    shortCloseErrors.value = emptyShortCloseErrors();
    shortCloseError.value = "";

    try {
        const data = await jsonRequest(props.payload.shortCloseStoreUrl, {
            method: "POST",
            body: JSON.stringify({
                short_closed_at: shortCloseForm.short_closed_at || null,
                reference: shortCloseForm.reference || null,
                notes: shortCloseForm.notes || null,
                purchase_order_line_id: shortCloseForm.purchase_order_line_id,
                short_closed_quantity: normalizeDecimal(shortCloseForm.short_closed_quantity),
            }),
        });

        applyWorkflowResponse(data.data || {});

        if (Array.isArray(data.data?.shortClosures)) {
            shortClosures.value = data.data.shortClosures;
        }

        shortCloseOpen.value = false;
        showToast("Short close recorded.");
    } catch (error) {
        shortCloseErrors.value = normalizeErrors(error.payload?.errors, emptyShortCloseErrors);
        shortCloseError.value = error.payload?.message ?? "Unable to short-close line.";
    } finally {
        shortCloseSubmitting.value = false;
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
        const data = await jsonRequest(props.payload.taskCreate?.storeUrl ?? "/tasks", {
            method: "POST",
            body: JSON.stringify({
                title: taskForm.title,
                assigned_to_user_id: taskForm.assigned_to_user_id === "" ? null : Number(taskForm.assigned_to_user_id),
                due_date: taskForm.due_date || null,
                description: taskForm.description,
                workflow_domain_id: workflow.value?.currentStage?.workflow_domain_id ?? null,
                domain_record_id: purchaseOrder.value?.id ?? null,
                workflow_stage_id: workflow.value?.currentStage?.id ?? null,
            }),
        });
        const task = data.data ?? {};
        const tasks = Array.isArray(workflow.value.currentStageTasks) ? workflow.value.currentStageTasks : [];

        workflow.value = {
            ...workflow.value,
            currentStageTasks: task.id ? [...tasks, task] : tasks,
        };
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
        });
        const updatedTask = data.data ?? {};

        workflow.value = {
            ...workflow.value,
            currentStageTasks: (workflow.value.currentStageTasks || []).map((entry) => (
                entry.id === updatedTask.id ? updatedTask : entry
            )),
        };
        showToast("Workflow task completed.");
    } catch (error) {
        showToast("Unable to complete workflow task.", "error");
    } finally {
        taskCompletingIds.value = taskCompletingIds.value.filter((taskId) => taskId !== task.id);
    }
}

async function submitNote() {
    if (!notesFeed.value?.store_url || noteSaving.value) {
        return;
    }

    noteSaving.value = true;
    noteError.value = "";

    try {
        const data = await jsonRequest(notesFeed.value.store_url, {
            method: "POST",
            body: JSON.stringify({ body: noteBody.value }),
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

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <Head :title="currentTitle" />

    <AuthShell :shell="shell" :title="currentTitle" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <div v-if="toast" class="fixed right-4 top-4 z-[1200] rounded-md px-4 py-2 text-sm font-medium shadow-lg" :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
                {{ toast.message }}
            </div>

            <ResourceDetailHeaderBreadcrumb
                class="pb-4"
                :items="breadcrumbItems"
                :title="currentTitle"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #actions>
                    <div v-if="canReceive && workflowActions.length > 0" class="relative" data-purchase-order-action-button data-workflow-action-button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="workflowLoading"
                            @click.stop="workflowMenuOpen = !workflowMenuOpen"
                        >
                            <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700">
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
                                :key="action.id || action.type || action.label"
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
                <div class="pt-0 pb-8 sm:pt-6 sm:pb-12">
                    <div class="mx-auto w-full min-w-0 max-w-7xl space-y-0 px-1 sm:space-y-6 sm:px-6 lg:px-8">
                        <nav v-if="workflowProgressSteps.length > 0" class="-mx-1 w-auto sm:mx-0 sm:w-full" aria-label="Progress">
                            <div class="flex overflow-hidden border-y border-gray-300 bg-white md:hidden" role="tablist" aria-label="Workflow stages">
                                <button
                                    v-for="(step, index) in workflowProgressSteps"
                                    :key="`${step.label}-${index}`"
                                    type="button"
                                    role="tab"
                                    class="relative min-h-16 cursor-pointer overflow-hidden bg-white py-2 pl-3 pr-6 transition-[flex-basis,flex-grow] duration-300 ease-out"
                                    :class="activeWorkflowStep === index ? 'basis-0 grow' : 'basis-16 grow-0'"
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
                                    <span class="flex w-full items-center px-4 py-3 text-sm font-medium sm:px-6">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full border-2" :class="workflowCircleClass(step)">
                                            <svg v-if="step.status === 'completed'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-5 text-white">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" />
                                            </svg>
                                            <span v-else class="text-sm font-semibold">{{ step.number }}</span>
                                        </span>
                                        <span class="ml-4 text-sm font-medium" :class="step.status === 'completed' ? 'text-gray-900' : workflowLabelClass(step)">{{ step.label }}</span>
                                    </span>
                                </li>
                            </ol>
                        </nav>

                        <div class="grid w-full min-w-0 gap-0 sm:gap-6 lg:grid-cols-4 lg:items-start">
                            <div class="min-w-0 space-y-0 sm:space-y-6 lg:col-span-3">
                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Details</h3>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.details ? 'true' : 'false'" aria-label="Toggle details" @click="sectionOpen.details = !sectionOpen.details">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.details ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.details" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
                                        <div class="grid gap-6 sm:grid-cols-2">
                                            <div class="max-w-sm">
                                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                                    Supplier
                                                    <span class="mt-2 flex items-center gap-2">
                                                        <select v-model="form.supplier_id" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveField('supplier_id')">
                                                            <option value="">Select supplier</option>
                                                            <option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">{{ supplier.company_name }}</option>
                                                        </select>
                                                        <svg v-show="savedFields.supplier_id" data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </span>
                                                </label>
                                                <p class="mt-1 text-xs text-red-600">{{ headerErrors.supplier_id[0] }}</p>
                                            </div>

                                            <div class="max-w-sm">
                                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                                    Assigned To
                                                    <span class="mt-2 flex items-center gap-2">
                                                        <select v-model="form.assigned_to_user_id" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-700" :disabled="!isEditable" @change="autosaveField('assigned_to_user_id')">
                                                            <option v-for="assignee in purchaseOrder.assignee_options || []" :key="assignee.value" :value="assignee.value">{{ assignee.label }}</option>
                                                        </select>
                                                        <svg v-show="savedFields.assigned_to_user_id" data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </span>
                                                </label>
                                                <p class="mt-1 text-xs text-red-600">{{ headerErrors.assigned_to_user_id[0] }}</p>
                                            </div>

                                            <div class="max-w-xs">
                                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                                    Order date
                                                    <span class="mt-2 flex items-center gap-2">
                                                        <input v-model="form.order_date" type="date" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveField('order_date')" @blur="autosaveField('order_date')" @click="$event.target.showPicker?.()">
                                                        <svg v-show="savedFields.order_date" data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </span>
                                                </label>
                                                <p class="mt-1 text-xs text-red-600">{{ headerErrors.order_date[0] }}</p>
                                            </div>

                                            <div class="max-w-sm">
                                                <label class="block text-xs font-semibold uppercase text-gray-500">
                                                    PO number
                                                    <span class="mt-2 flex items-center gap-2">
                                                        <input v-model="form.po_number" type="text" class="w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @blur="autosaveField('po_number')">
                                                        <svg v-show="savedFields.po_number" data-autosave-success-icon class="h-5 w-5 shrink-0 text-lime-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </span>
                                                </label>
                                                <p class="mt-1 text-xs text-red-600">{{ headerErrors.po_number[0] }}</p>
                                            </div>
                                        </div>

                                        <p v-if="headerError" class="mt-3 text-xs text-red-600">{{ headerError }}</p>
                                    </div>
                        </section>

                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Items</h3>
                                            <p class="mt-0.5 text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Supplier packages on this purchase order.</p>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.items ? 'true' : 'false'" aria-label="Toggle items" @click="sectionOpen.items = !sectionOpen.items">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.items ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.items" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
                                <div v-if="isEditable" class="space-y-2">
                                    <div class="flex items-start gap-2 sm:gap-3">
                                        <div class="min-w-0 flex-1">
                                            <select v-model="lineForm.item_purchase_option_id" class="block w-full rounded-lg border-gray-300 px-3 py-2 pr-9 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" :disabled="!form.supplier_id" @change="handleOptionChange">
                                                <option value="">Search supplier packages</option>
                                                <option v-for="option in supplierPackageOptions" :key="option.id" :value="String(option.id)">
                                                    {{ option.item_name }} ({{ option.pack_quantity_display || option.pack_quantity }} {{ option.pack_uom_symbol || option.pack_uom_name || 'pack' }})
                                                </option>
                                            </select>
                                            <p v-if="!form.supplier_id" class="mt-2 text-xs text-gray-500">
                                                Select a supplier before adding supplier packages.
                                            </p>
                                            <span class="mt-1 block text-xs text-red-600">{{ lineErrors.supplier_id[0] }}</span>
                                        </div>
                                        <div class="shrink-0 pt-1">
                                            <button type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-gray-300 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="isLineSubmitting || !form.supplier_id || !lineForm.item_purchase_option_id" aria-label="Add supplier package" @click="submitLine">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-red-600">{{ lineError || lineErrors.item_purchase_option_id[0] }}</p>
                                </div>

                                <div v-else class="rounded-lg border border-gray-100 p-4 text-sm text-gray-600">
                                    This purchase order is locked and can no longer be edited.
                                </div>

                                <div v-if="lines.length > 0" class="mt-6 space-y-2 md:hidden">
                                    <div v-for="line in lines" :key="`mobile-line-${line.id}`" class="relative rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                                        <div class="flex items-start gap-3">
                                            <div class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">{{ line.item_name || "Item" }}</div>
                                            <button v-if="isEditable" type="button" class="inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center text-gray-400 transition hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Remove purchase order line" :disabled="isDeleteLineSubmitting" @click="deleteLine(line)">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>

                                        <div class="mt-2 grid grid-cols-[minmax(0,1fr)_5rem_5.5rem] items-end gap-2">
                                            <div class="min-w-0">
                                                <div class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">Pack</div>
                                                <div class="mt-1 truncate text-xs font-medium text-gray-600">{{ lineLabel(line) }}</div>
                                            </div>
                                            <label class="block">
                                                <span class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">Qty</span>
                                                <input v-model="line.pack_count" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 px-2 py-1 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveLineField(line, 'pack_count')">
                                            </label>
                                            <label class="block">
                                                <span class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">Tax</span>
                                                <input v-model="line.tax_percent" type="text" class="mt-1 block w-full rounded-md border-gray-300 px-2 py-1 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveLineField(line, 'tax_percent')">
                                            </label>
                                        </div>

                                        <div class="mt-2 truncate text-[0.68rem] text-gray-500">
                                            Received <span>{{ line.received_sum_display }}</span>
                                            <span class="px-1">·</span>
                                            Short-closed <span>{{ line.short_closed_sum_display }}</span>
                                            <span class="px-1">·</span>
                                            Remaining <span>{{ line.remaining_balance_display }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="lines.length > 0" class="mt-6 hidden overflow-x-auto md:block">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Item</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Pack</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subtotal</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    <span class="sr-only">Remove</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="line in lines" :key="line.id">
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    <div class="font-medium">{{ line.item_name || "Item" }}</div>
                                                    <div class="mt-1 text-xs text-gray-500">
                                                        Received {{ line.received_sum_display }} · Short-closed {{ line.short_closed_sum_display }} · Remaining {{ line.remaining_balance_display }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ lineLabel(line) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    <input v-model="line.pack_count" type="number" min="1" class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveLineField(line, 'pack_count')">
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    <input v-model="line.tax_percent" type="text" class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveLineField(line, 'tax_percent')">
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatMoney(line.line_subtotal_cents) }}</td>
                                                <td class="px-4 py-3 text-right text-sm">
                                                    <div v-if="isEditable" class="flex justify-end">
                                                        <button type="button" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border border-gray-300 text-gray-500 transition hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Remove purchase order line" :disabled="isDeleteLineSubmitting" @click="deleteLine(line)">
                                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div v-else-if="canReceive" class="flex justify-end gap-3">
                                                        <button v-if="canShortCloseLine(line)" type="button" class="cursor-pointer text-yellow-600 hover:text-yellow-500" @click="openShortCloseLine(line)">Short-Close</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else class="mt-6 rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                                    No lines yet. Add a purchase option pack to start pricing this order.
                                </div>
                                    </div>
                                </section>
                            </div>

                            <div class="min-w-0 space-y-0 sm:space-y-6 lg:col-span-1">
                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Totals</h3>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.totals ? 'true' : 'false'" aria-label="Toggle totals" @click="sectionOpen.totals = !sectionOpen.totals">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.totals ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.totals" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
                                        <dl class="divide-y divide-gray-100 text-sm">
                                            <div class="flex items-center justify-between py-3"><dt class="text-gray-600">Subtotal</dt><dd class="font-medium text-gray-900">{{ formatMoney(purchaseOrder.po_subtotal_cents) }}</dd></div>
                                            <div class="flex items-center justify-between py-3">
                                                <dt class="text-gray-600">Shipping</dt>
                                                <dd class="flex items-center gap-2 font-medium text-gray-900">
                                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                                        <svg v-show="savedFields.shipping_amount" data-autosave-success-icon class="h-5 w-5 text-lime-400 transition-opacity" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
                                                    </span>
                                                    <input v-model="form.shipping_amount" type="text" inputmode="decimal" class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" :disabled="!isEditable" @change="autosaveField('shipping_amount')" @blur="autosaveField('shipping_amount')">
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between py-3"><dt class="text-gray-600">Tax</dt><dd class="font-medium text-gray-900">{{ formatMoney(purchaseOrder.tax_cents) }}</dd></div>
                                            <div class="flex items-center justify-between py-4 text-base"><dt class="font-semibold text-gray-900">Grand total</dt><dd class="font-semibold text-gray-900">{{ formatMoney(purchaseOrder.po_grand_total_cents) }}</dd></div>
                                        </dl>
                                    </div>
                                </section>
                            </div>

                            <div class="min-w-0 space-y-0 sm:space-y-6 lg:col-span-3">
                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Notes</h3>
                                            <p class="mt-0.5 text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Timeline of internal comments for this resource.</p>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.notes ? 'true' : 'false'" aria-label="Toggle notes" @click="sectionOpen.notes = !sectionOpen.notes">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.notes ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.notes" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
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
                                            <textarea v-model="noteBody" rows="3" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="Add an internal comment" :disabled="noteSaving" />
                                            <p v-if="noteError" class="text-sm text-red-600">{{ noteError }}</p>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" @click="toggleNoteSort">Sort</button>
                                                <button type="submit" class="cursor-pointer rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="noteSaving">Comment</button>
                                            </div>
                                        </form>
                                    </div>
                                </section>

                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Tasks</h3>
                                            <p class="mt-0.5 text-[0.65rem] text-gray-500 sm:mt-1 sm:text-sm">Complete current stage tasks before moving the purchase order forward.</p>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.tasks ? 'true' : 'false'" aria-label="Toggle tasks" @click="sectionOpen.tasks = !sectionOpen.tasks">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.tasks ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.tasks" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="sr-only">Tasks</span>
                                    <button type="button" class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50" aria-label="Create task" @click="openTaskCreate">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </button>
                                </div>
                                <div class="mt-4 space-y-2">
                                    <div v-if="(workflow.currentStageTasks || []).length === 0" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500">No tasks for the current workflow stage.</div>
                                    <article v-for="task in workflow.currentStageTasks || []" :key="task.id" class="rounded-lg border border-gray-200 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ task.assigned_to_user_name ? `Assigned to ${task.assigned_to_user_name}` : "Assigned user unavailable" }}</p>
                                            </div>
                                            <button v-if="task.can_complete" type="button" class="cursor-pointer rounded-md border border-emerald-300 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest text-emerald-700 hover:bg-emerald-50" :disabled="taskCompletingIds.includes(task.id)" @click="completeTask(task)">Complete</button>
                                        </div>
                                    </article>
                                </div>
                            </div>
                                    </div>
                                </section>

                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Receipt History</h3>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.receipts ? 'true' : 'false'" aria-label="Toggle receipt history" @click="sectionOpen.receipts = !sectionOpen.receipts">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.receipts ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.receipts" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
                                <div v-if="receipts.length > 0" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received At</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received By</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lines</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="receipt in receipts" :key="receipt.id">
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ receipt.received_at || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ receipt.received_by || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ receipt.reference || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ receipt.notes || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ receiptLineSummary(receipt) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                                    No receipts yet.
                                </div>
                                    </div>
                                </section>

                                <section class="-mx-1 !-mt-px w-auto min-w-0 overflow-visible border border-gray-500 bg-white shadow-sm first:!mt-0 sm:mx-0 sm:!mt-6 sm:first:!mt-0 sm:w-full sm:rounded-2xl sm:border-gray-200" data-detail-section-card>
                                    <div class="flex items-start justify-between gap-2 bg-blue-50 px-3 py-4 sm:gap-3 sm:px-6 sm:py-5">
                                        <div class="min-w-0 flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 sm:text-lg">Short-Close History</h3>
                                        </div>
                                        <button type="button" class="inline-flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-8 sm:w-8 sm:rounded-lg" :aria-expanded="sectionOpen.shortClosures ? 'true' : 'false'" aria-label="Toggle short-close history" @click="sectionOpen.shortClosures = !sectionOpen.shortClosures">
                                            <svg class="h-2.5 w-2.5 text-gray-400 transition duration-[400ms] ease-in-out sm:h-4 sm:w-4" :class="sectionOpen.shortClosures ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-show="sectionOpen.shortClosures" class="border-t border-gray-100 bg-white px-3 py-2 sm:px-6 sm:py-5" data-detail-section-body>
                                <div v-if="shortClosures.length > 0" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Short-Closed At</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Short-Closed By</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lines</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="shortClose in shortClosures" :key="shortClose.id">
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ shortClose.short_closed_at || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ shortClose.short_closed_by || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ shortClose.reference || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ shortClose.notes || "—" }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700">{{ shortCloseLineSummary(shortClose) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                                    No short-closes yet.
                                </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <BaseDrawer :open="taskOpen" labelled-by="purchase-order-task-title" @close="taskOpen = false">
                <form class="flex h-full flex-col" @submit.prevent="submitTask">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 id="purchase-order-task-title" class="text-sm font-semibold">Create Task</h2></div>
                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                        <input v-model="taskForm.title" type="text" required maxlength="255" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Title">
                        <p v-if="taskErrors.title?.length" class="text-sm text-red-600">{{ taskErrors.title[0] }}</p>
                        <select v-model="taskForm.assigned_to_user_id" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select user</option>
                            <option v-for="user in taskUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                        </select>
                        <input v-model="taskForm.due_date" type="date" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @click="$event.target.showPicker?.()">
                        <textarea v-model="taskForm.description" rows="4" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Description" />
                        <p v-if="taskError" class="text-sm text-red-600">{{ taskError }}</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                        <button type="button" class="cursor-pointer rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="taskOpen = false">Cancel</button>
                        <button type="submit" class="cursor-pointer rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="taskSubmitting">Create Task</button>
                    </div>
                </form>
            </BaseDrawer>

            <BaseDrawer :open="receiveOpen" labelled-by="purchase-order-receive-title" @close="receiveOpen = false">
                <form class="flex h-full flex-col" @submit.prevent="submitReceive">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 id="purchase-order-receive-title" class="text-sm font-semibold">Receive Purchase Order</h2></div>
                    <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <input v-model="receiveForm.received_at" type="datetime-local" step="1" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <input v-model="receiveForm.reference" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Reference">
                        <textarea v-model="receiveForm.notes" rows="3" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Notes" />
                        <div v-for="(line, index) in receiveForm.lines" :key="line.id" class="rounded-lg border border-gray-200 p-3">
                            <p class="text-sm font-medium text-gray-900">{{ line.item_name }}</p>
                            <p class="mt-1 text-xs text-gray-500">Remaining {{ line.remaining_balance_display }} {{ line.unit_context }}</p>
                            <input v-model="line.received_quantity" inputmode="numeric" class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p v-if="receiveLineErrors[index]" class="mt-1 text-xs text-red-600">{{ receiveLineErrors[index] }}</p>
                        </div>
                        <p v-if="receiveError" class="text-sm text-red-600">{{ receiveError }}</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                        <button type="button" class="cursor-pointer rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="receiveOpen = false">Cancel</button>
                        <button type="submit" class="cursor-pointer rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="receiveSubmitting">Receive</button>
                    </div>
                </form>
            </BaseDrawer>

            <BaseDrawer :open="shortCloseOpen" labelled-by="purchase-order-short-close-title" @close="shortCloseOpen = false">
                <form class="flex h-full flex-col" @submit.prevent="submitShortClose">
                    <div class="border-b border-slate-200 px-5 py-4"><h2 id="purchase-order-short-close-title" class="text-sm font-semibold">Short-Close Line</h2><p class="mt-1 text-sm text-gray-600">{{ shortCloseLineLabel }}</p></div>
                    <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                        <input v-model="shortCloseForm.short_closed_at" type="datetime-local" step="1" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <input v-model="shortCloseForm.reference" type="text" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Reference">
                        <textarea v-model="shortCloseForm.notes" rows="3" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Notes" />
                        <input v-model="shortCloseForm.short_closed_quantity" inputmode="numeric" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p v-if="shortCloseError" class="text-sm text-red-600">{{ shortCloseError }}</p>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                        <button type="button" class="cursor-pointer rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="shortCloseOpen = false">Cancel</button>
                        <button type="submit" class="cursor-pointer rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="shortCloseSubmitting">Short-Close</button>
                    </div>
                </form>
            </BaseDrawer>
        </div>
    </AuthShell>
</template>
