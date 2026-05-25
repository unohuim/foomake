import Alpine from 'alpinejs';
import { parseCrudConfig } from '../lib/crud-config';
import { mountCrudRenderer } from '../lib/crud-page';
import { createGenericCrud } from '../lib/generic-crud';
import { refreshNavigationState } from '../navigation/refresh-navigation-state';

export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const crud = createGenericCrud(parseCrudConfig(rootEl));
    const crudRootEl = rootEl.querySelector('[data-crud-root]');
    const actionDefinitions = (Array.isArray(crud.actions) ? crud.actions : []).map((action) => ({
        ...action,
        handler: action.id === 'make'
            ? 'make(record)'
            : action.id === 'edit'
                ? 'openEdit(record)'
                : action.id === 'archive'
                    ? 'archive(record)'
                    : '',
    }));

    mountCrudRenderer(crudRootEl, {
        ...crud,
        state: {
            records: 'recipes',
            loading: 'isLoadingList',
            error: 'listError',
            search: 'search',
            sort: 'sort',
        },
        handlers: {
            searchInput: 'handleSearchInput()',
            toggleSort: 'toggleSort(column)',
            create: 'openCreate()',
        },
        rowDisplay: {
            ...crud.rowDisplay,
            cellTextExpression: 'recipeCellText(record, column)',
        },
        mobileCard: {
            ...crud.mobileCard,
        },
        actions: actionDefinitions,
    });

    const emptyCreateErrors = () => ({
        item_id: [],
        recipe_type: [],
        name: [],
        output_quantity: [],
        is_active: [],
        is_default: [],
    });
    const emptyEditErrors = () => ({
        name: [],
        is_active: [],
        is_default: [],
    });
    const emptyVersionErrors = () => ({
        name: [],
        recipe_type: [],
        output_quantity: [],
        status: [],
        lines: [],
    });

    Alpine.data('manufacturingRecipesIndex', () => ({
        crud,
        endpoints: crud.endpoints || {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers || {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        recipes: Array.isArray(safePayload.initial_rows) ? safePayload.initial_rows : [],
        manufacturableItems: Array.isArray(safePayload.manufacturable_items) ? safePayload.manufacturable_items : [],
        csrfToken: safePayload.csrf_token || '',
        canManage: Boolean(safePayload.can_manage),
        listError: '',
        isLoadingList: false,
        search: '',
        sort: {
            column: 'updated_at',
            direction: 'desc',
        },
        isCreateOpen: false,
        isCreateSubmitting: false,
        createOnlyWithoutRecipe: true,
        createForm: {
            item_id: '',
            recipe_type: 'manufacturing',
            name: '',
            output_quantity: '',
            is_active: true,
            is_default: false,
        },
        createManufacturingOutputQuantity: '',
        createErrors: emptyCreateErrors(),
        createGeneralError: '',
        isEditOpen: false,
        isEditSubmitting: false,
        editRecipeId: null,
        editForm: {
            name: '',
            is_active: true,
            is_default: false,
        },
        editErrors: emptyEditErrors(),
        editGeneralError: '',
        isDeleteOpen: false,
        isDeleteSubmitting: false,
        deleteRecipeId: null,
        deleteRecipeName: '',
        deleteError: '',
        isVersionOpen: false,
        isVersionSubmitting: false,
        versionRecipeId: null,
        versionRecipeName: '',
        versionForm: {
            name: '',
            recipe_type: 'manufacturing',
            output_quantity: '1.000000',
            status: 'DRAFT',
        },
        versionErrors: emptyVersionErrors(),
        versionGeneralError: '',
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.fetchRecipes();

            const prefillCreate = safePayload.prefill_create || {};

            if (prefillCreate.open === true) {
                this.openCreate({
                    item_id: prefillCreate.item_id || '',
                });
            }
        },
        columnHeader(column) {
            return crud.headers?.[column] || column;
        },
        isSortableColumn(column) {
            return Array.isArray(crud.sortable) && crud.sortable.includes(column);
        },
        recipeCellText(record, column) {
            if (column === 'output_quantity') {
                return record?.output_quantity_display || record?.output_quantity || '—';
            }

            if (column === 'current_version_number') {
                return record?.current_version_number_display || '—';
            }

            return record?.[column] || '—';
        },
        recipeMobileSummary(record) {
            const parts = [];

            if (record?.current_version_number) {
                parts.push(record.current_version_number_display || '—');
            }

            if (record?.output_quantity_display || record?.output_quantity) {
                parts.push(`Qty ${record.output_quantity_display || record.output_quantity}`);
            }

            parts.push(record?.updated_at || '—');

            return parts.join(' · ');
        },
        defaultCreateForm() {
            return {
                item_id: '',
                recipe_type: 'manufacturing',
                name: '',
                output_quantity: this.defaultQuantityForItem(''),
                is_active: true,
                is_default: false,
            };
        },
        normalizeCreateErrors(errors) {
            return {
                ...emptyCreateErrors(),
                ...(errors || {}),
            };
        },
        normalizeEditErrors(errors) {
            return {
                ...emptyEditErrors(),
                ...(errors || {}),
            };
        },
        normalizeVersionErrors(errors) {
            return {
                ...emptyVersionErrors(),
                ...(errors || {}),
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
            const normalizedValue = String(value ?? '').trim();

            if (!/^\d+(?:\.\d+)?$/.test(normalizedValue)) {
                return normalizedPrecision === 0 ? '1' : `1.${''.padEnd(normalizedPrecision, '0')}`;
            }

            const [wholePart, decimalPart = ''] = normalizedValue.split('.', 2);

            if (normalizedPrecision === 0) {
                return wholePart;
            }

            return `${wholePart}.${decimalPart.padEnd(normalizedPrecision, '0').slice(0, normalizedPrecision)}`;
        },
        defaultQuantityForItem(itemId) {
            return this.normalizeQuantityValue('1', this.selectedOutputItemPrecision(itemId));
        },
        selectedOutputItemDisplayName(itemId) {
            return this.findOutputItem(itemId)?.name || '';
        },
        filteredCreateItems() {
            return this.manufacturableItems.filter((item) => {
                if (this.createOnlyWithoutRecipe && Boolean(item.has_recipe)) {
                    return false;
                }

                return true;
            });
        },
        recipeTypeLabel(value) {
            return value === 'fulfillment' ? 'Fulfillment' : 'Manufacturing';
        },
        allRecipeTypeOptions() {
            return [
                { value: 'manufacturing', label: 'Manufacturing' },
                { value: 'fulfillment', label: 'Fulfillment' },
            ];
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
        isFulfillmentRecipeType(recipeType) {
            return String(recipeType || '') === 'fulfillment';
        },
        syncCreateRecipeType() {
            this.createForm.recipe_type = this.normalizeRecipeTypeSelection(
                this.createForm.recipe_type,
                this.availableCreateRecipeTypeOptions()
            );

            if (this.isFulfillmentRecipeType(this.createForm.recipe_type)) {
                this.createForm.output_quantity = '1.000000';
                return;
            }

            this.createForm.output_quantity = this.normalizeQuantityValue(
                this.createManufacturingOutputQuantity || this.defaultQuantityForItem(this.createForm.item_id),
                this.selectedOutputItemPrecision(this.createForm.item_id)
            );
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
        },
        normalizeCreateOutputQuantity() {
            if (this.isFulfillmentRecipeType(this.createForm.recipe_type)) {
                this.createForm.output_quantity = '1.000000';
                return;
            }

            this.createForm.output_quantity = this.normalizeQuantityValue(
                this.createForm.output_quantity,
                this.selectedOutputItemPrecision(this.createForm.item_id)
            );
            this.createManufacturingOutputQuantity = this.createForm.output_quantity;
        },
        syncCreateNameFromSelectedItem() {
            this.createForm.name = this.selectedOutputItemDisplayName(this.createForm.item_id);
        },
        async fetchRecipes() {
            await crud.fetchList({
                search: this.search,
                sort: this.sort,
                onStart: () => {
                    this.isLoadingList = true;
                    this.listError = '';
                },
                onSuccess: (data) => {
                    this.recipes = Array.isArray(data?.data) ? data.data : [];
                    if (data?.meta?.sort?.column && data?.meta?.sort?.direction) {
                        this.sort = {
                            column: data.meta.sort.column,
                            direction: data.meta.sort.direction,
                        };
                    }
                },
                onValidationError: () => {
                    this.listError = 'Unable to load recipes.';
                },
                onError: () => {
                    this.listError = 'Unable to load recipes.';
                },
                onFinally: () => {
                    this.isLoadingList = false;
                },
            });
        },
        handleSearchInput() {
            this.fetchRecipes();
        },
        toggleSort(column) {
            if (!this.isSortableColumn(column)) {
                return;
            }

            this.sort = crud.nextSort(this.sort, column);
            this.fetchRecipes();
        },
        openCreate(prefill = {}) {
            this.createErrors = emptyCreateErrors();
            this.createGeneralError = '';
            this.createOnlyWithoutRecipe = !prefill.item_id;
            this.createForm = this.defaultCreateForm();
            this.createForm.item_id = prefill.item_id ? String(prefill.item_id) : '';
            this.syncCreateRecipeType();
            this.syncCreateNameFromSelectedItem();
            this.isCreateOpen = true;
        },
        closeCreate() {
            this.isCreateOpen = false;
            this.isCreateSubmitting = false;
            this.createErrors = emptyCreateErrors();
            this.createGeneralError = '';
        },
        async submitCreate() {
            this.isCreateSubmitting = true;
            this.createErrors = emptyCreateErrors();
            this.createGeneralError = '';

            await crud.submitCreate({
                body: {
                    item_id: this.createForm.item_id ? Number(this.createForm.item_id) : '',
                    recipe_type: this.createForm.recipe_type,
                    name: this.createForm.name,
                    output_quantity: this.createForm.output_quantity,
                    is_active: this.createForm.is_active,
                    is_default: this.createForm.is_default,
                },
                csrfToken: this.csrfToken,
                onValidationError: (data) => {
                    this.createErrors = this.normalizeCreateErrors(data.errors);
                    this.createGeneralError = data.message || 'Validation failed.';
                },
                onError: () => {
                    this.createGeneralError = 'Something went wrong. Please try again.';
                    this.showToast('error', this.createGeneralError);
                },
                onSuccess: async () => {
                    await this.fetchRecipes();
                    await refreshNavigationState(this.navigationStateUrl);
                    this.closeCreate();
                    this.showToast('success', 'Recipe created.');
                },
                onFinally: () => {
                    this.isCreateSubmitting = false;
                },
            });
        },
        openEdit(record) {
            this.editRecipeId = record.id;
            this.editErrors = emptyEditErrors();
            this.editGeneralError = '';
            this.editForm = {
                name: record.name || '',
                is_active: record.version_status !== 'ARCHIVED',
                is_default: Boolean(record.is_default),
            };
            this.isEditOpen = true;
        },
        closeEdit() {
            this.isEditOpen = false;
            this.isEditSubmitting = false;
            this.editRecipeId = null;
            this.editErrors = emptyEditErrors();
            this.editGeneralError = '';
        },
        async submitEdit() {
            this.isEditSubmitting = true;
            this.editErrors = emptyEditErrors();
            this.editGeneralError = '';

            const endpoint = (this.endpoints.update || '').replace('{id}', encodeURIComponent(String(this.editRecipeId)));

            try {
                const response = await fetch(endpoint, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(this.editForm),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.editErrors = this.normalizeEditErrors(data.errors);
                    this.editGeneralError = data.message || 'Validation failed.';
                    return;
                }

                if (!response.ok) {
                    this.editGeneralError = 'Something went wrong. Please try again.';
                    this.showToast('error', this.editGeneralError);
                    return;
                }

                await this.fetchRecipes();
                await refreshNavigationState(this.navigationStateUrl);
                this.closeEdit();
                this.showToast('success', 'Recipe updated.');
            } catch (error) {
                this.editGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.editGeneralError);
            } finally {
                this.isEditSubmitting = false;
            }
        },
        versionRecipeTypeOptions() {
            return this.allRecipeTypeOptions();
        },
        openVersion(record) {
            this.versionRecipeId = record.id;
            this.versionRecipeName = record.name || '';
            this.versionErrors = emptyVersionErrors();
            this.versionGeneralError = '';
            this.versionForm = {
                recipe_type: record.recipe_type || 'manufacturing',
                output_quantity: record.output_quantity || '1.000000',
            };
            this.isVersionOpen = true;
        },
        closeVersion() {
            this.isVersionOpen = false;
            this.isVersionSubmitting = false;
            this.versionRecipeId = null;
            this.versionRecipeName = '';
            this.versionErrors = emptyVersionErrors();
            this.versionGeneralError = '';
        },
        async submitVersion() {
            this.isVersionSubmitting = true;
            this.versionErrors = emptyVersionErrors();
            this.versionGeneralError = '';

            const endpoint = `${this.endpoints.versionStore.replace('{id}', encodeURIComponent(String(this.versionRecipeId)))}`;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(this.versionForm),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.versionErrors = this.normalizeVersionErrors(data.errors);
                    this.versionGeneralError = data.message || 'Validation failed.';
                    return;
                }

                if (!response.ok) {
                    this.versionGeneralError = 'Something went wrong. Please try again.';
                    this.showToast('error', this.versionGeneralError);
                    return;
                }

                await this.fetchRecipes();
                this.closeVersion();
                this.showToast('success', 'Recipe version created.');
            } catch (error) {
                this.versionGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.versionGeneralError);
            } finally {
                this.isVersionSubmitting = false;
            }
        },
        openDelete(record) {
            this.deleteRecipeId = record.id;
            this.deleteRecipeName = record.name || '';
            this.deleteError = '';
            this.isDeleteOpen = true;
        },
        closeDelete() {
            this.isDeleteOpen = false;
            this.isDeleteSubmitting = false;
            this.deleteRecipeId = null;
            this.deleteRecipeName = '';
            this.deleteError = '';
        },
        async archive(record) {
            this.openDelete(record);
        },
        async make(record) {
            if (!record?.make_url) {
                return;
            }

            try {
                const response = await fetch(record.make_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        runs: '1.000000',
                    }),
                });

                if (!response.ok) {
                    this.showToast('error', 'Unable to create make order.');
                    return;
                }

                const data = await response.json();

                if (data?.data?.show_url) {
                    window.location.assign(data.data.show_url);
                }
            } catch (error) {
                this.showToast('error', 'Unable to create make order.');
            }
        },
        async submitDelete() {
            this.isDeleteSubmitting = true;
            this.deleteError = '';

            const endpoint = (this.endpoints.delete || '').replace('{id}', encodeURIComponent(String(this.deleteRecipeId)));

            try {
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    this.deleteError = data.message || 'Unable to archive recipe.';
                    return;
                }

                if (!response.ok) {
                    this.deleteError = 'Unable to archive recipe.';
                    return;
                }

                await this.fetchRecipes();
                await refreshNavigationState(this.navigationStateUrl);
                this.closeDelete();
                this.showToast('success', 'Recipe archived.');
            } catch (error) {
                this.deleteError = 'Unable to archive recipe.';
            } finally {
                this.isDeleteSubmitting = false;
            }
        },
    }));
}
