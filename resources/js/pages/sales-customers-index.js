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
        handler: action.id === 'edit' ? 'openEdit(record)' : action.id === 'archive' ? 'archive(record)' : '',
    }));
    const rendererConfig = {
        ...crud,
        createHandler: 'openCreatePanel()',
        importHandler: 'openImportPanel()',
        state: {
            records: 'customers',
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
            cellTextExpression: 'customerCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    };

    mountCrudRenderer(crudRootEl, rendererConfig);

    const emptyErrors = () => ({
        name: [],
        status: [],
        notes: [],
        address_line_1: [],
        address_line_2: [],
        city: [],
        region: [],
        postal_code: [],
        country_code: [],
        formatted_address: [],
    });

    const emptyImportErrors = () => ({
        source: [],
    });

    const parseJsonResponse = async (response) => {
        try {
            return await response.json();
        } catch (error) {
            return {};
        }
    };

    const emptyForm = () => ({
        name: '',
        status: 'active',
        notes: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        region: '',
        postal_code: '',
        country_code: '',
        formatted_address: '',
    });

    const customerToForm = (customer) => ({
        name: customer.name || '',
        status: customer.status || 'active',
        notes: customer.notes || '',
        address_line_1: customer.address_line_1 || '',
        address_line_2: customer.address_line_2 || '',
        city: customer.city || '',
        region: customer.region || '',
        postal_code: customer.postal_code || '',
        country_code: customer.country_code || '',
        formatted_address: customer.formatted_address || '',
    });

    Alpine.data('salesCustomersIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        customers: Array.isArray(safePayload.customers) ? safePayload.customers : [],
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        sources: safePayload.sources || [],
        canManageImports: Boolean(safePayload.canManageImports),
        canManageConnections: Boolean(safePayload.canManageConnections),
        connectorsPageUrl: safePayload.connectorsPageUrl || '',
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'name',
            direction: 'asc',
        },
        isFormOpen: false,
        isSubmitting: false,
        formMode: 'create',
        editingCustomerId: null,
        form: emptyForm(),
        errors: emptyErrors(),
        generalError: '',
        isImportPanelOpen: false,
        selectedSource: '',
        previewRows: [],
        importErrors: emptyImportErrors(),
        importPreviewError: '',
        importError: '',
        importValidationErrors: {},
        isLoadingPreview: false,
        isSubmittingImport: false,
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchCustomers();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        customerCellText(customer, column) {
            switch (column) {
            case 'name':
                return customer?.name || '—';
            case 'email':
                return customer?.email || '—';
            case 'address_summary':
                return customer?.address_summary || '—';
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
        normalizeErrors(errors) {
            const normalized = emptyErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        async fetchCustomers() {
            if (!this.endpoints.list) {
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
                    this.listError = data.message || 'Unable to load customers.';
                    this.showToast('error', this.listError);
                },
                onError: () => {
                    this.listError = 'Unable to load customers.';
                    this.showToast('error', this.listError);
                },
                onSuccess: (data) => {
                    this.customers = Array.isArray(data.data) ? data.data : [];

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
            this.fetchCustomers();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchCustomers();
        },
        openCreate() {
            this.formMode = 'create';
            this.editingCustomerId = null;
            this.form = emptyForm();
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
            this.$nextTick(() => {
                this.$refs.customerNameInput?.focus();
            });
        },
        openCreatePanel() {
            this.openCreate();
        },
        openEdit(customer) {
            this.formMode = 'edit';
            this.editingCustomerId = customer.id;
            this.form = customerToForm(customer);
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
            this.$nextTick(() => {
                this.$refs.customerNameInput?.focus();
            });
        },
        closeForm() {
            this.isFormOpen = false;
            this.isSubmitting = false;
            this.errors = emptyErrors();
            this.generalError = '';
        },
        async submitForm() {
            if (this.isSubmitting) {
                return;
            }

            const body = {
                name: this.form.name,
                notes: this.form.notes || null,
                address_line_1: this.form.address_line_1 || null,
                address_line_2: this.form.address_line_2 || null,
                city: this.form.city || null,
                region: this.form.region || null,
                postal_code: this.form.postal_code || null,
                country_code: this.form.country_code || null,
                formatted_address: this.form.formatted_address || null,
            };

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';

            if (this.formMode === 'create') {
                await this.crud.submitCreate({
                    body,
                    csrfToken: this.csrfToken,
                    onValidationError: (data) => {
                        this.errors = this.normalizeErrors(data.errors);
                        this.generalError = data.message || 'Validation failed.';
                    },
                    onError: () => {
                        this.generalError = 'Something went wrong. Please try again.';
                        this.showToast('error', this.generalError);
                    },
                    onSuccess: async () => {
                        await this.fetchCustomers();
                        await refreshNavigationState(this.navigationStateUrl);
                        this.closeForm();
                        this.showToast('success', 'Customer created.');
                    },
                    onFinally: () => {
                        this.isSubmitting = false;
                    },
                });

                return;
            }

            const response = await fetch(`${this.updateUrlBase}/${this.editingCustomerId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    ...body,
                    status: this.form.status,
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
                this.showToast('error', this.generalError);
                this.isSubmitting = false;
                return;
            }

            await this.fetchCustomers();
            this.closeForm();
            this.showToast('success', 'Customer updated.');
        },
        async archive(customer) {
            const response = await fetch(`${this.updateUrlBase}/${customer.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to archive customer.');
                return;
            }

            await this.fetchCustomers();
            await refreshNavigationState(this.navigationStateUrl);
            this.showToast('success', 'Customer archived.');
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
            this.importErrors = emptyImportErrors();
            this.importPreviewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.isLoadingPreview = false;
            this.isSubmittingImport = false;
        },
        handleSourceChange() {
            this.previewRows = [];
            this.importErrors = emptyImportErrors();
            this.importPreviewError = '';
            this.importError = '';
            this.importValidationErrors = {};
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
        selectedSourceConnectionLabel() {
            return this.selectedSourceMeta()?.status_label || '';
        },
        selectedRowCount() {
            return this.previewRows.filter((row) => row.selected).length;
        },
        rowError(index, field) {
            const key = `rows.${index}.${field}`;
            const errors = this.importValidationErrors[key];

            return Array.isArray(errors) && errors.length > 0 ? errors[0] : '';
        },
        async loadPreview() {
            if (!this.endpoints.importPreview) {
                this.importPreviewError = 'Unable to load preview.';
                return;
            }

            if (this.isLoadingPreview) {
                return;
            }

            this.importPreviewError = '';
            this.importError = '';
            this.importValidationErrors = {};
            this.importErrors = emptyImportErrors();
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
                    const data = await parseJsonResponse(response);
                    this.importPreviewError = data.message || 'Unable to load preview.';
                    this.importErrors.source = Array.isArray(data.errors?.source) ? data.errors.source : [];
                    return;
                }

                if (!response.ok) {
                    const data = await parseJsonResponse(response);
                    this.importPreviewError = data.message || 'Unable to load preview.';
                    this.importErrors.source = Array.isArray(data.errors?.source) ? data.errors.source : [];
                    return;
                }

                const data = await parseJsonResponse(response);
                this.previewRows = (data.data?.rows || []).map((row) => ({
                    ...row,
                    selected: true,
                }));
            } catch (error) {
                this.importPreviewError = 'Unable to load preview.';
            } finally {
                this.isLoadingPreview = false;
            }
        },
        async submitImport() {
            if (!this.endpoints.importStore) {
                this.importError = 'Unable to import customers.';
                return;
            }

            const rows = this.previewRows
                .filter((row) => row.selected)
                .map((row) => ({
                    external_id: row.external_id,
                    name: row.name,
                    email: row.email || null,
                    phone: row.phone || null,
                    address_line_1: row.address_line_1 || null,
                    address_line_2: row.address_line_2 || null,
                    city: row.city || null,
                    region: row.region || null,
                    postal_code: row.postal_code || null,
                    country_code: row.country_code || null,
                }));

            if (rows.length === 0) {
                this.importError = 'Select at least one customer to import.';
                return;
            }

            this.isSubmittingImport = true;
            this.importError = '';
            this.importValidationErrors = {};

            const response = await fetch(this.endpoints.importStore, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    source: this.selectedSource,
                    rows,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.importError = data.message || 'Unable to import customers.';
                this.importValidationErrors = data.errors || {};
                this.isSubmittingImport = false;
                return;
            }

            if (!response.ok) {
                this.importError = 'Unable to import customers.';
                this.isSubmittingImport = false;
                return;
            }

            await this.fetchCustomers();
            await refreshNavigationState(this.navigationStateUrl);
            this.closeImportPanel();
            this.showToast('success', 'Customers imported.');
        },
    }));
}
