const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const asArray = (value) => (Array.isArray(value) ? value : []);

const asString = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const toStringValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
};

const renderPurchaseOrderCreate = () => `
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
                    <button type="button" class="text-sm text-gray-500 transition hover:text-gray-700" x-on:click="close()">Close</button>
                </div>

                <div class="flex-1 space-y-5 px-4 py-5 sm:px-6">
                    <p class="text-sm text-red-600" x-show="formError" x-text="formError"></p>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="purchase-order-create-supplier">Supplier</label>
                        <select
                            id="purchase-order-create-supplier"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            x-model="form.supplier_id"
                            x-on:change="handleSupplierChange()"
                        >
                            <option value="">Select</option>
                            <template x-for="supplier in suppliers" :key="supplier.id">
                                <option :value="supplier.id" x-text="supplier.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-red-600" x-text="firstError('supplier_id')"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="purchase-order-create-package">Supplier Package</label>
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

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500" for="purchase-order-create-pack-count">Quantity</label>
                        <input
                            id="purchase-order-create-pack-count"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            x-model="form.pack_count"
                        />
                        <p class="mt-1 text-xs text-red-600" x-text="firstError('pack_count')"></p>
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
    get suppliers() {
        return asArray(this.config.suppliers);
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
    targetEl.innerHTML = renderPurchaseOrderCreate();

    Alpine.data('purchaseOrderCreate', (el) => (
        el.closest('[data-purchase-order-create-root]')?._purchaseOrderCreateState || state
    ));

    return state;
}
