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
            ];
        });
        $payload = [
            'suppliers' => $suppliersPayload,
            'storeUrl' => route('purchasing.suppliers.store'),
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="supplier in suppliers" :key="supplier.id">
                                        <tr>
                                            <td class="px-4 py-4 text-sm text-gray-900" x-text="supplier.company_name"></td>
                                            <td class="px-4 py-4 text-sm text-gray-700">
                                                <span x-text="supplier.currency_code || '—'"></span>
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
                x-bind:class="isCreateOpen ? 'flex' : 'hidden'"
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
        </div>
    </div>
</x-app-layout>
