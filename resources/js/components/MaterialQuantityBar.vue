<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
    cards: {
        type: Array,
        default: () => [],
    },
});

const activeStat = ref(props.cards[0]?.key ?? null);

const normalizedCards = computed(() => Array.isArray(props.cards) ? props.cards : []);

watch(normalizedCards, (cards) => {
    if (!cards.some((card) => card.key === activeStat.value)) {
        activeStat.value = cards[0]?.key ?? null;
    }
});

const desktopGridClass = computed(() => {
    switch (normalizedCards.value.length) {
    case 1:
        return "sm:grid-cols-1";
    case 2:
        return "sm:grid-cols-2";
    case 3:
        return "sm:grid-cols-3";
    case 4:
        return "sm:grid-cols-4";
    default:
        return "sm:grid-cols-5";
    }
});

function asString(value, fallback = "") {
    return typeof value === "string" && value.trim() !== "" ? value : fallback;
}

function compactLabel(card) {
    return asString(card?.compact_label, asString(card?.mobile_label, asString(card?.label, "-")));
}

function quantityDisplay(card) {
    return asString(card?.quantity_display_grouped, asString(card?.quantity_display, "0"));
}

function uomSymbol(card) {
    return asString(card?.uom_symbol);
}
</script>

<template>
    <section
        v-if="normalizedCards.length > 0"
        class="-mx-1 overflow-hidden border-y border-slate-200 bg-white shadow-sm sm:mx-0 sm:rounded-2xl sm:border"
        data-material-inventory-stats
    >
        <div
            class="flex sm:hidden"
            role="tablist"
            aria-label="Inventory stats"
        >
            <button
                v-for="card in normalizedCards"
                :key="card.key"
                type="button"
                role="tab"
                :aria-label="card.label || card.compact_label || 'Inventory stat'"
                class="relative min-h-20 cursor-pointer overflow-hidden border-r border-slate-200 transition-[width,flex-grow,background-color,padding,opacity] duration-[400ms] ease-in-out last:border-r-0"
                :class="activeStat === card.key ? 'flex-1 bg-white px-3 py-2' : 'w-8 bg-slate-50'"
                :aria-selected="activeStat === card.key ? 'true' : 'false'"
                @click="activeStat = card.key"
            >
                <span
                    class="absolute inset-0 flex h-full items-center justify-center transition-opacity duration-[400ms] ease-in-out"
                    :class="activeStat === card.key ? 'opacity-0' : 'opacity-100'"
                    aria-hidden="true"
                >
                    <span class="-rotate-90 whitespace-nowrap text-[0.62rem] font-semibold uppercase leading-none tracking-wide text-slate-500">
                        {{ compactLabel(card) }}
                    </span>
                </span>

                <span
                    class="absolute inset-0 flex h-full items-center justify-between gap-3 px-3 py-2 transition-opacity duration-[400ms] ease-in-out"
                    :class="activeStat === card.key ? 'opacity-100' : 'opacity-0'"
                >
                    <span class="min-w-0 text-left">
                        <span class="block truncate whitespace-nowrap text-xs font-medium text-slate-500">
                            {{ card.label || "-" }}
                        </span>
                        <span class="mt-1 flex items-baseline gap-1">
                            <span class="text-xl font-semibold tracking-tight text-slate-900">
                                {{ quantityDisplay(card) }}
                            </span>
                            <span
                                v-if="uomSymbol(card) !== ''"
                                class="text-xs font-medium text-slate-500"
                            >
                                {{ uomSymbol(card) }}
                            </span>
                        </span>
                    </span>
                </span>
            </button>
        </div>

        <dl
            class="hidden divide-slate-200 sm:grid sm:divide-x"
            :class="desktopGridClass"
        >
            <div
                v-for="card in normalizedCards"
                :key="card.key"
                class="px-2 py-2 sm:px-6 sm:py-5"
            >
                <dt class="min-w-0 text-xs font-medium text-slate-500 sm:text-sm">
                    <span class="sm:hidden">{{ card.mobile_label || card.label || "-" }}</span>
                    <span class="hidden truncate whitespace-nowrap sm:block">{{ card.label || "-" }}</span>
                </dt>
                <dd class="mt-1 flex items-baseline gap-2 sm:mt-2">
                    <span class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
                        {{ quantityDisplay(card) }}
                    </span>
                    <span
                        v-if="uomSymbol(card) !== ''"
                        class="text-xs font-medium text-slate-500 sm:text-sm"
                    >
                        {{ uomSymbol(card) }}
                    </span>
                </dd>
            </div>
        </dl>
    </section>
</template>
