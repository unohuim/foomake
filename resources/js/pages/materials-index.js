import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => ({
        ...action,
        handler: action.id === 'edit' ? 'openEdit(record)' : action.id === 'delete' ? 'openDelete(record)' : '',
    }));
    const rendererConfig = {
        ...crud,
        state: {
            records: 'materials',
            loading: 'isLoadingList',
            error: 'listError',
            search: 'search',
            sort: 'sort',
        },
        handlers: {
            searchInput: 'handleSearchInput()',
            toggleSort: 'toggleSort(column)',
            create: 'openCreate()',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'materialCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    };

    mountCrudRenderer(crudRootEl, rendererConfig);

    const emptyErrors = () => ({
        name: [],
        base_uom_id: [],
        default_price_amount: [],
        default_price_currency_code: [],
    });

    const emptyForm = () => ({
        name: '',
        base_uom_id: '',
        is_purchasable: false,
        is_sellable: false,
        is_manufacturable: false,
        default_price_amount: '',
    });

    const buildItemEndpoint = (template, itemId) => {
        if (!template || itemId === null || itemId === undefined) {
            return '';
        }

        return template.replace('{id}', encodeURIComponent(String(itemId)));
    };

    Alpine.data('materialsIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        materials: [],
        uoms: Array.isArray(safePayload.uoms) ? safePayload.uoms : [],
        uomsById: {},
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        tenantCurrency: safePayload.tenantCurrency || '',
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'name',
            direction: 'asc',
        },
        isCreateOpen: false,
        isSubmitting: false,
        errors: emptyErrors(),
        generalError: '',
        form: emptyForm(),
        isEditOpen: false,
        isEditSubmitting: false,
        editErrors: emptyErrors(),
        editGeneralError: '',
        editItemId: null,
        editBaseUomLocked: false,
        editForm: emptyForm(),
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteError: '',
        deleteItemId: null,
        deleteItemName: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.uomsById = this.uoms.reduce((carry, uom) => {
                carry[uom.id] = uom;

                return carry;
            }, {});
            this.fetchMaterials();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        materialBaseUomLabel(record) {
            const name = record?.base_uom_name || '';
            const symbol = record?.base_uom_symbol || '';

            if (name && symbol) {
                return `${name} (${symbol})`;
            }

            return name || symbol || '—';
        },
        materialFlagsLabel(record) {
            const flags = [];

            if (record?.is_purchasable) {
                flags.push('Purchasable');
            }

            if (record?.is_sellable) {
                flags.push('Sellable');
            }

            if (record?.is_manufacturable) {
                flags.push('Manufacturable');
            }

            return flags.length > 0 ? flags.join(', ') : '—';
        },
        materialCellText(record, column) {
            if (column === 'base_uom') {
                return this.materialBaseUomLabel(record);
            }

            if (column === 'flags') {
                return this.materialFlagsLabel(record);
            }

            return record?.[column] || '—';
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
        async fetchMaterials() {
            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.materials = Array.isArray(data?.data) ? data.data : [];

                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: () => {
                    this.listError = 'Unable to load materials.';
                },
                onError: () => {
                    this.listError = 'Unable to load materials.';
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchMaterials();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchMaterials();
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                name: Array.isArray(errors.name) ? errors.name : [],
                base_uom_id: Array.isArray(errors.base_uom_id) ? errors.base_uom_id : [],
                default_price_amount: Array.isArray(errors.default_price_amount) ? errors.default_price_amount : [],
                default_price_currency_code: Array.isArray(errors.default_price_currency_code)
                    ? errors.default_price_currency_code
                    : [],
            };
        },
        openCreate() {
            if (!this.crud.permissions?.showCreate) {
                return;
            }

            this.isCreateOpen = true;
            this.generalError = '';
            this.errors = emptyErrors();
            this.form = emptyForm();
            this.$nextTick(() => {
                this.$refs.createMaterialNameInput?.focus();
            });
        },
        closeCreate() {
            this.isCreateOpen = false;
            this.isSubmitting = false;
            this.generalError = '';
            this.errors = emptyErrors();
            this.form = emptyForm();
        },
        openEdit(record) {
            this.editItemId = record.id;
            this.editForm = {
                name: record.name || '',
                base_uom_id: record.base_uom_id ? String(record.base_uom_id) : '',
                is_purchasable: Boolean(record.is_purchasable),
                is_sellable: Boolean(record.is_sellable),
                is_manufacturable: Boolean(record.is_manufacturable),
                default_price_amount: record.default_price_amount || '',
            };
            this.editBaseUomLocked = Boolean(record.has_stock_moves);
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
            this.isEditOpen = true;
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
            this.editItemId = null;
            this.editBaseUomLocked = false;
            this.editForm = emptyForm();
        },
        openDelete(record) {
            this.deleteItemId = record.id;
            this.deleteItemName = record.name || '';
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteError = '';
            this.deleteItemId = null;
            this.deleteItemName = '';
        },
        async submitCreate() {
            this.isSubmitting = true;
            this.generalError = '';
            this.errors = emptyErrors();

            await this.crud.submitCreate({
                body: this.form,
                csrfToken: this.csrfToken,
                onValidationError: (data) => {
                    this.errors = this.normalizeErrors(data.errors);
                    this.generalError = data.message || 'The given data was invalid.';
                },
                onError: () => {
                    this.generalError = 'Something went wrong. Please try again.';
                },
                onSuccess: async (data) => {
                    const redirectUrl = this.crud.buildDetailUrl(data?.data);

                    if (redirectUrl) {
                        window.location.assign(redirectUrl);
                        return;
                    }

                    await this.fetchMaterials();
                    await refreshNavigationState(this.navigationStateUrl);
                    this.closeCreate();
                    this.showToast('success', 'Material created.');
                },
                onFinally: () => {
                    this.isSubmitting = false;
                },
            });
        },
        async submitEdit() {
            const endpoint = buildItemEndpoint(this.endpoints.update, this.editItemId);

            if (!endpoint) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                return;
            }

            this.isEditSubmitting = true;
            this.editGeneralError = '';
            this.editErrors = emptyErrors();

            const response = await fetch(endpoint, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(this.editForm),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.editErrors = this.normalizeErrors(data.errors);
                this.editGeneralError = data.message || 'The given data was invalid.';
                this.isEditSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                this.isEditSubmitting = false;
                return;
            }

            await this.fetchMaterials();
            await refreshNavigationState(this.navigationStateUrl);
            this.closeEdit();
            this.showToast('success', 'Material updated.');
        },
        async submitDelete() {
            const endpoint = buildItemEndpoint(this.endpoints.delete, this.deleteItemId);

            if (!endpoint) {
                this.deleteError = 'Something went wrong. Please try again.';
                return;
            }

            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const response = await fetch(endpoint, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.deleteError = data.message || 'Material cannot be deleted.';
                this.isDeleteSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.deleteError = 'Something went wrong. Please try again.';
                this.isDeleteSubmitting = false;
                return;
            }

            await this.fetchMaterials();
            await refreshNavigationState(this.navigationStateUrl);
            this.closeDelete();
            this.showToast('success', 'Material deleted.');
        },
    }));
}
