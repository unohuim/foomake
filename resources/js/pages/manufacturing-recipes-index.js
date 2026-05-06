import Alpine from 'alpinejs';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyErrors = () => ({
        item_id: [],
        recipe_type: [],
        name: [],
        output_quantity: [],
        is_active: [],
    });

    Alpine.data('manufacturingRecipesIndex', () => ({
        recipes: safePayload.recipes || [],
        manufacturableItems: safePayload.manufacturable_items || [],
        storeUrl: safePayload.store_url || '',
        updateUrlBase: safePayload.update_url_base || '',
        deleteUrlBase: safePayload.delete_url_base || '',
        navigationStateUrl: safePayload.navigationStateUrl || '',
        csrfToken: safePayload.csrf_token || '',
        isCreateOpen: false,
        isCreateSubmitting: false,
        createOnlyWithoutRecipe: true,
        createForm: {
            item_id: '',
            recipe_type: 'manufacturing',
            name: '',
            output_quantity: '',
            is_active: true,
        },
        createManufacturingOutputQuantity: '',
        createErrors: emptyErrors(),
        createGeneralError: '',
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
                recipe_type: Array.isArray(errors.recipe_type) ? errors.recipe_type : [],
                name: Array.isArray(errors.name) ? errors.name : [],
                output_quantity: Array.isArray(errors.output_quantity) ? errors.output_quantity : [],
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
        defaultCreateForm() {
            return {
                item_id: '',
                recipe_type: 'manufacturing',
                name: '',
                output_quantity: this.defaultQuantityForItem(''),
                is_active: true,
            };
        },
        openCreate() {
            this.createErrors = emptyErrors();
            this.createGeneralError = '';
            this.createOnlyWithoutRecipe = true;
            this.createForm = this.defaultCreateForm();
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
            this.syncCreateRecipeType();
            this.isCreateOpen = true;
            this.$nextTick(() => {
                const input = this.$refs.createOutputItemCombobox?.querySelector('input[role="combobox"]');
                input?.focus();
            });
        },
        closeCreate() {
            this.isCreateOpen = false;
            this.isCreateSubmitting = false;
            this.createErrors = emptyErrors();
            this.createGeneralError = '';
            this.createOnlyWithoutRecipe = true;
            this.createForm = this.defaultCreateForm();
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
        },
        openEdit(recipe) {
            this.editRecipeId = recipe.id;
            this.editOnlyWithoutRecipe = true;
            this.editForm = {
                item_id: recipe.item_id,
                recipe_type: recipe.recipe_type || 'manufacturing',
                name: recipe.name,
                output_quantity: recipe.output_quantity,
                is_active: recipe.is_active,
            };
            this.editManufacturingOutputQuantity = recipe.recipe_type === 'manufacturing'
                ? recipe.output_quantity
                : this.defaultQuantityForItem(recipe.item_id);
            this.syncEditRecipeType();
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
            this.editOnlyWithoutRecipe = true;
            this.editManufacturingOutputQuantity = '';
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
        filteredCreateItems() {
            return this.manufacturableItems.filter((item) => {
                if (this.createOnlyWithoutRecipe && Boolean(item.has_recipe)) {
                    return false;
                }

                return true;
            });
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
        selectedOutputItemDisplayName(itemId) {
            return this.findOutputItem(itemId)?.name || '';
        },
        syncCreateNameFromSelectedItem() {
            this.createForm.name = this.selectedOutputItemDisplayName(this.createForm.item_id);
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
        availableCreateRecipeTypeOptions() {
            return this.recipeTypeOptionsForItem(this.createForm.item_id);
        },
        availableEditRecipeTypeOptions() {
            return this.recipeTypeOptionsForItem(this.editForm.item_id);
        },
        isFulfillmentRecipeType(recipeType) {
            return String(recipeType || '') === 'fulfillment';
        },
        resolvedCreateOutputQuantity() {
            return this.isFulfillmentRecipeType(this.createForm.recipe_type)
                ? '1.000000'
                : this.createForm.output_quantity;
        },
        resolvedEditOutputQuantity() {
            return this.isFulfillmentRecipeType(this.editForm.recipe_type)
                ? '1.000000'
                : this.editForm.output_quantity;
        },
        createOutputQuantityDisplayValue() {
            return this.isFulfillmentRecipeType(this.createForm.recipe_type)
                ? this.defaultQuantityForItem(this.createForm.item_id)
                : this.createForm.output_quantity;
        },
        editOutputQuantityDisplayValue() {
            return this.isFulfillmentRecipeType(this.editForm.recipe_type)
                ? this.defaultQuantityForItem(this.editForm.item_id)
                : this.editForm.output_quantity;
        },
        syncCreateOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.createForm.recipe_type)) {
                if (this.createForm.output_quantity !== '' && this.createForm.output_quantity !== this.defaultQuantityForItem(this.createForm.item_id)) {
                    this.createManufacturingOutputQuantity = this.createForm.output_quantity;
                }

                this.createForm.output_quantity = this.defaultQuantityForItem(this.createForm.item_id);
                return;
            }

            const fallbackValue = this.createManufacturingOutputQuantity || this.defaultQuantityForItem(this.createForm.item_id);
            this.createForm.output_quantity = this.normalizeQuantityValue(
                fallbackValue,
                this.selectedOutputItemPrecision(this.createForm.item_id)
            );
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
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
        normalizeCreateOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.createForm.recipe_type)) {
                this.createForm.output_quantity = this.defaultQuantityForItem(this.createForm.item_id);
                return;
            }

            this.createForm.output_quantity = this.normalizeQuantityValue(
                this.createForm.output_quantity,
                this.selectedOutputItemPrecision(this.createForm.item_id)
            );
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
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
        syncCreateRecipeType() {
            this.createForm.recipe_type = this.normalizeRecipeTypeSelection(
                this.createForm.recipe_type,
                this.availableCreateRecipeTypeOptions()
            );
            this.syncCreateOutputQuantity();
        },
        syncEditRecipeType() {
            this.editForm.recipe_type = this.normalizeRecipeTypeSelection(
                this.editForm.recipe_type,
                this.availableEditRecipeTypeOptions()
            );
            this.syncEditOutputQuantity();
        },
        init() {
            this.$watch('createForm.item_id', () => {
                this.syncCreateNameFromSelectedItem();
                this.syncCreateRecipeType();
            });

            this.$watch('createForm.recipe_type', () => {
                this.syncCreateOutputQuantity();
            });

            this.$watch('editForm.item_id', () => {
                this.syncEditRecipeType();
            });

            this.$watch('editForm.recipe_type', () => {
                this.syncEditOutputQuantity();
            });
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
                    recipe_type: this.createForm.recipe_type,
                    name: this.createForm.name,
                    output_quantity: this.resolvedCreateOutputQuantity(),
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
            await refreshNavigationState(this.navigationStateUrl);
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
                    recipe_type: this.editForm.recipe_type,
                    name: this.editForm.name,
                    output_quantity: this.resolvedEditOutputQuantity(),
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

            await refreshNavigationState(this.navigationStateUrl);
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
            await refreshNavigationState(this.navigationStateUrl);
            this.showToast('success', 'Recipe deleted.');
            this.closeDelete();
        },
    }));
}
