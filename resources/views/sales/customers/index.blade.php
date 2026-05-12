<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>

    <script type="application/json" id="sales-customers-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col overflow-hidden"
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

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>

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
                x-show="slideOvers.import.open"
                x-cloak
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="slideOvers.import.open"
                        x-on:click="closeImportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-0 pr-0 sm:pl-10">
                        <div class="pointer-events-auto w-screen max-w-4xl">
                            <div class="flex h-full min-h-0 w-full flex-col bg-white shadow-xl">
                                <div class="shrink-0 border-b border-gray-100 pl-4 pr-6 py-6 sm:px-6">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900" x-text="slideOverTitle('import')"></h2>
                                            <p class="mt-1 text-sm text-gray-600">WooCommerce previews import real external customer rows while preserving customer-specific validation and contact creation.</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeImportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>
                                </div>

                                <div class="flex min-h-0 w-full flex-1 flex-col gap-6 overflow-hidden pl-4 pr-6 py-6 sm:px-6">
                                    <div class="shrink-0 w-full min-w-0 space-y-6">
                                        <div class="w-full min-w-0 box-border">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Source
                                                <select
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    x-model="selectedSource"
                                                    x-on:change="handleSourceChange()"
                                                >
                                                    <option value="">Select source</option>
                                                    <template x-for="source in sources" :key="source.value">
                                                        <option :value="source.value" :disabled="!source.enabled" x-text="sourceOptionLabel(source)"></option>
                                                    </template>
                                                    <template x-for="fileSource in cachedFileSources" :key="fileSource.value">
                                                        <option :value="fileSource.value" x-text="fileSource.label"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <input
                                                x-ref="importFileInput"
                                                type="file"
                                                accept=".csv,text/csv"
                                                class="sr-only"
                                                x-on:change="handleLocalFileChange($event)"
                                            />
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.source[0]"></p>
                                            <p class="mt-1 text-sm text-red-600" x-text="errors.file[0]"></p>
                                        </div>

                                        <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-gray-50 px-4 py-4" x-show="selectedSource && !isFileUploadMode() && selectedSourceEnabled() && !sourceConnected()">
                                            <h3 class="text-sm font-semibold text-gray-900">Connection required</h3>
                                            <p class="mt-1 text-sm text-gray-600">
                                                WooCommerce status:
                                                <span class="font-medium" x-text="selectedSourceStatusLabel()"></span>.
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
                                    </div>

                                    <div
                                        class="flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center"
                                        x-show="!hasSelectedImportSource()"
                                    >
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                            <svg class="h-7 w-7 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V7.5m0 0L8.25 11.25M12 7.5l3.75 3.75M3.75 16.5v1.125c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V16.5" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-6 text-base font-semibold text-gray-900">Choose an import source</h3>
                                        <p class="mt-2 max-w-md text-sm text-gray-600">Select a source to start loading a customer import preview.</p>
                                    </div>

                                    <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white" x-show="hasSelectedImportSource()">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-4 text-left"
                                            x-on:click="previewRecordsAccordionOpen = !previewRecordsAccordionOpen"
                                        >
                                            <span class="text-sm font-semibold text-gray-900">Import Preview</span>
                                            <svg class="h-5 w-5 text-gray-400 transition" :class="previewRecordsAccordionOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <div class="w-full min-w-0 overflow-hidden border-t border-gray-100" x-show="previewRecordsAccordionOpen" x-cloak>
                                            <div class="shrink-0 w-full min-w-0 space-y-4 pl-4 pr-5 py-4 sm:px-4">
                                                <div class="flex w-full min-w-0 box-border flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                    <div class="flex w-full min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                                                        <label class="block min-w-0 flex-1 text-sm text-gray-700">
                                                            <span class="sr-only">Search preview records</span>
                                                            <input
                                                                type="search"
                                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                placeholder="Search preview records"
                                                                x-model="previewSearch"
                                                            />
                                                        </label>
                                                        <label class="inline-flex max-w-full items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="showDuplicateRows">
                                                            <span class="truncate" x-text="`Show Duplicates (${duplicateRowCount()} rows)`"></span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <label class="inline-flex items-center gap-3 text-sm text-gray-700">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                            :checked="allVisibleSelectableRowsSelected()"
                                                            x-on:change="toggleVisibleRowSelection($event)"
                                                        >
                                                        Select All
                                                    </label>
                                                    <p class="text-sm text-gray-500" x-show="!showDuplicateRows && duplicateRowCount() > 0">
                                                        Duplicate rows are hidden until enabled.
                                                    </p>
                                                </div>

                                                <p class="text-sm text-red-600" x-text="previewError"></p>
                                                <p class="text-sm text-red-600" x-text="importError"></p>
                                            </div>

                                            <div class="w-full min-w-0 box-border overflow-hidden pl-4 pr-5 sm:px-4">
                                                <div class="max-h-[32rem] w-full min-w-0 overflow-y-auto pb-52 sm:pb-32">
                                                    <div class="space-y-4" x-show="isLoadingPreview">
                                                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700" x-text="previewLoadingMessage"></div>
                                                        <div class="grid gap-4 lg:grid-cols-2">
                                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                                <div class="h-4 w-1/3 animate-pulse rounded bg-gray-200"></div>
                                                                <div class="mt-3 h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                                            </div>
                                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                                <div class="h-4 w-1/4 animate-pulse rounded bg-gray-200"></div>
                                                                <div class="mt-3 h-4 w-3/4 animate-pulse rounded bg-gray-100"></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="flex min-h-full items-center justify-center px-4 py-12"
                                                        x-show="!isLoadingPreview && !hasVisiblePreviewRows()"
                                                    >
                                                        <div class="w-full max-w-md rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-10 text-center">
                                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                                                </svg>
                                                            </div>
                                                            <h3 class="mt-4 text-sm font-semibold text-gray-900" x-text="previewEmptyStateTitle()"></h3>
                                                            <p class="mt-2 text-sm text-gray-600" x-text="previewEmptyStateMessage()"></p>
                                                        </div>
                                                    </div>

                                                    <div class="w-full min-w-0 space-y-2" x-show="!isLoadingPreview && hasVisiblePreviewRows()">
                                                        <template x-for="(row, index) in previewRows" :key="row.external_id">
                                                            <article
                                                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
                                                                x-show="rowVisibleInPreview(row)"
                                                                x-bind:aria-hidden="rowVisibleInPreview(row) ? 'false' : 'true'"
                                                            >
                                                                <div class="flex min-h-10 items-center gap-3">
                                                                    <input type="checkbox" class="shrink-0 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.selected" x-bind:disabled="rowSelectionDisabled(row)">
                                                                    <div class="min-w-0 flex-1 overflow-hidden">
                                                                        <div class="flex min-w-0 items-center justify-between gap-3">
                                                                            <p class="block truncate text-sm font-medium text-gray-900" x-bind:title="previewPrimaryLabel(row)" x-text="previewPrimaryLabel(row)"></p>
                                                                            <p class="shrink-0 truncate text-xs text-gray-500" x-show="rowHasSecondaryLabel(row)" x-text="previewSecondaryLabel(row)"></p>
                                                                        </div>
                                                                        <template x-if="rowHasCustomerErrors(index)">
                                                                            <div class="mt-3 space-y-1">
                                                                                <template x-for="message in rowCustomerErrors(index)" :key="message">
                                                                                    <p class="text-xs text-red-600" x-text="message"></p>
                                                                                </template>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </article>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 pl-4 pr-6 py-4 sm:px-6">
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
                                        x-on:click="submitImport()"
                                    >
                                        Import Selected
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
