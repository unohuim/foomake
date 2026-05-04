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

    Alpine.data('salesOrdersIndex', () => ({
        orders: safePayload.orders || [],
        customers: safePayload.customers || [],
        storeUrl: safePayload.storeUrl || '',
        updateUrlBase: safePayload.updateUrlBase || '',
        deleteUrlBase: safePayload.deleteUrlBase || '',
        csrfToken: safePayload.csrfToken || '',
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
        openCreate() {
            this.formMode = 'create';
            this.editingOrderId = null;
            this.form = emptyForm();
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
        },
        openEdit(order) {
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
                return;
            }

            this.orders.splice(existingIndex, 1, order);
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
