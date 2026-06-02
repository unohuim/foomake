<x-slide-over-shell
    open="showLineForm"
    close="closeLineForm()"
    submit="submitLineForm()"
    title="{{ __('Count Line') }}"
    description="{{ __('Add or update an item count line.') }}"
    title-id="inventory-count-line-form-slide-over-title"
>
    <div class="space-y-6">
        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700" for="item_id">
                {{ __('Item') }}
            </label>
            <select
                id="item_id"
                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                x-model="lineForm.item_id"
            >
                <option value="">{{ __('Select an item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">
                        {{ $item->name }} ({{ $item->baseUom->symbol }})
                    </option>
                @endforeach
            </select>
            <p class="text-sm text-red-600" x-show="errors.line.item_id" x-text="errors.line.item_id?.[0]"></p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700" for="counted_quantity">
                {{ __('Counted Quantity') }}
            </label>
            <input
                id="counted_quantity"
                type="text"
                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                x-model="lineForm.counted_quantity"
            />
            <p class="text-sm text-red-600" x-show="errors.line.counted_quantity" x-text="errors.line.counted_quantity?.[0]"></p>
        </div>

        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700" for="line_notes">
                {{ __('Notes') }}
            </label>
            <textarea
                id="line_notes"
                rows="3"
                class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                x-model="lineForm.notes"
            ></textarea>
            <p class="text-sm text-red-600" x-show="errors.line.notes" x-text="errors.line.notes?.[0]"></p>
        </div>

        <p class="text-sm text-red-600" x-show="errors.line.general" x-text="errors.line.general?.[0]"></p>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
            x-on:click="closeLineForm()"
        >
            {{ __('Cancel') }}
        </button>
        <button
            type="submit"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition"
        >
            {{ __('Save Line') }}
        </button>
    </x-slot>
</x-slide-over-shell>
