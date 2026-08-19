<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        required: true,
    },
    titleClass: {
        type: String,
        default: "font-semibold text-2xl leading-tight text-gray-900",
    },
    homeUrl: {
        type: String,
        default: "/dashboard",
    },
});
</script>

<template>
    <div class="space-y-0" data-resource-detail-header>
        <div v-if="$slots.header" data-resource-detail-header-body>
            <slot name="header" />
        </div>

        <div v-else class="max-w-5xl px-4 pb-4 pt-4 sm:px-6 sm:pb-6 sm:pt-6 lg:px-8" data-resource-detail-header-body>
            <div class="flex items-center justify-between gap-3 sm:items-center">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-6 sm:gap-8" data-resource-detail-header-title-row>
                        <h1 :class="titleClass" data-resource-detail-header-title>{{ title }}</h1>
                        <slot name="titleSuffix" />
                    </div>
                </div>
                <div v-if="$slots.actions" class="flex shrink-0 items-center justify-end sm:items-start">
                    <slot name="actions" />
                </div>
            </div>
        </div>

        <div class="w-full" data-resource-detail-breadcrumb>
            <nav class="flex w-full border-y border-gray-200 bg-white" aria-label="Breadcrumb">
                <ol role="list" class="flex w-full min-w-0 items-stretch overflow-hidden px-4 sm:px-6 lg:px-8">
                    <li class="flex shrink-0 self-stretch">
                        <div class="flex items-center">
                            <a :href="homeUrl" class="cursor-pointer py-2 text-gray-500 transition hover:text-gray-700">
                                <span class="sr-only">Home</span>
                                <svg class="size-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125A1.125 1.125 0 0 0 5.625 21h4.5v-5.25c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125V21h4.5a1.125 1.125 0 0 0 1.125-1.125V9.75M8.25 21h7.5" />
                                </svg>
                            </a>
                        </div>
                    </li>

                    <li
                        v-for="(item, index) in items"
                        :key="`${item.label}-${index}`"
                        class="flex self-stretch"
                        :class="index === 0 ? 'shrink-0' : 'min-w-0 flex-1'"
                    >
                        <div class="flex min-w-0 items-center">
                            <svg class="h-full w-4 shrink-0 text-gray-300" viewBox="0 0 24 44" preserveAspectRatio="none" fill="currentColor" aria-hidden="true">
                                <path d="M.293 0l22 22-22 22h1.414l22-22-22-22H.293z" />
                            </svg>

                            <a v-if="item.url && !item.current" :href="item.url" class="ml-4 min-w-0 cursor-pointer truncate py-2 text-xs font-medium text-gray-500 transition hover:text-gray-700">
                                {{ item.label }}
                            </a>
                            <a v-else-if="item.url && item.current" :href="item.url" aria-current="page" class="ml-4 min-w-0 cursor-pointer truncate py-2 text-xs font-medium text-gray-700 transition hover:text-gray-900">
                                {{ item.label }}
                            </a>
                            <span v-else class="ml-4 min-w-0 truncate py-2 text-xs font-medium" :class="item.current ? 'text-gray-700' : 'text-gray-500'" :aria-current="item.current ? 'page' : null">
                                {{ item.label }}
                            </span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
</template>
