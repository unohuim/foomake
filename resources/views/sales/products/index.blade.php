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
            <div class="md:hidden" data-products-mobile>
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="sticky top-0 z-10 border-b border-gray-100 bg-white p-4">
                        <div class="flex items-center gap-3">
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.95 7.95 0 1 0 5.4 5.4a7.95 7.95 0 0 0 11.25 11.25Z" />
                                    </svg>
                                </div>
                                <input
                                    type="search"
                                    class="block w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Search products"
                                    x-model="search"
                                    x-on:input.debounce.200ms="handleSearchInput()"
                                />
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-opacity duration-150"
                                    :class="isLoadingList ? 'opacity-100' : 'opacity-0'"
                                    aria-hidden="true"
                                >
                                    <svg class="h-4 w-4 animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </div>
                            </div>

                            @if ($payload['canManageImports'])
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                                    title="Import Products"
                                    aria-label="Import Products"
                                    x-on:click="openImportPanel()"
                                >
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5M12 16.5l-4.5-4.5M3.75 19.5h16.5" />
                                    </svg>
                                </button>
                            @endif

                            @if ($payload['canManageProducts'])
                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                                    title="Add New Product"
                                    aria-label="Add New Product"
                                    x-on:click="openCreatePanel()"
                                >
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="max-h-[36rem] overflow-y-auto p-4">
                        <div class="space-y-3 transition-opacity duration-150" :class="isLoadingList ? 'opacity-80' : 'opacity-100'">
                            <div
                                x-show="!isLoadingList && products.length === 0"
                                class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500"
                            >
                                No products found.
                            </div>

                            <template x-for="product in products" :key="`mobile-${product.id}`">
                                <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                                    <div class="flex items-start gap-3">
                                        <template x-if="product.image_url">
                                            <img
                                                :src="product.image_url"
                                                alt=""
                                                class="h-14 w-14 rounded-md object-cover"
                                            >
                                        </template>
                                        <template x-if="!product.image_url">
                                            <div class="h-14 w-14 rounded-md bg-gray-100"></div>
                                        </template>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-gray-900" x-text="product.name"></p>
                                                    <p class="mt-1 text-sm text-gray-600" x-text="productBaseUomLabel(product)"></p>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                                                    aria-label="Product actions"
                                                >
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <p class="mt-3 text-sm text-gray-700" x-text="formattedProductPrice(product)"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden md:block" data-products-desktop>
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="max-h-[36rem] overflow-y-auto">
                        <div class="sticky top-0 z-20 border-b border-gray-100 bg-white px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="relative flex-1">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.95 7.95 0 1 0 5.4 5.4a7.95 7.95 0 0 0 11.25 11.25Z" />
                                        </svg>
                                    </div>
                                    <input
                                        type="search"
                                        class="block w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Search products"
                                        x-model="search"
                                        x-on:input.debounce.200ms="handleSearchInput()"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-opacity duration-150"
                                        :class="isLoadingList ? 'opacity-100' : 'opacity-0'"
                                        aria-hidden="true"
                                    >
                                        <svg class="h-4 w-4 animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </div>
                                </div>

                                @if ($payload['canManageImports'])
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                                        title="Import Products"
                                        aria-label="Import Products"
                                        x-on:click="openImportPanel()"
                                    >
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5M12 16.5l-4.5-4.5M3.75 19.5h16.5" />
                                        </svg>
                                    </button>
                                @endif

                                @if ($payload['canManageProducts'])
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                                        title="Add New Product"
                                        aria-label="Add New Product"
                                        x-on:click="openCreatePanel()"
                                    >
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="sticky top-[73px] z-10 bg-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <button type="button" class="inline-flex items-center gap-2 text-left" x-on:click="toggleSort('name')">
                                            <span>Name</span>
                                            <span x-show="sort.column === 'name'">
                                                <svg x-show="sort.direction === 'desc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                                </svg>
                                                <svg x-show="sort.direction === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                                </svg>
                                            </span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <button type="button" class="inline-flex items-center gap-2 text-left" x-on:click="toggleSort('base_uom')">
                                            <span>Base UoM</span>
                                            <span x-show="sort.column === 'base_uom'">
                                                <svg x-show="sort.direction === 'desc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                                </svg>
                                                <svg x-show="sort.direction === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                                </svg>
                                            </span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <button type="button" class="inline-flex items-center gap-2 text-left" x-on:click="toggleSort('price')">
                                            <span>Price</span>
                                            <span x-show="sort.column === 'price'">
                                                <svg x-show="sort.direction === 'desc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                                </svg>
                                                <svg x-show="sort.direction === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                                </svg>
                                            </span>
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 bg-white transition-opacity duration-150"
                                :class="isLoadingList ? 'opacity-80' : 'opacity-100'"
                            >
                                <tr x-show="!isLoadingList && products.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No products found.</td>
                                </tr>

                                <template x-for="product in products" :key="product.id">
                                    <tr class="transition hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <div class="flex items-center gap-3">
                                                <template x-if="product.image_url">
                                                    <img
                                                        :src="product.image_url"
                                                        alt=""
                                                        class="h-10 w-10 rounded-md object-cover"
                                                    >
                                                </template>
                                                <template x-if="!product.image_url">
                                                    <div class="h-10 w-10 rounded-md bg-gray-100"></div>
                                                </template>
                                                <span class="font-medium" x-text="product.name"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            <span x-text="productBaseUomLabel(product)"></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            <span x-text="formattedProductPrice(product)"></span>
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm">
                                            <button
                                                type="button"
                                                class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                                                aria-label="Product actions"
                                            >
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
                                            <h2 class="text-lg font-medium text-gray-900">Add New Product</h2>
                                            <p class="mt-1 text-sm text-gray-600">Create a new sellable product item for this tenant.</p>
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
                                    >
                                        Add Product
                                    </button>
                                </div>
                            </form>
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
