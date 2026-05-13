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
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div data-crud-root></div>

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="isFormOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isFormOpen"
                        x-on:click="closeForm()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitForm()">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="formMode === 'create' ? 'Create customer' : 'Edit customer'"></h2>
                                            <p class="mt-1 text-sm text-gray-600">Manage customer details without leaving the page.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeForm()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-4">
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
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
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
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="slideOvers.export.open"
                x-cloak
                role="dialog"
                aria-modal="true"
                data-customers-export-panel
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="slideOvers.export.open"
                        x-on:click="closeExportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="slideOverTitle('export')"></h2>
                                            <p class="mt-1 text-sm text-gray-600">Export customers as CSV using the current customers list filters and sort when needed.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeExportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-4">
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                            <h3 class="text-sm font-semibold text-gray-900">Format</h3>
                                            <p class="mt-1 text-sm text-gray-600">CSV</p>
                                        </div>

                                        <fieldset class="space-y-3">
                                            <legend class="text-sm font-medium text-gray-700">Export Scope</legend>

                                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                                                <input type="radio" class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500" value="current" x-model="exportScope">
                                                <div>
                                                    <p class="font-medium text-gray-900">Current filters and sort</p>
                                                    <p class="mt-1 text-gray-600">Uses the current search text and sort order from the customers list.</p>
                                                </div>
                                            </label>

                                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                                                <input type="radio" class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500" value="all" x-model="exportScope">
                                                <div>
                                                    <p class="font-medium text-gray-900">All records</p>
                                                    <p class="mt-1 text-gray-600">Exports every active customer in the current tenant.</p>
                                                </div>
                                            </label>
                                        </fieldset>

                                        <p class="text-sm text-red-600" x-text="exportError"></p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeExportPanel()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        x-bind:disabled="isExportSubmitting"
                                        x-bind:class="isExportSubmitting ? 'cursor-not-allowed opacity-60' : ''"
                                        x-on:click="submitExport()"
                                    >
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
