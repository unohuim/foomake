import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};

    Alpine.data('uomCategoriesIndex', () => ({
        categories: safePayload.categories || [],
        storeUrl: safePayload.storeUrl || '',
        updateUrlTemplate: safePayload.updateUrlTemplate || '',
        deleteUrlTemplate: safePayload.deleteUrlTemplate || '',
        csrfToken: safePayload.csrfToken || '',
        formOpen: false,
        deleteOpen: false,
        isEditing: false,
        isSubmitting: false,
        form: { id: null, name: '' },
        errors: {},
        errorMessage: '',
        deleteTarget: null,

        openCreate() {
            this.resetErrors();
            this.form = { id: null, name: '' };
            this.isEditing = false;
            this.formOpen = true;
        },

        openEdit(category) {
            this.resetErrors();
            this.form = { id: category.id, name: category.name };
            this.isEditing = true;
            this.formOpen = true;
        },

        closeForm() {
            this.formOpen = false;
        },

        openDelete(category) {
            this.resetErrors();
            this.deleteTarget = category;
            this.deleteOpen = true;
        },

        closeDelete() {
            this.deleteOpen = false;
            this.deleteTarget = null;
        },

        resetErrors() {
            this.errors = {};
            this.errorMessage = '';
        },

        updateCategory(updated) {
            const index = this.categories.findIndex((category) => category.id === updated.id);
            if (index >= 0) {
                this.categories.splice(index, 1, updated);
            } else {
                this.categories.push(updated);
            }
            this.sortCategories();
        },

        removeCategory(id) {
            this.categories = this.categories.filter((category) => category.id !== id);
        },

        sortCategories() {
            this.categories = this.categories.sort((a, b) => a.name.localeCompare(b.name));
        },

        async submitForm() {
            this.resetErrors();
            this.isSubmitting = true;

            const url = this.isEditing
                ? this.updateUrlTemplate.replace('__ID__', this.form.id)
                : this.storeUrl;

            const method = this.isEditing ? 'PATCH' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ name: this.form.name }),
            });

            this.isSubmitting = false;

            if (response.status === 422) {
                const data = await response.json();
                this.errors = data.errors || {};
                return;
            }

            if (!response.ok) {
                this.errorMessage = 'Something went wrong. Please try again.';
                return;
            }

            const data = await response.json();
            this.updateCategory(data);
            this.closeForm();
        },

        async confirmDelete() {
            if (!this.deleteTarget) {
                return;
            }

            this.isSubmitting = true;

            const response = await fetch(
                this.deleteUrlTemplate.replace('__ID__', this.deleteTarget.id),
                {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                }
            );

            this.isSubmitting = false;

            if (!response.ok) {
                this.errorMessage = 'Unable to delete the category.';
                return;
            }

            this.removeCategory(this.deleteTarget.id);
            this.closeDelete();
        },
    }));
}
