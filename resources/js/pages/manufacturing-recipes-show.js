import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyRecipeErrors = () => ({
        item_id: [],
        recipe_type: [],
        name: [],
        output_quantity: [],
        is_active: [],
    });
    const emptyLineErrors = () => ({
        item_id: [],
        quantity: [],
    });

    Alpine.data('manufacturingRecipesShow', () => ({
        recipe: safePayload.recipe || {
            id: null,
            item_id: '',
            recipe_type: 'manufacturing',
            recipe_type_label: 'Manufacturing',
            name: '',
            item_name: '',
            output_quantity: '0.000000',
            output_quantity_display: '0.0',
            item_uom: '—',
            is_active: false,
            update_url: '',
            delete_url: '',
            has_lines: false,
        },
        manufacturableItems: safePayload.manufacturable_items || [],
        items: safePayload.items || [],
        lines: safePayload.lines || [],
        lineStoreUrl: safePayload.line_store_url || '',
        indexUrl: safePayload.index_url || '',
        csrfToken: safePayload.csrf_token || '',
        isEditOpen: false,
        isEditSubmitting: false,
        editOnlyWithoutRecipe: true,
        editForm: {
            item_id: '',
            recipe_type: 'manufacturing',
            name: '',
            output_quantity: '',
            is_active: true,
        },
        editManufacturingOutputQuantity: '',
        editErrors: emptyRecipeErrors(),
        editGeneralError: '',
        editOutputLocked: false,
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteError: '',
        deleteRecipeName: '',
        isLineFormOpen: false,
        isLineEditing: false,
        isLineSubmitting: false,
        lineForm: { item_id: '', quantity: '' },
        lineErrors: emptyLineErrors(),
        lineGeneralError: '',
        editLineId: null,
        isLineDeleteOpen: false,
        isLineDeleteSubmitting: false,
        deleteLineError: '',
        deleteLineId: null,
        deleteLineItemName: '',
        deleteLineUrl: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        normalizeRecipeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyRecipeErrors();
            }

            return {
                ...emptyRecipeErrors(),
                ...errors,
                item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
                recipe_type: Array.isArray(errors.recipe_type) ? errors.recipe_type : [],
                name: Array.isArray(errors.name) ? errors.name : [],
                output_quantity: Array.isArray(errors.output_quantity) ? errors.output_quantity : [],
                is_active: Array.isArray(errors.is_active) ? errors.is_active : [],
            };
        },
        normalizeLineErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyLineErrors();
            }

            return {
                ...emptyLineErrors(),
                ...errors,
                item_id: Array.isArray(errors.item_id) ? errors.item_id : [],
                quantity: Array.isArray(errors.quantity) ? errors.quantity : [],
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
        resolveItemUom(itemId) {
            const match = this.items.find((item) => String(item.id) === String(itemId));

            return match ? match.uom_display : '—';
        },
        lineItemOptions() {
            const outputId = String(this.recipe.item_id || '');

            return this.items.filter((item) => String(item.id) !== outputId);
        },
        recipeTypeLabel(value) {
            if (value === 'fulfillment') {
                return 'Fulfillment';
            }

            return 'Manufacturing';
        },
        allRecipeTypeOptions() {
            return [
                { value: 'manufacturing', label: 'Manufacturing' },
                { value: 'fulfillment', label: 'Fulfillment' },
            ];
        },
        findOutputItem(itemId) {
            return this.manufacturableItems.find((item) => String(item.id) === String(itemId)) || null;
        },
        selectedOutputItemPrecision(itemId) {
            const outputItem = this.findOutputItem(itemId);

            return Number.isInteger(Number(outputItem?.uom_display_precision))
                ? Number(outputItem.uom_display_precision)
                : 6;
        },
        normalizeQuantityValue(value, precision) {
            const normalizedPrecision = Math.max(0, Math.min(6, Number(precision ?? 6)));
            const parsedValue = Number.parseFloat(String(value ?? '').trim());

            if (Number.isNaN(parsedValue)) {
                return normalizedPrecision === 0 ? '1' : (1).toFixed(normalizedPrecision);
            }

            return normalizedPrecision === 0
                ? String(Math.round(parsedValue))
                : parsedValue.toFixed(normalizedPrecision);
        },
        defaultQuantityForItem(itemId) {
            return this.normalizeQuantityValue('1', this.selectedOutputItemPrecision(itemId));
        },
        recipeTypeOptionsForItem(itemId) {
            const outputItem = this.findOutputItem(itemId);

            if (!outputItem || !Array.isArray(outputItem.allowed_recipe_types) || outputItem.allowed_recipe_types.length === 0) {
                return this.allRecipeTypeOptions();
            }

            return outputItem.allowed_recipe_types.map((recipeType) => ({
                value: recipeType,
                label: this.recipeTypeLabel(recipeType),
            }));
        },
        normalizeRecipeTypeSelection(selectedValue, allowedOptions) {
            if (!Array.isArray(allowedOptions) || allowedOptions.length === 0) {
                return 'manufacturing';
            }

            const selectedRecipeType = String(selectedValue || '');
            const allowedValues = allowedOptions.map((option) => option.value);

            if (allowedValues.includes(selectedRecipeType)) {
                return selectedRecipeType;
            }

            if (allowedValues.includes('manufacturing')) {
                return 'manufacturing';
            }

            return allowedValues[0];
        },
        availableEditRecipeTypeOptions() {
            return this.recipeTypeOptionsForItem(this.editForm.item_id);
        },
        isFulfillmentRecipeType(recipeType) {
            return String(recipeType || '') === 'fulfillment';
        },
        resolvedEditOutputQuantity() {
            return this.isFulfillmentRecipeType(this.editForm.recipe_type)
                ? '1.000000'
                : this.editForm.output_quantity;
        },
        editOutputQuantityDisplayValue() {
            return this.isFulfillmentRecipeType(this.editForm.recipe_type)
                ? this.defaultQuantityForItem(this.editForm.item_id)
                : this.editForm.output_quantity;
        },
        syncEditOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.editForm.recipe_type)) {
                if (this.editForm.output_quantity !== '' && this.editForm.output_quantity !== this.defaultQuantityForItem(this.editForm.item_id)) {
                    this.editManufacturingOutputQuantity = this.editForm.output_quantity;
                }

                this.editForm.output_quantity = this.defaultQuantityForItem(this.editForm.item_id);
                return;
            }

            const fallbackValue = this.editManufacturingOutputQuantity || this.defaultQuantityForItem(this.editForm.item_id);
            this.editForm.output_quantity = this.normalizeQuantityValue(
                fallbackValue,
                this.selectedOutputItemPrecision(this.editForm.item_id)
            );
            this.editManufacturingOutputQuantity = this.editForm.output_quantity;
        },
        normalizeEditOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.editForm.recipe_type)) {
                this.editForm.output_quantity = this.defaultQuantityForItem(this.editForm.item_id);
                return;
            }

            this.editForm.output_quantity = this.normalizeQuantityValue(
                this.editForm.output_quantity,
                this.selectedOutputItemPrecision(this.editForm.item_id)
            );
            this.editManufacturingOutputQuantity = this.editForm.output_quantity;
        },
        syncEditRecipeType() {
            this.editForm.recipe_type = this.normalizeRecipeTypeSelection(
                this.editForm.recipe_type,
                this.availableEditRecipeTypeOptions()
            );
            this.syncEditOutputQuantity();
        },
        filteredEditItems() {
            return this.manufacturableItems.filter((item) => {
                const isSelectedItem = item.id === Number(this.editForm.item_id);

                if (
                    this.editOnlyWithoutRecipe
                    && Boolean(item.has_recipe)
                    && !isSelectedItem
                ) {
                    return false;
                }

                return true;
            });
        },
        openEditRecipe() {
            this.editErrors = emptyRecipeErrors();
            this.editGeneralError = '';
            this.editOnlyWithoutRecipe = true;
            this.editForm = {
                item_id: this.recipe.item_id,
                recipe_type: this.recipe.recipe_type || 'manufacturing',
                name: this.recipe.name,
                output_quantity: this.recipe.output_quantity,
                is_active: this.recipe.is_active,
            };
            this.editManufacturingOutputQuantity = this.recipe.recipe_type === 'manufacturing'
                ? this.recipe.output_quantity
                : this.defaultQuantityForItem(this.recipe.item_id);
            this.syncEditRecipeType();
            this.editOutputLocked = Boolean(this.recipe.has_lines);
            this.isEditOpen = true;
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editErrors = emptyRecipeErrors();
            this.editGeneralError = '';
            this.editOutputLocked = false;
            this.editOnlyWithoutRecipe = true;
            this.editManufacturingOutputQuantity = '';
        },
        openDeleteRecipe() {
            this.deleteRecipeName = this.recipe.item_name || '';
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteError = '';
        },
        async submitDelete() {
            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const response = await fetch(this.recipe.delete_url, {
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

            if (this.indexUrl) {
                window.location.assign(this.indexUrl);
            }
        },
        async submitEdit() {
            this.isEditSubmitting = true;
            this.editGeneralError = '';
            this.editErrors = emptyRecipeErrors();

            const response = await fetch(this.recipe.update_url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.editForm.item_id,
                    recipe_type: this.editForm.recipe_type,
                    name: this.editForm.name,
                    output_quantity: this.resolvedEditOutputQuantity(),
                    is_active: this.editForm.is_active,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.editErrors = this.normalizeRecipeErrors(data.errors);
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
            this.recipe = {
                ...this.recipe,
                ...data.data,
                item_uom: this.resolveItemUom(data.data.item_id),
            };

            this.showToast('success', 'Recipe updated.');
            this.closeEdit();
        },
        openCreateLine() {
            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';
            this.lineForm = { item_id: '', quantity: '' };
            this.editLineId = null;
            this.isLineEditing = false;
            this.isLineFormOpen = true;
        },
        openEditLine(line) {
            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';
            this.lineForm = {
                item_id: line.item_id,
                quantity: line.quantity,
            };
            this.editLineId = line.id;
            this.isLineEditing = true;
            this.isLineFormOpen = true;
        },
        closeLineForm() {
            this.isLineFormOpen = false;
            this.isLineSubmitting = false;
            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';
            this.lineForm = { item_id: '', quantity: '' };
            this.editLineId = null;
            this.isLineEditing = false;
        },
        openDeleteLine(line) {
            this.deleteLineId = line.id;
            this.deleteLineItemName = line.item_name || '';
            this.deleteLineError = '';
            this.deleteLineUrl = line.delete_url || '';
            this.isLineDeleteOpen = true;
        },
        closeDeleteLine() {
            this.isLineDeleteOpen = false;
            this.isLineDeleteSubmitting = false;
            this.deleteLineError = '';
            this.deleteLineId = null;
            this.deleteLineItemName = '';
            this.deleteLineUrl = '';
        },
        async submitLineForm() {
            this.isLineSubmitting = true;
            this.lineErrors = emptyLineErrors();
            this.lineGeneralError = '';

            const url = this.isLineEditing
                ? this.lines.find((line) => line.id === this.editLineId)?.update_url
                : this.lineStoreUrl;

            if (!url) {
                this.lineGeneralError = 'Unable to locate the line endpoint.';
                this.isLineSubmitting = false;
                return;
            }

            const method = this.isLineEditing ? 'PATCH' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.lineForm.item_id,
                    quantity: this.lineForm.quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.lineErrors = this.normalizeLineErrors(data.errors);
                this.isLineSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.lineGeneralError = 'Something went wrong. Please try again.';
                this.isLineSubmitting = false;
                return;
            }

            const data = await response.json();

            if (this.isLineEditing) {
                const index = this.lines.findIndex((line) => line.id === data.data.id);

                if (index !== -1) {
                    this.lines.splice(index, 1, data.data);
                }

                this.showToast('success', 'Line updated.');
            } else {
                this.lines.push(data.data);
                this.showToast('success', 'Line added.');
            }

            this.recipe.has_lines = this.lines.length > 0;
            this.closeLineForm();
        },
        async submitDeleteLine() {
            this.isLineDeleteSubmitting = true;
            this.deleteLineError = '';

            const response = await fetch(this.deleteLineUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                this.deleteLineError = data.message || 'Unable to delete line.';
                this.showToast('error', this.deleteLineError);
                this.isLineDeleteSubmitting = false;
                return;
            }

            this.lines = this.lines.filter((line) => line.id !== this.deleteLineId);
            this.recipe.has_lines = this.lines.length > 0;
            this.showToast('success', 'Line deleted.');
            this.closeDeleteLine();
        },
        init() {
            this.$watch('editForm.item_id', () => {
                this.syncEditRecipeType();
            });

            this.$watch('editForm.recipe_type', () => {
                this.syncEditOutputQuantity();
            });
        },
    }));
}
