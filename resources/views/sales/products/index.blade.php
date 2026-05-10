<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-products-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-products-index"
        data-payload="sales-products-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="salesProductsIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div data-crud-root></div>

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

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="slideOvers.import.open"
                x-cloak
                role="dialog"
                aria-modal="true"
                data-products-import-panel
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="slideOvers.import.open"
                        x-on:click="closeImportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-3xl">
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="slideOverTitle('import')"></h2>
                                            <p class="mt-1 text-sm text-gray-600">WooCommerce previews import real external rows while preserving normal item imports.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeImportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Ecommerce Store
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="selectedSource"
                                                    x-on:change="handleSourceChange()"
                                                >
                                                    <option value="">Select source</option>
                                                    <template x-for="source in sources" :key="source.value">
                                                        <option :value="source.value" :disabled="!source.enabled" x-text="source.label"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.source[0]"></p>
                                        </div>

                                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4" x-show="isFileUploadMode()">
                                            <div class="flex items-start gap-3">
                                                <svg class="h-5 w-5 shrink-0 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V16.5" />
                                                </svg>
                                                <div class="min-w-0">
                                                    <h3 class="text-sm font-semibold text-gray-900">Upload File</h3>
                                                    <p class="mt-1 text-sm text-gray-600">CSV upload will use the exported products template. Ecommerce preview remains available below.</p>
                                                </div>
                                            </div>
                                            <div class="mt-4">
                                                <label class="block text-sm font-medium text-gray-700" for="products-import-file">
                                                    CSV File
                                                </label>
                                                <input
                                                    id="products-import-file"
                                                    x-ref="importFileInput"
                                                    type="file"
                                                    accept=".csv,text/csv"
                                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500"
                                                    x-on:change="handleLocalFileChange($event)"
                                                />
                                                <p class="mt-2 text-xs text-gray-500" x-show="selectedFileName" x-text="selectedFileName"></p>
                                                <p class="mt-1 text-sm text-red-600" x-text="errors.file[0]"></p>
                                            </div>
                                        </div>

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedSource && !isFileUploadMode() && selectedSourceEnabled() && !sourceConnected()">
                                            <h3 class="text-sm font-semibold text-gray-900">Connection required</h3>
                                            <p class="mt-1 text-sm text-gray-600">
                                                WooCommerce status:
                                                <span class="font-medium" x-text="selectedSourceStatusLabel()"></span>.
                                            </p>
                                            <p class="mt-2 text-sm text-gray-600" x-show="canManageConnections">
                                                Manage store credentials from Profile → Connectors before loading a preview.
                                            </p>
                                            <p class="mt-2 text-sm text-gray-600" x-show="!canManageConnections">
                                                Ask an admin to connect WooCommerce from Profile → Connectors before loading a preview.
                                            </p>
                                            <div class="mt-4" x-show="canManageConnections && connectorsPageUrl">
                                                <a
                                                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                                    :href="connectorsPageUrl"
                                                >
                                                    Open Connectors
                                                </a>
                                            </div>
                                        </div>

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedSource && !isFileUploadMode() && selectedSourceEnabled() && sourceConnected()">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <h3 class="text-sm font-semibold text-gray-900">Preview importable rows</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Preview fetches live WooCommerce products and variations for import.</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                                    x-bind:disabled="isLoadingPreview"
                                                    x-bind:class="isLoadingPreview ? 'cursor-not-allowed opacity-60' : ''"
                                                    x-on:click="loadPreview()"
                                                >
                                                    <span x-show="!isLoadingPreview">Load Preview</span>
                                                    <span x-show="isLoadingPreview">Loading preview...</span>
                                                </button>
                                            </div>
                                            <p class="mt-3 text-sm text-red-600" x-text="previewError"></p>
                                            <p class="mt-2 text-sm text-gray-600" x-show="isLoadingPreview">Loading preview...</p>
                                        </div>

                                        <div x-show="previewRows.length > 0">
                                            <div class="mb-4 grid gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
                                                <label class="flex items-center gap-3 text-sm text-gray-700 sm:col-span-2">
                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="createFulfillmentRecipes">
                                                    Create fulfillment recipes
                                                </label>
                                                <label class="flex items-center gap-3 text-sm text-gray-700">
                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="bulkManufacturable">
                                                    Import all selected as manufacturable
                                                </label>
                                                <label class="flex items-center gap-3 text-sm text-gray-700">
                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="bulkPurchasable">
                                                    Import all selected as buyable/purchasable
                                                </label>
                                            </div>

                                            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                                <label class="block text-sm font-medium text-gray-700">
                                                    Bulk base UoM
                                                    <select
                                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="bulkBaseUomId"
                                                    >
                                                        <option value="">Select bulk UoM</option>
                                                        <template x-for="uom in uoms" :key="uom.id">
                                                            <option :value="String(uom.id)" x-text="`${uom.name} (${uom.symbol})`"></option>
                                                        </template>
                                                    </select>
                                                </label>
                                            </div>

                                            <div class="overflow-x-auto rounded-lg border border-gray-100">
                                                <table class="min-w-full divide-y divide-gray-100">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Select</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Base UoM</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Overrides</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 bg-white">
                                                        <template x-for="(row, index) in previewRows" :key="row.external_id">
                                                            <tr>
                                                                <td class="px-4 py-4 align-top">
                                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.selected" x-bind:disabled="row.is_duplicate">
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-900 align-top">
                                                                    <p class="font-medium" x-text="row.name"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="row.sku"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="row.external_id"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-show="row.external_source" x-text="`Source: ${row.external_source}`"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-show="row.price" x-text="`Price: ${row.price}`"></p>
                                                                    <p class="mt-2 text-xs text-red-600" x-show="row.is_duplicate" x-text="row.duplicate_reason"></p>
                                                                    <template x-if="rowHasProductErrors(index)">
                                                                        <div class="mt-2 space-y-1">
                                                                            <template x-for="message in rowProductErrors(index)" :key="message">
                                                                                <p class="text-xs text-red-600" x-text="message"></p>
                                                                            </template>
                                                                        </div>
                                                                    </template>
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase" :class="row.is_duplicate ? 'bg-red-50 text-red-700' : (row.is_active ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700')" x-text="row.is_duplicate ? 'Duplicate' : (row.is_active ? 'Active' : 'Inactive')"></span>
                                                                    <p class="mt-2 text-xs text-gray-500">Sellable on import</p>
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                                                    <select
                                                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                        x-model="row.base_uom_id"
                                                                    >
                                                                        <option value="">Select UoM</option>
                                                                        <template x-for="uom in uoms" :key="uom.id">
                                                                            <option :value="String(uom.id)" x-text="`${uom.name} (${uom.symbol})`"></option>
                                                                        </template>
                                                                    </select>
                                                                    <p class="mt-1 text-xs text-red-600" x-text="rowError(index, 'base_uom_id')"></p>
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                                                    <label class="flex items-center gap-2 text-sm">
                                                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.is_manufacturable" x-on:change="setManufacturableOverride(row)">
                                                                        Manufacturable
                                                                    </label>
                                                                    <label class="mt-2 flex items-center gap-2 text-sm">
                                                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.is_purchasable" x-on:change="setPurchasableOverride(row)">
                                                                        Purchasable
                                                                    </label>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <p class="mt-3 text-sm text-red-600" x-text="importError"></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeImportPanel()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        x-show="previewRows.length > 0"
                                        x-on:click="submitImport()"
                                    >
                                        Import Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="slideOvers.export.open"
                x-cloak
                role="dialog"
                aria-modal="true"
                data-products-export-panel
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="slideOvers.export.open"
                        x-on:click="closeExportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="slideOverTitle('export')"></h2>
                                            <p class="mt-1 text-sm text-gray-600">Export products as CSV using the same import-relevant field set.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeExportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-4">
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                            <h3 class="text-sm font-semibold text-gray-900">Format</h3>
                                            <p class="mt-1 text-sm text-gray-600">CSV</p>
                                        </div>

                                        <fieldset class="space-y-3">
                                            <legend class="text-sm font-medium text-gray-700">Export Scope</legend>

                                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                                                <input type="radio" class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500" value="current" x-model="exportScope">
                                                <div>
                                                    <p class="font-medium text-gray-900">Current filters and sort</p>
                                                    <p class="mt-1 text-gray-600">Uses the current search text and sort order from the products list.</p>
                                                </div>
                                            </label>

                                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                                                <input type="radio" class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500" value="all" x-model="exportScope">
                                                <div>
                                                    <p class="font-medium text-gray-900">All records</p>
                                                    <p class="mt-1 text-gray-600">Exports every sellable product in the current tenant.</p>
                                                </div>
                                            </label>
                                        </fieldset>

                                        <p class="text-sm text-red-600" x-text="exportError"></p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeExportPanel()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        x-on:click="submitExport()"
                                    >
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
