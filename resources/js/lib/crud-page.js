import { renderToggle } from '../components/toggle';

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
            showExpression: sanitizeExpression(action.showExpression, 'true'),
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
    const mobileToggle = recordDefinition(mobileCard.toggle);
    const rowActions = recordDefinition(config.rowActions);
    const rowToggle = recordDefinition(config.rowToggle);

    return {
        columns: Array.isArray(config.columns) ? config.columns : [],
        headers: recordDefinition(config.headers),
        sortable: Array.isArray(config.sortable) ? config.sortable : [],
        labels: {
            searchPlaceholder: sanitizeExpression(labels.searchPlaceholder, 'Search'),
            exportTitle: sanitizeExpression(labels.exportTitle, 'Export'),
            exportAriaLabel: sanitizeExpression(labels.exportAriaLabel, 'Export'),
            importTitle: sanitizeExpression(labels.importTitle, 'Import'),
            importAriaLabel: sanitizeExpression(labels.importAriaLabel, 'Import'),
            createTitle: sanitizeExpression(labels.createTitle, 'Create'),
            createAriaLabel: sanitizeExpression(labels.createAriaLabel, 'Create'),
            emptyState: sanitizeExpression(labels.emptyState, 'No records found.'),
            actionsAriaLabel: sanitizeExpression(labels.actionsAriaLabel, 'Actions'),
        },
        permissions: {
            showExport: Boolean(permissions.showExport),
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
            export: sanitizeExpression(handlers.export),
            create: sanitizeExpression(handlers.create),
            import: sanitizeExpression(handlers.import),
        },
        rowDisplay: {
            columns: recordDefinition(rowDisplay.columns),
            cellTextExpression: sanitizeExpression(rowDisplay.cellTextExpression, 'record[column] || "—"'),
        },
        mobileCard: {
            mediaExpression: sanitizeExpression(mobileCard.mediaExpression),
            titleExpression: sanitizeExpression(mobileCard.titleExpression, "record.name || '—'"),
            titleAsideExpression: sanitizeExpression(mobileCard.titleAsideExpression),
            subtitleExpression: sanitizeExpression(mobileCard.subtitleExpression),
            bodyExpression: sanitizeExpression(mobileCard.bodyExpression),
            layout: sanitizeExpression(mobileCard.layout, 'card'),
            badgesExpression: sanitizeExpression(mobileCard.badgesExpression),
            iconBadgesExpression: sanitizeExpression(mobileCard.iconBadgesExpression),
            urlExpression: sanitizeExpression(mobileCard.urlExpression),
            showActions: mobileCard.showActions !== false,
            toggle: {
                name: sanitizeExpression(mobileToggle.name),
                checkedExpression: sanitizeExpression(mobileToggle.checkedExpression),
                disabledExpression: sanitizeExpression(mobileToggle.disabledExpression, 'false'),
                showExpression: sanitizeExpression(mobileToggle.showExpression, 'true'),
                eventName: sanitizeExpression(mobileToggle.eventName, 'ui-toggle:changed'),
                handler: sanitizeExpression(mobileToggle.handler),
                ariaLabelExpression: sanitizeExpression(mobileToggle.ariaLabelExpression),
            },
        },
        rowActions: {
            mode: sanitizeExpression(rowActions.mode, 'menu'),
            icon: sanitizeExpression(rowActions.icon, 'ellipsis-vertical'),
            ariaLabel: sanitizeExpression(rowActions.ariaLabel, labels.actionsAriaLabel || 'Actions'),
        },
        rowToggle: {
            label: sanitizeExpression(rowToggle.label),
            name: sanitizeExpression(rowToggle.name),
            checkedExpression: sanitizeExpression(rowToggle.checkedExpression),
            disabledExpression: sanitizeExpression(rowToggle.disabledExpression, 'false'),
            showExpression: sanitizeExpression(rowToggle.showExpression, 'true'),
            eventName: sanitizeExpression(rowToggle.eventName, 'ui-toggle:changed'),
            handler: sanitizeExpression(rowToggle.handler),
            ariaLabelExpression: sanitizeExpression(rowToggle.ariaLabelExpression),
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
    const toolbarClasses = isMobile
        ? 'border-b border-gray-100 bg-white p-4'
        : 'border-b border-gray-100 bg-white px-6 py-4';
    const buttonClasses = isMobile
        ? 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900'
        : 'inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900';

    return `
        <div class="${toolbarClasses}" ${isMobile ? 'data-crud-toolbar-mobile' : 'data-crud-toolbar-desktop'}>
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

                ${config.permissions.showExport ? `
                    <button
                        type="button"
                        class="${buttonClasses}"
                        title="${escapeHtml(config.labels.exportTitle)}"
                        aria-label="${escapeHtml(config.labels.exportAriaLabel)}"
                        data-crud-toolbar-export-button
                        x-on:click="${config.handlers.export}"
                    >
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5A2.25 2.25 0 0 0 5.25 10.5v9A2.25 2.25 0 0 0 7.5 21.75h9A2.25 2.25 0 0 0 18.75 19.5v-9A2.25 2.25 0 0 0 16.5 8.25H15" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15V3m0 12 3.75-3.75M12 15l-3.75-3.75" />
                        </svg>
                    </button>
                ` : ''}

                ${config.permissions.showImport ? `
                    <button
                        type="button"
                        class="${buttonClasses}"
                        title="${escapeHtml(config.labels.importTitle)}"
                        aria-label="${escapeHtml(config.labels.importAriaLabel)}"
                        data-crud-toolbar-import-button
                        x-on:click="${config.handlers.import}"
                    >
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V16.5" />
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
    `;
};

const renderCellContent = (config, column) => {
    const definition = recordDefinition(config.rowDisplay.columns[column]);
    const kind = sanitizeExpression(definition.kind, 'text');
    const urlExpression = sanitizeExpression(definition.urlExpression);
    const subtitleExpression = sanitizeExpression(definition.subtitleExpression);
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

    if (kind === 'stacked-text') {
        if (urlExpression !== '') {
            return `
                <template x-if="column === '${column}'">
                    <div class="min-w-0">
                        <a class="font-medium text-blue-600 hover:text-blue-500" :href="${urlExpression}" x-text="${cellTextExpression}"></a>
                        ${subtitleExpression !== '' ? `
                            <p class="mt-1 truncate text-sm text-gray-500" x-show="Boolean(${subtitleExpression})" x-text="${subtitleExpression}"></p>
                        ` : ''}
                    </div>
                </template>
            `;
        }

        return `
            <template x-if="column === '${column}'">
                <div class="min-w-0">
                    <div class="font-medium text-gray-900" x-text="${cellTextExpression}"></div>
                    ${subtitleExpression !== '' ? `
                        <p class="mt-1 truncate text-sm text-gray-500" x-show="Boolean(${subtitleExpression})" x-text="${subtitleExpression}"></p>
                    ` : ''}
                </div>
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
    const hasActions = config.actions.length > 0;
    const hasRowToggle = config.rowToggle.name !== '' && config.rowToggle.checkedExpression !== '';
    const rowToggleMarkup = hasRowToggle ? renderToggle(config.rowToggle) : '';
    const colspan = String(config.columns.length + (hasActions || hasRowToggle ? 1 : 0));
    const columnsMarkup = config.columns.map((column) => renderCellContent(config, column)).join('');

    return `
        <div class="hidden h-full min-h-0 md:block">
            <div class="flex h-full min-h-0 flex-col">
                ${renderToolbar(config, 'desktop')}
                <div class="min-h-0 flex-1 overflow-y-auto" data-crud-records-scroll>
                <table class="min-w-full divide-y divide-gray-100" data-crud-table>
                    <thead class="bg-white">
                        <tr>
                            <template x-for="column in columns" :key="\`header-\${column}\`">
                                <th class="sticky top-0 z-10 bg-white px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
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
                            ${hasActions || hasRowToggle ? `
                                <th class="sticky top-0 z-10 bg-white px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <span>${hasRowToggle ? escapeHtml(config.rowToggle.label || 'Active') : '<span class="sr-only">Actions</span>'}</span>
                                </th>
                            ` : ''}
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
                                ${hasActions ? `
                                    <td class="px-6 py-4 text-right text-sm">
                                        ${renderActionCell(config, 'ml-auto')}
                                    </td>
                                ` : ''}
                                ${!hasActions && hasRowToggle ? `
                                    <td class="px-6 py-4 text-right text-sm">
                                        <div class="flex justify-end">
                                            ${rowToggleMarkup}
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
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
                x-show="${action.showExpression}"
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
    const directIconButton = config.rowActions.mode === 'icon-button' && config.actions.length === 1;

    if (directIconButton) {
        const directAction = config.actions[0];

        return `
            <div class="${containerClasses}" data-crud-action-cell>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-300 text-gray-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                    aria-label="${escapeHtml(config.rowActions.ariaLabel)}"
                    data-crud-direct-action-trigger
                    x-on:click="${directAction.handler}"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `;
    }

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
    const titleAsideExpression = config.mobileCard.titleAsideExpression;
    const subtitleExpression = config.mobileCard.subtitleExpression;
    const bodyExpression = config.mobileCard.bodyExpression;
    const badgesExpression = config.mobileCard.badgesExpression;
    const iconBadgesExpression = config.mobileCard.iconBadgesExpression;
    const urlExpression = config.mobileCard.urlExpression;
    const isFlushStacked = config.mobileCard.layout === 'flush-stacked';
    const hasActions = config.mobileCard.showActions && config.actions.length > 0;
    const hasToggle = config.mobileCard.toggle.name !== ''
        && config.mobileCard.toggle.checkedExpression !== '';
    const toggleMarkup = hasToggle ? renderToggle(config.mobileCard.toggle) : '';
    const rowClickAttributes = urlExpression !== ''
        ? `role="link" tabindex="0" x-on:click="if (${urlExpression}) { window.location.assign(${urlExpression}); }" x-on:keydown.enter.prevent="if (${urlExpression}) { window.location.assign(${urlExpression}); }" x-on:keydown.space.prevent="if (${urlExpression}) { window.location.assign(${urlExpression}); }"`
        : '';
    const rowClickableClass = urlExpression !== '' ? ' cursor-pointer transition hover:border-gray-200 hover:bg-gray-50' : '';
    const scrollPaddingClass = isFlushStacked ? 'p-0' : 'p-4';
    const listSpacingClass = isFlushStacked ? 'border-t border-gray-300 space-y-0' : 'space-y-3';
    const rowClass = isFlushStacked
        ? `border-b border-gray-300 bg-white px-4 py-2 shadow-sm${rowClickableClass}`
        : `rounded-lg border border-gray-100 bg-white p-4 shadow-sm${rowClickableClass}`;
    const iconBadgeMarkup = `
        <template x-if="badge.icon === 'rectangle-group'">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6A1.125 1.125 0 0 1 2.25 10.875v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-1.5Z" />
            </svg>
        </template>
        <template x-if="badge.icon === 'credit-card'">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
        </template>
        <template x-if="badge.icon === 'shopping-cart'">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437m0 0L7.5 14.25h11.25l2.25-9H5.106Zm0 0L4.5 3m3 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm11.25 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
            </svg>
        </template>
        <template x-if="badge.icon === 'cog'">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.198.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a7.723 7.723 0 0 1 0 .255c-.007.379.138.732.43.992l1.005.827c.424.35.534.955.26 1.431l-1.298 2.247a1.125 1.125 0 0 1-1.369.49l-1.217-.456c-.355-.133-.75-.074-1.076.124a6.573 6.573 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.397-1.11-.94l-.213-1.281c-.063-.374-.313-.686-.645-.87a6.52 6.52 0 0 1-.22-.127c-.324-.198-.72-.257-1.075-.124l-1.217.456a1.125 1.125 0 0 1-1.37-.49l-1.296-2.247a1.125 1.125 0 0 1 .26-1.431l1.003-.827c.293-.24.438-.613.431-.992a6.932 6.932 0 0 1 0-.255c.007-.379-.138-.732-.43-.992l-1.005-.827a1.125 1.125 0 0 1-.26-1.431l1.298-2.247a1.125 1.125 0 0 1 1.369-.49l1.217.456c.355.133.75.074 1.076-.124.072-.044.146-.086.22-.128.331-.183.581-.495.644-.869l.213-1.281Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
        </template>
    `;

    return `
        <div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>
            <div class="flex h-full min-h-0 flex-col">
                ${renderToolbar(config, 'mobile')}
                <div class="min-h-0 flex-1 overflow-y-auto ${scrollPaddingClass}" data-crud-records-scroll>
                <div class="${listSpacingClass} transition-opacity duration-150" :class="${config.state.loading} ? 'opacity-80' : 'opacity-100'">
                    <div
                        x-show="!${config.state.loading} && ${config.state.records}.length === 0"
                        class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500"
                        data-crud-empty-state
                    >
                        ${escapeHtml(config.labels.emptyState)}
                    </div>

                    <template x-for="record in ${config.state.records}" :key="\`mobile-\${record.id}\`">
                        <div class="${rowClass}" data-crud-mobile-row ${rowClickAttributes}>
                            <div class="flex items-stretch gap-3">
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

                                <div class="min-w-0 flex flex-1 flex-col">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div class="min-w-0 flex-1 overflow-hidden">
                                            <div class="flex min-w-0 items-baseline gap-6">
                                                <p class="block truncate text-sm font-medium text-gray-900" x-text="${config.mobileCard.titleExpression}"></p>
                                                ${titleAsideExpression !== '' ? `<p class="shrink-0 text-xs font-medium text-gray-500" x-text="${titleAsideExpression}"></p>` : ''}
                                            </div>
                                            ${subtitleExpression !== '' ? `<p class="mt-1 text-sm text-gray-600" x-text="${subtitleExpression}"></p>` : ''}
                                        </div>

                                        ${hasActions ? renderActionCell(config, 'ml-auto shrink-0') : ''}
                                    </div>

                                    ${badgesExpression !== '' ? `
                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                            <template x-for="badge in ${badgesExpression}" :key="\`mobile-\${record.id}-badge-\${badge}\`">
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[0.65rem] font-medium text-gray-700" x-text="badge"></span>
                                            </template>
                                        </div>
                                    ` : ''}
                                    ${iconBadgesExpression !== '' ? `
                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                            <template x-for="badge in ${iconBadgesExpression}" :key="\`mobile-\${record.id}-icon-badge-\${badge.icon}\`">
                                                <span
                                                    class="inline-flex items-center justify-center text-blue-600"
                                                    x-bind:title="badge.label"
                                                    x-bind:aria-label="badge.label"
                                                >
                                                    ${iconBadgeMarkup}
                                                </span>
                                            </template>
                                        </div>
                                    ` : ''}
                                    ${bodyExpression !== '' ? `<p class="mt-3 text-sm text-gray-700" x-text="${bodyExpression}"></p>` : ''}
                                </div>
                                ${hasToggle ? `<div class="ml-auto flex shrink-0 items-center self-stretch">${toggleMarkup}</div>` : ''}
                            </div>
                        </div>
                    </template>
                </div>
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
        <div class="flex h-full min-h-0 flex-col overflow-hidden border border-gray-100 bg-white shadow-sm" data-crud-renderer>
            <div class="border-b border-gray-100 px-6 py-4">
                <p class="text-sm text-red-600" x-show="${normalized.state.error}" x-text="${normalized.state.error}"></p>
            </div>
            ${renderMobileCards(normalized)}
            ${renderDesktopTable(normalized)}
        </div>
    `;
}
