<script setup>
defineProps({
    todo: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div data-dashboard-todo-section>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-950/5" data-detail-section-card>
            <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ todo.heading }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ todo.description }}
                </p>
            </div>

            <div class="px-5 py-5 sm:p-6">
                <div
                    v-if="!todo.hasTodo"
                    class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-600"
                >
                    {{ todo.emptyState }}
                </div>

                <div v-else class="space-y-6">
                    <div>
                        <h3 class="-mx-3 bg-blue-100 px-3 py-2 text-sm font-semibold text-gray-900 sm:mx-0 sm:rounded-md">
                            Workflow Responsibilities
                        </h3>

                        <p v-if="todo.responsibilities.length === 0" class="mt-3 text-sm text-gray-500">
                            No assigned responsibilities.
                        </p>

                        <div v-else class="-mx-3 mt-0 overflow-hidden border-y border-gray-200 sm:mx-0 sm:mt-3 sm:rounded-lg sm:border">
                            <ul class="divide-y divide-gray-200">
                                <li v-for="responsibility in todo.responsibilities" :key="`${responsibility.domainName}-${responsibility.title}-${responsibility.url}`">
                                    <a
                                        :href="responsibility.url"
                                        class="cursor-pointer block px-4 py-3 hover:bg-gray-50 sm:hidden"
                                        data-dashboard-todo-mobile-row
                                    >
                                        <div class="space-y-1">
                                            <div class="flex items-start justify-between gap-4">
                                                <span class="min-w-0 font-medium text-gray-900">
                                                    {{ responsibility.title }}
                                                </span>

                                                <span v-if="responsibility.dueDate" class="shrink-0 text-sm text-gray-500">
                                                    {{ responsibility.dueDate }}
                                                </span>
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                    <span v-if="responsibility.domainName" class="text-xs font-medium text-gray-600">
                                                        {{ responsibility.domainName }}
                                                    </span>
                                                </div>

                                                <span
                                                    v-if="responsibility.stage"
                                                    class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700"
                                                >
                                                    {{ responsibility.stage }}
                                                </span>
                                            </div>
                                        </div>
                                    </a>

                                    <div class="hidden px-4 py-3 sm:block">
                                        <div class="space-y-1">
                                            <div class="flex items-start justify-between gap-4">
                                                <a
                                                    :href="responsibility.url"
                                                    class="cursor-pointer min-w-0 font-medium text-gray-900 underline-offset-2 hover:text-gray-700 hover:underline"
                                                >
                                                    {{ responsibility.title }}
                                                </a>

                                                <span v-if="responsibility.dueDate" class="shrink-0 text-sm text-gray-500">
                                                    {{ responsibility.dueDate }}
                                                </span>
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                    <span v-if="responsibility.domainName" class="text-xs font-medium text-gray-600">
                                                        {{ responsibility.domainName }}
                                                    </span>
                                                </div>

                                                <span
                                                    v-if="responsibility.stage"
                                                    class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700"
                                                >
                                                    {{ responsibility.stage }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <h3 class="-mx-3 bg-blue-100 px-3 py-2 text-sm font-semibold text-gray-900 sm:mx-0 sm:rounded-md">
                            Stage Tasks
                        </h3>

                        <p v-if="todo.stageTasks.length === 0" class="mt-3 text-sm text-gray-500">
                            No assigned stage tasks.
                        </p>

                        <div v-else class="-mx-3 mt-0 overflow-hidden border-y border-gray-200 sm:mx-0 sm:mt-3 sm:rounded-lg sm:border">
                            <ul class="divide-y divide-gray-200">
                                <li v-for="task in todo.stageTasks" :key="`${task.domainName}-${task.title}-${task.url}`">
                                    <a
                                        :href="task.url"
                                        class="cursor-pointer block px-4 py-3 hover:bg-gray-50 sm:hidden"
                                        data-dashboard-todo-mobile-row
                                    >
                                        <div class="space-y-1">
                                            <div class="flex items-start justify-between gap-4">
                                                <span class="min-w-0 font-medium text-gray-900">
                                                    {{ task.title }}
                                                </span>

                                                <span v-if="task.dueDate" class="shrink-0 text-sm text-gray-500">
                                                    {{ task.dueDate }}
                                                </span>
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                    <span v-if="task.domainName" class="text-xs font-medium text-gray-600">
                                                        {{ task.domainName }}
                                                    </span>
                                                </div>

                                                <span
                                                    v-if="task.status"
                                                    class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700"
                                                >
                                                    {{ task.status }}
                                                </span>
                                            </div>
                                        </div>
                                    </a>

                                    <div class="hidden px-4 py-3 sm:block">
                                        <div class="space-y-1">
                                            <div class="flex items-start justify-between gap-4">
                                                <a
                                                    :href="task.url"
                                                    class="cursor-pointer min-w-0 font-medium text-gray-900 underline-offset-2 hover:text-gray-700 hover:underline"
                                                >
                                                    {{ task.title }}
                                                </a>

                                                <span v-if="task.dueDate" class="shrink-0 text-sm text-gray-500">
                                                    {{ task.dueDate }}
                                                </span>
                                            </div>

                                            <div class="flex items-center justify-between gap-4">
                                                <div class="min-w-0 flex flex-wrap items-center gap-2">
                                                    <span v-if="task.domainName" class="text-xs font-medium text-gray-600">
                                                        {{ task.domainName }}
                                                    </span>
                                                </div>

                                                <span
                                                    v-if="task.status"
                                                    class="inline-flex w-fit shrink-0 items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700"
                                                >
                                                    {{ task.status }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
