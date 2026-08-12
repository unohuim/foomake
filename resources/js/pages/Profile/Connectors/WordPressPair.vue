<script setup>
import { Head } from "@inertiajs/vue3";

import AuthShell from "../../../layouts/AuthShell.vue";

defineProps({
    shell: {
        type: Object,
        required: true,
    },
    pairing: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Pair WordPress Plugin" />

    <AuthShell :shell="shell" title="Pair WordPress Plugin">
        <div class="px-0 pb-10 pt-4 md:px-4 md:pt-5 lg:px-8">
            <div class="mx-auto w-full max-w-3xl px-4 sm:px-6 md:px-0">
                <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-950">Approve WordPress Plugin Pairing</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-600">
                            Confirm that this WordPress site can connect to your FooMake tenant.
                        </p>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div v-if="pairing.error" class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ pairing.error }}
                        </div>

                        <dl v-else class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Site</dt>
                                <dd class="mt-1 truncate text-slate-900">{{ pairing.siteName || pairing.siteUrl }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expires</dt>
                                <dd class="mt-1 text-slate-900">{{ pairing.expiresAt }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">URL</dt>
                                <dd class="mt-1 truncate text-slate-900">{{ pairing.siteUrl }}</dd>
                            </div>
                        </dl>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                            <a
                                class="inline-flex cursor-pointer items-center justify-center rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                :href="pairing.connectorsUrl"
                            >
                                Cancel
                            </a>

                            <form v-if="pairing.canApprove" method="post" :action="pairing.approveUrl">
                                <input type="hidden" name="_token" :value="pairing.csrfToken">
                                <input type="hidden" name="code" :value="pairing.code">
                                <button
                                    type="submit"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-md bg-[#6f895d] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#617952]"
                                >
                                    Approve Pairing
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthShell>
</template>
