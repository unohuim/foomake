import Alpine from 'alpinejs';
import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudCardRenderer } from '../lib/crud-card-page';
import { createGenericCrud } from '../lib/generic-crud';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const rendererConfig = {
        ...crud,
        state: {
            records: 'makeOrders',
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
            cellTextExpression: 'makeOrderCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        rowActions: crud.rowActions || {},
        actions: [],
    };

    mountCrudCardRenderer(crudRootEl, rendererConfig);

    const emptyForm = () => ({
        recipe_id: '',
        runs: '',
        due_date: '',
    });
    const emptyErrors = () => ({
        recipe_id: [],
        runs: [],
        due_date: [],
    });
    const buildEndpoint = (template, recordId) => {
        if (!template || recordId === null || recordId === undefined) {
            return '';
        }

        return template.replace('{id}', encodeURIComponent(String(recordId)));
    };

    Alpine.data('manufacturingMakeOrders', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        makeOrders: [],
        makeOrderRecipes: Array.isArray(safePayload.recipes) ? safePayload.recipes : [],
        storeUrl: safePayload.storeUrl || '',
        csrfToken: safePayload.csrfToken || '',
        canExecute: Boolean(safePayload.canExecute),
        prefillRecipeId: safePayload.prefillRecipeId || null,
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'due_date',
            direction: 'asc',
        },
        isMakeOrderFormOpen: false,
        isMakeOrderEditMode: false,
        isMakeOrderFormSubmitting: false,
        makeOrderFormRecordId: null,
        makeOrderForm: emptyForm(),
        makeOrderFormErrors: emptyErrors(),
        makeOrderFormGeneralError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchMakeOrders();

            if (this.prefillRecipeId) {
                this.openMakeOrderCreate({
                    recipe_id: this.prefillRecipeId,
                });
            }
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        makeOrderCellText(record, column) {
            if (column === 'runs') {
                return record?.runs_display || record?.runs || '—';
            }

            if (column === 'qty') {
                return record?.qty_display || record?.qty || '—';
            }

            if (column === 'due_date') {
                return record?.due_date || '—';
            }

            return record?.[column] || '—';
        },
        makeOrderMobileSummary(record) {
            const parts = [];

            if (record?.due_date) {
                parts.push(`Due ${record.due_date}`);
            }

            if (record?.runs_display || record?.runs) {
                parts.push(`Runs ${record.runs_display || record.runs}`);
            }

            if (record?.qty_display || record?.qty) {
                parts.push(`Qty ${record.qty_display || record.qty}`);
            }

            if (record?.workflow_state) {
                parts.push(record.workflow_state);
            }

            return parts.join(' · ');
        },
        makeOrderTitleBadges(record) {
            const status = String(record?.status_label || record?.workflow_state || '').trim();

            if (status === '') {
                return [];
            }

            const normalized = status.toUpperCase();
            const tones = {
                CANCELLED: 'gray',
                COMPLETED: 'green',
                DRAFT: 'yellow',
                MADE: 'green',
                SCHEDULED: 'blue',
                CREATED: 'blue',
                'IN PROGRESS': 'blue',
            };

            return [{
                label: status,
                tone: tones[normalized] || 'gray',
            }];
        },
        makeOrderCardRows(record) {
            return [
                {
                    left: [
                        { label: 'Recipe', value: record?.recipe_name || '—' },
                        { label: 'Runs', value: record?.runs_display || record?.runs || '—' },
                    ],
                    right: [],
                },
                {
                    left: [
                        { label: 'Expected', value: this.makeOrderQuantityWithUom(record?.expected_output_qty_display || record?.qty_display, record) },
                    ],
                    right: [
                        { label: 'Actual', value: this.makeOrderQuantityWithUom(record?.actual_output_qty_display || record?.actual_output_quantity_display, record) },
                    ],
                },
            ];
        },
        makeOrderQuantityWithUom(value, record) {
            const quantity = String(value || '—').trim();
            const symbol = String(record?.output_uom_symbol || '').trim();

            if (quantity === '—' || symbol === '') {
                return quantity;
            }

            return `${quantity} ${symbol}`;
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                recipe_id: Array.isArray(errors.recipe_id) ? errors.recipe_id : [],
                runs: Array.isArray(errors.runs) ? errors.runs : [],
                due_date: Array.isArray(errors.due_date) ? errors.due_date : [],
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
        async fetchMakeOrders() {
            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.makeOrders = Array.isArray(data?.data) ? data.data : [];

                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: () => {
                    this.listError = 'Unable to load make orders.';
                },
                onError: () => {
                    this.listError = 'Unable to load make orders.';
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchMakeOrders();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchMakeOrders();
        },
        openCreate() {
            this.openMakeOrderCreate();
        },
        openMakeOrderCreate(prefill = {}) {
            if (!this.canExecute) {
                return;
            }

            this.isMakeOrderEditMode = false;
            this.makeOrderFormRecordId = null;
            this.makeOrderFormErrors = emptyErrors();
            this.makeOrderFormGeneralError = '';
            this.makeOrderForm = {
                recipe_id: prefill.recipe_id ? String(prefill.recipe_id) : '',
                runs: '',
                due_date: '',
            };
            this.isMakeOrderFormOpen = true;
            this.$nextTick(() => {
                this.$refs.makeOrderRecipeSelect?.focus();
            });
        },
        openEdit(record) {
            if (!this.canExecute) {
                return;
            }

            this.isMakeOrderEditMode = true;
            this.makeOrderFormRecordId = record.id;
            this.makeOrderFormErrors = emptyErrors();
            this.makeOrderFormGeneralError = '';
            this.makeOrderForm = {
                recipe_id: record.recipe_id ? String(record.recipe_id) : '',
                runs: record.runs || '',
                due_date: record.due_date || '',
            };
            this.isMakeOrderFormOpen = true;
            this.$nextTick(() => {
                this.$refs.makeOrderRecipeSelect?.focus();
            });
        },
        closeMakeOrderForm() {
            this.isMakeOrderFormOpen = false;
            this.isMakeOrderEditMode = false;
            this.isMakeOrderFormSubmitting = false;
            this.makeOrderFormRecordId = null;
            this.makeOrderFormErrors = emptyErrors();
            this.makeOrderFormGeneralError = '';
            this.makeOrderForm = emptyForm();
        },
        async submitMakeOrderForm() {
            if (!this.canExecute) {
                this.makeOrderFormGeneralError = 'You do not have permission to create make orders.';
                return;
            }

            this.isMakeOrderFormSubmitting = true;
            this.makeOrderFormErrors = emptyErrors();
            this.makeOrderFormGeneralError = '';

            if (!this.isMakeOrderEditMode) {
                await this.crud.submitCreate({
                    body: {
                        recipe_id: this.makeOrderForm.recipe_id ? Number(this.makeOrderForm.recipe_id) : '',
                        runs: this.makeOrderForm.runs,
                    },
                    csrfToken: this.csrfToken,
                    onValidationError: (data) => {
                        this.makeOrderFormErrors = this.normalizeErrors(data.errors);
                        this.makeOrderFormGeneralError = data.message || 'Validation failed.';
                    },
                    onError: () => {
                        this.makeOrderFormGeneralError = 'Something went wrong. Please try again.';
                        this.showToast('error', this.makeOrderFormGeneralError);
                    },
                    onSuccess: async () => {
                        await this.fetchMakeOrders();
                        this.closeMakeOrderForm();
                        this.showToast('success', 'Make order created.');
                    },
                    onFinally: () => {
                        this.isMakeOrderFormSubmitting = false;
                    },
                });

                return;
            }

            const endpoint = buildEndpoint(this.endpoints.update, this.makeOrderFormRecordId);

            try {
                const response = await fetch(endpoint, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        recipe_id: this.makeOrderForm.recipe_id ? Number(this.makeOrderForm.recipe_id) : '',
                        runs: this.makeOrderForm.runs,
                        due_date: this.makeOrderForm.due_date || null,
                    }),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.makeOrderFormErrors = this.normalizeErrors(data.errors);
                    this.makeOrderFormGeneralError = data.message || 'Validation failed.';
                    this.isMakeOrderFormSubmitting = false;
                    return;
                }

                if (!response.ok) {
                    this.makeOrderFormGeneralError = 'Something went wrong. Please try again.';
                    this.showToast('error', this.makeOrderFormGeneralError);
                    this.isMakeOrderFormSubmitting = false;
                    return;
                }

                await this.fetchMakeOrders();
                this.closeMakeOrderForm();
                this.showToast('success', 'Make order updated.');
            } catch (error) {
                this.makeOrderFormGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.makeOrderFormGeneralError);
            } finally {
                this.isMakeOrderFormSubmitting = false;
            }
        },
        view(record) {
            if (!record?.show_url) {
                return;
            }

            window.location.assign(record.show_url);
        },
        async archive(record) {
            if (!this.canExecute) {
                this.showToast('error', 'You do not have permission to archive make orders.');
                return;
            }

            const endpoint = buildEndpoint(this.endpoints.delete, record?.id);

            try {
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.showToast('error', data.message || 'Unable to archive make order.');
                    return;
                }

                if (!response.ok) {
                    this.showToast('error', 'Unable to archive make order.');
                    return;
                }

                const data = await response.json();
                const removedId = data?.removed_id ?? record?.id;
                this.makeOrders = this.makeOrders.filter((entry) => entry.id !== removedId);
            } catch (error) {
                this.showToast('error', 'Unable to archive make order.');
            }
        },
    }));
}
