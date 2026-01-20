<div
    x-cloak
    x-show="showPostConfirm"
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
>
    <div class="absolute inset-0 bg-gray-500/50" @click="closePostConfirm()"></div>
    <div class="absolute inset-0 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Post Inventory Count') }}
            </h3>
            <p class="text-sm text-gray-600">
                {{ __('Posting will lock this count and create ledger adjustments.') }}
            </p>

            <p class="text-sm text-red-600" x-show="errors.post" x-text="errors.post"></p>

            <div class="flex justify-end space-x-3">
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                    @click="closePostConfirm()"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                    @click="confirmPost()"
                >
                    {{ __('Post Count') }}
                </button>
            </div>
        </div>
    </div>
</div>
