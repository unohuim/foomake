import Alpine from 'alpinejs';
import { mountCrudSection } from '../lib/js-crud-section';
import { mountPurchaseOrderCreate } from '../lib/js-purchase-order-create';

const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const asString = (value, fallback = '') => {
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    return fallback;
};

const toStringValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
};

const packageDisplayText = (record) => {
    const quantity = asString(record.pack_quantity_display, asString(record.pack_quantity, '—'));
    const uomSymbol = asString(record.pack_uom_symbol);

    if (uomSymbol === '') {
        return quantity;
    }

    return `${quantity} ${uomSymbol}`;
};

const supplierPackageStateDisplay = (record) => {
    if (record.is_active === false || record.state === 'archived') {
        return {
            text: 'Archived',
            tone: 'muted',
        };
    }

    return {
        text: 'Active',
        tone: 'success',
    };
};

const formatMoney = (currencyCode, cents) => {
    const safeCurrencyCode = asString(currencyCode, 'USD');
    const safeCents = Number(cents || 0);

    return `${safeCurrencyCode} ${(safeCents / 100).toFixed(2)}`;
};

const purchaseOrderStatusDisplay = (record) => {
    switch (record.status) {
    case 'OPEN':
    case 'PARTIALLY-RECEIVED':
        return {
            text: record.status,
            tone: 'default',
        };
    case 'RECEIVED':
        return {
            text: record.status,
            tone: 'success',
        };
    case 'BACK-ORDERED':
    case 'SHORT-CLOSED':
    case 'CANCELLED':
        return {
            text: record.status,
            tone: 'muted',
        };
    default:
        return {
            text: asString(record.status, '—'),
            tone: 'muted',
        };
    }
};

const buildSupplierPackagePayload = (form) => ({
    item_id: toStringValue(form.item_id),
    pack_quantity: asString(form.pack_quantity),
    pack_uom_id: toStringValue(form.pack_uom_id),
    supplier_sku: asString(form.supplier_sku),
    price_amount: asString(form.price_amount),
});

const detailsFromSupplier = (supplier) => ({
    company_name: asString(supplier.company_name),
    url: asString(supplier.url),
    phone: asString(supplier.phone),
    email: asString(supplier.email),
    currency_code: asString(supplier.currency_code),
});

const emptyDetailsSavedFields = () => ({
    company_name: false,
    url: false,
    phone: false,
    email: false,
    currency_code: false,
});

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const supplierPayload = asRecord(safePayload.supplier);
    const tenantCurrency = asString(safePayload.tenantCurrencyCode, 'USD');
    const sectionRootsByKey = new Map();
    const purchaseOrderCreateRootEl = rootEl.querySelector('[data-purchase-order-create-root]');
    const purchaseOrderCreate = mountPurchaseOrderCreate(
        purchaseOrderCreateRootEl,
        safePayload.purchaseOrderCreate || {}
    );

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        sectionRootsByKey.set(sectionKey, sectionRootEl);
    });

    Alpine.data('purchasingSuppliersShow', () => ({
        supplier: supplierPayload,
        details: detailsFromSupplier(supplierPayload),
        lastSavedDetails: detailsFromSupplier(supplierPayload),
        detailsSavedFields: emptyDetailsSavedFields(),
        detailsSavedTimeouts: {},
        detailsSaving: false,
        detailsError: '',
        detailsFieldClass(field) {
            return this.detailsSavedFields[field] ? 'border-2 border-lime-400' : 'border-gray-300';
        },
        detailsPayload() {
            return {
                company_name: asString(this.details.company_name),
                url: asString(this.details.url),
                phone: asString(this.details.phone),
                email: asString(this.details.email),
                currency_code: asString(this.details.currency_code).toUpperCase(),
            };
        },
        hydrateSupplier(data) {
            const supplier = asRecord(data);

            this.supplier = {
                ...this.supplier,
                company_name: asString(supplier.company_name, this.supplier.company_name),
                url: supplier.url ?? '',
                phone: supplier.phone ?? '',
                email: supplier.email ?? '',
                currency_code: supplier.currency_code ?? '',
            };
            this.details = detailsFromSupplier(this.supplier);
            this.lastSavedDetails = detailsFromSupplier(this.supplier);
        },
        markDetailsFieldSaved(field) {
            if (!field || !Object.prototype.hasOwnProperty.call(this.detailsSavedFields, field)) {
                return;
            }

            window.clearTimeout(this.detailsSavedTimeouts[field]);
            this.detailsSavedFields[field] = true;
            this.detailsSavedTimeouts[field] = window.setTimeout(() => {
                this.detailsSavedFields[field] = false;
            }, 1000);
        },
        async saveDetails(field) {
            if (!this.supplier.can_manage || !this.supplier.update_url || this.detailsSaving) {
                return;
            }

            const payload = this.detailsPayload();
            const current = JSON.stringify(payload);
            const previous = JSON.stringify(this.lastSavedDetails);

            if (current === previous) {
                return;
            }

            const previousDetails = { ...this.lastSavedDetails };
            this.detailsSaving = true;
            this.detailsError = '';

            try {
                const response = await fetch(this.supplier.update_url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': asString(safePayload.csrfToken),
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.details = previousDetails;
                    this.detailsError = 'Unable to update supplier details.';
                    return;
                }

                this.hydrateSupplier(asRecord(data).data || {});
                this.markDetailsFieldSaved(field);
            } catch (error) {
                this.details = previousDetails;
                this.detailsError = 'Unable to update supplier details.';
            } finally {
                this.detailsSaving = false;
            }
        },
    }));

    const adaptersBySectionKey = {
        purchaseOrders: {
            normalizeRow: (record) => {
                const status = purchaseOrderStatusDisplay(record);

                return {
                    ...record,
                    display: {
                        poNumberText: record.po_number ? `PO #${record.po_number}` : 'Draft PO',
                        orderDateText: asString(record.order_date, 'No order date'),
                        supplierText: asString(record.supplier_name, 'Supplier not set'),
                        totalText: formatMoney(tenantCurrency, record.po_grand_total_cents),
                        statusText: status.text,
                        statusTone: status.tone,
                        showUrl: asString(record.show_url),
                    },
                };
            },
            async handleCreateAction({ action }) {
                if (action.handlerKey !== 'createSupplierPurchaseOrder') {
                    return;
                }

                const response = await fetch(asString(action.url), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': asString(action.csrfToken, asString(safePayload.csrfToken)),
                    },
                    body: JSON.stringify({
                        supplier_id: action.prefill?.supplier_id ?? safePayload.supplier?.id ?? null,
                    }),
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                if (data?.data?.show_url) {
                    window.location.assign(data.data.show_url);
                }
            },
        },
        supplierPackages: {
            normalizeRow(record) {
                const stateDisplay = supplierPackageStateDisplay(record);

                return {
                    ...record,
                    formValues: {
                        item_id: toStringValue(record.item_id),
                        pack_quantity: asString(record.pack_quantity),
                        pack_uom_id: toStringValue(record.pack_uom_id),
                        supplier_sku: asString(record.supplier_sku),
                        price_amount: asString(record.price_amount),
                    },
                    display: {
                        primaryText: asString(record.item_name, 'Unknown material'),
                        packageText: packageDisplayText(record),
                        skuText: asString(record.supplier_sku, '—'),
                        statusText: stateDisplay.text,
                        statusTone: stateDisplay.tone,
                        priceText: asString(record.current_price_display, 'No price'),
                        showUrl: asString(record.show_url),
                    },
                };
            },
            buildCreatePayload(form) {
                return buildSupplierPackagePayload(form);
            },
            buildUpdatePayload(form) {
                return buildSupplierPackagePayload(form);
            },
            async handleAction({ action, record }) {
                if (action.handlerKey !== 'purchase' || !purchaseOrderCreate) {
                    return;
                }

                const purchaseUrl = asString(record.purchase_url);

                if (purchaseUrl !== '') {
                    purchaseOrderCreate.config.storeUrl = purchaseUrl;
                }

                purchaseOrderCreate.openFromSupplierPackage({
                    supplier_id: record.supplier_id,
                    item_purchase_option_id: record.item_purchase_option_id ?? record.id,
                });
            },
        },
    };

    sectionRootsByKey.forEach((sectionRootEl, sectionKey) => {
        const section = safePayload.sections?.[sectionKey];

        if (!section) {
            return;
        }

        mountCrudSection(sectionRootEl, {
            section,
            adapters: adaptersBySectionKey[sectionKey] || {},
        });
    });
}
