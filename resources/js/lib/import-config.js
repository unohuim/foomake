const sanitizeRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const sanitizeLabel = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const sanitizeBoolean = (value, fallback = false) => (typeof value === 'boolean' ? value : fallback);

const sanitizeSourceDefinitions = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((source) => source && typeof source === 'object' && !Array.isArray(source))
        .map((source) => ({
            value: sanitizeLabel(source.value),
            label: sanitizeLabel(source.label),
            enabled: Boolean(source.enabled),
            connected: Boolean(source.connected),
            status: sanitizeLabel(source.status),
            status_label: sanitizeLabel(source.status_label),
        }))
        .filter((source) => source.value !== '' && source.label !== '');
};

const sanitizeUoms = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((uom) => uom && typeof uom === 'object' && !Array.isArray(uom))
        .map((uom) => ({
            id: uom.id ?? null,
            name: sanitizeLabel(uom.name),
            symbol: sanitizeLabel(uom.symbol),
        }))
        .filter((uom) => uom.id !== null);
};

const sanitizeBulkOption = (value, fallbackLabel = '', fallbackDefault = false) => {
    const option = sanitizeRecord(value);

    return {
        label: sanitizeLabel(option.label, fallbackLabel),
        default: sanitizeBoolean(option.default, fallbackDefault),
    };
};

const sanitizeBulkTextOption = (value, fallbackLabel = '', fallbackDefault = '') => {
    const option = sanitizeRecord(value);

    return {
        label: sanitizeLabel(option.label, fallbackLabel),
        default: sanitizeLabel(option.default, fallbackDefault),
    };
};

export function normalizeImportConfig(config) {
    if (!config || typeof config !== 'object' || Array.isArray(config)) {
        return {};
    }

    const rawEndpoints = sanitizeRecord(config.endpoints);
    const rawLabels = sanitizeRecord(config.labels);
    const rawPermissions = sanitizeRecord(config.permissions);
    const rawBulkOptions = sanitizeRecord(config.bulkOptions);
    const rawRowBehavior = sanitizeRecord(config.rowBehavior);

    return {
        resource: sanitizeLabel(config.resource),
        endpoints: {
            preview: sanitizeLabel(rawEndpoints.preview),
            store: sanitizeLabel(rawEndpoints.store),
        },
        labels: {
            title: sanitizeLabel(rawLabels.title, 'Import'),
            source: sanitizeLabel(rawLabels.source, 'Source'),
            submit: sanitizeLabel(rawLabels.submit, 'Import Selected'),
            previewSearch: sanitizeLabel(rawLabels.previewSearch, 'Search preview records'),
            loadingPreviewDefault: sanitizeLabel(rawLabels.loadingPreviewDefault, 'Loading preview...'),
            loadingPreviewFile: sanitizeLabel(rawLabels.loadingPreviewFile, 'Loading file preview...'),
            loadingPreviewExternal: sanitizeLabel(rawLabels.loadingPreviewExternal, 'Loading WooCommerce preview...'),
        },
        permissions: {
            canManageImports: Boolean(rawPermissions.canManageImports),
            canManageConnections: Boolean(rawPermissions.canManageConnections),
        },
        connectorsPageUrl: sanitizeLabel(config.connectorsPageUrl),
        sources: sanitizeSourceDefinitions(config.sources),
        uoms: sanitizeUoms(config.uoms),
        bulkOptions: {
            create_fulfillment_recipes: sanitizeBulkOption(
                rawBulkOptions.create_fulfillment_recipes,
                'Create fulfillment recipes',
                true
            ),
            import_all_as_manufacturable: sanitizeBulkOption(
                rawBulkOptions.import_all_as_manufacturable,
                'Import all selected as manufacturable',
                false
            ),
            import_all_as_purchasable: sanitizeBulkOption(
                rawBulkOptions.import_all_as_purchasable,
                'Import all selected as buyable/purchasable',
                false
            ),
            bulk_base_uom_id: sanitizeBulkTextOption(
                rawBulkOptions.bulk_base_uom_id,
                'Bulk base UoM',
                ''
            ),
        },
        rowBehavior: {
            hideDuplicatesByDefault: sanitizeBoolean(rawRowBehavior.hideDuplicatesByDefault, true),
            selectVisibleNonDuplicateRowsOnly: Boolean(rawRowBehavior.selectVisibleNonDuplicateRowsOnly),
            submitSelectedVisibleRowsOnly: sanitizeBoolean(rawRowBehavior.submitSelectedVisibleRowsOnly, true),
            duplicateFlagField: sanitizeLabel(rawRowBehavior.duplicateFlagField, 'is_duplicate'),
            selectionField: sanitizeLabel(rawRowBehavior.selectionField, 'selected'),
        },
    };
}

export function parseImportConfig(rootEl) {
    if (!rootEl || typeof rootEl.getAttribute !== 'function') {
        return {};
    }

    const rawConfig = rootEl.getAttribute('data-import-config');

    if (!rawConfig) {
        return {};
    }

    try {
        return normalizeImportConfig(JSON.parse(rawConfig));
    } catch (error) {
        return {};
    }
}
