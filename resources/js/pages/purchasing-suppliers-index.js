import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyErrors = () => ({
        company_name: [],
        url: [],
        phone: [],
        email: [],
        currency_code: [],
    });

    Alpine.data('purchasingSuppliersIndex', () => ({
        suppliers: safePayload.suppliers || [],
        storeUrl: safePayload.storeUrl || '',
        csrfToken: safePayload.csrfToken || '',
        defaultCurrency: safePayload.defaultCurrency || '',
        isCreateOpen: false,
        isSubmitting: false,
        errors: emptyErrors(),
        generalError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        form: {
            company_name: '',
            url: '',
            phone: '',
            email: '',
            currency_code: safePayload.defaultCurrency || '',
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                company_name: Array.isArray(errors.company_name) ? errors.company_name : [],
                url: Array.isArray(errors.url) ? errors.url : [],
                phone: Array.isArray(errors.phone) ? errors.phone : [],
                email: Array.isArray(errors.email) ? errors.email : [],
                currency_code: Array.isArray(errors.currency_code) ? errors.currency_code : [],
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
        openCreate() {
            this.isCreateOpen = true;
            this.generalError = '';
            this.errors = emptyErrors();
            if (!this.form.currency_code) {
                this.form.currency_code = this.defaultCurrency || '';
            }
        },
        closeCreate() {
            this.isCreateOpen = false;
            this.isSubmitting = false;
            this.generalError = '';
            this.errors = emptyErrors();
            this.resetForm();
        },
        resetForm() {
            this.form = {
                company_name: '',
                url: '',
                phone: '',
                email: '',
                currency_code: this.defaultCurrency || '',
            };
        },
        async submitCreate() {
            this.isSubmitting = true;
            this.generalError = '';
            this.errors = emptyErrors();

            const payloadData = {
                company_name: this.form.company_name,
                url: this.form.url,
                phone: this.form.phone,
                email: this.form.email,
                currency_code: this.form.currency_code,
            };

            const response = await fetch(this.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payloadData),
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
            if (data.data) {
                this.suppliers.unshift({
                    id: data.data.id,
                    company_name: data.data.company_name,
                    url: data.data.url,
                    phone: data.data.phone,
                    email: data.data.email,
                    currency_code: data.data.currency_code,
                });
            }

            this.showToast('success', 'Supplier created.');
            this.closeCreate();
        },
    }));
}
