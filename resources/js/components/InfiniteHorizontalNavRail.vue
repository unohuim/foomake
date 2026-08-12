<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";

import NavIcon from "./NavIcon.vue";

const props = defineProps({
    entries: {
        type: Array,
        required: true,
    },
    activeKey: {
        type: String,
        default: null,
    },
    storageKey: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(["select"]);

const navRail = ref(null);
const navRailViewport = ref(null);
const scrollUseCount = ref(0);
const scrollUseTimer = ref(null);
const suppressScrollCount = ref(false);
const canFitAllEntries = ref(false);
const railResizeObserver = ref(null);

const loopedEntries = computed(() => [
    ...props.entries.map((entry) => ({ entry, loopKey: `start-${entry.key}` })),
    ...props.entries.map((entry) => ({ entry, loopKey: `middle-${entry.key}` })),
    ...props.entries.map((entry) => ({ entry, loopKey: `end-${entry.key}` })),
]);

const renderedEntries = computed(() => {
    if (canFitAllEntries.value) {
        return props.entries.map((entry) => ({ entry, loopKey: `single-${entry.key}` }));
    }

    return loopedEntries.value;
});

const loadScrollUseCount = () => {
    const storedCount = Number.parseInt(window.localStorage.getItem(props.storageKey) ?? "0", 10);

    scrollUseCount.value = Number.isNaN(storedCount) ? 0 : Math.min(storedCount, 3);
};

const saveScrollUseCount = () => {
    window.localStorage.setItem(props.storageKey, String(Math.min(scrollUseCount.value, 3)));
};

const centerRail = () => {
    const rail = navRail.value;

    if (!rail || props.entries.length === 0 || canFitAllEntries.value) {
        return;
    }

    rail.scrollLeft = rail.scrollWidth / 3;
};

const measureRailFit = () => {
    const viewport = navRailViewport.value;

    if (!viewport || props.entries.length === 0) {
        return;
    }

    const rootFontSize = Number.parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;
    const itemWidth = 3.35 * rootFontSize;
    const gapWidth = 4;
    const horizontalPadding = 12;
    const requiredWidth = (props.entries.length * itemWidth) + ((props.entries.length - 1) * gapWidth) + horizontalPadding;

    canFitAllEntries.value = viewport.clientWidth >= requiredWidth;

    if (!canFitAllEntries.value) {
        nextTick(centerRail);
    }
};

const handleRailScroll = () => {
    const rail = navRail.value;

    if (!rail || props.entries.length === 0 || canFitAllEntries.value) {
        return;
    }

    const segmentWidth = rail.scrollWidth / 3;

    if (rail.scrollLeft < segmentWidth * 0.35) {
        rail.scrollLeft += segmentWidth;
    }

    if (rail.scrollLeft > segmentWidth * 1.65) {
        rail.scrollLeft -= segmentWidth;
    }

    if (suppressScrollCount.value || scrollUseCount.value >= 3) {
        return;
    }

    if (scrollUseTimer.value) {
        window.clearTimeout(scrollUseTimer.value);
    }

    scrollUseTimer.value = window.setTimeout(() => {
        scrollUseCount.value = Math.min(scrollUseCount.value + 1, 3);
        saveScrollUseCount();
        scrollUseTimer.value = null;
    }, 350);
};

const selectEntry = (entry) => {
    emit("select", entry);
};

onMounted(() => {
    suppressScrollCount.value = true;
    loadScrollUseCount();

    nextTick(() => {
        measureRailFit();
        centerRail();

        railResizeObserver.value = new ResizeObserver(measureRailFit);

        if (navRailViewport.value) {
            railResizeObserver.value.observe(navRailViewport.value);
        }

        window.setTimeout(() => {
            suppressScrollCount.value = false;
        }, 100);
    });
});

onBeforeUnmount(() => {
    if (scrollUseTimer.value) {
        window.clearTimeout(scrollUseTimer.value);
    }

    if (railResizeObserver.value) {
        railResizeObserver.value.disconnect();
    }
});
</script>

<template>
    <div ref="navRailViewport" class="relative min-w-0 flex-1">
        <div
            ref="navRail"
            class="flex gap-1 px-1.5 py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            :class="canFitAllEntries ? 'justify-around overflow-hidden' : 'overflow-x-auto overscroll-x-contain'"
            @scroll.passive="handleRailScroll"
        >
            <template v-for="{ entry, loopKey } in renderedEntries" :key="loopKey">
                <a
                    v-if="entry.url"
                    :href="entry.url"
                    class="cursor-pointer flex min-w-[3.35rem] flex-none flex-col items-center gap-0.5 rounded-lg px-1 py-1 text-[9px] font-semibold leading-tight transition duration-300"
                    :class="entry.active ? 'bg-white/15 text-white shadow-lg shadow-slate-950/20 ring-1 ring-white/10' : 'text-blue-100/75 hover:bg-white/10 hover:text-white'"
                >
                    <NavIcon :name="entry.key" class="h-3.5 w-3.5" />
                    <span class="max-w-full truncate">{{ entry.label }}</span>
                </a>

                <button
                    v-else
                    type="button"
                    class="cursor-pointer flex min-w-[3.35rem] flex-none flex-col items-center gap-0.5 rounded-lg px-1 py-1 text-[9px] font-semibold leading-tight transition duration-300"
                    :class="entry.active || activeKey === entry.key ? 'bg-white/15 text-white shadow-lg shadow-slate-950/20 ring-1 ring-white/10' : 'text-blue-100/75 hover:bg-white/10 hover:text-white'"
                    :aria-expanded="activeKey === entry.key ? 'true' : 'false'"
                    @click="selectEntry(entry)"
                >
                    <NavIcon :name="entry.key" class="h-3.5 w-3.5" />
                    <span class="max-w-full truncate">{{ entry.label }}</span>
                </button>
            </template>
        </div>

        <div v-if="!canFitAllEntries" class="pointer-events-none absolute inset-y-0 left-0 w-4 bg-gradient-to-r from-[#001f3f] to-transparent" />
        <div v-if="!canFitAllEntries" class="pointer-events-none absolute inset-y-0 right-0 w-4 bg-gradient-to-l from-[#001f3f] to-transparent" />
        <svg
            v-if="!canFitAllEntries && scrollUseCount < 3"
            class="pointer-events-none absolute right-1 top-1/2 h-3 w-3 -translate-y-1/2 text-blue-100/70"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path fill-rule="evenodd" d="M7.22 14.78a.75.75 0 0 1 0-1.06L10.94 10 7.22 6.28a.75.75 0 1 1 1.06-1.06l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
        </svg>
    </div>
</template>
