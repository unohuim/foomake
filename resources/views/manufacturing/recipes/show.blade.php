<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Recipe') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $recipe->item?->name ?? '—' }}</p>
            </div>
            <a
                href="{{ route('manufacturing.recipes.index') }}"
                class="text-sm text-blue-600 hover:text-blue-500"
            >
                {{ __('Back to Recipes') }}
            </a>
        </div>
    </x-slot>

    <script type="application/json" id="manufacturing-recipes-show-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="manufacturing-recipes-show"
        data-payload="manufacturing-recipes-show-payload"
        x-data="manufacturingRecipesShow"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6 flex flex-col gap-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Output Item') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ __('The item produced by this recipe.') }}</p>
                        </div>
                        @can('inventory-make-orders-manage')
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                    x-on:click="openEditRecipe()"
                                >
                                    {{ __('Edit Recipe') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500"
                                    x-on:click="openDeleteRecipe()"
                                >
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        @endcan
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Name') }}</dt>
                            <dd class="mt-1 text-gray-900" x-text="recipe.item_name"></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Base UoM') }}</dt>
                            <dd class="mt-1 text-gray-900" x-text="recipe.item_uom"></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Active') }}</h3>
                    <p class="mt-2 text-sm text-gray-700" x-text="recipe.is_active ? 'Yes' : 'No'"></p>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Lines') }}</h3>
                        @can('inventory-make-orders-manage')
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                x-on:click="openCreateLine()"
                            >
                                {{ __('Add Line') }}
                            </button>
                        @endcan
                    </div>

                    <div x-show="lines.length === 0">
                        <p class="mt-2 text-sm text-gray-600">{{ __('No recipe lines yet.') }}</p>
                    </div>

                    <div class="overflow-x-auto" x-show="lines.length > 0">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500">
                                <tr class="border-b border-gray-100">
                                    <th class="px-3 py-2 font-medium">{{ __('Input Item') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Quantity') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('UoM') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="line in lines" :key="line.id">
                                    <tr>
                                        <td class="px-3 py-3 text-gray-900" x-text="line.item_name"></td>
                                        <td class="px-3 py-3 text-gray-600" x-text="line.quantity_display"></td>
                                        <td class="px-3 py-3 text-gray-600" x-text="line.item_uom"></td>
                                        <td class="px-3 py-3 text-right text-sm">
                                            @can('inventory-make-orders-manage')
                                                <button
                                                    type="button"
                                                    class="text-gray-700 hover:text-gray-900"
                                                    x-on:click="openEditLine(line)"
                                                >
                                                    {{ __('Edit') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="ml-3 text-red-600 hover:text-red-500"
                                                    x-on:click="openDeleteLine(line)"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            @endcan
                                            @cannot('inventory-make-orders-manage')
                                                <span class="text-gray-400">—</span>
                                            @endcannot
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @include('manufacturing.recipes.partials.delete-recipe-modal')
        @include('manufacturing.recipes.partials.edit-recipe-slide-over')
        @include('manufacturing.recipes.partials.line-form-slide-over')
        @include('manufacturing.recipes.partials.delete-line-modal')
    </div>
</x-app-layout>
