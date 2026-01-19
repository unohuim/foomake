<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Materials') }}
        </h2>
    </x-slot>

    @php
        $uomsExist = \App\Models\Uom::query()->exists();
        $uoms = \App\Models\Uom::query()->orderBy('name')->get();
        $itemsPayload = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_name' => $item->baseUom->name,
                'base_uom_symbol' => $item->baseUom->symbol,
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
            ];
        });
    @endphp

    <div
        class="py-12"
        x-data="{
            items: [],
            uomsExist: {{ $uomsExist ? 'true' : 'false' }},
            isCreateOpen: false,
            isSubmitting: false,
            errors: {},
            generalError: '',
            form: {
                name: '',
                base_uom_id: '',
                is_purchasable: false,
                is_sellable: false,
                is_manufacturable: false
            },
            init() {
                this.items = JSON.parse(this.$refs.itemsData.textContent);
            },
            openCreate() {
                if (!this.uomsExist) {
                    return;
                }

                this.isCreateOpen = true;
                this.generalError = '';
                this.errors = {};
            },
            closeCreate() {
                this.isCreateOpen = false;
                this.isSubmitting = false;
                this.generalError = '';
                this.errors = {};
                this.resetForm();
            },
            resetForm() {
                this.form = {
                    name: '',
                    base_uom_id: '',
                    is_purchasable: false,
                    is_sellable: false,
                    is_manufacturable: false
                };
            },
            async submitCreate() {
                this.isSubmitting = true;
                this.generalError = '';
                this.errors = {};

                const payload = {
                    name: this.form.name,
                    base_uom_id: this.form.base_uom_id,
                    is_purchasable: this.form.is_purchasable,
                    is_sellable: this.form.is_sellable,
                    is_manufacturable: this.form.is_manufacturable
                };

                const response = await fetch('{{ route('materials.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.errors = data.errors || {};
                    this.isSubmitting = false;
                    return;
                }

                if (!response.ok) {
                    this.generalError = 'Something went wrong. Please try again.';
                    this.isSubmitting = false;
                    return;
                }

                const data = await response.json();

                this.items.unshift({
                    id: data.data.id,
                    name: data.data.name,
                    base_uom_name: data.data.base_uom.name,
                    base_uom_symbol: data.data.base_uom.symbol,
                    is_purchasable: data.data.is_purchasable,
                    is_sellable: data.data.is_sellable,
                    is_manufacturable: data.data.is_manufacturable
                });

                this.closeCreate();
            }
        }"
        x-init="init()"
    >
        <script type="application/json" x-ref="itemsData">
            @json($itemsPayload)
        </script>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6" x-show="items.length > 0">
                <h3 class="text-lg font-medium text-gray-900">All materials</h3>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    x-on:click="openCreate()"
                    :disabled="!uomsExist"
                    :class="!uomsExist ? 'opacity-50 cursor-not-allowed' : ''"
                >
                    Create Material
                </button>
            </div>

            <div x-show="items.length === 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">No materials yet</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Materials will appear here once you add them.
                        </p>
                        @if (! $uomsExist)
                            <p class="mt-3 text-sm text-gray-600">
                                Create a Unit of Measure first to add materials.
                            </p>
                        @endif
                        <div class="mt-4">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                x-on:click="openCreate()"
                                :disabled="!uomsExist"
                                :class="!uomsExist ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Create Material
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="items.length > 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Base UoM
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Flags
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="item in items" :key="item.id">
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900" x-text="item.name"></td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <span x-text="item.base_uom_name"></span>
                                                (<span x-text="item.base_uom_symbol"></span>)
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <div class="flex flex-wrap gap-2 text-xs text-gray-600">
                                                    <span class="px-2 py-1 bg-gray-100 rounded-full" x-show="item.is_purchasable">
                                                        Purchasable
                                                    </span>
                                                    <span class="px-2 py-1 bg-gray-100 rounded-full" x-show="item.is_sellable">
                                                        Sellable
                                                    </span>
                                                    <span class="px-2 py-1 bg-gray-100 rounded-full" x-show="item.is_manufacturable">
                                                        Manufacturable
                                                    </span>
                                                    <span class="text-gray-400" x-show="!item.is_purchasable && !item.is_sellable && !item.is_manufacturable">—</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            @include('materials.partials.create-material-slide-over', ['uoms' => $uoms])
        </div>
    </div>
</x-app-layout>
