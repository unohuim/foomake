<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Recipes') }}
        </h2>
    </x-slot>

    <script type="application/json" id="manufacturing-recipes-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="manufacturing-recipes-index"
        data-payload="manufacturing-recipes-index-payload"
        x-data="manufacturingRecipesIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">{{ __('All recipes') }}</h3>
                @can('inventory-make-orders-manage')
                    <button
                        type="button"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                        x-on:click="openCreate()"
                    >
                        {{ __('Create Recipe') }}
                    </button>
                @endcan
            </div>

            <div x-cloak x-show="recipes.length === 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('No recipes yet') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ __('Recipes will appear here once you add them.') }}
                        </p>
                        @can('inventory-make-orders-manage')
                            <div class="mt-4">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
                                    x-on:click="openCreate()"
                                >
                                    {{ __('Create Recipe') }}
                                </button>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>

            <div x-cloak x-show="recipes.length > 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th class="px-3 py-2 font-medium">{{ __('Output Item') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Active') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Updated') }}</th>
                                        <th class="px-3 py-2 text-right font-medium">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="recipe in recipes" :key="recipe.id">
                                        <tr>
                                            <td class="px-3 py-3 text-gray-900">
                                                <a
                                                    class="text-gray-900 hover:text-blue-600"
                                                    x-bind:href="recipe.show_url"
                                                    x-text="recipe.item_name"
                                                ></a>
                                            </td>
                                            <td class="px-3 py-3 text-gray-600" x-text="recipe.is_active ? 'Yes' : 'No'"></td>
                                            <td class="px-3 py-3 text-gray-600" x-text="recipe.updated_at"></td>
                                            <td class="px-3 py-3 text-right text-sm">
                                                @can('inventory-make-orders-manage')
                                                    <div
                                                        class="relative inline-block text-left"
                                                        x-on:keydown.escape.window="closeActionMenu()"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700"
                                                            aria-label="Recipe actions"
                                                            x-on:click="toggleActionMenu($event, recipe.id)"
                                                        >
                                                            ⋮
                                                        </button>

                                                        <template x-teleport="body">
                                                            <div
                                                                x-show="isActionMenuOpenFor(recipe.id)"
                                                                x-on:click.outside="closeActionMenu()"
                                                                x-transition
                                                                class="fixed z-50 mt-2 w-40 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
                                                                x-bind:style="'top:' + actionMenuTop + 'px; left:' + (actionMenuLeft - 160) + 'px;'"
                                                            >
                                                                <div class="py-1">
                                                                    <button
                                                                        type="button"
                                                                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                                                        x-on:click="closeActionMenu(); openEdit(recipe)"
                                                                    >
                                                                        {{ __('Edit') }}
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                                                        x-on:click="closeActionMenu(); openDelete(recipe)"
                                                                    >
                                                                        {{ __('Delete') }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
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
            @include('manufacturing.recipes.partials.create-recipe-slide-over')
            @include('manufacturing.recipes.partials.edit-recipe-slide-over')
        </div>
    </div>
</x-app-layout>
