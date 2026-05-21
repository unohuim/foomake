import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const rendererConfig = {
        ...crud,
        state: {
            records: 'inventoryRows',
            loading: 'isLoadingList',
            error: 'listError',
            search: 'search',
            sort: 'sort',
        },
        handlers: {
            searchInput: 'handleSearchInput()',
            toggleSort: 'toggleSort(column)',
            create: '',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'inventoryCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: [],
    };

    mountCrudRenderer(crudRootEl, rendererConfig);

    Alpine.data('inventoryIndex', () => ({
        crud,
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        inventoryRows: [],
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'item',
            direction: 'asc',
        },
        init() {
            this.fetchInventory();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        inventoryCellText(record, column) {
            if (column === 'item') {
                return record?.item || '—';
            }

            const quantityDisplayFieldByColumn = {
                on_hand: 'on_hand_display',
                sell: 'sell_display',
                buy: 'buy_display',
                make: 'make_display',
                net: 'net_display',
            };

            const displayField = quantityDisplayFieldByColumn[column];

            if (displayField) {
                return record?.[displayField] || '—';
            }

            return record?.[column] || '—';
        },
        inventoryAvailabilitySummary(record) {
            return [
                `On-Hand: ${record?.on_hand_display || '0.000000'}`,
                `Sell: ${record?.sell_display || '0.000000'}`,
                `Buy: ${record?.buy_display || '0.000000'}`,
                `Make: ${record?.make_display || '0.000000'}`,
                `Net: ${record?.net_display || '0.000000'}`,
            ].join(' • ');
        },
        async fetchInventory() {
            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.inventoryRows = Array.isArray(data?.data) ? data.data : [];

                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: () => {
                    this.listError = 'Unable to load inventory.';
                },
                onError: () => {
                    this.listError = 'Unable to load inventory.';
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchInventory();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchInventory();
        },
    }));
}
