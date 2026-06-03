const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const asArray = (value) => (Array.isArray(value) ? value : []);

const asString = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const toStringValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
};

const escapeHtmlAttribute = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');

const serializeJsonAttribute = (value) => escapeHtmlAttribute(JSON.stringify(value));

const renderPurchaseOrderCreate = (config) => `
    <div x-data="purchaseOrderCreate($el)">
        <div
            data-purchase-order-create-panel
            class="fixed inset-0 z-40 flex justify-end bg-gray-900/30"
            x-show="isOpen"
            x-cloak
        >
            <div class="flex h-full w-full max-w-xl flex-col overflow-y-auto bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-4 sm:px-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900">Create Purchase Order</h4>
                        <p class="mt-1 text-sm text-gray-500">Create a draft purchase order with one line.</p>
                    </div>
                    <button
                        type="button"
                        aria-label="Close"
                        class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                        x-on:click="close()"
                    >
                        <span aria-hidden="true" class="text-lg leading-none">&times;</span>
                    </button>
                </div>

                <div class="flex-1 space-y-5 px-4 py-5 sm:px-6">
                    <p class="text-sm text-red-600" x-show="formError" x-text="formError"></p>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="purchase-order-create-supplier">Supplier</label>
                        <div
                            id="purchase-order-create-supplier"
                            data-supplier-options="${serializeJsonAttribute(asArray(config.suppliers).map((supplier) => ({
                                value: toStringValue(supplier.id),
                                label: asString(supplier.name),
                            })))}"
                            x-data="combobox({
                                name: 'supplier_id',
                                options: JSON.parse($el.dataset.supplierOptions || '[]'),
                                selectedValue: form.supplier_id,
                                placeholder: 'Search suppliers',
                                noResultsText: 'No suppliers found.',
                                inputId: 'purchase-order-create-supplier-input',
                                listId: 'purchase-order-create-supplier-listbox',
                            })"
                            x-modelable="selectedValue"
                            x-model="form.supplier_id"
                            x-on:click.outside="closeDropdown()"
                            x-on:focusout="handleFocusAway($event)"
                            x-on:keydown.arrow-down.prevent="highlightNext()"
                            x-on:keydown.arrow-up.prevent="highlightPrevious()"
                            x-on:keydown.enter.prevent="selectHighlighted()"
                            x-on:keydown.escape.prevent="closeDropdown()"
                            x-effect="configuredOptions = supplierComboboxOptions()"
                        >
                            <div class="relative mt-1">
                                <input
                                    id="purchase-order-create-supplier-input"
                                    type="text"
                                    role="combobox"
                                    autocomplete="off"
                                    class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-11 text-sm text-gray-900 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                    :placeholder="placeholder"
                                    x-model="query"
                                    x-on:focus="openDropdown()"
                                    x-on:input="handleQueryInput($event.target.value)"
                                    x-bind:aria-expanded="open.toString()"
                                    x-bind:aria-controls="listId"
                                    x-bind:aria-activedescendant="activeDescendantId()"
                                />

                                <input type="hidden" name="supplier_id" x-model="selectedValue" />

                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </div>

                                <div x-ref="slotOptions" class="hidden"></div>

                                <div
                                    class="absolute z-20 mt-2 max-h-72 w-full overflow-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl ring-1 ring-black/5"
                                    x-cloak
                                    x-show="open"
                                    role="listbox"
                                    id="purchase-order-create-supplier-listbox"
                                >
                                    <template x-if="filteredOptions().length === 0">
                                        <div class="rounded-xl px-3 py-3 text-sm text-gray-500" x-text="noResultsText"></div>
                                    </template>

                                    <template x-for="(option, index) in filteredOptions()" :key="option.value">
                                        <button
                                            type="button"
                                            class="flex w-full items-start justify-between rounded-xl px-3 py-3 text-left transition"
                                            role="option"
                                            x-bind:id="optionDomId(index)"
                                            x-bind:aria-selected="isSelected(option).toString()"
                                            x-on:mouseenter="highlightedIndex = index"
                                            x-on:click="selectOption(option)"
                                            x-bind:class="highlightedIndex === index ? 'bg-blue-50 text-blue-900' : 'text-gray-900 hover:bg-gray-50'"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium" x-text="option.label"></span>
                                                <span class="mt-1 block truncate text-xs text-gray-500" x-show="option.description" x-text="option.description"></span>
                                            </span>

                                            <span class="ml-3 text-blue-600" x-show="isSelected(option)">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-red-600" x-text="firstError('supplier_id')"></p>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="purchase-order-create-package">PACKAGE</label>
                            <select
                                id="purchase-order-create-package"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-model="form.item_purchase_option_id"
                            >
                                <option value="">Select</option>
                                <template x-for="option in availablePackages" :key="option.id">
                                    <option :value="option.id" x-text="option.label"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-red-600" x-text="firstError('item_purchase_option_id')"></p>
                        </div>

                        <div class="w-24 shrink-0">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="purchase-order-create-pack-count">QTY</label>
                            <input
                                id="purchase-order-create-pack-count"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-model="form.pack_count"
                            />
                            <p class="mt-1 text-xs text-red-600" x-text="firstError('pack_count')"></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-4 py-4 sm:px-6">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50" x-on:click="close()">Cancel</button>
                    <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50" x-bind:disabled="isSubmitting" x-on:click="submit()">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
`;

const createState = (config) => ({
    config,
    isOpen: false,
    isSubmitting: false,
    errors: {},
    formError: '',
    form: {
        supplier_id: '',
        item_purchase_option_id: '',
        pack_count: '',
    },
    init() {
        this.$watch('form.supplier_id', () => {
            this.handleSupplierChange();
        });
    },
    get packages() {
        return asArray(this.config.packages);
    },
    get availablePackages() {
        const supplierId = toStringValue(this.form.supplier_id);

        if (supplierId === '') {
            return this.packages;
        }

        return this.packages.filter((option) => toStringValue(option.supplier_id) === supplierId);
    },
    supplierComboboxOptions() {
        return asArray(this.config.suppliers).map((supplier) => ({
            value: toStringValue(supplier.id),
            label: asString(supplier.name),
        }));
    },
    refreshFromSupplierPackage(record = {}) {
        const supplierId = toStringValue(record.supplier_id);
        const supplierName = asString(record.supplier_name);
        const packageId = toStringValue(record.item_purchase_option_id ?? record.id);

        if (supplierId !== '' && supplierName !== '') {
            const supplierExists = asArray(this.config.suppliers)
                .some((supplier) => toStringValue(supplier.id) === supplierId);

            if (!supplierExists) {
                this.config.suppliers = [
                    ...asArray(this.config.suppliers),
                    {
                        id: Number(supplierId),
                        name: supplierName,
                    },
                ];
            }
        }

        if (packageId === '') {
            return;
        }

        const packageExists = asArray(this.config.packages)
            .some((option) => toStringValue(option.id) === packageId);

        if (packageExists) {
            this.config.packages = asArray(this.config.packages).map((option) => (
                toStringValue(option.id) === packageId
                    ? {
                        ...option,
                        ...record,
                        id: Number(packageId),
                    }
                    : option
            ));
            return;
        }

        this.config.packages = [
            ...asArray(this.config.packages),
            {
                id: Number(packageId),
                supplier_id: Number(supplierId),
                supplier_name: supplierName,
                item_id: record.item_id,
                item_name: asString(record.item_name),
                label: asString(record.label, asString(record.package_display, supplierName)),
                current_price_cents: record.current_price_cents ?? 0,
            },
        ];
    },
    firstError(field) {
        const values = this.errors[field];

        if (!Array.isArray(values) || values.length === 0) {
            return '';
        }

        return values[0];
    },
    handleSupplierChange() {
        const selectedOptionId = toStringValue(this.form.item_purchase_option_id);
        const matchesCurrentSupplier = this.availablePackages.some((option) => toStringValue(option.id) === selectedOptionId);

        if (!matchesCurrentSupplier) {
            this.form.item_purchase_option_id = '';
        }
    },
    openFromSupplierPackage(prefill = {}) {
        this.errors = {};
        this.formError = '';
        this.isSubmitting = false;
        this.form = {
            supplier_id: toStringValue(prefill.supplier_id),
            item_purchase_option_id: toStringValue(prefill.item_purchase_option_id),
            pack_count: '',
        };
        this.isOpen = true;
    },
    close() {
        this.isOpen = false;
        this.isSubmitting = false;
        this.errors = {};
        this.formError = '';
    },
    async submit() {
        if (!this.config.storeUrl || this.isSubmitting) {
            return;
        }

        this.isSubmitting = true;
        this.errors = {};
        this.formError = '';

        try {
            const response = await fetch(this.config.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken || '',
                },
                body: JSON.stringify({
                    supplier_id: this.form.supplier_id === '' ? null : Number(this.form.supplier_id),
                    item_purchase_option_id: this.form.item_purchase_option_id === '' ? null : Number(this.form.item_purchase_option_id),
                    pack_count: this.form.pack_count,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = asRecord(data.errors);
                this.formError = asString(data.message, 'Unable to create purchase order.');
                return;
            }

            if (!response.ok) {
                this.formError = 'Unable to create purchase order.';
                return;
            }

            const data = await response.json();
            const showUrl = asString(data.data?.show_url);

            if (showUrl !== '') {
                window.location.href = showUrl;
                return;
            }

            this.formError = 'Purchase order created, but redirect failed.';
        } catch (error) {
            this.formError = 'Unable to create purchase order.';
        } finally {
            this.isSubmitting = false;
        }
    },
});

export function mountPurchaseOrderCreate(targetEl, input) {
    if (!targetEl) {
        return null;
    }

    const config = asRecord(input);
    const Alpine = globalThis.Alpine;
    const state = Alpine && typeof Alpine.reactive === 'function'
        ? Alpine.reactive(createState(config))
        : createState(config);

    targetEl._purchaseOrderCreateConfig = config;
    targetEl._purchaseOrderCreateState = state;
    targetEl.innerHTML = renderPurchaseOrderCreate(config);

    Alpine.data('purchaseOrderCreate', (el) => (
        el.closest('[data-purchase-order-create-root]')?._purchaseOrderCreateState || state
    ));

    Alpine.initTree(targetEl);

    return state;
}
