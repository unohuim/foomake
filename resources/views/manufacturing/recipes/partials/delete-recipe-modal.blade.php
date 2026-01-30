<div
    class="fixed inset-0 z-40 flex items-center justify-center"
    x-cloak
    x-show="isDeleteOpen"
    x-on:keydown.escape.window="closeDelete()"
>
    <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeDelete()"></div>
    <div class="relative z-50 w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Delete recipe?') }}</h3>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('This will permanently remove') }} <span class="font-medium" x-text="deleteRecipeName"></span>.
            </p>
            <p class="mt-3 text-sm text-red-600" x-show="deleteError" x-text="deleteError"></p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                    x-on:click="closeDelete()"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500"
                    x-on:click="submitDelete()"
                    :disabled="isDeleteSubmitting"
                    :class="isDeleteSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                >
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
