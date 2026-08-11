<script setup>
import { Link } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

import NavIcon from "./NavIcon.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
});

const accountOpen = ref(false);
const collapsed = ref(false);
const accountRoot = ref(null);
const openGroups = ref(
    Object.fromEntries(
        props.shell.navigation.groups.map((group) => [group.key, group.active || group.key === "dashboard"]),
    ),
);

const initials = computed(() => {
    const name = props.shell.user.name || props.shell.user.email || "FM";

    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
});

const toggleGroup = (key) => {
    if (collapsed.value) {
        collapsed.value = false;
        openGroups.value = {
            ...openGroups.value,
            [key]: true,
        };

        return;
    }

    openGroups.value = {
        ...openGroups.value,
        [key]: !openGroups.value[key],
    };
};

const toggleCollapsed = () => {
    collapsed.value = !collapsed.value;
    accountOpen.value = false;
};

const toggleAccount = () => {
    if (collapsed.value) {
        collapsed.value = false;
    }

    accountOpen.value = !accountOpen.value;
};

const handleDocumentClick = (event) => {
    if (!accountOpen.value || collapsed.value) {
        return;
    }

    if (accountRoot.value?.contains(event.target)) {
        return;
    }

    accountOpen.value = false;
};

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <aside
        class="hidden min-h-screen shrink-0 overflow-hidden border-r border-white/10 bg-[#001f3f] text-white shadow-2xl shadow-slate-950/20 transition-[width] duration-500 ease-in-out md:flex md:flex-col"
        :class="collapsed ? 'w-16' : 'w-64'"
        data-desktop-sidebar
    >
        <div
            class="flex h-20 items-center border-b border-white/10"
            :class="collapsed ? 'justify-center px-2' : 'justify-between px-4'"
        >
            <a
                :href="shell.navigation.dashboardUrl"
                class="cursor-pointer flex min-w-0 items-center gap-3"
                :class="collapsed ? 'justify-center' : ''"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                    <img
                        v-if="shell.logo.src"
                        :src="shell.logo.src"
                        :alt="shell.logo.alt"
                        class="h-8 w-auto object-contain"
                    >
                    <span v-else class="text-sm font-semibold text-white">
                        FM
                    </span>
                </span>

                <span v-if="!collapsed" class="min-w-0 leading-none">
                    <span class="block truncate text-lg font-semibold tracking-wide">FooMake</span>
                    <span class="mt-1.5 block truncate text-xs text-blue-100/70">Production workspace</span>
                </span>
            </a>

            <button
                v-if="!collapsed"
                type="button"
                class="cursor-pointer flex h-8 w-8 items-center justify-center rounded-lg text-blue-100/80 transition hover:bg-white/10 hover:text-white"
                aria-label="Collapse desktop navigation"
                @click="toggleCollapsed"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 010 1.06L9.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <button
            v-if="collapsed"
            type="button"
            class="cursor-pointer mx-auto mt-3 flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-blue-100 transition hover:bg-white/15 hover:text-white"
            aria-label="Expand desktop navigation"
            @click="toggleCollapsed"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.22 14.78a.75.75 0 010-1.06L10.94 10 7.22 6.28a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0z" clip-rule="evenodd" />
            </svg>
        </button>

        <nav
            class="min-h-0 flex-1 overflow-y-auto"
            :class="collapsed ? 'px-2 py-3' : 'px-3 py-4'"
            aria-label="Desktop navigation"
        >
            <div :class="collapsed ? 'space-y-2.5' : 'space-y-2'">
                <section
                    v-for="group in shell.navigation.groups"
                    :key="group.key"
                >
                    <a
                        v-if="group.items.length === 1 && !group.items[0].children && group.items[0].enabled"
                        :href="group.items[0].url"
                        class="cursor-pointer group flex w-full items-center transition"
                        :class="[
                            collapsed
                                ? 'h-10 justify-center rounded-xl'
                                : 'gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold',
                            group.active
                                ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/10'
                                : 'text-blue-100/80 hover:bg-white/10 hover:text-white',
                        ]"
                        :title="collapsed ? group.label : null"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border"
                            :class="group.active ? 'border-white/20 bg-white/10' : 'border-white/10 bg-white/5 group-hover:border-white/20'"
                        >
                            <NavIcon :name="group.key" class="h-4 w-4" />
                        </span>
                        <span v-if="!collapsed" class="truncate">{{ group.label }}</span>
                    </a>

                    <button
                        v-else
                        type="button"
                        class="cursor-pointer group flex w-full items-center transition"
                        :class="[
                            collapsed
                                ? 'h-10 justify-center rounded-xl'
                                : 'justify-between rounded-xl px-3 py-2.5 text-left text-sm font-semibold',
                            group.active
                                ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/10'
                                : 'text-blue-100/80 hover:bg-white/10 hover:text-white',
                        ]"
                        :title="collapsed ? group.label : null"
                        :aria-expanded="openGroups[group.key] ? 'true' : 'false'"
                        @click="toggleGroup(group.key)"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border"
                                :class="group.active ? 'border-white/20 bg-white/10' : 'border-white/10 bg-white/5 group-hover:border-white/20'"
                            >
                                <NavIcon :name="group.key" class="h-4 w-4" />
                            </span>
                            <span v-if="!collapsed" class="truncate">{{ group.label }}</span>
                        </span>
                        <svg
                            v-if="!collapsed"
                            class="h-4 w-4 shrink-0 transition-transform duration-500 ease-in-out"
                            :class="openGroups[group.key] ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div
                        v-if="!(group.items.length === 1 && !group.items[0].children && group.items[0].enabled)"
                        class="overflow-hidden pl-11 transition-[max-height,opacity,margin-top] duration-500 ease-in-out"
                        :class="!collapsed && openGroups[group.key] ? 'mt-1.5 max-h-80 opacity-100' : 'mt-0 max-h-0 opacity-0 pointer-events-none'"
                    >
                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="`${group.key}-${item.label}`">
                                <a
                                    v-if="item.enabled && !item.children"
                                    :href="item.url"
                                    class="cursor-pointer flex min-h-8 items-center rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="item.active ? 'bg-white text-[#001f3f] shadow-sm' : 'text-blue-100/70 hover:bg-white/10 hover:text-white'"
                                >
                                    {{ item.label }}
                                </a>

                                <div
                                    v-else-if="item.children"
                                    class="rounded-xl border border-white/10 bg-white/5 p-1.5"
                                >
                                    <p class="px-2 pb-1 text-xs font-semibold uppercase text-blue-100/50">
                                        {{ item.label }}
                                    </p>
                                    <div class="space-y-1">
                                        <a
                                            v-for="child in item.children"
                                            :key="`${group.key}-${item.label}-${child.label}`"
                                            :href="child.url"
                                            class="cursor-pointer flex min-h-8 items-center rounded-lg px-2 py-1.5 text-sm font-medium transition"
                                            :class="child.active ? 'bg-white text-[#001f3f] shadow-sm' : 'text-blue-100/70 hover:bg-white/10 hover:text-white'"
                                        >
                                            {{ child.label }}
                                        </a>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="rounded-lg px-3 py-1.5 text-sm text-blue-100/35"
                                    :title="item.disabledReason"
                                >
                                    {{ item.label }}
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
        </nav>

        <div
            class="border-t border-white/10"
            :class="collapsed ? 'px-2 py-3' : 'p-3'"
        >
            <div ref="accountRoot" class="relative h-14">
                <div
                    class="absolute bottom-0 left-0 right-0 overflow-hidden rounded-2xl bg-white/10 text-left ring-1 ring-white/10 transition-[max-height,background-color,box-shadow,opacity] duration-500 ease-in-out"
                    :class="accountOpen && !collapsed ? 'max-h-80 bg-[#082b52] opacity-100 shadow-2xl shadow-slate-950/30' : 'max-h-14 opacity-90'"
                >
                    <button
                        type="button"
                        class="cursor-pointer flex w-full items-center text-left transition duration-500 ease-in-out hover:bg-white/10"
                        :class="collapsed ? 'justify-center p-1.5' : 'gap-3 p-2.5'"
                        :aria-expanded="accountOpen ? 'true' : 'false'"
                        aria-label="Open account menu"
                        @click="toggleAccount"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#6f895d] text-sm font-semibold text-white">
                            {{ initials }}
                        </span>
                        <span v-if="!collapsed" class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-white">{{ shell.user.name }}</span>
                            <span class="block truncate text-xs text-blue-100/60">{{ shell.user.email }}</span>
                        </span>
                        <svg
                            v-if="!collapsed"
                            class="h-4 w-4 shrink-0 text-blue-100/70 transition-transform duration-500 ease-in-out"
                            :class="accountOpen ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 0 1-1.06-.02L10 8.832 6.29 12.77a.75.75 0 1 1-1.08-1.04l4.25-4.512a.75.75 0 0 1 1.08 0l4.25 4.512a.75.75 0 0 1-.02 1.06Z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div
                        class="space-y-1 px-2 pb-2 transition-opacity duration-500 ease-in-out"
                        :class="accountOpen && !collapsed ? 'opacity-100' : 'opacity-0'"
                    >
                        <a
                            v-for="item in shell.navigation.accountItems"
                            :key="item.label"
                            :href="item.url"
                            class="cursor-pointer flex min-h-8 items-center rounded-lg px-3 py-1.5 text-sm font-medium transition duration-500 ease-in-out"
                            :class="item.active ? 'bg-white text-[#001f3f]' : 'text-blue-100/80 hover:bg-white/10 hover:text-white'"
                        >
                            {{ item.label }}
                        </a>

                        <div class="my-1 border-t border-white/10" />

                        <Link
                            :href="shell.navigation.logoutUrl"
                            method="post"
                            as="button"
                            class="cursor-pointer flex w-full items-center rounded-lg px-3 py-1.5 text-left text-sm font-medium text-blue-100/80 transition duration-500 ease-in-out hover:bg-white/10 hover:text-white"
                        >
                            Log Out
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
