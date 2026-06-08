@props([
    'title' => 'Ingredients',
    'description' => '',
    'defaultOpen' => false,
    'itemHeader' => 'Item Name',
    'contextTextExpression' => "''",
    'emptyState' => 'No ingredients yet.',
    'showOnHand' => false,
    'showActions' => false,
    'showRowActionsMenu' => true,
])

<x-detail-section-card
    :title="$title"
    :description="$description"
    :default-open="$defaultOpen"
>
    @if (trim((string) $contextTextExpression) !== '' && trim((string) $contextTextExpression) !== "''")
        <x-slot name="toolbar">
            <div class="min-w-0">
                <p class="text-sm text-gray-500">
                    <span x-text="{{ $contextTextExpression }}"></span>
                </p>
            </div>
        </x-slot>
    @endif

    <x-slot name="addRowLeft">
        <div
            class="min-w-0"
            x-cloak
            x-show="ingredients.can_edit"
            data-ingredients-section-component
            data-ingredients-add-row
            data-ingredients-add-row-left
        >
            <x-combobox
                name="ingredient_item_id"
                label=""
                placeholder="Search ingredients"
                options-expression="ingredients.item_options"
                selected-value=""
                x-model="selectedIngredientItemId"
            />
        </div>
    </x-slot>

    <x-slot name="addRowRight">
        <div
            class="flex justify-end"
            x-cloak
            x-show="ingredients.can_edit"
            data-ingredients-add-row-right
        >
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                x-on:click="addIngredient()"
                x-bind:disabled="!selectedIngredientItemId || ingredientsSaving"
                aria-label="Add ingredient"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </button>
        </div>
    </x-slot>

    <div class="overflow-x-auto overflow-y-visible">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left font-semibold text-gray-600">{{ __($itemHeader) }}</th>
                    <th class="px-3 py-3 text-left font-semibold text-gray-600">{{ __('UOM') }}</th>
                    <th class="px-3 py-3 text-left font-semibold text-gray-600">{{ __('Qty') }}</th>
                    @if ($showOnHand)
                        <th class="px-3 py-3 text-left font-semibold text-gray-600">{{ __('On Hand') }}</th>
                    @endif
                    @if ($showActions)
                        <th class="px-3 py-3 text-right font-semibold text-gray-600">{{ __('Actions') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <template x-if="ingredients.lines.length === 0">
                    <tr>
                        <td
                            class="px-3 py-6 text-center text-sm text-gray-500"
                            colspan="{{ 3 + ($showOnHand ? 1 : 0) + ($showActions ? 1 : 0) }}"
                        >
                            {{ __($emptyState) }}
                        </td>
                    </tr>
                </template>

                <template x-for="line in ingredients.lines" :key="line.id">
                    <tr>
                        <td class="px-3 py-3 text-gray-900" x-text="line.item_name"></td>
                        <td class="px-3 py-3 text-gray-600" x-text="line.uom"></td>
                        <td class="px-3 py-3">
                            <template x-if="ingredients.can_edit">
                                <div class="flex items-center gap-2">
                                    <x-ui.smart-number-input
                                        name="quantity"
                                        type="decimal"
                                        precision="6"
                                        class="w-32"
                                        x-model="line.quantity_input"
                                        after-change="saveIngredientQuantity(line)"
                                        after-blur="saveIngredientQuantity(line)"
                                    />
                                    <span class="text-emerald-600" x-show="ingredientSavedState[line.id] === 'saved'">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </div>
                            </template>
                            <template x-if="!ingredients.can_edit">
                                <span class="text-gray-700" x-text="line.quantity_display"></span>
                            </template>
                        </td>
                        @if ($showOnHand)
                            <td class="px-3 py-3 text-gray-700" x-text="line.on_hand_display"></td>
                        @endif
                        @if ($showActions)
                            <td class="px-3 py-3 text-right">
                                @if (! $showRowActionsMenu)
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-300 text-gray-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                                        x-show="ingredients.can_edit && line.remove_url"
                                        x-on:click="removeIngredient(line)"
                                        aria-label="Remove ingredient"
                                        data-ingredients-remove-button
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @else
                                    <x-dropdown align="right" width="w-40" contentClasses="rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5">
                                        <x-slot name="trigger">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                                                aria-label="Ingredient actions"
                                            >
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M10 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm1.5 6.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z" />
                                                </svg>
                                            </button>
                                        </x-slot>

                                        <x-slot name="content">
                                            <template x-if="line.view_url">
                                                <a
                                                    class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50"
                                                    x-bind:href="line.view_url"
                                                >
                                                    {{ __('View') }}
                                                </a>
                                            </template>

                                            <template x-if="line.make_url">
                                                <button
                                                    type="button"
                                                    class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50"
                                                    x-on:click="goTo(line.make_url)"
                                                >
                                                    {{ __('Make') }}
                                                </button>
                                            </template>

                                            <template x-if="line.purchase_url">
                                                <button
                                                    type="button"
                                                    class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50"
                                                    x-on:click="goTo(line.purchase_url)"
                                                >
                                                    {{ __('Purchase') }}
                                                </button>
                                            </template>

                                            <template x-if="ingredients.can_edit && line.remove_url">
                                                <button
                                                    type="button"
                                                    class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 transition hover:bg-red-50"
                                                    x-on:click="removeIngredient(line)"
                                                >
                                                    {{ __('Remove') }}
                                                </button>
                                            </template>
                                        </x-slot>
                                    </x-dropdown>
                                @endif
                            </td>
                        @endif
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</x-detail-section-card>
