<x-slide-over-shell
    open="isEditOpen"
    close="closeEdit()"
    submit="submitEdit()"
    title="{{ __('Edit Recipe') }}"
    description="{{ __('Update parent-level recipe metadata.') }}"
    title-id="edit-recipe-slide-over-title"
>
    <div x-show="editGeneralError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="editGeneralError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label for="recipe-edit-name" class="block text-sm font-medium text-gray-700">{{ __('Recipe Name') }}</label>
            <input
                id="recipe-edit-name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="editForm.name"
            />
            <p class="mt-1 text-sm text-red-600" x-show="editErrors.name.length" x-text="editErrors.name[0]"></p>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    x-model="editForm.is_active"
                >
                {{ __('Active') }}
            </label>
            <p class="mt-1 text-sm text-red-600" x-show="editErrors.is_active.length" x-text="editErrors.is_active[0]"></p>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    x-model="editForm.is_default"
                >
                {{ __('Default for output item') }}
            </label>
            <p class="mt-1 text-sm text-red-600" x-show="editErrors.is_default.length" x-text="editErrors.is_default[0]"></p>
        </div>

        <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">
            <p>{{ __('Execution fields such as recipe type, output quantity, and lines are versioned.') }}</p>
            <p class="mt-2">{{ __('Use New Version to change execution behavior.') }}</p>
        </div>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            x-on:click="closeEdit()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="isEditSubmitting"
            :class="isEditSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
        >
            {{ __('Save Changes') }}
        </button>
    </x-slot>
</x-slide-over-shell>
