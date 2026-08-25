<script setup>
import BaseDropdown from "./BaseDropdown.vue";

defineProps({
    records: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    title: {
        type: Function,
        default: (record) => record?.name || "-",
    },
    titleAside: {
        type: Function,
        default: () => "",
    },
    titleBadges: {
        type: Function,
        default: () => [],
    },
    iconBadges: {
        type: Function,
        default: () => [],
    },
    detailRows: {
        type: Function,
        default: () => [],
    },
    subtitle: {
        type: Function,
        default: () => "",
    },
    mobileSubtitle: {
        type: Function,
        default: null,
    },
    href: {
        type: Function,
        default: () => null,
    },
    actions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["action"]);

function badgeToneClasses(badge) {
    if (badge.tone === "blue") {
        return "bg-blue-50 text-blue-700";
    }

    if (badge.tone === "green") {
        return "bg-emerald-50 text-emerald-700";
    }

    if (badge.tone === "yellow") {
        return "bg-yellow-50 text-yellow-800";
    }

    if (badge.tone === "red") {
        return "bg-red-50 text-red-700";
    }

    return "bg-gray-100 text-gray-700";
}

function actionClasses(action) {
    if (action.tone === "danger" || action.tone === "warning") {
        return "text-red-600 hover:bg-red-50";
    }

    return "text-gray-700 hover:bg-gray-50";
}

function visibleActions(record, actions) {
    const allowed = Array.isArray(record?.available_actions)
        ? record.available_actions
        : (Array.isArray(record?.availableActions) ? record.availableActions : null);

    if (!allowed) {
        return actions;
    }

    return actions.filter((action) => allowed.includes(action.id));
}

function iconBadgeClasses(badge) {
    return badge.active
        ? "border-blue-600 text-blue-600"
        : "border-gray-300 text-gray-300";
}
</script>

<template>
    <div class="min-h-0 w-full">
        <div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>
            <div class="min-h-0 p-0" data-crud-records-scroll>
                <div class="border-t border-gray-300">
                    <div
                        v-for="record in records"
                        :key="`mobile-card-${record.id}`"
                        class="relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 overflow-visible border-b border-gray-300 bg-white px-4 py-2"
                        data-crud-card
                    >
                        <a class="min-w-0 cursor-pointer" :href="href(record) || undefined">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-900">
                                        {{ title(record) }}
                                    </p>
                                    <span
                                        v-for="badge in titleBadges(record)"
                                        :key="`mobile-card-${record.id}-title-badge-${badge.label}`"
                                        class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide"
                                        :class="badgeToneClasses(badge)"
                                        data-crud-mobile-title-badge
                                    >
                                        {{ badge.label }}
                                    </span>
                                </div>
                            </div>

                            <p class="mt-0.5 truncate text-xs text-gray-600">
                                {{ (mobileSubtitle || subtitle)(record) || "-" }}
                            </p>

                            <div class="mt-2 space-y-1.5" data-crud-card-detail-rows>
                                <div
                                    v-for="(row, rowIndex) in detailRows(record)"
                                    :key="`mobile-card-${record.id}-detail-row-${rowIndex}`"
                                    class="flex min-w-0 items-baseline justify-between gap-3 text-xs"
                                >
                                    <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                        <span
                                            v-for="item in row.left"
                                            :key="`mobile-card-${record.id}-detail-left-${rowIndex}-${item.label}`"
                                            class="inline-flex min-w-0 items-baseline gap-1"
                                        >
                                            <span class="shrink-0 text-[0.65rem] font-medium text-gray-400">{{ item.label }}</span>
                                            <span class="min-w-0 truncate text-xs font-medium text-gray-700">{{ item.value }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="iconBadges(record).length > 0" class="mt-2 flex flex-wrap gap-1.5" data-crud-card-icon-badges>
                                <span
                                    v-for="badge in iconBadges(record)"
                                    :key="`mobile-card-${record.id}-icon-badge-${badge.label}`"
                                    class="inline-flex h-5 w-5 items-center justify-center rounded-full border bg-white transition"
                                    :class="iconBadgeClasses(badge)"
                                    :title="badge.label"
                                    :aria-label="badge.label"
                                >
                                    <span class="sr-only">{{ badge.label }}</span>

                                    <svg v-if="badge.icon === 'shopping-cart'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                    </svg>

                                    <svg v-else-if="badge.icon === 'credit-card'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                    </svg>

                                    <svg v-else-if="badge.icon === 'cog'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
                                    </svg>

                                    <svg v-else-if="badge.icon === 'rectangle-group'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                    </svg>
                                </span>
                            </div>
                        </a>

                        <slot name="mobile-aside" :record="record" />

                        <p v-if="!$slots['mobile-aside'] && actions.length === 0 && titleAside(record)" class="shrink-0 self-start text-xs font-medium text-gray-500">
                            {{ titleAside(record) }}
                        </p>

                        <div v-if="visibleActions(record, actions).length > 0" class="relative z-10 flex shrink-0 items-center gap-2">
                            <BaseDropdown :aria-label="labels.actionsAriaLabel">
                                <template #trigger>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                    </svg>
                                </template>

                                <template #default="{ close }">
                                    <button
                                        v-for="action in visibleActions(record, actions)"
                                        :key="`mobile-card-${record.id}-action-${action.id}`"
                                        type="button"
                                        class="flex w-full cursor-pointer items-center px-4 py-2 text-sm"
                                        :class="actionClasses(action)"
                                        role="menuitem"
                                        @click="close(); emit('action', { action, record })"
                                    >
                                        {{ action.label }}
                                    </button>
                                </template>
                            </BaseDropdown>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden h-full min-h-0 md:block">
            <div class="flex h-full min-h-0 flex-col">
                <div class="min-h-0 flex-1 p-6" data-crud-records-scroll>
                    <div
                        class="resource-card-grid grid gap-4"
                        data-crud-card-grid
                        :class="loading ? 'opacity-80' : 'opacity-100'"
                    >
                        <article
                            v-for="record in records"
                            :key="`desktop-card-${record.id}`"
                            class="group flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md"
                            data-crud-card
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <a class="cursor-pointer truncate text-base font-semibold text-gray-900" :href="href(record) || undefined">
                                            {{ title(record) }}
                                        </a>
                                        <span
                                            v-for="badge in titleBadges(record)"
                                            :key="`desktop-card-${record.id}-title-badge-${badge.label}`"
                                            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide"
                                            :class="badgeToneClasses(badge)"
                                            data-crud-desktop-title-badge
                                        >
                                            {{ badge.label }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-sm text-gray-500">
                                        {{ subtitle(record) || "-" }}
                                    </p>
                                </div>

                                <slot name="desktop-aside" :record="record" />

                                <p v-if="!$slots['desktop-aside'] && titleAside(record)" class="shrink-0 text-xs font-medium text-gray-500">
                                    {{ titleAside(record) }}
                                </p>

                                <div v-if="visibleActions(record, actions).length > 0" class="relative flex shrink-0 items-start gap-2">
                                    <BaseDropdown :aria-label="labels.actionsAriaLabel">
                                        <template #trigger>
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                            </svg>
                                        </template>

                                        <template #default="{ close }">
                                            <button
                                                v-for="action in visibleActions(record, actions)"
                                                :key="`desktop-card-${record.id}-action-${action.id}`"
                                                type="button"
                                                class="flex w-full cursor-pointer items-center px-4 py-2 text-sm"
                                                :class="actionClasses(action)"
                                                role="menuitem"
                                                @click="close(); emit('action', { action, record })"
                                            >
                                                {{ action.label }}
                                            </button>
                                        </template>
                                    </BaseDropdown>
                                </div>
                            </div>

                            <div class="mt-2 space-y-1.5" data-crud-card-detail-rows>
                                <div
                                    v-for="(row, rowIndex) in detailRows(record)"
                                    :key="`desktop-card-${record.id}-detail-row-${rowIndex}`"
                                    class="flex min-w-0 items-baseline justify-between gap-3 text-xs"
                                >
                                    <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                        <span
                                            v-for="item in row.left"
                                            :key="`desktop-card-${record.id}-detail-left-${rowIndex}-${item.label}`"
                                            class="inline-flex min-w-0 items-baseline gap-1"
                                        >
                                            <span class="shrink-0 text-[0.65rem] font-medium text-gray-400">{{ item.label }}</span>
                                            <span class="min-w-0 truncate text-xs font-medium text-gray-700">{{ item.value }}</span>
                                        </span>
                                    </div>
                                    <div class="flex shrink-0 items-baseline gap-x-1.5">
                                        <span
                                            v-for="item in row.right"
                                            :key="`desktop-card-${record.id}-detail-right-${rowIndex}-${item.label}`"
                                            class="inline-flex items-baseline gap-1"
                                        >
                                            <span class="text-[0.65rem] font-medium text-gray-400">{{ item.label }}</span>
                                            <span class="text-xs font-medium text-gray-700">{{ item.value }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <slot name="footer" :record="record">
                                <div v-if="iconBadges(record).length > 0" class="mt-auto flex flex-wrap gap-2 pt-4" data-crud-card-icon-badges>
                                    <span
                                        v-for="badge in iconBadges(record)"
                                        :key="`desktop-card-${record.id}-icon-badge-${badge.label}`"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full border bg-white transition sm:h-6 sm:w-6"
                                        :class="iconBadgeClasses(badge)"
                                        :title="badge.label"
                                        :aria-label="badge.label"
                                    >
                                        <span class="sr-only">{{ badge.label }}</span>

                                        <svg v-if="badge.icon === 'shopping-cart'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                        </svg>

                                        <svg v-else-if="badge.icon === 'credit-card'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                        </svg>

                                        <svg v-else-if="badge.icon === 'cog'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
                                        </svg>

                                        <svg v-else-if="badge.icon === 'rectangle-group'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 sm:h-4 sm:w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                        </svg>
                                    </span>
                                </div>
                            </slot>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.resource-card-grid {
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 22rem));
    justify-content: start;
}
</style>
