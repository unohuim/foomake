import { parseCrudConfig } from '../lib/crud-config';
import { parseImportConfig } from '../lib/import-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';
import { createImportModule } from '../lib/import-module';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const importConfig = parseImportConfig(rootEl);
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
            title: importConfig.labels?.title || 'Import Products',
        },
        export: {
            open: false,
            title: 'Export Products',
        },
    });

    const importModule = createImportModule({
        config: importConfig,
        callbacks: {
            onImportSuccess: async (component) => {
                await component.fetchProducts();
                await refreshNavigationState(component.navigationStateUrl);
                component.showToast('success', 'Products imported.');
            },
        },
    });

    Alpine.data('salesProductsIndex', () => ({
        ...importModule,
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        products: [],
        uoms: Array.isArray(importConfig.uoms) && importConfig.uoms.length > 0
            ? importConfig.uoms
            : (safePayload.uoms || []),
        sources: Array.isArray(importConfig.sources) && importConfig.sources.length > 0
            ? importConfig.sources
            : (safePayload.sources || []),
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        tenantCurrency: safePayload.tenantCurrency || '',
        canExportProducts: Boolean(crud.permissions?.showExport),
        canManageImports: Boolean(importConfig.permissions?.canManageImports ?? safePayload.canManageImports),
        canManageProducts: Boolean(safePayload.canManageProducts),
        canManageConnections: Boolean(importConfig.permissions?.canManageConnections ?? safePayload.canManageConnections),
        connectorsPageUrl: importConfig.connectorsPageUrl || safePayload.connectorsPageUrl || '',
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
        resetExportState() {
            this.exportScope = 'current';
            this.exportError = '';
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
