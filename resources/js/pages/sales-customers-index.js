export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyErrors = () => ({
        name: [],
        status: [],
        notes: [],
    });

    Alpine.data('salesCustomersIndex', () => ({
        customers: safePayload.customers || [],
        storeUrl: safePayload.storeUrl || '',
        updateUrlBase: safePayload.updateUrlBase || '',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        isFormOpen: false,
        isSubmitting: false,
        formMode: 'create',
        editingCustomerId: null,
        form: {
            name: '',
            status: 'active',
            notes: '',
        },
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
        openCreate() {
            this.formMode = 'create';
            this.editingCustomerId = null;
            this.form = {
                name: '',
                status: 'active',
                notes: '',
            };
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
        },
        openEdit(customer) {
            this.formMode = 'edit';
            this.editingCustomerId = customer.id;
            this.form = {
                name: customer.name || '',
                status: customer.status || 'active',
                notes: customer.notes || '',
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
        async submitForm() {
            if (this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';

            const isCreate = this.formMode === 'create';
            const url = isCreate ? this.storeUrl : `${this.updateUrlBase}/${this.editingCustomerId}`;
            const method = isCreate ? 'POST' : 'PATCH';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(
                    isCreate
                        ? {
                            name: this.form.name,
                            notes: this.form.notes || null,
                        }
                        : {
                            name: this.form.name,
                            status: this.form.status,
                            notes: this.form.notes || null,
                        }
                ),
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
            const customer = data.data;

            if (isCreate) {
                this.customers.push(customer);
            } else {
                this.customers = this.customers.map((entry) => entry.id === customer.id ? customer : entry);
            }

            this.customers.sort((left, right) => left.name.localeCompare(right.name));
            this.closeForm();
            this.showToast('success', isCreate ? 'Customer created.' : 'Customer updated.');
        },
        async archive(customer) {
            const response = await fetch(`${this.updateUrlBase}/${customer.id}`, {
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

            this.customers = this.customers.filter((entry) => entry.id !== customer.id);
            this.showToast('success', 'Customer archived.');
        },
    }));
}
