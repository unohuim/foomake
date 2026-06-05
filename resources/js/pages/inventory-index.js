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

    const buildItemEndpoint = (template, itemId) => {
        if (!template || itemId === null || itemId === undefined) {
            return '';
        }

        return template.replace('{id}', encodeURIComponent(String(itemId)));
    };

    Alpine.data('inventoryIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        csrfToken: payload?.csrfToken || '',
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        inventoryRows: [],
        isLoadingList: false,
        listError: '',
        activeToggleSavingIds: [],
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
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
        canManageMaterials() {
            return Boolean(this.crud.permissions?.canManageMaterials);
        },
        inventoryMaterialFlagBadges(record) {
            const badges = [];

            if (record?.is_stockable) {
                badges.push('Stockable');
            }

            if (record?.is_purchasable) {
                badges.push('Purchasable');
            }

            if (record?.is_sellable) {
                badges.push('Sellable');
            }

            if (record?.is_manufacturable) {
                badges.push('Manufacturable');
            }

            return badges;
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
        async toggleInventoryMaterialActive(toggleDetail) {
            if (!this.canManageMaterials()) {
                return;
            }

            const record = toggleDetail?.record || toggleDetail?.row;

            if (!record?.id || this.activeToggleSavingIds.includes(record.id)) {
                return;
            }

            const endpoint = record.update_url || buildItemEndpoint(this.endpoints.update, record.id);

            if (!endpoint) {
                this.showToast('error', 'Something went wrong. Please try again.');
                return;
            }

            const previousValue = Boolean(record.is_active);
            const nextValue = Boolean(toggleDetail.checked);
            record.is_active = nextValue;
            this.activeToggleSavingIds = [...this.activeToggleSavingIds, record.id];

            const response = await fetch(endpoint, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    name: record.item || '',
                    base_uom_id: record.base_uom_id,
                    is_active: nextValue,
                    is_stockable: Boolean(record.is_stockable),
                    is_purchasable: Boolean(record.is_purchasable),
                    is_sellable: Boolean(record.is_sellable),
                    is_manufacturable: Boolean(record.is_manufacturable),
                    default_price_amount: record.default_price_amount || '',
                    default_price_currency_code: record.default_price_currency_code || '',
                }),
            });

            if (!response.ok) {
                record.is_active = previousValue;
                this.activeToggleSavingIds = this.activeToggleSavingIds.filter((id) => id !== record.id);
                this.showToast('error', 'Something went wrong. Please try again.');
                return;
            }

            const data = await response.json();
            const updated = data?.data || {};
            record.is_active = Boolean(updated.is_active);

            await this.fetchInventory();
            this.activeToggleSavingIds = this.activeToggleSavingIds.filter((id) => id !== record.id);
            this.showToast('success', `${record.item || 'Material'} ${record.is_active ? 'Active' : 'Inactive'}`);
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
