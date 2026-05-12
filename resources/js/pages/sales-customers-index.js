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

    const emptyFormErrors = () => ({
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

    const emptySlideOvers = () => ({
        import: {
            open: false,
            title: importConfig.labels?.title || 'Import Customers',
        },
    });

    const importModule = createImportModule({
        config: importConfig,
        callbacks: {
            parseLocalCsv: (text, component) => component.parseCustomerLocalCsv(text),
            onImportSuccess: async (component) => {
                await component.fetchCustomers();
                await refreshNavigationState(component.navigationStateUrl);
                component.showToast('success', 'Customers imported.');
            },
        },
    });

    Alpine.data('salesCustomersIndex', () => ({
        ...importModule,
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        customers: [],
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        sources: Array.isArray(importConfig.sources) ? importConfig.sources : [],
        canManageImports: Boolean(importConfig.permissions?.canManageImports),
        canManageConnections: Boolean(importConfig.permissions?.canManageConnections),
        connectorsPageUrl: importConfig.connectorsPageUrl || '',
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'name',
            direction: 'asc',
        },
        slideOvers: emptySlideOvers(),
        isFormOpen: false,
        isSubmitting: false,
        formMode: 'create',
        editingCustomerId: null,
        form: emptyForm(),
        formErrors: emptyFormErrors(),
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
        normalizeFormErrors(errors) {
            const normalized = emptyFormErrors();

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
            this.formErrors = emptyFormErrors();
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
            this.formErrors = emptyFormErrors();
            this.generalError = '';
            this.isFormOpen = true;
            this.$nextTick(() => {
                this.$refs.customerNameInput?.focus();
            });
        },
        closeForm() {
            this.isFormOpen = false;
            this.isSubmitting = false;
            this.formErrors = emptyFormErrors();
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
            this.formErrors = emptyFormErrors();
            this.generalError = '';

            if (this.formMode === 'create') {
                await this.crud.submitCreate({
                    body,
                    csrfToken: this.csrfToken,
                    onValidationError: (data) => {
                        this.formErrors = this.normalizeFormErrors(data.errors);
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
                this.formErrors = this.normalizeFormErrors(data.errors);
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
        buildImportRowPayload(row, importSource) {
            return {
                external_id: row.external_id,
                name: row.name,
                email: row.email || null,
                phone: row.phone || null,
                is_active: this.rowHasActiveState(row) ? this.rowIsActive(row) : null,
                address_line_1: row.address_line_1 || null,
                address_line_2: row.address_line_2 || null,
                city: row.city || null,
                region: row.region || null,
                postal_code: row.postal_code || null,
                country_code: row.country_code || null,
                external_source: row.external_source || importSource,
            };
        },
        previewSearchText(row) {
            return [
                row.name,
                row.email,
                row.phone,
                row.external_id,
                row.address_line_1,
                row.address_line_2,
                row.city,
                row.region,
                row.postal_code,
                row.country_code,
            ]
                .filter((value) => String(value || '').trim() !== '')
                .join(' ')
                .toLowerCase();
        },
        previewEmptyStateMessage() {
            if (this.previewRows.length === 0) {
                return 'No importable customers were returned for the selected source.';
            }

            return 'Adjust the current filters to show matching preview records.';
        },
        parseCustomerLocalCsv(text) {
            const rows = this.parseCsvRows(text);

            if (rows.length < 2) {
                this.errors.file = ['The selected CSV file does not contain any customer rows.'];

                return [];
            }

            const headers = rows[0].map((header) => header.trim());
            const requiredHeaders = [
                'name',
                'email',
                'phone',
                'address_line_1',
                'address_line_2',
                'city',
                'region',
                'postal_code',
                'country_code',
                'is_active',
            ];
            const hasRequiredHeaders = requiredHeaders.every((header) => headers.includes(header));

            if (!hasRequiredHeaders) {
                this.errors.file = ['The selected CSV file is missing one or more required customer headers.'];

                return [];
            }

            const parsedRows = rows
                .slice(1)
                .filter((row) => row.some((value) => value.trim() !== ''))
                .map((row, index) => {
                    const record = headers.reduce((carry, header, rowIndex) => {
                        carry[header] = row[rowIndex] ?? '';

                        return carry;
                    }, {});
                    const generatedExternalId = record.external_id !== ''
                        ? record.external_id
                        : `file-${index + 1}-${this.slugify(record.name || 'customer')}`;

                    return {
                        external_id: generatedExternalId,
                        external_source: record.external_source || '',
                        name: record.name,
                        email: record.email || '',
                        phone: record.phone || '',
                        is_active: this.csvBoolean(record.is_active, true),
                        address_line_1: record.address_line_1 || '',
                        address_line_2: record.address_line_2 || '',
                        city: record.city || '',
                        region: record.region || '',
                        postal_code: record.postal_code || '',
                        country_code: record.country_code || '',
                        selected: true,
                    };
                });

            if (parsedRows.length === 0) {
                this.errors.file = ['The selected CSV file does not contain any customer rows.'];
            }

            return parsedRows;
        },
        rowCustomerErrors(index) {
            const rowErrors = this.importValidationErrors?.[`rows.${index}`];

            if (Array.isArray(rowErrors)) {
                return rowErrors;
            }

            return [];
        },
        rowHasCustomerErrors(index) {
            return this.rowCustomerErrors(index).length > 0;
        },
    }));
}
