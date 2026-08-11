<script setup>
defineProps({
    name: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <svg
        class="shrink-0"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        aria-hidden="true"
    >
        <path
            v-if="name === 'dashboard'"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1v-9.5Z"
        />

        <path
            v-else-if="name === 'sales'"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M4 19h16M7 16V9m5 7V5m5 11v-4"
        />

        <template v-else-if="name === 'purchasing'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M7 7h13l-1.2 7H8.3L7 4H4"
            />
            <circle cx="9" cy="19" r="1.5" />
            <circle cx="18" cy="19" r="1.5" />
        </template>

        <template v-else-if="name === 'manufacturing'">
            <circle cx="12" cy="12" r="3" />
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h-.1A1.7 1.7 0 0 0 4 9.2a1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v-.1a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4H21a2 2 0 1 1 0 4h.1a1.7 1.7 0 0 0-1.1.4 1.7 1.7 0 0 0-.6 1Z"
            />
        </template>

        <template v-else-if="name === 'stock'">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"
            />
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="m4.5 8 7.5 4 7.5-4M12 12v8"
            />
        </template>

        <path
            v-else-if="name === 'account'"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"
        />

        <path
            v-else
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M5 12h14M12 5v14"
        />
    </svg>
</template>
