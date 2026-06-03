<x-slide-over-shell
    open="isVersionOpen"
    close="closeVersion()"
    submit="submitVersion()"
    title="{{ __('New Recipe Version') }}"
    description="{{ __('Create a new execution template for this recipe.') }}"
    title-id="create-recipe-version-slide-over-title"
>
    <div x-show="versionGeneralError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="versionGeneralError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('Recipe Name') }}</label>
            <p class="mt-1 text-sm text-gray-900" x-text="versionRecipeName"></p>
        </div>

        <div>
            <x-dropdown-select
                class="mt-1"
                x-model="versionForm.recipe_type"
                name="version_recipe_type"
                label="Recipe Type"
                options-expression="versionRecipeTypeOptions()"
                placeholder="Select recipe type"
                error-expression="versionErrors.recipe_type[0] || ''"
            >
                <x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>
                <x-dropdown-option value="fulfillment">Fulfillment</x-dropdown-option>
            </x-dropdown-select>
        </div>

        <div>
            <label for="recipe-version-output-quantity" class="block text-sm font-medium text-gray-700">{{ __('Output Qty') }}</label>
            <x-ui.smart-number-input
                id="recipe-version-output-quantity"
                name="output_quantity"
                type="decimal"
                precision="6"
                x-model="versionForm.output_quantity"
            />
            <p class="mt-1 text-sm text-red-600" x-show="versionErrors.output_quantity.length" x-text="versionErrors.output_quantity[0]"></p>
        </div>

        <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">
            <p>{{ __('New versions start as drafts and automatically clone the current version ingredients.') }}</p>
        </div>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            x-on:click="closeVersion()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="isVersionSubmitting"
            :class="isVersionSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
        >
            {{ __('Create Version') }}
        </button>
    </x-slot>
</x-slide-over-shell>
