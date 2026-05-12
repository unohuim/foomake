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

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-0 pr-0 sm:pl-10">
                        <div class="pointer-events-auto w-screen max-w-4xl">
                            <div class="flex h-full min-h-0 w-full flex-col bg-white shadow-xl">
                                <div class="shrink-0 border-b border-gray-100 pl-4 pr-6 py-6 sm:px-6">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="slideOverTitle('import')"></h2>
                                            <p class="mt-1 text-sm text-gray-600">WooCommerce previews import real external rows while preserving normal item imports.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeImportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>
                                </div>

                                <div class="flex min-h-0 w-full flex-1 flex-col gap-6 overflow-hidden pl-4 pr-6 py-6 sm:px-6">
                                    <div class="shrink-0 w-full min-w-0 space-y-6">
                                        <div class="w-full min-w-0 box-border">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Ecommerce Store
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="selectedSource"
                                                    x-on:change="handleSourceChange()"
                                                >
                                                    <option value="">Select source</option>
                                                    <template x-for="source in sources" :key="source.value">
                                                        <option :value="source.value" :disabled="!source.enabled" x-text="sourceOptionLabel(source)"></option>
                                                    </template>
                                                    <template x-for="fileSource in cachedFileSources" :key="fileSource.value">
                                                        <option :value="fileSource.value" x-text="fileSource.label"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <input
                                                id="products-import-file"
                                                x-ref="importFileInput"
                                                type="file"
                                                accept=".csv,text/csv"
                                                class="sr-only"
                                                x-on:change="handleLocalFileChange($event)"
                                                data-products-import-file-input
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.source[0]"></p>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.file[0]"></p>
                                        </div>

                                        <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-gray-50 px-4 py-4" x-show="selectedSource && !isFileUploadMode() && selectedSourceEnabled() && !sourceConnected()">
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
                                    </div>

                                    <div
                                        class="flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center"
                                        x-show="!hasSelectedImportSource()"
                                        data-products-import-empty-state
                                    >
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                            <svg class="h-7 w-7 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V7.5m0 0L8.25 11.25M12 7.5l3.75 3.75M3.75 16.5v1.125c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V16.5" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-6 text-base font-semibold text-gray-900">Choose an import source</h3>
                                        <p class="mt-2 max-w-md text-sm text-gray-600">Select a WooCommerce connection or switch to file upload to start loading an import preview.</p>
                                    </div>

                                    <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white" x-show="hasSelectedImportSource()">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-4 text-left"
                                            x-on:click="bulkOptionsAccordionOpen = !bulkOptionsAccordionOpen"
                                            data-products-import-bulk-options-accordion
                                        >
                                            <span class="text-sm font-semibold text-gray-900">Bulk Import Options</span>
                                            <svg class="h-5 w-5 text-gray-400 transition" :class="bulkOptionsAccordionOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                        <div class="w-full min-w-0 overflow-hidden border-t border-gray-100 pl-4 pr-5 py-4 sm:px-4" x-show="bulkOptionsAccordionOpen" x-cloak>
                                            <div class="grid w-full min-w-0 gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
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

                                            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
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
                                        </div>
                                    </div>

                                    <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white" x-show="hasSelectedImportSource()">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-4 text-left"
                                            x-on:click="previewRecordsAccordionOpen = !previewRecordsAccordionOpen"
                                            data-products-import-preview-records-accordion
                                        >
                                            <span class="text-sm font-semibold text-gray-900">Import Preview</span>
                                            <svg class="h-5 w-5 text-gray-400 transition" :class="previewRecordsAccordionOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <div class="w-full min-w-0 overflow-hidden border-t border-gray-100" x-show="previewRecordsAccordionOpen" x-cloak>
                                            <div class="shrink-0 w-full min-w-0 space-y-4 pl-4 pr-5 py-4 sm:px-4">
                                                <div class="flex w-full min-w-0 box-border flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                    <div class="flex w-full min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                                                        <label class="block min-w-0 flex-1 text-sm text-gray-700">
                                                            <span class="sr-only">Search preview records</span>
                                                            <input
                                                                type="search"
                                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                placeholder="Search preview records"
                                                                x-model="previewSearch"
                                                                data-products-import-preview-search
                                                            />
                                                        </label>
                                                        <label class="inline-flex max-w-full items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="showDuplicateRows" data-products-import-show-duplicates>
                                                            <span class="truncate" x-text="`Show Duplicates (${duplicateRowCount()} rows)`"></span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <label class="inline-flex items-center gap-3 text-sm text-gray-700">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                            :checked="allVisibleSelectableRowsSelected()"
                                                            x-on:change="toggleVisibleRowSelection($event)"
                                                            data-products-import-select-visible
                                                        >
                                                        Select All
                                                    </label>
                                                    <p class="text-sm text-gray-500" x-show="!showDuplicateRows && duplicateRowCount() > 0">
                                                        Duplicate rows are hidden until enabled.
                                                    </p>
                                                </div>

                                                <p class="text-sm text-red-600" x-text="previewError"></p>
                                                <p class="text-sm text-red-600" x-text="importError"></p>
                                            </div>

                                            <div class="w-full min-w-0 box-border overflow-hidden pl-4 pr-5 sm:px-4">
                                                <div class="max-h-[32rem] w-full min-w-0 overflow-y-auto pb-52 sm:pb-32" data-products-import-preview-scroll>
                                                    <div class="space-y-4" x-show="isLoadingPreview" data-products-import-preview-loading>
                                                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700" x-text="previewLoadingMessage"></div>
                                                        <div class="grid gap-4 lg:grid-cols-2">
                                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                                <div class="h-4 w-1/3 animate-pulse rounded bg-gray-200"></div>
                                                                <div class="mt-3 h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                                            </div>
                                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                                <div class="h-4 w-1/4 animate-pulse rounded bg-gray-200"></div>
                                                                <div class="mt-3 h-4 w-3/4 animate-pulse rounded bg-gray-100"></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="flex min-h-full items-center justify-center px-4 py-12"
                                                        x-show="!isLoadingPreview && !hasVisiblePreviewRows()"
                                                        data-products-import-preview-empty-state
                                                    >
                                                        <div class="w-full max-w-md rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-10 text-center">
                                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                                                </svg>
                                                            </div>
                                                            <h3 class="mt-4 text-sm font-semibold text-gray-900" x-text="previewEmptyStateTitle()"></h3>
                                                            <p class="mt-2 text-sm text-gray-600" x-text="previewEmptyStateMessage()"></p>
                                                        </div>
                                                    </div>

                                                    <div class="w-full min-w-0 space-y-2" x-show="!isLoadingPreview && hasVisiblePreviewRows()">
                                                        <template x-for="(row, index) in previewRows" :key="row.external_id">
                                                            <article
                                                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
                                                                x-show="rowVisibleInPreview(row)"
                                                                x-bind:aria-hidden="rowVisibleInPreview(row) ? 'false' : 'true'"
                                                                data-products-import-preview-card
                                                            >
                                                                <div class="flex min-h-10 items-center gap-3">
                                                                    <input type="checkbox" class="shrink-0 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.selected" x-bind:disabled="row.is_duplicate">
                                                                    <div class="min-w-0 flex-1 overflow-hidden">
                                                                        <p class="block truncate text-sm font-medium text-gray-900" x-bind:title="row.name" x-text="row.name"></p>
                                                                    </div>
                                                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase leading-none" :class="row.is_duplicate ? 'bg-red-50 text-red-700' : (row.is_active ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700')" x-text="previewStatusLabel(row)"></span>
                                                                </div>
                                                                <template x-if="rowHasProductErrors(index)">
                                                                    <div class="mt-2 space-y-1">
                                                                        <template x-for="message in rowProductErrors(index)" :key="message">
                                                                            <p class="text-xs text-red-600" x-text="message"></p>
                                                                        </template>
                                                                    </div>
                                                                </template>
                                                            </article>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 pl-4 pr-6 py-4 sm:px-6">
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
