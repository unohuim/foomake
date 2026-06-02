<x-slide-over-shell
    open="isCreateOpen"
    close="closeCreate()"
    submit="submitCreate()"
    title="Create Material"
    description="Add a new item to your inventory."
    title-id="create-material-slide-over-title"
>
    <div x-show="generalError">
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="generalError"></div>
    </div>

    <div class="mt-6 space-y-5">
        <div>
            <label for="material-name" class="block text-sm font-medium text-gray-700">Name</label>
            <input
                id="material-name"
                x-ref="createMaterialNameInput"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                x-model="form.name"
            />
            <p class="mt-1 text-sm text-red-600" x-show="errors.name" x-text="errors.name[0]"></p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
            <div class="sm:col-span-3">
                <label for="material-base-uom" class="block text-sm font-medium text-gray-700">UoM</label>
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

            <div class="sm:col-span-2">
                <label for="material-starting-quantity" class="block text-sm font-medium text-gray-700">Starting Qty</label>
                <input
                    id="material-starting-quantity"
                    type="text"
                    inputmode="decimal"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    x-model="form.starting_quantity"
                />
                <p class="mt-1 text-sm text-red-600" x-show="errors.starting_quantity" x-text="errors.starting_quantity[0]"></p>
            </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <div>
            <p class="text-sm font-medium text-gray-700">Type</p>
            <div class="mt-3 space-y-3 pl-3">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_stockable">
                    <span class="block">
                        <span class="block font-semibold">Stockable</span>
                        <span class="block text-gray-500">Keep track of stock levels of this item.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_purchasable">
                    <span class="block">
                        <span class="block font-semibold">Purchasable</span>
                        <span class="block text-gray-500">Allow this item to be bought from suppliers.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_sellable">
                    <span class="block">
                        <span class="block font-semibold">Sellable</span>
                        <span class="block text-gray-500">Allow this item to be sold to customers.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" x-model="form.is_manufacturable">
                    <span class="block">
                        <span class="block font-semibold">Manufacturable</span>
                        <span class="block text-gray-500">Allow this item to be made from a recipe.</span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <x-slot name="footer">
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
            Create
        </button>
    </x-slot>
</x-slide-over-shell>
