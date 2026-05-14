import { parseCrudConfig } from '../lib/crud-config';
import { parseImportConfig } from '../lib/import-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createExportModule } from '../lib/export-module';
import { createGenericCrud } from '../lib/generic-crud';
import { createImportModule } from '../lib/import-module';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const importConfig = parseImportConfig(rootEl);
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => ({
        ...action,
        handler: action.id === 'edit' ? 'openEdit(record)' : action.id === 'archive' ? 'archive(record)' : '',
    }));
    const rendererConfig = {
        ...crud,
        createHandler: 'openCreatePanel()',
        exportHandler: 'openExportPanel()',
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
            export: 'openExportPanel()',
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
    const parseCustomerIsActive = (value) => {
        if (value === null || value === undefined) {
            return false;
        }

        if (typeof value === 'boolean') {
            return value;
        }

        const normalized = String(value).trim().toLowerCase();

        if (['1', 'true', 'yes', 'active'].includes(normalized)) {
            return true;
        }

        if (['0', 'false', 'no', 'inactive', ''].includes(normalized)) {
            return false;
        }

        return false;
    };
    const importModule = createImportModule({
        config: importConfig,
        adapters: {
            parseLocalRows: (text, helpers) => {
                const rows = helpers.parseCsvRows(text);

                if (rows.length < 2) {
                    return [];
                }

                const headers = rows[0].map((header) => header.trim());
                const requiredHeaders = [
                    'external_id',
                    'external_source',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'region',
                    'postal_code',
                    'country_code',
                ];
                const hasRequiredHeaders = requiredHeaders.every((header) => headers.includes(header));

                if (!hasRequiredHeaders) {
                    helpers.setFileError('The selected CSV file is missing one or more required customer headers.');
                    return null;
                }

                return rows
                    .slice(1)
                    .filter((row) => row.some((value) => value.trim() !== ''))
                    .map((row, index) => headers.reduce((carry, header, rowIndex) => {
                        carry[header] = row[rowIndex] ?? '';

                        return carry;
                    }, {
                        __row_index: index,
                    }))
                    .map((record) => ({
                        external_id: record.external_id !== ''
                            ? record.external_id
                            : `file-customer-${record.__row_index + 1}-${helpers.slugify(record.name || 'customer')}`,
                        external_source: record.external_source || '',
                        name: record.name,
                        email: record.email || null,
                        phone: record.phone || null,
                        address_line_1: record.address_line_1 || null,
                        address_line_2: record.address_line_2 || null,
                        city: record.city || null,
                        region: record.region || null,
                        postal_code: record.postal_code || null,
                        country_code: record.country_code || null,
                        is_active: parseCustomerIsActive(record.is_active),
                        is_duplicate: false,
                        selected: true,
                    }));
            },
            normalizePreviewRow: (row) => ({
                ...row,
                selected: row.selected !== false,
                external_source: row.external_source || '',
                is_active: parseCustomerIsActive(row.is_active),
                is_duplicate: Boolean(row.is_duplicate),
                email: row.email || '',
                phone: row.phone || '',
                address_line_1: row.address_line_1 || '',
                address_line_2: row.address_line_2 || '',
                city: row.city || '',
                region: row.region || '',
                postal_code: row.postal_code || '',
                country_code: row.country_code || '',
            }),
            buildImportRowPayload: (defaultPayload, row) => ({
                external_id: row.external_id,
                name: row.name,
                email: row.email || null,
                phone: row.phone || null,
                is_active: row.is_active,
                address_line_1: row.address_line_1 || null,
                address_line_2: row.address_line_2 || null,
                city: row.city || null,
                region: row.region || null,
                postal_code: row.postal_code || null,
                country_code: row.country_code || null,
            }),
            buildSubmitBody: (defaultBody, { importSource, rows }) => ({
                source: defaultBody.is_local_file_import ? 'file-upload' : importSource,
                rows,
            }),
        },
        callbacks: {
            onImportSuccess: async (component) => {
                await component.fetchCustomers();
                await refreshNavigationState(component.navigationStateUrl);
                component.showToast('success', 'Customers imported.');
            },
        },
    });
    importModule.mount(rootEl);
    const exportModule = createExportModule({
        config: crud,
    });
    exportModule.mount(rootEl);

    Alpine.data('salesCustomersIndex', () => ({
        ...importModule,
        ...exportModule,
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        customers: Array.isArray(safePayload.customers) ? safePayload.customers : [],
        uoms: [],
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        sources: Array.isArray(importConfig.sources) && importConfig.sources.length > 0
            ? importConfig.sources
            : (safePayload.sources || []),
        canManageImports: Boolean(importConfig.permissions?.canManageImports ?? safePayload.canManageImports),
        canManageConnections: Boolean(importConfig.permissions?.canManageConnections ?? safePayload.canManageConnections),
        connectorsPageUrl: importConfig.connectorsPageUrl || safePayload.connectorsPageUrl || '',
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
        formErrors: emptyErrors(),
        generalError: '',
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
            this.formErrors = emptyErrors();
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
            this.formErrors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
            this.$nextTick(() => {
                this.$refs.customerNameInput?.focus();
            });
        },
        closeForm() {
            this.isFormOpen = false;
            this.isSubmitting = false;
            this.formErrors = emptyErrors();
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
            this.formErrors = emptyErrors();
            this.generalError = '';

            if (this.formMode === 'create') {
                await this.crud.submitCreate({
                    body,
                    csrfToken: this.csrfToken,
                    onValidationError: (data) => {
                        this.formErrors = this.normalizeErrors(data.errors);
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
                this.formErrors = this.normalizeErrors(data.errors);
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
    }));
}
