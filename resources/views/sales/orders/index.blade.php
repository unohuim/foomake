<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Sales Orders') }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-orders-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-orders-index"
        data-payload="sales-orders-index-payload"
        data-crud-config='@json($crudConfig)'
        data-import-config='@json($importConfig)'
        x-data="salesOrdersIndex"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div data-crud-root></div>

            <x-slide-over-shell
                open="isFormOpen"
                close="closeForm()"
                submit="submitForm()"
                title-expression="formMode === 'create' ? 'Create sales order' : 'Edit sales order'"
                description="Manage order header details without leaving the list page."
                title-id="sales-order-form-slide-over-title"
            >
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Customer
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.customer_id"
                                                    x-on:change="handleCustomerChange()"
                                                >
                                                    <option value="">Select customer</option>
                                                    <template x-for="customer in customers" :key="customer.id">
                                                        <option :value="String(customer.id)" x-text="customer.name"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.customer_id[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Contact
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.contact_id"
                                                >
                                                    <option value="">No contact</option>
                                                    <template x-for="contact in selectedCustomerContacts()" :key="contact.id">
                                                        <option :value="String(contact.id)" x-text="contactOptionLabel(contact)"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.contact_id[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Order date
                                                <input
                                                    type="date"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.order_date"
                                                />
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.order_date[0]"></p>
                                        </div>
                                    </div>

                                    <p class="mt-4 text-sm text-red-600" x-show="generalError" x-text="generalError"></p>

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
                                    >
                                        <span x-text="formMode === 'create' ? 'Create Order' : 'Save Order'"></span>
                                    </button>
                </x-slot>
            </x-slide-over-shell>
        </div>
    </div>
</x-app-layout>
