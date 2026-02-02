<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Suppliers') }}
        </h2>
    </x-slot>

    @php
        $suppliersPayload = $suppliers->map(function ($supplier) {
            return [
                'id' => $supplier->id,
                'company_name' => $supplier->company_name,
                'url' => $supplier->url,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'currency_code' => $supplier->currency_code,
                'showUrl' => route('purchasing.suppliers.show', $supplier),
            ];
        });
        $payload = [
            'suppliers' => $suppliersPayload,
            'storeUrl' => route('purchasing.suppliers.store'),
            'updateUrlBase' => url('/purchasing/suppliers'),
            'csrfToken' => csrf_token(),
            'defaultCurrency' => $defaultCurrency,
        ];
    @endphp

    <script type="application/json" id="purchasing-suppliers-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="purchasing-suppliers-index"
        data-payload="purchasing-suppliers-index-payload"
        x-data="purchasingSuppliersIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-6" x-show="suppliers.length > 0">
                <h3 class="text-lg font-medium text-gray-900">All suppliers</h3>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    x-on:click="openCreate()"
                >
                    Create Supplier
                </button>
            </div>

            <div x-cloak x-show="suppliers.length === 0">
                <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">No suppliers yet</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Suppliers will appear here once you add them.
                        </p>
                        <div class="mt-4">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                x-on:click="openCreate()"
                            >
                                Create Supplier
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="suppliers.length > 0">
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
                                            Currency
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="supplier in suppliers" :key="supplier.id">
                                        <tr>
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            <a
                                                class="text-blue-600 hover:text-blue-500"
                                                :href="supplier.showUrl"
                                            >
                                                <span x-text="supplier.company_name"></span>
                                            </a>
                                        </td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <span x-text="supplier.currency_code || '—'"></span>
                                            </td>
                                            <td class="px-4 py-4 text-right text-sm">
                                                <div
                                                    class="relative inline-block text-left"
                                                    x-on:keydown.escape.window="closeActionMenu()"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700"
                                                        aria-label="Supplier actions"
                                                        x-on:click="toggleActionMenu($event, supplier.id)"
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
                class="fixed inset-0 z-40 items-center justify-center"
                x-show="isCreateOpen"
                x-cloak
                x-on:keydown.escape.window="closeCreate()"
            >
                <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeCreate()"></div>
                <div class="relative z-50 w-full max-w-lg mx-4 bg-white rounded-lg shadow-xl">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Create supplier</h3>
                                <p class="mt-1 text-sm text-gray-600">Add a supplier to track purchasing relationships.</p>
                            </div>
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-600"
                                x-on:click="closeCreate()"
                                aria-label="Close"
                            >
                                ×
                            </button>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div>
                                <label for="supplier-company-name" class="block text-sm font-medium text-gray-700">Company name</label>
                                <input
                                    id="supplier-company-name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.company_name"
                                />
                                <p class="mt-1 text-sm text-red-600" x-text="errors.company_name[0]"></p>
                            </div>

                            <div>
                                <label for="supplier-url" class="block text-sm font-medium text-gray-700">Website</label>
                                <input
                                    id="supplier-url"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.url"
                                />
                                <p class="mt-1 text-sm text-red-600" x-text="errors.url[0]"></p>
                            </div>

                            <div>
                                <label for="supplier-phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input
                                    id="supplier-phone"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.phone"
                                />
                                <p class="mt-1 text-sm text-red-600" x-text="errors.phone[0]"></p>
                            </div>

                            <div>
                                <label for="supplier-email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input
                                    id="supplier-email"
                                    type="email"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.email"
                                />
                                <p class="mt-1 text-sm text-red-600" x-text="errors.email[0]"></p>
                            </div>

                            <div>
                                <label for="supplier-currency" class="block text-sm font-medium text-gray-700">Currency</label>
                                <input
                                    id="supplier-currency"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    x-model="form.currency_code"
                                />
                                <p class="mt-1 text-sm text-red-600" x-text="errors.currency_code[0]"></p>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-red-600" x-show="generalError" x-text="generalError"></p>

                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                x-on:click="closeCreate()"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-blue-500"
                                x-on:click="submitCreate()"
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Create
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="isEditOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isEditOpen"
                        x-on:click="closeEdit()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitEdit()">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Edit supplier</h2>
                                            <p class="mt-1 text-sm text-gray-600">Update the supplier details.</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-gray-500"
                                            x-on:click="closeEdit()"
                                        >
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6" x-show="editGeneralError">
                                        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="editGeneralError"></div>
                                    </div>

                                    <div class="mt-6 space-y-5">
                                        <div>
                                            <label for="edit-supplier-company-name" class="block text-sm font-medium text-gray-700">Company name</label>
                                            <input
                                                id="edit-supplier-company-name"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="editForm.company_name"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="editErrors.company_name[0]"></p>
                                        </div>

                                        <div>
                                            <label for="edit-supplier-url" class="block text-sm font-medium text-gray-700">Website</label>
                                            <input
                                                id="edit-supplier-url"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="editForm.url"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="editErrors.url[0]"></p>
                                        </div>

                                        <div>
                                            <label for="edit-supplier-phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                            <input
                                                id="edit-supplier-phone"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="editForm.phone"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="editErrors.phone[0]"></p>
                                        </div>

                                        <div>
                                            <label for="edit-supplier-email" class="block text-sm font-medium text-gray-700">Email</label>
                                            <input
                                                id="edit-supplier-email"
                                                type="email"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="editForm.email"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="editErrors.email[0]"></p>
                                        </div>

                                        <div>
                                            <label for="edit-supplier-currency" class="block text-sm font-medium text-gray-700">Currency</label>
                                            <input
                                                id="edit-supplier-currency"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="editForm.currency_code"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="editErrors.currency_code[0]"></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
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
                                        Update Supplier
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="fixed inset-0 z-40 items-center justify-center"
                x-show="isDeleteOpen"
                x-cloak
                x-on:keydown.escape.window="closeDelete()"
            >
                <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeDelete()"></div>
                <div class="relative z-50 w-full max-w-md mx-4 bg-white rounded-lg shadow-xl">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Delete supplier?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            This will permanently remove <span class="font-medium" x-text="deleteSupplierName"></span>.
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
