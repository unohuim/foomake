<div
    class="fixed inset-0 z-50 overflow-hidden"
    x-cloak
    x-show="isMakeOrderFormOpen"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
            x-show="isMakeOrderFormOpen"
            x-on:click="closeMakeOrderForm()"
        ></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-md">
                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitMakeOrderForm()">
                    <div class="flex-1 overflow-y-auto p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900" x-text="isMakeOrderEditMode ? 'Edit make order' : 'Create make order'"></h2>
                                <p class="mt-1 text-sm text-gray-600" x-text="isMakeOrderEditMode ? 'Update the selected make order without leaving the page.' : 'Create a draft make order from an active recipe.'"></p>
                            </div>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-500"
                                x-on:click="closeMakeOrderForm()"
                            >
                                <span class="sr-only">{{ __('Close panel') }}</span>
                                ✕
                            </button>
                        </div>

                        <div class="mt-6" x-show="makeOrderFormGeneralError">
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
                                <input
                                    id="make-order-runs"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
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
                                />
                                <p class="mt-1 text-xs text-red-600" x-cloak x-show="makeOrderFormErrors.due_date.length" x-text="makeOrderFormErrors.due_date[0]"></p>
                            </div>

                            <p class="text-xs text-gray-500" x-cloak x-show="!canExecute">
                                {{ __('You do not have permission to create make orders.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
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
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
