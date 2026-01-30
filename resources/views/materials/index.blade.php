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
        $payload = [
            'items' => $itemsPayload,
            'uoms' => $uomsPayload,
            'uomsExist' => $uomsExist,
            'updateUrlBase' => url('/materials'),
            'showUrlBase' => url('/materials'),
            'storeUrl' => route('materials.store'),
            'csrfToken' => csrf_token(),
        ];
    @endphp

    <script type="application/json" id="materials-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="materials-index"
        data-payload="materials-index-payload"
        x-data="materialsIndex"
        x-init="init()"
    >
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

            <div x-cloak x-show="items.length === 0">
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
                                                    x-on:keydown.escape.window="closeActionMenu()"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700"
                                                        aria-label="Material actions"
                                                        x-on:click="toggleActionMenu($event, item.id)"
                                                    >
                                                        ⋮
                                                    </button>
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
                class="fixed inset-0 z-40 items-center justify-center hidden"
                x-bind:class="isDeleteOpen ? 'flex' : 'hidden'"
                x-cloak
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

        <template x-teleport="body">
            <div
                x-show="actionMenuOpen"
                x-cloak
                x-on:click.outside="closeActionMenu()"
                x-transition
                class="fixed z-50 mt-2 w-40 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5"
                x-bind:style="'top:' + actionMenuTop + 'px; left:' + (actionMenuLeft - 160) + 'px;'"
            >
                <div class="py-1">
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        x-on:click="openEditFromActionMenu()"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                        x-on:click="openDeleteFromActionMenu()"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
