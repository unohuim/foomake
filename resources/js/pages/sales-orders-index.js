export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyErrors = () => ({
        customer_id: [],
        contact_id: [],
    });

    const emptyForm = () => ({
        customer_id: '',
        contact_id: '',
    });

    const emptyLineErrors = () => ({
        item_id: [],
        quantity: [],
    });

    const emptyLineForm = () => ({
        item_id: '',
        quantity: '1.000000',
    });

    Alpine.data('salesOrdersIndex', () => ({
        orders: safePayload.orders || [],
        customers: safePayload.customers || [],
        sellableItems: safePayload.sellableItems || [],
        storeUrl: safePayload.storeUrl || '',
        updateUrlBase: safePayload.updateUrlBase || '',
        deleteUrlBase: safePayload.deleteUrlBase || '',
        lineStoreUrlBase: safePayload.lineStoreUrlBase || '',
        csrfToken: safePayload.csrfToken || '',
        isFormOpen: false,
        isSubmitting: false,
        formMode: 'create',
        editingOrderId: null,
        form: emptyForm(),
        errors: emptyErrors(),
        generalError: '',
        lineForms: {},
        lineErrorsByOrder: {},
        lineGeneralErrorsByOrder: {},
        lineEditQuantities: {},
        lineEditErrorsByLine: {},
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.orders.forEach((order) => {
                this.syncLineState(order);
            });
        },
        normalizeErrors(errors) {
            const normalized = emptyErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        normalizeLineErrors(errors) {
            const normalized = emptyLineErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
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
        ensureLineForm(orderId) {
            if (!this.lineForms[orderId]) {
                this.lineForms[orderId] = emptyLineForm();
            }

            if (!this.lineErrorsByOrder[orderId]) {
                this.lineErrorsByOrder[orderId] = emptyLineErrors();
            }

            if (!Object.prototype.hasOwnProperty.call(this.lineGeneralErrorsByOrder, orderId)) {
                this.lineGeneralErrorsByOrder[orderId] = '';
            }
        },
        syncLineState(order) {
            this.ensureLineForm(order.id);

            (order.lines || []).forEach((line) => {
                this.lineEditQuantities[line.id] = line.quantity;

                if (!this.lineEditErrorsByLine[line.id]) {
                    this.lineEditErrorsByLine[line.id] = emptyLineErrors();
                }
            });
        },
        openCreate() {
            this.formMode = 'create';
            this.editingOrderId = null;
            this.form = emptyForm();
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
        },
        openEdit(order) {
            if (!order || !order.can_edit) {
                return;
            }

            this.formMode = 'edit';
            this.editingOrderId = order.id;
            this.form = {
                customer_id: order.customer_id ? String(order.customer_id) : '',
                contact_id: order.contact_id ? String(order.contact_id) : '',
            };
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
        upsertOrder(order) {
            const existingIndex = this.orders.findIndex((entry) => entry.id === order.id);

            if (existingIndex === -1) {
                this.orders.unshift(order);
                this.syncLineState(order);
                return;
            }

            this.orders.splice(existingIndex, 1, order);
            this.syncLineState(order);
        },
        canEditOrder(order) {
            return !!order?.can_edit;
        },
        canManageOrderLines(order) {
            return !!order?.can_manage_lines;
        },
        canChangeStatus(order) {
            return Array.isArray(order?.available_status_transitions) && order.available_status_transitions.length > 0;
        },
        applyOrderLifecycleUpdate(orderId, data) {
            const existingIndex = this.orders.findIndex((entry) => entry.id === orderId);

            if (existingIndex === -1) {
                return;
            }

            this.orders.splice(existingIndex, 1, {
                ...this.orders[existingIndex],
                status: data.status,
                can_edit: data.can_edit,
                can_manage_lines: data.can_manage_lines,
                available_status_transitions: data.available_status_transitions || [],
            });
        },
        formatLineMoney(amount, currencyCode) {
            return `${currencyCode} ${amount}`;
        },
        async submitForm() {
            if (this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';

            const isCreate = this.formMode === 'create';
            const url = isCreate ? this.storeUrl : `${this.updateUrlBase}/${this.editingOrderId}`;
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

            const data = await response.json();
            this.upsertOrder(data.data);
            this.closeForm();
            this.showToast('success', isCreate ? 'Sales order created.' : 'Sales order updated.');
        },
        async submitStatus(order, status) {
            if (!order || !order.status_update_url) {
                return;
            }

            const response = await fetch(order.status_update_url, {
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
            this.applyOrderLifecycleUpdate(order.id, data.data || {});
            this.showToast('success', 'Status updated.');
        },
        async submitLine(order) {
            if (!this.canManageOrderLines(order)) {
                return;
            }

            this.ensureLineForm(order.id);
            this.lineErrorsByOrder[order.id] = emptyLineErrors();
            this.lineGeneralErrorsByOrder[order.id] = '';

            const response = await fetch(`${this.lineStoreUrlBase}/${order.id}/lines`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.lineForms[order.id].item_id === '' ? null : Number(this.lineForms[order.id].item_id),
                    quantity: this.lineForms[order.id].quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.lineErrorsByOrder[order.id] = this.normalizeLineErrors(data.errors);
                this.lineGeneralErrorsByOrder[order.id] = data.message || 'Validation failed.';
                return;
            }

            if (!response.ok) {
                this.lineGeneralErrorsByOrder[order.id] = 'Unable to add line.';
                this.showToast('error', this.lineGeneralErrorsByOrder[order.id]);
                return;
            }

            const data = await response.json();
            this.upsertOrder(data.data.order);
            this.lineForms[order.id] = emptyLineForm();
            this.lineErrorsByOrder[order.id] = emptyLineErrors();
            this.lineGeneralErrorsByOrder[order.id] = '';
            this.showToast('success', 'Line added.');
        },
        async saveLineQuantity(order, line) {
            if (!this.canManageOrderLines(order)) {
                return;
            }

            this.lineEditErrorsByLine[line.id] = emptyLineErrors();

            const response = await fetch(`${this.lineStoreUrlBase}/${order.id}/lines/${line.id}`, {
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
            this.upsertOrder(data.data.order);
            this.showToast('success', 'Line quantity updated.');
        },
        async deleteLine(order, line) {
            if (!this.canManageOrderLines(order)) {
                return;
            }

            const response = await fetch(`${this.lineStoreUrlBase}/${order.id}/lines/${line.id}`, {
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
            this.upsertOrder(data.data.order);
            this.showToast('success', 'Line removed.');
        },
        async deleteOrder(order) {
            const response = await fetch(`${this.deleteUrlBase}/${order.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to delete sales order.');
                return;
            }

            this.orders = this.orders.filter((entry) => entry.id !== order.id);
            this.showToast('success', 'Sales order deleted.');
        },
    }));
}
