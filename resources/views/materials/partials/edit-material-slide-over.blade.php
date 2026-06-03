<x-slide-over-shell
    open="isEditOpen"
    close="closeEdit()"
    submit="submitEdit()"
    title="Edit Material"
    description="Update the material details."
    title-id="edit-material-slide-over-title"
>
    <div x-show="editGeneralError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="editGeneralError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label for="edit-material-name" class="block text-sm font-medium text-gray-700">Name</label>
            <input
                id="edit-material-name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="editForm.name"
            />
            <p class="mt-1 text-sm text-red-600" x-show="editErrors.name" x-text="editErrors.name[0]"></p>
        </div>

        <div>
            <label for="edit-material-base-uom" class="block text-sm font-medium text-gray-700">Base Unit of Measure</label>
            <select
                id="edit-material-base-uom"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="editForm.base_uom_id"
                :disabled="editBaseUomLocked"
                :class="editBaseUomLocked ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
            >
                <option value="">Select a unit</option>
                @foreach ($uoms as $uom)
                    <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->symbol }})</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-red-600" x-show="editErrors.base_uom_id" x-text="editErrors.base_uom_id[0]"></p>
            <p class="mt-1 text-xs text-gray-500" x-show="editBaseUomLocked">
                Base unit is locked once stock moves exist.
            </p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-700">Planning price</p>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="edit-material-default-price-amount" class="block text-xs font-medium text-gray-600">Amount</label>
                    <x-ui.smart-number-input
                        id="edit-material-default-price-amount"
                        name="default_price_amount"
                        type="money"
                        x-model="editForm.default_price_amount"
                    />
                    <p class="mt-1 text-sm text-red-600" x-show="editErrors.default_price_amount" x-text="editErrors.default_price_amount[0]"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Currency</label>
                    <input
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm sm:text-sm"
                        x-bind:value="tenantCurrency"
                        disabled
                    />
                    <p class="mt-1 text-sm text-red-600" x-show="editErrors.default_price_currency_code" x-text="editErrors.default_price_currency_code[0]"></p>
                </div>
            </div>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-700">Flags</p>
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="editForm.is_stockable">
                    Stockable
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="editForm.is_purchasable">
                    Purchasable
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="editForm.is_sellable">
                    Sellable
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="editForm.is_manufacturable">
                    Manufacturable
                </label>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            x-on:click="closeEdit()"
        >
            Cancel
        </button>
        <button
            type="submit"
            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            :disabled="isEditSubmitting"
            :class="isEditSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
        >
            Update Material
        </button>
    </x-slot>
</x-slide-over-shell>
