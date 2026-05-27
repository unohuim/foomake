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
                'label' => 'Home',
                'url' => url('/'),
            ],
            [
                'label' => 'Materials',
                'url' => route('materials.index'),
            ],
            [
                'label' => $item->name,
                'url' => null,
            ],
        ];
    @endphp

    <x-slot name="header">
        <x-resource-detail-header-breadcrumb :items="$breadcrumbItems" :title="$item->name">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                    {{ $item->baseUom ? $item->baseUom->name : '—' }}
                </span>

                @if ($item->is_purchasable || $item->is_sellable || $item->is_manufacturable)
                    <div class="flex items-center gap-3 text-gray-700">
                        @if ($item->is_purchasable)
                            <span aria-label="Purchasable" title="Purchasable" class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                <span class="sr-only">Purchasable</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                </svg>
                            </span>
                        @endif

                        @if ($item->is_sellable)
                            <span aria-label="Sellable" title="Sellable" class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                <span class="sr-only">Sellable</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                            </span>
                        @endif

                        @if ($item->is_manufacturable)
                            <span aria-label="Manufacturable" title="Manufacturable" class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                <span class="sr-only">Manufacturable</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
                                </svg>
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </x-resource-detail-header-breadcrumb>
    </x-slot>

    <script type="application/json" id="materials-show-payload">@json($payload)</script>

    <div class="max-w-5xl mx-auto px-1 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-4 sm:space-y-6" data-material-detail-content>
        @if ($payload['inventoryStats'] ?? null)
            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                data-material-inventory-stats
            >
                <dl class="grid grid-cols-1 divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-5">
                    @foreach (($payload['inventoryStats']['cards'] ?? []) as $card)
                        <div class="px-4 py-5 sm:px-6">
                            <dt class="text-sm font-medium text-slate-500">{{ $card['label'] ?? '—' }}</dt>
                            <dd class="mt-2 flex items-baseline gap-2">
                                <span class="text-2xl font-semibold tracking-tight text-slate-900">
                                    {{ $card['quantity_display'] ?? '0' }}
                                </span>
                                @if (($card['uom_symbol'] ?? '') !== '')
                                    <span class="text-sm font-medium text-slate-500">{{ $card['uom_symbol'] }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($payload['purchaseOrderCreate'] ?? null)
            <div data-purchase-order-create-root></div>
        @endif

        @if (($payload['sections']['supplierPackages'] ?? null))
            <div data-js-crud-section-root data-section-key="supplierPackages"></div>
        @endif

        @if (($payload['sections']['recipes'] ?? null))
            <div data-js-crud-section-root data-section-key="recipes"></div>
        @endif

        @if (($payload['sections']['inventoryCounts'] ?? null))
            <div data-js-crud-section-root data-section-key="inventoryCounts"></div>
        @endif

        @if (($payload['sections']['purchaseOrders'] ?? null))
            <div data-js-crud-section-root data-section-key="purchaseOrders"></div>
        @endif

        @if (($payload['sections']['makeOrders'] ?? null))
            <div data-js-crud-section-root data-section-key="makeOrders"></div>
        @endif

    </div>

    <x-slot name="overlays">
        <div data-material-detail-overlays>
            @if ($payload['recipeCreate'] ?? null)
                @include('manufacturing.recipes.partials.create-recipe-slide-over')
            @endif

            @if ($payload['makeOrderCreate'] ?? null)
                @include('manufacturing.make-orders.partials.create-make-order-slide-over')
            @endif

            @if ($payload['inventoryCountCreate'] ?? null)
                <div
                    data-material-inventory-count-create-root
                    x-data="materialInventoryCountCreate"
                >
                    @include('inventory.counts.partials.count-form', [
                        'submitLabel' => __('Create Count'),
                        'users' => collect(data_get($payload, 'inventoryCountCreate.users', [])),
                        'scopedItem' => $item,
                    ])
                </div>
            @endif
        </div>
    </x-slot>
</x-resource-detail-layout>
