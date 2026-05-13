import { normalizeImportConfig } from './import-config';

const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const sanitizeExpression = (value, fallback = '') => (typeof value === 'string' && value.trim() !== '' ? value : fallback);

const renderBulkOptionsMarkup = (config) => {
    const bulkOptions = config.bulkOptions || {};
    const createFulfillmentRecipesEnabled = Boolean(bulkOptions.create_fulfillment_recipes && bulkOptions.create_fulfillment_recipes.enabled);
    const bulkManufacturableEnabled = Boolean(bulkOptions.import_all_as_manufacturable && bulkOptions.import_all_as_manufacturable.enabled);
    const bulkPurchasableEnabled = Boolean(bulkOptions.import_all_as_purchasable && bulkOptions.import_all_as_purchasable.enabled);
    const bulkBaseUomEnabled = Boolean(bulkOptions.bulk_base_uom_id && bulkOptions.bulk_base_uom_id.enabled);
    const labels = config.labels || {};
    const sections = [];

    if (createFulfillmentRecipesEnabled || bulkManufacturableEnabled || bulkPurchasableEnabled) {
        sections.push(`
            <div class="grid w-full min-w-0 gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
                ${createFulfillmentRecipesEnabled ? `
                    <label class="flex items-center gap-3 text-sm text-gray-700 sm:col-span-2">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="createFulfillmentRecipes">
                        ${escapeHtml(bulkOptions.create_fulfillment_recipes.label)}
                    </label>
                ` : ''}
                ${bulkManufacturableEnabled ? `
                    <label class="flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="bulkManufacturable">
                        ${escapeHtml(bulkOptions.import_all_as_manufacturable.label)}
                    </label>
                ` : ''}
                ${bulkPurchasableEnabled ? `
                    <label class="flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="bulkPurchasable">
                        ${escapeHtml(bulkOptions.import_all_as_purchasable.label)}
                    </label>
                ` : ''}
            </div>
        `);
    }

    if (bulkBaseUomEnabled) {
        sections.push(`
            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <label class="block text-sm font-medium text-gray-700">
                    ${escapeHtml(bulkOptions.bulk_base_uom_id.label)}
                    <select
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        x-model="bulkBaseUomId"
                    >
                        <option value="">Select bulk UoM</option>
                        <template x-for="uom in uoms" :key="uom.id">
                            <option :value="String(uom.id)" x-text="\`\${uom.name} (\${uom.symbol})\`"></option>
                        </template>
                    </select>
                </label>
            </div>
        `);
    }

    if (sections.length === 0) {
        sections.push(`
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                ${escapeHtml(labels.noBulkOptions || 'No additional import options are available for this resource.')}
            </div>
        `);
    }

    return sections.join('');
};

const renderPreviewCardMarkup = (config) => {
    const previewDisplay = config.previewDisplay || {};
    const titleExpression = sanitizeExpression(previewDisplay.titleExpression, "row.name || '—'");
    const subtitleExpression = sanitizeExpression(previewDisplay.subtitleExpression);
    const bodyExpression = sanitizeExpression(previewDisplay.bodyExpression);

    return `
        <article
            class="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
            x-show="rowVisibleInPreview(row)"
            x-bind:aria-hidden="rowVisibleInPreview(row) ? 'false' : 'true'"
            data-shared-import-preview-card
        >
            <div class="flex min-h-10 items-center gap-3">
                <input type="checkbox" class="shrink-0 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="row.selected" x-bind:disabled="row.is_duplicate">
                <div class="min-w-0 flex-1 overflow-hidden">
                    <p class="block truncate text-sm font-medium text-gray-900" x-bind:title="${titleExpression}" x-text="${titleExpression}"></p>
                    ${subtitleExpression !== '' ? `
                        <p class="mt-1 truncate text-xs text-gray-500" x-show="Boolean(${subtitleExpression})" x-bind:title="${subtitleExpression}" x-text="${subtitleExpression}"></p>
                    ` : ''}
                    ${bodyExpression !== '' ? `
                        <p class="mt-1 truncate text-xs text-gray-500" x-show="Boolean(${bodyExpression})" x-text="${bodyExpression}"></p>
                    ` : ''}
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase leading-none" :class="row.is_duplicate ? 'bg-red-50 text-red-700' : (row.is_active ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700')" x-text="previewStatusLabel(row)"></span>
            </div>
            <template x-if="rowValidationMessages(index).length > 0">
                <div class="mt-2 space-y-1">
                    <template x-for="message in rowValidationMessages(index)" :key="message">
                        <p class="text-xs text-red-600" x-text="message"></p>
                    </template>
                </div>
            </template>
        </article>
    `;
};

const renderImportPanelMarkup = (config) => `
    <div data-shared-import-root>
        <div
            class="fixed inset-0 z-50 overflow-hidden"
            x-show="isImportPanelOpen"
            x-cloak
            role="dialog"
            aria-modal="true"
            data-shared-import-panel
        >
            <div class="absolute inset-0 overflow-hidden">
                <div
                    class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                    x-show="isImportPanelOpen"
                    x-on:click="closeImportPanel()"
                ></div>

                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div class="pointer-events-auto w-screen max-w-3xl">
                        <div class="flex h-full flex-col bg-white shadow-xl">
                            <div class="flex-1 overflow-y-auto p-6">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h2 class="text-lg font-medium text-gray-900" x-text="importTitleLabel()"></h2>
                                        <p class="mt-1 text-sm text-gray-600">${escapeHtml((config.labels || {}).previewDescription || 'Review the import preview before confirming the selected records.')}</p>
                                    </div>
                                    <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeImportPanel()">
                                        <span class="sr-only">Close panel</span>
                                        ✕
                                    </button>
                                </div>

                                <div class="mt-6 flex min-h-0 flex-1 flex-col gap-4">
                                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                                        <label class="block text-sm font-medium text-gray-700" for="shared-import-source">
                                            ${escapeHtml((config.labels || {}).source || 'Source')}
                                            <select
                                                id="shared-import-source"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                x-model="selectedSource"
                                                x-on:change="handleSourceChange()"
                                                data-shared-import-source
                                            >
                                                <option value="">Select source</option>
                                                <template x-for="source in sources" :key="source.value">
                                                    <option :value="source.value" :disabled="!source.enabled" x-text="sourceOptionLabel(source)"></option>
                                                </template>
                                                <template x-for="fileSource in cachedFileSources" :key="fileSource.value">
                                                    <option :value="fileSource.value" x-text="fileSource.label"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <input
                                            id="shared-import-file"
                                            x-ref="importFileInput"
                                            type="file"
                                            accept=".csv,text/csv"
                                            class="sr-only"
                                            x-on:change="handleLocalFileChange($event)"
                                            data-shared-import-file-input
                                        />
                                        <p class="mt-1 text-sm text-red-600" x-text="errors.source[0]"></p>
                                        <p class="mt-1 text-sm text-red-600" x-text="errors.file[0]"></p>
                                    </div>

                                    <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-gray-50 px-4 py-4" x-show="selectedSource && selectedSourceEnabled() && !sourceConnected() && !isCachedFileSource() && !isFileUploadMode()">
                                        <h3 class="text-sm font-semibold text-gray-900">Connection required</h3>
                                        <p class="mt-1 text-sm text-gray-600">
                                            WooCommerce status:
                                            <span class="font-medium" x-text="selectedSourceStatusLabel()"></span>.
                                        </p>
                                        <p class="mt-2 text-sm text-gray-600" x-show="canManageConnections">
                                            Manage store credentials from Profile → Connectors before loading a preview.
                                        </p>
                                        <p class="mt-2 text-sm text-gray-600" x-show="!canManageConnections">
                                            Ask an admin to connect WooCommerce from Profile → Connectors before loading a preview.
                                        </p>
                                        <div class="mt-4" x-show="canManageConnections && connectorsPageUrl">
                                            <a
                                                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                                :href="connectorsPageUrl"
                                            >
                                                Open Connectors
                                            </a>
                                        </div>
                                    </div>

                                    <div
                                        class="flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center"
                                        x-show="!hasSelectedImportSource()"
                                        data-shared-import-empty-state
                                    >
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                            <svg class="h-7 w-7 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V7.5m0 0L8.25 11.25M12 7.5l3.75 3.75M3.75 16.5v1.125c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V16.5" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-6 text-base font-semibold text-gray-900">Choose an import source</h3>
                                        <p class="mt-2 max-w-md text-sm text-gray-600">${escapeHtml((config.labels || {}).emptyStateDescription || 'Select a WooCommerce connection or switch to file upload to start loading an import preview.')}</p>
                                    </div>

                                    <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white" x-show="hasSelectedImportSource()">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-4 text-left"
                                            x-on:click="bulkOptionsAccordionOpen = !bulkOptionsAccordionOpen"
                                            data-shared-import-bulk-options-accordion
                                        >
                                            <span class="text-sm font-semibold text-gray-900">Bulk Import Options</span>
                                            <svg class="h-5 w-5 text-gray-400 transition" :class="bulkOptionsAccordionOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                        <div class="w-full min-w-0 overflow-hidden border-t border-gray-100 pl-4 pr-5 py-4 sm:px-4" x-show="bulkOptionsAccordionOpen" x-cloak>
                                            ${renderBulkOptionsMarkup(config)}
                                        </div>
                                    </div>

                                    <div class="w-full min-w-0 box-border rounded-lg border border-gray-200 bg-white" x-show="hasSelectedImportSource()">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-4 text-left"
                                            x-on:click="previewRecordsAccordionOpen = !previewRecordsAccordionOpen"
                                            data-shared-import-preview-records-accordion
                                        >
                                            <span class="text-sm font-semibold text-gray-900">Import Preview</span>
                                            <svg class="h-5 w-5 text-gray-400 transition" :class="previewRecordsAccordionOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <div class="w-full min-w-0 overflow-hidden border-t border-gray-100" x-show="previewRecordsAccordionOpen" x-cloak>
                                            <div class="shrink-0 w-full min-w-0 space-y-4 pl-4 pr-5 py-4 sm:px-4">
                                                <div class="flex w-full min-w-0 box-border flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                    <div class="flex w-full min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                                                        <label class="block min-w-0 flex-1 text-sm text-gray-700">
                                                            <span class="sr-only">Search preview records</span>
                                                            <input
                                                                type="search"
                                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                placeholder="${escapeHtml((config.labels || {}).previewSearch || 'Search preview records')}"
                                                                x-model="previewSearch"
                                                                data-shared-import-preview-search
                                                            />
                                                        </label>
                                                        <label class="inline-flex max-w-full items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" x-model="showDuplicateRows" data-shared-import-show-duplicates>
                                                            <span class="truncate" x-text="\`Show Duplicates (\${duplicateRowCount()} rows)\`"></span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <label class="inline-flex items-center gap-3 text-sm text-gray-700">
                                                        <input
                                                            type="checkbox"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                            :checked="allVisibleSelectableRowsSelected()"
                                                            x-on:change="toggleVisibleRowSelection($event)"
                                                            data-shared-import-select-visible
                                                        >
                                                        Select All
                                                    </label>
                                                    <p class="text-sm text-gray-500" x-show="!showDuplicateRows && duplicateRowCount() > 0">
                                                        Duplicate rows are hidden until enabled.
                                                    </p>
                                                </div>

                                                <p class="text-sm text-red-600" x-text="previewError"></p>
                                                <p class="text-sm text-red-600" x-text="importError"></p>
                                            </div>

                                            <div class="w-full min-w-0 box-border overflow-hidden pl-4 pr-5 sm:px-4">
                                                <div class="max-h-[32rem] w-full min-w-0 overflow-y-auto pb-52 sm:pb-32" data-shared-import-preview-scroll>
                                                    <div class="space-y-4" x-show="isLoadingPreview" data-shared-import-preview-loading>
                                                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700" x-text="previewLoadingMessage"></div>
                                                        <div class="grid gap-4 lg:grid-cols-2">
                                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                                <div class="h-4 w-1/3 animate-pulse rounded bg-gray-200"></div>
                                                                <div class="mt-3 h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                                            </div>
                                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                                <div class="h-4 w-1/4 animate-pulse rounded bg-gray-200"></div>
                                                                <div class="mt-3 h-4 w-3/4 animate-pulse rounded bg-gray-100"></div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="flex min-h-full items-center justify-center px-4 py-12"
                                                        x-show="!isLoadingPreview && !hasVisiblePreviewRows()"
                                                        data-shared-import-preview-empty-state
                                                    >
                                                        <div class="w-full max-w-md rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-10 text-center">
                                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200">
                                                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                                                </svg>
                                                            </div>
                                                            <h3 class="mt-4 text-sm font-semibold text-gray-900" x-text="previewEmptyStateTitle()"></h3>
                                                            <p class="mt-2 text-sm text-gray-600" x-text="previewEmptyStateMessage()"></p>
                                                        </div>
                                                    </div>

                                                    <div class="w-full min-w-0 space-y-2" x-show="!isLoadingPreview && hasVisiblePreviewRows()">
                                                        <template x-for="(row, index) in previewRows" :key="row.external_id">
                                                            ${renderPreviewCardMarkup(config)}
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 pl-4 pr-6 py-4 sm:px-6">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                    x-on:click="closeImportPanel()"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                    x-show="previewRows.length > 0"
                                    x-bind:disabled="isSubmittingImport"
                                    x-bind:class="isSubmittingImport ? 'cursor-not-allowed opacity-60' : ''"
                                    x-on:click="submitImport()"
                                >
                                    ${escapeHtml((config.labels || {}).submit || 'Import Selected')}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
`;

export function createImportModule(options = {}) {
    const config = normalizeImportConfig(options.config || options);
    const adapters = options.adapters && typeof options.adapters === 'object' ? options.adapters : {};
    const callbacks = options.callbacks && typeof options.callbacks === 'object' ? options.callbacks : {};
    const endpointsConfig = config.endpoints || {};
    const labels = config.labels || {};
    const messages = config.messages || {};
    const rowBehavior = config.rowBehavior || {};
    const bulkOptions = config.bulkOptions || {};
    const previewDisplay = config.previewDisplay || {};
    const normalizePreviewRowHook = typeof (adapters.normalizePreviewRow || options.normalizePreviewRow) === 'function'
        ? (adapters.normalizePreviewRow || options.normalizePreviewRow)
        : null;
    const parseLocalRowsHook = typeof (adapters.parseLocalRows || adapters.parseLocalCsv || options.parseLocalCsv) === 'function'
        ? (adapters.parseLocalRows || adapters.parseLocalCsv || options.parseLocalCsv)
        : null;
    const buildImportRowPayloadHook = typeof (adapters.buildImportRowPayload || options.buildImportRowPayload) === 'function'
        ? (adapters.buildImportRowPayload || options.buildImportRowPayload)
        : null;
    const buildSubmitBodyHook = typeof (adapters.buildSubmitBody || options.buildSubmitBody) === 'function'
        ? (adapters.buildSubmitBody || options.buildSubmitBody)
        : null;
    const endpoints = {
        importPreview: endpointsConfig.preview || '',
        importStore: endpointsConfig.store || '',
    };
    const loadingPreviewLabel = labels.loadingPreviewDefault || 'Loading preview...';
    const loadingFilePreviewLabel = labels.loadingPreviewFile || 'Loading file preview...';
    const loadingExternalPreviewLabel = labels.loadingPreviewExternal || 'Loading WooCommerce preview...';
    const previewUnavailableMessage = messages.previewUnavailable || 'Unable to load preview.';
    const importUnavailableMessage = messages.importUnavailable || 'Unable to import products.';
    const fileReadErrorMessage = messages.fileReadError || 'The selected CSV file could not be read.';
    const filePreviewUnavailableMessage = messages.filePreviewUnavailable || 'The selected CSV file could not be previewed.';
    const emptyFileRowsMessage = messages.emptyFileRows || 'The selected CSV file does not contain any product rows.';
    const missingFileHeadersMessage = messages.missingFileHeaders || 'The selected CSV file is missing one or more required product headers.';
    const emptySelectionMessage = messages.emptySelection || '';
    const hideDuplicatesByDefault = rowBehavior.hideDuplicatesByDefault === true;
    const showDuplicatesDefault = !hideDuplicatesByDefault;
    const submitSelectedVisibleRowsOnly = rowBehavior.submitSelectedVisibleRowsOnly !== false;
    const createFulfillmentRecipesDefault = Boolean(bulkOptions.create_fulfillment_recipes && bulkOptions.create_fulfillment_recipes.enabled)
        ? bulkOptions.create_fulfillment_recipes.default !== false
        : false;
    const bulkManufacturableDefault = Boolean(bulkOptions.import_all_as_manufacturable && bulkOptions.import_all_as_manufacturable.enabled)
        ? bulkOptions.import_all_as_manufacturable.default === true
        : false;
    const bulkPurchasableDefault = Boolean(bulkOptions.import_all_as_purchasable && bulkOptions.import_all_as_purchasable.enabled)
        ? bulkOptions.import_all_as_purchasable.default === true
        : false;
    const bulkBaseUomIdDefault = Boolean(bulkOptions.bulk_base_uom_id && bulkOptions.bulk_base_uom_id.enabled)
        ? (bulkOptions.bulk_base_uom_id.default || '')
        : '';
    const previewSearchExpressions = Array.isArray(previewDisplay.searchExpressions)
        ? previewDisplay.searchExpressions
        : [];
    const previewErrorFields = Array.isArray(previewDisplay.errorFields)
        ? previewDisplay.errorFields
        : [];

    const emptyErrors = () => ({
        source: [],
        file: [],
    });

    const defaultNormalizePreviewRow = (row) => ({
        ...row,
        selected: row.selected !== false,
        sku: row.sku || '',
        default_price_cents: Object.prototype.hasOwnProperty.call(row, 'default_price_cents')
            ? row.default_price_cents
            : null,
        image_url: Object.prototype.hasOwnProperty.call(row, 'image_url')
            && typeof row.image_url === 'string'
            && row.image_url.trim() !== ''
            ? row.image_url
            : null,
        external_source: row.external_source || '',
        base_uom_id: row.base_uom_id ? String(row.base_uom_id) : '',
        is_sellable: row.is_sellable !== false,
        is_active: Boolean(row.is_active),
        is_manufacturable: row.is_manufacturable === null || row.is_manufacturable === undefined
            ? null
            : Boolean(row.is_manufacturable),
        is_purchasable: row.is_purchasable === null || row.is_purchasable === undefined
            ? null
            : Boolean(row.is_purchasable),
        has_manufacturable_override: Boolean(row.has_manufacturable_override),
        has_purchasable_override: Boolean(row.has_purchasable_override),
        is_duplicate: Boolean(row.is_duplicate),
    });

    const normalizePreviewRow = (row, context = {}) => {
        const normalizedRow = defaultNormalizePreviewRow(row);

        if (!normalizePreviewRowHook) {
            return normalizedRow;
        }

        const customRow = normalizePreviewRowHook(normalizedRow, {
            rawRow: row,
            ...context,
        });

        return customRow && typeof customRow === 'object' && !Array.isArray(customRow)
            ? customRow
            : normalizedRow;
    };

    return {
        selectedSource: '',
        previewRows: [],
        errors: emptyErrors(),
        previewError: '',
        importError: '',
        importValidationErrors: {},
        hasLocalFileRows: false,
        selectedFileName: '',
        cachedFileSources: [],
        nextCachedFileSourceId: 1,
        previewSearch: '',
        showDuplicateRows: showDuplicatesDefault,
        bulkOptionsAccordionOpen: false,
        previewRecordsAccordionOpen: true,
        bulkManufacturable: bulkManufacturableDefault,
        bulkPurchasable: bulkPurchasableDefault,
        bulkBaseUomId: bulkBaseUomIdDefault,
        createFulfillmentRecipes: createFulfillmentRecipesDefault,
        isLoadingPreview: false,
        isSubmittingImport: false,
        isImportPanelOpen: false,
        loadingPreviewLabel,
        previewLoadingMessage: loadingPreviewLabel,
        mount(rootEl) {
            if (!rootEl || typeof rootEl.querySelector !== 'function') {
                return;
            }

            if (rootEl.querySelector('[data-shared-import-root]')) {
                return;
            }

            rootEl.insertAdjacentHTML('beforeend', renderImportPanelMarkup(config));
        },
        importTitleLabel() {
            return labels.title || 'Import';
        },
        openImportPanel() {
            if (!this.canManageImports) {
                return;
            }

            this.resetImportState();
            this.isImportPanelOpen = true;
        },
        closeImportPanel() {
            this.isImportPanelOpen = false;
            this.resetImportState();
        },
        resetImportState() {
            this.selectedSource = '';
            this.previewRows = [];
            this.errors = emptyErrors();
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.hasLocalFileRows = false;
            this.selectedFileName = '';
            this.cachedFileSources = [];
            this.nextCachedFileSourceId = 1;
            this.previewSearch = '';
            this.showDuplicateRows = showDuplicatesDefault;
            this.bulkOptionsAccordionOpen = false;
            this.previewRecordsAccordionOpen = true;
            this.isLoadingPreview = false;
            this.isSubmittingImport = false;
            this.previewLoadingMessage = loadingPreviewLabel;
            this.bulkManufacturable = bulkManufacturableDefault;
            this.bulkPurchasable = bulkPurchasableDefault;
            this.bulkBaseUomId = bulkBaseUomIdDefault;
            this.createFulfillmentRecipes = createFulfillmentRecipesDefault;
            this.clearImportFileInput();
        },
        handleSourceChange() {
            this.previewRows = [];
            this.errors = emptyErrors();
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.hasLocalFileRows = false;
            this.isLoadingPreview = false;
            this.isSubmittingImport = false;
            this.previewLoadingMessage = loadingPreviewLabel;
            this.previewSearch = '';
            this.showDuplicateRows = showDuplicatesDefault;

            if (!this.selectedSource) {
                return;
            }

            if (this.isFileUploadMode()) {
                this.openImportFilePicker();
                return;
            }

            if (this.isCachedFileSource()) {
                this.restoreCachedFilePreview();
                return;
            }

            if (!this.selectedSourceEnabled() || !this.sourceConnected()) {
                return;
            }

            this.loadPreview({
                source: this.selectedSource,
                loadingMessage: loadingExternalPreviewLabel,
            });
        },
        isFileUploadMode() {
            return this.selectedSource === 'file-upload';
        },
        isCachedFileSource() {
            return this.selectedSource.startsWith('file-upload-cached:');
        },
        importSourceValue() {
            return this.isFileUploadMode() || this.isCachedFileSource() ? null : this.selectedSource;
        },
        clearImportFileInput() {
            if (this.$refs.importFileInput) {
                this.$refs.importFileInput.value = '';
            }
        },
        openImportFilePicker() {
            this.$nextTick(() => {
                this.clearImportFileInput();
                if (this.$refs.importFileInput) {
                    this.$refs.importFileInput.click();
                }
            });
        },
        sourceOptionLabel(source) {
            return source && source.label ? source.label : '';
        },
        currentCachedFileSource() {
            if (!this.isCachedFileSource()) {
                return null;
            }

            return this.cachedFileSources.find((fileSource) => fileSource.value === this.selectedSource) || null;
        },
        restoreCachedFilePreview() {
            const fileSource = this.currentCachedFileSource();

            if (!fileSource) {
                this.selectedSource = '';
                return;
            }

            this.selectedFileName = fileSource.label;
            this.previewRows = fileSource.rows.map((row) => normalizePreviewRow({
                ...row,
            }, {
                component: this,
                previewSource: this.selectedSource,
            }));
            this.hasLocalFileRows = this.previewRows.length > 0;
        },
        cacheCurrentFilePreviewRows(rows) {
            const normalizedRows = rows.map((row) => ({
                ...row,
            }));
            const value = `file-upload-cached:${this.nextCachedFileSourceId}`;

            this.nextCachedFileSourceId += 1;
            this.cachedFileSources.push({
                value,
                label: this.selectedFileName,
                rows: normalizedRows,
            });
            this.selectedSource = value;
        },
        async handleLocalFileChange(event) {
            const file = event && event.target && event.target.files ? event.target.files[0] : null;

            this.errors = emptyErrors();
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.previewRows = [];
            this.hasLocalFileRows = false;

            if (!file) {
                if (this.cachedFileSources.length === 0) {
                    this.selectedSource = '';
                }

                return;
            }

            this.selectedFileName = file.name || '';
            this.selectedSource = 'file-upload';

            if (!file.name.toLowerCase().endsWith('.csv')) {
                this.errors.file = ['Please choose a CSV file.'];
                return;
            }

            try {
                const text = await file.text();
                const parsedRows = this.parseLocalCsv(text);

                if (parsedRows === null) {
                    return;
                }

                if (parsedRows.length === 0) {
                    this.errors.file = [emptyFileRowsMessage];
                    return;
                }

                if (!endpoints.importPreview) {
                    this.previewRows = parsedRows.map((row) => normalizePreviewRow(row, {
                        component: this,
                        previewSource: 'file-upload',
                    }));
                    this.hasLocalFileRows = true;
                    this.cacheCurrentFilePreviewRows(this.previewRows);
                    return;
                }

                await this.loadPreview({
                    source: 'file-upload',
                    rows: parsedRows,
                    loadingMessage: loadingFilePreviewLabel,
                });
            } catch (error) {
                this.errors.file = [fileReadErrorMessage];
            }
        },
        parseLocalCsv(text) {
            if (parseLocalRowsHook) {
                return parseLocalRowsHook(text, {
                    component: this,
                    parseCsvRows: (value) => this.parseCsvRows(value),
                    slugify: (value) => this.slugify(value),
                    setFileError: (message) => {
                        this.errors.file = [message];
                    },
                });
            }

            const rows = this.parseCsvRows(text);

            if (rows.length < 2) {
                return [];
            }

            const headers = rows[0].map((header) => header.trim());
            const requiredHeaders = [
                'name',
                'base_uom_id',
                'is_active',
                'is_purchasable',
                'is_manufacturable',
                'default_price_amount',
                'default_price_currency_code',
                'external_source',
                'external_id',
            ];
            const hasRequiredHeaders = requiredHeaders.every((header) => headers.includes(header));

            if (!hasRequiredHeaders) {
                this.errors.file = [missingFileHeadersMessage];
                return [];
            }

            return rows
                .slice(1)
                .filter((row) => row.some((value) => value.trim() !== ''))
                .map((row, index) => {
                    const record = headers.reduce((carry, header, rowIndex) => {
                        carry[header] = row[rowIndex] ?? '';

                        return carry;
                    }, {});
                    const generatedExternalId = record.external_id !== ''
                        ? record.external_id
                        : `file-${index + 1}-${this.slugify(record.name || 'product')}`;

                    return {
                        external_id: generatedExternalId,
                        external_source: record.external_source || '',
                        name: record.name,
                        sku: '',
                        price: record.default_price_amount,
                        default_price_cents: null,
                        image_url: null,
                        is_active: this.csvBoolean(record.is_active, true),
                        is_sellable: true,
                        selected: true,
                        base_uom_id: record.base_uom_id || '',
                        is_manufacturable: this.csvBooleanOrNull(record.is_manufacturable),
                        is_purchasable: this.csvBooleanOrNull(record.is_purchasable),
                    };
                });
        },
        parseCsvRows(text) {
            const rows = [];
            let currentRow = [];
            let currentValue = '';
            let inQuotes = false;

            for (let index = 0; index < text.length; index += 1) {
                const character = text[index];
                const nextCharacter = text[index + 1] || '';

                if (character === '"') {
                    if (inQuotes && nextCharacter === '"') {
                        currentValue += '"';
                        index += 1;
                    } else {
                        inQuotes = !inQuotes;
                    }

                    continue;
                }

                if (character === ',' && !inQuotes) {
                    currentRow.push(currentValue);
                    currentValue = '';
                    continue;
                }

                if ((character === '\n' || character === '\r') && !inQuotes) {
                    if (character === '\r' && nextCharacter === '\n') {
                        index += 1;
                    }

                    currentRow.push(currentValue);
                    rows.push(currentRow);
                    currentRow = [];
                    currentValue = '';
                    continue;
                }

                currentValue += character;
            }

            if (currentValue !== '' || currentRow.length > 0) {
                currentRow.push(currentValue);
                rows.push(currentRow);
            }

            return rows;
        },
        csvBoolean(value, fallback) {
            const normalized = String(value ?? '').trim().toLowerCase();

            if (normalized === '1' || normalized === 'true' || normalized === 'yes') {
                return true;
            }

            if (normalized === '0' || normalized === 'false' || normalized === 'no') {
                return false;
            }

            return fallback;
        },
        csvBooleanOrNull(value) {
            const normalized = String(value ?? '').trim().toLowerCase();

            if (normalized === '') {
                return null;
            }

            if (normalized === '1' || normalized === 'true' || normalized === 'yes') {
                return true;
            }

            if (normalized === '0' || normalized === 'false' || normalized === 'no') {
                return false;
            }

            return null;
        },
        slugify(value) {
            return String(value ?? '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'product';
        },
        selectedSourceMeta() {
            return this.sources.find((source) => source.value === this.selectedSource) || null;
        },
        selectedSourceEnabled() {
            if (this.isFileUploadMode()) {
                return true;
            }

            const source = this.selectedSourceMeta();

            return Boolean(source && source.enabled);
        },
        sourceConnected() {
            if (this.isFileUploadMode()) {
                return false;
            }

            const source = this.selectedSourceMeta();

            return Boolean(source && source.connected);
        },
        selectedSourceStatusLabel() {
            const source = this.selectedSourceMeta();

            return source && source.status_label ? source.status_label : '';
        },
        hasSelectedImportSource() {
            return this.selectedSource !== '';
        },
        rowError(index, field) {
            const key = `rows.${index}.${field}`;
            const errors = this.importValidationErrors[key];

            return Array.isArray(errors) && errors.length > 0 ? errors[0] : '';
        },
        rowValidationMessages(index) {
            if (previewErrorFields.length === 0) {
                return [];
            }

            return previewErrorFields
                .map((field) => this.rowError(index, field))
                .filter((message) => message !== '');
        },
        setManufacturableOverride(row) {
            row.has_manufacturable_override = true;
        },
        setPurchasableOverride(row) {
            row.has_purchasable_override = true;
        },
        resolvedRowManufacturable(row) {
            if (row.has_manufacturable_override) {
                return Boolean(row.is_manufacturable);
            }

            if (this.bulkManufacturable) {
                return true;
            }

            return Boolean(row.is_manufacturable);
        },
        resolvedRowPurchasable(row) {
            if (row.has_purchasable_override) {
                return Boolean(row.is_purchasable);
            }

            if (this.bulkPurchasable) {
                return true;
            }

            return Boolean(row.is_purchasable);
        },
        buildImportRowPayload(row, importSource) {
            const defaultPayload = {
                external_id: row.external_id,
                external_source: row.external_source || importSource,
                name: row.name,
                sku: row.sku,
                default_price_cents: Object.prototype.hasOwnProperty.call(row, 'default_price_cents')
                    ? row.default_price_cents
                    : null,
                image_url: Object.prototype.hasOwnProperty.call(row, 'image_url')
                    ? row.image_url
                    : null,
                base_uom_id: row.base_uom_id === '' ? null : Number(row.base_uom_id),
                is_active: Boolean(row.is_active),
                is_sellable: true,
                is_manufacturable: this.resolvedRowManufacturable(row),
                is_purchasable: this.resolvedRowPurchasable(row),
            };

            if (!buildImportRowPayloadHook) {
                return defaultPayload;
            }

            const customPayload = buildImportRowPayloadHook(defaultPayload, row, {
                importSource,
                component: this,
            });

            return customPayload && typeof customPayload === 'object' && !Array.isArray(customPayload)
                ? customPayload
                : defaultPayload;
        },
        buildSubmitBody(importSource, rows) {
            const defaultBody = {
                source: importSource,
                is_local_file_import: this.hasLocalFileRows,
                create_fulfillment_recipes: this.createFulfillmentRecipes,
                import_all_as_manufacturable: this.bulkManufacturable,
                import_all_as_purchasable: this.bulkPurchasable,
                bulk_base_uom_id: this.bulkBaseUomId === '' ? null : Number(this.bulkBaseUomId),
                rows,
            };

            if (!buildSubmitBodyHook) {
                return defaultBody;
            }

            const customBody = buildSubmitBodyHook(defaultBody, {
                importSource,
                rows,
                component: this,
            });

            return customBody && typeof customBody === 'object' && !Array.isArray(customBody)
                ? customBody
                : defaultBody;
        },
        previewStatusLabel(row) {
            if (row.is_duplicate) {
                return 'Duplicate';
            }

            return row.is_active ? 'Active' : 'Inactive';
        },
        duplicateRowCount() {
            return this.previewRows.filter((row) => row.is_duplicate).length;
        },
        previewSearchText(row) {
            if (previewSearchExpressions.length === 0) {
                return [
                    row.name,
                    row.sku,
                    row.external_id,
                    row.external_source,
                    this.previewStatusLabel(row),
                ]
                    .filter((value) => String(value || '').trim() !== '')
                    .join(' ')
                    .toLowerCase();
            }

            try {
                const evaluate = new Function('row', 'previewStatusLabel', `
                    return [${previewSearchExpressions.join(',')}];
                `);

                return evaluate(row, (value) => this.previewStatusLabel(value))
                    .filter((value) => String(value || '').trim() !== '')
                    .join(' ')
                    .toLowerCase();
            } catch (error) {
                return '';
            }
        },
        rowMatchesPreviewSearch(row) {
            const search = this.previewSearch.trim().toLowerCase();

            if (search === '') {
                return true;
            }

            return this.previewSearchText(row).includes(search);
        },
        rowVisibleInPreview(row) {
            if (!this.rowMatchesPreviewSearch(row)) {
                return false;
            }

            if (!this.showDuplicateRows && row.is_duplicate) {
                return false;
            }

            return true;
        },
        visibleSelectablePreviewRows() {
            return this.previewRows.filter((row) => this.rowVisibleInPreview(row) && !row.is_duplicate);
        },
        visiblePreviewRowsCount() {
            return this.previewRows.filter((row) => this.rowVisibleInPreview(row)).length;
        },
        hasVisiblePreviewRows() {
            return this.visiblePreviewRowsCount() > 0;
        },
        allVisibleSelectableRowsSelected() {
            const rows = this.visibleSelectablePreviewRows();

            return rows.length > 0 && rows.every((row) => row.selected);
        },
        toggleVisibleRowSelection(event) {
            const shouldSelect = Boolean(event && event.target && event.target.checked);

            this.visibleSelectablePreviewRows().forEach((row) => {
                row.selected = shouldSelect;
            });
        },
        selectedPreviewRows() {
            return this.previewRows.filter((row) => row.selected);
        },
        selectedVisiblePreviewRows() {
            return this.previewRows.filter((row) => row.selected && this.rowVisibleInPreview(row));
        },
        selectedImportRows() {
            return submitSelectedVisibleRowsOnly
                ? this.selectedVisiblePreviewRows()
                : this.selectedPreviewRows();
        },
        previewEmptyStateTitle() {
            if (this.previewRows.length === 0) {
                return 'No preview records found';
            }

            return 'No visible preview records';
        },
        previewEmptyStateMessage() {
            if (this.previewRows.length === 0) {
                return config.resource === 'customers'
                    ? 'No importable customers were returned for the selected source.'
                    : 'No importable products were returned for the selected source.';
            }

            if (!this.showDuplicateRows && this.duplicateRowCount() > 0) {
                return 'All preview rows are currently hidden as duplicates. Enable Show Duplicates to review them.';
            }

            return 'Adjust the current filters to show matching preview records.';
        },
        async loadPreview(options = {}) {
            const previewSource = options.source || this.selectedSource;
            const previewRows = Array.isArray(options.rows) ? options.rows : null;
            const loadingMessage = options.loadingMessage || loadingPreviewLabel;

            if (!endpoints.importPreview) {
                this.previewError = previewUnavailableMessage;
                return;
            }

            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = true;
            this.previewLoadingMessage = loadingMessage;
            this.previewSearch = '';
            this.showDuplicateRows = showDuplicatesDefault;
            this.previewRows = [];

            try {
                const response = await fetch(endpoints.importPreview, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        source: previewSource,
                        rows: previewRows,
                    }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    const errorMessage = data.message || previewUnavailableMessage;

                    if (previewSource === 'file-upload') {
                        this.errors.file = [errorMessage];
                    } else {
                        this.previewError = errorMessage;
                        this.errors.source = data.errors && Array.isArray(data.errors.source) ? data.errors.source : [];
                    }

                    return;
                }

                if (!response.ok) {
                    if (previewSource === 'file-upload') {
                        this.errors.file = [filePreviewUnavailableMessage];
                    } else {
                        this.previewError = previewUnavailableMessage;
                    }

                    return;
                }

                const data = await response.json();
                this.previewRows = (((data.data || {}).rows) || []).map((row) => normalizePreviewRow({
                    ...row,
                    external_source: previewSource === 'file-upload'
                        ? (row.external_source || '')
                        : (previewSource || ''),
                    is_manufacturable: null,
                    is_purchasable: null,
                }, {
                    component: this,
                    previewSource,
                }));
                this.hasLocalFileRows = previewSource === 'file-upload';

                if (previewSource === 'file-upload') {
                    this.cacheCurrentFilePreviewRows(this.previewRows);
                }
            } catch (error) {
                if (previewSource === 'file-upload') {
                    this.errors.file = [filePreviewUnavailableMessage];
                } else {
                    this.previewError = previewUnavailableMessage;
                }
            } finally {
                this.isLoadingPreview = false;
                this.previewLoadingMessage = loadingPreviewLabel;
            }
        },
        async submitImport() {
            if (!endpoints.importStore) {
                this.importError = importUnavailableMessage;
                return;
            }

            const rows = this.selectedImportRows()
                .map((row) => this.buildImportRowPayload(row, this.importSourceValue()));

            if (rows.length === 0 && emptySelectionMessage !== '') {
                this.importError = emptySelectionMessage;
                return;
            }

            this.importError = '';
            this.importValidationErrors = {};
            this.errors = emptyErrors();
            this.isSubmittingImport = true;
            const importSource = this.importSourceValue();

            const response = await fetch(endpoints.importStore, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(this.buildSubmitBody(importSource, rows)),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.importError = data.message || importUnavailableMessage;
                this.importValidationErrors = data.errors || {};
                this.errors.source = data.errors && Array.isArray(data.errors.source) ? data.errors.source : [];
                this.isSubmittingImport = false;
                return;
            }

            if (!response.ok) {
                this.importError = importUnavailableMessage;
                this.isSubmittingImport = false;
                return;
            }

            const data = await response.json();

            if (typeof callbacks.onImportSuccess === 'function') {
                await callbacks.onImportSuccess(this, data);
            }

            this.isSubmittingImport = false;
            this.closeImportPanel();
        },
    };
}
