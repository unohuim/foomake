const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const renderExportPanelMarkup = (config) => {
    const labels = config.labels || {};

    return `
        <div data-shared-export-root>
            <div
                class="fixed inset-0 z-50 overflow-hidden"
                x-show="isExportPanelOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
                data-shared-export-panel
            >
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                        x-show="isExportPanelOpen"
                        x-on:click="closeExportPanel()"
                    ></div>

                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div class="pointer-events-auto w-screen max-w-md">
                            <div class="flex h-full flex-col bg-white shadow-xl">
                                <div class="flex-1 overflow-y-auto p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h2 class="text-lg font-medium text-gray-900">${escapeHtml(labels.exportTitle || 'Export')}</h2>
                                            <p class="mt-1 text-sm text-gray-600">${escapeHtml(labels.exportDescription || 'Export records as CSV using the current list state when needed.')}</p>
                                        </div>
                                        <button type="button" class="rounded-md text-gray-400 hover:text-gray-500" x-on:click="closeExportPanel()">
                                            <span class="sr-only">Close panel</span>
                                            ✕
                                        </button>
                                    </div>

                                    <div class="mt-6 space-y-4">
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                            <h3 class="text-sm font-semibold text-gray-900">Format</h3>
                                            <p class="mt-1 text-sm text-gray-600">${escapeHtml(labels.exportFormatLabel || 'CSV')}</p>
                                        </div>

                                        <fieldset class="space-y-3">
                                            <legend class="text-sm font-medium text-gray-700">${escapeHtml(labels.exportScopeLegend || 'Export Scope')}</legend>

                                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                                                <input type="radio" class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500" value="current" x-model="exportScope">
                                                <div>
                                                    <p class="font-medium text-gray-900">${escapeHtml(labels.exportCurrentOptionTitle || 'Current filters and sort')}</p>
                                                    <p class="mt-1 text-gray-600">${escapeHtml(labels.exportCurrentOptionDescription || 'Uses the current search text and sort order from the list.')}</p>
                                                </div>
                                            </label>

                                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-sm text-gray-700">
                                                <input type="radio" class="mt-0.5 border-gray-300 text-blue-600 focus:ring-blue-500" value="all" x-model="exportScope">
                                                <div>
                                                    <p class="font-medium text-gray-900">${escapeHtml(labels.exportAllOptionTitle || 'All records')}</p>
                                                    <p class="mt-1 text-gray-600">${escapeHtml(labels.exportAllOptionDescription || 'Exports every record in the current tenant.')}</p>
                                                </div>
                                            </label>
                                        </fieldset>

                                        <p class="text-sm text-red-600" x-text="exportError"></p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 px-6 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                                        x-on:click="closeExportPanel()"
                                    >
                                        ${escapeHtml(labels.exportCancelLabel || 'Cancel')}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-500"
                                        x-bind:disabled="isExportSubmitting"
                                        x-bind:class="isExportSubmitting ? 'cursor-not-allowed opacity-60' : ''"
                                        x-on:click="submitExport()"
                                    >
                                        ${escapeHtml(labels.exportSubmitLabel || 'Export CSV')}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};

export function createExportModule(options = {}) {
    const config = options.config && typeof options.config === 'object' ? options.config : options;
    const endpoints = config.endpoints || {};
    const labels = config.labels || {};
    const permissions = config.permissions || {};
    const initialScope = typeof options.initialScope === 'string' && options.initialScope.trim() !== ''
        ? options.initialScope.trim()
        : 'current';
    const unavailableMessage = typeof labels.exportUnavailableMessage === 'string' && labels.exportUnavailableMessage.trim() !== ''
        ? labels.exportUnavailableMessage.trim()
        : 'Unable to export records.';
    const canExport = permissions.showExport === true;

    return {
        isExportPanelOpen: false,
        exportScope: initialScope,
        exportError: '',
        isExportSubmitting: false,
        mount(rootEl) {
            if (!rootEl || typeof rootEl.querySelector !== 'function') {
                return;
            }

            if (rootEl.querySelector('[data-shared-export-root]')) {
                return;
            }

            rootEl.insertAdjacentHTML('beforeend', renderExportPanelMarkup(config));
        },
        openExportPanel() {
            if (!canExport) {
                return;
            }

            this.resetExportState();
            this.isExportPanelOpen = true;
        },
        closeExportPanel() {
            this.isExportPanelOpen = false;
            this.resetExportState();
        },
        resetExportState() {
            this.exportScope = initialScope;
            this.exportError = '';
            this.isExportSubmitting = false;
        },
        buildExportUrl() {
            if (!endpoints.export) {
                return '';
            }

            const exportUrl = new URL(endpoints.export, window.location.origin);

            if (this.exportScope === 'all') {
                exportUrl.searchParams.set('scope', 'all');

                return exportUrl.toString();
            }

            exportUrl.searchParams.set('scope', 'current');

            if (typeof this.search === 'string' && this.search.trim() !== '') {
                exportUrl.searchParams.set('search', this.search.trim());
            }

            if (this.isSortableColumn(this.sort?.column) && ['asc', 'desc'].includes(this.sort?.direction)) {
                exportUrl.searchParams.set('sort', this.sort.column);
                exportUrl.searchParams.set('direction', this.sort.direction);
            }

            return exportUrl.toString();
        },
        submitExport() {
            this.isExportSubmitting = true;

            const exportUrl = this.buildExportUrl();

            if (exportUrl === '') {
                this.exportError = unavailableMessage;
                this.isExportSubmitting = false;
                return;
            }

            window.location.assign(exportUrl);
            this.closeExportPanel();
            this.isExportSubmitting = false;
        },
    };
}
