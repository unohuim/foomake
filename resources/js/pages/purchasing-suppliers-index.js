export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const emptyErrors = () => ({
        company_name: [],
        url: [],
        phone: [],
        email: [],
        currency_code: [],
    });

    const normalizeNullable = (value) => {
        if (value === '') {
            return null;
        }

        return value;
    };

    Alpine.data('purchasingSuppliersIndex', () => ({
        suppliers: safePayload.suppliers || [],
        storeUrl: safePayload.storeUrl || '',
        updateUrlBase: safePayload.updateUrlBase || '',
        csrfToken: safePayload.csrfToken || '',
        defaultCurrency: safePayload.defaultCurrency || '',
        isCreateOpen: false,
        isSubmitting: false,
        errors: emptyErrors(),
        generalError: '',
        isEditOpen: false,
        isEditSubmitting: false,
        editErrors: emptyErrors(),
        editGeneralError: '',
        editSupplierId: null,
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteError: '',
        deleteSupplierId: null,
        deleteSupplierName: '',
        actionMenuOpen: false,
        actionMenuTop: 0,
        actionMenuLeft: 0,
        actionMenuSupplierId: null,
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
        editForm: {
            company_name: '',
            url: '',
            phone: '',
            email: '',
            currency_code: '',
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
        openEdit(supplier) {
            this.closeActionMenu();
            this.editSupplierId = supplier.id;
            this.editForm = {
                company_name: supplier.company_name || '',
                url: supplier.url || '',
                phone: supplier.phone || '',
                email: supplier.email || '',
                currency_code: supplier.currency_code || '',
            };
            this.isEditOpen = true;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
            this.editSupplierId = null;
            this.resetEditForm();
        },
        resetEditForm() {
            this.editForm = {
                company_name: '',
                url: '',
                phone: '',
                email: '',
                currency_code: '',
            };
        },
        openDelete(supplier) {
            this.closeActionMenu();
            this.deleteSupplierId = supplier.id;
            this.deleteSupplierName = supplier.company_name || '';
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteError = '';
            this.deleteSupplierId = null;
            this.deleteSupplierName = '';
        },
        toggleActionMenu(event, supplierId) {
            if (this.actionMenuOpen && this.actionMenuSupplierId === supplierId) {
                this.closeActionMenu();
                return;
            }

            const button = event.currentTarget;
            if (!button) {
                return;
            }

            const rect = button.getBoundingClientRect();

            this.actionMenuTop = rect.bottom;
            this.actionMenuLeft = rect.right;
            this.actionMenuSupplierId = supplierId;
            this.actionMenuOpen = true;
        },
        closeActionMenu() {
            this.actionMenuOpen = false;
            this.actionMenuSupplierId = null;
            this.actionMenuTop = 0;
            this.actionMenuLeft = 0;
        },
        getActionMenuSupplier() {
            return this.suppliers.find((supplier) => supplier.id === this.actionMenuSupplierId) || null;
        },
        openEditFromActionMenu() {
            const supplier = this.getActionMenuSupplier();
            this.closeActionMenu();

            if (!supplier) {
                return;
            }

            this.openEdit(supplier);
        },
        openDeleteFromActionMenu() {
            const supplier = this.getActionMenuSupplier();
            this.closeActionMenu();

            if (!supplier) {
                return;
            }

            this.openDelete(supplier);
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
                const showUrl = data.data.show_url || `${this.updateUrlBase}/${data.data.id}`;
                window.location.href = showUrl;
                return;
            }

            this.generalError = 'Supplier created but unable to determine redirect.';
            this.isSubmitting = false;
        },
        async submitEdit() {
            this.isEditSubmitting = true;
            this.editGeneralError = '';
            this.editErrors = emptyErrors();

            const payloadData = {
                company_name: this.editForm.company_name,
                url: normalizeNullable(this.editForm.url),
                phone: normalizeNullable(this.editForm.phone),
                email: normalizeNullable(this.editForm.email),
                currency_code: normalizeNullable(this.editForm.currency_code),
            };

            const response = await fetch(this.updateUrlBase + '/' + this.editSupplierId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payloadData),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.editErrors = this.normalizeErrors(data.errors);
                this.editGeneralError = data.message || 'Validation failed.';
                this.isEditSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to update supplier.');
                this.isEditSubmitting = false;
                return;
            }

            const data = await response.json();
            const supplierIndex = this.suppliers.findIndex((supplier) => supplier.id === data.data.id);

            if (supplierIndex !== -1) {
                this.suppliers[supplierIndex] = {
                    ...this.suppliers[supplierIndex],
                    id: data.data.id,
                    company_name: data.data.company_name,
                    url: data.data.url,
                    phone: data.data.phone,
                    email: data.data.email,
                    currency_code: data.data.currency_code,
                };
            }

            this.showToast('success', 'Supplier updated.');
            this.closeEdit();
        },
        async submitDelete() {
            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const response = await fetch(this.updateUrlBase + '/' + this.deleteSupplierId, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.deleteError = data.message || 'Unable to delete supplier.';
                this.showToast('error', this.deleteError);
                this.isDeleteSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.deleteError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to delete supplier.');
                this.isDeleteSubmitting = false;
                return;
            }

            this.suppliers = this.suppliers.filter((supplier) => supplier.id !== this.deleteSupplierId);
            this.showToast('success', 'Supplier deleted.');
            this.closeDelete();
        },
    }));
}
