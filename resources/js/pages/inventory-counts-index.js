import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudCardRenderer } from '../lib/crud-card-page';
import { createGenericCrud } from '../lib/generic-crud';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => ({
        ...action,
        handler: action.id === 'view'
            ? 'view(record)'
            : action.id === 'edit'
                ? 'openEdit(record)'
                : action.id === 'delete'
                    ? 'openDelete(record)'
                    : '',
    }));
    const rendererConfig = {
        ...crud,
        state: {
            records: 'counts',
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
            cellTextExpression: 'inventoryCountCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        desktopCard: {
            ...crud.desktopCard,
        },
        actions: actionDefinitions,
    };

    mountCrudCardRenderer(crudRootEl, rendererConfig);

    const emptyErrors = () => ({
        name: [],
        counted_at: [],
        notes: [],
        assigned_to_user_id: [],
        general: [],
    });

    const emptyForm = () => ({
        id: null,
        name: '',
        counted_at: '',
        notes: '',
        assigned_to_user_id: '',
        action: '',
        method: 'POST',
    });

    const buildCountEndpoint = (template, countId) => {
        if (!template || countId === null || countId === undefined) {
            return '';
        }

        return template.replace('{id}', encodeURIComponent(String(countId)));
    };

    Alpine.data('inventoryCountsIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        users: Array.isArray(safePayload.users) ? safePayload.users : [],
        counts: [],
        csrf: safePayload.csrfToken || '',
        showCountForm: false,
        isEditing: false,
        isSubmitting: false,
        errors: emptyErrors(),
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'counted_at',
            direction: 'desc',
        },
        toast: {
            show: false,
            type: 'success',
            message: '',
            timeoutId: null,
        },
        form: emptyForm(),
        init() {
            this.fetchCounts();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        inventoryCountSummary(record) {
            const parts = [];

            if (record?.posted_at && record.posted_at !== '—') {
                parts.push(record.posted_at);
            }

            return parts.join(' • ');
        },
        inventoryCountStatusBadges(record) {
            const status = String(record?.status_label || '').trim();

            if (status === '') {
                return [];
            }

            const normalized = status.toUpperCase();
            const tones = {
                CANCELLED: 'gray',
                COMPLETED: 'green',
                COUNTED: 'green',
                CREATED: 'blue',
                DRAFT: 'yellow',
                POSTED: 'green',
                RECEIVED: 'received',
                SCHEDULED: 'blue',
            };

            return [{
                label: status,
                tone: tones[normalized] || 'gray',
            }];
        },
        inventoryCountCardStats(record) {
            return [
                { label: 'Assigned', value: record?.counter_name || record?.counter_email || '—', span: 3 },
                { label: 'Items', value: String(record?.lines_count ?? 0), span: 3 },
                { label: 'Posted', value: record?.posted_at || '—', span: 6 },
            ];
        },
        inventoryCountCellText(record, column) {
            if (column === 'status') {
                return record?.status_label || '—';
            }

            if (column === 'counter') {
                return record?.counter_email || '—';
            }

            if (column === 'posted_at') {
                return record?.posted_at || '—';
            }

            if (column === 'name') {
                return record?.name || '—';
            }

            if (column === 'lines_count') {
                return record?.lines_count ?? '—';
            }

            return record?.[column] || '—';
        },
        truncateCounterEmail(record) {
            const email = record?.counter_email || '';

            if (email.length <= 20) {
                return email || '—';
            }

            return `${email.slice(0, 20)}…`;
        },
        focusCountedAtNextField(fieldId) {
            if (!fieldId) {
                return;
            }

            const nextField = document.getElementById(fieldId);

            if (nextField instanceof HTMLElement && typeof nextField.focus === 'function') {
                nextField.focus({ preventScroll: true });
            }
        },
        handleCountedAtChange(event) {
            this.form.counted_at = event.target.value;

            if (!event?.target?.value) {
                return;
            }

            requestAnimationFrame(() => {
                event.target.blur();
                this.focusCountedAtNextField('notes');
            });
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                name: Array.isArray(errors.name) ? errors.name : [],
                counted_at: Array.isArray(errors.counted_at) ? errors.counted_at : [],
                notes: Array.isArray(errors.notes) ? errors.notes : [],
                assigned_to_user_id: Array.isArray(errors.assigned_to_user_id) ? errors.assigned_to_user_id : [],
                general: Array.isArray(errors.general) ? errors.general : [],
            };
        },
        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.show = true;

            if (this.toast.timeoutId) {
                clearTimeout(this.toast.timeoutId);
            }

            this.toast.timeoutId = setTimeout(() => {
                this.toast.show = false;
            }, 1500);
        },
        async fetchCounts() {
            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.counts = Array.isArray(data?.data) ? data.data : [];

                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: () => {
                    this.listError = 'Unable to load inventory counts.';
                },
                onError: () => {
                    this.listError = 'Unable to load inventory counts.';
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchCounts();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchCounts();
        },
        openCreate() {
            this.isEditing = false;
            this.errors = emptyErrors();
            this.form = {
                id: null,
                name: '',
                counted_at: '',
                notes: '',
                assigned_to_user_id: '',
                action: this.endpoints.create || '',
                method: 'POST',
            };
            this.showCountForm = Boolean(this.form.action);
        },
        openEdit(record) {
            if (!record || record.status !== 'draft') {
                this.view(record);
                return;
            }

            this.isEditing = true;
            this.errors = emptyErrors();
            this.form = {
                id: record.id,
                name: record.name || '',
                counted_at: record.counted_at_iso || '',
                notes: record.notes || '',
                assigned_to_user_id: record.assigned_to_user_id || '',
                action: record.update_url || buildCountEndpoint(this.endpoints.update, record.id),
                method: 'PATCH',
            };
            this.showCountForm = Boolean(this.form.action);
        },
        closeCountForm() {
            this.showCountForm = false;
        },
        async submitCountForm() {
            this.errors = emptyErrors();
            this.isSubmitting = true;

            const onValidationError = (data) => {
                this.errors = this.normalizeErrors(data?.errors);

                if (!data?.errors) {
                    this.errors.general = [data?.message || 'Unable to save count.'];
                }
            };

            const onError = () => {
                this.showToast('error', 'Unable to save count.');
            };

            if (this.form.method === 'POST') {
                await this.crud.submitCreate({
                    body: {
                        name: this.form.name,
                        counted_at: this.form.counted_at,
                        notes: this.form.notes,
                        assigned_to_user_id: this.form.assigned_to_user_id,
                    },
                    csrfToken: this.csrf,
                    onValidationError,
                    onError,
                    onSuccess: async () => {
                        await this.fetchCounts();
                        this.showToast('success', 'Inventory count saved.');
                        this.closeCountForm();
                    },
                    onFinally: () => {
                        this.isSubmitting = false;
                    },
                });

                return;
            }

            try {
                const response = await fetch(this.form.action, {
                    method: this.form.method,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                body: JSON.stringify({
                    name: this.form.name,
                    counted_at: this.form.counted_at,
                    notes: this.form.notes,
                    assigned_to_user_id: this.form.assigned_to_user_id,
                }),
            });

                const data = await response.json();

                if (response.status === 422) {
                    onValidationError(data);
                    return;
                }

                if (!response.ok) {
                    onError();
                    return;
                }

                await this.fetchCounts();
                this.showToast('success', 'Inventory count saved.');
                this.closeCountForm();
            } catch (error) {
                onError();
            } finally {
                this.isSubmitting = false;
            }
        },
        async openDelete(record) {
            if (!record) {
                return;
            }

            if (record.status !== 'draft') {
                this.showToast('error', 'Inventory count is posted and cannot be modified.');
                return;
            }

            if (!window.confirm('Cancel this inventory count?')) {
                return;
            }

            const deleteUrl = record.delete_url || buildCountEndpoint(this.endpoints.delete, record.id);

            if (!deleteUrl) {
                this.showToast('error', 'Unable to cancel count.');
                return;
            }

            try {
                const response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    this.showToast('error', data?.message || 'Unable to cancel count.');
                    return;
                }

                await this.fetchCounts();
                this.showToast('success', 'Inventory count cancelled.');
            } catch (error) {
                this.showToast('error', 'Unable to cancel count.');
            }
        },
        view(record) {
            if (!record?.show_url) {
                return;
            }

            window.location.assign(record.show_url);
        },
    }));
}
