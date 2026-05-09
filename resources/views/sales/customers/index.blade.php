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
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.name[0]"></p>
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
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.status[0]"></p>
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
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.notes[0]"></p>
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
                x-show="isImportPanelOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
                data-customers-import-panel
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isImportPanelOpen"
                        x-on:click="closeImportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-3xl">
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">Import Customers</h2>
                                            <p class="mt-1 text-sm text-gray-600">Preview WooCommerce customers first, then confirm the import without leaving the page.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeImportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">
                                                Source
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="selectedSource"
                                                    x-on:change="handleSourceChange()"
                                                >
                                                    <option value="">Select source</option>
                                                    <template x-for="source in sources" :key="source.value">
                                                        <option :value="source.value" :disabled="!source.enabled" x-text="source.label"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <p class="mt-1 text-sm text-red-600" x-text="importErrors.source[0]"></p>
                                        </div>

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedSource && selectedSourceEnabled() && !sourceConnected()">
                                            <h3 class="text-sm font-semibold text-gray-900">Connection required</h3>
                                            <p class="mt-1 text-sm text-gray-600">
                                                WooCommerce status:
                                                <span class="font-medium" x-text="selectedSourceConnectionLabel()"></span>.
                                            </p>
                                            <p class="mt-2 text-sm text-gray-600" x-show="canManageConnections">
                                                Manage store credentials from Profile → Connectors before loading a preview.
                                            </p>
                                            <p class="mt-2 text-sm text-gray-600" x-show="!canManageConnections">
                                                Ask an admin to connect WooCommerce from Profile → Connectors before loading a preview.
                                            </p>
                                            <div class="mt-4" x-show="canManageConnections && connectorsPageUrl">
                                                <a
                                                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                                    :href="connectorsPageUrl"
                                                >
                                                    Open Connectors
                                                </a>
                                            </div>
                                        </div>

                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedSource && selectedSourceEnabled() && sourceConnected()">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <h3 class="text-sm font-semibold text-gray-900">Preview importable customers</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Load the current WooCommerce customer list before confirming the import.</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                                    x-bind:disabled="isLoadingPreview"
                                                    x-bind:class="isLoadingPreview ? 'cursor-not-allowed opacity-60' : ''"
                                                    x-on:click="loadPreview()"
                                                >
                                                    <span x-show="!isLoadingPreview">Load Preview</span>
                                                    <span x-show="isLoadingPreview">Loading Preview...</span>
                                                </button>
                                            </div>
                                            <p class="mt-3 text-sm text-red-600" x-text="importPreviewError"></p>
                                        </div>

                                        <div x-show="previewRows.length > 0">
                                            <div class="mb-4 flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                                <p class="text-sm text-gray-700">
                                                    <span class="font-medium" x-text="selectedRowCount()"></span>
                                                    customers selected for import
                                                </p>
                                            </div>

                                            <div class="overflow-x-auto rounded-lg border border-gray-100">
                                                <table class="min-w-full divide-y divide-gray-100">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Select</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Address</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 bg-white">
                                                        <template x-for="(row, index) in previewRows" :key="row.external_id">
                                                            <tr>
                                                                <td class="px-4 py-4 align-top">
                                                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.selected">
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-900 align-top">
                                                                    <p class="font-medium" x-text="row.name"></p>
                                                                    <p class="mt-1 text-xs text-gray-500" x-text="row.external_id"></p>
                                                                </td>
                                                                <td class="px-4 py-4 text-sm text-gray-700 align-top" x-text="row.email || '—'"></td>
                                                                <td class="px-4 py-4 text-sm text-gray-700 align-top">
                                                                    <p class="max-w-sm whitespace-pre-line" x-text="[row.address_line_1, row.address_line_2, row.city, row.region, row.postal_code, row.country_code].filter(Boolean).join(', ') || '—'"></p>
                                                                    <p class="mt-1 text-xs text-red-600" x-text="rowError(index, 'name')"></p>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <p class="mt-3 text-sm text-red-600" x-text="importError"></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeImportPanel()"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        x-show="previewRows.length > 0"
                                        x-bind:disabled="isSubmittingImport"
                                        x-bind:class="isSubmittingImport ? 'cursor-not-allowed opacity-60' : ''"
                                        x-on:click="submitImport()"
                                    >
                                        Confirm Import
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
