<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-products-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
        data-page="sales-products-index"
        data-payload="sales-products-index-payload"
        data-crud-config='@json($crudConfig)'
        data-import-config='@json($importConfig)'
        x-data="salesProductsIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="isCreatePanelOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isCreatePanelOpen"
                        x-on:click="closeCreatePanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitCreate()">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="panelMode === 'create' ? 'Add New Product' : 'Edit Product'"></h2>
                                            <p class="mt-1 text-sm text-gray-600" x-text="panelMode === 'create' ? 'Create a new sellable product item for this tenant.' : 'Update sellable product details without leaving the page.'"></p>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-gray-500"
                                            x-on:click="closeCreatePanel()"
                                        >
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6" x-show="createGeneralError">
                                        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="createGeneralError"></div>
                                    </div>

                                    <div class="mt-6 space-y-5">
                                        <div>
                                            <label for="product-name" class="block text-sm font-medium text-gray-700">Name</label>
                                            <input
                                                id="product-name"
                                                x-ref="createProductNameInput"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="createForm.name"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-show="createErrors.name" x-text="createErrors.name[0]"></p>
                                        </div>

                                        <div>
                                            <label for="product-base-uom" class="block text-sm font-medium text-gray-700">Base Unit of Measure</label>
                                            <select
                                                id="product-base-uom"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="createForm.base_uom_id"
                                            >
                                                <option value="">Select a unit</option>
                                                <template x-for="uom in uoms" :key="uom.id">
                                                    <option :value="String(uom.id)" x-text="`${uom.name} (${uom.symbol})`"></option>
                                                </template>
                                            </select>
                                            <p class="mt-1 text-sm text-red-600" x-show="createErrors.base_uom_id" x-text="createErrors.base_uom_id[0]"></p>
                                        </div>

                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Planning price</p>
                                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                <div class="sm:col-span-2">
                                                    <label for="product-default-price-amount" class="block text-xs font-medium text-gray-600">Amount</label>
                                                    <input
                                                        id="product-default-price-amount"
                                                        type="text"
                                                        inputmode="decimal"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                        x-model="createForm.default_price_amount"
                                                    />
                                                    <p class="mt-1 text-sm text-red-600" x-show="createErrors.default_price_amount" x-text="createErrors.default_price_amount[0]"></p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600">Currency</label>
                                                    <input
                                                        type="text"
                                                        class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm sm:text-sm"
                                                        x-bind:value="tenantCurrency"
                                                        disabled
                                                    />
                                                    <p class="mt-1 text-sm text-red-600" x-show="createErrors.default_price_currency_code" x-text="createErrors.default_price_currency_code[0]"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <p class="text-sm font-medium text-gray-700">Flags</p>
                                            <div class="mt-3 space-y-2">
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked disabled>
                                                    Sellable
                                                </label>
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="createForm.is_purchasable">
                                                    Purchasable
                                                </label>
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="createForm.is_manufacturable">
                                                    Manufacturable
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        x-on:click="closeCreatePanel()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        :disabled="isCreateSubmitting"
                                        :class="isCreateSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                        x-text="panelMode === 'create' ? 'Add Product' : 'Save Changes'"
                                    >
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
