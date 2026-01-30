<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Make Orders') }}
        </h2>
    </x-slot>

    <script type="application/json" id="manufacturing-make-orders-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="manufacturing-make-orders"
        data-payload="manufacturing-make-orders-payload"
        x-data="manufacturingMakeOrders"
    >
        <div class="fixed top-6 right-6 z-50" x-cloak x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Execute a recipe') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Select an active recipe and enter the output quantity to post stock moves.') }}
                        </p>
                    </div>

                    <div x-cloak x-show="recipes.length === 0" class="text-sm text-gray-600">
                        {{ __('No active recipes are available to execute.') }}
                    </div>

                    <form class="space-y-4" x-on:submit.prevent="submit()">
                        <div>
                            <label for="recipe_id" class="block text-sm font-medium text-gray-700">
                                {{ __('Recipe') }}
                            </label>
                            <select
                                id="recipe_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                x-model="form.recipe_id"
                            >
                                <option value="">{{ __('Select a recipe') }}</option>
                                <template x-for="recipe in recipes" :key="recipe.id">
                                    <option x-bind:value="recipe.id" x-text="recipe.item_name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-red-600" x-cloak x-show="errors.recipe_id.length" x-text="errors.recipe_id[0]"></p>
                        </div>

                        <div>
                            <label for="output_quantity" class="block text-sm font-medium text-gray-700">
                                {{ __('Output quantity') }}
                            </label>
                            <input
                                id="output_quantity"
                                type="text"
                                inputmode="decimal"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                x-model="form.output_quantity"
                                placeholder="0.000000"
                            />
                            <p class="mt-1 text-xs text-red-600" x-cloak x-show="errors.output_quantity.length" x-text="errors.output_quantity[0]"></p>
                        </div>

                        <div class="flex items-center justify-between">
                            <p class="text-sm text-red-600" x-cloak x-show="generalError" x-text="generalError"></p>

                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                x-bind:disabled="isSubmitting || !canExecute"
                            >
                                <span x-show="!isSubmitting">{{ __('Execute') }}</span>
                                <span x-show="isSubmitting">{{ __('Executing...') }}</span>
                            </button>
                        </div>

                        <p class="text-xs text-gray-500" x-cloak x-show="!canExecute">
                            {{ __('You do not have permission to execute make orders.') }}
                        </p>
                    </form>
                </div>
            </div>

            <div
                class="bg-white border border-gray-100 shadow-sm sm:rounded-lg"
                x-cloak
                x-show="summaryVisible"
            >
                <div class="p-6 space-y-2">
                    <h4 class="text-sm font-semibold text-gray-900">{{ __('Execution summary') }}</h4>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium text-gray-700">{{ __('Recipe') }}:</span>
                        <span x-text="summary.output_item_name"></span>
                    </p>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium text-gray-700">{{ __('Output quantity') }}:</span>
                        <span x-text="summary.output_quantity"></span>
                    </p>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium text-gray-700">{{ __('Moves created') }}:</span>
                        <span x-text="summary.move_count"></span>
                        <span class="text-gray-500">(</span>
                        <span class="text-gray-500" x-text="summary.issue_count"></span>
                        <span class="text-gray-500"> {{ __('issue') }}, </span>
                        <span class="text-gray-500" x-text="summary.receipt_count"></span>
                        <span class="text-gray-500"> {{ __('receipt') }})</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
