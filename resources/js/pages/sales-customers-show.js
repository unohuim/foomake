export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyErrors = () => ({
        name: [],
        status: [],
        notes: [],
        address_line_1: [],
        address_line_2: [],
        city: [],
        region: [],
        postal_code: [],
        country_code: [],
        formatted_address: [],
    });

    const customerToForm = (customer) => ({
        name: customer.name || '',
        status: customer.status || 'active',
        notes: customer.notes || '',
        address_line_1: customer.address_line_1 || '',
        address_line_2: customer.address_line_2 || '',
        city: customer.city || '',
        region: customer.region || '',
        postal_code: customer.postal_code || '',
        country_code: customer.country_code || '',
        formatted_address: customer.formatted_address || '',
    });

    Alpine.data('salesCustomersShow', () => ({
        customer: safePayload.customer || {},
        updateUrl: safePayload.updateUrl || '',
        deleteUrl: safePayload.deleteUrl || '',
        indexUrl: safePayload.indexUrl || '/sales/customers',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        isFormOpen: false,
        isSubmitting: false,
        form: customerToForm(safePayload.customer || {}),
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
        openEdit() {
            this.form = customerToForm(this.customer);
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
        async submitForm() {
            if (this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';
            const addressPayload = {
                address_line_1: this.form.address_line_1 || null,
                address_line_2: this.form.address_line_2 || null,
                city: this.form.city || null,
                region: this.form.region || null,
                postal_code: this.form.postal_code || null,
                country_code: this.form.country_code || null,
                formatted_address: this.form.formatted_address || null,
            };

            const response = await fetch(this.updateUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    name: this.form.name,
                    status: this.form.status,
                    notes: this.form.notes || null,
                    ...addressPayload,
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
            this.customer = data.data;
            this.closeForm();
            this.showToast('success', 'Customer updated.');
        },
        async archive() {
            const response = await fetch(this.deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to archive customer.');
                return;
            }

            const data = await response.json();
            this.customer = data.data;
            window.location.href = this.indexUrl;
        },
    }));
}
