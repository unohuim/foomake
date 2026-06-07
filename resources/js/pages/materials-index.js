import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudCardRenderer } from '../lib/crud-card-page';
import { createGenericCrud } from '../lib/generic-crud';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
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
        desktopCard: {
            ...crud.desktopCard,
        },
        actions: [],
    };

    mountCrudCardRenderer(crudRootEl, rendererConfig);

    const buildItemEndpoint = (template, itemId) => {
        if (!template || itemId === null || itemId === undefined) {
            return '';
        }

        return template.replace('{id}', encodeURIComponent(String(itemId)));
    };

    const emptyErrors = () => ({
        name: [],
        base_uom_id: [],
        default_price_amount: [],
        default_price_currency_code: [],
        starting_quantity: [],
    });

    const emptyForm = () => ({
        name: '',
        base_uom_id: '',
        is_active: true,
        is_stockable: false,
        is_purchasable: false,
        is_sellable: false,
        is_manufacturable: false,
        default_price_amount: '',
        starting_quantity: '',
    });

    Alpine.data('materialsIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        csrfToken: safePayload.csrfToken || '',
        uoms: Array.isArray(safePayload.uoms) ? safePayload.uoms : [],
        navigationStateUrl: safePayload.navigationStateUrl || '',
        tenantCurrency: safePayload.tenantCurrency || '',
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        materials: [],
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
        isCreateOpen: false,
        isSubmitting: false,
        errors: emptyErrors(),
        generalError: '',
        form: emptyForm(),
        init() {
            this.fetchMaterials();
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
                starting_quantity: Array.isArray(errors.starting_quantity) ? errors.starting_quantity : [],
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
        materialFlagIcons(record) {
            return [
                { label: 'Sellable', icon: 'shopping-cart', active: Boolean(record?.is_sellable) },
                { label: 'Purchasable', icon: 'credit-card', active: Boolean(record?.is_purchasable) },
                { label: 'Makeable', icon: 'cog', active: Boolean(record?.is_manufacturable) },
                { label: 'Stockable', icon: 'rectangle-group', active: Boolean(record?.is_stockable) },
            ];
        },
        materialCellText(record, column) {
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
        materialCardUomLabel(record) {
            const name = String(record?.item_uom_name || '').trim();
            const symbol = String(record?.item_uom_symbol || '').trim();

            if (name !== '' && symbol !== '') {
                return `${name} (${symbol})`;
            }

            return name || symbol || '—';
        },
        formatMaterialCardQuantity(value) {
            const normalized = String(value || '0');
            const [whole, decimal] = normalized.split('.');
            const formattedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return decimal === undefined ? formattedWhole : `${formattedWhole}.${decimal}`;
        },
        materialAvailabilityStats(record) {
            return [
                { label: 'On hand', value: this.formatMaterialCardQuantity(record?.on_hand_display), span: 3 },
                { label: 'Net Qty', value: this.formatMaterialCardQuantity(record?.net_display), span: 3 },
                { label: 'SO Qty', value: this.formatMaterialCardQuantity(record?.sell_display), span: 2 },
                { label: 'PO Qty', value: this.formatMaterialCardQuantity(record?.buy_display), span: 2 },
                { label: 'MO Qty', value: this.formatMaterialCardQuantity(record?.make_display), span: 2 },
            ];
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
        async toggleMaterialActive(toggleDetail) {
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

            await this.fetchMaterials();
            await refreshNavigationState(this.navigationStateUrl);
            this.activeToggleSavingIds = this.activeToggleSavingIds.filter((id) => id !== record.id);
            this.showToast('success', `${record.item || 'Material'} ${record.is_active ? 'Active' : 'Inactive'}`);
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
                onSuccess: async () => {
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
    }));
}
