<x-resource-detail-layout
    class="relative"
    data-page="manufacturing-recipes-show"
    data-payload="manufacturing-recipes-show-payload"
    x-data="manufacturingRecipesShow"
    x-on:recipe-active-version-action.window="performHeaderVersionAction($event.detail.action)"
>
    @php
        $breadcrumbItems = [
            [
                'label' => 'Recipes',
                'url' => route('manufacturing.recipes.index'),
                'current' => false,
            ],
            [
                'label' => $recipe->name,
                'url' => null,
                'current' => true,
            ],
        ];

        $recipePayload = $payload['recipe'] ?? [];
        $activeVersion = $recipePayload['active_version'] ?? [];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb :items="$breadcrumbItems" :title="$recipePayload['name'] ?? $recipe->name">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ $recipePayload['output_item_name'] ?? '—' }}</span>
                @if ((data_get($activeVersion, 'display.typeIcon') ?? null) === 'shopping-cart')
                    <span class="inline-flex items-center gap-2 text-gray-700" aria-label="Fulfillment recipe" title="{{ data_get($activeVersion, 'display.typeLabel') ?? 'Fulfillment' }}">
                        <span class="sr-only">{{ data_get($activeVersion, 'display.typeLabel') ?? 'Fulfillment' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                    </span>
                @elseif ((data_get($activeVersion, 'display.typeIcon') ?? null) === 'cog')
                    <span class="inline-flex items-center gap-2 text-gray-700" aria-label="Manufacturing recipe" title="{{ data_get($activeVersion, 'display.typeLabel') ?? 'Manufacturing' }}">
                        <span class="sr-only">{{ data_get($activeVersion, 'display.typeLabel') ?? 'Manufacturing' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 3.752a1.5 1.5 0 0 1 1.16 0l.497.207a1.5 1.5 0 0 0 1.314-.07l.468-.292a1.5 1.5 0 0 1 1.52-.057l.526.304a1.5 1.5 0 0 0 1.318.047l.527-.243a1.5 1.5 0 0 1 2.05 1.356v.608a1.5 1.5 0 0 0 .43 1.05l.43.442a1.5 1.5 0 0 1 .338 1.573l-.214.565a1.5 1.5 0 0 0 .083 1.31l.306.53a1.5 1.5 0 0 1-.056 1.518l-.293.47a1.5 1.5 0 0 0-.07 1.313l.208.498a1.5 1.5 0 0 1-.745 1.928l-.553.267a1.5 1.5 0 0 0-.826 1.02l-.122.539a1.5 1.5 0 0 1-1.46 1.17h-.548a1.5 1.5 0 0 0-1.14.525l-.36.428a1.5 1.5 0 0 1-1.466.49l-.58-.145a1.5 1.5 0 0 0-1.22.196l-.456.325a1.5 1.5 0 0 1-1.564 0l-.456-.325a1.5 1.5 0 0 0-1.22-.196l-.58.144a1.5 1.5 0 0 1-1.466-.49l-.36-.427a1.5 1.5 0 0 0-1.14-.526h-.548a1.5 1.5 0 0 1-1.46-1.17l-.122-.538a1.5 1.5 0 0 0-.826-1.02l-.553-.268a1.5 1.5 0 0 1-.745-1.928l.208-.497a1.5 1.5 0 0 0-.07-1.314l-.293-.47a1.5 1.5 0 0 1-.056-1.518l.306-.53a1.5 1.5 0 0 0 .083-1.31l-.214-.566a1.5 1.5 0 0 1 .338-1.572l.43-.442a1.5 1.5 0 0 0 .43-1.05v-.608a1.5 1.5 0 0 1 2.05-1.356l.527.243a1.5 1.5 0 0 0 1.318-.047l.526-.304a1.5 1.5 0 0 1 1.52.057l.468.292a1.5 1.5 0 0 0 1.314.07l.497-.207Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>
                @endif
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ data_get($activeVersion, 'display.outputQuantityText') ?? '—' }}</span>
            </div>
            @if (! empty(data_get($activeVersion, 'header_menu.options', [])))
                <x-slot name="actions">
                    <div class="flex items-center gap-3" x-data="recipeActiveVersionHeaderMenu(@js($activeVersion))" data-recipe-active-version-menu>
                        <span
                            class="text-sm font-medium text-slate-600"
                            data-recipe-active-version-number-label
                            x-text="versionLabel()"
                        >
                            Version {{ data_get($activeVersion, 'version_number_display', '—') }}
                        </span>
                        <x-dropdown align="right" width="w-64" contentClasses="rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5">
                            <x-slot name="trigger">
                                <button
                                    type="button"
                                    class="inline-flex items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                                >
                                    <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700">
                                        <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-sm font-semibold text-slate-700" data-recipe-active-version-label x-text="statusLabel()">{{ data_get($activeVersion, 'status_label', '') }}</span>
                                    </span>
                                    <span class="inline-flex items-center border-l border-slate-300 px-2.5 text-slate-500">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                            </x-slot>

                            <div class="space-y-1" role="menu">
                                <template x-for="option in menuOptions()" :key="option.label">
                                    <button
                                        type="button"
                                        class="flex w-full items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50"
                                        x-on:click.prevent="dispatchAction(option.action)"
                                    >
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium text-slate-900" x-text="option.label"></span>
                                            <span class="mt-1 block text-xs leading-5 text-slate-500" x-show="option.description" x-text="option.description"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </x-dropdown>
                    </div>
                </x-slot>
            @endif
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="manufacturing-recipes-show-payload">@json($payload)</script>

    <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

    @php
        $ingredientsLines = data_get($payload, 'ingredients.lines', []);
        $ingredientsCanEdit = (bool) data_get($payload, 'ingredients.can_edit', false);
        $recipeVersionStatus = strtoupper((string) data_get($payload, 'recipe.version_status', ''));
        $checkoutAction = data_get($activeVersion, 'checkout_url');
    @endphp

    <div class="mx-auto max-w-5xl space-y-0 px-1 py-8 sm:space-y-6 sm:px-6 sm:py-12 lg:px-8" data-recipe-detail-content>
        <section
            class="overflow-hidden border border-slate-200 bg-white shadow-sm sm:rounded-2xl"
            x-cloak
            x-show="!ingredients.can_edit && ingredients.lines.length === 0"
        >
                <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-inset ring-blue-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 8.25A2.25 2.25 0 0 1 6.75 6h10.5A2.25 2.25 0 0 1 19.5 8.25v7.5A2.25 2.25 0 0 1 17.25 18H6.75a2.25 2.25 0 0 1-2.25-2.25v-7.5Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 9.75h9M7.5 12h9M7.5 14.25h5.25" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ __('Ingredients') }}</p>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Version protected') }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-6 sm:px-6 sm:py-7">
                    <div class="max-w-2xl">
                        <p class="text-sm leading-6 text-slate-600">
                            {{ __('Recipes are version protected. Check out this draft version to add or change ingredients.') }}
                        </p>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            @if ($checkoutAction)
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    x-on:click="performHeaderVersionAction({ handlerKey: 'checkoutVersion' })"
                                >
                                    {{ __('Check out version') }}
                                </button>
                            @endif
                            <span class="text-xs font-medium text-slate-500">
                                {{ __('Then add ingredients, check in, and publish when ready.') }}
                            </span>
                        </div>

                        <ol class="mt-7 grid gap-3 sm:grid-cols-3">
                            <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">1</span>
                                <p class="mt-3 text-sm font-semibold text-slate-900">{{ __('Check out version') }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('Open the draft for editing.') }}</p>
                            </li>
                            <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">2</span>
                                <p class="mt-3 text-sm font-semibold text-slate-900">{{ __('Add ingredients') }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('Add items and quantities while the version is checked out.') }}</p>
                            </li>
                            <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">3</span>
                                <p class="mt-3 text-sm font-semibold text-slate-900">{{ __('Check in or publish') }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('Finish the draft and make it available for use.') }}</p>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

        <div x-cloak x-show="ingredients.can_edit || ingredients.lines.length > 0">
            <x-ingredients-detail-section
                title="Ingredients"
                :description="__('Ingredients are shown for the current display version.')"
                :default-open="true"
                item-header="Item Name"
                :context-text-expression="'ingredients.can_edit ? \'Editing checked out version \' + ingredients.display_version_number : \'Showing version \' + ingredients.display_version_number'"
            />
        </div>

        @if (($payload['sections']['makeOrders'] ?? null))
            <div data-js-crud-section-root data-section-key="makeOrders"></div>
        @endif

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
