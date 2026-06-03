<x-slide-over-shell
    open="isCreateOpen"
    close="closeCreate()"
    submit="submitCreate()"
    title="{{ __('Create Recipe') }}"
    description="{{ __('Add a new recipe for a manufacturable item.') }}"
    title-id="create-recipe-slide-over-title"
>
    <div x-show="createGeneralError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="createGeneralError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label for="recipe-name" class="block text-sm font-medium text-gray-700">{{ __('Recipe Name') }}</label>
            <input
                id="recipe-name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="createForm.name"
            />
            <p class="mt-1 text-sm text-red-600" x-show="createErrors.name.length" x-text="createErrors.name[0]"></p>
        </div>

        <div>
            <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    x-model="createOnlyWithoutRecipe"
                >
                {{ __('Only show items without a recipe') }}
            </label>
            <x-combobox
                class="mt-3"
                x-ref="createOutputItemCombobox"
                x-model="createForm.item_id"
                name="item_id"
                label="Output Item"
                placeholder="Search output items"
                no-results-text="No items found."
                options-expression="filteredCreateItems()"
                error-expression="createErrors.item_id[0] || ''"
            />
        </div>

        <div>
            <x-dropdown-select
                class="mt-1"
                x-model="createForm.recipe_type"
                name="recipe_type"
                label="Recipe Type"
                options-expression="availableCreateRecipeTypeOptions()"
                selected-value="manufacturing"
                placeholder="Select recipe type"
                error-expression="createErrors.recipe_type[0] || ''"
            >
                <x-dropdown-option value="manufacturing">Manufacturing</x-dropdown-option>
                <x-dropdown-option value="fulfillment">Fulfillment</x-dropdown-option>
            </x-dropdown-select>
        </div>

        <div>
            <label for="recipe-output-quantity" class="block text-sm font-medium text-gray-700">{{ __('Output per Run') }}</label>
            <x-ui.smart-number-input
                id="recipe-output-quantity"
                name="output_quantity"
                type="decimal"
                precision="6"
                disabled-expression="isFulfillmentRecipeType(createForm.recipe_type)"
                placeholder="0.000000"
                after-blur="normalizeCreateOutputQuantity()"
                x-model="createForm.output_quantity"
            />
            <p class="mt-1 text-xs text-gray-500" x-show="isFulfillmentRecipeType(createForm.recipe_type)">
                {{ __('Fulfillment recipes always produce exactly 1 unit.') }}
            </p>
            <p class="mt-1 text-sm text-red-600" x-show="createErrors.output_quantity.length" x-text="createErrors.output_quantity[0]"></p>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    x-model="createForm.is_active"
                >
                {{ __('Active') }}
            </label>
            <p class="mt-1 text-sm text-red-600" x-show="createErrors.is_active.length" x-text="createErrors.is_active[0]"></p>
        </div>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            x-on:click="closeCreate()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="isCreateSubmitting"
            :class="isCreateSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
        >
            {{ __('Create Recipe') }}
        </button>
    </x-slot>
</x-slide-over-shell>
