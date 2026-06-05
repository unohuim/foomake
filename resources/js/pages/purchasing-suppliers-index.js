import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const emptyErrors = () => ({
        company_name: [],
        url: [],
        phone: [],
        email: [],
        currency_code: [],
    });
    const emptyForm = () => ({
        company_name: '',
        url: '',
        phone: '',
        email: '',
        currency_code: safePayload.defaultCurrency || '',
    });
    const supplierToForm = (supplier) => ({
        company_name: supplier?.company_name || '',
        url: supplier?.url || '',
        phone: supplier?.phone || '',
        email: supplier?.email || '',
        currency_code: supplier?.currency_code || '',
    });
    const nullableString = (value) => (value === '' ? null : value);
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => ({
        ...action,
        handler: action.id === 'edit' ? 'openEdit(record)' : action.id === 'archive' ? 'openArchive(record)' : '',
    }));

    mountCrudRenderer(crudRootEl, {
        ...crud,
        state: {
            records: 'suppliers',
            loading: 'isLoadingList',
            error: 'listError',
            search: 'search',
            sort: 'sort',
        },
        handlers: {
            searchInput: 'handleSearchInput()',
            toggleSort: 'toggleSort(column)',
            create: 'openCreatePanel()',
            import: '',
            export: '',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'supplierCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    });

    Alpine.data('purchasingSuppliersIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        suppliers: Array.isArray(safePayload.suppliers) ? safePayload.suppliers : [],
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        defaultCurrency: safePayload.defaultCurrency || '',
        canManageSuppliers: Boolean(safePayload.canManageSuppliers),
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'company_name',
            direction: 'asc',
        },
        isFormOpen: false,
        isSubmitting: false,
        formMode: 'create',
        editingSupplierId: null,
        form: emptyForm(),
        errors: emptyErrors(),
        generalError: '',
        isArchiveOpen: false,
        isArchiveSubmitting: false,
        archiveError: '',
        archiveSupplierId: null,
        archiveSupplierName: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchSuppliers();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        supplierCellText(supplier, column) {
            switch (column) {
            case 'company_name':
                return supplier?.company_name || '-';
            case 'phone':
                return supplier?.phone || '-';
            case 'email':
                return supplier?.email || '-';
            case 'currency_code':
                return supplier?.currency_code || '-';
            default:
                return '-';
            }
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                company_name: Array.isArray(errors.company_name) ? errors.company_name : [],
                url: Array.isArray(errors.url) ? errors.url : [],
                phone: Array.isArray(errors.phone) ? errors.phone : [],
                email: Array.isArray(errors.email) ? errors.email : [],
                currency_code: Array.isArray(errors.currency_code) ? errors.currency_code : [],
            };
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
            }, 1500);
        },
        async fetchSuppliers() {
            if (!this.endpoints.list) {
                this.suppliers = [];
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
                    this.listError = data.message || 'Unable to load suppliers.';
                    this.showToast('error', this.listError);
                },
                onError: () => {
                    this.listError = 'Unable to load suppliers.';
                    this.showToast('error', this.listError);
                },
                onSuccess: (data) => {
                    this.suppliers = Array.isArray(data.data) ? data.data : [];

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
            this.fetchSuppliers();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchSuppliers();
        },
        openCreatePanel() {
            if (!this.canManageSuppliers) {
                return;
            }

            this.formMode = 'create';
            this.editingSupplierId = null;
            this.form = emptyForm();
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
            this.$nextTick(() => {
                this.$refs.supplierNameInput?.focus();
            });
        },
        openEdit(supplier) {
            if (!this.canManageSuppliers) {
                return;
            }

            this.formMode = 'edit';
            this.editingSupplierId = supplier.id;
            this.form = supplierToForm(supplier);
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
            this.$nextTick(() => {
                this.$refs.supplierNameInput?.focus();
            });
        },
        closeForm() {
            this.isFormOpen = false;
            this.isSubmitting = false;
            this.formMode = 'create';
            this.editingSupplierId = null;
            this.form = emptyForm();
            this.errors = emptyErrors();
            this.generalError = '';
        },
        async submitForm() {
            if (this.formMode === 'edit') {
                await this.submitEdit();
                return;
            }

            await this.submitCreate();
        },
        async submitCreate() {
            this.isSubmitting = true;
            this.generalError = '';
            this.errors = emptyErrors();

            await this.crud.submitCreate({
                body: {
                    company_name: this.form.company_name,
                    url: nullableString(this.form.url),
                    phone: nullableString(this.form.phone),
                    email: nullableString(this.form.email),
                    currency_code: nullableString(this.form.currency_code),
                },
                csrfToken: this.csrfToken,
                onValidationError: (data) => {
                    this.errors = this.normalizeErrors(data.errors);
                    this.generalError = data.message || 'Validation failed.';
                },
                onError: () => {
                    this.generalError = 'Something went wrong. Please try again.';
                    this.showToast('error', this.generalError);
                },
                onSuccess: async (data) => {
                    const redirectUrl = this.crud.buildDetailUrl(data.data);

                    if (redirectUrl) {
                        window.location.assign(redirectUrl);
                        return;
                    }

                    await this.fetchSuppliers();
                    await refreshNavigationState(this.navigationStateUrl);
                    this.showToast('success', 'Supplier added.');
                    this.closeForm();
                },
                onFinally: () => {
                    this.isSubmitting = false;
                },
            });
        },
        async submitEdit() {
            this.isSubmitting = true;
            this.generalError = '';
            this.errors = emptyErrors();

            const response = await fetch(this.updateUrlBase + '/' + this.editingSupplierId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    company_name: this.form.company_name,
                    url: nullableString(this.form.url),
                    phone: nullableString(this.form.phone),
                    email: nullableString(this.form.email),
                    currency_code: nullableString(this.form.currency_code),
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = this.normalizeErrors(data.errors);
                this.generalError = data.message || 'Validation failed.';
                this.isSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.generalError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to update supplier.');
                this.isSubmitting = false;
                return;
            }

            await this.fetchSuppliers();
            await refreshNavigationState(this.navigationStateUrl);
            this.showToast('success', 'Supplier updated.');
            this.closeForm();
        },
        openArchive(supplier) {
            if (!this.canManageSuppliers) {
                return;
            }

            this.archiveSupplierId = supplier.id;
            this.archiveSupplierName = supplier.company_name || '';
            this.archiveError = '';
            this.isArchiveOpen = true;
        },
        closeArchive() {
            this.isArchiveOpen = false;
            this.isArchiveSubmitting = false;
            this.archiveError = '';
            this.archiveSupplierId = null;
            this.archiveSupplierName = '';
        },
        async submitArchive() {
            this.isArchiveSubmitting = true;
            this.archiveError = '';

            const response = await fetch(this.updateUrlBase + '/' + this.archiveSupplierId, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.archiveError = data.message || 'Unable to archive supplier.';
                this.showToast('error', this.archiveError);
                this.isArchiveSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.archiveError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to archive supplier.');
                this.isArchiveSubmitting = false;
                return;
            }

            await this.fetchSuppliers();
            await refreshNavigationState(this.navigationStateUrl);
            this.showToast('success', 'Supplier archived.');
            this.closeArchive();
        },
    }));
}
