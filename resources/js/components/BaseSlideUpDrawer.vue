<script setup>
import { onBeforeUnmount, onMounted } from "vue";

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    labelledBy: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(["close"]);

const close = () => {
    emit("close");
};

const handleKeydown = (event) => {
    if (!props.open || event.key !== "Escape") {
        return;
    }

    close();
};

onMounted(() => {
    document.addEventListener("keydown", handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
    <div
        class="fixed inset-0 z-[1000] transition-[visibility] duration-500 md:hidden"
        :class="open ? 'visible pointer-events-auto' : 'invisible pointer-events-none'"
        role="dialog"
        aria-modal="true"
        :aria-hidden="!open"
        :aria-labelledby="labelledBy"
    >
        <div
            class="absolute inset-0 bg-slate-950/40 transition-opacity duration-500 ease-in-out"
            :class="open ? 'opacity-100' : 'opacity-0'"
            @click="close"
        />

        <section
            class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-hidden rounded-t-xl bg-white shadow-2xl shadow-slate-950/25 transition-[transform,opacity] duration-500 ease-in-out"
            :class="open ? 'translate-y-0 opacity-100' : 'translate-y-full opacity-0'"
            @click.stop
        >
            <slot />
        </section>
    </div>
</template>
