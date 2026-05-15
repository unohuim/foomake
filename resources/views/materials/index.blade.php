<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Materials') }}
        </h2>
    </x-slot>

    <script type="application/json" id="materials-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
        data-page="materials-index"
        data-payload="materials-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="materialsIndex"
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
    </div>
</x-app-layout>
