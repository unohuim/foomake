import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudCardRenderer } from '../lib/crud-card-page';
import { createGenericCrud } from '../lib/generic-crud';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');

    mountCrudCardRenderer(crudRootEl, {
        ...crud,
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
            create: 'createOrder()',
            import: '',
            export: '',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'purchaseOrderCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: [],
    });

    Alpine.data('purchasingOrdersIndex', () => ({
        crud,
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        orders: Array.isArray(safePayload.orders) ? safePayload.orders : [],
        storeUrl: safePayload.storeUrl || crud.endpoints?.create || '',
        csrfToken: safePayload.csrfToken || '',
        tenantCurrency: safePayload.tenantCurrency || 'USD',
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'created_at',
            direction: 'desc',
        },
        isCreating: false,
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
        purchaseOrderCellText(order, column) {
            switch (column) {
            case 'order':
                return order?.order || 'Draft PO';
            case 'supplier_name':
                return order?.supplier_name || 'Supplier not set';
            case 'status':
                return order?.status || '-';
            case 'po_grand_total_cents':
                return order?.po_grand_total_display || this.formatMoney(order?.po_grand_total_cents);
            case 'lines_count':
                return String(order?.lines_count ?? 0);
            default:
                return order?.[column] || '-';
            }
        },
        purchaseOrderMobileSummary(order) {
            return [
                order?.status || '-',
                order?.order_date || 'No order date',
                order?.po_grand_total_display || this.formatMoney(order?.po_grand_total_cents),
            ].join(' • ');
        },
        purchaseOrderTitleBadges(order) {
            const status = order?.status || '';

            if (status === '') {
                return [];
            }

            const tones = {
                CANCELLED: 'gray',
                COMPLETED: 'green',
                CREATED: 'blue',
                DRAFT: 'yellow',
                RECEIVED: 'received',
            };

            return [{
                label: status,
                tone: tones[status] || 'gray',
            }];
        },
        formatMoney(cents) {
            if (cents === null || cents === undefined) {
                return `${this.tenantCurrency} 0.00`;
            }

            return `${this.tenantCurrency} ${(Number(cents) / 100).toFixed(2)}`;
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
        async fetchOrders() {
            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.orders = Array.isArray(data?.data) ? data.data : [];

                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: (data) => {
                    this.listError = data?.message || 'Unable to load purchase orders.';
                    this.showToast('error', this.listError);
                },
                onError: () => {
                    this.listError = 'Unable to load purchase orders.';
                    this.showToast('error', this.listError);
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
        async createOrder() {
            if (!this.storeUrl || this.isCreating) {
                return;
            }

            this.isCreating = true;

            await this.crud.submitCreate({
                body: {},
                csrfToken: this.csrfToken,
                onSuccess: async () => {
                    await this.fetchOrders();
                    this.showToast('success', 'Purchase order created.');
                },
                onValidationError: (data) => {
                    this.showToast('error', data?.message || 'Unable to create purchase order.');
                },
                onError: () => {
                    this.showToast('error', 'Unable to create purchase order.');
                },
                onFinally: () => {
                    this.isCreating = false;
                },
            });
        },
    }));
}
