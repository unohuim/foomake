<script setup>
import { computed, nextTick, ref, watch } from "vue";

import BaseDrawer from "./BaseDrawer.vue";

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    mode: {
        type: String,
        required: true,
        validator: (value) => ["login", "register"].includes(value),
    },
    logo: {
        type: Object,
        required: true,
    },
    login: {
        type: Object,
        required: true,
    },
    register: {
        type: Object,
        required: true,
    },
    passwordReset: {
        type: Object,
        required: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    "close",
    "switch-mode",
    "submit-login",
    "submit-register",
    "submit-password-reset",
    "update-login-field",
    "update-register-field",
    "update-password-reset-field",
]);

const loginEmail = ref(null);
const registerName = ref(null);
const resetEmail = ref(null);
const resetOpen = ref(false);

const isRegister = computed(() => props.mode === "register");
const isLogin = computed(() => props.mode === "login");

const revealPasswordReset = () => {
    resetOpen.value = true;

    nextTick(() => {
        resetEmail.value?.focus();
    });
};

const hidePasswordReset = () => {
    resetOpen.value = false;
};

const focusActivePanel = () => {
    nextTick(() => {
        window.setTimeout(() => {
            if (props.mode === "register") {
                registerName.value?.focus();
            } else if (resetOpen.value) {
                resetEmail.value?.focus();
            } else {
                loginEmail.value?.focus();
            }
        }, 500);
    });
};

watch(
    () => [props.open, props.mode],
    ([open]) => {
        if (open) {
            focusActivePanel();
        }
    },
);

watch(
    () => props.mode,
    () => {
        resetOpen.value = false;
    },
);
</script>

<template>
    <BaseDrawer
        :open="open"
        labelled-by="login-drawer-title"
        close-label="Close login drawer"
        @close="emit('close')"
    >
        <div class="flex h-full min-w-0 bg-white">
            <button
                type="button"
                class="cursor-pointer flex w-14 shrink-0 items-center justify-center bg-[#11284c] text-sm font-semibold uppercase text-white"
                @click.stop="emit('switch-mode', 'register')"
            >
                <span class="-rotate-90 whitespace-nowrap">Register</span>
            </button>

            <div
                class="min-w-0 overflow-hidden bg-white transition-[width,opacity] duration-500 ease-in-out"
                :class="isRegister ? 'w-[calc(100%-7rem)] opacity-100' : 'w-0 opacity-0'"
            >
                <div class="flex h-full w-[calc(100vw-7rem)] max-w-[21rem] flex-col justify-center overflow-y-auto px-6 py-10 sm:px-8">
                    <div class="mx-auto flex w-full max-w-sm flex-col items-center">
                        <img
                            v-if="logo.src"
                            :src="logo.src"
                            :alt="logo.alt"
                            class="h-32 w-auto object-contain sm:h-36"
                        >
                        <div
                            v-else
                            class="flex h-28 w-28 items-center justify-center rounded-2xl bg-blue-950 text-2xl font-semibold text-white shadow-sm"
                        >
                            FM
                        </div>

                        <h2 id="login-drawer-title" class="mt-8 text-center text-2xl font-semibold text-stone-950">
                            welcome
                        </h2>
                        <p class="mt-2 text-center text-sm text-stone-500">
                            your sanity awaits
                        </p>
                    </div>

                    <form
                        v-if="canRegister"
                        class="mx-auto mt-8 w-full max-w-sm"
                        @submit.prevent="emit('submit-register')"
                    >
                        <div>
                            <label for="drawer-name" class="block text-sm font-medium text-stone-700">Name</label>
                            <input
                                id="drawer-name"
                                ref="registerName"
                                type="text"
                                name="name"
                                :value="register.name"
                                required
                                autocomplete="name"
                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                @input="emit('update-register-field', 'name', $event.target.value)"
                            >
                            <p v-if="register.errors.name" class="mt-2 text-sm text-red-600">
                                {{ register.errors.name }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label for="drawer-register-email" class="block text-sm font-medium text-stone-700">Email</label>
                            <input
                                id="drawer-register-email"
                                type="email"
                                name="email"
                                :value="register.email"
                                required
                                autocomplete="username"
                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                @input="emit('update-register-field', 'email', $event.target.value)"
                            >
                            <p v-if="register.errors.email" class="mt-2 text-sm text-red-600">
                                {{ register.errors.email }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label for="drawer-register-password" class="block text-sm font-medium text-stone-700">Password</label>
                            <input
                                id="drawer-register-password"
                                type="password"
                                name="password"
                                :value="register.password"
                                required
                                autocomplete="new-password"
                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                @input="emit('update-register-field', 'password', $event.target.value)"
                            >
                            <p v-if="register.errors.password" class="mt-2 text-sm text-red-600">
                                {{ register.errors.password }}
                            </p>
                        </div>

                        <div class="mt-5">
                            <label for="drawer-password-confirmation" class="block text-sm font-medium text-stone-700">Confirm password</label>
                            <input
                                id="drawer-password-confirmation"
                                type="password"
                                name="password_confirmation"
                                :value="register.password_confirmation"
                                required
                                autocomplete="new-password"
                                class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                @input="emit('update-register-field', 'password_confirmation', $event.target.value)"
                            >
                        </div>

                        <button
                            type="submit"
                            class="cursor-pointer mt-7 flex w-full justify-center rounded-md bg-blue-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950 disabled:opacity-60"
                            :disabled="register.processing"
                        >
                            Create workspace
                        </button>
                    </form>
                </div>
            </div>

            <button
                type="button"
                class="cursor-pointer flex w-14 shrink-0 items-center justify-center border-l border-white bg-[#11284c] text-sm font-semibold uppercase text-white"
                @click.stop="emit('switch-mode', 'login')"
            >
                <span class="-rotate-90 whitespace-nowrap">Login</span>
            </button>

            <div
                class="min-w-0 overflow-hidden bg-white transition-[width,opacity] duration-500 ease-in-out"
                :class="isLogin ? 'w-[calc(100%-7rem)] opacity-100' : 'w-0 opacity-0'"
            >
                <div class="flex h-full w-[calc(100vw-7rem)] max-w-[21rem] flex-col justify-center overflow-y-auto px-6 py-10 sm:px-8">
                    <div class="mx-auto flex w-full max-w-sm flex-col items-center">
                        <img
                            v-if="logo.src"
                            :src="logo.src"
                            :alt="logo.alt"
                            class="h-32 w-auto object-contain sm:h-36"
                        >
                        <div
                            v-else
                            class="flex h-28 w-28 items-center justify-center rounded-2xl bg-blue-950 text-2xl font-semibold text-white shadow-sm"
                        >
                            FM
                        </div>

                        <h2 class="mt-8 text-center text-2xl font-semibold text-stone-950">
                            hello again
                        </h2>
                        <p class="mt-2 text-center text-sm text-stone-500">
                            Sign in to today's production board.
                        </p>
                    </div>

                    <div class="relative mx-auto mt-8 h-[18.75rem] w-full max-w-sm overflow-hidden">
                        <form
                            class="absolute inset-x-0 top-0 w-full transition-[opacity,transform] duration-500 ease-in-out"
                            :class="resetOpen ? '-translate-y-8 opacity-0 pointer-events-none' : 'translate-y-0 opacity-100'"
                            @submit.prevent="emit('submit-login')"
                        >
                            <div>
                                <label for="drawer-email" class="block text-sm font-medium text-stone-700">Email</label>
                                <input
                                    id="drawer-email"
                                    ref="loginEmail"
                                    type="email"
                                    name="email"
                                    :value="login.email"
                                    required
                                    autocomplete="username"
                                    class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                    @input="emit('update-login-field', 'email', $event.target.value)"
                                >
                                <p v-if="login.errors.email" class="mt-2 text-sm text-red-600">
                                    {{ login.errors.email }}
                                </p>
                            </div>

                            <div class="mt-5">
                                <label for="drawer-password" class="block text-sm font-medium text-stone-700">Password</label>
                                <input
                                    id="drawer-password"
                                    type="password"
                                    name="password"
                                    :value="login.password"
                                    required
                                    autocomplete="current-password"
                                    class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                    @input="emit('update-login-field', 'password', $event.target.value)"
                                >
                                <p v-if="login.errors.password" class="mt-2 text-sm text-red-600">
                                    {{ login.errors.password }}
                                </p>
                            </div>

                            <div class="mt-5 flex items-center justify-between">
                                <label for="drawer-remember" class="flex items-center gap-2 text-sm text-stone-600">
                                    <input
                                        id="drawer-remember"
                                        type="checkbox"
                                        name="remember"
                                        :checked="login.remember"
                                        class="rounded border-slate-300 text-blue-950 shadow-sm focus:ring-blue-950"
                                        @change="emit('update-login-field', 'remember', $event.target.checked)"
                                    >
                                    Remember me
                                </label>

                                <button
                                    type="button"
                                    class="cursor-pointer text-sm font-medium text-blue-950 transition hover:text-blue-900"
                                    @click="revealPasswordReset"
                                >
                                    Forgot password?
                                </button>
                            </div>

                            <button
                                type="submit"
                                class="cursor-pointer mt-7 flex w-full justify-center rounded-md bg-blue-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950 disabled:opacity-60"
                                :disabled="login.processing"
                            >
                                Log in
                            </button>
                        </form>

                        <form
                            class="absolute inset-x-0 top-0 rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 transition-[opacity,transform] duration-500 ease-in-out"
                            :class="resetOpen ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0 pointer-events-none'"
                            @submit.prevent="emit('submit-password-reset')"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-stone-950">Reset password</h3>
                                    <p class="mt-1 text-xs leading-5 text-stone-500">Enter your email and we will send a reset link.</p>
                                </div>
                                <button
                                    type="button"
                                    class="cursor-pointer flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-stone-500 transition hover:bg-white hover:text-stone-900"
                                    aria-label="Hide password reset form"
                                    @click="hidePasswordReset"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-4">
                                <label for="drawer-reset-email" class="block text-sm font-medium text-stone-700">Email</label>
                                <input
                                    id="drawer-reset-email"
                                    ref="resetEmail"
                                    type="email"
                                    name="email"
                                    :value="passwordReset.email"
                                    required
                                    autocomplete="username"
                                    class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm focus:border-blue-950 focus:ring-blue-950 sm:text-sm"
                                    @input="emit('update-password-reset-field', 'email', $event.target.value)"
                                >
                                <p v-if="passwordReset.errors.email" class="mt-2 text-sm text-red-600">
                                    {{ passwordReset.errors.email }}
                                </p>
                                <p v-if="passwordReset.recentlySuccessful" class="mt-2 text-sm text-green-700">
                                    Reset link sent.
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="cursor-pointer mt-4 flex w-full justify-center rounded-md bg-blue-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 disabled:opacity-60"
                                :disabled="passwordReset.processing"
                            >
                                Send Link
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </BaseDrawer>
</template>
