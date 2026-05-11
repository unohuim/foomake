const sanitizeStringArray = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.filter((entry) => typeof entry === 'string' && entry.trim() !== '');
};

const sanitizeRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const sanitizeLabel = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const sanitizeActionDefinitions = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((action) => action && typeof action === 'object' && !Array.isArray(action))
        .map((action) => ({
            id: sanitizeLabel(action.id),
            label: sanitizeLabel(action.label),
            tone: sanitizeLabel(action.tone, 'default'),
        }))
        .filter((action) => action.id !== '' && action.label !== '');
};

export function normalizeCrudConfig(config) {
    if (!config || typeof config !== 'object' || Array.isArray(config)) {
        return {};
    }

    const rawEndpoints = sanitizeRecord(config.endpoints);
    const columns = sanitizeStringArray(config.columns);
    const headers = {};
    const rawHeaders = sanitizeRecord(config.headers);
    const sortable = sanitizeStringArray(config.sortable).filter((column) => columns.includes(column));
    const rawLabels = sanitizeRecord(config.labels);
    const rawPermissions = sanitizeRecord(config.permissions);
    const rawRowDisplay = sanitizeRecord(config.rowDisplay);
    const rawMobileCard = sanitizeRecord(config.mobileCard);
    const rowDisplayColumns = sanitizeRecord(rawRowDisplay.columns);

    columns.forEach((column) => {
        if (typeof rawHeaders[column] === 'string' && rawHeaders[column].trim() !== '') {
            headers[column] = rawHeaders[column];
        }
    });

    return {
        endpoints: {
            list: typeof rawEndpoints.list === 'string' && rawEndpoints.list.trim() !== '' ? rawEndpoints.list : '',
            export: typeof rawEndpoints.export === 'string' && rawEndpoints.export.trim() !== '' ? rawEndpoints.export : '',
            create: typeof rawEndpoints.create === 'string' && rawEndpoints.create.trim() !== '' ? rawEndpoints.create : '',
            importPreview: typeof rawEndpoints.importPreview === 'string' && rawEndpoints.importPreview.trim() !== ''
                ? rawEndpoints.importPreview
                : '',
            importStore: typeof rawEndpoints.importStore === 'string' && rawEndpoints.importStore.trim() !== ''
                ? rawEndpoints.importStore
                : '',
        },
        columns,
        headers,
        sortable,
        resource: sanitizeLabel(config.resource),
        labels: {
            searchPlaceholder: sanitizeLabel(rawLabels.searchPlaceholder, 'Search'),
            exportTitle: sanitizeLabel(rawLabels.exportTitle, 'Export'),
            exportAriaLabel: sanitizeLabel(rawLabels.exportAriaLabel, 'Export'),
            importTitle: sanitizeLabel(rawLabels.importTitle, 'Import'),
            importAriaLabel: sanitizeLabel(rawLabels.importAriaLabel, 'Import'),
            createTitle: sanitizeLabel(rawLabels.createTitle, 'Create'),
            createAriaLabel: sanitizeLabel(rawLabels.createAriaLabel, 'Create'),
            emptyState: sanitizeLabel(rawLabels.emptyState, 'No records found.'),
            actionsAriaLabel: sanitizeLabel(rawLabels.actionsAriaLabel, 'Actions'),
        },
        permissions: {
            showExport: Boolean(rawPermissions.showExport),
            showImport: Boolean(rawPermissions.showImport),
            showCreate: Boolean(rawPermissions.showCreate),
        },
        rowDisplay: {
            columns: columns.reduce((carry, column) => {
                const definition = sanitizeRecord(rowDisplayColumns[column]);

                carry[column] = {
                    kind: sanitizeLabel(definition.kind, 'text'),
                    urlExpression: sanitizeLabel(definition.urlExpression),
                };

                return carry;
            }, {}),
        },
        mobileCard: {
            mediaExpression: sanitizeLabel(rawMobileCard.mediaExpression),
            titleExpression: sanitizeLabel(rawMobileCard.titleExpression, "record.name || '—'"),
            subtitleExpression: sanitizeLabel(rawMobileCard.subtitleExpression),
            bodyExpression: sanitizeLabel(rawMobileCard.bodyExpression),
        },
        actions: sanitizeActionDefinitions(config.actions),
    };
}

export function parseCrudConfig(rootEl) {
    if (!rootEl || typeof rootEl.getAttribute !== 'function') {
        return {};
    }

    const rawConfig = rootEl.getAttribute('data-crud-config');

    if (!rawConfig) {
        return {};
    }

    try {
        return normalizeCrudConfig(JSON.parse(rawConfig));
    } catch (error) {
        return {};
    }
}
