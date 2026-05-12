import { normalizeImportConfig } from './import-config';

export function createImportModule(options = {}) {
    const config = normalizeImportConfig(options.config || options);
    const callbacks = options.callbacks && typeof options.callbacks === 'object' ? options.callbacks : {};
    const endpointConfig = config.endpoints && typeof config.endpoints === 'object' ? config.endpoints : {};
    const labels = config.labels && typeof config.labels === 'object' ? config.labels : {};
    const endpoints = {
        importPreview: endpointConfig.preview || '',
        importStore: endpointConfig.store || '',
    };
    const loadingPreviewLabel = labels.loadingPreviewDefault || 'Loading preview...';
    const loadingFilePreviewLabel = labels.loadingPreviewFile || 'Loading file preview...';
    const loadingExternalPreviewLabel = labels.loadingPreviewExternal || 'Loading WooCommerce preview...';
    const rowBehavior = config.rowBehavior || {};
    const bulkOptions = config.bulkOptions || {};
    const recipeOption = bulkOptions.create_fulfillment_recipes && typeof bulkOptions.create_fulfillment_recipes === 'object'
        ? bulkOptions.create_fulfillment_recipes
        : {};
    const hideDuplicatesByDefault = rowBehavior.hideDuplicatesByDefault === true;
    const selectVisibleNonDuplicateRowsOnly = rowBehavior.selectVisibleNonDuplicateRowsOnly === true;
    const submitSelectedVisibleRowsOnly = rowBehavior.submitSelectedVisibleRowsOnly !== false;
    const createFulfillmentRecipesDefault = recipeOption.default !== false;
    const manufacturableOption = bulkOptions.import_all_as_manufacturable || {};
    const purchasableOption = bulkOptions.import_all_as_purchasable || {};
    const bulkManufacturableDefault = manufacturableOption.default === true;
    const bulkPurchasableDefault = purchasableOption.default === true;
    const bulkBaseUomOption = bulkOptions.bulk_base_uom_id && typeof bulkOptions.bulk_base_uom_id === 'object'
        ? bulkOptions.bulk_base_uom_id
        : {};
    const bulkBaseUomIdDefault = bulkBaseUomOption.default || '';
    const localCsvParser = typeof callbacks.parseLocalCsv === 'function' ? callbacks.parseLocalCsv : null;

    const emptyErrors = () => ({
        source: [],
        file: [],
    });

    const normalizePreviewRow = (row) => ({
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
        has_active_state: Object.prototype.hasOwnProperty.call(row, 'is_active'),
        is_active: Object.prototype.hasOwnProperty.call(row, 'is_active')
            ? Boolean(row.is_active)
            : null,
        is_manufacturable: row.is_manufacturable === null || row.is_manufacturable === undefined
            ? null
            : Boolean(row.is_manufacturable),
        is_purchasable: row.is_purchasable === null || row.is_purchasable === undefined
            ? null
            : Boolean(row.is_purchasable),
        has_manufacturable_override: Boolean(row.has_manufacturable_override),
        has_purchasable_override: Boolean(row.has_purchasable_override),
    });

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
        showDuplicateRows: !hideDuplicatesByDefault,
        bulkOptionsAccordionOpen: false,
        previewRecordsAccordionOpen: true,
        bulkManufacturable: bulkManufacturableDefault,
        bulkPurchasable: bulkPurchasableDefault,
        bulkBaseUomId: bulkBaseUomIdDefault,
        createFulfillmentRecipes: createFulfillmentRecipesDefault,
        isLoadingPreview: false,
        loadingPreviewLabel,
        previewLoadingMessage: loadingPreviewLabel,
        openImportPanel() {
            if (!this.canManageImports) {
                return;
            }

            this.resetImportState();

            if (typeof this.openSlideOver === 'function') {
                this.openSlideOver('import');
                return;
            }

            if (this.slideOvers && this.slideOvers.import) {
                this.slideOvers.import.open = true;
            }
        },
        closeImportPanel() {
            if (typeof this.closeSlideOver === 'function') {
                this.closeSlideOver('import');
            } else if (this.slideOvers && this.slideOvers.import) {
                this.slideOvers.import.open = false;
            }

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
            this.showDuplicateRows = !hideDuplicatesByDefault;
            this.bulkOptionsAccordionOpen = false;
            this.previewRecordsAccordionOpen = true;
            this.isLoadingPreview = false;
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
            this.previewLoadingMessage = loadingPreviewLabel;
            this.previewSearch = '';
            this.showDuplicateRows = !hideDuplicatesByDefault;

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
                if (this.$refs && this.$refs.importFileInput && typeof this.$refs.importFileInput.click === 'function') {
                    this.$refs.importFileInput.click();
                }
            });
        },
        sourceOptionLabel(source) {
            if (!source || typeof source !== 'object') {
                return '';
            }

            return source.label || '';
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
            const target = event && event.target && typeof event.target === 'object' ? event.target : null;
            const files = target && Array.isArray(target.files) === false ? target.files : null;
            const file = files && files[0] ? files[0] : null;

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
                const parsedRowsResult = localCsvParser
                    ? localCsvParser(text, this)
                    : this.parseLocalCsv(text);
                const parsedRows = Array.isArray(parsedRowsResult) ? parsedRowsResult : [];

                if (parsedRows.length === 0) {
                    if (!Array.isArray(this.errors.file) || this.errors.file.length === 0) {
                        this.errors.file = ['The selected CSV file does not contain any product rows.'];
                    }

                    return;
                }

                if (!endpoints.importPreview) {
                    this.previewRows = parsedRows.map((row) => normalizePreviewRow(row));
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
                this.errors.file = ['The selected CSV file could not be read.'];
            }
        },
        parseLocalCsv(text) {
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
                this.errors.file = ['The selected CSV file is missing one or more required product headers.'];
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

            const sourceMeta = this.selectedSourceMeta();

            return Boolean(sourceMeta && sourceMeta.enabled);
        },
        sourceConnected() {
            if (this.isFileUploadMode()) {
                return false;
            }

            const sourceMeta = this.selectedSourceMeta();

            return Boolean(sourceMeta && sourceMeta.connected);
        },
        selectedSourceStatusLabel() {
            const sourceMeta = this.selectedSourceMeta();

            return sourceMeta && sourceMeta.status_label ? sourceMeta.status_label : '';
        },
        hasSelectedImportSource() {
            return this.selectedSource !== '';
        },
        rowError(index, field) {
            const key = `rows.${index}.${field}`;
            const errors = this.importValidationErrors[key];

            return Array.isArray(errors) && errors.length > 0 ? errors[0] : '';
        },
        rowHasError(index, field) {
            return this.rowError(index, field) !== '';
        },
        rowProductErrors(index) {
            return [
                this.rowError(index, 'name'),
                this.rowError(index, 'base_uom_id'),
            ].filter((value) => value !== '');
        },
        rowHasProductErrors(index) {
            return this.rowProductErrors(index).length > 0;
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
            return {
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
                is_active: this.rowHasActiveState(row) ? this.rowIsActive(row) : null,
                is_sellable: true,
                is_manufacturable: this.resolvedRowManufacturable(row),
                is_purchasable: this.resolvedRowPurchasable(row),
            };
        },
        previewPrimaryLabel(row) {
            if (!row || typeof row !== 'object') {
                return '—';
            }

            return row.name || row.title || row.sku || row.external_id || '—';
        },
        previewSecondaryLabel(row) {
            if (!row || typeof row !== 'object') {
                return '';
            }

            return typeof row.city === 'string' && row.city.trim() !== '' ? row.city.trim() : '';
        },
        rowHasSecondaryLabel(row) {
            return this.previewSecondaryLabel(row) !== '';
        },
        rowHasActiveState(row) {
            return Boolean(row && typeof row === 'object' && row.has_active_state === true);
        },
        rowIsActive(row) {
            return Boolean(row && typeof row === 'object' && row.is_active === true);
        },
        previewStatusLabel(row) {
            if (this.rowIsDuplicate(row)) {
                return 'Duplicate';
            }

            if (!this.rowHasActiveState(row)) {
                return '';
            }

            return this.rowIsActive(row) ? 'Active' : 'Inactive';
        },
        rowIsDuplicate(row) {
            return Boolean(row && typeof row === 'object' && row.is_duplicate);
        },
        rowSelectionDisabled(row) {
            return selectVisibleNonDuplicateRowsOnly && this.rowIsDuplicate(row);
        },
        duplicateRowCount() {
            return this.previewRows.filter((row) => this.rowIsDuplicate(row)).length;
        },
        previewSearchText(row) {
            return [
                this.previewPrimaryLabel(row),
                this.previewSecondaryLabel(row),
                row.sku,
                row.external_id,
                row.external_source,
                row.price,
            ]
                .filter((value) => String(value || '').trim() !== '')
                .join(' ')
                .toLowerCase();
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

            if (!this.showDuplicateRows && this.rowIsDuplicate(row)) {
                return false;
            }

            return true;
        },
        visibleSelectablePreviewRows() {
            return this.previewRows.filter((row) => this.rowVisibleInPreview(row) && !this.rowSelectionDisabled(row));
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
            const target = event && event.target && typeof event.target === 'object' ? event.target : null;
            const shouldSelect = Boolean(target && target.checked);

            this.visibleSelectablePreviewRows().forEach((row) => {
                row.selected = shouldSelect;
            });
        },
        selectedPreviewRows() {
            return this.previewRows.filter((row) => row.selected);
        },
        selectedVisiblePreviewRows() {
            const rows = this.previewRows.filter((row) => row.selected);

            if (!submitSelectedVisibleRowsOnly) {
                return rows;
            }

            return rows.filter((row) => this.rowVisibleInPreview(row));
        },
        previewEmptyStateTitle() {
            if (this.previewRows.length === 0) {
                return 'No preview records found';
            }

            return 'No visible preview records';
        },
        previewEmptyStateMessage() {
            if (this.previewRows.length === 0) {
                return 'No importable products were returned for the selected source.';
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
                this.previewError = 'Unable to load preview.';
                return;
            }

            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = true;
            this.previewLoadingMessage = loadingMessage;
            this.previewSearch = '';
            this.showDuplicateRows = !hideDuplicatesByDefault;
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
                    const errorMessage = data.message || 'Unable to load preview.';

                    if (previewSource === 'file-upload') {
                        this.errors.file = [errorMessage];
                    } else {
                        this.previewError = errorMessage;
                        const responseErrors = data && data.errors && typeof data.errors === 'object' ? data.errors : {};

                        this.errors.source = Array.isArray(responseErrors.source) ? responseErrors.source : [];
                    }

                    return;
                }

                if (!response.ok) {
                    if (previewSource === 'file-upload') {
                        this.errors.file = ['The selected CSV file could not be previewed.'];
                    } else {
                        this.previewError = 'Unable to load preview.';
                    }

                    return;
                }

                const data = await response.json();
                const responseData = data && data.data && typeof data.data === 'object' ? data.data : {};
                const responseRows = Array.isArray(responseData.rows) ? responseData.rows : [];

                this.previewRows = responseRows.map((row) => normalizePreviewRow({
                    ...row,
                    external_source: previewSource === 'file-upload'
                        ? (row.external_source || '')
                        : (previewSource || ''),
                    is_manufacturable: null,
                    is_purchasable: null,
                }));
                this.hasLocalFileRows = previewSource === 'file-upload';

                if (previewSource === 'file-upload') {
                    this.cacheCurrentFilePreviewRows(this.previewRows);
                }
            } catch (error) {
                if (previewSource === 'file-upload') {
                    this.errors.file = ['The selected CSV file could not be previewed.'];
                } else {
                    this.previewError = 'Unable to load preview.';
                }
            } finally {
                this.isLoadingPreview = false;
                this.previewLoadingMessage = loadingPreviewLabel;
            }
        },
        async submitImport() {
            if (!endpoints.importStore) {
                this.importError = 'Unable to import products.';
                return;
            }

            this.importError = '';
            this.importValidationErrors = {};
            this.errors = emptyErrors();
            const importSource = this.importSourceValue();

            const rows = this.selectedVisiblePreviewRows()
                .map((row) => this.buildImportRowPayload(row, importSource));

            const response = await fetch(endpoints.importStore, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    source: importSource,
                    is_local_file_import: this.hasLocalFileRows,
                    create_fulfillment_recipes: this.createFulfillmentRecipes,
                    import_all_as_manufacturable: this.bulkManufacturable,
                    import_all_as_purchasable: this.bulkPurchasable,
                    bulk_base_uom_id: this.bulkBaseUomId === '' ? null : Number(this.bulkBaseUomId),
                    rows,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.importError = data.message || 'Unable to import products.';
                this.importValidationErrors = data.errors || {};
                this.errors.source = Array.isArray(data.errors && data.errors.source) ? data.errors.source : [];
                return;
            }

            if (!response.ok) {
                this.importError = 'Unable to import products.';
                return;
            }

            const data = await response.json();

            if (typeof callbacks.onImportSuccess === 'function') {
                await callbacks.onImportSuccess(this, data);
            }

            this.closeImportPanel();
        },
    };
}
