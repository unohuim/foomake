<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-customers-index-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-customers-index"
        data-payload="sales-customers-index-payload"
        data-crud-config='@json($crudConfig)'
        data-import-config='@json($importConfig)'
        x-data="salesCustomersIndex"
    >
        <x-ui.toast visible="toast.visible" type="toast.type" message="toast.message" />

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div data-crud-root></div>

            <x-slide-over-shell
                open="isFormOpen"
                close="closeForm()"
                submit="submitForm()"
                title-expression="formMode === 'create' ? 'Create customer' : 'Edit customer'"
                description="Manage customer details without leaving the page."
                title-id="customer-form-slide-over-title"
            >
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Name
                                                <input
                                                    x-ref="customerNameInput"
                                                    type="text"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.name"
                                                />
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="formErrors.name[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Customer Type
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.customer_type"
                                                >
                                                    <template x-for="[value, label] in Object.entries(customerTypes)" :key="value">
                                                        <option :value="value" x-text="label"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="formErrors.customer_type[0]"></p>
                                        </div>

                                        <div x-show="formMode === 'edit'">
                                            <label class="block text-sm font-medium text-gray-700">
                                                <span x-text="'Sta' + 'tus'"></span>
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.status"
                                                >
                                                    <template x-for="status in statuses" :key="status">
                                                        <option :value="status" x-text="status"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="formErrors.status[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                <span x-text="'No' + 'tes'"></span>
                                                <textarea
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    rows="4"
                                                    x-model="form.notes"
                                                ></textarea>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="formErrors.notes[0]"></p>
                                        </div>

                                        <section class="rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                                            <div class="mb-4">
                                                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500">Address</h3>
                                                <p class="mt-1 text-sm text-gray-600">Capture mailing or service details without leaving the page.</p>
                                            </div>

                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Address line 1
                                                        <input
                                                            type="text"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="form.address_line_1"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.address_line_1[0]"></p>
                                                </div>

                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Address line 2
                                                        <input
                                                            type="text"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="form.address_line_2"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.address_line_2[0]"></p>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        City
                                                        <input
                                                            type="text"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="form.city"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.city[0]"></p>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Region
                                                        <input
                                                            type="text"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="form.region"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.region[0]"></p>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Postal code
                                                        <input
                                                            type="text"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="form.postal_code"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.postal_code[0]"></p>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Country code
                                                        <input
                                                            type="text"
                                                            maxlength="2"
                                                            class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="form.country_code"
                                                        />
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.country_code[0]"></p>
                                                </div>

                                                <div class="sm:col-span-2">
                                                    <label class="block text-sm font-medium text-gray-700">
                                                        Formatted address
                                                        <textarea
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            rows="3"
                                                            x-model="form.formatted_address"
                                                        ></textarea>
                                                    </label>
                                                    <p class="mt-1 text-sm text-red-600" x-text="formErrors.formatted_address[0]"></p>
                                                </div>
                                            </div>
                                        </section>
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
                                        <span x-text="formMode === 'create' ? 'Create Customer' : 'Save Customer'"></span>
                                    </button>
                </x-slot>
            </x-slide-over-shell>

        </div>
    </div>
</x-app-layout>
