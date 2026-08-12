<script setup>
import { Link } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";

import BaseSlideUpDrawer from "./BaseSlideUpDrawer.vue";
import InfiniteHorizontalNavRail from "./InfiniteHorizontalNavRail.vue";
import NavIcon from "./NavIcon.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
});

const activeMenuKey = ref(null);
const drawerEntry = ref(null);
const closeTimer = ref(null);
const accountOpen = ref(false);
const openNestedItems = ref({});

const mobileLabels = {
    dashboard: "Home",
    sales: "Sell",
    purchasing: "Buy",
    manufacturing: "Make",
    stock: "Stock",
};

const entries = computed(() => props.shell.navigation.groups.map((group) => ({
    ...group,
    label: mobileLabels[group.key] ?? group.label,
    url: group.key === "dashboard" ? group.items[0].url : null,
})));

const scrollHintStorageKey = computed(() => `fm.mobileNavScrollHint.${props.shell.user.email}`);

const initials = computed(() => {
    const name = props.shell.user.name || props.shell.user.email || "FM";

    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
});

const activeEntry = computed(() => {
    if (activeMenuKey.value) {
        return entries.value.find((entry) => entry.key === activeMenuKey.value) ?? drawerEntry.value;
    }

    return drawerEntry.value;
});

const openMenu = (entry) => {
    if (closeTimer.value) {
        window.clearTimeout(closeTimer.value);
        closeTimer.value = null;
    }

    accountOpen.value = false;
    drawerEntry.value = entry;
    activeMenuKey.value = entry.key;
    openNestedItems.value = Object.fromEntries(
        entry.items
            .filter((item) => item.children)
            .map((item) => [`${entry.key}-${item.label}`, Boolean(item.active)]),
    );
};

const closeMenu = () => {
    if (!activeMenuKey.value || closeTimer.value) {
        return;
    }

    activeMenuKey.value = null;

    closeTimer.value = window.setTimeout(() => {
        drawerEntry.value = null;
        closeTimer.value = null;
    }, 500);
};

const openAccount = () => {
    if (activeMenuKey.value) {
        closeMenu();
    }

    accountOpen.value = true;
};

const closeAccount = () => {
    accountOpen.value = false;
};

const nestedItemKey = (item) => `${activeEntry.value?.key}-${item.label}`;

const toggleNestedItem = (item) => {
    const key = nestedItemKey(item);

    openNestedItems.value = {
        ...openNestedItems.value,
        [key]: !openNestedItems.value[key],
    };
};

onBeforeUnmount(() => {
    if (closeTimer.value) {
        window.clearTimeout(closeTimer.value);
    }
});
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#001f3f] pb-[env(safe-area-inset-bottom)] shadow-[0_-10px_22px_rgba(15,23,42,0.18)] backdrop-blur md:hidden"
        aria-label="Mobile navigation"
    >
        <div class="flex items-stretch">
            <InfiniteHorizontalNavRail
                :entries="entries"
                :active-key="activeMenuKey"
                :storage-key="scrollHintStorageKey"
                @select="openMenu"
            />

            <div class="flex flex-none items-center px-2 py-2">
                <button
                    type="button"
                    class="cursor-pointer flex h-7 w-7 items-center justify-center rounded-full bg-[#6f895d] text-[10px] font-semibold text-white shadow-sm transition hover:brightness-105"
                    aria-label="Open account menu"
                    :aria-expanded="accountOpen ? 'true' : 'false'"
                    @click="openAccount"
                >
                    {{ initials }}
                </button>
            </div>
        </div>
    </nav>

    <BaseSlideUpDrawer
        :open="Boolean(activeMenuKey)"
        labelled-by="mobile-nav-drawer-title"
        @close="closeMenu"
    >
        <div v-if="activeEntry" class="flex max-h-[85vh] flex-col">
            <div class="relative border-b border-white/10 bg-[#001f3f] px-3 pb-3 pt-2 text-white">
                <div class="mx-auto mb-2 h-1 w-10 rounded-full bg-white/25" />

                <div class="flex min-h-10 items-center gap-2 px-1 py-2 text-white">
                    <NavIcon :name="activeEntry.key" class="h-4 w-4" />
                    <h2 id="mobile-nav-drawer-title" class="min-w-0 truncate text-sm font-semibold">
                        {{ activeEntry.label }}
                    </h2>
                </div>

                <button
                    type="button"
                    class="cursor-pointer absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-lg text-gray-200 transition hover:bg-white/10 hover:text-white"
                    aria-label="Close navigation drawer"
                    @click="closeMenu"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                <div class="space-y-2">
                    <template v-for="item in activeEntry.items" :key="`${activeEntry.key}-${item.label}`">
                        <a
                            v-if="item.enabled && !item.children"
                            :href="item.url"
                            class="cursor-pointer flex min-h-11 items-center rounded-2xl px-4 text-sm font-semibold transition"
                            :class="item.active ? 'bg-[#001f3f] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'"
                            @click="closeMenu"
                        >
                            {{ item.label }}
                        </a>

                        <div
                            v-else-if="item.children"
                            class="overflow-hidden"
                        >
                            <button
                                type="button"
                                class="cursor-pointer flex min-h-11 w-full items-center justify-between rounded-2xl px-4 text-left text-sm font-semibold transition"
                                :class="item.active ? 'bg-[#001f3f] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'"
                                :aria-expanded="openNestedItems[nestedItemKey(item)] ? 'true' : 'false'"
                                @click="toggleNestedItem(item)"
                            >
                                <span>{{ item.label }}</span>
                                <svg
                                    class="h-4 w-4 shrink-0 transition-transform duration-500 ease-in-out"
                                    :class="openNestedItems[nestedItemKey(item)] ? 'rotate-180' : ''"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.512a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div
                                class="overflow-hidden pl-4 transition-[max-height,opacity,margin-top] duration-500 ease-in-out"
                                :class="openNestedItems[nestedItemKey(item)] ? 'mt-1 max-h-52 opacity-100' : 'mt-0 max-h-0 opacity-0 pointer-events-none'"
                            >
                                <div class="space-y-1 border-l border-slate-200 pl-3">
                                    <a
                                        v-for="child in item.children"
                                        :key="`${activeEntry.key}-${item.label}-${child.label}`"
                                        :href="child.url"
                                        class="cursor-pointer flex min-h-10 items-center rounded-2xl px-3 text-sm font-semibold transition"
                                        :class="child.active ? 'bg-[#001f3f] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'"
                                        @click="closeMenu"
                                    >
                                        {{ child.label }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex min-h-11 items-center rounded-2xl px-4 text-sm font-semibold text-slate-400"
                            :title="item.disabledReason"
                        >
                            {{ item.label }}
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </BaseSlideUpDrawer>

    <BaseSlideUpDrawer
        :open="accountOpen"
        labelled-by="mobile-bottom-account-drawer-title"
        @close="closeAccount"
    >
        <div class="flex max-h-[85vh] flex-col">
            <div class="relative border-b border-white/10 bg-[#001f3f] px-5 pb-4 pt-5 text-white">
                <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-white/25" />

                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#6f895d] text-sm font-semibold text-white">
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <h2 id="mobile-bottom-account-drawer-title" class="truncate text-lg font-semibold text-white">
                            {{ shell.user.name }}
                        </h2>
                        <p class="truncate text-sm text-blue-100/70">
                            {{ shell.user.email }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="cursor-pointer absolute right-4 top-4 flex h-7 w-7 items-center justify-center rounded-lg text-gray-200 transition hover:bg-white/10 hover:text-white"
                    aria-label="Close account drawer"
                    @click="closeAccount"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                <div class="space-y-1">
                    <a
                        v-for="item in shell.navigation.accountItems"
                        :key="item.label"
                        :href="item.url"
                        class="cursor-pointer flex min-h-11 items-center rounded-2xl px-4 text-sm font-semibold transition"
                        :class="item.active ? 'bg-[#001f3f] text-white' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'"
                        @click="closeAccount"
                    >
                        {{ item.label }}
                    </a>

                    <div class="my-2 border-t border-slate-200" />

                    <Link
                        :href="shell.navigation.logoutUrl"
                        method="post"
                        as="button"
                        class="cursor-pointer flex min-h-11 w-full items-center rounded-2xl px-4 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                        @click="closeAccount"
                    >
                        Log Out
                    </Link>
                </div>
            </div>
        </div>
    </BaseSlideUpDrawer>
</template>
