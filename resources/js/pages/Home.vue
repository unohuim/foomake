<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

import AuthDrawer from "../components/AuthDrawer.vue";
import homeLogoSrc from "../../img/foomake_logo.png";
import GuestShell from "../layouts/GuestShell.vue";

const props = defineProps({
    logo: {
        type: Object,
        required: true,
    },
    hero: {
        type: Object,
        required: true,
    },
    workExamples: {
        type: Array,
        required: true,
    },
    learnLinks: {
        type: Array,
        required: true,
    },
    authRoutes: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const logoPayload = computed(() => ({
    ...props.logo,
    src: props.logo.src ?? homeLogoSrc,
}));

const prepItems = [
    ["Receive oat pack", "Supplier Pack · 25 kg"],
    ["Prep granola run", "Make Order #1042"],
    ["Pack maple bars", "Market Order #218"],
];

const shelfItems = [
    ["Oats", "82 kg"],
    ["Rye", "14 kg"],
    ["Honey", "9 L"],
];

const featureSummaries = [
    [
        "Made for small batches",
        "Materials, supplier packs, counts, and make orders stay close to the way you actually produce.",
    ],
    [
        "Useful on a phone",
        "Taskers can see and complete assigned work without stepping away from the bench.",
    ],
    [
        "Calm enough for daily use",
        "No giant ERP ceremony. Just the day's ingredients, batches, orders, and responsibilities.",
    ],
];

const drawerOpen = ref(false);
const drawerMode = ref("login");

const loginForm = useForm({
    email: "",
    password: "",
    remember: false,
});

const registerForm = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const openAuth = (mode = "login") => {
    drawerMode.value = mode;
    drawerOpen.value = true;
};

const openAuthAfterInitialPaint = (mode) => {
    drawerMode.value = mode;

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            drawerOpen.value = true;
        });
    });
};

const closeAuth = () => {
    drawerOpen.value = false;
};

const switchAuthMode = (mode) => {
    drawerMode.value = mode;
};

const updateLoginField = (field, value) => {
    loginForm[field] = value;
    loginForm.clearErrors(field);
};

const updateRegisterField = (field, value) => {
    registerForm[field] = value;
    registerForm.clearErrors(field);
};

const submitLogin = () => {
    loginForm.post(props.authRoutes.loginUrl, {
        preserveScroll: true,
    });
};

const submitRegister = () => {
    registerForm.post(props.authRoutes.registerUrl, {
        preserveScroll: true,
    });
};

onMounted(() => {
    const authMode = new URLSearchParams(window.location.search).get("auth");

    if (authMode === "register" || authMode === "login") {
        openAuthAfterInitialPaint(authMode);
        return;
    }

    if (window.location.hash === "#register") {
        openAuthAfterInitialPaint("register");
        return;
    }

    if (window.location.hash === "#login") {
        openAuthAfterInitialPaint("login");
    }
});
</script>

<template>
    <Head title="Small Food Manufacturing MRP" />

    <GuestShell
        :auth-user="authUser"
        :dashboard-url="authRoutes.dashboardUrl"
        :logo="logoPayload"
        @open-auth="openAuth"
    >
        <main>
            <section class="relative -mt-20 flex min-h-screen items-center overflow-hidden pt-20">
                <div class="absolute inset-x-0 bottom-0 -z-10 h-44 bg-white" />
                <div class="absolute right-0 top-20 -z-10 h-72 w-72 rounded-full bg-blue-100/70 blur-3xl" />

                <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 pb-14 pt-10 sm:px-6 lg:grid-cols-[0.92fr_1.08fr] lg:px-8 lg:pb-18 lg:pt-14">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-medium text-blue-950 shadow-sm">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-950" />
                            {{ hero.eyebrow }}
                        </div>

                        <h1 class="mt-7 text-5xl font-semibold leading-none text-stone-950 sm:text-6xl lg:text-7xl">
                            {{ hero.headline }}
                        </h1>

                        <p class="mt-6 max-w-xl text-base leading-7 text-stone-600 sm:text-lg">
                            {{ hero.body }}
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a
                                v-if="authUser"
                                :href="hero.authenticatedCtaUrl"
                                class="cursor-pointer inline-flex items-center justify-center rounded-md bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                            >
                                {{ hero.authenticatedCtaLabel }}
                            </a>
                            <button
                                v-else
                                type="button"
                                class="cursor-pointer group inline-flex items-center gap-4 rounded-full border border-blue-950/25 bg-transparent px-5 py-3 text-sm font-semibold text-blue-950 hover:border-2 hover:border-blue-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                                @click="openAuth('login')"
                            >
                                {{ hero.primaryCtaLabel }}
                                <span class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-blue-950/30 group-hover:border-2 group-hover:border-blue-300">
                                    <svg class="relative h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                                    </svg>
                                </span>
                            </button>
                        </div>

                        <div class="mt-10 max-w-xl border-y border-stone-200 py-5">
                            <p class="text-sm font-semibold text-stone-950">Built around the work you already do:</p>
                            <div class="mt-4 grid gap-3 text-sm text-stone-600 sm:grid-cols-2">
                                <p v-for="example in workExamples" :key="example">
                                    {{ example }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-3xl">
                        <div class="grid gap-5 lg:grid-cols-[0.92fr_1.08fr]">
                            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-xl shadow-slate-950/5">
                                <div class="flex items-center justify-between border-b border-stone-100 pb-4">
                                    <div>
                                        <p class="text-sm font-semibold text-stone-950">Today's prep list</p>
                                        <p class="mt-1 text-xs text-stone-500">Thursday · farm kitchen</p>
                                    </div>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-950">Mobile-first</span>
                                </div>

                                <div class="mt-4 divide-y divide-stone-100">
                                    <div class="py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-stone-950">Count rye flour</p>
                                                <p class="mt-1 text-xs text-stone-500">Inventory Count #31</p>
                                            </div>
                                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">Counting</span>
                                        </div>
                                        <div class="mt-3 flex items-end justify-between">
                                            <div>
                                                <p class="text-xs font-medium uppercase text-stone-500">Scanned</p>
                                                <p class="mt-1 text-xl font-semibold text-stone-950">344 g</p>
                                            </div>
                                            <button type="button" class="cursor-pointer rounded-md bg-blue-950 px-3 py-2 text-xs font-semibold text-white shadow-sm">
                                                Complete
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-for="item in prepItems"
                                        :key="item[0]"
                                        class="flex items-center justify-between py-3"
                                    >
                                        <div>
                                            <p class="text-sm font-semibold text-stone-950">{{ item[0] }}</p>
                                            <p class="mt-1 text-xs text-stone-500">{{ item[1] }}</p>
                                        </div>
                                        <span class="text-lg font-semibold text-stone-300">›</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-950/5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-stone-950">Maple granola</p>
                                            <p class="mt-1 text-xs text-stone-500">Oats · Honey · Pecans · Sea salt</p>
                                        </div>
                                        <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-950">Ready</span>
                                    </div>
                                    <div class="mt-5 h-2 rounded-full bg-stone-100">
                                        <div class="h-2 w-9/12 rounded-full bg-blue-950" />
                                    </div>
                                    <div class="mt-3 flex justify-between text-xs text-stone-500">
                                        <span>Batch ingredients staged</span>
                                        <span>75%</span>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-[#eef3fb] p-5 shadow-xl shadow-slate-950/5">
                                    <p class="text-sm font-semibold text-stone-950">Ingredient shelf</p>
                                    <div class="mt-4 grid grid-cols-3 gap-2">
                                        <div
                                            v-for="item in shelfItems"
                                            :key="item[0]"
                                            class="rounded-2xl bg-white p-3"
                                        >
                                            <p class="text-xs text-stone-500">{{ item[0] }}</p>
                                            <p class="mt-1 text-lg font-semibold text-stone-950">{{ item[1] }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-950/5">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-semibold text-stone-950">Saturday market</p>
                                        <span class="text-xs font-medium text-stone-500">19 orders</span>
                                    </div>
                                    <div class="mt-4 flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-blue-100" />
                                        <div class="h-10 w-10 rounded-full bg-amber-100" />
                                        <div class="h-10 w-10 rounded-full bg-sky-100" />
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-600">+8</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-3 lg:px-8">
                    <div
                        v-for="item in featureSummaries"
                        :key="item[0]"
                        class="border-l border-blue-300 pl-4"
                    >
                        <p class="text-sm font-semibold text-stone-950">{{ item[0] }}</p>
                        <p class="mt-2 text-sm leading-6 text-stone-600">{{ item[1] }}</p>
                    </div>
                </div>

                <div class="mx-auto max-w-7xl px-4 pb-10 sm:px-6 lg:px-8">
                    <div class="border-t border-stone-200 pt-6">
                        <p class="text-xs font-semibold uppercase text-stone-500">Learn more</p>
                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-blue-950">
                            <a
                                v-for="link in learnLinks"
                                :key="link.url"
                                :href="link.url"
                                class="cursor-pointer hover:text-blue-800"
                            >
                                {{ link.label }}
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <AuthDrawer
            v-if="!authUser"
            :open="drawerOpen"
            :mode="drawerMode"
            :logo="logoPayload"
            :login="loginForm"
            :register="registerForm"
            :password-reset-url="authRoutes.passwordResetUrl"
            :can-register="authRoutes.canRegister"
            @close="closeAuth"
            @switch-mode="switchAuthMode"
            @submit-login="submitLogin"
            @submit-register="submitRegister"
            @update-login-field="updateLoginField"
            @update-register-field="updateRegisterField"
        />
    </GuestShell>
</template>
