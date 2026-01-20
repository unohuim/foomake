<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Materials') }}
        </h2>
    </x-slot>

    @php
        $uomsExist = \App\Models\Uom::query()->exists();
        $uoms = \App\Models\Uom::query()->orderBy('name')->get();
        $uomsPayload = $uoms->map(function ($uom) {
            return [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
            ];
        });
        $itemsPayload = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'base_uom_id' => $item->base_uom_id,
                'base_uom_name' => $item->baseUom->name,
                'base_uom_symbol' => $item->baseUom->symbol,
                'is_purchasable' => $item->is_purchasable,
                'is_sellable' => $item->is_sellable,
                'is_manufacturable' => $item->is_manufacturable,
                'has_stock_moves' => $item->stockMoves()->exists(),
            ];
        });
    @endphp

    <div
        class="py-12"
        x-data="{
            items: [],
            uomsById: {},
            uomsExist: {{ $uomsExist ? 'true' : 'false' }},
            updateUrlBase: '{{ url('/materials') }}',
            showUrlBase: '{{ url('/materials') }}',
            isCreateOpen: false,
            isSubmitting: false,
            errors: {},
            generalError: '',
            isEditOpen: false,
            isEditSubmitting: false,
            editErrors: {},
            editGeneralError: '',
            editItemId: null,
            editBaseUomLocked: false,
            isDeleteOpen: false,
            isDeleteSubmitting: false,
            deleteError: '',
            deleteItemId: null,
            deleteItemName: '',
            toast: {
                visible: false,
                message: '',
                type: 'success',
                timeoutId: null
            },
            form: {
                name: '',
                base_uom_id: '',
                is_purchasable: false,
                is_sellable: false,
                is_manufacturable: false
            },
            editForm: {
                name: '',
                base_uom_id: '',
                is_purchasable: false,
                is_sellable: false,
                is_manufacturable: false
            },
            init() {
                this.items = JSON.parse(this.$refs.itemsData.textContent);
                this.uomsById = JSON.parse(this.$refs.uomsData.textContent)
                    .reduce((map, uom) => {
                        map[uom.id] = uom;
                        return map;
                    }, {});
            },
            showToast(type, message) {
                this.toast.type = type;
                this.toast.message = message;
                this.toast.visible = true;

                if (this.toast.timeoutId) {
                    clearTimeout(this.toast.timeoutId);
                }

                this.toast.timeoutId = setTimeout(() => {
                    this.toast.visible = false;
                }, 2500);
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
            openEdit(item) {
                this.editItemId = item.id;
                this.editForm = {
                    name: item.name,
                    base_uom_id: item.base_uom_id,
                    is_purchasable: item.is_purchasable,
                    is_sellable: item.is_sellable,
                    is_manufacturable: item.is_manufacturable
                };
                this.editBaseUomLocked = item.has_stock_moves;
                this.isEditOpen = true;
                this.editErrors = {};
                this.editGeneralError = '';
            },
            closeEdit() {
                this.isEditOpen = false;
                this.isEditSubmitting = false;
                this.editErrors = {};
                this.editGeneralError = '';
                this.editItemId = null;
                this.editBaseUomLocked = false;
                this.resetEditForm();
            },
            resetEditForm() {
                this.editForm = {
                    name: '',
                    base_uom_id: '',
                    is_purchasable: false,
                    is_sellable: false,
                    is_manufacturable: false
                };
            },
            openDelete(item) {
                this.deleteItemId = item.id;
                this.deleteItemName = item.name;
                this.deleteError = '';
                this.isDeleteOpen = true;
            },
            closeDelete() {
                this.isDeleteOpen = false;
                this.isDeleteSubmitting = false;
                this.deleteError = '';
                this.deleteItemId = null;
                this.deleteItemName = '';
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
                const uom = this.uomsById[data.data.base_uom_id] || { name: '', symbol: '' };

                this.items.unshift({
                    id: data.data.id,
                    name: data.data.name,
                    base_uom_id: data.data.base_uom_id,
                    base_uom_name: uom.name,
                    base_uom_symbol: uom.symbol,
                    is_purchasable: data.data.is_purchasable,
                    is_sellable: data.data.is_sellable,
                    is_manufacturable: data.data.is_manufacturable,
                    has_stock_moves: false
                });

                this.closeCreate();
            },
            async submitEdit() {
                this.isEditSubmitting = true;
                this.editGeneralError = '';
                this.editErrors = {};

                const payload = {
                    name: this.editForm.name,
                    base_uom_id: this.editForm.base_uom_id,
                    is_purchasable: this.editForm.is_purchasable,
                    is_sellable: this.editForm.is_sellable,
                    is_manufacturable: this.editForm.is_manufacturable
                };

                const response = await fetch(this.updateUrlBase + '/' + this.editItemId, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.editErrors = data.errors || {};
                    this.isEditSubmitting = false;
                    return;
                }

                if (!response.ok) {
                    this.editGeneralError = 'Something went wrong. Please try again.';
                    this.showToast('error', 'Unable to update material.');
                    this.isEditSubmitting = false;
                    return;
                }

                const data = await response.json();
                const uom = this.uomsById[data.data.base_uom_id] || { name: '', symbol: '' };
                const itemIndex = this.items.findIndex((item) => item.id === data.data.id);

                if (itemIndex !== -1) {
                    this.items[itemIndex] = {
                        ...this.items[itemIndex],
                        id: data.data.id,
                        name: data.data.name,
                        base_uom_id: data.data.base_uom_id,
                        base_uom_name: uom.name,
                        base_uom_symbol: uom.symbol,
                        is_purchasable: data.data.is_purchasable,
                        is_sellable: data.data.is_sellable,
                        is_manufacturable: data.data.is_manufacturable,
                        has_stock_moves: data.data.has_stock_moves ?? this.items[itemIndex].has_stock_moves
                    };
                }

                this.showToast('success', 'Material updated.');
                this.closeEdit();
            },
            async submitDelete() {
                this.isDeleteSubmitting = true;
                this.deleteError = '';

                const response = await fetch(this.updateUrlBase + '/' + this.deleteItemId, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.deleteError = data.message || 'Unable to delete material.';
                    this.showToast('error', this.deleteError);
                    this.isDeleteSubmitting = false;
                    return;
                }

                if (!response.ok) {
                    this.deleteError = 'Something went wrong. Please try again.';
                    this.showToast('error', 'Unable to delete material.');
                    this.isDeleteSubmitting = false;
                    return;
                }

                this.items = this.items.filter((item) => item.id !== this.deleteItemId);
                this.showToast('success', 'Material deleted.');
                this.closeDelete();
            }
        }"
        x-init="init()"
    >
        <script type="application/json" x-ref="itemsData">
            @json($itemsPayload)
        </script>
        <script type="application/json" x-ref="uomsData">
            @json($uomsPayload)
        </script>

        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

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
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="item in items" :key="item.id">
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                <a
                                                    class="text-gray-900 hover:text-blue-600"
                                                    x-bind:href="showUrlBase + '/' + item.id"
                                                    x-text="item.name"
                                                ></a>
                                            </td>
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

                                            <td class="px-4 py-4 text-right text-sm">
                                                <div
                                                    class="relative inline-block text-left"
                                                    x-data="{ open: false, top: 0, left: 0, width: 0 }"
                                                    x-on:keydown.escape.window="open = false"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700"
                                                        aria-label="Material actions"
                                                        x-ref="btn"
                                                        x-on:click="
                                                            open = !open;
                                                            if (open) {
                                                                const r = $refs.btn.getBoundingClientRect();
                                                                top = r.bottom;
                                                                left = r.right;
                                                                width = r.width;
                                                            }
                                                        "
                                                    >
                                                        ⋮
                                                    </button>

                                                    <template x-teleport="body">
                                                        <div
                                                            x-show="open"
                                                            x-on:click.outside="open = false"
                                                            x-transition
                                                            class="fixed z-50 mt-2 w-40 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
                                                            x-bind:style="'top:' + top + 'px; left:' + (left - 160) + 'px;'"
                                                        >
                                                            <div class="py-1">
                                                                <button
                                                                    type="button"
                                                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                                                                    x-on:click="open = false; openEdit(item)"
                                                                >
                                                                    Edit
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                                                    x-on:click="open = false; openDelete(item)"
                                                                >
                                                                    Delete
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
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

            <div
                class="fixed inset-0 z-40 flex items-center justify-center"
                x-show="isDeleteOpen"
                x-on:keydown.escape.window="closeDelete()"
            >
                <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeDelete()"></div>
                <div class="relative z-50 w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Delete material?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            This will permanently remove <span class="font-medium" x-text="deleteItemName"></span>.
                        </p>
                        <p class="mt-3 text-sm text-red-600" x-show="deleteError" x-text="deleteError"></p>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                x-on:click="closeDelete()"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-500"
                                x-on:click="submitDelete()"
                                :disabled="isDeleteSubmitting"
                                :class="isDeleteSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @include('materials.partials.create-material-slide-over', ['uoms' => $uoms])
            @include('materials.partials.edit-material-slide-over', ['uoms' => $uoms])
        </div>
    </div>
</x-app-layout>
