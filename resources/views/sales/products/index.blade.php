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
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Sellable products</h3>
                    <p class="mt-1 text-sm text-gray-600">Products are the sales-facing view of normal sellable items.</p>
                </div>

                @if ($payload['canManageImports'])
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                        x-on:click="openImportPanel()"
                    >
                        Import Products
                    </button>
                @endif
            </div>

            <div class="mt-8" x-cloak x-show="products.length === 0">
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">No products yet</h3>
                        <p class="mt-2 text-sm text-gray-600">Sellable items will appear here once they exist or are imported.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6" x-show="products.length > 0">
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Base UoM</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Flags</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">External</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="product in products" :key="product.id">
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900" x-text="product.name"></td>
                                            <td class="px-4 py-4 text-sm">
                                                <span
                                                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase"
                                                    :class="product.is_active ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'"
                                                    x-text="product.status_label"
                                                ></span>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <span x-text="product.base_uom_name || '—'"></span>
                                                <span x-show="product.base_uom_symbol" x-text="`(${product.base_uom_symbol})`"></span>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <div class="flex flex-wrap gap-2 text-xs text-gray-600">
                                                    <span class="rounded-full bg-gray-100 px-2 py-1">Sellable</span>
                                                    <span class="rounded-full bg-gray-100 px-2 py-1" x-show="product.is_manufacturable">Manufacturable</span>
                                                    <span class="rounded-full bg-gray-100 px-2 py-1" x-show="product.is_purchasable">Purchasable</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <span x-show="product.external_source && product.external_id" x-text="`${product.external_source}:${product.external_id}`"></span>
                                                <span x-show="!product.external_source || !product.external_id">—</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="isImportPanelOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
                data-products-import-panel
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isImportPanelOpen"
                        x-on:click="closeImportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-3xl">
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Import external products</h2>
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
                                                Source
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

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedSource && selectedSourceEnabled() && !sourceConnected()">
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

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedSource && selectedSourceEnabled() && sourceConnected()">
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
                                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.selected">
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-900 align-top">
                                                                    <p class="font-medium" x-text="row.name"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="row.sku"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="row.external_id"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-show="row.price" x-text="`Price: ${row.price}`"></p>
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase" :class="row.is_active ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'" x-text="row.is_active ? 'Active' : 'Inactive'"></span>
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
                                                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.is_manufacturable">
                                                                        Manufacturable
                                                                    </label>
                                                                    <label class="mt-2 flex items-center gap-2 text-sm">
                                                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.is_purchasable">
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
        </div>
    </div>
</x-app-layout>
