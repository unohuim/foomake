import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const payloadUoms = safePayload.uoms || [];
    const emptyErrors = () => ({
        name: [],
        base_uom_id: [],
    });

    Alpine.data('materialsIndex', () => ({
        items: safePayload.items || [],
        uoms: payloadUoms,
        uomsById: {},
        uomsExist: Boolean(safePayload.uomsExist),
        updateUrlBase: safePayload.updateUrlBase || '',
        showUrlBase: safePayload.showUrlBase || '',
        storeUrl: safePayload.storeUrl || '',
        csrfToken: safePayload.csrfToken || '',
        isCreateOpen: false,
        isSubmitting: false,
        errors: emptyErrors(),
        generalError: '',
        isEditOpen: false,
        isEditSubmitting: false,
        editErrors: emptyErrors(),
        editGeneralError: '',
        editItemId: null,
        editBaseUomLocked: false,
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteError: '',
        deleteItemId: null,
        deleteItemName: '',
        actionMenuOpen: false,
        actionMenuTop: 0,
        actionMenuLeft: 0,
        actionMenuItemId: null,
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        form: {
            name: '',
            base_uom_id: '',
            is_purchasable: false,
            is_sellable: false,
            is_manufacturable: false,
        },
        editForm: {
            name: '',
            base_uom_id: '',
            is_purchasable: false,
            is_sellable: false,
            is_manufacturable: false,
        },
        init() {
            this.uomsById = this.uoms.reduce((map, uom) => {
                map[uom.id] = uom;
                return map;
            }, {});
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                name: Array.isArray(errors.name) ? errors.name : [],
                base_uom_id: Array.isArray(errors.base_uom_id) ? errors.base_uom_id : [],
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
            if (!this.uomsExist) {
                return;
            }

            this.isCreateOpen = true;
            this.generalError = '';
            this.errors = emptyErrors();
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
                name: '',
                base_uom_id: '',
                is_purchasable: false,
                is_sellable: false,
                is_manufacturable: false,
            };
        },
        openEdit(item) {
            this.closeActionMenu();
            this.editItemId = item.id;
            this.editForm = {
                name: item.name,
                base_uom_id: item.base_uom_id,
                is_purchasable: item.is_purchasable,
                is_sellable: item.is_sellable,
                is_manufacturable: item.is_manufacturable,
            };
            this.editBaseUomLocked = item.has_stock_moves;
            this.isEditOpen = true;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editErrors = emptyErrors();
            this.editGeneralError = '';
            this.editItemId = null;
            this.editBaseUomLocked = false;
            this.resetEditForm();
        },
        resetEditForm() {
            this.editForm = {
                name: '',
                base_uom_id: '',
                is_purchasable: false,
                is_sellable: false,
                is_manufacturable: false,
            };
        },
        openDelete(item) {
            this.closeActionMenu();
            this.deleteItemId = item.id;
            this.deleteItemName = item.name;
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteError = '';
            this.deleteItemId = null;
            this.deleteItemName = '';
        },
        toggleActionMenu(event, itemId) {
            if (this.actionMenuOpen && this.actionMenuItemId === itemId) {
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
            this.actionMenuItemId = itemId;
            this.actionMenuOpen = true;
        },
        closeActionMenu() {
            this.actionMenuOpen = false;
            this.actionMenuItemId = null;
            this.actionMenuTop = 0;
            this.actionMenuLeft = 0;
        },
        isActionMenuOpenFor(itemId) {
            return this.actionMenuOpen && this.actionMenuItemId === itemId;
        },
        getActionMenuItem() {
            return this.items.find((item) => item.id === this.actionMenuItemId) || null;
        },
        openEditFromActionMenu() {
            const item = this.getActionMenuItem();
            this.closeActionMenu();

            if (!item) {
                return;
            }

            this.openEdit(item);
        },
        openDeleteFromActionMenu() {
            const item = this.getActionMenuItem();
            this.closeActionMenu();

            if (!item) {
                return;
            }

            this.openDelete(item);
        },
        async submitCreate() {
            this.isSubmitting = true;
            this.generalError = '';
            this.errors = emptyErrors();

            const payload = {
                name: this.form.name,
                base_uom_id: this.form.base_uom_id,
                is_purchasable: this.form.is_purchasable,
                is_sellable: this.form.is_sellable,
                is_manufacturable: this.form.is_manufacturable,
            };

            const response = await fetch(this.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = this.normalizeErrors(data.errors);
                this.isSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.generalError = 'Something went wrong. Please try again.';
                this.isSubmitting = false;
                return;
            }

            const data = await response.json();
            const uom = this.uomsById[data.data.base_uom_id] || { name: '', symbol: '' };

            this.items.unshift({
                id: data.data.id,
                name: data.data.name,
                base_uom_id: data.data.base_uom_id,
                base_uom_name: uom.name,
                base_uom_symbol: uom.symbol,
                is_purchasable: data.data.is_purchasable,
                is_sellable: data.data.is_sellable,
                is_manufacturable: data.data.is_manufacturable,
                has_stock_moves: false,
            });

            this.closeCreate();
        },
        async submitEdit() {
            this.isEditSubmitting = true;
            this.editGeneralError = '';
            this.editErrors = emptyErrors();

            const payload = {
                name: this.editForm.name,
                base_uom_id: this.editForm.base_uom_id,
                is_purchasable: this.editForm.is_purchasable,
                is_sellable: this.editForm.is_sellable,
                is_manufacturable: this.editForm.is_manufacturable,
            };

            const response = await fetch(this.updateUrlBase + '/' + this.editItemId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.editErrors = this.normalizeErrors(data.errors);
                this.isEditSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to update material.');
                this.isEditSubmitting = false;
                return;
            }

            const data = await response.json();
            const uom = this.uomsById[data.data.base_uom_id] || { name: '', symbol: '' };
            const itemIndex = this.items.findIndex((item) => item.id === data.data.id);

            if (itemIndex !== -1) {
                this.items[itemIndex] = {
                    ...this.items[itemIndex],
                    id: data.data.id,
                    name: data.data.name,
                    base_uom_id: data.data.base_uom_id,
                    base_uom_name: uom.name,
                    base_uom_symbol: uom.symbol,
                    is_purchasable: data.data.is_purchasable,
                    is_sellable: data.data.is_sellable,
                    is_manufacturable: data.data.is_manufacturable,
                    has_stock_moves: data.data.has_stock_moves ?? this.items[itemIndex].has_stock_moves,
                };
            }

            this.showToast('success', 'Material updated.');
            this.closeEdit();
        },
        async submitDelete() {
            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const response = await fetch(this.updateUrlBase + '/' + this.deleteItemId, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.deleteError = data.message || 'Unable to delete material.';
                this.showToast('error', this.deleteError);
                this.isDeleteSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.deleteError = 'Something went wrong. Please try again.';
                this.showToast('error', 'Unable to delete material.');
                this.isDeleteSubmitting = false;
                return;
            }

            this.items = this.items.filter((item) => item.id !== this.deleteItemId);
            this.showToast('success', 'Material deleted.');
            this.closeDelete();
        },
    }));
}
