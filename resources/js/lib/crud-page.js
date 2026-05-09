const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const recordDefinition = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const sanitizeExpression = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const sanitizeActions = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((action) => action && typeof action === 'object' && !Array.isArray(action))
        .map((action) => ({
            id: sanitizeExpression(action.id),
            label: sanitizeExpression(action.label),
            tone: sanitizeExpression(action.tone, 'default'),
            handler: sanitizeExpression(action.handler),
        }))
        .filter((action) => action.id !== '' && action.label !== '');
};

const normalizeRendererConfig = (config) => {
    const state = recordDefinition(config.state);
    const handlers = recordDefinition(config.handlers);
    const labels = recordDefinition(config.labels);
    const permissions = recordDefinition(config.permissions);
    const rowDisplay = recordDefinition(config.rowDisplay);
    const mobileCard = recordDefinition(config.mobileCard);

    return {
        columns: Array.isArray(config.columns) ? config.columns : [],
        headers: recordDefinition(config.headers),
        sortable: Array.isArray(config.sortable) ? config.sortable : [],
        labels: {
            searchPlaceholder: sanitizeExpression(labels.searchPlaceholder, 'Search'),
            importTitle: sanitizeExpression(labels.importTitle, 'Import'),
            importAriaLabel: sanitizeExpression(labels.importAriaLabel, 'Import'),
            createTitle: sanitizeExpression(labels.createTitle, 'Create'),
            createAriaLabel: sanitizeExpression(labels.createAriaLabel, 'Create'),
            emptyState: sanitizeExpression(labels.emptyState, 'No records found.'),
            actionsAriaLabel: sanitizeExpression(labels.actionsAriaLabel, 'Actions'),
        },
        permissions: {
            showImport: Boolean(permissions.showImport),
            showCreate: Boolean(permissions.showCreate),
        },
        state: {
            records: sanitizeExpression(state.records, 'records'),
            loading: sanitizeExpression(state.loading, 'isLoadingList'),
            error: sanitizeExpression(state.error, 'listError'),
            search: sanitizeExpression(state.search, 'search'),
            sort: sanitizeExpression(state.sort, 'sort'),
        },
        handlers: {
            searchInput: sanitizeExpression(handlers.searchInput, 'handleSearchInput()'),
            toggleSort: sanitizeExpression(handlers.toggleSort, 'toggleSort(column)'),
            create: sanitizeExpression(handlers.create),
            import: sanitizeExpression(handlers.import),
        },
        rowDisplay: {
            columns: recordDefinition(rowDisplay.columns),
            cellTextExpression: sanitizeExpression(rowDisplay.cellTextExpression, 'record[column] || "—"'),
        },
        mobileCard: {
            mediaExpression: sanitizeExpression(mobileCard.mediaExpression),
            titleExpression: sanitizeExpression(mobileCard.titleExpression, 'record.name || "—"'),
            subtitleExpression: sanitizeExpression(mobileCard.subtitleExpression),
            bodyExpression: sanitizeExpression(mobileCard.bodyExpression),
        },
        actions: sanitizeActions(config.actions),
    };
};

const sortIconsMarkup = (sortExpression) => `
    <span x-show="${sortExpression}.column === column">
        <svg x-show="${sortExpression}.direction === 'desc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
        </svg>
        <svg x-show="${sortExpression}.direction === 'asc'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
        </svg>
    </span>
`;

const renderToolbar = (config, variant) => {
    const isMobile = variant === 'mobile';
    const wrapperClasses = isMobile ? 'md:hidden' : 'hidden md:block';
    const toolbarClasses = isMobile
        ? 'sticky top-0 z-10 border-b border-gray-100 bg-white p-4'
        : 'sticky top-0 z-20 border-b border-gray-100 bg-white px-6 py-4';
    const buttonClasses = isMobile
        ? 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900'
        : 'inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900';

    return `
        <div class="${wrapperClasses}" ${isMobile ? 'data-crud-toolbar-mobile' : 'data-crud-toolbar-desktop'}>
            <div class="${toolbarClasses}">
                <div class="flex items-center gap-3" data-crud-toolbar>
                    <div class="relative flex-1" data-crud-toolbar-search>
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.95 7.95 0 1 0 5.4 5.4a7.95 7.95 0 0 0 11.25 11.25Z" />
                            </svg>
                        </div>
                        <input
                            type="search"
                            class="block w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="${escapeHtml(config.labels.searchPlaceholder)}"
                            aria-label="${escapeHtml(config.labels.searchPlaceholder)}"
                            x-model="${config.state.search}"
                            x-on:input.debounce.200ms="${config.handlers.searchInput}"
                        />
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-opacity duration-150"
                            :class="${config.state.loading} ? 'opacity-100' : 'opacity-0'"
                            aria-hidden="true"
                        >
                            <svg class="h-4 w-4 animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </div>
                    </div>

                    ${config.permissions.showImport ? `
                        <button
                            type="button"
                            class="${buttonClasses}"
                            title="${escapeHtml(config.labels.importTitle)}"
                            aria-label="${escapeHtml(config.labels.importAriaLabel)}"
                            data-crud-toolbar-import-button
                            x-on:click="${config.handlers.import}"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5M12 16.5l-4.5-4.5M3.75 19.5h16.5" />
                            </svg>
                        </button>
                    ` : ''}

                    ${config.permissions.showCreate ? `
                        <button
                            type="button"
                            class="${buttonClasses}"
                            title="${escapeHtml(config.labels.createTitle)}"
                            aria-label="${escapeHtml(config.labels.createAriaLabel)}"
                            data-crud-toolbar-create-button
                            x-on:click="${config.handlers.create}"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
};

const renderCellContent = (config, column) => {
    const definition = recordDefinition(config.rowDisplay.columns[column]);
    const kind = sanitizeExpression(definition.kind, 'text');
    const urlExpression = sanitizeExpression(definition.urlExpression);
    const cellTextExpression = config.rowDisplay.cellTextExpression;

    if (kind === 'product-name') {
        return `
            <template x-if="column === '${column}'">
                <div class="flex items-center gap-3 text-gray-900">
                    <template x-if="record.image_url">
                        <img
                            :src="record.image_url"
                            alt=""
                            class="h-10 w-10 rounded-md object-cover"
                        >
                    </template>
                    <template x-if="!record.image_url">
                        <div class="h-10 w-10 rounded-md bg-gray-100"></div>
                    </template>
                    <span class="font-medium" x-text="${cellTextExpression}"></span>
                </div>
            </template>
        `;
    }

    if (kind === 'linked-text' && urlExpression !== '') {
        return `
            <template x-if="column === '${column}'">
                <a class="font-medium text-blue-600 hover:text-blue-500" :href="${urlExpression}" x-text="${cellTextExpression}"></a>
            </template>
        `;
    }

    return `
        <template x-if="column === '${column}'">
            <span x-text="${cellTextExpression}"></span>
        </template>
    `;
};

const renderDesktopTable = (config) => {
    const colspan = String(config.columns.length + 1);
    const columnsMarkup = config.columns.map((column) => renderCellContent(config, column)).join('');

    return `
        <div class="hidden md:block">
            ${renderToolbar(config, 'desktop')}
            <div class="max-h-[36rem] overflow-y-auto" data-crud-records-scroll>
                <table class="min-w-full divide-y divide-gray-100" data-crud-table>
                    <thead class="sticky top-0 z-10 bg-white">
                        <tr>
                            <template x-for="column in columns" :key="\`header-\${column}\`">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <template x-if="isSortableColumn(column)">
                                        <button type="button" class="inline-flex items-center gap-2 text-left" x-on:click="${config.handlers.toggleSort}">
                                            <span x-text="columnHeader(column)"></span>
                                            ${sortIconsMarkup(config.state.sort)}
                                        </button>
                                    </template>
                                    <template x-if="!isSortableColumn(column)">
                                        <span x-text="columnHeader(column)"></span>
                                    </template>
                                </th>
                            </template>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 bg-white transition-opacity duration-150"
                        :class="${config.state.loading} ? 'opacity-80' : 'opacity-100'"
                    >
                        <tr x-show="!${config.state.loading} && ${config.state.records}.length === 0" data-crud-empty-state>
                            <td colspan="${colspan}" class="px-6 py-10 text-center text-sm text-gray-500">${escapeHtml(config.labels.emptyState)}</td>
                        </tr>

                        <template x-for="record in ${config.state.records}" :key="record.id">
                            <tr class="transition hover:bg-gray-50">
                                <template x-for="column in columns" :key="\`cell-\${record.id}-\${column}\`">
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        ${columnsMarkup}
                                    </td>
                                </template>
                                <td class="px-6 py-4 text-right text-sm">
                                    ${renderActionCell(config, 'ml-auto')}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    `;
};

const renderActionItems = (config) => {
    if (config.actions.length === 0) {
        return '';
    }

    return config.actions.map((action) => {
        const toneClasses = action.tone === 'warning'
            ? 'flex w-full items-center px-3 py-2 text-left text-sm text-yellow-700 transition hover:bg-yellow-50 hover:text-yellow-800'
            : 'flex w-full items-center px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50';

        return `
            <button
                type="button"
                class="${toneClasses}"
                data-crud-action-item-${escapeHtml(action.id)}
                role="menuitem"
                x-on:click="open = false; ${action.handler}"
            >
                ${escapeHtml(action.label)}
            </button>
        `;
    }).join('');
};

function renderActionCell(config, wrapperClass = '') {
    const hasMenu = config.actions.length > 0;
    const containerClasses = ['relative', 'inline-flex', wrapperClass].filter(Boolean).join(' ');

    return `
        <div
            class="${containerClasses}"
            x-data="{ open: false }"
            data-crud-action-cell
            x-on:keydown.escape.window="open = false"
            x-on:click.outside="open = false"
        >
            <button
                type="button"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                aria-label="${escapeHtml(config.labels.actionsAriaLabel)}"
                ${hasMenu ? `aria-haspopup="menu" x-bind:aria-expanded="open ? 'true' : 'false'" x-on:click="open = !open"` : `aria-expanded="false" x-on:click.prevent`}
                data-crud-action-trigger
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 6a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                </svg>
            </button>

            ${hasMenu ? `
                <div
                    class="absolute right-0 z-20 mt-2 w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg"
                    x-show="open"
                    x-cloak
                    data-crud-action-menu
                    role="menu"
                >
                    ${renderActionItems(config)}
                </div>
            ` : ''}
        </div>
    `;
}

const renderMobileCards = (config) => {
    const mediaExpression = config.mobileCard.mediaExpression;
    const subtitleExpression = config.mobileCard.subtitleExpression;
    const bodyExpression = config.mobileCard.bodyExpression;

    return `
        <div class="md:hidden" data-crud-mobile-cards>
            ${renderToolbar(config, 'mobile')}

            <div class="max-h-[36rem] overflow-y-auto p-4" data-crud-records-scroll>
                <div class="space-y-3 transition-opacity duration-150" :class="${config.state.loading} ? 'opacity-80' : 'opacity-100'">
                    <div
                        x-show="!${config.state.loading} && ${config.state.records}.length === 0"
                        class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500"
                        data-crud-empty-state
                    >
                        ${escapeHtml(config.labels.emptyState)}
                    </div>

                    <template x-for="record in ${config.state.records}" :key="\`mobile-\${record.id}\`">
                        <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-3">
                                ${mediaExpression !== '' ? `
                                    <template x-if="${mediaExpression}">
                                        <img
                                            :src="${mediaExpression}"
                                            alt=""
                                            class="h-14 w-14 rounded-md object-cover"
                                        >
                                    </template>
                                    <template x-if="!(${mediaExpression})">
                                        <div class="h-14 w-14 rounded-md bg-gray-100"></div>
                                    </template>
                                ` : ''}

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900" x-text="${config.mobileCard.titleExpression}"></p>
                                            ${subtitleExpression !== '' ? `<p class="mt-1 text-sm text-gray-600" x-text="${subtitleExpression}"></p>` : ''}
                                        </div>

                                        ${renderActionCell(config)}
                                    </div>

                                    ${bodyExpression !== '' ? `<p class="mt-3 text-sm text-gray-700" x-text="${bodyExpression}"></p>` : ''}
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    `;
};

export function mountCrudRenderer(targetEl, config) {
    if (!targetEl) {
        return;
    }

    const normalized = normalizeRendererConfig(config);

    targetEl.innerHTML = `
        <div class="rounded-lg border border-gray-100 bg-white shadow-sm" data-crud-renderer>
            <div class="border-b border-gray-100 px-6 py-4">
                <p class="text-sm text-red-600" x-show="${normalized.state.error}" x-text="${normalized.state.error}"></p>
            </div>
            ${renderMobileCards(normalized)}
            ${renderDesktopTable(normalized)}
        </div>
    `;
}
