<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";

const props = defineProps({
    ariaLabel: {
        type: String,
        default: "Open menu",
    },
    align: {
        type: String,
        default: "right",
        validator: (value) => ["left", "right"].includes(value),
    },
    buttonClass: {
        type: String,
        default: "inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-blue-300 hover:text-gray-900",
    },
    menuClass: {
        type: String,
        default: "w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg",
    },
    offset: {
        type: Number,
        default: 8,
    },
});

const open = ref(false);
const root = ref(null);
const trigger = ref(null);
const menu = ref(null);
const menuTop = ref(0);
const menuLeft = ref(null);
const menuRight = ref(null);

const menuStyle = computed(() => ({
    top: `${menuTop.value}px`,
    left: menuLeft.value === null ? "auto" : `${menuLeft.value}px`,
    right: menuRight.value === null ? "auto" : `${menuRight.value}px`,
}));

const positionMenu = async () => {
    await nextTick();

    const triggerRect = trigger.value?.getBoundingClientRect();
    const menuRect = menu.value?.getBoundingClientRect();

    if (!triggerRect || !menuRect) {
        return;
    }

    menuTop.value = Math.max(
        props.offset,
        Math.min(triggerRect.bottom + props.offset, window.innerHeight - menuRect.height - props.offset),
    );

    if (props.align === "left") {
        menuLeft.value = Math.max(props.offset, Math.min(triggerRect.left, window.innerWidth - menuRect.width - props.offset));
        menuRight.value = null;

        return;
    }

    menuRight.value = Math.max(props.offset, window.innerWidth - triggerRect.right);
    menuLeft.value = null;
};

const close = () => {
    open.value = false;
};

const toggle = async () => {
    open.value = !open.value;

    if (open.value) {
        await positionMenu();
    }
};

const handleDocumentClick = (event) => {
    if (!open.value) {
        return;
    }

    if (root.value?.contains(event.target) || menu.value?.contains(event.target)) {
        return;
    }

    close();
};

const handleKeydown = (event) => {
    if (event.key === "Escape") {
        close();
    }
};

const closeOnViewportChange = () => {
    close();
};

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
    document.addEventListener("keydown", handleKeydown);
    window.addEventListener("resize", closeOnViewportChange);
    window.addEventListener("scroll", closeOnViewportChange, true);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
    document.removeEventListener("keydown", handleKeydown);
    window.removeEventListener("resize", closeOnViewportChange);
    window.removeEventListener("scroll", closeOnViewportChange, true);
});
</script>

<template>
    <div ref="root" class="relative inline-flex" @click.stop>
        <button
            ref="trigger"
            type="button"
            class="cursor-pointer"
            :class="buttonClass"
            :aria-label="ariaLabel"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="toggle"
        >
            <slot name="trigger" :open="open" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="fixed z-[80]"
                :class="menuClass"
                :style="menuStyle"
                role="menu"
                @click.stop
            >
                <slot :close="close" />
            </div>
        </Teleport>
    </div>
</template>
