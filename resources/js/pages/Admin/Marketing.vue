<script setup>
import { Head } from "@inertiajs/vue3";

import AuthShell from "../../layouts/AuthShell.vue";

defineProps({
    shell: {
        type: Object,
        required: true,
    },
    searchConsole: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Marketing" />

    <AuthShell :shell="shell" title="Marketing">
        <div class="px-0 pb-10 pt-3 md:px-4 lg:px-8">
            <div class="mx-auto w-full max-w-6xl space-y-5 px-4 sm:px-5 md:px-0">
                <section class="border-b border-slate-200 pb-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Admin</p>
                            <h1 class="mt-1 text-2xl font-semibold text-[#111b31]">Marketing</h1>
                            <p class="mt-1 max-w-2xl text-sm text-slate-600">
                                Search visibility, keyword demand, and content opportunities for FooMake.
                            </p>
                        </div>

                        <a
                            :href="searchConsole.connectorsUrl"
                            class="inline-flex cursor-pointer items-center justify-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Manage Connection
                        </a>
                    </div>
                </section>

                <section class="grid gap-3 md:grid-cols-[1fr,auto] md:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Google Search Console</p>
                        <p class="mt-1 text-sm font-semibold text-[#111b31]">
                            {{ searchConsole.siteUrl || "No Search Console property connected" }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ searchConsole.connected ? `Last checked ${searchConsole.lastVerifiedAt || "unknown"}` : "Connect Search Console before viewing performance data." }}
                        </p>
                        <p v-if="searchConsole.lastError" class="mt-2 text-xs text-red-600">
                            {{ searchConsole.lastError }}
                        </p>
                    </div>

                    <div
                        class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                        :class="searchConsole.connected ? 'bg-[#eef4e9] text-[#5f784f]' : 'bg-amber-50 text-amber-700'"
                    >
                        {{ searchConsole.connected ? "Connected" : "Disconnected" }}
                    </div>
                </section>

                <section v-if="searchConsole.connected" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="view in searchConsole.views"
                        :key="view.label"
                        class="border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-[#111b31]">{{ view.label }}</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-600">{{ view.description }}</p>
                    </article>
                </section>

                <section v-if="searchConsole.connected" class="flex flex-wrap gap-2">
                    <a
                        :href="searchConsole.reportUrl"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md bg-[#111b31] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1d2a47]"
                    >
                        Download Report
                    </a>
                    <a
                        :href="`${searchConsole.performanceUrl}?days=28`"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        View Query JSON
                    </a>
                </section>
            </div>
        </div>
    </AuthShell>
</template>
