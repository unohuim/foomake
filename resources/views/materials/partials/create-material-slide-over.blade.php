<div
    class="fixed inset-0 z-50 overflow-hidden"
    x-show="isCreateOpen"
    x-cloak
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity hidden"
            x-show="isCreateOpen"
            x-on:click="closeCreate()"
        ></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-md">
                <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitCreate()">
                    <div class="flex-1 overflow-y-auto p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">Create Material</h2>
                                <p class="mt-1 text-sm text-gray-600">Add a new material for your tenant.</p>
                            </div>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-500"
                                x-on:click="closeCreate()"
                            >
                                <span class="sr-only">Close panel</span>
                                ✕
                            </button>
                        </div>

                        <div class="mt-6" x-show="generalError">
                            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="generalError"></div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="material-name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input
                                    id="material-name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    x-model="form.name"
                                />
                                <p class="mt-1 text-sm text-red-600" x-show="errors.name" x-text="errors.name[0]"></p>
                            </div>

                            <div>
                                <label for="material-base-uom" class="block text-sm font-medium text-gray-700">Base Unit of Measure</label>
                                <select
                                    id="material-base-uom"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    x-model="form.base_uom_id"
                                >
                                    <option value="">Select a unit</option>
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->symbol }})</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-red-600" x-show="errors.base_uom_id" x-text="errors.base_uom_id[0]"></p>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-700">Planning price</p>
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div class="sm:col-span-2">
                                        <label for="material-default-price-amount" class="block text-xs font-medium text-gray-600">Amount</label>
                                        <input
                                            id="material-default-price-amount"
                                            type="text"
                                            inputmode="decimal"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            x-model="form.default_price_amount"
                                        />
                                        <p class="mt-1 text-sm text-red-600" x-show="errors.default_price_amount" x-text="errors.default_price_amount[0]"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Currency</label>
                                        <input
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm sm:text-sm"
                                            x-bind:value="tenantCurrency"
                                            disabled
                                        />
                                        <p class="mt-1 text-sm text-red-600" x-show="errors.default_price_currency_code" x-text="errors.default_price_currency_code[0]"></p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-700">Flags</p>
                                <div class="mt-3 space-y-2">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_purchasable">
                                        Purchasable
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_sellable">
                                        Sellable
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_manufacturable">
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
                            x-on:click="closeCreate()"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            :disabled="isSubmitting"
                            :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Create Material
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
