import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => {
        const actionConfig = {
            ...action,
            handler: '',
            showExpression: 'false',
        };

        if (action.id === 'change-role') {
            actionConfig.handler = 'openChangeRole(record)';
            actionConfig.showExpression = 'record.type === "member" || record.status_key === "pending-invitation"';
        }

        if (action.id === 'remove-member') {
            actionConfig.handler = 'openRemoveMember(record)';
            actionConfig.showExpression = 'record.type === "member"';
        }

        if (action.id === 'resend-invite') {
            actionConfig.handler = 'resendInvite(record)';
            actionConfig.showExpression = 'record.type === "invitation"';
        }

        if (action.id === 'revoke-invite') {
            actionConfig.handler = 'openRevokeInvite(record)';
            actionConfig.showExpression = 'record.type === "invitation"';
        }

        return actionConfig;
    });
    const rendererConfig = {
        ...crud,
        state: {
            records: 'rows',
            loading: 'isLoadingList',
            error: 'listError',
            search: 'search',
            sort: 'sort',
        },
        handlers: {
            searchInput: 'handleSearchInput()',
            toggleSort: 'toggleSort(column)',
            create: 'openInvitePanel()',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'userCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    };

    mountCrudRenderer(crudRootEl, rendererConfig);

    const emptyInviteForm = () => ({
        email: '',
        role_id: '',
    });

    const emptyErrors = () => ({
        email: [],
        role_id: [],
    });

    Alpine.data('adminUsersIndex', () => ({
        crud,
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        rows: [],
        roles: Array.isArray(safePayload.roles) ? safePayload.roles : [],
        storeInvitationUrl: safePayload.storeInvitationUrl || '',
        csrfToken: safePayload.csrfToken || '',
        canManageUsers: Boolean(safePayload.canManageUsers),
        isLoadingList: false,
        listError: '',
        search: '',
        sort: {
            column: 'name',
            direction: 'asc',
        },
        invitePanelOpen: false,
        submittingInvite: false,
        inviteForm: emptyInviteForm(),
        errors: emptyErrors(),
        generalError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchRows();
        },
        columnHeader(column) {
            return this.headers[column] || column;
        },
        isSortableColumn(column) {
            return this.sortable.includes(column);
        },
        userCellText(record, column) {
            return record?.[column] || '-';
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                email: Array.isArray(errors.email) ? errors.email : [],
                role_id: Array.isArray(errors.role_id) ? errors.role_id : [],
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
        async fetchRows() {
            await this.crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.rows = Array.isArray(data?.data) ? data.data : [];

                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: () => {
                    this.listError = 'Unable to load users.';
                },
                onError: () => {
                    this.listError = 'Unable to load users.';
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchRows();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = this.crud.nextSort(this.sort, column);
            this.fetchRows();
        },
        openInvitePanel() {
            if (!this.canManageUsers) {
                return;
            }

            this.inviteForm = emptyInviteForm();
            this.errors = emptyErrors();
            this.generalError = '';
            this.invitePanelOpen = true;
            this.$nextTick(() => {
                this.$refs.inviteEmailInput?.focus();
            });
        },
        closeInvitePanel() {
            this.invitePanelOpen = false;
            this.submittingInvite = false;
        },
        openChangeRole(record) {
            this.showToast('error', `Role changes are not available for ${record.email}.`);
        },
        openRemoveMember(record) {
            this.showToast('error', `Member removal is not available for ${record.email}.`);
        },
        async resendInvite(record) {
            if (!record?.resend_url) {
                return;
            }

            const response = await fetch(record.resend_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to resend invitation.');
                return;
            }

            await this.fetchRows();
            this.showToast('success', 'Invitation resent.');
        },
        async openRevokeInvite(record) {
            if (!record?.revoke_url) {
                return;
            }

            const response = await fetch(record.revoke_url, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to revoke invitation.');
                return;
            }

            await this.fetchRows();
            this.showToast('success', 'Invitation revoked.');
        },
        async submitInvite() {
            if (!this.storeInvitationUrl || this.submittingInvite) {
                return;
            }

            this.submittingInvite = true;
            this.errors = emptyErrors();
            this.generalError = '';

            const response = await fetch(this.storeInvitationUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    email: this.inviteForm.email,
                    role_id: this.inviteForm.role_id === '' ? null : Number(this.inviteForm.role_id),
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.errors = this.normalizeErrors(data.errors || {});
                this.generalError = data.message || 'Unable to create invitation.';
                this.showToast('error', this.generalError);
                this.submittingInvite = false;
                return;
            }

            await this.fetchRows();
            this.showToast('success', 'Invitation sent.');
            this.closeInvitePanel();
        },
    }));
}
