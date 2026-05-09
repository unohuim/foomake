const sanitizeStringArray = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.filter((entry) => typeof entry === 'string' && entry.trim() !== '');
};

export function normalizeCrudConfig(config) {
    if (!config || typeof config !== 'object' || Array.isArray(config)) {
        return {};
    }

    const rawEndpoints = config.endpoints && typeof config.endpoints === 'object' && !Array.isArray(config.endpoints)
        ? config.endpoints
        : {};
    const columns = sanitizeStringArray(config.columns);
    const headers = {};
    const rawHeaders = config.headers && typeof config.headers === 'object' && !Array.isArray(config.headers)
        ? config.headers
        : {};
    const sortable = sanitizeStringArray(config.sortable).filter((column) => columns.includes(column));

    columns.forEach((column) => {
        if (typeof rawHeaders[column] === 'string' && rawHeaders[column].trim() !== '') {
            headers[column] = rawHeaders[column];
        }
    });

    return {
        endpoints: {
            list: typeof rawEndpoints.list === 'string' && rawEndpoints.list.trim() !== '' ? rawEndpoints.list : '',
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
