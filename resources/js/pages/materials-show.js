import { mountCrudSection } from '../lib/js-crud-section';

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

const stateDisplay = (record) => {
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

const buildPayload = (form) => ({
    supplier_id: toStringValue(form.supplier_id),
    pack_quantity: asString(form.pack_quantity),
    pack_uom_id: toStringValue(form.pack_uom_id),
    supplier_sku: asString(form.supplier_sku),
    price_amount: asString(form.price_amount),
});

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const sectionConfig = safePayload.sections?.supplierPackages || null;
    const sectionRootEl = rootEl.querySelector('[data-js-crud-section-root]');

    mountCrudSection(sectionRootEl, {
        section: sectionConfig,
        adapters: {
            normalizeRow: (record) => {
                const state = stateDisplay(record);

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
            buildCreatePayload: (form) => buildPayload(form),
            buildUpdatePayload: (form) => buildPayload(form),
            handleAction: async () => {},
        },
    });
}
