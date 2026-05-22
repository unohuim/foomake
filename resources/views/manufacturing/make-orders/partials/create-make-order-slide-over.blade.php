<div
    class="fixed inset-0 z-50 overflow-hidden"
    x-cloak
    x-show="isMakeOrderCreateOpen"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
            x-show="isMakeOrderCreateOpen"
            x-on:click="closeMakeOrderCreate()"
        ></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-md">
                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitMakeOrderCreate()">
                    <div class="flex-1 overflow-y-auto p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">{{ __('Create make order') }}</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ __('Create a draft make order from an active recipe.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-500"
                                x-on:click="closeMakeOrderCreate()"
                            >
                                <span class="sr-only">{{ __('Close panel') }}</span>
                                ✕
                            </button>
                        </div>

                        <div class="mt-6" x-show="makeOrderCreateGeneralError">
                            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="makeOrderCreateGeneralError"></div>
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
                                    x-model="makeOrderCreateForm.recipe_id"
                                >
                                    <option value="">{{ __('Select a recipe') }}</option>
                                    <template x-for="recipe in makeOrderCreateRecipes" :key="recipe.id">
                                        <option x-bind:value="recipe.id" x-text="recipe.name"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-red-600" x-cloak x-show="makeOrderCreateErrors.recipe_id.length" x-text="makeOrderCreateErrors.recipe_id[0]"></p>
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
                                    x-model="makeOrderCreateForm.runs"
                                    placeholder="0.000000"
                                />
                                <p class="mt-1 text-xs text-red-600" x-cloak x-show="makeOrderCreateErrors.runs.length" x-text="makeOrderCreateErrors.runs[0]"></p>
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
                            x-on:click="closeMakeOrderCreate()"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :disabled="isMakeOrderCreateSubmitting || !canExecute"
                            :class="isMakeOrderCreateSubmitting || !canExecute ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            <span x-show="!isMakeOrderCreateSubmitting">{{ __('Create') }}</span>
                            <span x-show="isMakeOrderCreateSubmitting">{{ __('Creating...') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
