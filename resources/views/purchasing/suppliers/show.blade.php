<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Supplier: :name', ['name' => $supplier->company_name]) }}
        </h2>
    </x-slot>

    @php
        $payloadId = 'purchasing-suppliers-show-payload';
    @endphp

    <script type="application/json" id="{{ $payloadId }}">
        @json($payload)
    </script>

    <div
        class="py-12"
        data-page="purchasing-suppliers-show"
        data-payload="{{ $payloadId }}"
        x-data="purchasingSuppliersShow"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <section data-section="supplier-packages">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm text-gray-500">Currency</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ $supplier->currency_code ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Package count</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    {{ count($payload['packages'] ?? []) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-2 space-y-4">
                            @forelse ($payload['packages'] as $package)
                                <div class="flex flex-col gap-2 rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                {{ $package['item_name'] ?? 'Material' }}
                                            </p>
                                            <p class="text-xs text-gray-600">
                                                {{ $package['pack_quantity'] ?? '0.000000' }}
                                                {{ $package['pack_uom_symbol'] ?? '—' }}
                                            </p>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $package['current_price_display'] ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-3 text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p>
                                                SKU:
                                                <span class="text-gray-700">
                                                    {{ $package['supplier_sku'] ?? '—' }}
                                                </span>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                ID:
                                                <span class="text-gray-700">
                                                    {{ $package['id'] }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                                    No supplier packages yet.
                                </div>
                            @endforelse
                        </div>

                        <div
                            class="mt-6 rounded-lg border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-600"
                            x-show="canManage"
                            x-cloak
                            data-section="supplier-package-actions"
                        >
                            <h3 class="mb-3 text-xs font-semibold uppercase text-gray-500">
                                Add package + set price
                            </h3>
                            <form class="space-y-4" x-on:submit.prevent="submitPackageAndPrice">
                                <div class="grid gap-3 text-sm text-gray-700 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Item
                                            <select
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model.number="form.item_id"
                                                required
                                            >
                                                <option value="">Select item</option>
                                                <template x-for="item in purchasableItems" :key="item.id">
                                                    <option :value="item.id" x-text="item.name"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <p
                                            class="mt-1 text-xs text-red-600"
                                            x-text="packageErrors.item_id?.[0]"
                                            x-show="packageErrors.item_id && packageErrors.item_id.length"
                                        ></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Pack quantity
                                            <input
                                                type="text"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="form.pack_quantity"
                                                required
                                            />
                                        </label>
                                        <p
                                            class="mt-1 text-xs text-red-600"
                                            x-text="packageErrors.pack_quantity?.[0]"
                                            x-show="packageErrors.pack_quantity && packageErrors.pack_quantity.length"
                                        ></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Pack UoM
                                            <select
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model.number="form.pack_uom_id"
                                                required
                                            >
                                                <option value="">Select UOM</option>
                                                <template x-for="uom in uoms" :key="uom.id">
                                                    <option :value="uom.id" x-text="`${uom.symbol} – ${uom.name}`"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <p
                                            class="mt-1 text-xs text-red-600"
                                            x-text="packageErrors.pack_uom_id?.[0]"
                                            x-show="packageErrors.pack_uom_id && packageErrors.pack_uom_id.length"
                                        ></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Supplier SKU
                                            <input
                                                type="text"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="form.supplier_sku"
                                            />
                                        </label>
                                        <p
                                            class="mt-1 text-xs text-red-600"
                                            x-text="packageErrors.supplier_sku?.[0]"
                                            x-show="packageErrors.supplier_sku && packageErrors.supplier_sku.length"
                                        ></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Price (cents)
                                            <input
                                                type="number"
                                                min="0"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="form.price_cents"
                                                required
                                            />
                                        </label>
                                        <p
                                            class="mt-1 text-xs text-red-600"
                                            x-text="priceErrors.price_cents?.[0]"
                                            x-show="priceErrors.price_cents && priceErrors.price_cents.length"
                                        ></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-gray-500">
                                            Currency (3 chars)
                                            <input
                                                type="text"
                                                maxlength="3"
                                                class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="form.price_currency_code"
                                                required
                                            />
                                        </label>
                                        <p class="mt-1 text-xs text-gray-500">
                                            Defaults to
                                            <span x-text="supplierCurrencyCode || tenantCurrencyCode"></span>
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-red-600"
                                            x-text="priceErrors.price_currency_code?.[0]"
                                            x-show="priceErrors.price_currency_code && priceErrors.price_currency_code.length"
                                        ></p>
                                    </div>
                                </div>

                                <div
                                    x-show="hasFxFields()"
                                    x-cloak
                                    class="space-y-3 rounded border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600"
                                >
                                    <p class="text-xs font-semibold uppercase text-gray-500">FX details</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                                FX rate
                                                <input
                                                    type="text"
                                                    class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.fx_rate"
                                                />
                                            </label>
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                                x-text="priceErrors.fx_rate?.[0]"
                                                x-show="priceErrors.fx_rate && priceErrors.fx_rate.length"
                                            ></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase text-gray-500">
                                                FX rate date
                                                <input
                                                    type="date"
                                                    class="mt-1 w-full rounded border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.fx_rate_as_of"
                                                />
                                            </label>
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                                x-text="priceErrors.fx_rate_as_of?.[0]"
                                                x-show="priceErrors.fx_rate_as_of && priceErrors.fx_rate_as_of.length"
                                            ></p>
                                        </div>
                                    </div>
                                </div>

                                <p
                                    class="text-xs text-red-600"
                                    x-text="generalError"
                                    x-show="generalError"
                                ></p>

                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 disabled:opacity-50"
                                        :disabled="isSubmitting"
                                    >
                                        <span x-show="!isSubmitting">Save package & price</span>
                                        <span x-show="isSubmitting">Saving…</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
