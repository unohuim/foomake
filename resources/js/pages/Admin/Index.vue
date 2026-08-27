<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

import ResourceIndex from "../../components/ResourceIndex.vue";
import WorkflowsPanel from "../../components/admin/WorkflowsPanel.vue";
import ConnectorsPanel from "../../components/connectors/ConnectorsPanel.vue";
import AuthShell from "../../layouts/AuthShell.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    adminHub: {
        type: Object,
        required: true,
    },
});

const activeTab = computed(() => props.adminHub.activeTab || "billing");
const notice = computed(() => props.adminHub.notice || null);
const dismissedNotice = ref(false);
const billing = computed(() => props.adminHub.billing || {});
const connectors = computed(() => props.adminHub.connectors || null);
const workflows = computed(() => props.adminHub.workflows || null);
const users = computed(() => props.adminHub.users || {});
const userSearch = ref("");
const userRows = ref([...(users.value.rows || [])]);
const usersLoading = ref(false);
const usersError = ref("");
const inviteOpen = ref(false);
const inviteSubmitting = ref(false);
const inviteErrors = ref({});
const inviteForm = reactive({
    email: "",
    role_id: "",
});

const filteredUserRows = computed(() => {
    const search = userSearch.value.trim().toLowerCase();

    if (!search) {
        return userRows.value;
    }

    return userRows.value.filter((row) => [
        row.name,
        row.email,
        row.role,
        row.status,
    ].join(" ").toLowerCase().includes(search));
});

function visitTab(tab) {
    if (tab.key === activeTab.value) {
        return;
    }

    router.get(tab.url, {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

async function fetchUsers(nextSearch = userSearch.value) {
    userSearch.value = nextSearch;

    if (!users.value.listUrl) {
        return;
    }

    usersLoading.value = true;
    usersError.value = "";

    try {
        const params = new URLSearchParams({
            search: userSearch.value,
            sort: "name",
            direction: "asc",
        });
        const response = await fetch(`${users.value.listUrl}?${params.toString()}`, {
            headers: {
                Accept: "application/json",
            },
        });
        const data = await response.json();

        if (!response.ok) {
            usersError.value = data.message || "Unable to load users.";
            return;
        }

        userRows.value = data.data || [];
    } catch (error) {
        usersError.value = "Unable to load users.";
    } finally {
        usersLoading.value = false;
    }
}

async function submitInvite() {
    inviteSubmitting.value = true;
    inviteErrors.value = {};

    try {
        const response = await fetch(users.value.storeInvitationUrl, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": users.value.csrfToken,
            },
            body: JSON.stringify({
                email: inviteForm.email,
                role_id: inviteForm.role_id,
            }),
        });
        const data = await response.json();

        if (!response.ok) {
            inviteErrors.value = data.errors || {};
            return;
        }

        inviteForm.email = "";
        inviteForm.role_id = "";
        inviteOpen.value = false;
        await fetchUsers();
    } finally {
        inviteSubmitting.value = false;
    }
}
</script>

<template>
    <Head title="Admin" />

    <AuthShell :shell="shell" title="Admin">
        <div class="flex h-full min-h-0 flex-col overflow-hidden bg-[#f7f9fc] px-0 md:px-4 lg:px-8">
            <div class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col px-4 py-4 sm:px-5 md:px-0">
                <div v-if="notice && !dismissedNotice" class="mb-3 flex shrink-0 items-center gap-3 rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-medium text-orange-900">
                    <span class="min-w-0 flex-1">{{ notice.message }}</span>
                    <button
                        type="button"
                        class="inline-flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded text-orange-800 transition hover:bg-orange-100 hover:text-orange-950"
                        aria-label="Dismiss notice"
                        @click="dismissedNotice = true"
                    >
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="shrink-0 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm" aria-label="Admin sections">
                    <div class="flex min-w-max gap-1 px-4">
                        <button
                            v-for="tab in adminHub.tabs"
                            :key="tab.key"
                            type="button"
                            class="relative cursor-pointer px-5 py-4 text-xs font-semibold transition"
                            :class="tab.key === activeTab ? 'text-[#1849ff]' : 'text-[#111b31] hover:text-[#1849ff]'"
                            @click="visitTab(tab)"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.key === activeTab"
                                class="absolute inset-x-4 bottom-0 h-0.5 rounded-full bg-[#1849ff]"
                            />
                        </button>
                    </div>
                </nav>

                <div
                    class="min-h-0 flex-1"
                    :class="activeTab === 'connectors' ? 'overflow-hidden' : 'overflow-y-auto'"
                >
                    <section v-if="activeTab === 'billing'" class="max-w-5xl py-4">
                        <section v-if="billing.checkoutMessage" class="mb-4 border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 shadow-sm">
                            {{ billing.checkoutMessage }}
                        </section>

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <div class="border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-sm font-semibold text-[#111b31]">{{ billing.tenantName }}</h2>
                                        <p class="mt-1 text-xs text-slate-500">Subscription and trial status.</p>
                                    </div>
                                    <span class="inline-flex shrink-0 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide" :class="billing.hasAccess ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'">
                                        {{ billing.hasAccess ? "Access active" : "Billing required" }}
                                    </span>
                                </div>

                                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="border border-slate-200 p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Trial ends</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ billing.trialEndsAt || "Not available" }}</dd>
                                    </div>
                                    <div class="border border-slate-200 p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Subscription</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ billing.subscriptionActive ? (billing.subscriptionPlanName ? `Active: ${billing.subscriptionPlanName}` : "Active") : "Not active" }}</dd>
                                    </div>
                                    <div class="border border-slate-200 p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Billing provider</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ billing.billingProvider || "Not connected" }}</dd>
                                    </div>
                                    <div class="border border-slate-200 p-3">
                                        <dt class="text-xs font-semibold text-slate-500">Temporary exemption</dt>
                                        <dd class="mt-1 text-sm text-slate-900">{{ billing.billingExempt ? "Active" : "None" }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <aside class="border border-slate-200 bg-slate-50 p-4">
                                <h3 class="text-sm font-semibold text-[#111b31]">Manage billing</h3>
                                <p class="mt-2 text-xs text-slate-600">
                                    {{ billing.trialActive && !billing.subscriptionActive ? "Add payment details now and keep access uninterrupted after the trial." : "Start or restore subscription access for this tenant." }}
                                </p>
                                <form v-if="billing.canManageBilling" class="mt-4" method="POST" :action="billing.checkoutUrl">
                                    <input type="hidden" name="_token" :value="billing.csrfToken">
                                    <button type="submit" class="w-full cursor-pointer bg-[#111b31] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white transition hover:bg-[#1d2943]">
                                        {{ billing.trialActive && !billing.subscriptionActive ? "Add payment details" : "Subscribe" }}
                                    </button>
                                </form>
                            </aside>
                        </div>
                    </section>

                    <ConnectorsPanel v-else-if="activeTab === 'connectors' && connectors" :connectors="connectors" />

                    <section v-else-if="activeTab === 'workflows' && workflows" class="py-4">
                        <WorkflowsPanel :payload="workflows" />
                    </section>

                    <section v-else-if="activeTab === 'users'" class="py-4">
                        <ResourceIndex
                            :labels="{ searchPlaceholder: 'Search users', createTitle: 'Invite Member', createAriaLabel: 'Invite Member', emptyState: 'No users or invitations found.' }"
                            :permissions="{ showCreate: users.canManageUsers, showExport: false, showImport: false }"
                            :records="filteredUserRows"
                            :search="userSearch"
                            :loading="usersLoading"
                            :error="usersError"
                            bounded-height-class="h-[calc(100vh-12rem)]"
                            @search="fetchUsers"
                            @create="inviteOpen = true"
                        >
                            <template #default="{ records }">
                                <div class="grid gap-3 p-3 sm:grid-cols-[repeat(auto-fit,minmax(16rem,1fr))]">
                                    <article v-for="row in records" :key="row.id" class="border border-slate-200 bg-white p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-[#111b31]">{{ row.name }}</p>
                                                <p class="truncate text-xs text-slate-500">{{ row.email }}</p>
                                            </div>
                                            <span class="shrink-0 bg-slate-100 px-2 py-1 text-[11px] font-semibold uppercase text-slate-600">{{ row.status }}</span>
                                        </div>
                                        <p class="mt-3 text-xs text-slate-600">{{ row.role }}</p>
                                    </article>
                                </div>
                            </template>
                        </ResourceIndex>

                        <div v-if="inviteOpen" class="fixed inset-0 z-[1200] bg-black/30" @click.self="inviteOpen = false">
                            <form class="ml-auto flex h-full w-full max-w-sm flex-col bg-white p-4 shadow-xl" @submit.prevent="submitInvite">
                                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                                    <div>
                                        <h2 class="text-sm font-semibold text-[#111b31]">Invite member</h2>
                                        <p class="mt-1 text-xs text-slate-500">Choose the role this member receives when they accept.</p>
                                    </div>
                                    <button type="button" class="cursor-pointer text-slate-500 hover:text-[#111b31]" @click="inviteOpen = false">x</button>
                                </div>

                                <div class="mt-4 space-y-4">
                                    <label class="block text-xs font-semibold text-slate-600">
                                        Email
                                        <input v-model="inviteForm.email" type="email" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-[#111b31] focus:ring-[#111b31]">
                                        <span v-if="inviteErrors.email?.[0]" class="mt-1 block text-xs text-red-600">{{ inviteErrors.email[0] }}</span>
                                    </label>

                                    <label class="block text-xs font-semibold text-slate-600">
                                        Role
                                        <select v-model="inviteForm.role_id" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-[#111b31] focus:ring-[#111b31]">
                                            <option value="">Choose a role</option>
                                            <option v-for="role in users.roles || []" :key="role.id" :value="role.id">{{ role.name }}</option>
                                        </select>
                                        <span v-if="inviteErrors.role_id?.[0]" class="mt-1 block text-xs text-red-600">{{ inviteErrors.role_id[0] }}</span>
                                    </label>
                                </div>

                                <div class="mt-auto flex justify-end gap-2 border-t border-slate-200 pt-3">
                                    <button type="button" class="cursor-pointer border border-slate-300 px-4 py-2 text-xs font-semibold uppercase text-slate-700" @click="inviteOpen = false">
                                        Cancel
                                    </button>
                                    <button type="submit" class="cursor-pointer bg-[#111b31] px-4 py-2 text-xs font-semibold uppercase text-white" :disabled="inviteSubmitting">
                                        Send invite
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthShell>
</template>
