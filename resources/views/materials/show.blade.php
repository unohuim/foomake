<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Material</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $item->name }}</p>
            </div>
            <a
                href="{{ route('materials.index') }}"
                class="text-sm text-blue-600 hover:text-blue-500"
            >
                Back to Materials
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Core fields</h3>
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $item->name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Flags</h3>
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Purchasable</dt>
                            <dd class="mt-1 text-gray-900">{{ $item->is_purchasable ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Sellable</dt>
                            <dd class="mt-1 text-gray-900">{{ $item->is_sellable ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Manufacturable</dt>
                            <dd class="mt-1 text-gray-900">{{ $item->is_manufacturable ? 'Yes' : 'No' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Base UoM</h3>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ $item->baseUom ? $item->baseUom->name . ' (' . $item->baseUom->symbol . ')' : '—' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if(($payload['canViewPurchasing'] ?? false))
        @php
            $payloadId = 'materials-show-supplier-packages-payload';
        @endphp

        <script type="application/json" id="{{ $payloadId }}">
            @json($payload)
        </script>

        <div
            class="py-12"
            data-page="materials-show-supplier-pricing"
            data-payload="{{ $payloadId }}"
            x-data="materialsShowSupplierPricing"
        >
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div
                    class="bg-white border border-gray-100 shadow-sm sm:rounded-lg"
                    data-section="supplier-packages"
                >
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Supplier packages</h3>
                                <p class="text-sm text-gray-500">Linked purchasing options</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ count($payload['packages'] ?? []) }} packages
                            </p>
                        </div>

                        <div class="mt-2 space-y-4">
                            @forelse ($payload['packages'] as $package)
                                <div class="flex flex-col gap-2 rounded-lg border border-gray-100 bg-gray-50 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                {{ $package['supplier_company_name'] ?? 'Supplier' }}
                                            </p>
                                            <p class="text-xs text-gray-600">
                                                {{ $package['pack_quantity_display'] ?? $package['pack_quantity'] }}
                                                {{ $package['pack_uom_symbol'] ?? '—' }}
                                            </p>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $package['current_price_display'] ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-between text-gray-500 text-sm">
                                        <p>
                                            SKU:
                                            <span class="text-gray-700">
                                                {{ $package['supplier_sku'] ?? '—' }}
                                            </span>
                                        </p>
                                        <p>
                                            ID:
                                            <span class="text-gray-700">
                                                {{ $package['id'] }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                                    No supplier packages have been added for this material.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
