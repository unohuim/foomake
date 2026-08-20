<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    views: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(["update:modelValue", "select"]);

const open = ref(false);
const root = ref(null);

const activeView = computed(() => props.views.find((view) => view.key === props.modelValue) || props.views[0]);

const selectView = (view) => {
    emit("update:modelValue", view.key);
    emit("select", view);
    open.value = false;
};

const handleDocumentClick = (event) => {
    if (!open.value || root.value?.contains(event.target)) {
        return;
    }

    open.value = false;
};

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative w-full sm:w-72">
        <button
            type="button"
            class="flex h-10 w-full cursor-pointer items-center justify-between border border-slate-300 bg-white px-3 text-left text-sm font-semibold text-[#111b31] shadow-sm transition hover:border-[#111b31]"
            :aria-expanded="open ? 'true' : 'false'"
            @click="open = !open"
        >
            <span class="truncate">{{ activeView?.label || "Select view" }}</span>
            <svg
                class="h-4 w-4 shrink-0 text-slate-500 transition-transform"
                :class="open ? 'rotate-180' : ''"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.512a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute left-0 right-0 top-11 z-[150] border border-slate-200 bg-white shadow-xl"
        >
            <button
                v-for="view in views"
                :key="view.key"
                type="button"
                class="block w-full cursor-pointer px-3 py-2 text-left transition hover:bg-slate-50"
                :class="view.key === modelValue ? 'bg-slate-100' : ''"
                @click="selectView(view)"
            >
                <span class="block text-xs font-semibold text-[#111b31]">{{ view.label }}</span>
                <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">{{ view.description }}</span>
            </button>
        </div>
    </div>
</template>
