<script setup>
const props = defineProps({
    checked: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    name: {
        type: String,
        default: "toggle",
    },
    record: {
        type: Object,
        default: null,
    },
    ariaLabel: {
        type: String,
        default: "Toggle",
    },
});

const emit = defineEmits(["update:checked", "change"]);

function handleClick() {
    if (props.disabled) {
        return;
    }

    const nextChecked = !props.checked;
    const detail = {
        name: props.name,
        checked: nextChecked,
        value: nextChecked,
        row: props.record,
        record: props.record,
        id: props.record?.id ?? null,
    };

    emit("update:checked", nextChecked);
    emit("change", detail);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        class="group relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-500 disabled:cursor-not-allowed disabled:opacity-50"
        :class="checked ? 'bg-lime-500' : 'bg-gray-200'"
        :aria-checked="checked ? 'true' : 'false'"
        :aria-label="ariaLabel"
        :disabled="disabled"
        data-ui-toggle
        @click.stop.prevent="handleClick"
    >
        <span class="sr-only">{{ ariaLabel }}</span>
        <span
            aria-hidden="true"
            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="checked ? 'translate-x-5' : 'translate-x-0'"
        />
    </button>
</template>
