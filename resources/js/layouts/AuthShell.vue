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
    <div class="min-h-screen bg-gray-100 font-sans text-slate-950 antialiased md:flex">
        <DesktopSidebar :shell="shellPayload" />
        <MobileBottomNav :shell="shellPayload" />

        <div class="min-w-0 flex-1">
            <header class="bg-[#f5f7fa]">
                <div class="mx-auto max-w-7xl px-4 pb-3 pt-7 sm:px-6 lg:px-8">
                    <h1 class="text-2xl font-semibold leading-tight text-slate-950">
                        {{ title }}
                    </h1>
                </div>
            </header>

            <main class="pb-20 md:pb-0">
                <slot />
            </main>
        </div>
    </div>
</template>
