import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyErrors = () => ({
        item_id: [],
        is_active: [],
    });

    Alpine.data('manufacturingRecipesIndex', () => ({
        recipes: safePayload.recipes || [],
        manufacturableItems: safePayload.manufacturable_items || [],
        storeUrl: safePayload.store_url || '',
        updateUrlBase: safePayload.update_url_base || '',
        deleteUrlBase: safePayload.delete_url_base || '',
        csrfToken: safePayload.csrf_token || '',
        isCreateOpen: false,
        isCreateSubmitting: false,
        createForm: { item_id: '', is_active: true },
        createErrors: emptyErrors(),
        createGeneralError: '',
        isEditOpen: false,
        isEditSubmitting: false,
        editForm: { item_id: '', is_active: true },
        editErrors: emptyErrors(),
        editGeneralError: '',
        editRecipeId: null,
        editOutputLocked: false,
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteError: '',
        deleteRecipeId: null,
        deleteRecipeName: '',
        actionMenuOpen: false,
        actionMenuTop: 0,
        actionMenuLeft: 0,
        actionMenuRecipeId: null,
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
                is_active: Array.isArray(errors.is_active) ? errors.is_active : [],
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
            this.createErrors = emptyErrors();
            this.createGeneralError = '';
            this.createForm = { item_id: '', is_active: true };
            this.isCreateOpen = true;
        },
        closeCreate() {
            this.isCreateOpen = false;
            this.isCreateSubmitting = false;
            this.createErrors = emptyErrors();
            this.createGeneralError = '';
            this.createForm = { item_id: '', is_active: true };
        },
        openEdit(recipe) {
            this.editRecipeId = recipe.id;
            this.editForm = {
                item_id: recipe.item_id,
                is_active: recipe.is_active,
            };
            this.editOutputLocked = (recipe.lines_count || 0) > 0;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
            this.isEditOpen = true;
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
            this.editRecipeId = null;
            this.editOutputLocked = false;
        },
        openDelete(recipe) {
            this.deleteRecipeId = recipe.id;
            this.deleteRecipeName = recipe.item_name || '';
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteError = '';
            this.deleteRecipeId = null;
            this.deleteRecipeName = '';
        },
        toggleActionMenu(event, recipeId) {
            if (this.actionMenuOpen && this.actionMenuRecipeId === recipeId) {
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
            this.actionMenuRecipeId = recipeId;
            this.actionMenuOpen = true;
        },
        closeActionMenu() {
            this.actionMenuOpen = false;
            this.actionMenuRecipeId = null;
        },
        isActionMenuOpenFor(recipeId) {
            return this.actionMenuOpen && this.actionMenuRecipeId === recipeId;
        },
        async submitCreate() {
            this.isCreateSubmitting = true;
            this.createGeneralError = '';
            this.createErrors = emptyErrors();

            const response = await fetch(this.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.createForm.item_id,
                    is_active: this.createForm.is_active,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.createErrors = this.normalizeErrors(data.errors);
                this.isCreateSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.createGeneralError = 'Something went wrong. Please try again.';
                this.isCreateSubmitting = false;
                return;
            }

            const data = await response.json();
            this.recipes.unshift(data.data);
            this.showToast('success', 'Recipe created.');
            this.closeCreate();
        },
        async submitEdit() {
            this.isEditSubmitting = true;
            this.editGeneralError = '';
            this.editErrors = emptyErrors();

            const response = await fetch(this.updateUrlBase + '/' + this.editRecipeId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.editForm.item_id,
                    is_active: this.editForm.is_active,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.editErrors = this.normalizeErrors(data.errors);
                this.isEditSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to update recipe.');
                this.isEditSubmitting = false;
                return;
            }

            const data = await response.json();
            const index = this.recipes.findIndex((recipe) => recipe.id === data.data.id);

            if (index !== -1) {
                this.recipes.splice(index, 1, {
                    ...this.recipes[index],
                    ...data.data,
                });
            }

            this.showToast('success', 'Recipe updated.');
            this.closeEdit();
        },
        async submitDelete() {
            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const response = await fetch(this.deleteUrlBase + '/' + this.deleteRecipeId, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                this.deleteError = data.message || 'Unable to delete recipe.';
                this.showToast('error', this.deleteError);
                this.isDeleteSubmitting = false;
                return;
            }

            this.recipes = this.recipes.filter((recipe) => recipe.id !== this.deleteRecipeId);
            this.showToast('success', 'Recipe deleted.');
            this.closeDelete();
        },
    }));
}
