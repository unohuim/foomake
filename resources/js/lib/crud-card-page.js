import { renderToggle } from '../components/toggle';

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const escapeAttributeExpression = (value) => escapeHtml(value);

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

const normalizeCardRendererConfig = (config) => {
    const state = recordDefinition(config.state);
    const handlers = recordDefinition(config.handlers);
    const labels = recordDefinition(config.labels);
    const permissions = recordDefinition(config.permissions);
    const mobileCard = recordDefinition(config.mobileCard);
    const desktopCard = recordDefinition(config.desktopCard);
    const desktopList = recordDefinition(config.desktopList);
    const mobileToggle = recordDefinition(mobileCard.toggle);
    const rowToggle = recordDefinition(config.rowToggle);

    return {
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
        },
        handlers: {
            searchInput: sanitizeExpression(handlers.searchInput, 'handleSearchInput()'),
            export: sanitizeExpression(handlers.export),
            create: sanitizeExpression(handlers.create),
            import: sanitizeExpression(handlers.import),
        },
        mobileCard: {
            titleExpression: sanitizeExpression(mobileCard.titleExpression, "record.name || '—'"),
            titleAsideExpression: sanitizeExpression(mobileCard.titleAsideExpression),
            titleAsideStatsExpression: sanitizeExpression(mobileCard.titleAsideStatsExpression),
            titleAsideSecondaryExpression: sanitizeExpression(mobileCard.titleAsideSecondaryExpression),
            titleAsidePlacement: sanitizeExpression(mobileCard.titleAsidePlacement),
            titleBadgesExpression: sanitizeExpression(mobileCard.titleBadgesExpression),
            subtitleExpression: sanitizeExpression(mobileCard.subtitleExpression),
            detailRowsExpression: sanitizeExpression(mobileCard.detailRowsExpression),
            bodyExpression: sanitizeExpression(mobileCard.bodyExpression),
            showBody: mobileCard.showBody !== false,
            badgesExpression: sanitizeExpression(mobileCard.badgesExpression),
            iconBadgesExpression: sanitizeExpression(mobileCard.iconBadgesExpression),
            centerStatsExpression: sanitizeExpression(mobileCard.centerStatsExpression),
            iconRowStatsExpression: sanitizeExpression(mobileCard.iconRowStatsExpression),
            urlExpression: sanitizeExpression(mobileCard.urlExpression),
            showActions: mobileCard.showActions !== false,
            showToggle: mobileCard.showToggle !== false,
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
        desktopCard: {
            titleExpression: sanitizeExpression(desktopCard.titleExpression, sanitizeExpression(mobileCard.titleExpression, "record.name || '—'")),
            titleAsideExpression: sanitizeExpression(desktopCard.titleAsideExpression, sanitizeExpression(mobileCard.titleAsideExpression)),
            titleAsideSecondaryExpression: sanitizeExpression(desktopCard.titleAsideSecondaryExpression),
            titleBadgesExpression: sanitizeExpression(desktopCard.titleBadgesExpression, sanitizeExpression(mobileCard.titleBadgesExpression)),
            subtitleExpression: sanitizeExpression(desktopCard.subtitleExpression, sanitizeExpression(mobileCard.subtitleExpression)),
            subtitleAsideExpression: sanitizeExpression(desktopCard.subtitleAsideExpression),
            detailRowsExpression: sanitizeExpression(desktopCard.detailRowsExpression, sanitizeExpression(mobileCard.detailRowsExpression)),
            bodyExpression: sanitizeExpression(desktopCard.bodyExpression, sanitizeExpression(mobileCard.bodyExpression)),
            showBody: desktopCard.showBody !== false,
            compact: Boolean(desktopCard.compact),
            badgesExpression: sanitizeExpression(desktopCard.badgesExpression, sanitizeExpression(mobileCard.badgesExpression)),
            iconBadgesExpression: sanitizeExpression(desktopCard.iconBadgesExpression),
            statsExpression: sanitizeExpression(desktopCard.statsExpression),
            urlExpression: sanitizeExpression(desktopCard.urlExpression, sanitizeExpression(mobileCard.urlExpression)),
            showActions: desktopCard.showActions !== false,
        },
        desktopList: {
            enabled: Boolean(desktopList.enabled),
            titleExpression: sanitizeExpression(
                desktopList.titleExpression,
                sanitizeExpression(desktopCard.titleExpression, sanitizeExpression(mobileCard.titleExpression, "record.name || '—'"))
            ),
            subtitleExpression: sanitizeExpression(
                desktopList.subtitleExpression,
                sanitizeExpression(desktopCard.subtitleExpression, sanitizeExpression(mobileCard.subtitleExpression))
            ),
            metaExpression: sanitizeExpression(desktopList.metaExpression),
            badgesExpression: sanitizeExpression(
                desktopList.badgesExpression,
                sanitizeExpression(desktopCard.badgesExpression, sanitizeExpression(mobileCard.badgesExpression))
            ),
            asideExpression: sanitizeExpression(desktopList.asideExpression),
            urlExpression: sanitizeExpression(
                desktopList.urlExpression,
                sanitizeExpression(desktopCard.urlExpression, sanitizeExpression(mobileCard.urlExpression, "record.show_url || '#'"))
            ),
            subtitleUrlExpression: sanitizeExpression(
                desktopList.subtitleUrlExpression,
                sanitizeExpression(
                    desktopList.urlExpression,
                    sanitizeExpression(desktopCard.urlExpression, sanitizeExpression(mobileCard.urlExpression, "record.show_url || '#'"))
                )
            ),
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

const renderToolbar = (config) => `
    <div class="border-b border-gray-100 bg-white px-4 py-3 sm:px-6" data-crud-toolbar>
        <div class="flex items-center gap-3">
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
            </div>

            ${config.permissions.showExport ? renderToolbarButton(config.labels.exportTitle, config.labels.exportAriaLabel, 'data-crud-toolbar-export-button', config.handlers.export, 'M9 8.25H7.5A2.25 2.25 0 0 0 5.25 10.5v9A2.25 2.25 0 0 0 7.5 21.75h9A2.25 2.25 0 0 0 18.75 19.5v-9A2.25 2.25 0 0 0 16.5 8.25H15 M12 15V3m0 12 3.75-3.75M12 15l-3.75-3.75') : ''}
            ${config.permissions.showImport ? renderToolbarButton(config.labels.importTitle, config.labels.importAriaLabel, 'data-crud-toolbar-import-button', config.handlers.import, 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5 M16.5 12 12 7.5m0 0L7.5 12m4.5-4.5V16.5') : ''}
            ${config.permissions.showCreate ? renderToolbarButton(config.labels.createTitle, config.labels.createAriaLabel, 'data-crud-toolbar-create-button', config.handlers.create, 'M12 4.5v15m7.5-7.5h-15') : ''}
        </div>
    </div>
`;

const renderToolbarButton = (title, ariaLabel, dataAttribute, handler, pathData) => `
    <button
        type="button"
        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
        title="${escapeHtml(title)}"
        aria-label="${escapeHtml(ariaLabel)}"
        ${dataAttribute}
        x-on:click="${handler}"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            ${pathData.split(' M').map((path, index) => `<path stroke-linecap="round" stroke-linejoin="round" d="${index === 0 ? path : `M${path}`}" />`).join('')}
        </svg>
    </button>
`;

const renderActionItems = (config) => config.actions.map((action) => {
    const toneClasses = action.tone === 'warning'
        ? 'flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50'
        : 'flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50';

    return `
        <button
            type="button"
            class="${toneClasses}"
            x-show="${escapeAttributeExpression(action.showExpression)}"
            x-on:click.stop="open = false; ${escapeAttributeExpression(action.handler)}"
            role="menuitem"
        >${escapeHtml(action.label)}</button>
    `;
}).join('');

const renderActionCell = (config) => `
    <div
        class="relative shrink-0"
        x-data="{
            open: false,
            menuTop: 0,
            menuRight: 0,
            toggle() {
                if (this.open) {
                    this.open = false;
                    return;
                }

                const rect = this.$refs.button.getBoundingClientRect();

                this.menuTop = Math.max(8, Math.min(rect.bottom + 8, window.innerHeight - ${Math.max(44, (config.actions.length * 36) + 8)} - 8));
                this.menuRight = Math.max(8, window.innerWidth - rect.right);
                this.open = true;
            },
        }"
        x-bind:class="open ? 'z-50' : 'z-0'"
        x-on:click.stop
        x-on:resize.window="open = false"
        x-on:scroll.window="open = false"
        x-on:wheel.window="open = false"
        x-on:touchmove.window="open = false"
    >
        <button
            x-ref="button"
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-blue-300 hover:text-gray-900"
            x-on:click="toggle()"
            aria-label="${escapeHtml(config.labels.actionsAriaLabel)}"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
            </svg>
        </button>
        <template x-teleport="body">
            <div
                class="fixed z-[80] w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg"
                x-bind:style="\`top: \${menuTop}px; right: \${menuRight}px;\`"
                x-show="open"
                x-on:click.outside="open = false"
                x-on:click.stop
                x-cloak
                role="menu"
            >
                ${renderActionItems(config)}
            </div>
        </template>
    </div>
`;

const renderBadges = (expression, keySuffix) => expression !== '' ? `
    <div class="flex flex-wrap gap-1.5">
        <template x-for="badge in ${escapeAttributeExpression(expression)}" :key="\`${keySuffix}-\${record.id}-\${badge}\`">
            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-blue-700" x-text="badge"></span>
        </template>
    </div>
` : '';

const badgeLabelExpression = (badgeExpression) => `typeof ${badgeExpression} === 'string' ? ${badgeExpression} : ${badgeExpression}.label`;

const badgeToneClassExpression = (badgeExpression, fallbackToneClasses) => `{
    'border border-green-600 bg-white text-green-700': typeof ${badgeExpression} !== 'string' && ${badgeExpression}.tone === 'received',
    'bg-green-50 text-green-700': typeof ${badgeExpression} !== 'string' && ${badgeExpression}.tone === 'green',
    'bg-blue-50 text-blue-700': typeof ${badgeExpression} !== 'string' && ${badgeExpression}.tone === 'blue',
    'bg-yellow-50 text-yellow-800': typeof ${badgeExpression} !== 'string' && ${badgeExpression}.tone === 'yellow',
    'bg-red-50 text-red-700': typeof ${badgeExpression} !== 'string' && ${badgeExpression}.tone === 'red',
    '${fallbackToneClasses}': typeof ${badgeExpression} === 'string' || !['received', 'green', 'blue', 'yellow', 'red'].includes(${badgeExpression}.tone),
}`;

const renderStats = (expression) => expression !== '' ? `
    <div class="-mx-4 grid grid-cols-6 gap-px border-y border-gray-100 bg-gray-100" data-crud-card-stats>
        <template x-for="stat in ${escapeAttributeExpression(expression)}" :key="\`desktop-card-\${record.id}-stat-\${stat.label}\`">
            <div class="bg-white px-4 py-2" :class="stat.span === 6 ? 'col-span-6' : stat.span === 2 ? 'col-span-2' : 'col-span-3'">
                <dt class="truncate text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500" x-text="stat.label"></dt>
                <dd class="mt-1 truncate text-sm font-semibold text-gray-900" x-text="stat.value"></dd>
            </div>
        </template>
    </div>
` : '';

const renderDetailRows = (expression, keyPrefix, className = 'mt-3 space-y-2') => expression !== '' ? `
    <div class="${className}" data-crud-card-detail-rows>
        <template x-for="(row, rowIndex) in ${escapeAttributeExpression(expression)}" :key="\`${keyPrefix}-\${record.id}-detail-row-\${rowIndex}\`">
            <div class="flex min-w-0 items-baseline justify-between gap-3 text-xs">
                <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                    <template x-for="item in (row.left || [])" :key="\`${keyPrefix}-\${record.id}-detail-left-\${rowIndex}-\${item.label}\`">
                        <span class="inline-flex min-w-0 items-baseline gap-1">
                            <span class="shrink-0 text-[0.65rem] font-medium text-gray-400" x-text="item.label"></span>
                            <span class="min-w-0 truncate text-xs font-medium text-gray-700" x-text="item.value"></span>
                        </span>
                    </template>
                </div>
                <div class="flex shrink-0 items-baseline gap-x-1.5 text-right">
                    <template x-for="item in (row.right || [])" :key="\`${keyPrefix}-\${record.id}-detail-right-\${rowIndex}-\${item.label}\`">
                        <span class="inline-flex items-baseline gap-1">
                            <span class="text-[0.65rem] font-medium text-gray-400" x-text="item.label"></span>
                            <span class="text-xs font-medium text-gray-700" x-text="item.value"></span>
                        </span>
                    </template>
                </div>
            </div>
        </template>
    </div>
` : '';

const iconBadgeMarkup = (sizeClasses = 'h-5 w-5') => `
    <template x-if="badge.icon === 'shopping-cart'">
        <svg xmlns="http://www.w3.org/2000/svg" class="${sizeClasses}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
        </svg>
    </template>
    <template x-if="badge.icon === 'credit-card'">
        <svg xmlns="http://www.w3.org/2000/svg" class="${sizeClasses}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
        </svg>
    </template>
    <template x-if="badge.icon === 'cog'">
        <svg xmlns="http://www.w3.org/2000/svg" class="${sizeClasses}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
        </svg>
    </template>
    <template x-if="badge.icon === 'rectangle-group'">
        <svg xmlns="http://www.w3.org/2000/svg" class="${sizeClasses}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
        </svg>
    </template>
`;

const renderIconBadges = (expression) => expression !== '' ? `
    <div class="flex items-center gap-2" data-crud-card-icon-badges>
        <template x-for="badge in ${escapeAttributeExpression(expression)}" :key="\`desktop-card-\${record.id}-icon-\${badge.icon}\`">
            <span
                class="inline-flex shrink-0 items-center justify-center"
                :class="badge.active ? 'text-blue-600' : 'text-gray-300'"
                x-bind:title="badge.label"
                x-bind:aria-label="badge.label"
            >
                ${iconBadgeMarkup()}
            </span>
        </template>
    </div>
` : '';

const renderCardToggle = (config) => {
    const toggleConfig = config.rowToggle.name !== '' && config.rowToggle.checkedExpression !== ''
        ? config.rowToggle
        : config.mobileCard.toggle;

    if (toggleConfig.name === '' || toggleConfig.checkedExpression === '') {
        return '';
    }

    return `<div class="shrink-0" x-on:click.stop>${renderToggle(toggleConfig)}</div>`;
};

const renderCardGrid = (config) => {
    const card = config.desktopCard;
    const hasActions = card.showActions && config.actions.length > 0;
    const toggleMarkup = renderCardToggle(config);
    const titleAsideMarkup = card.titleAsideSecondaryExpression !== '' ? `
        <div class="flex shrink-0 self-stretch flex-col items-end justify-between text-right">
            <p class="text-xs font-medium text-gray-500" x-text="${escapeAttributeExpression(card.titleAsideExpression)}"></p>
            <p class="mt-2 text-sm font-semibold text-gray-900" x-text="${escapeAttributeExpression(card.titleAsideSecondaryExpression)}"></p>
        </div>
    ` : card.titleAsideExpression !== '' ? `<p class="shrink-0 text-right text-xs font-medium text-gray-500" x-text="${escapeAttributeExpression(card.titleAsideExpression)}"></p>` : '';
    const titleBadgesMarkup = card.titleBadgesExpression !== '' ? `
        <template x-for="badge in ${escapeAttributeExpression(card.titleBadgesExpression)}" :key="\`desktop-card-\${record.id}-title-badge-\${typeof badge === 'string' ? badge : badge.label}\`">
            <span
                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide"
                data-crud-desktop-title-badge
                :class="${badgeToneClassExpression('badge', 'bg-gray-100 text-gray-700')}"
                x-text="${badgeLabelExpression('badge')}"
            ></span>
        </template>
    ` : '';
    const subtitleInlineMarkup = card.subtitleAsideExpression === '' && card.subtitleExpression !== ''
        ? `<p class="mt-0.5 truncate text-sm text-gray-500" x-text="${escapeAttributeExpression(card.subtitleExpression)}"></p>`
        : '';
    const subtitleAsideMarkup = card.subtitleAsideExpression !== '' ? `
        <div class="mt-0.5 flex min-w-0 items-center justify-between gap-3 text-sm text-gray-500">
            ${card.subtitleExpression !== '' ? `<p class="min-w-0 truncate" x-text="${escapeAttributeExpression(card.subtitleExpression)}"></p>` : '<span></span>'}
            <p class="shrink-0 text-right font-medium text-gray-700" x-text="${escapeAttributeExpression(card.subtitleAsideExpression)}"></p>
        </div>
    ` : '';

    return `
        <div class="hidden h-full min-h-0 md:block">
            <div class="flex h-full min-h-0 flex-col">
                <div class="min-h-0 flex-1 overflow-y-auto p-6" data-crud-records-scroll>
                    <div
                        x-show="!${config.state.loading} && ${config.state.records}.length === 0"
                        class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500"
                        data-crud-empty-state
                    >
                        ${escapeHtml(config.labels.emptyState)}
                    </div>

                    <div
                        class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
                        data-crud-card-grid
                        :class="${config.state.loading} ? 'opacity-80' : 'opacity-100'"
                    >
                        <template x-for="record in ${config.state.records}" :key="\`desktop-card-\${record.id}\`">
                            <article class="group flex ${card.compact ? '' : 'min-h-56'} flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md" data-crud-card>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <a
                                                class="truncate text-base font-semibold text-gray-900"
                                                :href="${escapeAttributeExpression(card.urlExpression)}"
                                                x-text="${escapeAttributeExpression(card.titleExpression)}"
                                            ></a>
                                            ${titleBadgesMarkup}
                                        </div>
                                        ${subtitleInlineMarkup}
                                    </div>
                                    ${titleAsideMarkup}
                                    ${toggleMarkup || hasActions ? `
                                        <div class="flex shrink-0 items-start gap-2">
                                            ${toggleMarkup}
                                            ${hasActions ? renderActionCell(config) : ''}
                                        </div>
                                    ` : ''}
                                </div>
                                ${subtitleAsideMarkup}
                                ${renderDetailRows(card.detailRowsExpression, 'desktop-card', 'mt-2 space-y-1.5')}

                                ${card.iconBadgesExpression !== '' || card.badgesExpression !== '' ? `<div class="mt-2">
                                    ${renderIconBadges(card.iconBadgesExpression)}
                                    ${renderBadges(card.badgesExpression, 'desktop-card-badge')}
                                </div>` : ''}

                                ${card.showBody && card.bodyExpression !== '' ? `<p class="mt-4 line-clamp-2 text-sm text-gray-600" x-text="${escapeAttributeExpression(card.bodyExpression)}"></p>` : ''}

                                ${card.statsExpression !== '' ? `<div class="mt-4">
                                    ${renderStats(card.statsExpression)}
                                </div>` : ''}

                            </article>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    `;
};

const renderDesktopList = (config) => {
    const list = config.desktopList;
    const hasActions = config.actions.length > 0;
    const toggleMarkup = renderCardToggle(config);

    return `
        <div class="hidden h-full min-h-0 md:block">
            <div class="flex h-full min-h-0 flex-col">
                <div class="min-h-0 flex-1 overflow-y-auto p-6" data-crud-records-scroll>
                    <div
                        x-show="!${config.state.loading} && ${config.state.records}.length === 0"
                        class="rounded-lg border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500"
                        data-crud-empty-state
                    >
                        ${escapeHtml(config.labels.emptyState)}
                    </div>

                    <ul
                        role="list"
                        class="rounded-lg border border-gray-200 bg-white shadow-sm"
                        data-crud-stacked-list
                        :class="${config.state.loading} ? 'opacity-80' : 'opacity-100'"
                    >
                        <template x-for="record in ${config.state.records}" :key="\`desktop-list-\${record.id}\`">
                            <li class="flex items-center justify-between gap-x-6 border-b border-gray-100 px-5 py-4 last:border-b-0 transition hover:bg-gray-50" data-crud-list-row>
                                <div class="min-w-0 flex-auto">
                                    <div class="flex min-w-0 items-center gap-x-3">
                                        <a
                                            class="truncate text-sm font-semibold leading-6 text-gray-900 transition hover:text-blue-600"
                                            :href="${escapeAttributeExpression(list.urlExpression)}"
                                            x-text="${escapeAttributeExpression(list.titleExpression)}"
                                        ></a>
                                        ${renderBadges(list.badgesExpression, 'desktop-list-badge')}
                                    </div>

                                    <div class="mt-1 flex min-w-0 items-center gap-x-3 text-xs leading-5 text-gray-500">
                                        ${list.subtitleExpression !== '' ? `<a class="truncate font-medium text-gray-600 hover:text-blue-600" :href="${escapeAttributeExpression(list.subtitleUrlExpression)}" x-text="${escapeAttributeExpression(list.subtitleExpression)}"></a>` : ''}
                                        ${list.metaExpression !== '' ? `<span class="truncate" x-text="${escapeAttributeExpression(list.metaExpression)}"></span>` : ''}
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-x-4">
                                    ${list.asideExpression !== '' ? `<p class="hidden text-xs font-medium text-gray-500 lg:block" x-text="${escapeAttributeExpression(list.asideExpression)}"></p>` : ''}
                                    ${toggleMarkup}
                                    ${hasActions ? renderActionCell(config) : ''}
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    `;
};

const renderMobileCards = (config) => {
    const card = config.mobileCard;
    const hasActions = card.showActions && config.actions.length > 0;
    const toggleMarkup = card.showToggle ? renderCardToggle(config) : '';
    const titleAsideMarkup = card.titleAsideExpression !== '' ? `
        <p class="ml-3 max-w-28 shrink-0 truncate text-right text-[0.7rem] font-medium leading-5 text-gray-500" data-crud-mobile-title-aside x-text="${escapeAttributeExpression(card.titleAsideExpression)}"></p>
    ` : '';
    const titleAsideStatsMarkup = card.titleAsideStatsExpression !== '' ? `
        <div class="grid shrink-0 grid-cols-2 items-center" data-crud-mobile-title-aside-stats>
            <template x-for="stat in ${escapeAttributeExpression(card.titleAsideStatsExpression)}" :key="\`mobile-card-\${record.id}-title-aside-stat-\${stat.label}\`">
                <div class="min-w-14 border-l border-gray-100 px-3 text-center leading-none">
                    <div class="text-[0.55rem] font-normal leading-none text-gray-400" x-text="stat.label"></div>
                    <div class="mt-0.5 text-[0.65rem] font-normal leading-none text-gray-600" x-text="stat.value"></div>
                </div>
            </template>
        </div>
    ` : '';
    const subtitleMarkup = card.titleAsideSecondaryExpression !== '' && card.iconBadgesExpression === '' ? `
        <div class="mt-0.5 flex min-w-0 items-center justify-between gap-3 text-xs text-gray-600">
            ${card.subtitleExpression !== '' ? `<p class="truncate" x-text="${escapeAttributeExpression(card.subtitleExpression)}"></p>` : '<span></span>'}
            <p class="shrink-0 text-right font-semibold text-gray-900" x-text="${escapeAttributeExpression(card.titleAsideSecondaryExpression)}"></p>
        </div>
    ` : card.subtitleExpression !== '' ? `<p class="mt-0.5 truncate text-xs text-gray-600" x-text="${escapeAttributeExpression(card.subtitleExpression)}"></p>` : '';
    const iconRowAsideMarkup = card.iconRowStatsExpression !== '' && card.iconBadgesExpression !== ''
        ? `<div class="grid shrink-0 grid-cols-2 items-baseline gap-x-5">
            <template x-for="stat in ${escapeAttributeExpression(card.iconRowStatsExpression)}" :key="\`mobile-card-\${record.id}-icon-row-stat-\${stat.label}\`">
                <div class="flex items-baseline gap-x-1">
                    <span class="text-[0.55rem] font-normal text-gray-400" x-text="stat.label"></span>
                    <span class="text-[0.65rem] font-normal text-gray-600" x-text="stat.value"></span>
                </div>
            </template>
        </div>`
        : card.titleAsideSecondaryExpression !== '' && card.iconBadgesExpression !== ''
            ? `<p class="shrink-0 text-right text-[0.55rem] font-normal text-gray-400" x-text="${escapeAttributeExpression(card.titleAsideSecondaryExpression)}"></p>`
        : '';
    const titleBadgesMarkup = card.titleBadgesExpression !== '' ? `
        <template x-for="badge in ${escapeAttributeExpression(card.titleBadgesExpression)}" :key="\`mobile-card-\${record.id}-title-badge-\${typeof badge === 'string' ? badge : badge.label}\`">
            <span
                class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide"
                data-crud-mobile-title-badge
                :class="${badgeToneClassExpression('badge', 'bg-gray-100 text-gray-700')}"
                x-text="${badgeLabelExpression('badge')}"
            ></span>
        </template>
    ` : '';
    const titleRowMarkup = card.titleAsidePlacement === 'top-right'
        ? `
            <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <p class="truncate text-sm font-semibold text-gray-900" x-text="${escapeAttributeExpression(card.titleExpression)}"></p>
                    ${titleBadgesMarkup}
                </div>
                ${titleAsideMarkup}
            </div>
        `
        : `
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <p class="truncate text-sm font-semibold text-gray-900" x-text="${escapeAttributeExpression(card.titleExpression)}"></p>
                    ${titleBadgesMarkup}
                </div>
                ${titleAsideMarkup}
            </div>
        `;
    const centerStatsMarkup = card.centerStatsExpression !== '' ? `
        <div class="pointer-events-none absolute left-1/2 top-1/2 z-0 grid -translate-x-1/2 -translate-y-1/2 gap-y-0.5">
            <template x-for="stat in ${escapeAttributeExpression(card.centerStatsExpression)}" :key="\`mobile-card-\${record.id}-center-stat-\${stat.label}\`">
                <div class="grid min-w-24 grid-cols-[auto_auto] items-baseline gap-x-2 leading-none">
                    <div class="text-left text-[0.5rem] font-semibold uppercase tracking-wide text-gray-400" x-text="stat.label"></div>
                    <div class="text-right text-[0.6rem] font-semibold text-gray-800" x-text="stat.value"></div>
                </div>
            </template>
        </div>
    ` : '';

    return `
        <div class="h-full min-h-0 md:hidden" data-crud-mobile-cards>
            <div class="min-h-0 flex-1 overflow-y-auto p-0" data-crud-records-scroll>
                <div class="border-t border-gray-300">
                    <div
                        x-show="!${config.state.loading} && ${config.state.records}.length === 0"
                        class="border-b border-gray-300 bg-white px-4 py-10 text-center text-sm text-gray-500"
                        data-crud-empty-state
                    >
                        ${escapeHtml(config.labels.emptyState)}
                    </div>

                    <template x-for="record in ${config.state.records}" :key="\`mobile-card-\${record.id}\`">
                        <div class="relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 overflow-visible border-b border-gray-300 bg-white px-4 py-2" data-crud-card>
                            ${centerStatsMarkup}
                            <a class="min-w-0" :href="${escapeAttributeExpression(card.urlExpression)}">
                                ${titleRowMarkup}
                                ${subtitleMarkup}
                                ${renderDetailRows(card.detailRowsExpression, 'mobile-card', 'mt-2 space-y-1.5')}
                                ${card.showBody && card.bodyExpression !== '' ? `<p class="mt-1 truncate text-xs text-gray-600" x-text="${escapeAttributeExpression(card.bodyExpression)}"></p>` : ''}
                                ${renderBadges(card.badgesExpression, 'mobile-card-badge')}
                                ${card.iconBadgesExpression !== '' ? `
                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 flex-wrap gap-2">
                                            <template x-for="badge in ${escapeAttributeExpression(card.iconBadgesExpression)}" :key="\`mobile-card-\${record.id}-icon-\${badge.icon}\`">
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center"
                                                    :class="badge.active ? 'text-blue-600' : 'text-gray-300'"
                                                    x-bind:title="badge.label"
                                                    x-bind:aria-label="badge.label"
                                                >
                                                    ${iconBadgeMarkup('h-4 w-4')}
                                                </span>
                                            </template>
                                        </div>
                                        ${iconRowAsideMarkup}
                                    </div>
                                ` : ''}
                            </a>
                            ${titleAsideStatsMarkup}
                            ${toggleMarkup || hasActions ? `<div class="relative z-10 flex shrink-0 items-center gap-2">
                                ${toggleMarkup}
                                ${hasActions ? renderActionCell(config) : ''}
                            </div>` : ''}
                        </div>
                    </template>
                </div>
            </div>
        </div>
    `;
};

export function mountCrudCardRenderer(targetEl, config) {
    if (!targetEl) {
        return;
    }

    const normalized = normalizeCardRendererConfig(config);

    targetEl.innerHTML = `
        <div class="flex h-full min-h-0 flex-col overflow-hidden bg-white" data-crud-card-renderer>
            <div class="border-b border-gray-100 px-4 py-3 sm:px-6">
                <p class="text-sm text-red-600" x-show="${normalized.state.error}" x-text="${normalized.state.error}"></p>
            </div>
            ${renderToolbar(normalized)}
            ${renderMobileCards(normalized)}
            ${normalized.desktopList.enabled ? renderDesktopList(normalized) : renderCardGrid(normalized)}
        </div>
    `;
}
