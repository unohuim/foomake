<script setup>
import { ref } from "vue";

const props = defineProps({
    downloadUrl: {
        type: String,
        required: true,
    },
});

const confirmOpen = ref(false);

const openConfirm = () => {
    confirmOpen.value = true;
};

const closeConfirm = () => {
    confirmOpen.value = false;
};

const downloadPlugin = () => {
    if (!props.downloadUrl) {
        return;
    }

    window.location.href = props.downloadUrl;
    closeConfirm();
};
</script>

<template>
    <section>
        <div>
            <button
                type="button"
                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md bg-[#4f8f45] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#437d3b] focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#6f895d]"
                @click="openConfirm"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                </svg>
                Download WP Plugin
            </button>
        </div>

        <Teleport to="body">
            <div
                v-if="confirmOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wordpress-plugin-download-title"
                @click="closeConfirm"
            >
                <div class="w-full max-w-sm rounded-md bg-white shadow-xl" @click.stop>
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 id="wordpress-plugin-download-title" class="text-sm font-semibold text-slate-950">
                            Download WordPress plugin?
                        </h2>
                        <p class="mt-1 text-xs leading-5 text-slate-600">
                            Your browser will download the current FooMake connector zip.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-4">
                        <button
                            type="button"
                            class="cursor-pointer rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="closeConfirm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md bg-[#6f895d] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#617952]"
                            @click="downloadPlugin"
                        >
                            Yes, Download
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </section>
</template>
