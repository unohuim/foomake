<x-slide-over-shell
    open="isMakeOrderFormOpen"
    close="closeMakeOrderForm()"
    submit="submitMakeOrderForm()"
    title-expression="isMakeOrderEditMode ? 'Edit make order' : 'Create make order'"
    description-expression="isMakeOrderEditMode ? 'Update the selected make order without leaving the page.' : 'Create a draft make order from an active recipe.'"
    title-id="make-order-form-slide-over-title"
>
    <div x-show="makeOrderFormGeneralError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="makeOrderFormGeneralError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label for="make-order-recipe-id" class="block text-sm font-medium text-gray-700">
                {{ __('Recipe') }}
            </label>
            <select
                id="make-order-recipe-id"
                x-ref="makeOrderRecipeSelect"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="makeOrderForm.recipe_id"
            >
                <option value="">{{ __('Select a recipe') }}</option>
                <template x-for="recipe in makeOrderRecipes" :key="recipe.id">
                    <option x-bind:value="recipe.id" x-text="recipe.name"></option>
                </template>
            </select>
            <p class="mt-1 text-xs text-red-600" x-cloak x-show="makeOrderFormErrors.recipe_id.length" x-text="makeOrderFormErrors.recipe_id[0]"></p>
        </div>

        <div>
            <label for="make-order-runs" class="block text-sm font-medium text-gray-700">
                {{ __('Runs') }}
            </label>
            <x-ui.smart-number-input
                id="make-order-runs"
                name="runs"
                type="decimal"
                precision="6"
                x-model="makeOrderForm.runs"
                placeholder="0.000000"
            />
            <p class="mt-1 text-xs text-red-600" x-cloak x-show="makeOrderFormErrors.runs.length" x-text="makeOrderFormErrors.runs[0]"></p>
        </div>

        <div x-show="isMakeOrderEditMode">
            <label for="make-order-due-date" class="block text-sm font-medium text-gray-700">
                {{ __('Due Date') }}
            </label>
            <input
                id="make-order-due-date"
                type="date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="makeOrderForm.due_date"
                x-on:click="$el.showPicker?.()"
            />
            <p class="mt-1 text-xs text-red-600" x-cloak x-show="makeOrderFormErrors.due_date.length" x-text="makeOrderFormErrors.due_date[0]"></p>
        </div>

        <p class="text-xs text-gray-500" x-cloak x-show="!canExecute">
            {{ __('You do not have permission to create make orders.') }}
        </p>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            x-on:click="closeMakeOrderForm()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="isMakeOrderFormSubmitting || !canExecute"
            :class="isMakeOrderFormSubmitting || !canExecute ? 'opacity-50 cursor-not-allowed' : ''"
        >
            <span x-show="!isMakeOrderFormSubmitting" x-text="isMakeOrderEditMode ? 'Save' : 'Create'"></span>
            <span x-show="isMakeOrderFormSubmitting" x-text="isMakeOrderEditMode ? 'Saving...' : 'Creating...'"></span>
        </button>
    </x-slot>
</x-slide-over-shell>
