<script setup>
import BaseDrawer from "./BaseDrawer.vue";

defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        default: "Create",
    },
    description: {
        type: String,
        default: "",
    },
    titleId: {
        type: String,
        default: "resource-create-drawer-title",
    },
    submitLabel: {
        type: String,
        default: "Save",
    },
    cancelLabel: {
        type: String,
        default: "Cancel",
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: "",
    },
    panelClass: {
        type: String,
        default: "w-screen max-w-md",
    },
});

const emit = defineEmits(["close", "submit"]);
</script>

<template>
    <BaseDrawer
        :open="open"
        :labelled-by="titleId"
        close-label="Close create drawer"
        :panel-class="panelClass"
        @close="emit('close')"
    >
        <form class="flex h-full flex-col divide-y divide-gray-200" @submit.prevent="emit('submit')">
            <div class="h-0 flex-1 overflow-y-auto">
                <div class="bg-blue-600 px-4 py-6 sm:px-6">
                    <div class="pr-10">
                        <h2 :id="titleId" class="text-lg font-semibold text-white">
                            {{ title }}
                        </h2>
                        <p v-if="description" class="mt-1 text-sm text-blue-100">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div class="px-4 py-6 sm:px-6">
                    <slot name="error" :error="error">
                        <div v-if="error" class="mb-6 rounded-md bg-red-50 p-3 text-sm text-red-700">
                            {{ error }}
                        </div>
                    </slot>

                    <slot />
                </div>
            </div>

            <div class="flex shrink-0 justify-end gap-3 px-4 py-4 sm:px-6">
                <slot name="footer" :submitting="submitting">
                    <button
                        type="button"
                        class="cursor-pointer inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        @click="emit('close')"
                    >
                        {{ cancelLabel }}
                    </button>
                    <button
                        type="submit"
                        class="cursor-pointer inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        :disabled="submitting"
                        :class="submitting ? 'cursor-not-allowed opacity-60' : ''"
                    >
                        {{ submitLabel }}
                    </button>
                </slot>
            </div>
        </form>
    </BaseDrawer>
</template>
