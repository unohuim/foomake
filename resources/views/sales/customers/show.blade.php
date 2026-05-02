<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customer: :name', ['name' => $customer->name]) }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-customers-show-payload">@json($payload)</script>

    <div
        class="py-12"
        data-page="sales-customers-show"
        data-payload="sales-customers-show-payload"
        x-data="salesCustomersShow"
    >
        <div class="fixed top-6 right-6 z-50" x-show="toast.visible">
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white border border-gray-100 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900" x-text="customer.status"></p>
                        </div>
                        <div class="inline-flex items-center gap-3">
                            <button type="button" class="text-blue-600 hover:text-blue-500" x-on:click="openEdit()">Edit</button>
                            <button type="button" class="text-yellow-600 hover:text-yellow-500" x-on:click="archive()">Archive</button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <div>
                            <p class="text-sm text-gray-500">Name</p>
                            <p class="mt-1 text-base text-gray-900" x-text="customer.name"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Notes</p>
                            <p class="mt-1 text-base text-gray-900 whitespace-pre-line" x-text="customer.notes || '—'"></p>
                        </div>
                    </div>

                    <div class="mt-8 rounded-2xl border border-gray-200 bg-gray-50/70 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500">Address</h3>
                                <p class="mt-1 text-sm text-gray-600">Stored customer address details.</p>
                            </div>
                            <p class="text-sm text-gray-500" x-show="customer.address_summary" x-text="customer.address_summary"></p>
                        </div>

                        <div class="mt-6 grid gap-6 sm:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500">Address line 1</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_line_1 || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Address line 2</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_line_2 || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">City</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.city || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Region</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.region || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Postal code</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.postal_code || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Country code</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.country_code || '—'"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-sm text-gray-500">Formatted address</p>
                                <p class="mt-1 whitespace-pre-line text-base text-gray-900" x-text="customer.formatted_address || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Latitude</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.latitude || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Longitude</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.longitude || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Address provider</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_provider || '—'"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Provider ID</p>
                                <p class="mt-1 text-base text-gray-900" x-text="customer.address_provider_id || '—'"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                                            <h2 class="text-lg font-medium text-gray-900">Edit customer</h2>
                                            <p class="mt-1 text-sm text-gray-600">Update the customer details.</p>
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
                                                    type="text"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.name"
                                                />
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.name[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Status
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="form.status"
                                                >
                                                    <template x-for="status in statuses" :key="status">
                                                        <option :value="status" x-text="status"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.status[0]"></p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Notes
                                                <textarea
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    rows="4"
                                                    x-model="form.notes"
                                                ></textarea>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.notes[0]"></p>
                                        </div>

                                        <section class="rounded-2xl border border-gray-200 bg-gray-50/60 p-4">
                                            <div class="mb-4">
                                                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500">Address</h3>
                                                <p class="mt-1 text-sm text-gray-600">Update the customer address fields used on the detail and list views.</p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.address_line_1[0]"></p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.address_line_2[0]"></p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.city[0]"></p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.region[0]"></p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.postal_code[0]"></p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.country_code[0]"></p>
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
                                                    <p class="mt-1 text-sm text-red-600" x-text="errors.formatted_address[0]"></p>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <p class="mt-4 text-sm text-red-600" x-show="generalError" x-text="generalError"></p>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeForm()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        :disabled="isSubmitting"
                                        :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                                    >
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
