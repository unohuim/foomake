<x-app-layout>
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
        <div>
            <div class="flex flex-col gap-2">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Material</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-500">
                        <p class="font-medium text-gray-800">{{ $item->name }}</p>
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
                </div>
            </div>

            <x-resource-breadcrumbs :items="$breadcrumbItems" />
        </div>
    </x-slot>

    <script type="application/json" id="materials-show-payload">@json($payload)</script>

    <div
        class="py-8 sm:py-12"
        data-page="materials-show"
        data-payload="materials-show-payload"
    >
        <div class="max-w-5xl mx-auto px-1 sm:px-6 lg:px-8 space-y-4 sm:space-y-6">
            @if ($payload['purchaseOrderCreate'] ?? null)
                <div data-purchase-order-create-root></div>
            @endif

            @if (($payload['sections']['purchaseOrders'] ?? null))
                <div data-js-crud-section-root data-section-key="purchaseOrders"></div>
            @endif

            @if (($payload['sections']['supplierPackages'] ?? null))
                <div data-js-crud-section-root data-section-key="supplierPackages"></div>
            @endif
        </div>
    </div>
</x-app-layout>
