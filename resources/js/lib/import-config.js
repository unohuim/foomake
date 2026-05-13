const sanitizeRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const sanitizeLabel = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const sanitizeBoolean = (value, fallback = false) => (typeof value === 'boolean' ? value : fallback);

const sanitizeExpression = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const sanitizeStringList = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((entry) => typeof entry === 'string')
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '');
};

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
        enabled: Object.keys(option).length > 0,
        label: sanitizeLabel(option.label, fallbackLabel),
        default: sanitizeBoolean(option.default, fallbackDefault),
    };
};

const sanitizeBulkTextOption = (value, fallbackLabel = '', fallbackDefault = '') => {
    const option = sanitizeRecord(value);

    return {
        enabled: Object.keys(option).length > 0,
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
    const rawMessages = sanitizeRecord(config.messages);
    const rawPermissions = sanitizeRecord(config.permissions);
    const rawBulkOptions = sanitizeRecord(config.bulkOptions);
    const rawRowBehavior = sanitizeRecord(config.rowBehavior);
    const rawPreviewDisplay = sanitizeRecord(config.previewDisplay);

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
            emptyStateDescription: sanitizeLabel(
                rawLabels.emptyStateDescription,
                'Select a WooCommerce connection or switch to file upload to start loading an import preview.'
            ),
            noBulkOptions: sanitizeLabel(rawLabels.noBulkOptions, 'No additional import options are available for this resource.'),
            previewDescription: sanitizeLabel(
                rawLabels.previewDescription,
                'Review the import preview before confirming the selected records.'
            ),
        },
        permissions: {
            canManageImports: Boolean(rawPermissions.canManageImports),
            canManageConnections: Boolean(rawPermissions.canManageConnections),
        },
        messages: {
            previewUnavailable: sanitizeLabel(rawMessages.previewUnavailable),
            importUnavailable: sanitizeLabel(rawMessages.importUnavailable),
            fileReadError: sanitizeLabel(rawMessages.fileReadError),
            filePreviewUnavailable: sanitizeLabel(rawMessages.filePreviewUnavailable),
            emptyFileRows: sanitizeLabel(rawMessages.emptyFileRows),
            missingFileHeaders: sanitizeLabel(rawMessages.missingFileHeaders),
            emptySelection: sanitizeLabel(rawMessages.emptySelection),
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
            hideDuplicatesByDefault: Boolean(rawRowBehavior.hideDuplicatesByDefault),
            selectVisibleNonDuplicateRowsOnly: Boolean(rawRowBehavior.selectVisibleNonDuplicateRowsOnly),
            submitSelectedVisibleRowsOnly: Boolean(rawRowBehavior.submitSelectedVisibleRowsOnly),
            duplicateFlagField: sanitizeLabel(rawRowBehavior.duplicateFlagField, 'is_duplicate'),
            selectionField: sanitizeLabel(rawRowBehavior.selectionField, 'selected'),
        },
        previewDisplay: {
            titleExpression: sanitizeExpression(rawPreviewDisplay.titleExpression, "row.name || '—'"),
            subtitleExpression: sanitizeExpression(rawPreviewDisplay.subtitleExpression),
            bodyExpression: sanitizeExpression(rawPreviewDisplay.bodyExpression),
            searchExpressions: sanitizeStringList(rawPreviewDisplay.searchExpressions),
            errorFields: sanitizeStringList(rawPreviewDisplay.errorFields),
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
