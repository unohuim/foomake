<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

import AuthShell from "../../layouts/AuthShell.vue";
import MarketingViewDropdown from "../../components/MarketingViewDropdown.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    searchConsole: {
        type: Object,
        required: true,
    },
});

const selectedView = ref("query");
const selectedTimeframe = ref("28d");
const loading = ref(false);
const error = ref("");
const payload = ref(null);
const sortKey = ref("");
const sortDirection = ref("desc");

const activeView = computed(() => props.searchConsole.views.find((view) => view.key === selectedView.value));

const rows = computed(() => {
    const baseRows = payload.value?.rows || [];

    if (!sortKey.value) {
        return baseRows;
    }

    return [...baseRows].sort((first, second) => {
        const firstValue = first[sortKey.value];
        const secondValue = second[sortKey.value];
        const firstNumber = Number(firstValue);
        const secondNumber = Number(secondValue);
        const direction = sortDirection.value === "asc" ? 1 : -1;

        if (!Number.isNaN(firstNumber) && !Number.isNaN(secondNumber)) {
            return (firstNumber - secondNumber) * direction;
        }

        return String(firstValue || "").localeCompare(String(secondValue || "")) * direction;
    });
});
const tableStatusText = computed(() => {
    if (selectedView.value === "report") {
        return "The markdown report includes summary, analysis, recommendations, and query detail.";
    }

    return dateRangeLabel.value || "Select a view to load data.";
});

const columns = computed(() => {
    const hourlyColumns = selectedTimeframe.value === "24h"
        ? [{ key: "hour", label: "Hour", align: "left" }]
        : [];

    if (selectedView.value === "page") {
        return [
            ...hourlyColumns,
            { key: "page", label: "Page", align: "left" },
            { key: "clicks", label: "Clicks", align: "right" },
            { key: "impressions", label: "Impressions", align: "right" },
            { key: "ctr", label: "CTR", align: "right", format: "percent" },
            { key: "position", label: "Position", align: "right", format: "number" },
        ];
    }

    if (selectedView.value === "query_by_page") {
        return [
            ...hourlyColumns,
            { key: "page", label: "Page", align: "left" },
            { key: "query", label: "Query", align: "left" },
            { key: "clicks", label: "Clicks", align: "right" },
            { key: "impressions", label: "Impressions", align: "right" },
            { key: "ctr", label: "CTR", align: "right", format: "percent" },
            { key: "position", label: "Position", align: "right", format: "number" },
        ];
    }

    if (selectedView.value === "comparison") {
        return [
            ...hourlyColumns,
            { key: "query", label: "Query", align: "left" },
            { key: "impressions", label: "7d Impr.", align: "right" },
            { key: "priorImpressions", label: "Prior Impr.", align: "right" },
            { key: "impressionDelta", label: "Delta", align: "right", format: "signed" },
            { key: "clicks", label: "7d Clicks", align: "right" },
            { key: "priorClicks", label: "Prior Clicks", align: "right" },
            { key: "clickDelta", label: "Delta", align: "right", format: "signed" },
        ];
    }

    return [
        ...hourlyColumns,
        { key: "query", label: "Query", align: "left" },
        { key: "clicks", label: "Clicks", align: "right" },
        { key: "impressions", label: "Impressions", align: "right" },
        { key: "ctr", label: "CTR", align: "right", format: "percent" },
        { key: "position", label: "Position", align: "right", format: "number" },
    ];
});

const dateRangeLabel = computed(() => {
    const range = payload.value?.dateRange;

    if (!range) {
        return "";
    }

    if (selectedView.value === "comparison") {
        return `${range.current.start} to ${range.current.end} vs ${range.prior.start} to ${range.prior.end}`;
    }

    return `${range.start} to ${range.end}`;
});

const formatValue = (value, format) => {
    if (format === "percent") {
        return `${((Number(value) || 0) * 100).toFixed(2)}%`;
    }

    if (format === "number") {
        return (Number(value) || 0).toFixed(2);
    }

    if (format === "signed") {
        const number = Number(value) || 0;

        return number > 0 ? `+${number}` : `${number}`;
    }

    return value || "-";
};

const sortBy = (column) => {
    if (sortKey.value === column.key) {
        sortDirection.value = sortDirection.value === "desc" ? "asc" : "desc";
        return;
    }

    sortKey.value = column.key;
    sortDirection.value = "desc";
};

const loadSelectedView = async () => {
    const view = activeView.value;

    if (!view) {
        return;
    }

    selectedView.value = view.key;
    error.value = "";

    if (view.key === "report") {
        payload.value = null;
        return;
    }

    loading.value = true;

    try {
        const params = new URLSearchParams({
            view: view.key,
            timeframe: selectedTimeframe.value,
        });
        const response = await fetch(`${props.searchConsole.dataUrl}?${params.toString()}`, {
            headers: {
                Accept: "application/json",
            },
        });
        const data = await response.json();

        if (!response.ok) {
            error.value = data.message || "Unable to load Search Console data.";
            return;
        }

        payload.value = data.data;
        sortKey.value = selectedView.value === "comparison" ? "impressionDelta" : "impressions";
        sortDirection.value = "desc";
    } catch (requestError) {
        error.value = "Unable to load Search Console data.";
    } finally {
        loading.value = false;
    }
};

const selectView = (view) => {
    selectedView.value = view.key;
    loadSelectedView();
};

const selectTimeframe = (event) => {
    selectedTimeframe.value = event.target.value;
    loadSelectedView();
};

onMounted(() => {
    if (props.searchConsole.connected) {
        selectedView.value = (props.searchConsole.views.find((view) => view.key === "query") || props.searchConsole.views[0]).key;
        loadSelectedView();
    }
});
</script>

<template>
    <Head title="Marketing" />

    <AuthShell :shell="shell" title="Marketing">
        <div class="flex h-[calc(100vh-4rem)] flex-col px-0 pt-3 md:px-4 lg:px-8">
            <div class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 px-4 sm:px-5 md:px-0">
                <section class="shrink-0 border-b border-slate-200 pb-3">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Admin</p>
                            <h1 class="mt-1 text-xl font-semibold text-[#111b31]">Marketing</h1>
                            <p class="mt-1 max-w-2xl text-xs text-slate-600">
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

                <section class="grid shrink-0 gap-3 md:grid-cols-[1fr,auto] md:items-center">
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

                <section v-if="searchConsole.connected" class="flex min-h-0 flex-1 flex-col border border-slate-200 bg-white shadow-sm">
                    <div class="z-20 flex shrink-0 flex-col gap-3 border-b border-slate-200 bg-white px-3 py-3 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-[#111b31]">{{ activeView?.label || "Search Console Data" }}</h2>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ tableStatusText }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <label class="sr-only" for="marketing-timeframe">Timeframe</label>
                            <select
                                id="marketing-timeframe"
                                class="h-10 cursor-pointer border border-slate-300 bg-white px-3 text-sm font-semibold text-[#111b31] shadow-sm transition hover:border-[#111b31]"
                                :value="selectedTimeframe"
                                @change="selectTimeframe"
                            >
                                <option
                                    v-for="timeframe in searchConsole.timeframes"
                                    :key="timeframe.key"
                                    :value="timeframe.key"
                                >
                                    {{ timeframe.label }}
                                </option>
                            </select>
                            <MarketingViewDropdown
                                v-model="selectedView"
                                :views="searchConsole.views"
                                @select="selectView"
                            />
                            <a
                                :href="searchConsole.reportUrl"
                                class="inline-flex h-10 w-10 cursor-pointer items-center justify-center border border-slate-300 bg-white text-slate-700 transition hover:border-[#111b31] hover:text-[#111b31]"
                                title="Download markdown report"
                            >
                                <span class="sr-only">Download markdown report</span>
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <path d="M7 10l5 5 5-5" />
                                    <path d="M12 15V3" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div v-if="loading" class="px-4 py-6 text-sm text-slate-500">
                        Loading Search Console data...
                    </div>

                    <div v-else-if="error" class="px-4 py-4 text-sm text-red-600">
                        {{ error }}
                    </div>

                    <div v-else-if="selectedView === 'report'" class="px-4 py-4 text-sm text-slate-600">
                        Use the download icon in the table header to save the markdown report.
                    </div>

                    <div v-else-if="rows.length === 0" class="px-4 py-6 text-sm text-slate-500">
                        Select a view to load rows.
                    </div>

                    <div v-else class="min-h-0 flex-1 overflow-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th
                                        v-for="column in columns"
                                        :key="column.key"
                                        class="px-3 py-2"
                                        :class="column.align === 'right' ? 'text-right' : 'text-left'"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center gap-1 font-semibold uppercase tracking-wide transition hover:text-[#111b31]"
                                            :class="column.align === 'right' ? 'justify-end' : 'justify-start'"
                                            @click="sortBy(column)"
                                        >
                                            <span>{{ column.label }}</span>
                                            <span class="inline-flex h-3 w-3 items-center justify-center text-[10px]">
                                                <span v-if="sortKey === column.key">{{ sortDirection === "desc" ? "↓" : "↑" }}</span>
                                                <span v-else class="text-slate-300">↕</span>
                                            </span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="(row, index) in rows" :key="`${selectedView}-${index}`">
                                    <td
                                        v-for="column in columns"
                                        :key="`${index}-${column.key}`"
                                        class="max-w-[22rem] px-3 py-2"
                                        :class="column.align === 'right' ? 'text-right tabular-nums' : 'truncate text-left'"
                                    >
                                        {{ formatValue(row[column.key], column.format) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthShell>
</template>
