export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyLineErrors = () => ({
        item_id: [],
        quantity: [],
    });

    const emptyLineForm = () => ({
        item_id: '',
        quantity: '1.000000',
    });

    Alpine.data('salesOrdersShow', () => ({
        order: safePayload.order || {},
        sellableItems: safePayload.sellableItems || [],
        lineStoreUrlBase: safePayload.lineStoreUrlBase || '',
        indexUrl: safePayload.indexUrl || '/sales/orders',
        csrfToken: safePayload.csrfToken || '',
        lineForm: emptyLineForm(),
        lineErrors: emptyLineErrors(),
        lineGeneralError: '',
        lineEditQuantities: {},
        lineEditErrorsByLine: {},
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.syncLineState();
        },
        syncLineState() {
            (this.order.lines || []).forEach((line) => {
                this.lineEditQuantities[line.id] = line.quantity;

                if (!this.lineEditErrorsByLine[line.id]) {
                    this.lineEditErrorsByLine[line.id] = emptyLineErrors();
                }
            });
        },
        normalizeLineErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyLineErrors();
            }

            return {
                ...emptyLineErrors(),
                item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
                quantity: Array.isArray(errors.quantity) ? errors.quantity : [],
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
            }, 2500);
        },
        canManageOrderLines() {
            return !!this.order?.can_manage_lines;
        },
        canChangeStatus(order) {
            return Array.isArray(order?.available_status_transitions) && order.available_status_transitions.length > 0;
        },
        formatLineMoney(amount, currencyCode) {
            return `${currencyCode} ${amount}`;
        },
        applyOrderLifecycleUpdate(data) {
            this.order = {
                ...this.order,
                status: data.status,
                can_edit: data.can_edit,
                can_manage_lines: data.can_manage_lines,
                available_status_transitions: data.available_status_transitions || [],
                current_stage_tasks: data.current_stage_tasks || [],
            };
        },
        async submitStatus(status) {
            if (!this.order?.status_update_url) {
                return;
            }

            const response = await fetch(this.order.status_update_url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ status }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to update status.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to update status.');
                return;
            }

            const data = await response.json();
            this.applyOrderLifecycleUpdate(data.data || {});
            this.showToast('success', 'Status updated.');
        },
        async completeTask(task) {
            if (!task?.complete_url || !task.can_complete) {
                return;
            }

            const response = await fetch(task.complete_url, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to complete task.');
                return;
            }

            const data = await response.json();
            const tasks = Array.isArray(this.order.current_stage_tasks) ? [...this.order.current_stage_tasks] : [];
            const taskIndex = tasks.findIndex((entry) => entry.id === data.data?.id);

            if (taskIndex === -1) {
                tasks.push(data.data || {});
            } else {
                tasks.splice(taskIndex, 1, data.data || {});
            }

            this.order.current_stage_tasks = tasks;
            this.showToast('success', 'Task completed.');
        },
        async submitLine() {
            if (!this.canManageOrderLines()) {
                return;
            }

            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';

            const response = await fetch(`${this.lineStoreUrlBase}/${this.order.id}/lines`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.lineForm.item_id === '' ? null : Number(this.lineForm.item_id),
                    quantity: this.lineForm.quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.lineErrors = this.normalizeLineErrors(data.errors);
                this.lineGeneralError = data.message || 'Validation failed.';
                return;
            }

            if (!response.ok) {
                this.lineGeneralError = 'Unable to add line.';
                this.showToast('error', this.lineGeneralError);
                return;
            }

            const data = await response.json();
            this.order = data.data.order;
            this.lineForm = emptyLineForm();
            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';
            this.syncLineState();
            this.showToast('success', 'Line added.');
        },
        async saveLineQuantity(line) {
            if (!this.canManageOrderLines()) {
                return;
            }

            this.lineEditErrorsByLine[line.id] = emptyLineErrors();

            const response = await fetch(`${this.lineStoreUrlBase}/${this.order.id}/lines/${line.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    quantity: this.lineEditQuantities[line.id] || line.quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.lineEditErrorsByLine[line.id] = this.normalizeLineErrors(data.errors);
                this.showToast('error', data.message || 'Unable to update line quantity.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to update line quantity.');
                return;
            }

            const data = await response.json();
            this.order = data.data.order;
            this.syncLineState();
            this.showToast('success', 'Line quantity updated.');
        },
        async deleteLine(line) {
            if (!this.canManageOrderLines()) {
                return;
            }

            const response = await fetch(`${this.lineStoreUrlBase}/${this.order.id}/lines/${line.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to remove line.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to remove line.');
                return;
            }

            const data = await response.json();
            this.order = data.data.order;
            this.syncLineState();
            this.showToast('success', 'Line removed.');
        },
    }));
}
