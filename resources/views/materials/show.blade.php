<x-resource-detail-layout
    class="relative"
    data-page="materials-show"
    data-payload="materials-show-payload"
    x-data="materialsShowPage"
    x-on:materials-show:open-recipe-create="openRecipeCreate($event.detail)"
    x-on:materials-show:open-make-order-create="openMakeOrderCreate($event.detail)"
>
    @php
        $breadcrumbItems = [
            [
                'label' => 'Materials',
                'url' => route('materials.index'),
                'current' => false,
            ],
            [
                'label' => $item->name,
                'url' => null,
                'current' => true,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb
            :items="$breadcrumbItems"
            :title="$item->name"
            title-class="font-semibold text-base leading-tight text-gray-900 sm:text-2xl"
            class="pb-4 sm:pb-0"
        >
            @if (data_get($payload, 'item.can_manage'))
                <x-slot name="titleSuffix">
                    <div class="relative" x-data="materialNameEditor" data-material-name-editor>
                        <button
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-500 transition hover:text-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600 sm:h-7 sm:w-7"
                            aria-label="{{ __('Edit material name') }}"
                            title="{{ __('Edit material name') }}"
                            x-on:click="openEditor()"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </button>

                        <div
                            class="fixed inset-x-3 top-24 z-40 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white p-4 shadow-xl sm:absolute sm:inset-x-auto sm:left-0 sm:top-full sm:mt-2 sm:w-80 sm:max-w-[calc(100vw-2rem)] sm:overflow-visible"
                            x-show="isOpen"
                            x-transition.opacity.scale.origin.top.left
                            x-on:click.outside="closeEditor()"
                            x-cloak
                        >
                            <label for="material-name-editor-input" class="block text-xs font-medium text-gray-700">
                                {{ __('Material name') }}
                            </label>
                            <input
                                id="material-name-editor-input"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-model="draftName"
                                x-ref="nameInput"
                                x-on:keydown.escape.prevent="closeEditor()"
                                x-on:keydown.enter.prevent="saveName()"
                            />
                            <template x-if="errorMessage !== ''">
                                <p class="mt-2 text-xs text-red-600" x-text="errorMessage"></p>
                            </template>
                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                    x-on:click="closeEditor()"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    x-bind:disabled="isSaving"
                                    x-on:click="saveName()"
                                >
                                    {{ __('Save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </x-slot>
            @endif

            <div class="flex w-full items-center justify-between gap-x-6 gap-y-2 text-sm text-gray-500 sm:w-auto sm:justify-start">
                <div class="relative" x-data="materialTypeToggles" data-material-type-toggles>
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <template x-for="typeToggle in materialTypeToggles" :key="typeToggle.field">
                            <button
                                type="button"
                                class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border bg-white transition disabled:cursor-not-allowed disabled:opacity-70 sm:h-8 sm:w-8 sm:border-2"
                                :class="materialTypeActive(typeToggle.field) ? 'border-blue-600 text-blue-600 hover:border-blue-500 hover:text-blue-500' : 'border-gray-300 text-gray-300 hover:border-blue-600 hover:text-blue-600'"
                                :aria-pressed="materialTypeActive(typeToggle.field) ? 'true' : 'false'"
                                :aria-label="typeToggle.label"
                                :aria-disabled="!canToggleMaterialTypes ? 'true' : 'false'"
                                :title="materialTypeTitle(typeToggle)"
                                :disabled="!canToggleMaterialTypes || materialTypeSaving[typeToggle.field]"
                                @click="toggleMaterialType(typeToggle.field)"
                            >
                                <span class="sr-only" x-text="typeToggle.label"></span>

                                <template x-if="typeToggle.icon === 'shopping-cart'">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                    </svg>
                                </template>

                                <template x-if="typeToggle.icon === 'credit-card'">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                    </svg>
                                </template>

                                <template x-if="typeToggle.icon === 'cog'">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
                                    </svg>
                                </template>

                                <template x-if="typeToggle.icon === 'rectangle-group'">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                    </svg>
                                </template>
                            </button>
                        </template>
                    </div>
                </div>

                @if (data_get($payload, 'item.can_manage'))
                    <div class="ml-6 sm:ml-8" x-data="materialBaseUomDropdown" data-material-base-uom-dropdown>
                        <x-ui.dropdown
                            id="material-base-uom-dropdown"
                            align="right"
                            width="w-56"
                            button-class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                            menu-class="py-1"
                            disabled-expression="!canChangeBaseUom || isChangingBaseUom"
                            title-expression="canChangeBaseUom ? 'Change base unit of measure' : 'Requires material management permission'"
                        >
                            <x-slot name="trigger">
                                <span x-text="currentUomName">{{ $item->baseUom ? $item->baseUom->name : '—' }}</span>
                                <span class="sr-only">{{ __('Change base unit of measure') }}</span>
                            </x-slot>

                            <template x-if="availableUomOptions().length === 0">
                                <div class="px-4 py-2 text-sm text-gray-500" role="none">
                                    {{ __('No other units available') }}
                                </div>
                            </template>

                            <template x-for="option in availableUomOptions()" :key="option.id">
                                <button
                                    type="button"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-60"
                                    role="menuitem"
                                    :disabled="isChangingBaseUom"
                                    x-on:click="selectBaseUom(option); $dispatch('close')"
                                    x-text="optionLabel(option)"
                                ></button>
                            </template>
                        </x-ui.dropdown>
                    </div>
                @else
                    <span class="ml-6 inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200 ring-inset sm:ml-8">
                        {{ $item->baseUom ? $item->baseUom->name : '—' }}
                    </span>
                @endif
            </div>
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="materials-show-payload">@json($payload)</script>

    <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

    <div class="max-w-5xl mx-auto space-y-0 px-1 pb-8 sm:space-y-6 sm:px-6 sm:py-12 lg:px-8" data-material-detail-content>
        @if ($payload['inventoryStats'] ?? null)
            <section
                class="-mx-1 overflow-hidden border-y border-slate-200 bg-white shadow-sm sm:mx-0 sm:rounded-2xl sm:border"
                x-data="materialInventoryStats"
                data-material-inventory-stats
            >
                <div
                    class="flex sm:hidden"
                    role="tablist"
                    aria-label="Inventory stats"
                >
                    <template x-for="card in cards" :key="card.key">
                        <button
                            type="button"
                            role="tab"
                            :aria-label="card.label || card.compact_label || 'Inventory stat'"
                            class="min-h-20 overflow-hidden border-r border-slate-200 transition last:border-r-0"
                            :class="activeStat === card.key ? 'flex-1 bg-white px-3 py-2' : 'w-8 bg-slate-50'"
                            :aria-selected="activeStat === card.key ? 'true' : 'false'"
                            @click="activeStat = card.key"
                        >
                            <span
                                class="flex h-full items-center justify-center"
                                x-show="activeStat !== card.key"
                                aria-hidden="true"
                            >
                                <span
                                    class="-rotate-90 whitespace-nowrap text-[0.62rem] font-semibold uppercase leading-none tracking-wide text-slate-500"
                                    x-text="compactLabel(card)"
                                ></span>
                            </span>

                            <span
                                class="flex h-full items-center justify-between gap-3"
                                x-show="activeStat === card.key"
                            >
                                <span class="min-w-0 text-left">
                                    <span
                                        class="block truncate whitespace-nowrap text-xs font-medium text-slate-500"
                                        x-text="card.label || '—'"
                                    ></span>
                                    <span class="mt-1 flex items-baseline gap-1">
                                        <span
                                            class="text-xl font-semibold tracking-tight text-slate-900"
                                            x-text="quantityDisplay(card)"
                                        ></span>
                                        <span
                                            class="text-xs font-medium text-slate-500"
                                            x-show="uomSymbol(card) !== ''"
                                            x-text="uomSymbol(card)"
                                        ></span>
                                    </span>
                                </span>
                            </span>
                        </button>
                    </template>
                </div>

                <dl
                    class="hidden divide-slate-200 sm:grid sm:divide-x"
                    :class="desktopGridClass()"
                >
                    <template x-for="card in cards" :key="card.key">
                        <div class="px-2 py-2 sm:px-6 sm:py-5">
                            <dt class="min-w-0 text-xs font-medium text-slate-500 sm:text-sm">
                                <span class="sm:hidden" x-text="card.mobile_label || card.label || '—'"></span>
                                <span class="hidden truncate whitespace-nowrap sm:block" x-text="card.label || '—'"></span>
                            </dt>
                            <dd class="mt-1 flex items-baseline gap-2 sm:mt-2">
                                <span
                                    class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl"
                                    x-text="quantityDisplay(card)"
                                ></span>
                                <span
                                    class="text-xs font-medium text-slate-500 sm:text-sm"
                                    x-show="uomSymbol(card) !== ''"
                                    x-text="uomSymbol(card)"
                                ></span>
                            </dd>
                        </div>
                    </template>
                </dl>
            </section>
        @endif

        <div data-js-crud-section-root data-section-key="supplierPackages"></div>
        <div data-js-crud-section-root data-section-key="recipes"></div>
        <div data-js-crud-section-root data-section-key="inventoryCounts"></div>
        <div data-js-crud-section-root data-section-key="stockMoves"></div>
        <div data-js-crud-section-root data-section-key="purchaseOrders"></div>
        <div data-js-crud-section-root data-section-key="makeOrders"></div>

        <div
            x-data="taskCreateSection"
            x-on:task-created.window="appendCreatedTask($event)"
        >
            <x-detail-section-card
                title="Tasks"
                :description="__('Create assigned tasks without linking them to this material.')"
                :default-open="false"
            >
                <x-slot name="actions">
                    <button
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 sm:h-10 sm:w-10"
                        x-on:click="showTaskCreate = true"
                        aria-label="{{ __('Create task') }}"
                    >
                        <svg class="h-3.5 w-3.5 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </x-slot>

                <div
                    class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500"
                    x-show="createdTasks.length === 0"
                >
                    {{ __('Manual tasks created here are assigned to the selected user only.') }}
                </div>

                <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white" x-show="createdTasks.length > 0">
                    <template x-for="task in createdTasks" :key="task.id">
                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900" x-text="task.title"></p>
                                <p class="mt-1 text-sm text-gray-500" x-show="task.description" x-text="task.description"></p>
                                <p class="mt-1 text-xs text-gray-500" x-show="task.assigned_to_user_name">
                                    <span>{{ __('Assigned To') }}:</span>
                                    <span x-text="task.assigned_to_user_name"></span>
                                </p>
                                <p class="mt-1 text-xs text-gray-500" x-show="task.due_date">
                                    <span>{{ __('Due') }}:</span>
                                    <span x-text="task.due_date"></span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                    x-bind:class="taskStatusClasses(task)"
                                    x-text="task.status || '{{ __('open') }}'"
                                ></span>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    x-show="task.can_complete && !task.is_completed"
                                    x-bind:disabled="isTaskCompleting(task)"
                                    x-on:click="completeCreatedTask(task)"
                                >
                                    {{ __('Complete') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-detail-section-card>

            @include('tasks.partials.create-task-slide-over', [
                'users' => collect(data_get($payload, 'taskCreate.users', [])),
            ])
        </div>

    </div>

    <x-slot name="overlays">
        <div data-material-detail-overlays>
            @include('manufacturing.recipes.partials.create-recipe-slide-over')

            @include('manufacturing.make-orders.partials.create-make-order-slide-over')

            <div
                data-material-inventory-count-create-root
                x-data="materialInventoryCountCreate"
            >
                @include('inventory.counts.partials.count-form', [
                    'submitLabel' => __('Create Count'),
                    'users' => collect(data_get($payload, 'inventoryCountCreate.users', [])),
                    'usersExpression' => 'inventoryCountUsers',
                    'scopedItem' => $item,
                ])
            </div>
        </div>
    </x-slot>
</x-resource-detail-layout>
