<script setup>
defineProps({
    authUser: {
        type: Object,
        default: null,
    },
    dashboardUrl: {
        type: String,
        required: true,
    },
    logo: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["open-auth"]);
</script>

<template>
    <div class="min-h-screen overflow-hidden">
        <header class="relative z-20">
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8"
                aria-label="Global"
            >
                <a href="/" class="cursor-pointer flex items-center gap-3">
                    <img
                        v-if="logo.src"
                        :src="logo.src"
                        :alt="logo.alt"
                        class="h-12 w-auto object-contain sm:h-14"
                    >
                    <span
                        v-else
                        class="flex h-20 w-20 items-center justify-center rounded-md bg-blue-950 text-base font-semibold text-white shadow-sm sm:h-24 sm:w-24"
                    >
                        FM
                    </span>
                </a>

                <div class="flex items-center gap-3">
                    <a
                        v-if="authUser"
                        :href="dashboardUrl"
                        class="cursor-pointer inline-flex items-center rounded-md bg-blue-950 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                    >
                        Dashboard
                    </a>
                    <button
                        v-if="!authUser"
                        type="button"
                        class="cursor-pointer hidden rounded-md bg-blue-950 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950 sm:inline-flex"
                        @click="emit('open-auth', 'register')"
                    >
                        Start beta access
                    </button>
                    <button
                        v-if="!authUser"
                        type="button"
                        class="cursor-pointer relative inline-flex h-12 w-12 items-center justify-center rounded-full border border-blue-950/25 bg-transparent text-blue-950 hover:border-2 hover:border-blue-300 hover:text-blue-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-950"
                        aria-label="Open sign in drawer"
                        @click="emit('open-auth', 'login')"
                    >
                        <svg
                            class="relative h-6 w-6"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.6"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                        </svg>
                    </button>
                </div>
            </nav>
        </header>

        <slot />
    </div>
</template>
