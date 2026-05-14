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
        handler: action.id === 'view'
            ? 'view(record)'
            : action.id === 'edit'
                ? 'openEdit(record)'
                : '',
    }));
    const rendererConfig = {
        ...crud,
        createHandler: 'openCreatePanel()',
        exportHandler: 'openExportPanel()',
        importHandler: 'openImportPanel()',
        state: {
            records: 'orders',
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
            cellTextExpression: 'orderCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    };

    mountCrudRenderer(crudRootEl, rendererConfig);

    const emptyErrors = () => ({
        customer_id: [],
        contact_id: [],
        order_date: [],
    });

    const emptyForm = () => ({
        customer_id: '',
        contact_id: '',
        order_date: '',
    });

    const orderToForm = (order) => ({
        customer_id: order?.customer_id ? String(order.customer_id) : '',
        contact_id: order?.contact_id ? String(order.contact_id) : '',
        order_date: order?.date || '',
    });

    const importModule = createImportModule({
        config: importConfig,
        adapters: {
            parseLocalRows: (text, helpers) => {
                const rows = helpers.parseCsvRows(text);

                if (rows.length < 2) {
                    return [];
                }

                const headers = rows[0].map((header) => header.trim());
                const exportHeaders = [
                    'external_source',
                    'order_external_id',
                    'order_date',
                    'customer_name',
                    'contact_name',
                    'city',
                    'status',
                    'external_status',
                    'line_external_id',
                    'product_external_id',
                    'product_name',
                    'quantity',
                    'unit_price',
                ];
                const hasExportHeaders = exportHeaders.every((header) => headers.includes(header));

                if (!hasExportHeaders) {
                    helpers.setFileError('The selected CSV file is missing one or more required order headers.');
                    return null;
                }

                const parsedRows = rows
                    .slice(1)
                    .filter((row) => row.some((value) => value.trim() !== ''))
                    .map((row, index) => headers.reduce((carry, header, rowIndex) => {
                        carry[header] = row[rowIndex] ?? '';

                        return carry;
                    }, {
                        __row_index: index,
                    }));

                const normalizedSources = parsedRows
                    .map((record) => (record.external_source || '').trim().toLowerCase())
                    .filter((value) => value !== '');

                if (normalizedSources.length !== parsedRows.length) {
                    helpers.setFileError('Every imported order row must include external_source.');
                    return null;
                }

                if (new Set(normalizedSources).size > 1) {
                    helpers.setFileError('Every row in one import file must use the same external_source.');
                    return null;
                }

                const groupedRecords = new Map();

                for (const record of parsedRows) {
                    const externalSource = (record.external_source || '').trim();
                    const orderExternalId = (record.order_external_id || '').trim();

                    if (orderExternalId === '') {
                        helpers.setFileError('Every imported order row must include order_external_id.');
                        return null;
                    }

                    const groupKey = `${externalSource.toLowerCase()}|${orderExternalId}`;

                    if (!groupedRecords.has(groupKey)) {
                        groupedRecords.set(groupKey, {
                            external_id: orderExternalId,
                            external_source: externalSource,
                            external_status: (record.external_status || record.status || '').trim(),
                            status: (record.status || '').trim(),
                            date: (record.order_date || '').trim(),
                            contact_name: (record.contact_name || '').trim(),
                            customer: {
                                external_id: '',
                                name: (record.customer_name || '').trim(),
                                email: '',
                                phone: '',
                                address_line_1: '',
                                address_line_2: '',
                                city: (record.city || '').trim(),
                                region: '',
                                postal_code: '',
                                country_code: '',
                            },
                            lines: [],
                            is_duplicate: false,
                            selected: true,
                        });
                    }

                    groupedRecords.get(groupKey).lines.push({
                        external_id: (record.line_external_id || '').trim(),
                        product_external_id: (record.product_external_id || '').trim(),
                        name: (record.product_name || '').trim(),
                        quantity: (record.quantity || '0.000000').trim(),
                        unit_price: (record.unit_price || '0').trim(),
                        currency_code: '',
                    });
                }

                return Array.from(groupedRecords.values());
            },
            normalizePreviewRow: (row) => ({
                ...row,
                selected: row.selected !== false,
                external_source: row.external_source || '',
                external_status: row.external_status || '',
                status: row.status || '',
                date: row.date || '',
                contact_name: row.contact_name || '',
                customer: row.customer && typeof row.customer === 'object'
                    ? {
                        external_id: row.customer.external_id || '',
                        name: row.customer.name || '',
                        email: row.customer.email || '',
                        phone: row.customer.phone || '',
                        address_line_1: row.customer.address_line_1 || '',
                        address_line_2: row.customer.address_line_2 || '',
                        city: row.customer.city || '',
                        region: row.customer.region || '',
                        postal_code: row.customer.postal_code || '',
                        country_code: row.customer.country_code || '',
                    }
                    : {
                        external_id: '',
                        name: '',
                        email: '',
                        phone: '',
                        address_line_1: '',
                        address_line_2: '',
                        city: '',
                        region: '',
                        postal_code: '',
                        country_code: '',
                    },
                lines: Array.isArray(row.lines)
                    ? row.lines.map((line) => ({
                        external_id: line.external_id || '',
                        product_external_id: line.product_external_id || '',
                        name: line.name || '',
                        quantity: line.quantity || '0.000000',
                        unit_price: line.unit_price || '',
                        unit_price_cents: line.unit_price_cents ?? null,
                        currency_code: line.currency_code || '',
                    }))
                    : [],
                is_duplicate: Boolean(row.is_duplicate),
            }),
            buildImportRowPayload: (defaultPayload, row, context) => ({
                external_id: row.external_id,
                external_source: row.external_source || context.importSource,
                external_status: row.external_status || '',
                status: row.status || '',
                date: row.date || null,
                contact_name: row.contact_name || null,
                customer: {
                    external_id: row.customer?.external_id || '',
                    name: row.customer?.name || '',
                    email: row.customer?.email || null,
                    phone: row.customer?.phone || null,
                    address_line_1: row.customer?.address_line_1 || null,
                    address_line_2: row.customer?.address_line_2 || null,
                    city: row.customer?.city || null,
                    region: row.customer?.region || null,
                    postal_code: row.customer?.postal_code || null,
                    country_code: row.customer?.country_code || null,
                },
                lines: Array.isArray(row.lines)
                    ? row.lines.map((line) => ({
                        external_id: line.external_id,
                        product_external_id: line.product_external_id || '',
                        name: line.name || '',
                        quantity: line.quantity || '0.000000',
                        unit_price: line.unit_price || null,
                        unit_price_cents: line.unit_price_cents || null,
                        currency_code: line.currency_code || null,
                    }))
                    : [],
            }),
            buildSubmitBody: (defaultBody, { importSource, rows }) => ({
                source: defaultBody.is_local_file_import ? 'file-upload' : importSource,
                rows,
            }),
        },
        callbacks: {
            onImportSuccess: async (component) => {
                await component.fetchOrders();
                await refreshNavigationState(component.navigationStateUrl);
                component.showToast('success', 'Orders imported.');
            },
        },
    });
    importModule.mount(rootEl);
    const exportModule = createExportModule({
        config: crud,
    });
    exportModule.mount(rootEl);

    Alpine.data('salesOrdersIndex', () => ({
        ...importModule,
        ...exportModule,
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        orders: Array.isArray(safePayload.orders) ? safePayload.orders : [],
        customers: Array.isArray(safePayload.customers) ? safePayload.customers : [],
        sources: Array.isArray(importConfig.sources) && importConfig.sources.length > 0
            ? importConfig.sources
            : (safePayload.sources || []),
        updateUrlBase: safePayload.updateUrlBase || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrfToken || '',
        canManageImports: Boolean(importConfig.permissions?.canManageImports ?? safePayload.canManageImports),
        canManageConnections: Boolean(importConfig.permissions?.canManageConnections ?? safePayload.canManageConnections),
        connectorsPageUrl: importConfig.connectorsPageUrl || safePayload.connectorsPageUrl || '',
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'date',
            direction: 'desc',
        },
        isFormOpen: false,
        isSubmitting: false,
        formMode: 'create',
        editingOrderId: null,
        form: emptyForm(),
        errors: emptyErrors(),
        generalError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchOrders();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        orderCustomerSummary(order) {
            const name = order?.customer_name || '—';
            const city = order?.city || '—';

            return `${this.truncatedCustomerName(name)} • ${city}`;
        },
        orderStatusSummary(order) {
            return order?.status || '—';
        },
        previewStatusLabel(row) {
            if (row && row.is_duplicate) {
                return 'Duplicate';
            }

            return (row && row.external_status) || 'Pending';
        },
        truncatedPreviewCustomerName(row) {
            const customer = row && typeof row.customer === 'object' && !Array.isArray(row.customer)
                ? row.customer
                : {};

            return this.truncatedCustomerName(customer.name || '');
        },
        compactPreviewMeta(row) {
            const customer = row && typeof row.customer === 'object' && !Array.isArray(row.customer)
                ? row.customer
                : {};
            const date = row && typeof row.date === 'string' && row.date.trim() !== ''
                ? row.date.trim()
                : '—';
            const city = typeof customer.city === 'string' && customer.city.trim() !== ''
                ? customer.city.trim()
                : '—';

            return `${date} • ${city}`;
        },
        truncatedCustomerName(name) {
            const value = typeof name === 'string' ? name : '';

            if (value.length <= 32) {
                return value || '—';
            }

            return `${value.slice(0, 29)}...`;
        },
        orderCellText(order, column) {
            switch (column) {
            case 'id':
                return order?.id ? String(order.id) : '—';
            case 'date':
                return order?.date || '—';
            case 'customer_name':
                return this.truncatedCustomerName(order?.customer_name || '');
            case 'city':
                return order?.city || '—';
            case 'status':
                return order?.status || '—';
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
        selectedCustomer() {
            const customerId = Number(this.form.customer_id);

            if (!customerId) {
                return null;
            }

            return this.customers.find((customer) => customer.id === customerId) || null;
        },
        selectedCustomerContacts() {
            return this.selectedCustomer()?.contacts || [];
        },
        contactOptionLabel(contact) {
            return contact.full_name;
        },
        defaultContactIdForCustomer(customerId) {
            const customer = this.customers.find((entry) => entry.id === Number(customerId));

            if (!customer || !customer.primary_contact_id) {
                return '';
            }

            return String(customer.primary_contact_id);
        },
        handleCustomerChange() {
            this.form.contact_id = this.defaultContactIdForCustomer(this.form.customer_id);
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                customer_id: Array.isArray(errors.customer_id) ? errors.customer_id : [],
                contact_id: Array.isArray(errors.contact_id) ? errors.contact_id : [],
                order_date: Array.isArray(errors.order_date) ? errors.order_date : [],
            };
        },
        async fetchOrders() {
            if (!this.endpoints.list) {
                this.orders = [];
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
                    this.listError = data.message || 'Unable to load orders.';
                    this.showToast('error', this.listError);
                },
                onError: () => {
                    this.listError = 'Unable to load orders.';
                    this.showToast('error', this.listError);
                },
                onSuccess: (data) => {
                    this.orders = Array.isArray(data.data) ? data.data : [];

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
            this.fetchOrders();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchOrders();
        },
        openCreatePanel() {
            this.formMode = 'create';
            this.editingOrderId = null;
            this.form = emptyForm();
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
        },
        openEdit(order) {
            if (!order?.can_edit) {
                return;
            }

            this.formMode = 'edit';
            this.editingOrderId = order.id;
            this.form = orderToForm(order);
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
        },
        closeForm() {
            this.isFormOpen = false;
            this.isSubmitting = false;
            this.errors = emptyErrors();
            this.generalError = '';
        },
        view(order) {
            if (!order?.show_url) {
                return;
            }

            window.location.assign(order.show_url);
        },
        async submitForm() {
            if (this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';

            const isCreate = this.formMode === 'create';
            const url = isCreate ? this.endpoints.create : `${this.updateUrlBase}/${this.editingOrderId}`;
            const method = isCreate ? 'POST' : 'PATCH';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    customer_id: this.form.customer_id === '' ? null : Number(this.form.customer_id),
                    contact_id: this.form.contact_id === '' ? null : Number(this.form.contact_id),
                    order_date: this.form.order_date || null,
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

            await this.fetchOrders();
            await refreshNavigationState(this.navigationStateUrl);
            this.closeForm();
            this.showToast('success', isCreate ? 'Sales order created.' : 'Sales order updated.');
        },
    }));
}
