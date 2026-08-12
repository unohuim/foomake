<script setup>
defineProps({
    connected: {
        type: Boolean,
        required: true,
    },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
        :class="connected ? 'bg-[#eef4e9] text-[#5f784f]' : 'bg-amber-50 text-amber-700'"
    >
        {{ connected ? "Connected" : "Disconnected" }}
    </span>
</template>
