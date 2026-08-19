<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";

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

const customer = computed(() => payload.value?.customer ?? {});
const contacts = computed(() => payload.value?.contacts ?? []);
const orders = computed(() => payload.value?.orders ?? []);
const canManage = computed(() => Boolean(payload.value?.canManage));
const canManageOrders = computed(() => Boolean(payload.value?.canManageOrders));
const statuses = computed(() => optionEntries(payload.value?.statuses ?? []));
const pageTitle = computed(() => {
    const name = String(customer.value?.name ?? "").trim();

    return name === "" ? props.title : `Customer: ${name}`;
});
const customerInitials = computed(() => {
    const words = String(customer.value?.name ?? "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return "CU";
    }

    return words.slice(0, 2).map((word) => word[0]).join("").toUpperCase();
});
const locationDisplay = computed(() => {
    const parts = [
        customer.value?.city,
        customer.value?.region,
        customer.value?.country_code,
    ].filter((part) => String(part ?? "").trim() !== "");

    return parts.length === 0 ? "-" : parts.join(", ");
});
const addressDisplay = computed(() => customer.value?.address_summary || customer.value?.formatted_address || "No address on file");
const notesDisplay = computed(() => {
    const notes = String(customer.value?.notes ?? "").trim();

    return notes === "" ? "-" : notes;
});

const editOpen = ref(false);
const editSubmitting = ref(false);
const editError = ref("");
const editErrors = ref({});
const editForm = reactive(emptyCustomerForm());

const contactOpen = ref(false);
const contactSubmitting = ref(false);
const contactMode = ref("create");
const editingContactId = ref(null);
const contactError = ref("");
const contactErrors = ref({});
const contactForm = reactive(emptyContactForm());
const taskOpen = ref(false);
const taskSubmitting = ref(false);
const taskError = ref("");
const taskErrors = ref({});
const taskForm = reactive(emptyTaskForm());
const createdTasks = ref([]);
const taskCompletingIds = ref([]);
const taskUsers = computed(() => payload.value?.taskCreate?.users ?? []);
const breadcrumbItems = computed(() => [
    {
        label: "Customers",
        url: props.indexUrl,
        current: false,
    },
    {
        label: customer.value?.name ?? "",
        url: null,
        current: true,
    },
]);

function emptyCustomerForm() {
    return {
        name: "",
        status: "active",
        currency_code: "",
        notes: "",
        address_line_1: "",
        address_line_2: "",
        city: "",
        region: "",
        postal_code: "",
        country_code: "",
        formatted_address: "",
    };
}

function emptyContactForm() {
    return {
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        role: "",
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

function optionEntries(options) {
    if (Array.isArray(options)) {
        return options.map((option) => ({
            value: option.value ?? option.key ?? option,
            label: option.label ?? option.name ?? option,
        }));
    }

    return Object.entries(options ?? {}).map(([value, label]) => ({ value, label }));
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
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
    }, 1800);
}

async function loadPayload() {
    loading.value = true;
    pageError.value = "";

    const response = await fetch(props.payloadUrl, {
        headers: { Accept: "application/json" },
    });

    if (!response.ok) {
        pageError.value = "Unable to load this customer.";
        loading.value = false;
        return;
    }

    const data = await response.json();
    payload.value = data.data ?? {};
    loading.value = false;
}

function customerToForm() {
    Object.assign(editForm, emptyCustomerForm(), {
        name: customer.value.name ?? "",
        status: customer.value.status ?? "active",
        currency_code: customer.value.currency_code ?? "",
        notes: customer.value.notes ?? "",
        address_line_1: customer.value.address_line_1 ?? "",
        address_line_2: customer.value.address_line_2 ?? "",
        city: customer.value.city ?? "",
        region: customer.value.region ?? "",
        postal_code: customer.value.postal_code ?? "",
        country_code: customer.value.country_code ?? "",
        formatted_address: customer.value.formatted_address ?? "",
    });
}

function openEdit() {
    if (!canManage.value) {
        return;
    }

    customerToForm();
    editErrors.value = {};
    editError.value = "";
    editOpen.value = true;
}

async function submitEdit() {
    if (editSubmitting.value || !canManage.value) {
        return;
    }

    editSubmitting.value = true;
    editErrors.value = {};
    editError.value = "";

    const response = await fetch(payload.value.updateUrl, {
        method: "PATCH",
        headers: csrfHeaders(),
        body: JSON.stringify({
            ...editForm,
            currency_code: editForm.currency_code || null,
            notes: editForm.notes || null,
        }),
    });

    if (response.status === 422) {
        const data = await response.json();
        editErrors.value = data.errors ?? {};
        editError.value = data.message ?? "Validation failed.";
        editSubmitting.value = false;
        return;
    }

    if (!response.ok) {
        editError.value = "Unable to update customer.";
        editSubmitting.value = false;
        return;
    }

    const data = await response.json();
    payload.value.customer = data.data;
    editOpen.value = false;
    editSubmitting.value = false;
    showToast("Customer updated.");
}

async function archiveCustomer() {
    if (!canManage.value) {
        return;
    }

    const response = await fetch(payload.value.deleteUrl, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": payload.value.csrfToken ?? "",
        },
    });

    if (!response.ok) {
        showToast("Unable to archive customer.", "error");
        return;
    }

    router.visit(payload.value.indexUrl ?? props.indexUrl);
}

function contactToForm(contact = null) {
    Object.assign(contactForm, emptyContactForm(), {
        first_name: contact?.first_name ?? "",
        last_name: contact?.last_name ?? "",
        email: contact?.email ?? "",
        phone: contact?.phone ?? "",
        role: contact?.role ?? "",
    });
}

function openContactCreate() {
    if (!canManage.value) {
        return;
    }

    contactMode.value = "create";
    editingContactId.value = null;
    contactToForm();
    contactErrors.value = {};
    contactError.value = "";
    contactOpen.value = true;
}

function openContactEdit(contact) {
    if (!canManage.value) {
        return;
    }

    contactMode.value = "edit";
    editingContactId.value = contact.id;
    contactToForm(contact);
    contactErrors.value = {};
    contactError.value = "";
    contactOpen.value = true;
}

function upsertContact(contact) {
    const nextContacts = [...contacts.value];
    const index = nextContacts.findIndex((entry) => entry.id === contact.id);

    if (index === -1) {
        nextContacts.push(contact);
    } else {
        nextContacts.splice(index, 1, contact);
    }

    nextContacts.sort((left, right) => {
        if (left.is_primary === right.is_primary) {
            return String(left.full_name ?? "").localeCompare(String(right.full_name ?? ""));
        }

        return left.is_primary ? -1 : 1;
    });

    payload.value.contacts = nextContacts;
}

async function submitContact() {
    if (contactSubmitting.value || !canManage.value) {
        return;
    }

    contactSubmitting.value = true;
    contactErrors.value = {};
    contactError.value = "";

    const creating = contactMode.value === "create";
    const url = creating
        ? payload.value.contactsStoreUrl
        : `${payload.value.contactsBaseUrl}/${editingContactId.value}`;
    const method = creating ? "POST" : "PATCH";

    const response = await fetch(url, {
        method,
        headers: csrfHeaders(),
        body: JSON.stringify({
            ...contactForm,
            email: contactForm.email || null,
            phone: contactForm.phone || null,
            role: contactForm.role || null,
        }),
    });

    if (response.status === 422) {
        const data = await response.json();
        contactErrors.value = data.errors ?? {};
        contactError.value = data.message ?? "Validation failed.";
        contactSubmitting.value = false;
        return;
    }

    if (!response.ok) {
        contactError.value = "Unable to save contact.";
        contactSubmitting.value = false;
        return;
    }

    const data = await response.json();
    upsertContact(data.data);
    contactOpen.value = false;
    contactSubmitting.value = false;
    showToast(creating ? "Contact created." : "Contact updated.");
}

async function setPrimary(contact) {
    const response = await fetch(`${payload.value.contactsBaseUrl}/${contact.id}/primary`, {
        method: "PATCH",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": payload.value.csrfToken ?? "",
        },
    });

    if (!response.ok) {
        showToast("Unable to update primary contact.", "error");
        return;
    }

    const data = await response.json();
    payload.value.contacts = contacts.value.map((entry) => ({
        ...entry,
        is_primary: entry.id === data.data.id,
    }));
    upsertContact(data.data);
    showToast("Primary contact updated.");
}

async function deleteContact(contact) {
    const response = await fetch(`${payload.value.contactsBaseUrl}/${contact.id}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": payload.value.csrfToken ?? "",
        },
    });

    if (!response.ok) {
        showToast("Unable to delete contact.", "error");
        return;
    }

    payload.value.contacts = contacts.value.filter((entry) => entry.id !== contact.id);
    showToast("Contact deleted.");
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
            workflow_domain_id: null,
            domain_record_id: null,
            workflow_stage_id: null,
        }),
    });

    if (response.status === 422) {
        const data = await response.json();
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

    const data = await response.json();
    createdTasks.value = [...createdTasks.value, data.data ?? {}].filter((task) => Boolean(task.id));
    taskOpen.value = false;
    taskSubmitting.value = false;
    showToast("Task created.");
}

function taskStatusClasses(task) {
    return task?.is_completed
        ? "bg-emerald-100 text-emerald-700"
        : "bg-amber-100 text-amber-700";
}

async function completeCreatedTask(task) {
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
        createdTasks.value = createdTasks.value.map((entry) => (
            entry.id === data.data?.id ? data.data : entry
        ));
        showToast("Task completed.");
    } else {
        showToast("Unable to complete task.", "error");
    }

    taskCompletingIds.value = taskCompletingIds.value.filter((taskId) => taskId !== task.id);
}

async function updateOrderStatus(order, status) {
    const response = await fetch(order.status_update_url, {
        method: "PATCH",
        headers: csrfHeaders(),
        body: JSON.stringify({ status }),
    });

    if (!response.ok) {
        showToast("Unable to update status.", "error");
        return;
    }

    await loadPayload();
    showToast("Status updated.");
}

function money(amount, currencyCode) {
    return `${currencyCode ?? "USD"} ${amount ?? "0.00"}`;
}

function formatLineMoney(amount, currencyCode) {
    return `${currencyCode ?? "USD"} ${amount}`;
}

function formatOrderLineMoney(amount, lines) {
    const firstLine = (lines || [])[0];
    const currencyCode = firstLine ? firstLine.unit_price_currency_code : "USD";

    return formatLineMoney(amount, currencyCode);
}

function canChangeOrderStatus(order) {
    return Array.isArray(order?.available_status_transitions) && order.available_status_transitions.length > 0;
}

onMounted(loadPayload);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthShell :shell="shell" :title="pageTitle" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <div v-if="toast" class="fixed right-4 top-4 z-[1200] rounded-md px-4 py-2 text-sm font-medium shadow-lg" :class="toast.tone === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
                {{ toast.message }}
            </div>

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="pageTitle"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #header>
                    <div class="w-full bg-white px-4 py-4 sm:px-6 lg:px-8">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.85fr)] lg:divide-x lg:divide-slate-200">
                            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                                <div class="relative shrink-0">
                                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-violet-100 text-xl font-semibold text-indigo-800 shadow-inner">
                                        {{ customerInitials }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-2xl font-semibold leading-tight text-slate-950">{{ customer.name }}</h2>
                                    </div>
                                    <p class="mt-1 truncate text-xs font-medium text-slate-500 lg:hidden">{{ addressDisplay }}</p>

                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600">
                                            <svg class="h-3.5 w-3.5 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0" />
                                            </svg>
                                            {{ customer.customer_type_label || "Customer" }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600">
                                            <svg class="h-3.5 w-3.5 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5m-18 3.75h16.5m-14.25 3.75h6m-7.5 3h15a2.25 2.25 0 0 0 2.25-2.25v-9A2.25 2.25 0 0 0 19.5 5.25h-15A2.25 2.25 0 0 0 2.25 7.5v9A2.25 2.25 0 0 0 4.5 18.75Z" />
                                            </svg>
                                            Currency: {{ customer.currency_code || "Tenant default" }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="min-w-0 lg:pl-6">
                                <div class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7.5-5.4 7.5-12a7.5 7.5 0 0 0-15 0c0 6.6 7.5 12 7.5 12Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" />
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-slate-500">Location</p>
                                        <p class="mt-1 truncate text-sm font-medium text-slate-950">{{ locationDisplay }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden min-w-0 lg:block lg:pl-6">
                                <div class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 21V5.25A2.25 2.25 0 0 1 7.5 3h9a2.25 2.25 0 0 1 2.25 2.25V21M9 7.5h1.5M9 11.25h1.5M9 15h1.5M13.5 7.5H15M13.5 11.25H15M13.5 15H15" />
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-slate-500">Address</p>
                                        <p class="mt-1 line-clamp-2 text-sm font-medium text-slate-950">{{ addressDisplay }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden min-w-0 lg:block lg:pl-6">
                                <div class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-5.25Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 2.25V7.5a.75.75 0 0 0 .75.75h5.25" />
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-slate-500">Notes</p>
                                        <p class="mt-1 line-clamp-2 text-sm font-medium text-slate-950">{{ notesDisplay }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </ResourceDetailHeaderBreadcrumb>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading" class="py-12">
                    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm text-gray-500">Loading customer...</div>
                        </div>
                    </div>
                </div>

                <div v-else-if="pageError" class="py-12">
                    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6 text-sm text-red-600">{{ pageError }}</div>
                        </div>
                    </div>
                </div>

                <div v-else class="py-12">
                    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                        <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg" data-section="customer-contacts">
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Contacts</h3>
                                        <p class="mt-1 text-sm text-gray-600">Manage contact people for this customer from the detail view.</p>
                                    </div>

                                    <button v-if="canManage" type="button" class="cursor-pointer inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500" @click="openContactCreate">
                                        Add Contact
                                    </button>
                                </div>

                                <div v-if="contacts.length > 0" class="mt-6 space-y-4">
                                    <div v-for="contact in contacts" :key="contact.id" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="flex items-center gap-3">
                                                    <p class="text-base font-semibold text-gray-900">{{ contact.full_name }}</p>
                                                    <span v-if="contact.is_primary" class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700">Primary</span>
                                                </div>
                                                <p class="mt-2 text-sm text-gray-700">{{ contact.email || "-" }}</p>
                                                <p class="mt-1 text-sm text-gray-700">{{ contact.phone || "-" }}</p>
                                                <p class="mt-1 text-sm text-gray-700">{{ contact.role || "-" }}</p>
                                            </div>

                                            <div v-if="canManage" class="flex items-center gap-3">
                                                <button type="button" class="cursor-pointer text-blue-600 hover:text-blue-500" @click="openContactEdit(contact)">Edit</button>
                                                <button type="button" class="cursor-pointer text-red-600 hover:text-red-500" @click="deleteContact(contact)">Delete</button>
                                                <button v-if="!contact.is_primary" type="button" class="cursor-pointer text-gray-600 hover:text-gray-500" @click="setPrimary(contact)">Set Primary</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mt-6 rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                                    <p>No contacts yet.</p>
                                </div>
                            </div>
                        </section>

                        <section class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Tasks</h3>
                                        <p class="mt-1 text-sm text-gray-600">Create assigned tasks without linking them to this customer.</p>
                                    </div>
                                    <button type="button" class="cursor-pointer inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-10 sm:w-10" aria-label="Create task" @click="openTaskCreate">
                                        <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    </button>
                                </div>

                                <div v-if="createdTasks.length === 0" class="mt-6 rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                                    Manual tasks created here are assigned to the selected user only.
                                </div>

                                <div v-else class="mt-6 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                                    <div v-for="task in createdTasks" :key="task.id" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900">{{ task.title }}</p>
                                            <p v-if="task.description" class="mt-1 text-sm text-gray-500">{{ task.description }}</p>
                                            <p v-if="task.assigned_to_user_name" class="mt-1 text-xs text-gray-500">Assigned To: {{ task.assigned_to_user_name }}</p>
                                            <p v-if="task.due_date" class="mt-1 text-xs text-gray-500">Due: {{ task.due_date }}</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="taskStatusClasses(task)">{{ task.status || "open" }}</span>
                                            <button v-if="task.can_complete && !task.is_completed" type="button" class="cursor-pointer inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="taskCompletingIds.includes(task.id)" @click="completeCreatedTask(task)">
                                                Complete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section v-if="canManageOrders" class="bg-white border border-gray-100 shadow-sm sm:rounded-lg" data-section="customer-orders">
                            <div class="p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Orders</h3>
                                        <p class="mt-1 text-sm text-gray-600">Manage sales orders for this customer without leaving the detail page.</p>
                                    </div>

                                    <button type="button" class="inline-flex cursor-pointer items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50" :disabled="(payload?.orderItems ?? []).length === 0">
                                        Add Order
                                    </button>
                                </div>

                                <div v-if="orders.length > 0" class="mt-6 space-y-4">
                                    <div v-for="order in orders" :key="order.id" class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3">
                                                    <p class="text-base font-semibold text-gray-900">{{ order.customer_name || customer.name }}</p>
                                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-gray-700">{{ order.status }}</span>
                                                </div>
                                                <p class="mt-2 text-sm text-gray-700">{{ order.contact_name || "-" }}</p>
                                                <p class="mt-2 text-xs text-gray-500">{{ order.line_count || 0 }} line(s) - {{ formatOrderLineMoney(order.order_total_amount || "0.000000", order.lines) }}</p>
                                                <div v-if="canChangeOrderStatus(order)" class="mt-3 flex flex-wrap gap-2">
                                                    <button v-for="status in order.available_status_transitions" :key="`${order.id}-${status}`" type="button" class="cursor-pointer inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50" @click="updateOrderStatus(order, status)">
                                                        {{ status }}
                                                    </button>
                                                </div>

                                                <div v-if="(order.current_stage_tasks || []).length > 0" class="mt-4 rounded-2xl border border-gray-200 bg-white p-4">
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500">Checklist</p>
                                                    <div class="mt-3 space-y-2">
                                                        <div v-for="task in order.current_stage_tasks" :key="task.id" class="rounded-xl border border-gray-200 bg-gray-50 p-3">
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
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div v-if="(order.lines || []).length > 0" class="mt-4 space-y-2">
                                                    <div v-for="line in order.lines" :key="line.id" class="rounded-lg border border-gray-200 bg-white p-3">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div>
                                                                <p class="font-medium text-gray-900">{{ line.item_name }}</p>
                                                                <p class="mt-1 text-xs text-gray-500">{{ formatLineMoney(line.unit_price_amount, line.unit_price_currency_code) }}</p>
                                                                <p class="mt-1 text-xs text-gray-500">Total: {{ formatLineMoney(line.line_total_amount, line.unit_price_currency_code) }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mt-6 rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                                    <p>No orders for this customer yet.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <BaseDrawer :open="editOpen" labelled-by="customer-edit-title" panel-class="w-screen max-w-md" @close="editOpen = false">
            <form class="flex h-full flex-col" @submit.prevent="submitEdit">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="customer-edit-title" class="text-sm font-semibold">Edit Customer</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="editError" class="text-xs text-red-600">{{ editError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Name<input v-model="editForm.name" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    <p v-if="editErrors.name?.[0]" class="text-xs text-red-600">{{ editErrors.name[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">Status<select v-model="editForm.status" class="mt-1 block w-full border-slate-300 text-sm"><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select></label>
                    <label class="block text-xs font-medium text-slate-700">Currency<input v-model="editForm.currency_code" maxlength="3" class="mt-1 block w-full border-slate-300 text-sm uppercase" type="text" /></label>
                    <label class="block text-xs font-medium text-slate-700">Notes<textarea v-model="editForm.notes" class="mt-1 block w-full border-slate-300 text-sm" rows="3"></textarea></label>
                    <label class="block text-xs font-medium text-slate-700">Address line 1<input v-model="editForm.address_line_1" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    <label class="block text-xs font-medium text-slate-700">Address line 2<input v-model="editForm.address_line_2" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block text-xs font-medium text-slate-700">City<input v-model="editForm.city" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                        <label class="block text-xs font-medium text-slate-700">Region<input v-model="editForm.region" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block text-xs font-medium text-slate-700">Postal<input v-model="editForm.postal_code" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                        <label class="block text-xs font-medium text-slate-700">Country<input v-model="editForm.country_code" maxlength="2" class="mt-1 block w-full border-slate-300 text-sm uppercase" type="text" /></label>
                    </div>
                    <label class="block text-xs font-medium text-slate-700">Formatted address<textarea v-model="editForm.formatted_address" class="mt-1 block w-full border-slate-300 text-sm" rows="3"></textarea></label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="editOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="editSubmitting">Save</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="contactOpen" labelled-by="customer-contact-title" panel-class="w-screen max-w-sm" @close="contactOpen = false">
            <form class="flex h-full flex-col" @submit.prevent="submitContact">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="customer-contact-title" class="text-sm font-semibold">{{ contactMode === "create" ? "Add Contact" : "Edit Contact" }}</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="contactError" class="text-xs text-red-600">{{ contactError }}</p>
                    <label class="block text-xs font-medium text-slate-700">First name<input v-model="contactForm.first_name" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    <p v-if="contactErrors.first_name?.[0]" class="text-xs text-red-600">{{ contactErrors.first_name[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">Last name<input v-model="contactForm.last_name" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    <p v-if="contactErrors.last_name?.[0]" class="text-xs text-red-600">{{ contactErrors.last_name[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">Email<input v-model="contactForm.email" class="mt-1 block w-full border-slate-300 text-sm" type="email" /></label>
                    <label class="block text-xs font-medium text-slate-700">Phone<input v-model="contactForm.phone" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                    <label class="block text-xs font-medium text-slate-700">Role<input v-model="contactForm.role" class="mt-1 block w-full border-slate-300 text-sm" type="text" /></label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="contactOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="contactSubmitting">Save</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="taskOpen" labelled-by="customer-task-title" panel-class="w-screen max-w-sm" @close="taskOpen = false">
            <form class="flex h-full flex-col" @submit.prevent="submitTask">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="customer-task-title" class="text-sm font-semibold">Create Task</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="taskError" class="text-xs text-red-600">{{ taskError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Title<input v-model="taskForm.title" class="mt-1 block w-full border-slate-300 text-sm" type="text" required maxlength="255" /></label>
                    <p v-if="taskErrors.title?.[0]" class="text-xs text-red-600">{{ taskErrors.title[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">Assigned To<select v-model="taskForm.assigned_to_user_id" class="mt-1 block w-full border-slate-300 text-sm" required><option value="">Select user</option><option v-for="user in taskUsers" :key="user.id" :value="String(user.id)">{{ user.name }}</option></select></label>
                    <p v-if="taskErrors.assigned_to_user_id?.[0]" class="text-xs text-red-600">{{ taskErrors.assigned_to_user_id[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">Due Date<input v-model="taskForm.due_date" class="mt-1 block w-full border-slate-300 text-sm" type="date" /></label>
                    <label class="block text-xs font-medium text-slate-700">Description<textarea v-model="taskForm.description" class="mt-1 block w-full border-slate-300 text-sm" rows="4"></textarea></label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="taskOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="taskSubmitting">Create</button>
                </div>
            </form>
        </BaseDrawer>
    </AuthShell>
</template>
