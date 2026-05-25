<div
    class="fixed inset-0 z-50 overflow-hidden"
    x-cloak
    x-show="isVersionOpen"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
            x-show="isVersionOpen"
            x-on:click="closeVersion()"
        ></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-md">
                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitVersion()">
                    <div class="flex-1 overflow-y-auto p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">{{ __('New Recipe Version') }}</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Create a new execution template for this recipe.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-500"
                                x-on:click="closeVersion()"
                            >
                                <span class="sr-only">{{ __('Close panel') }}</span>
                                ✕
                            </button>
                        </div>

                        <div class="mt-6" x-show="versionGeneralError">
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
                                <input
                                    id="recipe-version-output-quantity"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    x-model="versionForm.output_quantity"
                                />
                                <p class="mt-1 text-sm text-red-600" x-show="versionErrors.output_quantity.length" x-text="versionErrors.output_quantity[0]"></p>
                            </div>

                            <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">
                                <p>{{ __('New versions start as drafts and automatically clone the current version ingredients.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
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
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
