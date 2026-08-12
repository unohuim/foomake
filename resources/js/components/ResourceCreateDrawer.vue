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
        close-button-class="text-white hover:text-[#dbe8d0] focus-visible:outline-white"
        @close="emit('close')"
    >
        <form class="flex h-full flex-col bg-white" @submit.prevent="emit('submit')">
            <div class="h-0 flex-1 overflow-y-auto">
                <div class="create-header-texture relative overflow-hidden bg-[#001f3f] px-5 py-5 text-white sm:px-6">
                    <div class="relative flex items-center gap-3 pr-10">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-[#6f895d] bg-white/5 text-[#e6efd9]">
                            <slot name="header-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75v-1.5a3.75 3.75 0 0 0-3.75-3.75h-4.5A3.75 3.75 0 0 0 6 17.25v1.5M15.75 8.25a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM19.5 8.25v4.5M21.75 10.5h-4.5" />
                                </svg>
                            </slot>
                        </div>

                        <div class="min-w-0">
                            <h2 :id="titleId" class="text-base font-semibold leading-tight text-white">
                            {{ title }}
                            </h2>
                            <p v-if="description" class="mt-1 text-xs leading-5 text-blue-50">
                                {{ description }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-4 sm:px-6">
                    <slot name="error" :error="error">
                        <div v-if="error" class="mb-5 rounded-md bg-red-50 p-3 text-sm text-red-700">
                            {{ error }}
                        </div>
                    </slot>

                    <slot />
                </div>
            </div>

            <div class="grid shrink-0 grid-cols-2 gap-3 border-t border-gray-100 bg-white px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.06)] sm:px-6">
                <slot name="footer" :submitting="submitting">
                    <button
                        type="button"
                        class="cursor-pointer inline-flex h-9 items-center justify-center rounded-md border border-gray-200 bg-white px-3 text-xs font-semibold text-[#001f3f] shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#6f895d] focus:ring-offset-2"
                        @click="emit('close')"
                    >
                        {{ cancelLabel }}
                    </button>
                    <button
                        type="submit"
                        class="cursor-pointer inline-flex h-9 items-center justify-center gap-2 rounded-md border border-transparent bg-[#6f895d] px-3 text-xs font-semibold text-white shadow-sm transition hover:brightness-105 focus:outline-none focus:ring-2 focus:ring-[#6f895d] focus:ring-offset-2"
                        :disabled="submitting"
                        :class="submitting ? 'cursor-not-allowed opacity-60' : ''"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75v-1.5a3.75 3.75 0 0 0-3.75-3.75h-4.5A3.75 3.75 0 0 0 6 17.25v1.5M15.75 8.25a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM19.5 8.25v4.5M21.75 10.5h-4.5" />
                        </svg>
                        {{ submitLabel }}
                    </button>
                </slot>
            </div>
        </form>
    </BaseDrawer>
</template>

<style scoped>
.create-header-texture::before {
    background-image:
        radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.18) 1px, transparent 0),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 12px),
        linear-gradient(135deg, rgba(255, 255, 255, 0.07), transparent 48%);
    background-size: 16px 16px, 18px 18px, 100% 100%;
    content: "";
    inset: 0;
    opacity: 0.75;
    pointer-events: none;
    position: absolute;
}
</style>
