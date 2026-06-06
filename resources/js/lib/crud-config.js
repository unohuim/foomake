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
    const rawMobileToggle = sanitizeRecord(rawMobileCard.toggle);
    const rawRowToggle = sanitizeRecord(config.rowToggle);
    const rawRowActions = sanitizeRecord(config.rowActions);
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
            update: typeof rawEndpoints.update === 'string' && rawEndpoints.update.trim() !== '' ? rawEndpoints.update : '',
            delete: typeof rawEndpoints.delete === 'string' && rawEndpoints.delete.trim() !== '' ? rawEndpoints.delete : '',
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
            canManageMaterials: Boolean(rawPermissions.canManageMaterials),
        },
        rowDisplay: {
            columns: columns.reduce((carry, column) => {
                const definition = sanitizeRecord(rowDisplayColumns[column]);

                carry[column] = {
                    kind: sanitizeLabel(definition.kind, 'text'),
                    urlExpression: sanitizeLabel(definition.urlExpression),
                    subtitleExpression: sanitizeLabel(definition.subtitleExpression),
                };

                return carry;
            }, {}),
        },
        mobileCard: {
            mediaExpression: sanitizeLabel(rawMobileCard.mediaExpression),
            titleExpression: sanitizeLabel(rawMobileCard.titleExpression, "record.name || '—'"),
            titleAsideExpression: sanitizeLabel(rawMobileCard.titleAsideExpression),
            titleAsidePlacement: sanitizeLabel(rawMobileCard.titleAsidePlacement),
            titleBadgesExpression: sanitizeLabel(rawMobileCard.titleBadgesExpression),
            subtitleExpression: sanitizeLabel(rawMobileCard.subtitleExpression),
            bodyExpression: sanitizeLabel(rawMobileCard.bodyExpression),
            layout: sanitizeLabel(rawMobileCard.layout),
            badgesExpression: sanitizeLabel(rawMobileCard.badgesExpression),
            iconBadgesExpression: sanitizeLabel(rawMobileCard.iconBadgesExpression),
            urlExpression: sanitizeLabel(rawMobileCard.urlExpression),
            showActions: rawMobileCard.showActions !== false,
            toggle: {
                name: sanitizeLabel(rawMobileToggle.name),
                checkedExpression: sanitizeLabel(rawMobileToggle.checkedExpression),
                disabledExpression: sanitizeLabel(rawMobileToggle.disabledExpression),
                showExpression: sanitizeLabel(rawMobileToggle.showExpression),
                eventName: sanitizeLabel(rawMobileToggle.eventName),
                handler: sanitizeLabel(rawMobileToggle.handler),
                ariaLabelExpression: sanitizeLabel(rawMobileToggle.ariaLabelExpression),
            },
        },
        rowActions: {
            mode: sanitizeLabel(rawRowActions.mode, 'menu'),
            icon: sanitizeLabel(rawRowActions.icon, 'ellipsis-vertical'),
            ariaLabel: sanitizeLabel(rawRowActions.ariaLabel, rawLabels.actionsAriaLabel || 'Actions'),
        },
        rowToggle: {
            label: sanitizeLabel(rawRowToggle.label),
            name: sanitizeLabel(rawRowToggle.name),
            checkedExpression: sanitizeLabel(rawRowToggle.checkedExpression),
            disabledExpression: sanitizeLabel(rawRowToggle.disabledExpression),
            showExpression: sanitizeLabel(rawRowToggle.showExpression),
            eventName: sanitizeLabel(rawRowToggle.eventName),
            handler: sanitizeLabel(rawRowToggle.handler),
            ariaLabelExpression: sanitizeLabel(rawRowToggle.ariaLabelExpression),
        },
        detailUrlTemplate: sanitizeLabel(config.detailUrlTemplate),
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
