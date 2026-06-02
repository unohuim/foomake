<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Suppliers') }}
        </h2>
    </x-slot>

    <script type="application/json" id="purchasing-suppliers-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
        data-page="purchasing-suppliers-index"
        data-payload="purchasing-suppliers-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="purchasingSuppliersIndex"
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

            <x-slide-over-shell
                open="isFormOpen"
                close="closeForm()"
                submit="submitForm()"
                title-expression="formMode === 'create' ? 'Add supplier' : 'Edit supplier'"
                description="Manage supplier details without leaving the page."
                title-id="supplier-form-slide-over-title"
            >
                                    <div x-show="generalError">
                                        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="generalError"></div>
                                    </div>

                                    <div class="mt-6 space-y-5">
                                        <div>
                                            <label for="supplier-company-name" class="block text-sm font-medium text-gray-700">Company name</label>
                                            <input
                                                id="supplier-company-name"
                                                x-ref="supplierNameInput"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="form.company_name"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-show="errors.company_name" x-text="errors.company_name[0]"></p>
                                        </div>

                                        <div>
                                            <label for="supplier-url" class="block text-sm font-medium text-gray-700">Website</label>
                                            <input
                                                id="supplier-url"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="form.url"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-show="errors.url" x-text="errors.url[0]"></p>
                                        </div>

                                        <div>
                                            <label for="supplier-phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                            <input
                                                id="supplier-phone"
                                                type="text"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="form.phone"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-show="errors.phone" x-text="errors.phone[0]"></p>
                                        </div>

                                        <div>
                                            <label for="supplier-email" class="block text-sm font-medium text-gray-700">Email</label>
                                            <input
                                                id="supplier-email"
                                                type="email"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="form.email"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-show="errors.email" x-text="errors.email[0]"></p>
                                        </div>

                                        <div>
                                            <label for="supplier-currency" class="block text-sm font-medium text-gray-700">Currency</label>
                                            <input
                                                id="supplier-currency"
                                                type="text"
                                                maxlength="3"
                                                class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                x-model="form.currency_code"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-show="errors.currency_code" x-text="errors.currency_code[0]"></p>
                                        </div>
                                    </div>
                <x-slot name="footer">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        x-on:click="closeForm()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        :disabled="isSubmitting"
                                        :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                        x-text="formMode === 'create' ? 'Add Supplier' : 'Save Supplier'"
                                    >
                                    </button>
                </x-slot>
            </x-slide-over-shell>

            <div
                class="fixed inset-0 z-50 flex items-center justify-center"
                x-show="isArchiveOpen"
                x-cloak
                x-on:keydown.escape.window="closeArchive()"
            >
                <div class="fixed inset-0 bg-gray-900/30" x-on:click="closeArchive()"></div>
                <div class="relative z-50 w-full max-w-md mx-4 rounded-lg bg-white shadow-xl">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Archive supplier?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            This uses the existing supplier delete behavior for <span class="font-medium" x-text="archiveSupplierName"></span>.
                        </p>
                        <p class="mt-3 text-sm text-red-600" x-show="archiveError" x-text="archiveError"></p>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                x-on:click="closeArchive()"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-500"
                                x-on:click="submitArchive()"
                                :disabled="isArchiveSubmitting"
                                :class="isArchiveSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
