<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";

import UiToast from "../../components/UiToast.vue";
import AuthShell from "../../layouts/AuthShell.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    profileHub: {
        type: Object,
        required: true,
    },
});

const profile = computed(() => props.profileHub.profile || {});
const toast = reactive({
    visible: Boolean(props.profileHub.status),
    type: "success",
    message: statusMessage(props.profileHub.status),
});
const profileForm = useForm({
    name: profile.value.user?.name || "",
    email: profile.value.user?.email || "",
    currency_code: profile.value.user?.currency_code || "USD",
});
const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});
const deleteForm = useForm({
    password: "",
});
const verificationForm = useForm({});
const deleteOpen = ref(false);
const passwordConfirmationError = computed(() => {
    if (passwordForm.errors.password_confirmation) {
        return passwordForm.errors.password_confirmation;
    }

    if (
        passwordForm.errors.password
        && passwordForm.errors.password.toLowerCase().includes("confirmation")
    ) {
        return passwordForm.errors.password;
    }

    return "";
});

function statusMessage(status) {
    if (status === "profile-updated") {
        return "Profile updated.";
    }

    if (status === "password-updated") {
        return "Password updated.";
    }

    return status || "";
}

function showToast(message, type = "success") {
    toast.message = message;
    toast.type = type;
    toast.visible = true;
}

watch(
    () => props.profileHub.status,
    (status) => {
        if (status) {
            showToast(statusMessage(status), "success");
        }
    },
);

function submitProfile() {
    profileForm.patch(profile.value.updateUrl, {
        preserveScroll: true,
    });
}

function submitPassword() {
    passwordForm.put(profile.value.passwordUpdateUrl, {
        errorBag: "updatePassword",
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function submitDelete() {
    deleteForm.delete(profile.value.destroyUrl, {
        errorBag: "userDeletion",
        preserveScroll: true,
    });
}

function sendVerification() {
    verificationForm.post(profile.value.verificationUrl, {
        preserveScroll: true,
        onError: () => showToast("Verification email could not be sent.", "error"),
    });
}
</script>

<template>
    <Head title="Profile" />

    <AuthShell :shell="shell" title="Profile">
        <UiToast
            v-model:visible="toast.visible"
            :message="toast.message"
            :type="toast.type"
        />

        <div class="flex h-full min-h-0 flex-col overflow-hidden bg-[#f7f9fc] px-0 md:px-4 lg:px-8">
            <div class="min-h-0 flex-1 overflow-y-auto py-4">
                <div class="mx-auto w-full max-w-7xl space-y-4 px-4 sm:px-5 md:px-0">
                    <section class="shrink-0 pb-2">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-[#315492]">Account</p>
                        <h1 class="mt-1 text-3xl font-bold leading-tight text-[#111b31]">Profile</h1>
                        <p class="mt-1 max-w-2xl text-xs text-slate-600">
                            Manage your account details and password.
                        </p>
                    </section>

                    <form class="rounded-lg border border-slate-200 bg-white px-7 py-6 shadow-sm" @submit.prevent="submitProfile">
                        <div class="flex items-start gap-5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#f0f4fc] text-[#12306b]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 21a8 8 0 0 0-16 0" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-bold text-[#111b31]">Profile Information</h2>
                                <p class="mt-1 text-xs text-slate-500">Update your name, email, and account currency.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-2">
                            <label class="block text-xs font-semibold text-slate-600">
                                Name
                                <span class="relative mt-2 block">
                                    <input v-model="profileForm.name" type="text" class="block h-11 w-full rounded-md border-slate-300 pr-10 text-sm font-medium text-[#111b31] shadow-sm focus:border-[#1849ff] focus:ring-[#1849ff]">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M5 8h2v2H5V8Zm4 0h2v2H9V8Zm4 0h2v2h-2V8Z" />
                                        </svg>
                                    </span>
                                </span>
                                <span v-if="profileForm.errors.name" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.name }}</span>
                            </label>

                            <label class="block text-xs font-semibold text-slate-600">
                                Email
                                <input v-model="profileForm.email" type="email" class="mt-2 block h-11 w-full rounded-md border-slate-300 text-sm font-medium text-[#111b31] shadow-sm focus:border-[#1849ff] focus:ring-[#1849ff]">
                                <span v-if="profileForm.errors.email" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.email }}</span>
                            </label>

                            <label class="block text-xs font-semibold text-slate-600">
                                Currency
                                <select v-model="profileForm.currency_code" class="mt-2 block h-11 w-64 rounded-md border-slate-300 text-sm font-medium uppercase text-[#111b31] shadow-sm focus:border-[#1849ff] focus:ring-[#1849ff]">
                                    <option :value="profileForm.currency_code">{{ profileForm.currency_code }}</option>
                                </select>
                                <span v-if="profileForm.errors.currency_code" class="mt-1 block text-xs text-red-600">{{ profileForm.errors.currency_code }}</span>
                            </label>
                        </div>

                        <div v-if="profile.mustVerifyEmail && !profile.user?.email_verified" class="mt-4 border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                            Your email address is unverified.
                            <button type="button" class="cursor-pointer font-semibold underline" @click="sendVerification">
                                Send verification email.
                            </button>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="cursor-pointer rounded-md bg-[#061a4a] px-6 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[#102862] disabled:cursor-not-allowed disabled:opacity-60" :disabled="profileForm.processing">
                                Save Changes
                            </button>
                        </div>
                    </form>

                    <form class="rounded-lg border border-slate-200 bg-white px-7 py-6 shadow-sm" @submit.prevent="submitPassword">
                        <div class="flex items-start gap-5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#f0f4fc] text-[#12306b]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="5" y="11" width="14" height="10" rx="2" />
                                    <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-bold text-[#111b31]">Update Password</h2>
                                <p class="mt-1 text-xs text-slate-500">Use a long, random password to keep your account secure.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-6 md:grid-cols-3">
                            <label class="block text-xs font-semibold text-slate-600">
                                Current password
                                <input v-model="passwordForm.current_password" type="password" class="mt-2 block h-11 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#1849ff] focus:ring-[#1849ff]">
                                <span v-if="passwordForm.errors.current_password" class="mt-1 block text-xs text-red-600">{{ passwordForm.errors.current_password }}</span>
                            </label>

                            <label class="block text-xs font-semibold text-slate-600">
                                New password
                                <input v-model="passwordForm.password" type="password" class="mt-2 block h-11 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#1849ff] focus:ring-[#1849ff]">
                                <span v-if="passwordForm.errors.password" class="mt-1 block text-xs text-red-600">{{ passwordForm.errors.password }}</span>
                            </label>

                            <label class="block text-xs font-semibold text-slate-600">
                                Confirm password
                                <input v-model="passwordForm.password_confirmation" type="password" class="mt-2 block h-11 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#1849ff] focus:ring-[#1849ff]">
                                <span v-if="passwordConfirmationError" class="mt-1 block text-xs text-red-600">{{ passwordConfirmationError }}</span>
                            </label>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="cursor-pointer rounded-md bg-[#061a4a] px-6 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[#102862] disabled:cursor-not-allowed disabled:opacity-60" :disabled="passwordForm.processing">
                                Save Changes
                            </button>
                        </div>
                    </form>

                    <section class="rounded-lg border border-red-200 bg-red-50/70 px-7 py-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-5">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 6h18" />
                                        <path d="M8 6V4h8v2" />
                                        <path d="M19 6l-1 15H6L5 6" />
                                        <path d="M10 11v6" />
                                        <path d="M14 11v6" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-red-700">Delete Account</h2>
                                    <p class="mt-1 text-xs text-slate-600">This permanently deletes your account.</p>
                                </div>
                            </div>
                            <button type="button" class="cursor-pointer rounded-md border border-red-400 bg-white px-6 py-3 text-xs font-semibold text-red-700 transition hover:bg-red-50" @click="deleteOpen = true">
                                Delete Account
                            </button>
                        </div>

                        <form v-if="deleteOpen" class="mt-4 border-t border-red-100 pt-4" @submit.prevent="submitDelete">
                            <label class="block text-xs font-semibold text-slate-600">
                                Password
                                <input v-model="deleteForm.password" type="password" class="mt-1 block w-full max-w-sm border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500">
                                <span v-if="deleteForm.errors.password" class="mt-1 block text-xs text-red-600">{{ deleteForm.errors.password }}</span>
                            </label>
                            <div class="mt-4 flex gap-2">
                                <button type="button" class="cursor-pointer border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700" @click="deleteOpen = false">
                                    Cancel
                                </button>
                                <button type="submit" class="cursor-pointer bg-red-700 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white" :disabled="deleteForm.processing">
                                    Delete Account
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AuthShell>
</template>
