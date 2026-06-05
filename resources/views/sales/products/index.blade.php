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
        <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>

            <x-slide-over-shell
                open="isCreatePanelOpen"
                close="closeCreatePanel()"
                submit="submitCreate()"
                title-expression="panelMode === 'create' ? 'Add New Product' : 'Edit Product'"
                description-expression="panelMode === 'create' ? 'Create a new sellable product item for this tenant.' : 'Update sellable product details without leaving the page.'"
                title-id="sales-product-form-slide-over-title"
            >
                                    <div x-show="createGeneralError">
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
                                                    <x-ui.smart-number-input
                                                        id="product-default-price-amount"
                                                        name="default_price_amount"
                                                        type="money"
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
                <x-slot name="footer">
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
                </x-slot>
            </x-slide-over-shell>

        </div>
    </div>
</x-app-layout>
