<x-slide-over-shell
    open="isLineFormOpen"
    close="closeLineForm()"
    submit="submitLineForm()"
    title-expression="isLineEditing ? 'Edit Line' : 'Add Line'"
    description="{{ __('Set the input item and quantity.') }}"
    title-id="recipe-line-form-slide-over-title"
>
    <div x-show="lineGeneralError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="lineGeneralError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label for="recipe-line-item" class="block text-sm font-medium text-gray-700">{{ __('Input Item') }}</label>
            <select
                id="recipe-line-item"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="lineForm.item_id"
            >
                <option value="">{{ __('Select an item') }}</option>
                <template x-for="item in lineItemOptions()" :key="item.id">
                    <option x-bind:value="item.id" x-text="item.name"></option>
                </template>
            </select>
            <p class="mt-1 text-sm text-red-600" x-show="lineErrors.item_id.length" x-text="lineErrors.item_id[0]"></p>
        </div>

        <div>
            <label for="recipe-line-quantity" class="block text-sm font-medium text-gray-700">{{ __('Quantity') }}</label>
            <input
                id="recipe-line-quantity"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                placeholder="0.000000"
                x-model="lineForm.quantity"
            />
            <p class="mt-1 text-sm text-red-600" x-show="lineErrors.quantity.length" x-text="lineErrors.quantity[0]"></p>
        </div>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            x-on:click="closeLineForm()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="isLineSubmitting"
            :class="isLineSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
        >
            <span x-text="isLineEditing ? 'Save Line' : 'Add Line'"></span>
        </button>
    </x-slot>
</x-slide-over-shell>
