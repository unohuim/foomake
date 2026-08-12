<script setup>
import { onBeforeUnmount, onMounted } from "vue";

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    labelledBy: {
        type: String,
        required: true,
    },
    closeLabel: {
        type: String,
        default: "Close drawer",
    },
    panelClass: {
        type: String,
        default: "w-screen max-w-md",
    },
    closeButtonClass: {
        type: String,
        default: "text-slate-400 hover:text-slate-600 focus-visible:outline-blue-950",
    },
});

const emit = defineEmits(["close"]);

const handleKeydown = (event) => {
    if (props.open && event.key === "Escape") {
        emit("close");
    }
};

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
    <div
        class="fixed inset-0 z-50 overflow-hidden transition-[visibility] duration-500"
        :class="open ? 'visible pointer-events-auto' : 'invisible pointer-events-none'"
        :aria-labelledby="labelledBy"
        role="dialog"
        aria-modal="true"
        :aria-hidden="!open"
    >
        <div
            class="absolute inset-0 bg-stone-950/30 transition-opacity duration-500 ease-in-out"
            :class="open ? 'opacity-100' : 'opacity-0'"
            @click="emit('close')"
        />

        <div class="pointer-events-none absolute inset-y-0 right-0 flex w-full max-w-full justify-end pl-8 sm:pl-12">
            <aside
                class="pointer-events-auto relative h-full transform-gpu overflow-hidden bg-white shadow-2xl transition-[transform,opacity] duration-500 ease-in-out"
                :class="[panelClass, open ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0']"
                @click.stop
            >
                <button
                    type="button"
                    class="cursor-pointer absolute right-4 top-4 z-10 rounded-md transition focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                    :class="closeButtonClass"
                    @click="emit('close')"
                >
                    <span class="sr-only">{{ closeLabel }}</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                <slot />
            </aside>
        </div>
    </div>
</template>
