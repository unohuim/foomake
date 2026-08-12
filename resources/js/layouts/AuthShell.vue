<script setup>
import { computed } from "vue";

import DesktopSidebar from "../components/DesktopSidebar.vue";
import MobileBottomNav from "../components/MobileBottomNav.vue";
import shellLogoSrc from "../../img/foomake_logo_square_white.png";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    shell: {
        type: Object,
        required: true,
    },
});

const shellPayload = computed(() => ({
    ...props.shell,
    logo: {
        ...props.shell.logo,
        src: props.shell.logo.src ?? shellLogoSrc,
    },
}));
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-100 font-sans text-slate-950 antialiased md:flex">
        <DesktopSidebar :shell="shellPayload" />
        <MobileBottomNav :shell="shellPayload" />

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="bg-[#001f3f] md:bg-[#f5f7fa]">
                <div class="mx-auto max-w-7xl px-4 pb-3 pt-7 sm:px-6 lg:px-8">
                    <h1 class="text-2xl font-semibold leading-tight text-blue-100 md:text-slate-950">
                        {{ title }}
                    </h1>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-hidden pb-20 md:pb-0">
                <slot />
            </main>
        </div>
    </div>
</template>
