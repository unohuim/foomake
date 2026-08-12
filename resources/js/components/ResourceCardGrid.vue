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
                        </a>

                        <p v-if="actions.length === 0 && titleAside(record)" class="shrink-0 self-start text-xs font-medium text-gray-500">
                            {{ titleAside(record) }}
                        </p>

                        <div v-if="actions.length > 0" class="relative z-10 flex shrink-0 items-center gap-2">
                            <BaseDropdown :aria-label="labels.actionsAriaLabel">
                                <template #trigger>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                    </svg>
                                </template>

                                <template #default="{ close }">
                                    <button
                                        v-for="action in actions"
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

                                <p v-if="titleAside(record)" class="shrink-0 text-xs font-medium text-gray-500">
                                    {{ titleAside(record) }}
                                </p>

                                <div v-if="actions.length > 0" class="relative flex shrink-0 items-start gap-2">
                                    <BaseDropdown :aria-label="labels.actionsAriaLabel">
                                        <template #trigger>
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                            </svg>
                                        </template>

                                        <template #default="{ close }">
                                            <button
                                                v-for="action in actions"
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
