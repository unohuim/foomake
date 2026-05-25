<x-resource-detail-layout
    class="relative"
    data-page="manufacturing-recipes-show"
    data-payload="manufacturing-recipes-show-payload"
    x-data="manufacturingRecipesShow"
>
    @php
        $breadcrumbItems = [
            [
                'label' => 'Home',
                'url' => url('/'),
            ],
            [
                'label' => 'Recipes',
                'url' => route('manufacturing.recipes.index'),
            ],
            [
                'label' => $recipe->name,
                'url' => null,
            ],
        ];

        $recipePayload = $payload['recipe'] ?? [];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb :items="$breadcrumbItems" :title="$recipePayload['name'] ?? $recipe->name">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $recipePayload['output_item_name'] ?? '—' }}</span>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $recipePayload['display_version_number'] ?? '—' }}</span>
                @if (($recipePayload['display_recipe_type_icon'] ?? null) === 'shopping-cart')
                    <span class="inline-flex items-center gap-2 text-gray-700" aria-label="Fulfillment recipe" title="{{ $recipePayload['display_recipe_type_label'] ?? 'Fulfillment' }}">
                        <span class="sr-only">{{ $recipePayload['display_recipe_type_label'] ?? 'Fulfillment' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                    </span>
                @elseif (($recipePayload['display_recipe_type_icon'] ?? null) === 'cog')
                    <span class="inline-flex items-center gap-2 text-gray-700" aria-label="Manufacturing recipe" title="{{ $recipePayload['display_recipe_type_label'] ?? 'Manufacturing' }}">
                        <span class="sr-only">{{ $recipePayload['display_recipe_type_label'] ?? 'Manufacturing' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 3.752a1.5 1.5 0 0 1 1.16 0l.497.207a1.5 1.5 0 0 0 1.314-.07l.468-.292a1.5 1.5 0 0 1 1.52-.057l.526.304a1.5 1.5 0 0 0 1.318.047l.527-.243a1.5 1.5 0 0 1 2.05 1.356v.608a1.5 1.5 0 0 0 .43 1.05l.43.442a1.5 1.5 0 0 1 .338 1.573l-.214.565a1.5 1.5 0 0 0 .083 1.31l.306.53a1.5 1.5 0 0 1-.056 1.518l-.293.47a1.5 1.5 0 0 0-.07 1.313l.208.498a1.5 1.5 0 0 1-.745 1.928l-.553.267a1.5 1.5 0 0 0-.826 1.02l-.122.539a1.5 1.5 0 0 1-1.46 1.17h-.548a1.5 1.5 0 0 0-1.14.525l-.36.428a1.5 1.5 0 0 1-1.466.49l-.58-.145a1.5 1.5 0 0 0-1.22.196l-.456.325a1.5 1.5 0 0 1-1.564 0l-.456-.325a1.5 1.5 0 0 0-1.22-.196l-.58.144a1.5 1.5 0 0 1-1.466-.49l-.36-.427a1.5 1.5 0 0 0-1.14-.526h-.548a1.5 1.5 0 0 1-1.46-1.17l-.122-.538a1.5 1.5 0 0 0-.826-1.02l-.553-.268a1.5 1.5 0 0 1-.745-1.928l.208-.497a1.5 1.5 0 0 0-.07-1.314l-.293-.47a1.5 1.5 0 0 1-.056-1.518l.306-.53a1.5 1.5 0 0 0 .083-1.31l-.214-.566a1.5 1.5 0 0 1 .338-1.572l.43-.442a1.5 1.5 0 0 0 .43-1.05v-.608a1.5 1.5 0 0 1 2.05-1.356l.527.243a1.5 1.5 0 0 0 1.318-.047l.526-.304a1.5 1.5 0 0 1 1.52.057l.468.292a1.5 1.5 0 0 0 1.314.07l.497-.207Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>
                @endif
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $recipePayload['display_output_quantity_text'] ?? '—' }}</span>
            </div>
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="manufacturing-recipes-show-payload">@json($payload)</script>

    <div class="fixed right-6 top-6 z-50" x-show="toast.visible">
        <div
            class="rounded-md px-4 py-3 text-sm shadow-md"
            :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
            x-text="toast.message"
        ></div>
    </div>

    <div class="mx-auto max-w-5xl space-y-4 px-1 py-8 sm:space-y-6 sm:px-6 sm:py-12 lg:px-8" data-recipe-detail-content>
        @if (($payload['sections']['makeOrders'] ?? null))
            <div data-js-crud-section-root data-section-key="makeOrders"></div>
        @endif

        <x-ingredients-detail-section
            title="Ingredients"
            :description="__('Ingredients are shown for the current display version.')"
            :default-open="false"
            item-header="Item Name"
            :context-text-expression="'ingredients.can_edit ? \'Editing checked out version \' + ingredients.display_version_number : \'Showing version \' + ingredients.display_version_number'"
        />

        @if (($payload['sections']['versions'] ?? null))
            <div data-js-crud-section-root data-section-key="versions"></div>
        @endif

        @can('inventory-make-orders-manage')
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    x-on:click="openEditRecipe()"
                >
                    {{ __('Edit Recipe') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                    x-on:click="openDeleteRecipe()"
                >
                    {{ __('Delete') }}
                </button>
            </div>
        @endcan
    </div>

    <x-slot name="overlays">
        <div data-recipe-detail-overlays>
            @include('manufacturing.recipes.partials.delete-recipe-modal')
            @include('manufacturing.recipes.partials.edit-recipe-slide-over')
            @include('manufacturing.recipes.partials.create-recipe-version-slide-over')
        </div>
    </x-slot>
</x-resource-detail-layout>
