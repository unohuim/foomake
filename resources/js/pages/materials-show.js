import { mountCrudSection } from '../lib/js-crud-section';
import { mountPurchaseOrderCreate } from '../lib/js-purchase-order-create';

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

const buildSupplierPackagePayload = (form) => ({
    supplier_id: toStringValue(form.supplier_id),
    pack_quantity: asString(form.pack_quantity),
    pack_uom_id: toStringValue(form.pack_uom_id),
    supplier_sku: asString(form.supplier_sku),
    price_amount: asString(form.price_amount),
});

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

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const tenantCurrency = asString(safePayload.tenantCurrency, 'USD');
    const purchaseOrderCreateRootEl = rootEl.querySelector('[data-purchase-order-create-root]');
    const purchaseOrderCreate = mountPurchaseOrderCreate(purchaseOrderCreateRootEl, safePayload.purchaseOrderCreate || {});

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
            handleAction: async () => {},
        },
        supplierPackages: {
            normalizeRow: (record) => {
                const state = supplierPackageStateDisplay(record);

                return {
                    ...record,
                    formValues: {
                        supplier_id: toStringValue(record.supplier_id),
                        pack_quantity: asString(record.pack_quantity),
                        pack_uom_id: toStringValue(record.pack_uom_id),
                        supplier_sku: asString(record.supplier_sku),
                        price_amount: asString(record.price_amount),
                    },
                    display: {
                        primaryText: asString(record.supplier_name, 'Unknown supplier'),
                        packageText: packageDisplayText(record),
                        skuText: asString(record.supplier_sku, '—'),
                        stateText: state.text,
                        stateTone: state.tone,
                        priceText: asString(record.current_price_display, 'No price'),
                    },
                };
            },
            buildCreatePayload: (form) => buildSupplierPackagePayload(form),
            buildUpdatePayload: (form) => buildSupplierPackagePayload(form),
            handleAction: async ({ action, record }) => {
                if (action.handlerKey === 'purchase' && purchaseOrderCreate) {
                    purchaseOrderCreate.openFromSupplierPackage({
                        supplier_id: record.supplier_id,
                        item_purchase_option_id: record.item_purchase_option_id ?? record.id,
                    });
                }
            },
        },
    };

    rootEl.querySelectorAll('[data-js-crud-section-root]').forEach((sectionRootEl) => {
        const sectionKey = sectionRootEl.dataset.sectionKey || '';
        const sectionConfig = safePayload.sections?.[sectionKey] || null;

        mountCrudSection(sectionRootEl, {
            section: sectionConfig,
            adapters: adaptersBySectionKey[sectionKey] || {},
        });
    });
}
