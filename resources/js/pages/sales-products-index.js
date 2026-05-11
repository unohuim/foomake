import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const loadingPreviewLabel = 'Loading preview...';
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const productToForm = (product) => ({
        name: product?.name || '',
        base_uom_id: product?.base_uom?.id ? String(product.base_uom.id) : '',
        is_purchasable: Boolean(product?.is_purchasable),
        is_manufacturable: Boolean(product?.is_manufacturable),
        default_price_amount: product?.price || '',
    });
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => ({
        ...action,
        handler: action.id === 'edit' ? 'openEdit(record)' : '',
    }));
    const rendererConfig = {
        ...crud,
        createHandler: 'openCreatePanel()',
        exportHandler: 'openExportPanel()',
        importHandler: 'openImportPanel()',
        state: {
            records: 'products',
            loading: 'isLoadingList',
            error: 'listError',
            search: 'search',
            sort: 'sort',
        },
        handlers: {
            searchInput: 'handleSearchInput()',
            toggleSort: 'toggleSort(column)',
            export: 'openExportPanel()',
            create: 'openCreatePanel()',
            import: 'openImportPanel()',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'productCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    };

    mountCrudRenderer(crudRootEl, rendererConfig);

    const emptyErrors = () => ({
        source: [],
        file: [],
    });

    const emptyCreateErrors = () => ({
        name: [],
        base_uom_id: [],
        default_price_amount: [],
        default_price_currency_code: [],
    });

    const emptyCreateForm = () => ({
        name: '',
        base_uom_id: '',
        is_purchasable: false,
        is_manufacturable: false,
        default_price_amount: '',
    });

    const emptySlideOvers = () => ({
        import: {
            open: false,
            title: 'Import Products',
        },
        export: {
            open: false,
            title: 'Export Products',
        },
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
        is_active: Boolean(row.is_active),
        is_manufacturable: row.is_manufacturable === null || row.is_manufacturable === undefined
            ? null
            : Boolean(row.is_manufacturable),
        is_purchasable: row.is_purchasable === null || row.is_purchasable === undefined
            ? null
            : Boolean(row.is_purchasable),
        has_manufacturable_override: Boolean(row.has_manufacturable_override),
        has_purchasable_override: Boolean(row.has_purchasable_override),
    });

    Alpine.data('salesProductsIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        products: [],
        uoms: safePayload.uoms || [],
        sources: safePayload.sources || [],
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        tenantCurrency: safePayload.tenantCurrency || '',
        canExportProducts: Boolean(crud.permissions?.showExport),
        canManageImports: Boolean(safePayload.canManageImports),
        canManageProducts: Boolean(safePayload.canManageProducts),
        canManageConnections: Boolean(safePayload.canManageConnections),
        connectorsPageUrl: safePayload.connectorsPageUrl || '',
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'name',
            direction: 'desc',
        },
        slideOvers: emptySlideOvers(),
        isCreatePanelOpen: false,
        panelMode: 'create',
        editingProductId: null,
        isCreateSubmitting: false,
        createGeneralError: '',
        createErrors: emptyCreateErrors(),
        createForm: emptyCreateForm(),
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
        showDuplicateRows: false,
        bulkOptionsAccordionOpen: false,
        previewRecordsAccordionOpen: true,
        bulkManufacturable: false,
        bulkPurchasable: false,
        bulkBaseUomId: '',
        createFulfillmentRecipes: true,
        isLoadingPreview: false,
        loadingPreviewLabel,
        previewLoadingMessage: loadingPreviewLabel,
        exportScope: 'current',
        exportError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchProducts();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        productBaseUomLabel(product) {
            const baseUom = product?.base_uom || {};

            if (baseUom.name && baseUom.symbol) {
                return `${baseUom.name} (${baseUom.symbol})`;
            }

            return baseUom.name || baseUom.symbol || '—';
        },
        formattedProductPrice(product) {
            if (product?.price && product?.currency) {
                return `${product.currency} ${product.price}`;
            }

            if (product?.price) {
                return product.price;
            }

            return '—';
        },
        productCellText(product, column) {
            switch (column) {
            case 'name':
                return product?.name || '—';
            case 'base_uom':
                return this.productBaseUomLabel(product);
            case 'price':
                return this.formattedProductPrice(product);
            default:
                return '—';
            }
        },
        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.visible = true;

            if (this.toast.timeoutId) {
                clearTimeout(this.toast.timeoutId);
            }

            this.toast.timeoutId = setTimeout(() => {
                this.toast.visible = false;
            }, 2500);
        },
        async fetchProducts() {
            if (!this.endpoints.list) {
                this.products = [];
                return;
            }

            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onValidationError: (data) => {
                    this.listError = data.message || 'Unable to load products.';
                    this.showToast('error', this.listError);
                },
                onError: () => {
                    this.listError = 'Unable to load products.';
                    this.showToast('error', this.listError);
                },
                onSuccess: (data) => {
                    this.products = Array.isArray(data.data) ? data.data : [];

                    if (data.meta?.sort) {
                        this.sort = {
                            column: data.meta.sort.column || this.sort.column,
                            direction: data.meta.sort.direction || this.sort.direction,
                        };
                    }
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchProducts();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchProducts();
        },
        normalizeCreateErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyCreateErrors();
            }

            return {
                ...emptyCreateErrors(),
                ...errors,
                name: Array.isArray(errors.name) ? errors.name : [],
                base_uom_id: Array.isArray(errors.base_uom_id) ? errors.base_uom_id : [],
                default_price_amount: Array.isArray(errors.default_price_amount) ? errors.default_price_amount : [],
                default_price_currency_code: Array.isArray(errors.default_price_currency_code)
                    ? errors.default_price_currency_code
                    : [],
            };
        },
        slideOverTitle(name) {
            return this.slideOvers[name]?.title || '';
        },
        openSlideOver(name) {
            if (!this.slideOvers[name]) {
                return;
            }

            this.slideOvers[name].open = true;
        },
        closeSlideOver(name) {
            if (!this.slideOvers[name]) {
                return;
            }

            this.slideOvers[name].open = false;
        },
        openCreatePanel() {
            if (!this.canManageProducts) {
                return;
            }

            this.panelMode = 'create';
            this.editingProductId = null;
            this.isCreatePanelOpen = true;
            this.createGeneralError = '';
            this.createErrors = emptyCreateErrors();
            this.resetCreateForm();
            this.$nextTick(() => {
                this.$refs.createProductNameInput?.focus();
            });
        },
        openEdit(product) {
            if (!this.canManageProducts) {
                return;
            }

            this.panelMode = 'edit';
            this.editingProductId = product.id;
            this.createForm = productToForm(product);
            this.createGeneralError = '';
            this.createErrors = emptyCreateErrors();
            this.isCreatePanelOpen = true;
            this.$nextTick(() => {
                this.$refs.createProductNameInput?.focus();
            });
        },
        closeCreatePanel() {
            this.isCreatePanelOpen = false;
            this.panelMode = 'create';
            this.editingProductId = null;
            this.isCreateSubmitting = false;
            this.createGeneralError = '';
            this.createErrors = emptyCreateErrors();
            this.resetCreateForm();
        },
        resetCreateForm() {
            this.createForm = emptyCreateForm();
        },
        async submitCreate() {
            const isEdit = this.panelMode === 'edit';

            if (!isEdit && !this.endpoints.create) {
                this.createGeneralError = 'Something went wrong. Please try again.';
                this.isCreateSubmitting = false;
                return;
            }

            if (isEdit && !this.updateUrlBase) {
                this.createGeneralError = 'Something went wrong. Please try again.';
                this.isCreateSubmitting = false;
                return;
            }

            this.isCreateSubmitting = true;
            this.createGeneralError = '';
            this.createErrors = emptyCreateErrors();

            if (isEdit) {
                const response = await fetch(`${this.updateUrlBase}/${this.editingProductId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(this.createForm),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.createErrors = this.normalizeCreateErrors(data.errors);
                    this.createGeneralError = data.message || 'The given data was invalid.';
                    this.isCreateSubmitting = false;
                    return;
                }

                if (!response.ok) {
                    this.createGeneralError = 'Something went wrong. Please try again.';
                    this.isCreateSubmitting = false;
                    return;
                }

                await this.fetchProducts();
                await refreshNavigationState(this.navigationStateUrl);
                this.showToast('success', 'Product updated.');
                this.closeCreatePanel();
                return;
            }

            await this.crud.submitCreate({
                body: this.createForm,
                csrfToken: this.csrfToken,
                onValidationError: (data) => {
                    this.createErrors = this.normalizeCreateErrors(data.errors);
                },
                onError: () => {
                    this.createGeneralError = 'Something went wrong. Please try again.';
                },
                onSuccess: async () => {
                    await this.fetchProducts();
                    await refreshNavigationState(this.navigationStateUrl);
                    this.showToast('success', 'Product created.');
                    this.closeCreatePanel();
                },
                onFinally: () => {
                    this.isCreateSubmitting = false;
                },
            });
        },
        openImportPanel() {
            if (!this.canManageImports) {
                return;
            }

            this.resetImportState();
            this.openSlideOver('import');
        },
        closeImportPanel() {
            this.closeSlideOver('import');
            this.resetImportState();
        },
        openExportPanel() {
            if (!this.canExportProducts) {
                return;
            }

            this.resetExportState();
            this.openSlideOver('export');
        },
        closeExportPanel() {
            this.closeSlideOver('export');
            this.resetExportState();
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
            this.showDuplicateRows = false;
            this.bulkOptionsAccordionOpen = false;
            this.previewRecordsAccordionOpen = true;
            this.isLoadingPreview = false;
            this.previewLoadingMessage = loadingPreviewLabel;
            this.bulkManufacturable = false;
            this.bulkPurchasable = false;
            this.bulkBaseUomId = '';
            this.createFulfillmentRecipes = true;
            this.clearImportFileInput();
        },
        resetExportState() {
            this.exportScope = 'current';
            this.exportError = '';
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
            this.showDuplicateRows = false;

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
                loadingMessage: 'Loading WooCommerce preview...',
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
                this.$refs.importFileInput?.click();
            });
        },
        sourceOptionLabel(source) {
            return source?.label || '';
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
            const file = event?.target?.files?.[0];

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

                if (parsedRows.length === 0) {
                    this.errors.file = ['The selected CSV file does not contain any product rows.'];
                    return;
                }

                if (!this.endpoints.importPreview) {
                    this.previewRows = parsedRows.map((row) => normalizePreviewRow(row));
                    this.hasLocalFileRows = true;
                    this.cacheCurrentFilePreviewRows(this.previewRows);
                    return;
                }

                await this.loadPreview({
                    source: 'file-upload',
                    rows: parsedRows,
                    loadingMessage: 'Loading file preview...',
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
                    const record = headers.reduce((carry, header, index) => {
                        carry[header] = row[index] ?? '';

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

            return Boolean(this.selectedSourceMeta()?.enabled);
        },
        sourceConnected() {
            if (this.isFileUploadMode()) {
                return false;
            }

            return Boolean(this.selectedSourceMeta()?.connected);
        },
        selectedSourceStatusLabel() {
            return this.selectedSourceMeta()?.status_label || '';
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
                is_active: Boolean(row.is_active),
                is_sellable: true,
                is_manufacturable: this.resolvedRowManufacturable(row),
                is_purchasable: this.resolvedRowPurchasable(row),
            };
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
            return [
                row.name,
                row.sku,
                row.external_id,
                row.external_source,
                row.price,
                this.previewStatusLabel(row),
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
            const shouldSelect = Boolean(event?.target?.checked);

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

            if (!this.endpoints.importPreview) {
                this.previewError = 'Unable to load preview.';
                return;
            }

            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = true;
            this.previewLoadingMessage = loadingMessage;
            this.previewSearch = '';
            this.showDuplicateRows = false;
            this.previewRows = [];

            try {
                const response = await fetch(this.endpoints.importPreview, {
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
                        this.errors.source = Array.isArray(data.errors?.source) ? data.errors.source : [];
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
                this.previewRows = (data.data?.rows || []).map((row) => normalizePreviewRow({
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
            if (!this.endpoints.importStore) {
                this.importError = 'Unable to import products.';
                return;
            }

            this.importError = '';
            this.importValidationErrors = {};
            this.errors = emptyErrors();
            const importSource = this.importSourceValue();

            const rows = this.selectedVisiblePreviewRows()
                .map((row) => this.buildImportRowPayload(row, importSource));

            const response = await fetch(this.endpoints.importStore, {
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
                this.errors.source = Array.isArray(data.errors?.source) ? data.errors.source : [];
                return;
            }

            if (!response.ok) {
                this.importError = 'Unable to import products.';
                return;
            }

            await this.fetchProducts();
            await refreshNavigationState(this.navigationStateUrl);
            this.showToast('success', 'Products imported.');
            this.closeImportPanel();
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
            const exportUrl = this.buildExportUrl();

            if (exportUrl === '') {
                this.exportError = 'Unable to export products.';
                return;
            }

            window.location.assign(exportUrl);
            this.closeExportPanel();
        },
    }));
}
