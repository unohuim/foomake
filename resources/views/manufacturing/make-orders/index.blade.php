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

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Create make order') }}</h3>
                            <p class="text-sm text-gray-600">{{ __('Create a draft make order from an active recipe.') }}</p>
                        </div>
                    </div>

                    <form class="grid gap-4 sm:grid-cols-3" x-on:submit.prevent="submitCreate()">
                        <div class="sm:col-span-1">
                            <label for="recipe_id" class="block text-sm font-medium text-gray-700">
                                {{ __('Recipe') }}
                            </label>
                            <select
                                id="recipe_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                x-model="createForm.recipe_id"
                            >
                                <option value="">{{ __('Select a recipe') }}</option>
                                <template x-for="recipe in recipes" :key="recipe.id">
                                    <option x-bind:value="recipe.id" x-text="recipe.item_name"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-red-600" x-cloak x-show="createErrors.recipe_id.length" x-text="createErrors.recipe_id[0]"></p>
                        </div>

                        <div class="sm:col-span-1">
                            <label for="output_quantity" class="block text-sm font-medium text-gray-700">
                                {{ __('Output quantity') }}
                            </label>
                            <input
                                id="output_quantity"
                                type="text"
                                inputmode="decimal"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                x-model="createForm.output_quantity"
                                placeholder="0.000000"
                            />
                            <p class="mt-1 text-xs text-red-600" x-cloak x-show="createErrors.output_quantity.length" x-text="createErrors.output_quantity[0]"></p>
                        </div>

                        <div class="sm:col-span-1 flex items-end justify-end">
                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                x-bind:disabled="isCreateSubmitting || !canExecute"
                            >
                                <span x-show="!isCreateSubmitting">{{ __('Create') }}</span>
                                <span x-show="isCreateSubmitting">{{ __('Creating...') }}</span>
                            </button>
                        </div>
                    </form>

                    <p class="text-sm text-red-600" x-cloak x-show="createGeneralError" x-text="createGeneralError"></p>
                    <p class="text-xs text-gray-500" x-cloak x-show="!canExecute">
                        {{ __('You do not have permission to create make orders.') }}
                    </p>
                </div>
            </div>

            <div x-cloak x-show="makeOrders.length === 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('No make orders yet') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ __('Create a make order to track production runs.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div x-cloak x-show="makeOrders.length > 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Output Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Quantity') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Due Date') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="order in makeOrders" :key="order.id">
                                        <tr>
                                            <td class="px-3 py-3 text-gray-900" x-text="order.output_item_name"></td>
                                            <td class="px-3 py-3 text-gray-600" x-text="order.output_quantity"></td>
                                            <td class="px-3 py-3 text-gray-600" x-text="order.status"></td>
                                            <td class="px-3 py-3 text-gray-600">
                                                <div class="space-y-1">
                                                    <input
                                                        type="date"
                                                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="scheduleDates[order.id]"
                                                        x-bind:disabled="order.status === 'MADE' || !canExecute"
                                                        x-bind:placeholder="order.due_date || ''"
                                                    />
                                                    <p class="text-xs text-red-600" x-cloak x-show="getScheduleErrors(order.id).due_date.length" x-text="getScheduleErrors(order.id).due_date[0]"></p>
                                                    <p class="text-xs text-red-600" x-cloak x-show="getScheduleErrors(order.id).recipe_id.length" x-text="getScheduleErrors(order.id).recipe_id[0]"></p>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 text-right space-y-2">
                                                <div class="flex flex-col items-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="text-blue-600 hover:text-blue-500"
                                                        x-on:click="scheduleOrder(order.id)"
                                                        x-bind:disabled="scheduleSubmitting[order.id] || order.status === 'MADE' || !canExecute"
                                                    >
                                                        <span x-show="!scheduleSubmitting[order.id]">{{ __('Schedule') }}</span>
                                                        <span x-show="scheduleSubmitting[order.id]">{{ __('Scheduling...') }}</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="text-green-600 hover:text-green-500"
                                                        x-on:click="makeOrder(order.id)"
                                                        x-bind:disabled="makeSubmitting[order.id] || order.status === 'MADE' || !canExecute"
                                                    >
                                                        <span x-show="!makeSubmitting[order.id]">{{ __('Make') }}</span>
                                                        <span x-show="makeSubmitting[order.id]">{{ __('Making...') }}</span>
                                                    </button>
                                                    <p class="text-xs text-red-600" x-cloak x-show="getMakeErrors(order.id).recipe_id.length" x-text="getMakeErrors(order.id).recipe_id[0]"></p>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
