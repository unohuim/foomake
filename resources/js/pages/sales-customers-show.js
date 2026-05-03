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

    const emptyContactErrors = () => ({
        first_name: [],
        last_name: [],
        email: [],
        phone: [],
        role: [],
        is_primary: [],
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

    const emptyContactForm = () => ({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        role: '',
    });

    const contactToForm = (contact) => ({
        first_name: contact.first_name || '',
        last_name: contact.last_name || '',
        email: contact.email || '',
        phone: contact.phone || '',
        role: contact.role || '',
    });

    Alpine.data('salesCustomersShow', () => ({
        customer: safePayload.customer || {},
        contacts: safePayload.contacts || [],
        canManage: safePayload.canManage || false,
        updateUrl: safePayload.updateUrl || '',
        deleteUrl: safePayload.deleteUrl || '',
        contactsStoreUrl: safePayload.contactsStoreUrl || '',
        contactsBaseUrl: safePayload.contactsBaseUrl || '',
        indexUrl: safePayload.indexUrl || '/sales/customers',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        isFormOpen: false,
        isSubmitting: false,
        form: customerToForm(safePayload.customer || {}),
        errors: emptyErrors(),
        generalError: '',
        isContactFormOpen: false,
        isContactSubmitting: false,
        contactFormMode: 'create',
        editingContactId: null,
        contactForm: emptyContactForm(),
        contactErrors: emptyContactErrors(),
        contactGeneralError: '',
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
        normalizeContactErrors(errors) {
            const normalized = emptyContactErrors();

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
            if (!this.canManage) {
                return;
            }

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
        openContactCreate() {
            if (!this.canManage) {
                return;
            }

            this.contactFormMode = 'create';
            this.editingContactId = null;
            this.contactForm = emptyContactForm();
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';
            this.isContactFormOpen = true;
        },
        openContactEdit(contact) {
            if (!this.canManage) {
                return;
            }

            this.contactFormMode = 'edit';
            this.editingContactId = contact.id;
            this.contactForm = contactToForm(contact);
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';
            this.isContactFormOpen = true;
        },
        closeContactForm() {
            this.isContactFormOpen = false;
            this.isContactSubmitting = false;
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';
        },
        upsertContact(contact) {
            const existingIndex = this.contacts.findIndex((entry) => entry.id === contact.id);

            if (existingIndex === -1) {
                this.contacts.push(contact);
            } else {
                this.contacts.splice(existingIndex, 1, contact);
            }

            this.contacts.sort((left, right) => {
                if (left.is_primary === right.is_primary) {
                    return left.full_name.localeCompare(right.full_name);
                }

                return left.is_primary ? -1 : 1;
            });
        },
        async submitForm() {
            if (this.isSubmitting || !this.canManage) {
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
        async submitContactForm() {
            if (this.isContactSubmitting || !this.canManage) {
                return;
            }

            this.isContactSubmitting = true;
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';

            const isCreate = this.contactFormMode === 'create';
            const url = isCreate
                ? this.contactsStoreUrl
                : `${this.contactsBaseUrl}/${this.editingContactId}`;
            const method = isCreate ? 'POST' : 'PATCH';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    first_name: this.contactForm.first_name,
                    last_name: this.contactForm.last_name,
                    email: this.contactForm.email || null,
                    phone: this.contactForm.phone || null,
                    role: this.contactForm.role || null,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.contactErrors = this.normalizeContactErrors(data.errors);
                this.contactGeneralError = data.message || 'Validation failed.';
                this.isContactSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.contactGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.contactGeneralError);
                this.isContactSubmitting = false;
                return;
            }

            const data = await response.json();
            this.upsertContact(data.data);
            this.closeContactForm();
            this.showToast('success', isCreate ? 'Contact created.' : 'Contact updated.');
        },
        async setPrimary(contact) {
            if (!this.canManage) {
                return;
            }

            const response = await fetch(`${this.contactsBaseUrl}/${contact.id}/primary`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to update the primary contact.');
                return;
            }

            const data = await response.json();
            this.contacts = this.contacts.map((entry) => ({
                ...entry,
                is_primary: entry.id === data.data.id,
            }));
            this.upsertContact(data.data);
            this.showToast('success', 'Primary contact updated.');
        },
        async deleteContact(contact) {
            if (!this.canManage) {
                return;
            }

            const response = await fetch(`${this.contactsBaseUrl}/${contact.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to delete contact.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to delete contact.');
                return;
            }

            this.contacts = this.contacts.filter((entry) => entry.id !== contact.id);
            this.showToast('success', 'Contact deleted.');
        },
        async archive() {
            if (!this.canManage) {
                return;
            }

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
