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
    });

    const emptyCreateErrors = () => ({
        name: [],
        base_uom_id: [],
        default_price_amount: [],
        default_price_currency_code: [],
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
        isCreatePanelOpen: false,
        panelMode: 'create',
        editingProductId: null,
        isCreateSubmitting: false,
        createGeneralError: '',
        createErrors: emptyCreateErrors(),
        createForm: {
            name: '',
            base_uom_id: '',
            is_purchasable: false,
            is_manufacturable: false,
            default_price_amount: '',
        },
        isImportPanelOpen: false,
        selectedSource: '',
        previewRows: [],
        errors: emptyErrors(),
        previewError: '',
        importError: '',
        importValidationErrors: {},
        bulkManufacturable: false,
        bulkPurchasable: false,
        bulkBaseUomId: '',
        createFulfillmentRecipes: true,
        isLoadingPreview: false,
        loadingPreviewLabel,
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
            this.createForm = {
                name: '',
                base_uom_id: '',
                is_purchasable: false,
                is_manufacturable: false,
                default_price_amount: '',
            };
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

            this.isImportPanelOpen = true;
            this.resetImportState();
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
            this.isLoadingPreview = false;
            this.bulkManufacturable = false;
            this.bulkPurchasable = false;
            this.bulkBaseUomId = '';
            this.createFulfillmentRecipes = true;
        },
        handleSourceChange() {
            this.previewRows = [];
            this.errors = emptyErrors();
            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = false;
        },
        selectedSourceMeta() {
            return this.sources.find((source) => source.value === this.selectedSource) || null;
        },
        selectedSourceEnabled() {
            return Boolean(this.selectedSourceMeta()?.enabled);
        },
        sourceConnected() {
            return Boolean(this.selectedSourceMeta()?.connected);
        },
        selectedSourceStatusLabel() {
            return this.selectedSourceMeta()?.status_label || '';
        },
        rowError(index, field) {
            const key = `rows.${index}.${field}`;
            const errors = this.importValidationErrors[key];

            return Array.isArray(errors) && errors.length > 0 ? errors[0] : '';
        },
        async loadPreview() {
            if (!this.endpoints.importPreview) {
                this.previewError = 'Unable to load preview.';
                return;
            }

            this.previewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = true;

            try {
                const response = await fetch(this.endpoints.importPreview, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        source: this.selectedSource,
                    }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.previewError = data.message || 'Unable to load preview.';
                    this.errors.source = Array.isArray(data.errors?.source) ? data.errors.source : [];
                    return;
                }

                if (!response.ok) {
                    this.previewError = 'Unable to load preview.';
                    return;
                }

                const data = await response.json();
                this.previewRows = (data.data?.rows || []).map((row) => ({
                    ...row,
                    selected: true,
                    base_uom_id: row.base_uom_id ? String(row.base_uom_id) : '',
                    is_manufacturable: null,
                    is_purchasable: null,
                }));
            } catch (error) {
                this.previewError = 'Unable to load preview.';
            } finally {
                this.isLoadingPreview = false;
            }
        },
        async submitImport() {
            if (!this.endpoints.importStore) {
                this.importError = 'Unable to import products.';
                return;
            }

            this.importError = '';
            this.importValidationErrors = {};

            const rows = this.previewRows
                .filter((row) => row.selected)
                .map((row) => {
                    const payload = {
                        external_id: row.external_id,
                        name: row.name,
                        sku: row.sku,
                        base_uom_id: row.base_uom_id === '' ? null : Number(row.base_uom_id),
                        is_active: Boolean(row.is_active),
                    };

                    if (row.is_manufacturable !== null && row.is_manufacturable !== undefined) {
                        payload.is_manufacturable = Boolean(row.is_manufacturable);
                    }

                    if (row.is_purchasable !== null && row.is_purchasable !== undefined) {
                        payload.is_purchasable = Boolean(row.is_purchasable);
                    }

                    return payload;
                });

            const response = await fetch(this.endpoints.importStore, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    source: this.selectedSource,
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
    }));
}
