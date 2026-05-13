export function createExportModule(options = {}) {
    const permissionKey = typeof options.permissionKey === 'string' && options.permissionKey.trim() !== ''
        ? options.permissionKey.trim()
        : 'canExportProducts';
    const unavailableMessage = typeof options.unavailableMessage === 'string' && options.unavailableMessage.trim() !== ''
        ? options.unavailableMessage.trim()
        : 'Unable to export records.';
    const initialScope = typeof options.initialScope === 'string' && options.initialScope.trim() !== ''
        ? options.initialScope.trim()
        : 'current';

    return {
        exportScope: initialScope,
        exportError: '',
        isExportSubmitting: false,
        openExportPanel() {
            if (!this[permissionKey]) {
                return;
            }

            this.resetExportState();
            this.openSlideOver('export');
        },
        closeExportPanel() {
            this.closeSlideOver('export');
            this.resetExportState();
        },
        resetExportState() {
            this.exportScope = initialScope;
            this.exportError = '';
            this.isExportSubmitting = false;
        },
        buildExportUrl() {
            if (!this.endpoints.export) {
                return '';
            }

            const exportUrl = new URL(this.endpoints.export, window.location.origin);

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
